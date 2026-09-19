<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class TenantRolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_users_and_invitation_does_not_send_a_password(): void
    {
        Notification::fake();
        [$tenant, $owner] = $this->tenantAndUser('owner');

        $this->actingAs($owner)->post(route('admin.users.store'), [
            'name' => 'Editora Convidada',
            'email' => 'EDITORA@EXAMPLE.COM',
            'role' => 'editor',
        ])->assertRedirect();

        $invited = User::where('email', 'editora@example.com')->firstOrFail();
        $this->assertSame($tenant->id, $invited->tenant_id);
        $this->assertSame('editor', $invited->role);
        $this->assertNull($invited->invitation_accepted_at);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $invited->email]);
        Notification::assertSentTo($invited, TenantInvitationNotification::class);
        $this->actingAs($owner)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Editora Convidada')
            ->assertSee('Convite pendente');
    }

    public function test_accepting_invitation_defines_password_and_marks_registration_as_complete(): void
    {
        [$tenant] = $this->tenantAndUser('owner');
        $invited = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'viewer',
            'email' => 'convite@example.com',
            'email_verified_at' => null,
            'invitation_accepted_at' => null,
        ]);
        $token = Password::createToken($invited);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $invited->email,
            'password' => 'Senha-Nova2026!',
            'password_confirmation' => 'Senha-Nova2026!',
        ])->assertRedirect(route('login'));

        $invited->refresh();
        $this->assertNotNull($invited->email_verified_at);
        $this->assertNotNull($invited->invitation_accepted_at);
    }

    public function test_admin_can_only_manage_editors_and_viewers(): void
    {
        Notification::fake();
        [$tenant, $admin] = $this->tenantAndUser('admin');
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $editor = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'editor']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Outro administrador',
            'email' => 'outro-admin@example.com',
            'role' => 'admin',
        ])->assertForbidden();

        $this->actingAs($admin)->patch(route('admin.users.update', $owner), [
            'role' => 'viewer',
        ])->assertForbidden();

        $this->actingAs($admin)->patch(route('admin.users.update', $editor), [
            'role' => 'viewer',
        ])->assertRedirect();

        $this->assertSame('viewer', $editor->fresh()->role);
        $this->assertDatabaseMissing('users', ['email' => 'outro-admin@example.com']);
    }

    public function test_editor_can_manage_catalog_but_cannot_change_appearance_delete_products_or_manage_users(): void
    {
        [$tenant, $editor] = $this->tenantAndUser('editor');
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Produto protegido',
            'slug' => 'produto-protegido',
            'status' => 'draft',
        ]);

        $this->actingAs($editor)->post(route('admin.products.store'), [
            'name' => 'Produto do editor',
            'status' => 'draft',
        ])->assertRedirect();

        $this->actingAs($editor)->get(route('admin.categories.index'))->assertOk();
        $this->actingAs($editor)->get(route('admin.content.edit'))->assertOk();
        $this->actingAs($editor)->get(route('admin.theme.edit'))->assertForbidden();
        $this->actingAs($editor)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($editor)->delete(route('admin.products.destroy', $product))->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_viewer_has_read_only_access_to_dashboard_and_products(): void
    {
        [$tenant, $viewer] = $this->tenantAndUser('viewer');
        Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Produto visível',
            'slug' => 'produto-visivel',
            'status' => 'published',
        ]);

        $this->actingAs($viewer)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Somente leitura');
        $this->actingAs($viewer)->get(route('admin.products.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.categories.store'), [
            'name' => 'Categoria indevida',
        ])->assertForbidden();

        $this->assertDatabaseMissing('categories', [
            'tenant_id' => $tenant->id,
            'name' => 'Categoria indevida',
        ]);
    }

    public function test_user_management_is_scoped_to_the_authenticated_tenant(): void
    {
        [$tenant, $owner] = $this->tenantAndUser('owner');
        $otherTenant = Tenant::create(['name' => 'Outra', 'slug' => 'outra']);
        $foreignUser = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'editor']);

        $this->actingAs($owner)->patch(route('admin.users.update', $foreignUser), [
            'role' => 'viewer',
        ])->assertNotFound();

        $this->actingAs($owner)->delete(route('admin.users.destroy', $foreignUser))
            ->assertNotFound();

        $this->assertSame('editor', $foreignUser->fresh()->role);
        $this->assertSame($tenant->id, $owner->tenant_id);
    }

    public function test_role_change_ends_existing_database_sessions(): void
    {
        [$tenant, $owner] = $this->tenantAndUser('owner');
        $editor = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'editor']);
        DB::table('sessions')->insert([
            'id' => 'active-session',
            'user_id' => $editor->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $this->actingAs($owner)->patch(route('admin.users.update', $editor), [
            'role' => 'viewer',
        ])->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'active-session']);
    }

    public function test_user_cannot_remove_or_downgrade_their_own_account(): void
    {
        [, $owner] = $this->tenantAndUser('owner');

        $this->actingAs($owner)->patch(route('admin.users.update', $owner), [
            'role' => 'viewer',
        ])->assertForbidden();

        $this->actingAs($owner)->delete(route('admin.users.destroy', $owner))
            ->assertForbidden();

        $this->assertSame('owner', $owner->fresh()->role);
    }

    private function tenantAndUser(string $role): array
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);

        return [$tenant, $user];
    }
}
