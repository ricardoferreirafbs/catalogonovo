<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $tenant = Tenant::query()
            ->where('status', 'active')
            ->where(function ($query) use ($host) {
                $query->where('custom_domain', $host);

                $parts = explode('.', $host);
                if (count($parts) > 2 && ! in_array($parts[0], ['www', 'app'], true)) {
                    $query->orWhere('slug', $parts[0]);
                }
            })
            ->first();

        if (! $tenant && app()->environment(['local', 'testing'])) {
            $tenant = Tenant::where('slug', env('DEMO_TENANT_SLUG', 'aurora'))->first();
        }

        abort_unless($tenant, 404, 'Catálogo não encontrado.');
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
