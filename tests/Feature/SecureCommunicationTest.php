<?php

namespace Tests\Feature;

use App\Models\Communication;
use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SecureCommunicationNotification;
use App\Services\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SecureCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_publishes_encrypted_message_only_to_selected_roles(): void
    {
        Notification::fake();
        [$tenant, $owner, $viewer] = $this->tenantUsers();
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->withSession(['mfa_verified_user_id' => $superAdmin->id])
            ->post(route('platform.communications.store'), [
                'tenant_id' => $tenant->id,
                'subject' => 'Manutenção confidencial',
                'body' => 'A janela ocorrerá às 23 horas.',
                'category' => 'maintenance',
                'priority' => 'high',
                'recipient_roles' => ['owner'],
                'requires_acknowledgement' => '1',
            ])
            ->assertRedirect(route('platform.communications.index'));

        $communication = Communication::firstOrFail();
        $this->assertSame('published', $communication->status);
        $this->assertTrue($communication->requires_acknowledgement);
        $this->assertNotSame('Manutenção confidencial', DB::table('communications')->value('subject'));
        $this->assertNotSame('A janela ocorrerá às 23 horas.', DB::table('communication_messages')->value('body'));
        $this->assertDatabaseHas('communication_recipients', ['communication_id' => $communication->id, 'user_id' => $owner->id]);
        $this->assertDatabaseMissing('communication_recipients', ['communication_id' => $communication->id, 'user_id' => $viewer->id]);

        Notification::assertSentTo($owner, SecureCommunicationNotification::class, function ($notification) use ($owner): bool {
            $mail = $notification->toMail($owner);
            $content = implode(' ', array_map('strval', $mail->introLines));

            return ! str_contains($content, 'Manutenção confidencial')
                && ! str_contains($content, '23 horas');
        });
        Notification::assertNotSentTo($viewer, SecureCommunicationNotification::class);
    }

    public function test_recipient_can_read_acknowledge_and_reply_but_other_users_cannot_access(): void
    {
        Notification::fake();
        [$tenant, $owner, $viewer] = $this->tenantUsers();
        $otherTenant = Tenant::create(['name' => 'Outra', 'slug' => 'outra']);
        $outsider = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'owner']);
        $superAdmin = $this->superAdmin();
        $communication = $this->communication($tenant, $superAdmin, $owner);

        $this->actingAs($viewer)->get(route('admin.communications.show', $communication))->assertNotFound();
        $this->actingAs($outsider)->get(route('admin.communications.show', $communication))->assertNotFound();

        $this->actingAs($owner)
            ->get(route('admin.communications.show', $communication))
            ->assertOk()
            ->assertSee('Conteúdo protegido do teste');
        $this->assertNotNull($communication->recipients()->where('user_id', $owner->id)->firstOrFail()->read_at);

        $this->actingAs($owner)
            ->post(route('admin.communications.acknowledge', $communication))
            ->assertSessionHas('success');
        $this->assertNotNull($communication->recipients()->where('user_id', $owner->id)->firstOrFail()->acknowledged_at);

        $this->actingAs($owner)
            ->post(route('admin.communications.reply', $communication), ['body' => 'Resposta privada do cliente.'])
            ->assertSessionHas('success');
        $this->assertNotSame('Resposta privada do cliente.', DB::table('communication_messages')->latest('id')->value('body'));
        Notification::assertSentTo($superAdmin, SecureCommunicationNotification::class);

        $owner->update(['role' => 'viewer']);
        $this->actingAs($owner)->get(route('admin.communications.show', $communication))->assertNotFound();
    }

    public function test_scheduled_communication_is_published_only_when_due(): void
    {
        Notification::fake();
        [$tenant, $owner] = $this->tenantUsers();
        $superAdmin = $this->superAdmin();
        $communication = Communication::create([
            'protocol' => 'COM-AGENDADA001',
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $superAdmin->id,
            'subject' => 'Manutenção futura',
            'category' => 'maintenance',
            'priority' => 'normal',
            'status' => 'scheduled',
            'recipient_roles' => ['owner'],
            'scheduled_at' => now()->addHour(),
        ]);
        $communication->messages()->create(['sender_user_id' => $superAdmin->id, 'sender_side' => 'platform', 'body' => 'Aviso agendado.']);

        $this->artisan('communications:publish')->assertSuccessful();
        $this->assertSame('scheduled', $communication->fresh()->status);
        Notification::assertNothingSent();

        $communication->update(['scheduled_at' => now()->subMinute()]);
        $this->artisan('communications:publish')->assertSuccessful();
        $this->assertSame('published', $communication->fresh()->status);
        Notification::assertSentTo($owner, SecureCommunicationNotification::class);
    }

    public function test_expired_communication_is_permanently_pruned_with_messages_and_recipients(): void
    {
        [$tenant, $owner] = $this->tenantUsers();
        $superAdmin = $this->superAdmin();
        $communication = $this->communication($tenant, $superAdmin, $owner);
        $communication->update(['expires_at' => now()->subMinute()]);

        $this->artisan('communications:prune')
            ->expectsOutput('1 comunicação(ões) removida(s) conforme a retenção.')
            ->assertSuccessful();

        $this->assertModelMissing($communication);
        $this->assertDatabaseCount('communication_messages', 0);
        $this->assertDatabaseCount('communication_recipients', 0);
    }

    public function test_push_subscription_is_encrypted_and_scoped_to_the_authenticated_user(): void
    {
        config([
            'webpush.public_key' => 'public-test-key',
            'webpush.private_key' => 'private-test-key',
            'webpush.allowed_endpoint_hosts' => ['push.example.test'],
        ]);
        [$tenant, $owner, $viewer] = $this->tenantUsers();
        $endpoint = 'https://push.example.test/send/device-secret-token';
        $payload = [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'browser-public-key', 'auth' => 'browser-auth-secret'],
            'contentEncoding' => 'aes128gcm',
        ];

        $this->actingAs($owner)
            ->postJson(route('admin.push.store'), $payload)
            ->assertCreated()
            ->assertJson(['enabled' => true]);

        $subscription = PushSubscription::firstOrFail();
        $this->assertSame($owner->id, $subscription->user_id);
        $this->assertSame($endpoint, $subscription->endpoint);
        $raw = DB::table('push_subscriptions')->first();
        $this->assertNotSame($endpoint, $raw->endpoint);
        $this->assertNotSame('browser-public-key', $raw->public_key);
        $this->assertNotSame('browser-auth-secret', $raw->auth_token);

        $this->actingAs($viewer)
            ->deleteJson(route('admin.push.destroy'), ['endpoint' => $endpoint])
            ->assertOk();
        $this->assertModelExists($subscription);

        $this->actingAs($viewer)
            ->postJson(route('admin.push.store'), $payload)
            ->assertConflict();

        $this->actingAs($owner)
            ->deleteJson(route('admin.push.destroy'), ['endpoint' => $endpoint])
            ->assertOk()
            ->assertJson(['enabled' => false]);
        $this->assertModelMissing($subscription);
    }

    public function test_push_endpoint_outside_the_allowlist_is_rejected(): void
    {
        config([
            'webpush.public_key' => 'public-test-key',
            'webpush.private_key' => 'private-test-key',
            'webpush.allowed_endpoint_hosts' => ['fcm.googleapis.com'],
        ]);
        [, $owner] = $this->tenantUsers();

        $this->actingAs($owner)->postJson(route('admin.push.store'), [
            'endpoint' => 'https://internal.example.test/collect',
            'keys' => ['p256dh' => 'browser-public-key', 'auth' => 'browser-auth-secret'],
            'contentEncoding' => 'aes128gcm',
        ])->assertUnprocessable()->assertJsonValidationErrors('endpoint');

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_push_payload_is_generic_and_does_not_contain_message_content(): void
    {
        [$tenant, $owner] = $this->tenantUsers();
        $superAdmin = $this->superAdmin();
        $communication = $this->communication($tenant, $superAdmin, $owner);

        $payload = app(WebPushSender::class)->payloadFor($communication);
        $serialized = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($communication->subject, $serialized);
        $this->assertStringNotContainsString('Conteúdo protegido do teste', $serialized);
        $this->assertSame('/painel/comunicacoes/'.$communication->protocol, $payload['url']);
        $this->assertSame('Você possui uma nova mensagem segura. Entre na plataforma para consultar.', $payload['body']);
    }

    public function test_push_button_waits_for_browser_check_and_panel_exposes_web_app_manifest(): void
    {
        config(['webpush.public_key' => 'configured-public-key']);
        [, $owner] = $this->tenantUsers();

        $this->actingAs($owner)
            ->withSession(['mfa_verified_user_id' => $owner->id])
            ->get(route('admin.communications.index'))
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('data-push-status aria-live="polite"', false)
            ->assertSee('data-push-toggle disabled', false);
    }

    private function tenantUsers(): array
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'viewer']);

        return [$tenant, $owner, $viewer];
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'tenant_id' => null,
            'role' => 'superadmin',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);
    }

    private function communication(Tenant $tenant, User $superAdmin, User $recipient): Communication
    {
        $communication = Communication::create([
            'protocol' => 'COM-TESTE000001',
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $superAdmin->id,
            'subject' => 'Assunto protegido',
            'category' => 'security',
            'priority' => 'critical',
            'status' => 'published',
            'recipient_roles' => ['owner'],
            'requires_acknowledgement' => true,
            'published_at' => now(),
            'expires_at' => now()->addDays(60),
        ]);
        $communication->messages()->create([
            'sender_user_id' => $superAdmin->id,
            'sender_side' => 'platform',
            'body' => 'Conteúdo protegido do teste.',
        ]);
        $communication->recipients()->create([
            'user_id' => $recipient->id,
            'delivered_at' => now(),
        ]);

        return $communication;
    }
}
