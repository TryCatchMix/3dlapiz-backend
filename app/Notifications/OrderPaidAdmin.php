<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaidAdmin extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $info = $this->order->shipping_info ?? [];
        $items = $this->order->items()->get();

        $mail = (new MailMessage)
            ->subject("Nueva venta — pedido #{$this->order->order_number}")
            ->greeting('Tienes una nueva venta')
            ->line("**Pedido:** #{$this->order->order_number}")
            ->line('**Cliente:** ' . ($info['fullName'] ?? '—') . ' (' . ($info['email'] ?? '—') . ')')
            ->line('**Teléfono:** ' . ($info['phone'] ?? '—'))
            ->line('**Dirección:** ' . ($info['address'] ?? '') . ', ' . ($info['city'] ?? '') . ' ' . ($info['postalCode'] ?? '') . ' — ' . ($info['country_code'] ?? ''))
            ->line('**Total cobrado:** ' . number_format($this->order->total, 2, ',', '.') . ' €')
            ->line('---')
            ->line('**Productos:**');

        foreach ($items as $it) {
            $variantTxt = $it->variant === 'unpainted' ? ' (sin pintar)' : '';
            $mail->line("• {$it->quantity}× {$it->product_name}{$variantTxt} — " . number_format($it->price, 2, ',', '.') . ' €');
        }

        return $mail;
    }
}
