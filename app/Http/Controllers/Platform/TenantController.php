<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
        return view('platform.tenants.form', ['tenant' => new Tenant, 'owner' => new User]);
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

        return redirect()->route('platform.tenants.edit', $tenant)->with('success', 'Empresa e administrador criados com sucesso.');
    }

    public function edit(Tenant $tenant)
    {
        $tenant->loadCount(['products', 'users']);
        $owner = $tenant->owner()->first() ?? new User;

        return view('platform.tenants.form', compact('tenant', 'owner'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $owner = $tenant->owner()->first();
        $data = $this->validated($request, $tenant, $owner);

        DB::transaction(function () use ($data, $tenant, $owner) {
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

            if ($owner) {
                $owner->update($ownerData);
            } else {
                $ownerData['password'] = $ownerData['password'] ?? Hash::make(Str::random(32));
                $tenant->users()->create($ownerData);
            }
        });

        return back()->with('success', 'Empresa atualizada com sucesso.');
    }

    public function status(Tenant $tenant)
    {
        $tenant->update(['status' => $tenant->status === 'active' ? 'suspended' : 'active']);

        $message = $tenant->status === 'active' ? 'Empresa ativada.' : 'Empresa suspensa.';

        return back()->with('success', $message);
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
}
