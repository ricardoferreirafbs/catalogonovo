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
        $platformHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($request->is('/') && $platformHost !== '' && $host === $platformHost) {
            $user = $request->user();

            if (! $user) {
                return redirect()->route('login');
            }

            return redirect()->route($user->isSuperAdmin() ? 'platform.dashboard' : 'admin.dashboard');
        }

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
