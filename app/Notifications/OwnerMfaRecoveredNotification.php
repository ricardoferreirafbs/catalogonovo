<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnerMfaRecoveredNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $tenantName,
        private readonly string $protocol,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Recuperação de MFA concluída · '.config('app.name'))
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('O segundo fator da sua conta de Proprietário em '.$this->tenantName.' foi redefinido pela administração da plataforma.')
            ->line('Protocolo: '.$this->protocol)
            ->line('Todas as sessões e assinaturas de notificação vinculadas à conta foram encerradas.')
            ->line('Entre novamente com sua senha e cadastre um novo autenticador antes de acessar o painel.')
            ->action('Entrar e configurar novo MFA', route('login'))
            ->line('Se você não reconhece esta recuperação, contate imediatamente o suporte e informe apenas o protocolo. Nunca envie sua senha ou códigos MFA.');
    }
}
