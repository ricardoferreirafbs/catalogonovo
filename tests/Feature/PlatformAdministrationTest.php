<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmins_can_access_the_platform_panel(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => 'superadmin']);

        $this->actingAs($tenantUser)->get(route('platform.dashboard'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('platform.dashboard'))->assertOk();
    }

    public function test_superadmin_can_create_a_tenant_and_its_owner(): void
    {
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => 'superadmin']);

        $response = $this->actingAs($superAdmin)->post(route('platform.tenants.store'), [
            'name' => 'Empresa Nova',
            'slug' => 'empresa-nova',
            'custom_domain' => 'CATALOGO.EMPRESA.COM.BR',
            'plan' => 'professional',
            'status' => 'active',
            'contact_phone' => '5511999999999',
            'admin_name' => 'Gestor da Empresa',
            'admin_email' => 'GESTOR@EMPRESA.COM.BR',
            'admin_password' => 'senha-segura',
            'admin_password_confirmation' => 'senha-segura',
        ]);

        $tenant = Tenant::where('slug', 'empresa-nova')->firstOrFail();

        $response->assertRedirect(route('platform.tenants.edit', $tenant));
        $this->assertSame('catalogo.empresa.com.br', $tenant->custom_domain);
        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'email' => 'gestor@empresa.com.br',
            'role' => 'owner',
        ]);
    }

    public function test_suspended_tenant_user_cannot_sign_in(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente', 'status' => 'suspended']);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'email' => 'cliente@example.com',
            'password' => Hash::make('senha-segura'),
        ]);

        $this->post(route('login.store'), [
            'email' => 'cliente@example.com',
            'password' => 'senha-segura',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_superadmin_can_delete_a_tenant_after_typing_its_slug(): void
    {
        Storage::fake('uploads');
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => 'superadmin']);
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        Storage::disk('uploads')->put("tenants/{$tenant->id}/products/item.jpg", 'imagem');

        $this->actingAs($superAdmin)->delete(route('platform.tenants.destroy', $tenant), [
            'confirmation' => 'cliente',
        ])->assertRedirect(route('platform.tenants.index'));

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        Storage::disk('uploads')->assertMissing("tenants/{$tenant->id}");
    }
}
