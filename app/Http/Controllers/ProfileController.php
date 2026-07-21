<?php

namespace App\Http\Controllers;

use App\Jobs\SendPasswordChangedJob;
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
 *   POST /profile/photo          → updatePhoto()      profile.photo
 *   POST /profile/password       → changePassword()   profile.password
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

    // ── Password change ───────────────────────────────────────────────────

    /**
     * Validate and apply a new password for the authenticated user.
     *
     * POST /profile/password
     * Body: { password, password_confirmation }
     * Response: { success: true }  |  422 with errors
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers(),
            ],
        ]);

        $user = auth()->user();
        $user->update(['password' => Hash::make($request->input('password'))]);

        SendPasswordChangedJob::dispatch($user->id);

        $this->activityLog->log('profile_password_changed', 'Password changed from profile', $user);

        return response()->json(['success' => true]);
    }
}
