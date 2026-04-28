<?php

return [
    'default_price' => env('SHIPPING_DEFAULT_PRICE', 70),
    'rates_by_country' => [
        'ES' => env('SHIPPING_PRICE_ES', 20),
    ],
];
