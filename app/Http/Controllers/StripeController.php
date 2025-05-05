<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }


    public function createCheckoutSession(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0.50',
            'items.*.name' => 'required|string',
            'customer_email' => 'nullable|email',
        ]);

        $lineItems = [];

        foreach ($request->items as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $item['name'],
                    ],
                    'unit_amount' => (int)($item['price'] * 100),
                ],
                'quantity' => $item['quantity'],
            ];
        }

        try {
            $checkout_session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => route('stripe.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('stripe.cancel'),
                'customer_email' => $request->customer_email,
                'metadata' => [
                    'order_id' => uniqid('order_'),
                ],
            ]);

            $order = new Order();
            $order->stripe_session_id = $checkout_session->id;
            $order->status = 'pending';
            $order->total = array_sum(array_map(function($item) {
                return $item['price'] * $item['quantity'];
            }, $request->items));
            $order->save();

            return response()->json([
                'id' => $checkout_session->id,
                'url' => $checkout_session->url,
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Error al crear sesión de Stripe: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function handleWebhook(Request $request)
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
                    $order->status = 'paid';
                    $order->payment_intent = $session->payment_intent;
                    $order->save();
                }
                break;


            default:
                Log::info('Evento de Stripe no manejado: ' . $event->type);
        }

        return response()->json(['status' => 'success']);
    }

    public function success(Request $request)
    {
        $session_id = $request->get('session_id');

        try {
            $session = Session::retrieve($session_id);
            if ($session->payment_status === 'paid') {
                return response()->json(['status' => 'success', 'session' => $session]);
            }
        } catch (ApiErrorException $e) {
            Log::error('Error al verificar sesión: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function cancel()
    {
        return response()->json(['status' => 'cancelled']);
    }
}
