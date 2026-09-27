<?php

namespace App\Services;

use App\Models\Communication;
use App\Models\CommunicationRecipient;
use App\Notifications\SecureCommunicationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommunicationPublisher
{
    public function publish(Communication $communication): int
    {
        $recipientIds = DB::transaction(function () use ($communication): array {
            $locked = Communication::query()->lockForUpdate()->findOrFail($communication->id);

            if ($locked->status === 'published' || $locked->status === 'closed') {
                return [];
            }

            $now = now();
            $locked->update([
                'status' => 'published',
                'published_at' => $locked->published_at ?? $now,
                'expires_at' => $now->copy()->addDays(max(1, (int) config('communication.retention_days', 60))),
            ]);

            $users = $locked->tenant->users()
                ->whereIn('role', $locked->recipient_roles)
                ->whereNotNull('invitation_accepted_at')
                ->get();

            foreach ($users as $user) {
                $locked->recipients()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['delivered_at' => $now],
                );
            }

            return $users->pluck('id')->all();
        });

        $communication->refresh()->load('recipients.user');
        $failures = 0;

        foreach ($communication->recipients->whereIn('user_id', $recipientIds) as $recipient) {
            try {
                $recipient->user->notify(new SecureCommunicationNotification($communication));
                $recipient->update(['email_sent_at' => now(), 'email_failed_at' => null]);
            } catch (Throwable $exception) {
                $failures++;
                $recipient->update(['email_failed_at' => now()]);
                Log::error('Falha ao avisar destinatário de uma comunicação segura.', [
                    'protocol' => $communication->protocol,
                    'recipient_id' => $recipient->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $failures;
    }

    public function notifyTenantRecipients(Communication $communication, bool $isReply = true): int
    {
        $failures = 0;
        $communication->loadMissing('recipients.user');

        foreach ($communication->recipients as $recipient) {
            if (! $recipient->user
                || $recipient->user->tenant_id !== $communication->tenant_id
                || ! in_array($recipient->user->role, $communication->recipient_roles, true)) {
                continue;
            }

            try {
                $recipient->user->notify(new SecureCommunicationNotification($communication, 'tenant', $isReply));
                $recipient->update(['email_sent_at' => now(), 'email_failed_at' => null]);
            } catch (Throwable $exception) {
                $failures++;
                $recipient->update(['email_failed_at' => now()]);
                Log::error('Falha ao avisar resposta em comunicação segura.', [
                    'protocol' => $communication->protocol,
                    'recipient_id' => $recipient->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $failures;
    }

    public function notifyPlatform(Communication $communication): int
    {
        $failures = 0;
        $admins = \App\Models\User::query()->whereNull('tenant_id')->where('role', 'superadmin')->get();

        foreach ($admins as $admin) {
            try {
                $admin->notify(new SecureCommunicationNotification($communication, 'platform', true));
            } catch (Throwable $exception) {
                $failures++;
                Log::error('Falha ao avisar a plataforma sobre resposta de cliente.', [
                    'protocol' => $communication->protocol,
                    'admin_id' => $admin->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $failures;
    }
}
