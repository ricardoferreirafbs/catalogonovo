<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireVerifiedEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 401);

        if ($user->requiresEmailVerification() && ! $user->hasVerifiedEmail()) {
            return redirect()->guest(route('verification.notice'));
        }

        return $next($request);
    }
}
