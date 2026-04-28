<?php

namespace App\Services;

use App\Models\ShippingRate;

class ShippingService
{
    /**
     * Devuelve el precio de envío para un código ISO 3166-1 alpha-2.
     * Prioridad: 1) override en BD, 2) defaults config, 3) default global.
     */
    public function priceFor(string $countryCode): float
    {
        $code = strtoupper(trim($countryCode));

        if (!preg_match('/^[A-Z]{2}$/', $code)) {
            throw new \InvalidArgumentException('Código de país no válido.');
        }

        $override = ShippingRate::where('country_code', $code)->value('price');
        if ($override !== null) {
            return (float) $override;
        }

        $defaults = config('shipping.rates_by_country', []);
        if (array_key_exists($code, $defaults)) {
            return (float) $defaults[$code];
        }

        return (float) config('shipping.default_price');
    }
}
