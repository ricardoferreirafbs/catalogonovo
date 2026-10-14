<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyOwnerEmailNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = max(5, (int) config('security.owner_email_verification_minutes', 60));
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($minutes),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        return (new MailMessage)
            ->subject('Confirme seu e-mail de Proprietário · '.config('app.name'))
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Confirme que este e-mail pertence ao Proprietário responsável pelo catálogo de '.$notifiable->tenant->name.'.')
            ->action('Confirmar meu e-mail', $url)
            ->line("Este link expira em {$minutes} minutos e não concede acesso direto ao painel.")
            ->line('Depois da confirmação, entre com sua senha e conclua o MFA obrigatório.')
            ->line('Se você não reconhece este cadastro, não confirme o endereço e comunique o suporte. Nunca envie senha ou códigos MFA.');
    }
}
