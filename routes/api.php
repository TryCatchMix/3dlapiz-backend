<?php

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
use App\Http\Controllers\Admin\AdminOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS
|--------------------------------------------------------------------------
*/

// Autenticación
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');

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

// Envíos (cálculo público — solo recibe country_code y devuelve precio)
Route::get('/shipping/calculate', [ShippingController::class, 'calculate']);

// Stripe (webhooks y redirecciones)
Route::prefix('stripe')->group(function () {
    Route::post('/webhook', [StripeController::class, 'handleWebhook'])->name('stripe.webhook');
    Route::get('/success', [StripeController::class, 'success'])->name('stripe.success');
    Route::get('/cancel', [StripeController::class, 'cancel'])->name('stripe.cancel');
});


/*
|--------------------------------------------------------------------------
| RUTAS AUTENTICADAS (auth:sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Sesión / cuenta
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/verify-token', [AuthController::class, 'verifyToken']);
    Route::get('/verify-role/{role}', [AuthController::class, 'verifyRole']);
    Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Perfil
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::patch('/', [ProfileController::class, 'update']);
        Route::get('/limits', [ProfileController::class, 'limits']);
        Route::post('/change-password', [ProfileController::class, 'changePassword']);
    });

    // Carrito
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'getCart']);
        Route::post('/add', [CartController::class, 'addToCart']);
        Route::put('/update', [CartController::class, 'updateCartItem']);
        Route::delete('/item/{productId}', [CartController::class, 'removeCartItem']);
        Route::delete('/clear', [CartController::class, 'clearCart']);
        Route::post('/sync', [CartController::class, 'syncCart']);
    });
    Route::post('/products/details', [CartController::class, 'getProductsDetails']);

    // Pedidos
    Route::prefix('orders')->group(function () {
        Route::get('/my-orders', [OrderController::class, 'getUserOrders']);
        Route::get('/{order}', [OrderController::class, 'getOrder']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancelOrder']);
        Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
    });

    // Stripe (checkout autenticado)
    Route::prefix('stripe')->group(function () {
        Route::post('/checkout', [StripeController::class, 'createCheckoutSession']);
        Route::post('/confirm-payment', [StripeController::class, 'confirmPayment']);
    });
});


/*
|--------------------------------------------------------------------------
| RUTAS DE ADMINISTRADOR (auth:sanctum + admin)
|--------------------------------------------------------------------------
| Todo lo que esté aquí dentro requiere usuario autenticado con rol admin.
| El middleware `admin` devuelve 403 a cualquier usuario sin permisos.
*/

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

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
    });

    // Precios de envío por país
    Route::prefix('shipping-rates')->group(function () {
        Route::get('/', [AdminShippingRateController::class, 'index']);
        Route::post('/', [AdminShippingRateController::class, 'store']);
        Route::put('/{id}', [AdminShippingRateController::class, 'update']);
        Route::delete('/{id}', [AdminShippingRateController::class, 'destroy']);
    });

    Route::prefix('orders')->group(function () {
    Route::get('/', [AdminOrderController::class, 'index']);
    Route::get('/{id}', [AdminOrderController::class, 'show']);
    Route::patch('/{id}/tracking', [AdminOrderController::class, 'setTracking']);
    Route::patch('/{id}/status', [AdminOrderController::class, 'updateStatus']);
});

});


/*
|--------------------------------------------------------------------------
| RUTAS DE ESCRITURA DE PRODUCTOS (legacy)
|--------------------------------------------------------------------------
| Estas rutas estaban antes en /products bajo solo auth:sanctum. Las paso
| también a admin: si quieres que un usuario normal pueda llamar a
| POST /products no tiene mucho sentido en una tienda. Si en algún momento
| eso cambia, mueves estas rutas al grupo solo-auth de arriba.
*/

Route::middleware(['auth:sanctum', 'admin'])->prefix('products')->group(function () {
    Route::post('/', [ProductController::class, 'store']);
    Route::put('/{id}', [ProductController::class, 'update']);
    Route::delete('/{id}', [ProductController::class, 'destroy']);
    Route::post('/{product}/images', [ProductImageController::class, 'store']);
});
