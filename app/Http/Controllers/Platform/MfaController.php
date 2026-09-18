<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'two_factor_last_used_counter' => null,
            ])->save();
        }

        $provisioningUri = $totp->provisioningUri((string) $user->two_factor_secret, $user->email);

        return view('platform.mfa', [
            'user' => $user->fresh(),
            'provisioningUri' => $provisioningUri,
            'qrCodeDataUri' => $totp->qrCodeDataUri($provisioningUri),
        ]);
    }

    public function confirm(Request $request, TotpService $totp)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $plainCodes = $totp->generateRecoveryCodes();

        DB::transaction(function () use ($request, $totp, $data, $plainCodes): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_if($user->two_factor_confirmed_at, 409, 'A autenticação em dois fatores já está ativa.');

            $counter = $totp->matchingCounter((string) $user->two_factor_secret, $data['code']);

            if ($counter === null) {
                throw ValidationException::withMessages(['code' => 'O código informado não é válido. Confira o horário do celular e tente novamente.']);
            }

            $user->forceFill([
                'two_factor_recovery_codes' => array_map($totp->hashRecoveryCode(...), $plainCodes),
                'two_factor_confirmed_at' => now(),
                'two_factor_last_used_counter' => $counter,
            ])->save();
        });

        $request->session()->regenerate();
        $request->session()->put('mfa_verified_user_id', $request->user()->id);

        return redirect()->route('platform.mfa.setup')
            ->with('success', 'Autenticação em dois fatores ativada.')
            ->with('recovery_codes', $plainCodes);
    }

    public function regenerateRecoveryCodes(Request $request, TotpService $totp)
    {
        $data = $request->validate([
            'password' => ['required', 'current_password'],
            'code' => ['required', 'digits:6'],
        ]);
        $plainCodes = $totp->generateRecoveryCodes();

        DB::transaction(function () use ($request, $totp, $data, $plainCodes): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $counter = $totp->matchingCounter((string) $user->two_factor_secret, $data['code']);

            if ($counter === null || ($user->two_factor_last_used_counter !== null && $counter <= $user->two_factor_last_used_counter)) {
                throw ValidationException::withMessages(['code' => 'Não foi possível confirmar a identidade.']);
            }

            $user->forceFill([
                'two_factor_recovery_codes' => array_map($totp->hashRecoveryCode(...), $plainCodes),
                'two_factor_last_used_counter' => $counter,
            ])->save();
        });

        return back()
            ->with('success', 'Novos códigos de recuperação gerados. Os anteriores foram invalidados.')
            ->with('recovery_codes', $plainCodes);
    }
}
