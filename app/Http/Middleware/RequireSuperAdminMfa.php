<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperAdminMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->isSuperAdmin(), 403);

        if (! $user->two_factor_confirmed_at) {
            return redirect()->route('platform.mfa.setup');
        }

        if ((int) $request->session()->get('mfa_verified_user_id') !== $user->id) {
            return redirect()->guest(route('mfa.challenge'));
        }

        return $next($request);
    }
}
