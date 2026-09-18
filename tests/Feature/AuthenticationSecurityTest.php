<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_request_is_generic_and_sends_a_notification_to_known_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'cliente@example.com']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('success', 'Se o e-mail estiver cadastrado, enviaremos as instruções para redefinir a senha.');

        $this->post(route('password.email'), ['email' => 'desconhecido@example.com'])
            ->assertSessionHas('success', 'Se o e-mail estiver cadastrado, enviaremos as instruções para redefinir a senha.');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_user_can_reset_password_with_a_strong_password(): void
    {
        $user = User::factory()->create(['email' => 'cliente@example.com']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Nova-Senha2026!',
            'password_confirmation' => 'Nova-Senha2026!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Nova-Senha2026!', $user->fresh()->password));
    }

    public function test_weak_password_is_rejected_during_reset(): void
    {
        $user = User::factory()->create(['email' => 'cliente@example.com']);

        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'senha-fraca',
            'password_confirmation' => 'senha-fraca',
        ])->assertSessionHasErrors('password');
    }

    public function test_superadmin_must_configure_mfa_before_accessing_platform(): void
    {
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => 'superadmin']);

        $this->actingAs($superAdmin)
            ->get(route('platform.dashboard'))
            ->assertRedirect(route('platform.mfa.setup'));
    }

    public function test_superadmin_can_configure_totp_and_receives_hashed_recovery_codes(): void
    {
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => 'superadmin']);
        $totp = app(TotpService::class);

        $this->actingAs($superAdmin)->get(route('platform.mfa.setup'))->assertOk();
        $superAdmin->refresh();

        $response = $this->post(route('platform.mfa.confirm'), [
            'code' => $totp->code($superAdmin->two_factor_secret),
        ]);

        $response->assertRedirect(route('platform.mfa.setup'));
        $response->assertSessionHas('recovery_codes');
        $superAdmin->refresh();
        $this->assertNotNull($superAdmin->two_factor_confirmed_at);
        $this->assertCount(8, $superAdmin->two_factor_recovery_codes);
        $this->assertNotEqualsCanonicalizing(
            session('recovery_codes'),
            $superAdmin->two_factor_recovery_codes
        );
    }

    public function test_confirmed_superadmin_must_complete_mfa_challenge_after_login(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role' => 'superadmin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Senha-Segura2026!'),
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('login.store'), [
            'email' => $superAdmin->email,
            'password' => 'Senha-Segura2026!',
        ])->assertRedirect(route('mfa.challenge'));

        $this->get(route('platform.dashboard'))->assertRedirect(route('mfa.challenge'));

        $this->post(route('mfa.verify'), [
            'code' => $totp->code($secret),
        ])->assertRedirect(route('platform.dashboard'));

        $this->get(route('platform.dashboard'))->assertOk();
    }

    public function test_recovery_code_can_only_be_used_once(): void
    {
        $totp = app(TotpService::class);
        $recoveryCode = 'ABCD-1234';
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role' => 'superadmin',
            'two_factor_secret' => $totp->generateSecret(),
            'two_factor_recovery_codes' => [$totp->hashRecoveryCode($recoveryCode)],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($superAdmin)->post(route('mfa.verify'), ['code' => $recoveryCode])
            ->assertRedirect(route('platform.dashboard'));

        $this->assertSame([], $superAdmin->fresh()->two_factor_recovery_codes);

        $this->withSession(['mfa_verified_user_id' => null])
            ->post(route('mfa.verify'), ['code' => $recoveryCode])
            ->assertSessionHasErrors('code');
    }

    public function test_totp_matches_the_rfc_hotp_counter_zero_vector(): void
    {
        $totp = app(TotpService::class);

        $this->assertSame('755224', $totp->code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 0));
        $this->assertTrue($totp->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '755224', 0, 0));
    }

    public function test_mutating_requests_are_recorded_without_form_contents(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.menus.store'), [
            'label' => 'Catálogo',
            'url' => '#catalogo',
            'is_active' => true,
        ])->assertSessionDoesntHaveErrors();

        $log = AuditLog::where('event', 'admin.menus.store')->firstOrFail();
        $this->assertSame($owner->id, $log->actor_user_id);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertSame('success', data_get($log->metadata, 'outcome'));
        $this->assertStringNotContainsString('Catálogo', json_encode($log->metadata));
        $this->assertStringNotContainsString('#catalogo', json_encode($log->metadata));
    }

    public function test_rejected_mutation_is_recorded_without_the_malicious_value(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.menus.store'), [
            'label' => 'Tentativa',
            'url' => 'javascript://alert(1)',
        ])->assertSessionHasErrors('url');

        $log = AuditLog::where('event', 'admin.menus.store')->firstOrFail();
        $this->assertSame('rejected', data_get($log->metadata, 'outcome'));
        $this->assertSame(302, $log->status);
        $this->assertStringNotContainsString('javascript', json_encode($log->metadata));
    }

    public function test_failed_and_successful_logins_are_distinguished_in_audit_log(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'email' => 'auditoria@example.com',
            'password' => Hash::make('Senha-Segura2026!'),
        ]);

        $this->post(route('login.store'), [
            'email' => $owner->email,
            'password' => 'incorreta',
        ])->assertSessionHasErrors('email');

        $failed = AuditLog::where('event', 'login.store')->latest('id')->firstOrFail();
        $this->assertSame('rejected', data_get($failed->metadata, 'outcome'));
        $this->assertNull($failed->actor_user_id);

        $this->post(route('login.store'), [
            'email' => $owner->email,
            'password' => 'Senha-Segura2026!',
        ])->assertRedirect(route('admin.dashboard'));

        $successful = AuditLog::where('event', 'login.store')->latest('id')->firstOrFail();
        $this->assertSame('success', data_get($successful->metadata, 'outcome'));
        $this->assertSame($owner->id, $successful->actor_user_id);
        $this->assertStringNotContainsString('Senha-Segura2026!', json_encode($successful->metadata));
    }

    public function test_old_audit_records_can_be_pruned_by_retention_policy(): void
    {
        AuditLog::create($this->auditData(['created_at' => now()->subDays(200)]));
        AuditLog::create($this->auditData(['created_at' => now()->subDays(10)]));

        $this->artisan('audit:prune', ['--days' => 180])->assertSuccessful();

        $this->assertDatabaseCount('audit_logs', 1);
    }

    private function auditData(array $overrides = []): array
    {
        return array_merge([
            'event' => 'test.event',
            'method' => 'POST',
            'path' => 'teste',
            'status' => 200,
            'metadata' => ['outcome' => 'success'],
        ], $overrides);
    }
}
