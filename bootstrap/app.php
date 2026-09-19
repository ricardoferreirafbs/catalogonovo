<?php

use App\Http\Middleware\AuditRequests;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\RequireMfaVerified;
use App\Http\Middleware\RequireSuperAdminMfa;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
