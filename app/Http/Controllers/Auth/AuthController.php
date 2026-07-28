<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLog,
        private EmailService $emailService
    ) {
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // ── 1. Find user by email (without authenticating yet) ────────────
        $user = User::where('email', $credentials['email'])->first();

        // ── 2. Account lock check (by email lookup before Auth::attempt) ──
        if ($user && $user->isLocked()) {
            $seconds = (int) now()->diffInSeconds($user->locked_until, false);
            return back()
                ->withErrors(['email' => 'Account locked.'])
                ->with('locked_until', $user->locked_until->toIso8601String())
                ->with('hide_forgot_password', $user->force_password_change)
                ->onlyInput('email');
        }

        // ── 3. Attempt authentication ─────────────────────────────────────
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            // If user exists, track the failed attempt
            if ($user) {
                $user->incrementFailedLogins();
                $user->refresh();

                if ($user->isLocked()) {
                    // Just got locked on this attempt
                    $this->activityLog->log('login_locked',
                        "Account locked after {$user->failed_login_attempts} failed attempts: {$user->email}");
                    return back()
                        ->withErrors(['email' => 'Too many failed attempts. Account locked for 10 minutes.'])
                        ->with('locked_until', $user->locked_until->toIso8601String())
                        ->with('hide_forgot_password', $user->force_password_change)
                        ->onlyInput('email');
                }

                $remaining = $user->remainingLoginAttempts();
                return back()
                    ->withErrors(['email' => "Invalid credentials. {$remaining} attempt(s) remaining."])
                    ->with('hide_forgot_password', $user->force_password_change)
                    ->onlyInput('email');
            }

            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
        }

        // ── 4. Authentication succeeded — get the now-authenticated user ──
        $user = Auth::user();

        // ── 5. Active check ───────────────────────────────────────────────
        if (!$user->is_active) {
            Auth::logout();
            return back()->withErrors(['email' => 'Your account is deactivated.']);
        }

        // ── 6. Temporary password expiry check ───────────────────────────
        if ($user->isTemporaryPasswordExpired()) {
            Auth::logout();
            return back()
                ->withErrors(['email' => 'Temporary password expired.'])
                ->with('temp_expired_email', $user->email)
                ->with('hide_forgot_password', true)
                ->onlyInput('email');
        }

        // ── 7. Success — reset counters, update last login ────────────────
        $user->resetFailedLogins();
        $user->update(['last_login_at' => now()]);
        $this->activityLog->log('login', 'User logged in');
        $request->session()->regenerate();

        return redirect()->intended($this->dashboardRoute($user));
    }

    /**
     * Request a new temporary password when the existing one has expired.
     *
     * Guards (in order):
     *  1. force_password_change must be true — only temp-password accounts qualify
     *  2. Account must NOT be locked — must wait for lock to expire first
     *  3. 60-second cooldown — prevents email spam (canRequestNewTempPassword())
     *
     * On success:
     *  - Generates new temporary password
     *  - Hashes and saves it
     *  - Resets failed_login_attempts and locked_until
     *  - Sets new temporary_password_expires_at (+24 hours)
     *  - Stamps temp_password_last_requested_at (for cooldown)
     *  - Dispatches SendNewTemporaryPasswordJob on 'emails' queue
     *  - Logs attempt and success
     *
     * Route: POST /login/request-new-password  (guest middleware)
     */
    public function requestNewTemporaryPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->input('email');
        $user  = User::where('email', $email)->first();

        // ── Log every attempt (regardless of outcome) ─────────────────────
        $this->activityLog->log(
            'temp_password_request_attempt',
            "Temporary password request attempted for: {$email}"
        );

        // ── Guard: user must exist and be in force_password_change state ──
        // Return the same success message on all failure paths to prevent
        // email enumeration — only the logs reveal the real outcome.
        if (!$user || !$user->force_password_change) {
            return back()->with('success',
                'If a matching account exists, a new temporary password has been sent.');
        }

        // ── Guard: account must NOT be currently locked ────────────────────
        if ($user->isLocked()) {
            $minutesLeft = (int) ceil(now()->diffInSeconds($user->locked_until) / 60);
            return back()->withErrors([
                'email' => "Account is locked. Please wait {$minutesLeft} minute(s) before requesting a new password.",
            ])->onlyInput('email');
        }

        // ── Guard: 60-second resend cooldown ─────────────────────────────
        if (!$user->canRequestNewTempPassword()) {
            $wait = $user->tempPasswordCooldownSecondsRemaining();
            return back()->withErrors([
                'email' => "Please wait {$wait} second(s) before requesting another temporary password.",
            ])->onlyInput('email');
        }

        // ── Generate and save new temporary password ──────────────────────
        $temporaryPassword = $this->generateTemporaryPassword();
        $expiresAt         = now()->addHours(User::TEMP_PASSWORD_EXPIRY_HOURS);

        $user->update([
            'password'                       => Hash::make($temporaryPassword),
            'failed_login_attempts'          => 0,
            'locked_until'                   => null,
            'temporary_password_expires_at'  => $expiresAt,
            'temp_password_last_requested_at'=> now(),
        ]);

        // ── Dispatch new-temporary-password email job ─────────────────────
        \App\Jobs\SendNewTemporaryPasswordJob::dispatch(
            $user->id,
            $temporaryPassword,
            $expiresAt->format('d M Y, H:i')   // human-readable expiry for the email
        );

        // ── Log success ───────────────────────────────────────────────────
        $this->activityLog->log(
            'temp_password_reissued',
            "New temporary password successfully issued for: {$user->email}"
        );

        return back()->with('success',
            'A new temporary password has been sent to your email address. It expires in 24 hours.');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'academic_year' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $studentRole = Role::where('slug', RoleSlug::STUDENT)->firstOrFail();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $studentRole->id,
            'academic_year' => $data['academic_year'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('student.dashboard');
    }

    public function logout(Request $request)
    {
        $this->activityLog->log('logout', 'User logged out');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // ── Force Password Change ─────────────────────────────────────────

    /**
     * Show the force-password-change page.
     * Only reachable when force_password_change = true (middleware enforces it).
     */
    public function showForcePasswordChange()
    {
        return view('auth.force-password-change');
    }

    /**
     * Handle the forced password update.
     * Clears the flag so the user reaches their dashboard on next request.
     */
    public function updateForcePasswordChange(Request $request)
    {
        $request->validate([
            'password' => ['required', 'confirmed', 'min:8', 'different:current_password'],
            'current_password' => ['nullable'], // present for the 'different' rule reference only
        ]);

        $user = Auth::user();

        $user->update([
            'password'                      => Hash::make($request->input('password')),
            'force_password_change'         => false,
            'temporary_password_expires_at' => null,
            'failed_login_attempts'         => 0,
            'locked_until'                  => null,
        ]);

        $this->activityLog->log('forced_password_changed', 'User changed temporary password');

        return redirect()->intended($this->dashboardRoute($user))
            ->with('success', 'Password updated successfully. Welcome!');
    }

    private function dashboardRoute(User $user): string
    {
        return match (true) {
            $user->isAdmin() => route('admin.dashboard'),
            $user->isTeacher() => route('teacher.dashboard'),
            default => route('student.dashboard'),
        };
    }

    /**
     * Generate a cryptographically random 12-character temporary password.
     * 3 uppercase + 3 lowercase + 3 digits + 3 symbols, Fisher-Yates shuffled.
     */
    private function generateTemporaryPassword(): string
    {
        $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower   = 'abcdefghjkmnpqrstuvwxyz';
        $digits  = '23456789';
        $symbols = '!@#$%&*';

        $pick = function (string $charset, int $count): string {
            $result = '';
            $len    = strlen($charset);
            for ($i = 0; $i < $count; $i++) {
                $result .= $charset[random_int(0, $len - 1)];
            }
            return $result;
        };

        $raw   = $pick($upper, 3) . $pick($lower, 3) . $pick($digits, 3) . $pick($symbols, 3);
        $chars = str_split($raw);

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j             = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
