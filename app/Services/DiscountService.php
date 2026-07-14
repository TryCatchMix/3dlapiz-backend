<?php

namespace App\Services;

use App\Models\DiscountCode;
use Stripe\Coupon as StripeCoupon;
use Stripe\Stripe;

class DiscountService
{
    /**
     * Valida un código para un usuario dado.
     * Devuelve el código si es válido, lanza excepción con motivo si no.
     */
    public function validate(string $code, string $userId, float $subtotal): DiscountCode
    {
        $normalized = strtoupper(trim($code));

        $discount = DiscountCode::where('code', $normalized)
            ->where('active', true)
            ->first();

        if (!$discount) {
            throw new \InvalidArgumentException('Código no válido.');
        }

        if ($discount->isExpired()) {
            throw new \InvalidArgumentException('Este código ha caducado.');
        }

        if ($discount->isMaxUsesReached()) {
            throw new \InvalidArgumentException('Este código ya no se puede usar.');
        }

        if ($discount->isMaxUsesPerUserReached($userId)) {
            throw new \InvalidArgumentException('Ya has usado este código el máximo de veces.');
        }

        if ($discount->min_order_amount !== null && $subtotal < (float) $discount->min_order_amount) {
            throw new \InvalidArgumentException(
                "El pedido mínimo para este código es de {$discount->min_order_amount} €."
            );
        }

        return $discount;
    }

    /**
     * Calcula el importe del descuento a aplicar al subtotal.
     */
    public function amountFor(DiscountCode $discount, float $subtotal): float
    {
        return round($subtotal * ($discount->percentage / 100), 2);
    }

    /**
 * Devuelve el ID del cupón Stripe, creándolo si no existe todavía.
 */
public function getOrCreateStripeCoupon(DiscountCode $discount): string
{
    if ($discount->stripe_coupon_id) {
        return $discount->stripe_coupon_id;
    }

    Stripe::setApiKey(config('services.stripe.secret'));

    $coupon = StripeCoupon::create([
        'percent_off' => $discount->percentage,
        'duration'    => 'once',  // se aplica una sola vez por sesión
        'name'        => $discount->code,
        'metadata'    => [
            'discount_code_id' => $discount->id,
            'code'             => $discount->code,
        ],
    ]);

    $discount->update(['stripe_coupon_id' => $coupon->id]);

    return $coupon->id;
}
}
