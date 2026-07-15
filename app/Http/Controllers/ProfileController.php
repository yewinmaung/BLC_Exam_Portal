<?php

namespace App\Http\Controllers;

use App\Jobs\SendPasswordChangedJob;
use App\Jobs\SendProfileOtpJob;
use App\Models\ProfileOtp;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

/**
 * Shared profile controller for Admin, Teacher, and Student roles.
 *
 * Routes:
 *   GET  /profile                → show()          profile.show
 *   POST /profile/photo          → updatePhoto()   profile.photo
 *   POST /profile/otp/request    → otpRequest()    profile.otp.request
 *   POST /profile/otp/verify     → otpVerify()     profile.otp.verify
 *   POST /profile/otp/resend     → otpResend()     profile.otp.resend
 */
class ProfileController extends Controller
{
    public function __construct(private ActivityLogService $activityLog)
    {
    }

    // ── Page ──────────────────────────────────────────────────────────────

    public function show(): \Illuminate\View\View
    {
        $user = auth()->user();

        return view('profile.show', compact('user'));
    }

    // ── Photo upload ──────────────────────────────────────────────────────

    /**
     * Accept a Base64-encoded WebP image (from the canvas cropper),
     * store it on the public disk, update the user record, return the URL.
     *
     * POST /profile/photo  →  { success: true, url: "..." }
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'string'],
        ]);

        $dataUri = $request->input('image');

        // Validate it looks like a data URI
        if (!preg_match('/^data:image\/(webp|jpeg|png);base64,/', $dataUri)) {
            return response()->json(['error' => 'Invalid image format.'], 422);
        }

        // Decode
        $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $dataUri);
        $decoded    = base64_decode($base64Data, strict: true);

        if ($decoded === false || strlen($decoded) < 100) {
            return response()->json(['error' => 'Could not decode image data.'], 422);
        }

        // Size guard (2 MB)
        if (strlen($decoded) > 2 * 1024 * 1024) {
            return response()->json(['error' => 'Image exceeds 2 MB limit.'], 422);
        }

        $user = auth()->user();

        // Delete old photo if any
        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        // Store under avatars/{userId}.webp
        $path = 'avatars/' . $user->id . '.webp';
        Storage::disk('public')->put($path, $decoded);

        $user->update(['profile_photo' => $path]);

        $this->activityLog->log('profile_photo_updated', 'Updated profile photo', $user);

        return response()->json([
            'success' => true,
            'url'     => Storage::disk('public')->url($path) . '?t=' . time(),
        ]);
    }

    // ── OTP: Step 1 — request (send code) ────────────────────────────────

    /**
     * Validate the new password, generate an OTP, and dispatch the email.
     *
     * POST /profile/otp/request
     * Body: { password, password_confirmation }
     * Response: { sent: true }  |  422 with errors
     */
    public function otpRequest(Request $request): JsonResponse
    {
        $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers(),
            ],
        ]);

        $user            = auth()->user();
        $newPasswordHash = Hash::make($request->input('password'));

        [, $plainCode] = ProfileOtp::generate($user, $newPasswordHash);

        SendProfileOtpJob::dispatch($user->id, $plainCode);

        $this->activityLog->log('profile_otp_requested', 'OTP requested for password change', $user);

        return response()->json(['sent' => true]);
    }

    // ── OTP: Step 2 — verify and apply ───────────────────────────────────

    /**
     * Verify the 6-digit code, apply the new password, and send confirmation.
     *
     * POST /profile/otp/verify
     * Body: { code }
     * Response: { success: true }  |  422 / 429 with error
     */
    public function otpVerify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        $user = auth()->user();
        $otp  = ProfileOtp::latestForUser($user->id);

        if (!$otp) {
            return response()->json(['error' => 'No active verification code. Please request a new one.'], 422);
        }

        if (!$otp->isValid()) {
            return response()->json(['error' => 'This code has expired or been used. Please request a new one.'], 422);
        }

        // Increment attempts before checking, to prevent timing attacks from revealing validity
        $otp->increment('attempts');

        if ($otp->attempts > 5) {
            return response()->json(['error' => 'Too many incorrect attempts. Please request a new code.'], 429);
        }

        if (!$otp->checkCode($request->input('code'))) {
            $remaining = 5 - $otp->attempts;
            $msg = $remaining > 0
                ? "Incorrect code. {$remaining} attempt(s) remaining."
                : 'Too many incorrect attempts. Please request a new code.';
            return response()->json(['error' => $msg], 422);
        }

        // ── Code is correct — apply the password change ──
        $user->update(['password' => $otp->new_password_hash]);
        $otp->update(['used_at' => now()]);

        SendPasswordChangedJob::dispatch($user->id);

        $this->activityLog->log('profile_password_changed', 'Password changed via OTP verification', $user);

        return response()->json(['success' => true]);
    }

    // ── OTP: Resend ───────────────────────────────────────────────────────

    /**
     * Regenerate a new OTP using the same pending password hash.
     * Enforces a 60-second cooldown based on the previous OTP's created_at.
     *
     * POST /profile/otp/resend
     * Response: { sent: true }  |  422 with error
     */
    public function otpResend(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Look for any OTP (including expired) to get the password hash
        $previous = ProfileOtp::where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (!$previous) {
            return response()->json(['error' => 'No pending password change. Please start over.'], 422);
        }

        // Enforce 60-second cooldown
        if ($previous->created_at->diffInSeconds(now()) < 60) {
            $wait = 60 - (int) $previous->created_at->diffInSeconds(now());
            return response()->json(['error' => "Please wait {$wait} seconds before resending."], 422);
        }

        $newPasswordHash = $previous->new_password_hash;

        // Invalidate old OTP and generate fresh one
        ProfileOtp::where('user_id', $user->id)->whereNull('used_at')->delete();

        $newOtp = ProfileOtp::create([
            'user_id'           => $user->id,
            'code_hash'         => \Illuminate\Support\Facades\Hash::make($plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)),
            'new_password_hash' => $newPasswordHash,
            'attempts'          => 0,
            'expires_at'        => now()->addMinutes(5),
        ]);

        SendProfileOtpJob::dispatch($user->id, $plainCode);

        $this->activityLog->log('profile_otp_resent', 'OTP resent for password change', $user);

        return response()->json(['sent' => true]);
    }
}
