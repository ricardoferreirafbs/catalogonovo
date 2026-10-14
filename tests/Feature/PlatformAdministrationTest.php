<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OwnerMfaRecoveredNotification;
use App\Notifications\VerifyOwnerEmailNotification;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmins_can_access_the_platform_panel(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $superAdmin = $this->verifiedSuperAdmin();

        $this->actingAs($tenantUser)->get(route('platform.dashboard'))->assertForbidden();
        $this->actingAs($superAdmin)->withSession(['mfa_verified_user_id' => $superAdmin->id])->get(route('platform.dashboard'))->assertOk();
    }

    public function test_superadmin_can_create_a_tenant_and_its_owner(): void
    {
        Notification::fake();
        $superAdmin = $this->verifiedSuperAdmin();

        $response = $this->actingAs($superAdmin)->withSession(['mfa_verified_user_id' => $superAdmin->id])->post(route('platform.tenants.store'), [
            'name' => 'Empresa Nova',
            'slug' => 'empresa-nova',
            'custom_domain' => 'CATALOGO.EMPRESA.COM.BR',
            'plan' => 'professional',
            'status' => 'active',
            'contact_phone' => '5511999999999',
            'admin_name' => 'Gestor da Empresa',
            'admin_email' => 'GESTOR@EMPRESA.COM.BR',
            'admin_password' => 'Senha-Segura2026!',
            'admin_password_confirmation' => 'Senha-Segura2026!',
        ]);

        $tenant = Tenant::where('slug', 'empresa-nova')->firstOrFail();

        $response->assertRedirect(route('platform.tenants.edit', $tenant));
        $this->assertSame('catalogo.empresa.com.br', $tenant->custom_domain);
        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'email' => 'gestor@empresa.com.br',
            'role' => 'owner',
        ]);
        $owner = $tenant->owner()->firstOrFail();
        $this->assertNull($owner->email_verified_at);
        Notification::assertSentTo($owner, VerifyOwnerEmailNotification::class);
    }

    public function test_changing_owner_email_revokes_sessions_and_requires_new_verification(): void
    {
        Notification::fake();
        $totp = app(TotpService::class);
        $superAdmin = $this->verifiedSuperAdmin();
        $tenant = Tenant::create([
            'name' => 'Cliente',
            'slug' => 'cliente',
            'plan' => 'professional',
            'status' => 'active',
        ]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'email' => 'anterior@example.com',
            'two_factor_secret' => $totp->generateSecret(),
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);
        DB::table('sessions')->insert([
            'id' => 'owner-email-session',
            'user_id' => $owner->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Owner browser',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);
        $owner->pushSubscriptions()->create([
            'endpoint_hash' => hash('sha256', 'https://push.example/email-change'),
            'endpoint' => 'https://push.example/email-change',
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->put(route('platform.tenants.update', $tenant), [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'custom_domain' => null,
                'plan' => $tenant->plan,
                'status' => $tenant->status,
                'contact_phone' => null,
                'admin_name' => $owner->name,
                'admin_email' => 'novo@example.com',
                'admin_password' => null,
                'admin_password_confirmation' => null,
            ])->assertRedirect()
            ->assertSessionHas('success');

        $owner->refresh();
        $this->assertSame('novo@example.com', $owner->email);
        $this->assertNull($owner->email_verified_at);
        $this->assertNotNull($owner->two_factor_confirmed_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'owner-email-session']);
        $this->assertDatabaseMissing('push_subscriptions', ['user_id' => $owner->id]);
        Notification::assertSentTo($owner, VerifyOwnerEmailNotification::class);
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
        $superAdmin = $this->verifiedSuperAdmin();
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        Storage::disk('uploads')->put("tenants/{$tenant->id}/products/item.jpg", 'imagem');

        $this->actingAs($superAdmin)->withSession(['mfa_verified_user_id' => $superAdmin->id])->delete(route('platform.tenants.destroy', $tenant), [
            'confirmation' => 'cliente',
        ])->assertRedirect(route('platform.tenants.index'));

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        Storage::disk('uploads')->assertMissing("tenants/{$tenant->id}");
    }

    public function test_verified_superadmin_can_securely_recover_an_owners_mfa(): void
    {
        Notification::fake();
        $totp = app(TotpService::class);
        $superAdmin = $this->verifiedSuperAdmin();
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $ownerSecret = $totp->generateSecret();
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'password' => Hash::make('Senha-Cliente2026!'),
            'two_factor_secret' => $ownerSecret,
            'two_factor_recovery_codes' => [$totp->hashRecoveryCode('OWNER-2026')],
            'two_factor_confirmed_at' => now(),
        ]);
        $otherUser = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'viewer']);
        $rememberToken = $owner->remember_token;

        DB::table('sessions')->insert([
            [
                'id' => 'owner-session',
                'user_id' => $owner->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Owner browser',
                'payload' => 'payload',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'other-session',
                'user_id' => $otherUser->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Viewer browser',
                'payload' => 'payload',
                'last_activity' => now()->timestamp,
            ],
        ]);
        $owner->pushSubscriptions()->create([
            'endpoint_hash' => hash('sha256', 'https://push.example/owner'),
            'endpoint' => 'https://push.example/owner',
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
        ]);

        $response = $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->post(route('platform.tenants.owner-mfa.recover', [$tenant, $owner]), [
                'verification_method' => 'confirmed_email_reply',
                'support_reference' => 'SUP-20261013-001',
                'owner_email' => $owner->email,
                'password' => 'password',
                'code' => $totp->code((string) $superAdmin->two_factor_secret),
                'acknowledgement' => '1',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'Protocolo MFA-'));

        $owner->refresh();
        $this->assertNull($owner->two_factor_secret);
        $this->assertNull($owner->two_factor_recovery_codes);
        $this->assertNull($owner->two_factor_confirmed_at);
        $this->assertNull($owner->two_factor_last_used_counter);
        $this->assertNotSame($rememberToken, $owner->remember_token);
        $this->assertTrue(Hash::check('Senha-Cliente2026!', $owner->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'owner-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        $this->assertDatabaseMissing('push_subscriptions', ['user_id' => $owner->id]);
        $this->assertNotNull($superAdmin->fresh()->two_factor_last_used_counter);
        Notification::assertSentTo($owner, OwnerMfaRecoveredNotification::class);

        $audit = AuditLog::query()->where('event', 'security.owner_mfa_recovered')->firstOrFail();
        $this->assertSame($owner->id, $audit->metadata['target_user_id']);
        $this->assertSame('confirmed_email_reply', $audit->metadata['verification_method']);
        $this->assertSame('SUP-20261013-001', $audit->metadata['support_reference']);
        $this->assertSame(1, $audit->metadata['sessions_revoked']);
        $this->assertSame(1, $audit->metadata['push_subscriptions_revoked']);
        $this->assertTrue($audit->metadata['notification_sent']);
        $this->assertArrayNotHasKey('password', $audit->metadata);
        $this->assertArrayNotHasKey('code', $audit->metadata);
    }

    public function test_owner_mfa_recovery_rejects_wrong_confirmation_without_changing_the_account(): void
    {
        Notification::fake();
        $totp = app(TotpService::class);
        $superAdmin = $this->verifiedSuperAdmin();
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'two_factor_secret' => $totp->generateSecret(),
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->post(route('platform.tenants.owner-mfa.recover', [$tenant, $owner]), [
                'verification_method' => 'confirmed_email_reply',
                'support_reference' => 'SUP-20261013-002',
                'owner_email' => 'outra-pessoa@example.com',
                'password' => 'password',
                'code' => $totp->code((string) $superAdmin->two_factor_secret),
                'acknowledgement' => '1',
            ])->assertSessionHasErrors('owner_email');

        $this->assertNotNull($owner->fresh()->two_factor_confirmed_at);
        $this->assertNull($superAdmin->fresh()->two_factor_last_used_counter);
        Notification::assertNothingSent();
        $this->assertDatabaseMissing('audit_logs', ['event' => 'security.owner_mfa_recovered']);
    }

    public function test_owner_mfa_recovery_rejects_a_reused_superadmin_code(): void
    {
        $totp = app(TotpService::class);
        $superAdmin = $this->verifiedSuperAdmin();
        $currentCounter = intdiv(time(), 30);
        $superAdmin->forceFill(['two_factor_last_used_counter' => $currentCounter])->save();
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'two_factor_secret' => $totp->generateSecret(),
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->post(route('platform.tenants.owner-mfa.recover', [$tenant, $owner]), [
                'verification_method' => 'verified_phone_call',
                'support_reference' => 'SUP-20261013-003',
                'owner_email' => $owner->email,
                'password' => 'password',
                'code' => $totp->code((string) $superAdmin->two_factor_secret),
                'acknowledgement' => '1',
            ])->assertSessionHasErrors('code');

        $this->assertNotNull($owner->fresh()->two_factor_confirmed_at);
    }

    public function test_owner_mfa_recovery_rejects_the_wrong_superadmin_password(): void
    {
        $totp = app(TotpService::class);
        $superAdmin = $this->verifiedSuperAdmin();
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'two_factor_secret' => $totp->generateSecret(),
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->post(route('platform.tenants.owner-mfa.recover', [$tenant, $owner]), [
                'verification_method' => 'contractual_contact',
                'support_reference' => 'SUP-20261013-004',
                'owner_email' => $owner->email,
                'password' => 'senha-incorreta',
                'code' => $totp->code((string) $superAdmin->two_factor_secret),
                'acknowledgement' => '1',
            ])->assertSessionHasErrors('password');

        $this->assertNotNull($owner->fresh()->two_factor_confirmed_at);
        $this->assertNull($superAdmin->fresh()->two_factor_last_used_counter);
    }

    public function test_superadmin_cannot_recover_an_owner_through_another_tenant(): void
    {
        $superAdmin = $this->verifiedSuperAdmin();
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $otherTenant = Tenant::create(['name' => 'Outro cliente', 'slug' => 'outro-cliente']);
        $foreignOwner = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'owner']);

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->post(route('platform.tenants.owner-mfa.recover', [$tenant, $foreignOwner]))
            ->assertNotFound();
    }

    public function test_tenant_user_cannot_recover_an_owners_mfa(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        $this->actingAs($owner)
            ->post(route('platform.tenants.owner-mfa.recover', [$tenant, $owner]))
            ->assertForbidden();
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
