<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OwnerMfaRecoveredNotification;
use App\Services\OwnerMfaRecoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $status = $request->query('status');

        $tenants = Tenant::query()
            ->with('owner')
            ->withCount(['products', 'users'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('custom_domain', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'suspended'], true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('platform.tenants.index', compact('tenants', 'search', 'status'));
    }

    public function create()
    {
        return view('platform.tenants.form', [
            'tenant' => new Tenant,
            'owner' => new User,
            'identityMethods' => OwnerMfaRecoveryService::IDENTITY_METHODS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $tenant = DB::transaction(function () use ($data) {
            $tenant = Tenant::create(array_merge($this->tenantData($data), ['theme' => Tenant::defaultTheme()]));
            $tenant->users()->create([
                'name' => $data['admin_name'],
                'email' => strtolower($data['admin_email']),
                'password' => Hash::make($data['admin_password']),
                'role' => 'owner',
                'invitation_accepted_at' => now(),
            ]);

            return $tenant;
        });

        $owner = $tenant->owner()->firstOrFail();
        $notificationSent = $this->sendOwnerVerification($owner);
        $response = redirect()->route('platform.tenants.edit', $tenant)
            ->with('success', 'Empresa e Proprietário criados. O e-mail precisa ser confirmado antes do primeiro acesso ao painel.');

        if (! $notificationSent) {
            $response->with('warning', 'A empresa foi criada, mas o link de confirmação não foi enviado. Confira o SMTP e use o reenvio pela conta do Proprietário.');
        }

        return $response;
    }

    public function edit(Tenant $tenant)
    {
        $tenant->loadCount(['products', 'users']);
        $owner = $tenant->owner()->first() ?? new User;

        return view('platform.tenants.form', [
            'tenant' => $tenant,
            'owner' => $owner,
            'identityMethods' => OwnerMfaRecoveryService::IDENTITY_METHODS,
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $owner = $tenant->owner()->first();
        $data = $this->validated($request, $tenant, $owner);
        $ownerEmailChanged = $owner
            && strtolower($owner->email) !== strtolower($data['admin_email']);
        $ownerNeedsVerification = ! $owner || $ownerEmailChanged;

        DB::transaction(function () use ($data, $tenant, $owner, $ownerEmailChanged) {
            $tenant->update($this->tenantData($data));

            $ownerData = [
                'name' => $data['admin_name'],
                'email' => strtolower($data['admin_email']),
                'role' => 'owner',
                'invitation_accepted_at' => $owner?->invitation_accepted_at ?? now(),
            ];

            if (! empty($data['admin_password'])) {
                $ownerData['password'] = Hash::make($data['admin_password']);
            }

            if ($ownerEmailChanged) {
                $ownerData['email_verified_at'] = null;
                $ownerData['remember_token'] = Str::random(60);
                DB::table('sessions')->where('user_id', $owner->id)->delete();
                PasswordBroker::deleteToken($owner);
                $owner->pushSubscriptions()->delete();
            }

            if ($owner) {
                $owner->update($ownerData);
            } else {
                $ownerData['password'] = $ownerData['password'] ?? Hash::make(Str::random(32));
                $tenant->users()->create($ownerData);
            }
        });

        $currentOwner = $tenant->owner()->first();
        $notificationSent = ! $ownerNeedsVerification || ($currentOwner && $this->sendOwnerVerification($currentOwner));
        $successMessage = match (true) {
            $ownerEmailChanged => 'Empresa atualizada. As sessões do Proprietário foram encerradas e o novo e-mail precisa ser confirmado.',
            ! $owner => 'Empresa atualizada e conta proprietária criada. O e-mail precisa ser confirmado.',
            default => 'Empresa atualizada com sucesso.',
        };
        $response = back()->with(
            'success',
            $successMessage
        );

        if (! $notificationSent) {
            $response->with('warning', 'O novo e-mail foi salvo, mas o link de confirmação não foi enviado. Confira o SMTP.');
        }

        return $response;
    }

    public function status(Tenant $tenant)
    {
        $tenant->update(['status' => $tenant->status === 'active' ? 'suspended' : 'active']);

        $message = $tenant->status === 'active' ? 'Empresa ativada.' : 'Empresa suspensa.';

        return back()->with('success', $message);
    }

    public function recoverOwnerMfa(
        Request $request,
        Tenant $tenant,
        User $owner,
        OwnerMfaRecoveryService $recovery
    ) {
        abort_unless($owner->tenant_id === $tenant->id && $owner->role === 'owner', 404);

        $request->merge([
            'owner_email' => strtolower(trim((string) $request->input('owner_email'))),
            'support_reference' => trim((string) $request->input('support_reference')),
        ]);

        $data = $request->validate([
            'verification_method' => ['required', Rule::in(array_keys(OwnerMfaRecoveryService::IDENTITY_METHODS))],
            'support_reference' => ['required', 'string', 'max:80', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]{5,79}\z/'],
            'owner_email' => ['required', Rule::in([strtolower($owner->email)])],
            'password' => ['required', 'current_password'],
            'code' => ['required', 'digits:6'],
            'acknowledgement' => ['accepted'],
        ], [
            'support_reference.regex' => 'Use uma referência de 6 a 80 caracteres contendo apenas letras, números, ponto, hífen ou sublinhado.',
            'owner_email.in' => 'Digite exatamente o e-mail atual do Proprietário.',
            'password.current_password' => 'A senha atual do Superadmin não foi confirmada.',
            'acknowledgement.accepted' => 'Confirme que a identidade foi validada e que o Proprietário será avisado.',
        ]);

        $protocol = 'MFA-'.Str::upper(Str::random(12));
        $operation = DB::transaction(function () use ($request, $tenant, $owner, $recovery, $data, $protocol): array {
            $result = $recovery->recover($request->user(), $tenant, $owner, $data['code']);
            $audit = AuditLog::create([
                'actor_user_id' => $request->user()->id,
                'tenant_id' => $tenant->id,
                'event' => 'security.owner_mfa_recovered',
                'method' => $request->method(),
                'path' => Str::limit($request->path(), 500, ''),
                'status' => 200,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'metadata' => [
                    'outcome' => 'success',
                    'protocol' => $protocol,
                    'target_user_id' => $result['owner']->id,
                    'verification_method' => $data['verification_method'],
                    'support_reference' => $data['support_reference'],
                    'sessions_revoked' => $result['sessions_revoked'],
                    'push_subscriptions_revoked' => $result['push_subscriptions_revoked'],
                    'notification_sent' => null,
                ],
            ]);

            return ['result' => $result, 'audit' => $audit];
        });

        $result = $operation['result'];
        $audit = $operation['audit'];
        $notificationSent = true;

        try {
            $result['owner']->notify(new OwnerMfaRecoveredNotification($tenant->name, $protocol));
        } catch (Throwable $exception) {
            report($exception);
            $notificationSent = false;
        }

        $auditMetadata = $audit->metadata;
        $auditMetadata['notification_sent'] = $notificationSent;
        $audit->update(['metadata' => $auditMetadata]);

        $response = back()->with(
            'success',
            "MFA do Proprietário redefinido. Protocolo {$protocol}. No próximo acesso, um novo MFA será obrigatório."
        );

        if (! $notificationSent) {
            $response->with(
                'warning',
                "A recuperação foi concluída, mas o aviso por e-mail falhou. Registre a falha no atendimento {$protocol}."
            );
        }

        return $response;
    }

    public function destroy(Request $request, Tenant $tenant)
    {
        $request->validate([
            'confirmation' => ['required', Rule::in([$tenant->slug])],
        ], ['confirmation.in' => 'Digite exatamente o identificador da empresa para confirmar.']);

        $tenantId = $tenant->id;

        DB::transaction(function () use ($tenant) {
            $tenant->users()->delete();
            $tenant->delete();
        });

        Storage::disk('uploads')->deleteDirectory("tenants/{$tenantId}");

        return redirect()->route('platform.tenants.index')->with('success', 'Empresa, usuários, catálogo e arquivos excluídos.');
    }

    private function validated(Request $request, ?Tenant $tenant = null, ?User $owner = null): array
    {
        $request->merge([
            'slug' => strtolower(trim((string) $request->input('slug'))),
            'custom_domain' => filled($request->input('custom_domain')) ? strtolower(trim((string) $request->input('custom_domain'))) : null,
            'admin_email' => strtolower(trim((string) $request->input('admin_email'))),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash', 'max:120', Rule::unique('tenants')->ignore($tenant?->id)],
            'custom_domain' => ['nullable', 'string', 'max:255', 'regex:/^(?!https?:\/\/)[a-z0-9.-]+$/i', Rule::unique('tenants')->ignore($tenant?->id)],
            'plan' => ['required', Rule::in(['starter', 'professional', 'business'])],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'admin_name' => ['required', 'string', 'max:160'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($owner?->id)],
            'admin_password' => [
                $tenant && $owner ? 'nullable' : 'required',
                'string',
                Password::min(12)->mixedCase()->letters()->numbers()->symbols(),
                'confirmed',
            ],
        ], [
            'custom_domain.regex' => 'Informe somente o domínio, sem http://, https:// ou caminhos.',
            'admin_password.confirmed' => 'A confirmação da senha não corresponde.',
        ]);
    }

    private function tenantData(array $data): array
    {
        return [
            'name' => $data['name'],
            'slug' => strtolower($data['slug']),
            'custom_domain' => filled($data['custom_domain'] ?? null) ? strtolower(trim($data['custom_domain'])) : null,
            'plan' => $data['plan'],
            'status' => $data['status'],
            'contact_phone' => $data['contact_phone'] ?? null,
        ];
    }

    private function sendOwnerVerification(User $owner): bool
    {
        try {
            $owner->sendEmailVerificationNotification();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
