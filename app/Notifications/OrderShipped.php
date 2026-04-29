<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/') . '/orders/' . $this->order->id;

        return (new MailMessage)
            ->subject("Tu pedido #{$this->order->order_number} ha sido enviado")
            ->greeting('¡Tu pedido está en camino!')
            ->line("Hemos enviado tu pedido **#{$this->order->order_number}**.")
            ->line('**Transportista:** ' . ($this->order->shipping_carrier ?? '—'))
            ->line('**Número de seguimiento:** ' . ($this->order->tracking_number ?? '—'))
            ->action('Ver mi pedido', $url)
            ->line('Si tienes cualquier duda, responde a este correo.');
    }
}
