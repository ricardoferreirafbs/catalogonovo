<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MfaChallengeController extends Controller
{
    public function create(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        if (! $request->user()->two_factor_confirmed_at) {
            return redirect()->route('platform.mfa.setup');
        }

        return view('auth.mfa-challenge');
    }

    public function store(Request $request, TotpService $totp)
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdmin() && $user->two_factor_confirmed_at, 403);

        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $valid = $totp->verify((string) $user->two_factor_secret, $data['code']);

        if (! $valid) {
            $codes = $user->two_factor_recovery_codes ?? [];
            $index = $totp->recoveryCodeIndex($codes, $data['code']);

            if ($index !== null) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                $valid = true;
            }
        }

        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'Código de autenticação inválido.']);
        }

        $request->session()->regenerate();
        $request->session()->put('mfa_verified_user_id', $user->id);

        return redirect()->intended(route('platform.dashboard'));
    }
}
