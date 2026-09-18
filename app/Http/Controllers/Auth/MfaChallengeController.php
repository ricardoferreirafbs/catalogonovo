<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);

        $valid = DB::transaction(function () use ($request, $totp, $data): bool {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($user->isSuperAdmin() && $user->two_factor_confirmed_at, 403);

            $counter = $totp->matchingCounter((string) $user->two_factor_secret, $data['code']);

            if ($counter !== null) {
                if ($user->two_factor_last_used_counter !== null && $counter <= $user->two_factor_last_used_counter) {
                    return false;
                }

                $user->forceFill(['two_factor_last_used_counter' => $counter])->save();

                return true;
            }

            $codes = $user->two_factor_recovery_codes ?? [];
            $index = $totp->recoveryCodeIndex($codes, $data['code']);

            if ($index === null) {
                return false;
            }

            unset($codes[$index]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

            return true;
        });

        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'Código de autenticação inválido.']);
        }

        $request->session()->regenerate();
        $request->session()->put('mfa_verified_user_id', $request->user()->id);

        return redirect()->intended(route('platform.dashboard'));
    }
}
