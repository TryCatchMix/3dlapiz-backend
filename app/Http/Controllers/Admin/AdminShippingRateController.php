<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingRate;
use Illuminate\Http\Request;

class AdminShippingRateController extends Controller
{
    public function index()
    {
        return ShippingRate::orderBy('country_code')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/', 'unique:shipping_rates,country_code'],
            'price' => 'required|numeric|min:0',
        ]);

        $rate = ShippingRate::create($validated);
        return response()->json($rate, 201);
    }

    public function update(Request $request, string $id)
    {
        $rate = ShippingRate::findOrFail($id);

        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $rate->update($validated);
        return response()->json($rate);
    }

    public function destroy(string $id)
    {
        $rate = ShippingRate::findOrFail($id);
        $rate->delete();
        return response()->json(['message' => 'Override eliminado, vuelve al precio por defecto.']);
    }
}
