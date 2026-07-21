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

        return view('auth.forgot-password-verify', compact('user', 'otpVerified'));
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

        if (!$otp || !$otp->isValid()) {
            return back()->withErrors(['otp' => 'The code has expired or is no longer valid. Please request a new one.']);
        }

        // Increment attempts before checking to prevent timing oracle
        $otp->increment('attempts');

        if (!$otp->checkCode($request->input('otp'))) {
            $freshAttempts = $otp->fresh()->attempts;
            if ($freshAttempts >= 5) {
                return back()->withErrors(['otp' => 'Too many incorrect attempts. Please request a new code.']);
            }
            $remaining = 5 - $freshAttempts;
            return back()->withErrors(['otp' => "Incorrect code. {$remaining} attempt(s) remaining."]);
        }

        // Code is correct — mark it as used and set the session flag
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

        // Enforce 60-second cooldown
        $latest = ProfileOtp::where('user_id', $userId)->latest()->first();

        if ($latest && $latest->created_at->diffInSeconds(now()) < 60) {
            $wait = 60 - (int) $latest->created_at->diffInSeconds(now());
            return back()->withErrors(['otp' => "Please wait {$wait} second(s) before requesting a new code."]);
        }

        [$otp, $plainCode] = ProfileOtp::generate($user, '');
        dispatch_sync(new SendProfileOtpJob($user->id, $plainCode));

        // Reset verification flag so they must re-enter the new code
        $request->session()->forget('fp_otp_verified');

        return back()->with('info', 'A new code has been sent to your email.');
    }
}
