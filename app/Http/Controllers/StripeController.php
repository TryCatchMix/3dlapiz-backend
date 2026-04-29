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

    public function createCheckoutSession(Request $request, \App\Services\ShippingService $shipping)
{
    $validated = $request->validate([
        'shipping_info' => 'required|array',
        'shipping_info.fullName' => 'required|string|max:120',
        'shipping_info.email' => 'required|email',
        'shipping_info.address' => 'required|string|max:255',
        'shipping_info.city' => 'required|string|max:100',
        'shipping_info.postalCode' => 'required|string|max:20',
        'shipping_info.country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
        'shipping_info.phone' => 'required|string|max:30',

        'items' => 'required|array|min:1',
        'items.*.product_id' => 'required|exists:products,id',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.variant' => 'nullable|in:painted,unpainted',
    ]);

    $user = $request->user();

    // 1) Precio de envío recalculado en backend
    try {
        $shippingPrice = $shipping->priceFor($validated['shipping_info']['country_code']);
    } catch (\InvalidArgumentException $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }

    // 2) Resolver productos y calcular precios reales (nunca confiar en el frontend)
    $productIds = collect($validated['items'])->pluck('product_id')->unique()->values();
    $products = \App\Models\Product::with('images')->whereIn('id', $productIds)->get()->keyBy('id');

    $resolvedItems = [];
    $subtotal = 0;

    foreach ($validated['items'] as $item) {
        $product = $products->get($item['product_id']);
        if (!$product) {
            return response()->json(['error' => "Producto no encontrado: {$item['product_id']}"], 422);
        }
        if ($product->stock < $item['quantity']) {
            return response()->json(['error' => "Stock insuficiente para {$product->name}"], 422);
        }

        $variant = $item['variant'] ?? 'painted';
        try {
            $price = $product->priceForVariant($variant);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $resolvedItems[] = compact('product', 'variant', 'price') + ['quantity' => $item['quantity']];
        $subtotal += $price * $item['quantity'];
    }

    $total = $subtotal + $shippingPrice;

    if ($total < 0.5) {
        return response()->json(['error' => 'El total debe ser al menos 0,50€.'], 422);
    }

    // 3) Crear orden + sesión Stripe en transacción
    try {
        return DB::transaction(function () use ($user, $validated, $resolvedItems, $shippingPrice, $total) {
            $order = Order::create([
                'user_id'        => $user->id,
                'status'         => 'pending',
                'payment_status' => 'pending',
                'total'          => $total,
                'shipping_info'  => $validated['shipping_info'],
                'shipping_method' => [
                    'country_code' => strtoupper($validated['shipping_info']['country_code']),
                    'price'        => $shippingPrice,
                ],
            ]);

            $lineItems = [];

            foreach ($resolvedItems as $r) {
                /** @var \App\Models\Product $product */
                $product = $r['product'];

                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $product->id,
                    'quantity'      => $r['quantity'],
                    'variant'       => $r['variant'],
                    'price'         => $r['price'],
                    'product_name'  => $product->name,
                    'product_image' => optional($product->images->first())->image_url,
                ]);

                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $product->name . ($r['variant'] === 'unpainted' ? ' (sin pintar)' : ''),
                        ],
                        'unit_amount' => (int) round($r['price'] * 100),
                    ],
                    'quantity' => $r['quantity'],
                ];
            }

            if ($shippingPrice > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => ['name' => 'Envío'],
                        'unit_amount' => (int) round($shippingPrice * 100),
                    ],
                    'quantity' => 1,
                ];
            }

            $session = Session::create([
    'payment_method_types' => ['card'],
    'line_items'           => $lineItems,
    'mode'                 => 'payment',
    'success_url'          => rtrim(config('app.frontend_url'), '/') . '/checkout?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'           => rtrim(config('app.frontend_url'), '/') . '/cart',
    'customer_email'       => $user->email,
]);

            $order->update(['stripe_session_id' => $session->id]);

            return response()->json(['session_id' => $session->id]);
        });
    } catch (ApiErrorException $e) {
        Log::error('Stripe error: ' . $e->getMessage());
        return response()->json(['error' => 'Error procesando el pago.'], 500);
    } catch (\Exception $e) {
        Log::error('Checkout error: ' . $e->getMessage());
        return response()->json(['error' => 'Error creando el pedido.'], 500);
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
        'payment_intent' => $session->payment_intent,
    ]);

    $this->notifyOrderPaid($order);
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

    private function handleCheckoutCompleted($session): void
{
    $order = Order::where('stripe_session_id', $session->id)->first();
    if (!$order) return;

    if ($order->payment_status !== 'paid') {
        $order->update([
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_intent' => $session->payment_intent ?? null,
        ]);

        $this->notifyOrderPaid($order);
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

    private function notifyOrderPaid(\App\Models\Order $order): void
{
    try {
        // Cliente
        if ($order->user) {
            $order->user->notify(new \App\Notifications\OrderPaidCustomer($order));
        }

        // Admin
        $adminEmail = config('mail.admin_notification_email');
        if ($adminEmail) {
            \Illuminate\Support\Facades\Notification::route('mail', $adminEmail)
                ->notify(new \App\Notifications\OrderPaidAdmin($order));
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Error enviando notificación de pedido pagado: ' . $e->getMessage(), [
            'order_id' => $order->id,
        ]);
    }
}
}
