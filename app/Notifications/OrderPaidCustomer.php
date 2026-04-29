<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaidCustomer extends Notification
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
        $info = $this->order->shipping_info ?? [];

        return (new MailMessage)
            ->subject("Hemos recibido tu pedido #{$this->order->order_number}")
            ->greeting('¡Gracias por tu compra!')
            ->line("Tu pedido **#{$this->order->order_number}** se ha registrado correctamente.")
            ->line('**Total:** ' . number_format($this->order->total, 2, ',', '.') . ' €')
            ->line('**Enviaremos a:** ' . ($info['fullName'] ?? '') . ', ' . ($info['address'] ?? '') . ', ' . ($info['city'] ?? '') . ' (' . ($info['country_code'] ?? '') . ')')
            ->action('Ver mi pedido', $url)
            ->line('Te enviaremos otro correo en cuanto el pedido salga del taller con su número de seguimiento.');
    }
}
