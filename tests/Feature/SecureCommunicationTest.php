<?php

namespace Tests\Feature;

use App\Models\Communication;
use App\Models\CommunicationRecipient;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SecureCommunicationNotification;
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
