<?php

use App\Http\Middleware\AuditRequests;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\RequireMfaVerified;
use App\Http\Middleware\RequireSuperAdminMfa;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ErrorReference;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [AuditRequests::class]);

        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'superadmin' => EnsureSuperAdmin::class,
            'superadmin.mfa' => RequireSuperAdminMfa::class,
            'tenant.user' => EnsureTenantUser::class,
            'mfa.verified' => RequireMfaVerified::class,
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || ! ErrorReference::supports($status)) {
                return $response;
            }

            // Preserve Laravel's detailed diagnostics for developers, never in production.
            if (config('app.debug') && $status >= 500) {
                return $response;
            }

            $reference = ErrorReference::forStatus($status);
            $protocol = ErrorReference::protocol();
            $user = $request->user();

            Log::log($status >= 500 ? 'error' : ($status === 404 ? 'notice' : 'warning'), 'Erro exibido ao usuário.', [
                'error_code' => $reference['code'],
                'protocol' => $protocol,
                'status' => $status,
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'route' => $request->route()?->getName(),
                'user_id' => $user?->getAuthIdentifier(),
                'tenant_id' => $user?->tenant_id,
                'exception' => $exception::class,
            ]);

            return response()->view('errors.catalog', [
                'status' => $status,
                'errorCode' => $reference['code'],
                'errorTitle' => $reference['title'],
                'errorMessage' => $reference['message'],
                'protocol' => $protocol,
            ], $status, [
                'Cache-Control' => 'no-store, private',
                'X-Error-Code' => $reference['code'],
                'X-Error-Protocol' => $protocol,
            ]);
        });
    })->create();
