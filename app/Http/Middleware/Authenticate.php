<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     * Blocks terminated (is_active = false) users immediately after auth check.
     */
    public function handle($request, \Closure $next, ...$guards)
    {
        // Run the standard authentication check first
        $this->authenticate($request, $guards);

        // After authentication, block suspended accounts
        $user = auth()->user();
        if ($user && !$user->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been suspended. Please contact the administrator.']);
        }

        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when not authenticated.
     * Must match parent signature exactly (no type hints) for PHP 8.2 compatibility.
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
