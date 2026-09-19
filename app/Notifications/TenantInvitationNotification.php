<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $tenantName,
        private readonly string $inviterName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Convite para o catálogo · '.config('app.name'))
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line($this->inviterName.' convidou você para colaborar no catálogo de '.$this->tenantName.'.')
            ->line('Use o botão abaixo para criar sua senha e concluir o acesso.')
            ->action('Aceitar convite', $url)
            ->line("Este link expira em {$minutes} minutos e só pode ser utilizado uma vez.")
            ->line('Se você não reconhece este convite, ignore esta mensagem.');
    }
}
