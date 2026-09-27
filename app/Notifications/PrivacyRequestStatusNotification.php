<?php

namespace App\Notifications;

use App\Models\PrivacyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PrivacyRequestStatusNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly PrivacyRequest $privacyRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hours = max(1, (int) config('privacy.tracking_link_hours', 168));
        $url = URL::temporarySignedRoute(
            'privacy.requests.track',
            now()->addHours($hours),
            ['privacyRequest' => $this->privacyRequest->protocol],
        );

        $isFinal = in_array($this->privacyRequest->status, ['completed', 'rejected'], true);
        $messageLabel = match ($this->privacyRequest->status) {
            'completed' => 'Resposta final',
            'rejected' => 'Decisão e orientação',
            'awaiting_information' => 'Informações necessárias',
            default => 'Mensagem da equipe',
        };

        $message = (new MailMessage)
            ->subject(($isFinal ? 'Resposta da solicitação ' : 'Atualização da solicitação ').$this->privacyRequest->protocol)
            ->greeting('Olá, '.$this->privacyRequest->requester_name.'!')
            ->line($isFinal
                ? 'A análise da sua solicitação de privacidade foi concluída.'
                : 'Sua solicitação de privacidade foi atualizada.')
            ->line('Protocolo: '.$this->privacyRequest->protocol)
            ->line('Situação: '.$this->privacyRequest->statusLabel());

        if ($this->privacyRequest->requester_message) {
            $message->line($messageLabel.': '.$this->privacyRequest->requester_message);
        }

        return $message
            ->action('Acompanhar solicitação', $url)
            ->line("Por segurança, o link de acompanhamento expira em {$hours} horas.")
            ->line('Nunca enviaremos anotações internas, senhas ou códigos MFA por este canal.');
    }
}
