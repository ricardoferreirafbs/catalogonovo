<?php

namespace Tests\Feature;

use App\Models\ErrorOccurrence;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorOccurrenceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_verified_superadmin_can_access_occurrence_center(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $superAdmin = $this->verifiedSuperAdmin();
        $occurrence = $this->occurrence(['tenant_id' => $tenant->id, 'actor_user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('platform.occurrences.index'))->assertForbidden();

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->get(route('platform.occurrences.index'))
            ->assertOk()
            ->assertSee($occurrence->protocol)
            ->assertSee('Central de ocorrências');

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $superAdmin->id,
            'event' => 'platform.occurrences.index',
        ]);
    }

    public function test_security_classification_extends_retention_to_180_days(): void
    {
        $superAdmin = $this->verifiedSuperAdmin();
        $occurrence = $this->occurrence();

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->patch(route('platform.occurrences.update', $occurrence), [
                'status' => 'investigating',
                'security_related' => '1',
                'internal_notes' => 'Tentativas de acesso não reconhecidas reportadas pelo cliente.',
            ])
            ->assertRedirect();

        $occurrence->refresh();
        $this->assertTrue($occurrence->security_related);
        $this->assertSame('investigating', $occurrence->status);
        $this->assertSame($superAdmin->id, $occurrence->reviewed_by_user_id);
        $this->assertTrue($occurrence->retention_until->between(now()->addDays(179), now()->addDays(181)));
    }

    public function test_security_classification_requires_an_internal_summary(): void
    {
        $superAdmin = $this->verifiedSuperAdmin();
        $occurrence = $this->occurrence();

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->patch(route('platform.occurrences.update', $occurrence), [
                'status' => 'investigating',
                'security_related' => '1',
                'internal_notes' => '',
            ])
            ->assertSessionHasErrors('internal_notes');

        $this->assertFalse($occurrence->fresh()->security_related);
    }

    public function test_prune_command_uses_each_occurrence_retention_date(): void
    {
        $expiredSimple = $this->occurrence([
            'protocol' => 'SIMPLE0001',
            'retention_until' => now()->subMinute(),
        ]);
        $expiredSecurity = $this->occurrence([
            'protocol' => 'SECURE0001',
            'security_related' => true,
            'retention_until' => now()->subMinute(),
        ]);
        $active = $this->occurrence([
            'protocol' => 'ACTIVE0001',
            'retention_until' => now()->addDay(),
        ]);

        $this->artisan('occurrences:prune')
            ->expectsOutput('2 ocorrência(s) removida(s) conforme a política de retenção.')
            ->assertSuccessful();

        $this->assertModelMissing($expiredSimple);
        $this->assertModelMissing($expiredSecurity);
        $this->assertModelExists($active);
    }

    private function occurrence(array $attributes = []): ErrorOccurrence
    {
        return ErrorOccurrence::create(array_merge([
            'protocol' => 'TESTE00001',
            'error_code' => 'CAT-404-RECURSO',
            'http_status' => 404,
            'status' => 'new',
            'security_related' => false,
            'method' => 'GET',
            'path' => '/pagina-inexistente',
            'exception_class' => 'Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException',
            'retention_until' => now()->addDays(90),
        ], $attributes));
    }

    private function verifiedSuperAdmin(): User
    {
        return User::factory()->create([
            'tenant_id' => null,
            'role' => 'superadmin',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
