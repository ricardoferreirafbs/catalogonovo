<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'E-mail ou senha inválidos.'])->onlyInput('email');
        }

        $user = Auth::user();

        if (! $user->isSuperAdmin() && $user->tenant?->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Esta empresa está suspensa ou não está disponível.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->session()->forget('mfa_verified_user_id');

        if ($user->two_factor_confirmed_at) {
            return redirect()->route('mfa.challenge');
        }

        if ($user->requiresMfa()) {
            return redirect()->route($user->isSuperAdmin() ? 'platform.mfa.setup' : 'admin.mfa.setup');
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
