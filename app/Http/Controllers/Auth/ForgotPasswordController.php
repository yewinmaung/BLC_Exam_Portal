<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordChangedJob;
use App\Jobs\SendProfileOtpJob;
use App\Models\ProfileOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Forgot Password — two-step OTP flow.
 *
 * Routes (guest middleware):
 *   GET  /forgot-password              showEmailForm()   – enter email
 *   POST /forgot-password/send         sendOtp()         – send OTP email
 *   GET  /forgot-password/verify       showVerifyForm()  – show Step 1 (OTP) or Step 2 (new password)
 *   POST /forgot-password/check-otp    checkOtp()        – verify code only; reveal password step on success
 *   POST /forgot-password/verify       resetPassword()   – apply new password (requires verified session flag)
 *   POST /forgot-password/resend       resendOtp()       – resend with 60 s cooldown
 *
 * Session keys used:
 *   fp_user_id      – ID of the user going through the reset
 *   fp_otp_verified – set to true once the correct OTP is entered
 */
class ForgotPasswordController extends Controller
{
    // ── Step 1: Show email entry form ──────────────────────────────────────

    public function showEmailForm()
    {
        return view('auth.forgot-password');
    }

    // ── Step 2: Find user, generate OTP, dispatch email ───────────────────

    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if ($user && $user->is_active) {
            [$otp, $plainCode] = ProfileOtp::generate($user, '');
            // dispatch_sync sends immediately — no queue worker needed.
            dispatch_sync(new SendProfileOtpJob($user->id, $plainCode));
            $request->session()->put('fp_user_id', $user->id);
        }

        // Always redirect to verify — enumerate-safe.
        $request->session()->forget('fp_otp_verified');

        return redirect()->route('forgot-password.verify')
            ->with('info', 'If that email is registered and active, a 6-digit code has been sent.');
    }

    // ── Step 3: Show verify page (OTP step or password step) ──────────────

    public function showVerifyForm(Request $request)
    {
        if (!$request->session()->has('fp_user_id')) {
            return redirect()->route('forgot-password')
                ->with('info', 'Please enter your email address first.');
        }

        $user        = User::find($request->session()->get('fp_user_id'));
        $otpVerified = (bool) $request->session()->get('fp_otp_verified', false);

        // Get OTP expiration time for countdown
        $otpExpiration = null;
        if (!$otpVerified) {
            $otp = ProfileOtp::latestForUser($user->id);
            if ($otp) {
                $otpExpiration = $otp->expires_at->timestamp;
            } else {
                // Fallback: assume OTP was just created (5 minutes from now)
                $otpExpiration = now()->addMinutes(5)->timestamp;
            }
        }

        return view('auth.forgot-password-verify', compact('user', 'otpVerified', 'otpExpiration'));
    }

    // ── Step 4a: Verify OTP only — reveal password fields on success ───────

    public function checkOtp(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $userId = $request->session()->get('fp_user_id');

        if (!$userId) {
            return back()->withErrors(['otp' => 'Session expired. Please start over.']);
        }

        $otp = ProfileOtp::latestForUser($userId);

        // Priority 1: Check if OTP exists
        if (!$otp) {
            return back()->withErrors(['otp' => 'No OTP found. Please request a new code.']);
        }

        // ── BEFORE-VALIDATION LOG ─────────────────────────────────────────
        \Log::info('[OTP CHECK] Before validation', [
            'otp_id'       => $otp->id,
            'user_id'      => $otp->user_id,
            'created_at'   => $otp->created_at->toDateTimeString(),
            'expires_at'   => $otp->expires_at->toDateTimeString(),
            'attempts'     => $otp->attempts,
            'used_at'      => $otp->used_at?->toDateTimeString(),
            'server_now'   => now()->toDateTimeString(),
            'is_past'      => $otp->expires_at->isPast(),
            'seconds_left' => $otp->expires_at->diffInSeconds(now(), false),
        ]);
        // ─────────────────────────────────────────────────────────────────

        // Priority 2: Check if OTP already used
        if (!is_null($otp->used_at)) {
            return back()->withErrors(['otp' => 'This OTP has already been used. Please request a new code.']);
        }

        // Priority 3: Check if OTP expired (5 minutes from generation)
        if ($otp->expires_at->isPast()) {
            return back()->withErrors(['otp' => 'The OTP has expired. Please request a new code.']);
        }

        // Priority 4: Check if already reached maximum attempts (3 attempts)
        if ($otp->attempts >= 3) {
            return back()->withErrors(['otp' => 'Too many incorrect attempts. Please request a new OTP.']);
        }

        // Priority 5: Check if the OTP code is correct
        if (!$otp->checkCode($request->input('otp'))) {
            // Increment attempts only on wrong code
            $otp->increment('attempts');

            // ── AFTER-INCREMENT LOG ───────────────────────────────────────
            $otpAfter = $otp->fresh();
            \Log::info('[OTP CHECK] After wrong attempt increment', [
                'otp_id'     => $otpAfter->id,
                'attempts'   => $otpAfter->attempts,
                'expires_at' => $otpAfter->expires_at->toDateTimeString(),
                'used_at'    => $otpAfter->used_at?->toDateTimeString(),
            ]);
            // ─────────────────────────────────────────────────────────────

            if ($otpAfter->attempts >= 3) {
                return back()->withErrors(['otp' => 'Too many incorrect attempts. Please request a new OTP.']);
            }

            $remaining = 3 - $otpAfter->attempts;
            $attemptWord = $remaining === 1 ? 'attempt' : 'attempts';
            return back()->withErrors(['otp' => "Incorrect OTP. {$remaining} {$attemptWord} remaining."]);
        }

        // Priority 6: OTP is correct — mark it as used and set the session flag
        $otp->update(['used_at' => now()]);
        $request->session()->put('fp_otp_verified', true);

        return redirect()->route('forgot-password.verify');
    }

    // ── Step 4b: Apply new password (only after OTP verified) ─────────────

    public function resetPassword(Request $request)
    {
        // Guard: must have completed OTP step first
        if (!$request->session()->get('fp_otp_verified')) {
            return redirect()->route('forgot-password.verify');
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $userId = $request->session()->get('fp_user_id');

        if (!$userId) {
            return redirect()->route('forgot-password')
                ->withErrors(['password' => 'Session expired. Please start over.']);
        }

        $user = User::findOrFail($userId);
        $user->update(['password' => Hash::make($request->input('password'))]);

        // Send confirmation email (queued — timing is not critical here)
        SendPasswordChangedJob::dispatch($user->id);

        // Clear all reset-related session data
        $request->session()->forget(['fp_user_id', 'fp_otp_verified']);

        return redirect()->route('login')
            ->with('success', 'Password reset successfully. You can now sign in with your new password.');
    }

    // ── Step 5: Resend OTP (60-second cooldown) ───────────────────────────

    public function resendOtp(Request $request)
    {
        $userId = $request->session()->get('fp_user_id');

        if (!$userId) {
            return back()->withErrors(['otp' => 'Session expired. Please start over.']);
        }

        $user = User::find($userId);

        if (!$user || !$user->is_active) {
            return back()->withErrors(['otp' => 'Unable to resend code. Please start over.']);
        }

        // Check latest OTP status
        $latest = ProfileOtp::where('user_id', $userId)->latest()->first();

        // Allow immediate resend if OTP is expired, used, or max attempts reached
        $canResendImmediately = !$latest 
            || !is_null($latest->used_at)
            || $latest->expires_at->isPast()
            || $latest->attempts >= 3;

        // Enforce 60-second cooldown only for valid OTPs
        if (!$canResendImmediately && $latest->created_at->diffInSeconds(now()) < 60) {
            $wait = 60 - (int) $latest->created_at->diffInSeconds(now());
            return back()->withErrors(['otp' => "Please wait {$wait} second(s) before requesting a new code."]);
        }

        [$otp, $plainCode] = ProfileOtp::generate($user, '');
        dispatch_sync(new SendProfileOtpJob($user->id, $plainCode));

        // Reset verification flag so they must re-enter the new code
        $request->session()->forget('fp_otp_verified');

        // Redirect to the verify page to reload with fresh OTP expiration time
        return redirect()->route('forgot-password.verify')
            ->with('info', 'A new code has been sent to your email.');
    }
}
