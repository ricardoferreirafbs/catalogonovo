<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMfaVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 401);

        if ($user->two_factor_confirmed_at
            && (int) $request->session()->get('mfa_verified_user_id') !== $user->id) {
            return redirect()->guest(route('mfa.challenge'));
        }

        return $next($request);
    }
}
