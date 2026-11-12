<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended('/');
        }

        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $throttleKey = strtolower($credentials['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->intended('/')->with('success', 'Logged in successfully.');
        }

        RateLimiter::hit($throttleKey, 60);

        throw ValidationException::withMessages([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Logged out successfully.');
    }
}
