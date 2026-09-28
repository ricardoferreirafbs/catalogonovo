<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CatalogBackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class BackupRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $backupKey;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('uploads');
        $this->backupKey = 'base64:'.base64_encode(random_bytes(32));
        config([
            'backup.encryption_key' => $this->backupKey,
            'backup.disk' => 'local',
            'backup.directory' => 'backups',
        ]);
    }

    public function test_encrypted_backup_can_be_verified_and_restored_into_an_empty_installation(): void
    {
        $tenant = Tenant::create(['name' => 'Empresa Recuperável', 'slug' => 'recuperavel']);
        User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Produto protegido',
            'slug' => 'produto-protegido',
            'status' => 'published',
        ]);
        $product->media()->create(['path' => "tenants/{$tenant->id}/products/foto.jpg", 'alt_text' => 'Foto']);
        Storage::disk('uploads')->put("tenants/{$tenant->id}/products/foto.jpg", 'imagem-binaria-de-teste');

        $manager = app(CatalogBackupManager::class);
        $created = $manager->create();
        $encrypted = Storage::disk('local')->get($created['path']);

        $this->assertStringNotContainsString('Empresa Recuperável', $encrypted);
        $this->assertStringNotContainsString('imagem-binaria-de-teste', $encrypted);
        $verified = $manager->verify($created['path']);
        $this->assertSame(1, $verified['metadata']['format']);
        $this->assertGreaterThanOrEqual(4, $verified['footer']['rows']);
        $this->assertSame(1, $verified['footer']['files']);

        $this->emptyBackupTables();
        Storage::disk('uploads')->deleteDirectory('tenants');

        $restored = $manager->restore($created['path']);

        $this->assertSame($verified['footer']['rows'], $restored['rows']);
        $this->assertSame(1, $restored['files']);
        $this->assertDatabaseHas('tenants', ['name' => 'Empresa Recuperável', 'slug' => 'recuperavel']);
        $this->assertDatabaseHas('products', ['name' => 'Produto protegido']);
        Storage::disk('uploads')->assertExists("tenants/{$tenant->id}/products/foto.jpg");
        $this->assertSame('imagem-binaria-de-teste', Storage::disk('uploads')->get("tenants/{$tenant->id}/products/foto.jpg"));
    }

    public function test_backup_verification_rejects_wrong_key(): void
    {
        $manager = app(CatalogBackupManager::class);
        $created = $manager->create();

        config(['backup.encryption_key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->expectException(RuntimeException::class);
        $manager->verify($created['path']);
    }

    public function test_backup_verification_rejects_corruption(): void
    {
        $manager = app(CatalogBackupManager::class);
        $created = $manager->create();
        $lines = explode("\n", Storage::disk('local')->get($created['path']));
        $envelope = json_decode($lines[1], true, 8, JSON_THROW_ON_ERROR);
        $envelope['c'][5] = $envelope['c'][5] === 'A' ? 'B' : 'A';
        $lines[1] = json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        Storage::disk('local')->put($created['path'], implode("\n", $lines));

        $this->expectException(RuntimeException::class);
        $manager->verify($created['path']);
    }

    public function test_restore_refuses_to_overwrite_an_installation_with_data(): void
    {
        $tenant = Tenant::create(['name' => 'Empresa Ativa', 'slug' => 'ativa']);
        $manager = app(CatalogBackupManager::class);
        $created = $manager->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A restauração só é permitida em banco vazio');
        $manager->restore($created['path']);

        $this->assertModelExists($tenant);
    }

    private function emptyBackupTables(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach (array_reverse(CatalogBackupManager::TABLES) as $table) {
                DB::table($table)->delete();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
