<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EnsureSingleExamSession
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (!$user || !$user->isStudent()) {
            return $next($request);
        }

        if (!$user->exam_session_token) {
            $user->update(['exam_session_token' => Str::random(60)]);
        }

        $sessionToken = $request->session()->get('exam_session_token');

        if ($sessionToken && $sessionToken !== $user->exam_session_token) {
            auth()->logout();
            $request->session()->invalidate();

            return redirect()->route('login')
                ->withErrors(['email' => 'Another active exam session was detected.']);
        }

        if (!$sessionToken) {
            $request->session()->put('exam_session_token', $user->exam_session_token);
        }

        return $next($request);
    }
}
