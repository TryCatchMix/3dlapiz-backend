<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use Illuminate\Http\Request;
use Stripe\Coupon as StripeCoupon;
use Stripe\Stripe;

class AdminDiscountCodeController extends Controller
{
    public function index()
    {
        return DiscountCode::orderByDesc('created_at')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9\-]{3,20}$/'],
            'percentage' => ['required', 'integer', 'min:1', 'max:99'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['active'] = true;

        // Comprobación única sobre el código normalizado
        if (DiscountCode::where('code', $validated['code'])->exists()) {
            return response()->json(['message' => 'Ya existe un código con ese nombre.'], 422);
        }

        $discount = DiscountCode::create($validated);

        return response()->json($discount, 201);
    }

    public function update(Request $request, string $id)
    {
        $discount = DiscountCode::findOrFail($id);

        $validated = $request->validate([
            'percentage' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'min_order_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_uses' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $discount->update($validated);

        return response()->json($discount);
    }

    public function destroy(string $id)
{
    $discount = DiscountCode::findOrFail($id);

    // Borrar cupón en Stripe si existe
    if ($discount->stripe_coupon_id) {
        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            StripeCoupon::retrieve($discount->stripe_coupon_id)->delete();
        } catch (\Throwable $e) {
            // Log pero no falla la operación

        }
    }

    if ($discount->used_count > 0) {
        $discount->update(['active' => false]);
        return response()->json(['message' => 'Código desactivado (tenía usos).']);
    }

    $discount->delete();
    return response()->json(['message' => 'Código eliminado.']);
}
}
