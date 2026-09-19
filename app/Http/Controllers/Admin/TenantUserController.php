<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class TenantUserController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        $users = $actor->tenant->users()->orderBy('name')->get();

        return view('admin.users.index', [
            'users' => $users,
            'roleLabels' => User::TENANT_ROLE_LABELS,
            'assignableRoles' => $this->assignableRoles($actor),
        ]);
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys(User::TENANT_ROLE_LABELS))],
        ]);

        abort_unless($actor->canAssignTenantRole($data['role']), 403);

        $user = $actor->tenant->users()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(48)),
            'role' => $data['role'],
            'email_verified_at' => null,
            'invitation_accepted_at' => null,
        ]);

        $this->sendInvitation($user, $actor, true);

        return back()->with('success', 'Usuário convidado. O link para criar a senha foi enviado por e-mail.');
    }

    public function update(Request $request, User $user)
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(User::TENANT_ROLE_LABELS))],
        ]);
        abort_unless($actor->canAssignTenantRole($data['role']), 403);

        if ($user->role === 'owner' && $data['role'] !== 'owner') {
            $this->ensureAnotherOwnerExists($user);
        }

        if ($user->role !== $data['role']) {
            $user->update(['role' => $data['role']]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with('success', 'Papel do usuário atualizado. As sessões anteriores foram encerradas.');
    }

    public function destroy(Request $request, User $user)
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        if ($user->role === 'owner') {
            $this->ensureAnotherOwnerExists($user);
        }

        DB::transaction(function () use ($user): void {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Password::deleteToken($user);
            $user->delete();
        });

        return back()->with('success', 'Usuário removido e sessões encerradas.');
    }

    public function resend(Request $request, User $user)
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);
        abort_if($user->invitation_accepted_at, 409, 'Este usuário já concluiu o cadastro.');

        $this->sendInvitation($user, $actor);

        return back()->with('success', 'Convite reenviado. O link anterior foi invalidado.');
    }

    private function authorizeTarget(User $actor, User $target): void
    {
        abort_unless($target->tenant_id === $actor->tenant_id, 404);
        abort_unless($actor->canManageTenantUser($target), 403);
    }

    private function ensureAnotherOwnerExists(User $user): void
    {
        $hasAnotherOwner = $user->tenant->users()
            ->where('role', 'owner')
            ->where('id', '!=', $user->id)
            ->exists();

        if (! $hasAnotherOwner) {
            throw ValidationException::withMessages([
                'role' => 'A empresa precisa manter pelo menos um proprietário.',
            ]);
        }
    }

    private function sendInvitation(User $user, User $actor, bool $deleteOnFailure = false): void
    {
        try {
            Password::deleteToken($user);
            $token = Password::createToken($user);
            $user->notify(new TenantInvitationNotification($token, $actor->tenant->name, $actor->name));
        } catch (Throwable $exception) {
            report($exception);
            Password::deleteToken($user);

            if ($deleteOnFailure && ! $user->invitation_accepted_at) {
                $user->delete();
            }

            throw ValidationException::withMessages([
                'email' => 'Não foi possível enviar o convite. Confira a configuração de e-mail e tente novamente.',
            ]);
        }
    }

    private function assignableRoles(User $actor): array
    {
        return collect(User::TENANT_ROLE_LABELS)
            ->filter(fn (string $label, string $role) => $actor->canAssignTenantRole($role))
            ->all();
    }
}
