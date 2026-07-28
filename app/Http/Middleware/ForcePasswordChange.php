<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ForcePasswordChange middleware.
 *
 * After a user with force_password_change = true logs in, every request
 * (except the change-password route itself and logout) is intercepted and
 * redirected to the password change page.
 *
 * Excluded from interception:
 *  - GET/POST /password/change  (the force-change page itself)
 *  - POST /logout
 *
 * Does NOT affect admin accounts — admins can manage others and are never
 * forced through this flow even if the flag is somehow set.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::user();

        // Only apply to authenticated, non-admin users with the flag set
        if (
            $user
            && !$user->isAdmin()
            && $user->force_password_change
            && !$request->routeIs('password.force-change', 'password.force-change.update', 'logout')
        ) {
            return redirect()->route('password.force-change');
        }

        return $next($request);
    }
}
