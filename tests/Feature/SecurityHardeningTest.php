<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_does_not_disclose_demo_credentials(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('admin@catalogo.test')
            ->assertDontSee('catalogo123');
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'email' => 'limite@example.com',
            'password' => Hash::make('Senha-Correta2026!'),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'limite@example.com',
                'password' => 'senha-incorreta',
            ])->assertRedirect();
        }

        $this->post(route('login.store'), [
            'email' => 'limite@example.com',
            'password' => 'senha-incorreta',
        ])->assertTooManyRequests();
    }

    public function test_responses_include_baseline_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $this->assertStringContainsString("object-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_hosting_rules_preserve_the_complete_content_security_policy(): void
    {
        $rules = file_get_contents(base_path('.htaccess'));

        $this->assertIsString($rules);
        $this->assertStringContainsString("default-src 'self'", $rules);
        $this->assertStringContainsString("object-src 'none'", $rules);
        $this->assertStringContainsString("frame-ancestors 'none'", $rules);
        $this->assertStringContainsString("form-action 'self'", $rules);
    }

    public function test_dangerous_menu_url_is_rejected(): void
    {
        [$tenant, $owner] = $this->tenantOwner();

        $this->actingAs($owner)->post(route('admin.menus.store'), [
            'label' => 'Link perigoso',
            'url' => 'javascript://alert(1)',
            'is_active' => true,
        ])->assertSessionHasErrors('url');

        $this->assertDatabaseMissing('menu_items', [
            'tenant_id' => $tenant->id,
            'label' => 'Link perigoso',
        ]);
    }

    public function test_dangerous_template_url_is_rejected(): void
    {
        [, $owner] = $this->tenantOwner();

        $this->actingAs($owner)->put(route('admin.content.update'), [
            'hero_title' => 'Catálogo seguro',
            'hero_primary_url' => 'data:text/html,<script>alert(1)</script>',
        ])->assertSessionHasErrors('hero_primary_url');
    }

    public function test_safe_internal_and_https_urls_are_accepted(): void
    {
        [$tenant, $owner] = $this->tenantOwner();

        $this->actingAs($owner)->post(route('admin.menus.store'), [
            'label' => 'Catálogo',
            'url' => '#catalogo',
            'is_active' => true,
        ])->assertSessionDoesntHaveErrors();

        $this->actingAs($owner)->post(route('admin.menus.store'), [
            'label' => 'Site institucional',
            'url' => 'https://empresa.example/produtos',
            'is_active' => true,
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('menu_items', 2);
        $this->assertDatabaseHas('menu_items', ['tenant_id' => $tenant->id, 'url' => '#catalogo']);
    }

    public function test_demo_account_removal_preserves_the_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Demonstração', 'slug' => 'aurora']);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'admin@catalogo.test',
            'role' => 'owner',
        ]);

        $this->artisan('security:remove-demo-account', ['--force' => true])
            ->expectsOutput('Conta de demonstração removida. A empresa e o catálogo foram preservados.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'admin@catalogo.test']);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }

    public function test_weak_tenant_admin_password_is_rejected(): void
    {
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role' => 'superadmin',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($superAdmin)->withSession(['mfa_verified_user_id' => $superAdmin->id])->post(route('platform.tenants.store'), [
            'name' => 'Empresa Nova',
            'slug' => 'empresa-nova',
            'plan' => 'professional',
            'status' => 'active',
            'admin_name' => 'Gestor',
            'admin_email' => 'gestor@example.com',
            'admin_password' => 'senha-fraca',
            'admin_password_confirmation' => 'senha-fraca',
        ])->assertSessionHasErrors('admin_password');

        $this->assertDatabaseMissing('tenants', ['slug' => 'empresa-nova']);
    }

    private function tenantOwner(): array
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        return [$tenant, $owner];
    }
}
