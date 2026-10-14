<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerMfaRecoveryService
{
    public const IDENTITY_METHODS = [
        'confirmed_email_reply' => 'Resposta confirmada pelo e-mail cadastrado',
        'verified_phone_call' => 'Contato telefônico previamente cadastrado',
        'contractual_contact' => 'Validação pelo contato contratual',
    ];

    public function __construct(private readonly TotpService $totp) {}

    /**
     * @return array{owner: User, sessions_revoked: int, push_subscriptions_revoked: int}
     */
    public function recover(User $actor, Tenant $tenant, User $owner, string $totpCode): array
    {
        return DB::transaction(function () use ($actor, $tenant, $owner, $totpCode): array {
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->id);

            abort_unless($lockedActor->isSuperAdmin() && $lockedActor->two_factor_confirmed_at, 403);

            $counter = $this->totp->matchingCounter(
                (string) $lockedActor->two_factor_secret,
                $totpCode
            );

            if ($counter === null
                || ($lockedActor->two_factor_last_used_counter !== null
                    && $counter <= $lockedActor->two_factor_last_used_counter)) {
                throw ValidationException::withMessages([
                    'code' => 'O código do Superadmin é inválido ou já foi utilizado. Aguarde um novo código e tente novamente.',
                ]);
            }

            $lockedOwner = User::query()
                ->whereKey($owner->id)
                ->where('tenant_id', $tenant->id)
                ->where('role', 'owner')
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $lockedOwner->two_factor_confirmed_at,
                409,
                'Este Proprietário não possui MFA ativo para recuperar.'
            );

            $sessionsRevoked = DB::table('sessions')->where('user_id', $lockedOwner->id)->count();
            $pushSubscriptionsRevoked = $lockedOwner->pushSubscriptions()->count();

            DB::table('sessions')->where('user_id', $lockedOwner->id)->delete();
            Password::deleteToken($lockedOwner);
            $lockedOwner->pushSubscriptions()->delete();

            $lockedOwner->forceFill([
                'remember_token' => Str::random(60),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_used_counter' => null,
            ])->save();

            $lockedActor->forceFill([
                'two_factor_last_used_counter' => $counter,
            ])->save();

            return [
                'owner' => $lockedOwner,
                'sessions_revoked' => $sessionsRevoked,
                'push_subscriptions_revoked' => $pushSubscriptionsRevoked,
            ];
        });
    }
}
