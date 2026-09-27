<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(filled(config('webpush.public_key')) && filled(config('webpush.private_key')), 503, 'Web Push não configurado.');

        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:512'],
            'contentEncoding' => ['nullable', Rule::in(['aes128gcm', 'aesgcm'])],
        ]);

        $user = $request->user();
        $this->validateEndpointHost($data['endpoint']);
        $endpointHash = $this->endpointHash($data['endpoint']);
        $existing = PushSubscription::where('endpoint_hash', $endpointHash)->first();
        abort_if($existing && $existing->user_id !== $user->id, 409, 'Esta assinatura já está vinculada a outro acesso.');

        if (! $existing && $user->pushSubscriptions()->count() >= 10) {
            $user->pushSubscriptions()->oldest('updated_at')->first()?->delete();
        }

        $subscription = $user->pushSubscriptions()->updateOrCreate(
            ['endpoint_hash' => $endpointHash],
            [
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent_hash' => hash_hmac('sha256', Str::limit((string) $request->userAgent(), 500, ''), (string) config('app.key')),
                'last_failure_at' => null,
            ],
        );

        return response()->json(['enabled' => true, 'subscription_id' => $subscription->id], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url:https', 'max:2048']]);
        $request->user()->pushSubscriptions()
            ->where('endpoint_hash', $this->endpointHash($data['endpoint']))
            ->delete();

        return response()->json(['enabled' => false]);
    }

    private function endpointHash(string $endpoint): string
    {
        return hash_hmac('sha256', $endpoint, (string) config('app.key'));
    }

    private function validateEndpointHost(string $endpoint): void
    {
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        $port = parse_url($endpoint, PHP_URL_PORT);
        $allowed = collect(config('webpush.allowed_endpoint_hosts', []))->contains(function (string $candidate) use ($host): bool {
            $candidate = strtolower(trim($candidate));

            return $candidate !== '' && ($candidate[0] === '.'
                ? str_ends_with($host, $candidate)
                : hash_equals($candidate, $host));
        });

        if (! $allowed || ($port !== null && $port !== 443)) {
            throw ValidationException::withMessages([
                'endpoint' => 'O endpoint não pertence a um provedor Web Push permitido.',
            ]);
        }
    }
}
