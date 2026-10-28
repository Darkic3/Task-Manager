<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->merge(['username' => trim((string) $request->input('username'))]);

        $data = $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required',
        ]);

        $key = Str::lower($data['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors([
                'username' => __('Too many login attempts. Please try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ])->onlyInput('username');
        }

        $credentials = ['username' => Str::lower($data['username']), 'password' => $data['password']];
        $authenticated = Auth::attempt($credentials, $request->boolean('remember'));

        // Existing accounts can continue signing in with their email address.
        if (! $authenticated && filter_var($data['username'], FILTER_VALIDATE_EMAIL)) {
            $authenticated = Auth::attempt([
                'email' => $data['username'],
                'password' => $data['password'],
            ], $request->boolean('remember'));
        }

        if ($authenticated) {
            RateLimiter::clear($key);
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($key, 60);

        return back()->withErrors(['username' => __('auth.failed')])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
