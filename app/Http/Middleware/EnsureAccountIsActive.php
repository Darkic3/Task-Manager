<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspended or soft-deleted accounts lose access immediately, even when
 * their session is still alive: the account is logged out and sent back to
 * the login screen with a clear reason.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->status() === 'active') {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = $user->status() === 'deleted'
            ? __('This account has been deleted. Please contact the administrator.')
            : __('This account has been suspended. Please contact the administrator.');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('login')->withErrors(['username' => $message]);
    }
}
