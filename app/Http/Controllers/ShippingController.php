<?php

namespace App\Http\Controllers;

use App\Services\ShippingService;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function calculate(Request $request, ShippingService $shipping)
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
        ]);

        try {
            $price = $shipping->priceFor($validated['country_code']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'country_code' => strtoupper($validated['country_code']),
            'price' => $price,
        ]);
    }
}
