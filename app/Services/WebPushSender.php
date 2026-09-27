<?php

namespace App\Services;

use App\Models\Communication;
use App\Models\PushSubscription as StoredPushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushSender
{
    public function send(iterable $users, Communication $communication, string $destination = 'tenant'): int
    {
        if (! $this->configured()) {
            return 0;
        }

        $users = collect($users)->filter(fn ($user) => $user instanceof User)->unique('id');
        $subscriptions = StoredPushSubscription::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => (string) config('webpush.subject'),
                    'publicKey' => (string) config('webpush.public_key'),
                    'privateKey' => (string) config('webpush.private_key'),
                ],
            ], [
                'TTL' => max(60, (int) config('webpush.ttl', 300)),
                'urgency' => $communication->priority === 'critical' ? 'high' : 'normal',
            ], 15);
        } catch (Throwable $exception) {
            Log::error('Configuração de Web Push inválida.', ['exception' => $exception::class]);

            return $subscriptions->count();
        }

        $queued = new Collection;
        $payload = json_encode($this->payloadFor($communication, $destination), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        foreach ($subscriptions as $stored) {
            try {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $stored->endpoint,
                    'publicKey' => $stored->public_key,
                    'authToken' => $stored->auth_token,
                    'contentEncoding' => $stored->content_encoding,
                ]), $payload);
                $queued->put($stored->endpoint_hash, $stored);
            } catch (Throwable $exception) {
                $stored->update(['last_failure_at' => now()]);
                Log::warning('Assinatura Web Push inválida.', [
                    'subscription_id' => $stored->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        $failures = $subscriptions->count() - $queued->count();

        try {
            foreach ($webPush->flush() as $report) {
                $hash = hash_hmac('sha256', $report->getEndpoint(), (string) config('app.key'));
                $stored = $queued->get($hash);
                if (! $stored) {
                    continue;
                }

                if ($report->isSuccess()) {
                    $stored->update(['last_success_at' => now(), 'last_failure_at' => null]);
                } elseif ($report->isSubscriptionExpired()) {
                    $stored->delete();
                    $failures++;
                } else {
                    $stored->update(['last_failure_at' => now()]);
                    $failures++;
                    Log::warning('Falha no envio de Web Push.', ['subscription_id' => $stored->id]);
                }
            }
        } catch (Throwable $exception) {
            $failures += $queued->count();
            Log::error('Falha ao processar o lote de Web Push.', ['exception' => $exception::class]);
        }

        return $failures;
    }

    public function payloadFor(Communication $communication, string $destination = 'tenant'): array
    {
        return [
            'title' => 'Catalog · Nova comunicação',
            'body' => 'Você possui uma nova mensagem segura. Entre na plataforma para consultar.',
            'url' => $destination === 'platform'
                ? route('platform.communications.show', $communication, false)
                : route('admin.communications.show', $communication, false),
            'tag' => 'catalog-secure-communication',
        ];
    }

    private function configured(): bool
    {
        return filled(config('webpush.subject'))
            && filled(config('webpush.public_key'))
            && filled(config('webpush.private_key'));
    }
}
