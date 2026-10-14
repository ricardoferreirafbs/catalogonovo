<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class EmailVerificationController extends Controller
{
    public function notice(Request $request)
    {
        $user = $request->user();

        abort_unless($user && $user->requiresEmailVerification(), 404);

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->two_factor_confirmed_at ? 'mfa.challenge' : 'admin.mfa.setup');
        }

        return view('auth.verify-email', ['user' => $user]);
    }

    public function resend(Request $request)
    {
        $user = $request->user();

        abort_unless($user && $user->requiresEmailVerification(), 404);

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->two_factor_confirmed_at ? 'mfa.challenge' : 'admin.mfa.setup');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'email' => 'Não foi possível enviar o link agora. Aguarde alguns minutos e tente novamente.',
            ]);
        }

        return back()->with('success', 'Enviamos um novo link de confirmação. Verifique a caixa de entrada e o spam.');
    }

    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::query()->with('tenant')->findOrFail($id);

        abort_unless($user->requiresEmailVerification(), 404);
        abort_unless($user->tenant?->status === 'active', 403);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            AuditLog::create([
                'actor_user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'event' => 'security.owner_email_verified',
                'method' => $request->method(),
                'path' => Str::limit($request->path(), 500, ''),
                'status' => 200,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'metadata' => [
                    'outcome' => 'success',
                    'target_user_id' => $user->id,
                ],
            ]);
        }

        if ($request->user()?->is($user)) {
            return redirect()->route($user->two_factor_confirmed_at ? 'mfa.challenge' : 'admin.mfa.setup')
                ->with('success', 'E-mail confirmado com sucesso.');
        }

        return redirect()->route('login')->with('success', 'E-mail confirmado. Entre para continuar a configuração segura da conta.');
    }
}
