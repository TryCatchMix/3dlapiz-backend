<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\OrderShipped;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['items', 'user'])->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('id', $search);
            });
        }

        return response()->json($query->paginate($request->input('per_page', 25)));
    }

    public function show(string $id)
    {
        $order = Order::with(['items', 'user'])->findOrFail($id);
        return response()->json($order);
    }

    public function setTracking(Request $request, string $id)
    {
        $validated = $request->validate([
            'tracking_number'  => 'required|string|max:100',
            'shipping_carrier' => 'required|string|max:50',
        ]);

        $order = Order::with('user')->findOrFail($id);

        $order->update([
            'tracking_number'  => $validated['tracking_number'],
            'shipping_carrier' => $validated['shipping_carrier'],
            'status'           => 'shipped',
            'shipped_at'       => now(),
        ]);

        try {
            if ($order->user) {
                $order->user->notify(new OrderShipped($order));
            }
        } catch (\Throwable $e) {
            Log::error('Error enviando notificación de envío: ' . $e->getMessage(), ['order_id' => $order->id]);
        }

        return response()->json($order->fresh(['items', 'user']));
    }

    public function updateStatus(Request $request, string $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,paid,shipped,delivered,cancelled',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $validated['status']]);

        return response()->json($order->fresh());
    }
}
