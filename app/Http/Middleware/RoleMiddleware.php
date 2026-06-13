<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (!$user || !$user->role) {
            abort(403, 'Unauthorized access.');
        }

        if (!in_array($user->role->slug, $roles, true)) {
            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
