<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\CatalogBackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('uploads');
        config([
            'backup.encryption_key' => 'base64:'.base64_encode(random_bytes(32)),
            'backup.disk' => 'local',
            'backup.directory' => 'backups',
        ]);
    }

    public function test_only_the_tenant_owner_can_access_company_backups(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        $this->actingAs($owner)->get(route('admin.backups.index'))->assertOk()->assertSee('Cópia isolada do seu catálogo');

        foreach (['admin', 'editor', 'viewer'] as $role) {
            $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
            $this->actingAs($user)->get(route('admin.backups.index'))->assertForbidden();
        }
    }

    public function test_owner_cannot_download_another_tenants_backup(): void
    {
        $tenantA = Tenant::create(['name' => 'Alfa', 'slug' => 'alfa']);
        $tenantB = Tenant::create(['name' => 'Beta', 'slug' => 'beta']);
        $ownerA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'owner']);
        User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'owner']);
        $backupB = app(CatalogBackupManager::class)->createTenant($tenantB);

        $this->actingAs($ownerA)
            ->post(route('admin.backups.download', basename($backupB['path'])))
            ->assertNotFound();
    }

    public function test_superadmin_can_create_and_view_general_backups(): void
    {
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role' => 'superadmin',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->post(route('platform.backups.store'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->get(route('platform.backups.index'))
            ->assertOk()
            ->assertSee('Proteção integral da plataforma');

        $this->assertCount(1, app(CatalogBackupManager::class)->generalFiles());
    }
}
