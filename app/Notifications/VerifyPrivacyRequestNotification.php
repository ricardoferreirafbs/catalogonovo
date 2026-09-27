<?php

namespace App\Notifications;

use App\Models\PrivacyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyPrivacyRequestNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly PrivacyRequest $privacyRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hours = max(1, (int) config('privacy.verification_hours', 24));
        $url = URL::temporarySignedRoute(
            'privacy.requests.verify',
            now()->addHours($hours),
            ['privacyRequest' => $this->privacyRequest->protocol],
        );

        return (new MailMessage)
            ->subject('Confirme sua solicitação de privacidade · '.config('app.name'))
            ->greeting('Olá, '.$this->privacyRequest->requester_name.'!')
            ->line('Recebemos uma solicitação relacionada aos seus dados pessoais.')
            ->line('Protocolo: '.$this->privacyRequest->protocol)
            ->action('Confirmar endereço de e-mail', $url)
            ->line("O link expira em {$hours} horas. A análise começa somente depois dessa confirmação.")
            ->line('Se você não fez essa solicitação, ignore esta mensagem.');
    }
}
