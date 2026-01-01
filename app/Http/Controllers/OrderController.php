<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Notifications\OrderStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function checkout(Request $request)
    {
        $cart = Cart::with('items.product')->where('user_id', auth()->id())->firstOrFail();

        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        try {
            $lineItems = [];
            foreach ($cart->items as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $item->product->name,
                            'images' => [$item->product->image_url],
                        ],
                        'unit_amount' => (int)($item->price * 100),
                    ],
                    'quantity' => $item->quantity,
                ];
            }

            $order = DB::transaction(function () use ($cart) {
                $order = Order::create([
                    'user_id' => $cart->user_id,
                    'status' => 'pending',
                    'total' => $cart->items->sum(fn($item) => $item->quantity * $item->price)
                ]);

                foreach ($cart->items as $item) {
                    $order->items()->create([
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'product_name' => $item->product->name,
                        'product_image' => $item->product->image_url,
                    ]);
                }

                return $order;
            });

            $checkout_session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => route('stripe.success') . '?session_id={CHECKOUT_SESSION_ID}&order_id=' . $order->id,
                'cancel_url' => route('stripe.cancel') . '?order_id=' . $order->id,
                'customer_email' => auth()->user()->email ?? null,
                'metadata' => [
                    'order_id' => $order->id,
                ],
            ]);

            $order->update([
                'stripe_session_id' => $checkout_session->id,
                'payment_status' => 'pending'
            ]);

            DB::transaction(function () use ($cart) {
                $cart->items()->delete();
                $cart->delete();
            });

            return response()->json([
                'order' => $order->load('items.product'),
                'checkout_url' => $checkout_session->url
            ], 201);

        } catch (ApiErrorException $e) {
            Log::error('Stripe error: ' . $e->getMessage());
            return response()->json(['message' => 'Payment processing error', 'error' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error('Order creation error: ' . $e->getMessage());
            return response()->json(['message' => 'Order creation failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateStatus(Order $order, Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,paid,shipped,delivered,cancelled'
        ]);

        $order->update($validated);

        $order->user->notify(new OrderStatusNotification($order));

        return response()->json(['message' => 'Order status updated', 'order' => $order->load('items.product')]);
    }

    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;

                $order = Order::where('stripe_session_id', $session->id)->first();
                if ($order) {
                    $order->update([
                        'status' => 'paid',
                        'payment_status' => 'paid',
                        'payment_intent' => $session->payment_intent
                    ]);

                    $order->user->notify(new OrderStatusNotification($order));
                }
                break;

            case 'checkout.session.expired':
                $session = $event->data->object;
                $order = Order::where('stripe_session_id', $session->id)->first();
                if ($order) {
                    $order->update([
                        'payment_status' => 'failed'
                    ]);
                }
                break;

            default:
                Log::info('Unhandled Stripe event: ' . $event->type);
        }

        return response()->json(['status' => 'success']);
    }

    public function stripeSuccess(Request $request)
    {
        $sessionId = $request->get('session_id');
        $orderId = $request->get('order_id');

        try {
            $session = Session::retrieve($sessionId);
            $order = Order::findOrFail($orderId);

            if ($session->payment_status === 'paid' && $order->payment_status !== 'paid') {
                $order->update([
                    'status' => 'paid',
                    'payment_status' => 'paid',
                    'payment_intent' => $session->payment_intent
                ]);

                $order->user->notify(new OrderStatusNotification($order));
            }

            return response()->json([
                'success' => true,
                'order' => $order->load('items.product')
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Stripe session verification error: ' . $e->getMessage());
            return response()->json(['message' => 'Payment verification failed'], 500);
        }
    }

    public function stripeCancel(Request $request)
    {
        $orderId = $request->get('order_id');

        if ($orderId) {
            $order = Order::find($orderId);
            if ($order && $order->payment_status === 'pending') {
                $order->update([
                    'payment_status' => 'cancelled'
                ]);
            }
        }

        return response()->json([
            'status' => 'cancelled',
            'message' => 'Payment was cancelled'
        ]);
    }

    public function getUserOrders()
    {
        $orders = Order::where('user_id', auth()->id())
            ->with(['items.product'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    public function getOrder(Order $order)
    {
        // Verificar que el pedido pertenece al usuario autenticado
        if ($order->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado'
            ], 403);
        }

        $order->load('items.product');

        return response()->json([
            'success' => true,
            'order' => $order
        ]);
    }

    public function cancelOrder(Order $order, Request $request)
    {
        // Verificar que el pedido pertenece al usuario autenticado
        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if (!in_array($order->status, ['pending', 'processing'])) {
            return response()->json(['message' => 'Este pedido no puede ser cancelado'], 400);
        }

        $order->update([
            'status' => 'cancelled',
            'payment_status' => 'cancelled'
        ]);

        $order->user->notify(new OrderStatusNotification($order));

        return response()->json([
            'message' => 'Pedido cancelado correctamente',
            'order' => $order->load('items.product')
        ]);
    }
}
