<?php

namespace App\Support;

use App\Models\ErrorOccurrence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ErrorOccurrenceRecorder
{
    /** @param array{code: string, title: string, message: string, meaning: string, actions: list<string>} $reference */
    public static function record(Request $request, Throwable $exception, int $status, array $reference, string $protocol): void
    {
        try {
            if (! Schema::hasTable('error_occurrences')) {
                return;
            }

            $user = $request->user();
            $resolvedTenant = $request->attributes->get('tenant');
            $ip = $request->ip();
            $platformFailure = in_array($status, [500, 503], true);

            ErrorOccurrence::create([
                'protocol' => $protocol,
                'error_code' => $reference['code'],
                'http_status' => $status,
                'status' => $platformFailure ? 'investigating' : 'new',
                'security_related' => false,
                'tenant_id' => $user?->tenant_id ?? $resolvedTenant?->id,
                'actor_user_id' => $user?->getAuthIdentifier(),
                'route' => Str::limit((string) $request->route()?->getName(), 160, ''),
                'method' => Str::limit($request->method(), 10, ''),
                'path' => Str::limit('/'.ltrim($request->path(), '/'), 500, ''),
                'exception_class' => Str::limit($exception::class, 255, ''),
                'ip_hash' => $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
                'retention_until' => now()->addDays(max(30, (int) config('security.error_retention_days', 90))),
            ]);
        } catch (Throwable $recordingException) {
            Log::warning('Não foi possível gravar a ocorrência de erro no banco.', [
                'protocol' => $protocol,
                'error_code' => $reference['code'],
                'exception' => $recordingException::class,
            ]);
        }
    }
}
