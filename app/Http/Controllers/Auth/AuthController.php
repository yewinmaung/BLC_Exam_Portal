<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(private ActivityLogService $activityLog)
    {
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return back()->withErrors(['email' => 'Your account is deactivated.']);
        }

        $user->update(['last_login_at' => now()]);
        $this->activityLog->log('login', 'User logged in');
        $request->session()->regenerate();

        return redirect()->intended($this->dashboardRoute($user));
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

    private function dashboardRoute(User $user): string
    {
        return match (true) {
            $user->isAdmin() => route('admin.dashboard'),
            $user->isTeacher() => route('teacher.dashboard'),
            default => route('student.dashboard'),
        };
    }
}
