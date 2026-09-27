<?php

namespace Tests\Feature;

use App\Models\PrivacyRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\VerifyPrivacyRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PrivacyRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_privacy_page_exposes_the_rights_channel(): void
    {
        config([
            'privacy.controller_name' => 'Empresa Responsável Ltda.',
            'privacy.contact_email' => 'privacidade@example.com',
        ]);

        $this->get(route('privacy.index'))
            ->assertOk()
            ->assertSee('Seus dados, seus direitos.')
            ->assertSee('Empresa Responsável Ltda.')
            ->assertSee('privacidade@example.com')
            ->assertSee('Enviar solicitação');
    }

    public function test_request_is_encrypted_and_requires_email_confirmation(): void
    {
        Notification::fake();

        $response = $this->post(route('privacy.requests.store'), [
            'name' => 'Titular de Teste',
            'email' => 'TITULAR@EXAMPLE.COM',
            'request_type' => 'confirmation_access',
            'scope' => 'platform',
            'details' => 'Quero confirmar os dados vinculados à minha conta.',
            'privacy_acknowledgement' => '1',
        ]);

        $privacyRequest = PrivacyRequest::firstOrFail();
        $response->assertRedirect(route('privacy.index'))
            ->assertSessionHas('success');
        $this->assertSame('titular@example.com', $privacyRequest->requester_email);
        $this->assertSame('awaiting_verification', $privacyRequest->status);
        $this->assertNull($privacyRequest->email_verified_at);
        $this->assertNotSame('titular@example.com', DB::table('privacy_requests')->value('requester_email'));
        Notification::assertSentTo($privacyRequest, VerifyPrivacyRequestNotification::class);
    }

    public function test_signed_confirmation_verifies_the_request(): void
    {
        $privacyRequest = $this->privacyRequest();
        $url = URL::temporarySignedRoute(
            'privacy.requests.verify',
            now()->addHour(),
            ['privacyRequest' => $privacyRequest->protocol],
        );

        $this->get($url)->assertRedirect(route('privacy.index'));

        $privacyRequest->refresh();
        $this->assertSame('verified', $privacyRequest->status);
        $this->assertNotNull($privacyRequest->email_verified_at);
    }

    public function test_unsigned_confirmation_link_is_rejected(): void
    {
        $privacyRequest = $this->privacyRequest();

        $this->get(route('privacy.requests.verify', $privacyRequest->protocol))
            ->assertForbidden();

        $this->assertNull($privacyRequest->fresh()->email_verified_at);
    }

    public function test_privacy_management_is_restricted_and_audited(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $superAdmin = $this->verifiedSuperAdmin();
        $privacyRequest = $this->privacyRequest();

        $this->actingAs($owner)->get(route('platform.privacy.index'))->assertForbidden();

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->get(route('platform.privacy.show', $privacyRequest))
            ->assertOk()
            ->assertSee($privacyRequest->protocol)
            ->assertSee('titular@example.com');

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $superAdmin->id,
            'event' => 'platform.privacy.show',
        ]);
    }

    public function test_completed_request_records_decision_and_retention(): void
    {
        $superAdmin = $this->verifiedSuperAdmin();
        $privacyRequest = $this->privacyRequest(['status' => 'verified', 'email_verified_at' => now()]);

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->patch(route('platform.privacy.update', $privacyRequest), [
                'status' => 'completed',
                'internal_notes' => 'Identidade validada e declaração enviada por canal seguro.',
            ])
            ->assertRedirect();

        $privacyRequest->refresh();
        $this->assertSame('completed', $privacyRequest->status);
        $this->assertSame($superAdmin->id, $privacyRequest->reviewed_by_user_id);
        $this->assertNotNull($privacyRequest->completed_at);
        $this->assertTrue($privacyRequest->retention_until->between(now()->addDays(729), now()->addDays(731)));
    }

    public function test_unverified_request_cannot_advance_to_review(): void
    {
        $superAdmin = $this->verifiedSuperAdmin();
        $privacyRequest = $this->privacyRequest();

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->patch(route('platform.privacy.update', $privacyRequest), [
                'status' => 'in_review',
                'internal_notes' => 'Tentativa antes da confirmação.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('awaiting_verification', $privacyRequest->fresh()->status);
    }

    public function test_privacy_prune_removes_only_expired_requests(): void
    {
        $expired = $this->privacyRequest(['protocol' => 'LGPD-EXPIRED1', 'retention_until' => now()->subMinute()]);
        $active = $this->privacyRequest(['protocol' => 'LGPD-ACTIVE01', 'retention_until' => now()->addDay()]);

        $this->artisan('privacy:prune')
            ->expectsOutput('1 solicitação(ões) de privacidade removida(s) conforme a retenção.')
            ->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelExists($active);
    }

    private function privacyRequest(array $attributes = []): PrivacyRequest
    {
        $email = 'titular@example.com';

        return PrivacyRequest::create(array_merge([
            'protocol' => 'LGPD-TESTE00001',
            'request_type' => 'confirmation_access',
            'scope' => 'platform',
            'requester_name' => 'Titular de Teste',
            'requester_email' => $email,
            'requester_email_hash' => hash_hmac('sha256', $email, (string) config('app.key')),
            'details' => 'Solicitação de teste.',
            'status' => 'awaiting_verification',
            'retention_until' => now()->addDays(30),
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
