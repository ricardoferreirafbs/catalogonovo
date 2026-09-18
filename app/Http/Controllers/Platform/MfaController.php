<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MfaController extends Controller
{
    public function create(Request $request, TotpService $totp)
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at && (int) $request->session()->get('mfa_verified_user_id') !== $user->id) {
            return redirect()->route('mfa.challenge');
        }

        if (! $user->two_factor_secret) {
            $user->forceFill([
                'two_factor_secret' => $totp->generateSecret(),
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();
        }

        return view('platform.mfa', [
            'user' => $user->fresh(),
            'provisioningUri' => $totp->provisioningUri((string) $user->two_factor_secret, $user->email),
        ]);
    }

    public function confirm(Request $request, TotpService $totp)
    {
        $user = $request->user();
        abort_if($user->two_factor_confirmed_at, 409, 'A autenticação em dois fatores já está ativa.');

        $data = $request->validate(['code' => ['required', 'digits:6']]);

        if (! $totp->verify((string) $user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'O código informado não é válido. Confira o horário do celular e tente novamente.']);
        }

        $plainCodes = $totp->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => array_map($totp->hashRecoveryCode(...), $plainCodes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->regenerate();
        $request->session()->put('mfa_verified_user_id', $user->id);

        return redirect()->route('platform.mfa.setup')
            ->with('success', 'Autenticação em dois fatores ativada.')
            ->with('recovery_codes', $plainCodes);
    }

    public function regenerateRecoveryCodes(Request $request, TotpService $totp)
    {
        $user = $request->user();
        $data = $request->validate([
            'password' => ['required', 'current_password'],
            'code' => ['required', 'digits:6'],
        ]);

        if (! $totp->verify((string) $user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Não foi possível confirmar a identidade.']);
        }

        $plainCodes = $totp->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => array_map($totp->hashRecoveryCode(...), $plainCodes),
        ])->save();

        return back()
            ->with('success', 'Novos códigos de recuperação gerados. Os anteriores foram invalidados.')
            ->with('recovery_codes', $plainCodes);
    }
}
