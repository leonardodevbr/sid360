<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notificação de teste para validar o envio de Web Push.
 * Disparada via POST /api/push/test (usuário autenticado).
 */
class TestPushNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Teste de notificação – Sid360')
            ->body('Se você está vendo esta mensagem, as notificações push estão funcionando.')
            ->action('Abrir sistema', 'open_app')
            ->data(['url' => config('app.url', '/')]);
    }
}
