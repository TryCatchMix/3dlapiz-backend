<?php

use App\Http\Controllers\Admin\AdminDiscountCodeController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminShippingRateController;
use App\Http\Controllers\Api\CountriesController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\StripeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| STRIPE WEBHOOK — SIN throttle, sin auth
| Stripe necesita acceso libre para notificar pagos.
|--------------------------------------------------------------------------
*/
Route::post('/stripe/webhook', [StripeController::class, 'handleWebhook'])
    ->name('stripe.webhook');


/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS — con throttle específico según el tipo
|--------------------------------------------------------------------------
*/

// Autenticación — cada acción con su throttle propio
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:register');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:password-reset');

Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:password-reset');

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');


// Contenido público — throttle generoso (120/min)
Route::middleware('throttle:public-api')->group(function () {

    // Países
    Route::prefix('countries')->group(function () {
        Route::get('/', [CountriesController::class, 'index']);
        Route::get('/search', [CountriesController::class, 'search']);
    });

    // Productos (lectura)
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{id}', [ProductController::class, 'show']);
        Route::get('/{id}/images', [ProductController::class, 'showWithImages']);
    });

    // Envíos (cálculo público)
    Route::get('/shipping/calculate', [ShippingController::class, 'calculate']);
});


/*
|--------------------------------------------------------------------------
| RUTAS AUTENTICADAS (auth:sanctum + throttle:user-api)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'throttle:user-api'])->group(function () {

    // Sesión / cuenta
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/verify-token', [AuthController::class, 'verifyToken']);
    Route::get('/verify-role/{role}', [AuthController::class, 'verifyRole']);
    Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    // Cambio de contraseña — throttle propio (más estricto)
    Route::post('/change-password', [AuthController::class, 'changePassword'])
        ->withoutMiddleware('throttle:user-api')
        ->middleware('throttle:password-change');

    // Perfil
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::patch('/', [ProfileController::class, 'update']);
        Route::get('/limits', [ProfileController::class, 'limits']);

        Route::post('/change-password', [ProfileController::class, 'changePassword'])
            ->withoutMiddleware('throttle:user-api')
            ->middleware('throttle:password-change');
    });

    // Carrito
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'getCart']);
        Route::post('/add', [CartController::class, 'addToCart']);
        Route::put('/update', [CartController::class, 'updateCartItem']);
        Route::delete('/item/{productId}', [CartController::class, 'removeCartItem']);
        Route::delete('/remove', [CartController::class, 'removeItem']);
        Route::delete('/clear', [CartController::class, 'clearCart']);
        Route::post('/sync', [CartController::class, 'syncCart']);
    });
    Route::post('/products/details', [CartController::class, 'getProductsDetails']);

    // Pedidos
    Route::prefix('orders')->group(function () {
        Route::get('/my-orders', [OrderController::class, 'getUserOrders']);
        Route::get('/{order}', [OrderController::class, 'getOrder']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancelOrder']);
    });

    // Códigos de descuento — throttle propio contra brute force
    Route::post('/discounts/validate', [CartController::class, 'validateCode'])
        ->withoutMiddleware('throttle:user-api')
        ->middleware('throttle:discount-validate');

    // Stripe (checkout autenticado)
    Route::prefix('stripe')->group(function () {
        Route::post('/checkout', [StripeController::class, 'createCheckoutSession']);
        Route::post('/confirm-payment', [StripeController::class, 'confirmPayment']);
    });
});


/*
|--------------------------------------------------------------------------
| RUTAS ADMIN (auth:sanctum + admin + throttle:admin-api)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'admin', 'throttle:admin-api'])
    ->prefix('admin')
    ->group(function () {

        // Productos
        Route::prefix('products')->group(function () {
            Route::get('/', [AdminProductController::class, 'index']);
            Route::post('/', [AdminProductController::class, 'store']);
            Route::get('/dashboard', [AdminProductController::class, 'dashboard']);
            Route::get('/low-stock', [AdminProductController::class, 'lowStock']);
            Route::put('/{id}', [AdminProductController::class, 'update']);
            Route::post('/{id}/images', [AdminProductController::class, 'manageImages']);
            Route::patch('/{id}/status', [AdminProductController::class, 'updateStatus']);
            Route::patch('/{id}/featured', [AdminProductController::class, 'updateFeatured']);
            Route::get('/{id}/statistics', [AdminProductController::class, 'statistics']);
            Route::post('/{id}/duplicate', [AdminProductController::class, 'duplicate']);
            Route::post('/{id}/restore', [AdminProductController::class, 'restore']);
            Route::delete('/{id}', [AdminProductController::class, 'destroy']);
        });

        // Precios de envío por país
        Route::prefix('shipping-rates')->group(function () {
            Route::get('/', [AdminShippingRateController::class, 'index']);
            Route::post('/', [AdminShippingRateController::class, 'store']);
            Route::put('/{id}', [AdminShippingRateController::class, 'update']);
            Route::delete('/{id}', [AdminShippingRateController::class, 'destroy']);
        });

        // Configuración de envíos (default global)
        Route::get('/shipping-settings', [AdminShippingRateController::class, 'getSettings']);
        Route::put('/shipping-settings', [AdminShippingRateController::class, 'updateSettings']);

        // Pedidos
        Route::prefix('orders')->group(function () {
            Route::get('/', [AdminOrderController::class, 'index']);
            Route::get('/{id}', [AdminOrderController::class, 'show']);
            Route::patch('/{id}/tracking', [AdminOrderController::class, 'setTracking']);
            Route::patch('/{id}/status', [AdminOrderController::class, 'updateStatus']);
        });

        // Códigos de descuento
        Route::prefix('discounts')->group(function () {
            Route::get('/', [AdminDiscountCodeController::class, 'index']);
            Route::post('/', [AdminDiscountCodeController::class, 'store']);
            Route::put('/{id}', [AdminDiscountCodeController::class, 'update']);
            Route::delete('/{id}', [AdminDiscountCodeController::class, 'destroy']);
        });
    });


/*
|--------------------------------------------------------------------------
| RUTAS ADMIN LEGACY (mantén si aún las usa algo del frontend)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'admin', 'throttle:admin-api'])
    ->prefix('products')
    ->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::put('/{id}', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);
        Route::post('/{product}/images', [ProductImageController::class, 'store']);
    });
