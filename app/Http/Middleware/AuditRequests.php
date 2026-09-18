<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $actorBefore = $request->user();
        $errorsBefore = $request->session()->get('errors');

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $status = match (true) {
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                $exception instanceof ValidationException => 422,
                default => 500,
            };
            $this->record($request, $actorBefore, $status, 'rejected');

            throw $exception;
        }

        $errorsAfter = $request->session()->get('errors');
        $validationRejected = $errorsAfter !== null && $errorsAfter !== $errorsBefore;
        $outcome = $response->getStatusCode() >= 400 || $validationRejected ? 'rejected' : 'success';
        $this->record($request, $request->user() ?? $actorBefore, $response->getStatusCode(), $outcome);

        return $response;
    }

    private function record(Request $request, mixed $user, int $status, string $outcome): void
    {
        try {
            if (! Schema::hasTable('audit_logs')) {
                return;
            }

            $route = $request->route();
            $parameters = collect($route?->parameters() ?? [])
                ->map(fn ($value) => $value instanceof Model ? $value->getKey() : $value)
                ->filter(fn ($value) => is_scalar($value) || $value === null)
                ->all();

            AuditLog::create([
                'actor_user_id' => $user?->id,
                'tenant_id' => $user?->tenant_id,
                'event' => $route?->getName() ?? 'http.request',
                'method' => $request->method(),
                'path' => Str::limit($request->path(), 500, ''),
                'status' => $status,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'metadata' => [
                    'outcome' => $outcome,
                    'route_parameters' => $parameters,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::warning('Não foi possível gravar o evento de auditoria.', [
                'event' => $request->route()?->getName(),
                'exception' => $exception::class,
            ]);
        }
    }
}
