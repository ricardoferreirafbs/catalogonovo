<?php

namespace App\Notifications;

use App\Models\Communication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecureCommunicationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Communication $communication,
        private readonly string $destination = 'tenant',
        private readonly bool $isReply = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $route = $this->destination === 'platform'
            ? route('platform.communications.show', $this->communication)
            : route('admin.communications.show', $this->communication);

        return (new MailMessage)
            ->subject($this->isReply ? 'Nova resposta na Central de Comunicação' : 'Nova comunicação segura disponível')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line($this->isReply
                ? 'Há uma nova resposta em uma comunicação segura da plataforma.'
                : 'Uma nova comunicação segura está disponível no Catalog.')
            ->line('Protocolo: '.$this->communication->protocol)
            ->action('Abrir comunicação segura', $route)
            ->line('Por segurança, o conteúdo não é enviado por e-mail. Entre na plataforma para consultar e responder.')
            ->line('Nunca informe senha, código MFA ou segredo de autenticação em uma mensagem.');
    }
}
