<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripeController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createCheckoutSession(Request $request, User $user)
    {
        $request->validate([
            'shipping_info' => 'required|array',
            'shipping_info.fullName' => 'required|string',
            'shipping_info.email' => 'required|email',
            'shipping_info.address' => 'required|string',
            'shipping_info.city' => 'required|string',
            'shipping_info.postalCode' => 'required|string',
            'shipping_info.country' => 'required|string',
            'shipping_info.phone' => 'required|string',
            'shipping_method' => 'required|array',
            'shipping_method.id' => 'required',
            'shipping_method.name' => 'required|string',
            'shipping_method.price' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0.50',
            'items.*.name' => 'required|string',
            'total' => 'required|numeric|min:0.50',
            'success_url' => 'required|string',
            'cancel_url' => 'required|string'
        ]);

        // Obtener el usuario autenticado
        $authenticatedUser = $request->user();

        try {
            return DB::transaction(function () use ($request, $authenticatedUser) {
                // Crear la orden primero
                $order = Order::create([
                    'user_id' => $authenticatedUser->id,
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'total' => $request->total,
                    'shipping_info' => json_encode($request->shipping_info),
                    'shipping_method' => json_encode($request->shipping_method)
                ]);

                // Crear los items de la orden
                foreach ($request->items as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'product_name' => $item['name'],
                        'product_image' => $item['image_url'] ?? null
                    ]);
                }

                // Preparar line items para Stripe
                $lineItems = $this->buildLineItems($request);

                // Crear sesión de Stripe
                $checkout_session = Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => $lineItems,
                    'mode' => 'payment',
                    'success_url' => $request->success_url,
                    'cancel_url' => $request->cancel_url,
                    'customer_email' => $request->shipping_info['email'],
                    'shipping_address_collection' => [
                        'allowed_countries' => ['ES', 'FR', 'DE', 'IT', 'PT'], // Países europeos
                    ],
                    'metadata' => [
                        'order_id' => $order->id,
                        'user_id' => $authenticatedUser->id
                    ],
                ]);

                // Actualizar orden con session_id
                $order->update([
                    'stripe_session_id' => $checkout_session->id
                ]);

                return response()->json([
                    'success' => true,
                    'session_id' => $checkout_session->id,
                    'order_id' => $order->id
                ]);
            });

        } catch (ApiErrorException $e) {
            Log::error('Stripe API Error: ' . $e->getMessage(), [
                'user_id' => $authenticatedUser->id,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el pago',
                'error' => $e->getMessage()
            ], 500);

        } catch (\Exception $e) {
            Log::error('Order creation error: ' . $e->getMessage(), [
                'user_id' => $authenticatedUser->id,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la orden',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function confirmPayment(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string'
        ]);

        try {
            $session = Session::retrieve($request->session_id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesión no encontrada'
                ], 404);
            }

            $order = Order::where('stripe_session_id', $session->id)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Orden no encontrada'
                ], 404);
            }

            if ($session->payment_status === 'paid' && $order->payment_status !== 'paid') {
                $order->update([
                    'status' => 'paid',
                    'payment_status' => 'paid',
                    'payment_intent' => $session->payment_intent
                ]);

                // Aquí puedes agregar notificaciones, emails, etc.
            }

            return response()->json([
                'success' => true,
                'order' => $order->load('items')
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Stripe confirmation error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar el pago'
            ], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid webhook payload');
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Invalid webhook signature');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($event->data->object);
                break;

            case 'checkout.session.expired':
                $this->handleCheckoutExpired($event->data->object);
                break;

            default:
                Log::info('Unhandled Stripe event: ' . $event->type);
        }

        return response()->json(['status' => 'success']);
    }

    private function handleCheckoutCompleted($session)
    {
        $order = Order::where('stripe_session_id', $session->id)->first();

        if ($order && $order->payment_status !== 'paid') {
            $order->update([
                'status' => 'paid',
                'payment_status' => 'paid',
                'payment_intent' => $session->payment_intent
            ]);

            Log::info('Order marked as paid', ['order_id' => $order->id]);
        }
    }

    private function handleCheckoutExpired($session)
    {
        $order = Order::where('stripe_session_id', $session->id)->first();

        if ($order && $order->payment_status === 'pending') {
            $order->update([
                'payment_status' => 'failed'
            ]);

            Log::info('Order marked as failed due to session expiry', ['order_id' => $order->id]);
        }
    }

    public function success(Request $request)
    {
        $session_id = $request->get('session_id');

        try {
            $session = Session::retrieve($session_id);

            return response()->json([
                'status' => 'success',
                'session' => $session,
                'payment_status' => $session->payment_status
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Error retrieving session: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function cancel()
    {
        return response()->json([
            'status' => 'cancelled',
            'message' => 'Pago cancelado por el usuario'
        ]);
    }

    /**
     * Construye los line items para Stripe basado en la request
     */
    private function buildLineItems(Request $request): array
    {
        $lineItems = [];

        // Agregar productos
        foreach ($request->items as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $item['name'],
                        'images' => isset($item['image_url']) ? [$item['image_url']] : [],
                    ],
                    'unit_amount' => (int)($item['price'] * 100),
                ],
                'quantity' => $item['quantity'],
            ];
        }

        // Agregar envío como un line item separado
        if ($request->shipping_method['price'] > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $request->shipping_method['name'],
                    ],
                    'unit_amount' => (int)($request->shipping_method['price'] * 100),
                ],
                'quantity' => 1,
            ];
        }

        return $lineItems;
    }
}
