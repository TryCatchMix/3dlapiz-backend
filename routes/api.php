<?php

use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Api\CountriesController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\StripeController;
use Illuminate\Support\Facades\Route;

// ============================================
// RUTAS PÚBLICAS
// ============================================

// Autenticación
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

// Países (público)
Route::prefix('countries')->group(function () {
    Route::get('/', [CountriesController::class, 'index']);
    Route::get('/search', [CountriesController::class, 'search']);
});

// Productos (público)
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}', [ProductController::class, 'show']);
    Route::get('/{id}/images', [ProductController::class, 'showWithImages']);
});

// Stripe webhooks y redirecciones (público)
Route::prefix('stripe')->group(function () {
    Route::post('/webhook', [StripeController::class, 'handleWebhook'])->name('stripe.webhook');
    Route::get('/success', [StripeController::class, 'success'])->name('stripe.success');
    Route::get('/cancel', [StripeController::class, 'cancel'])->name('stripe.cancel');
});

// ============================================
// RUTAS PROTEGIDAS (auth:sanctum)
// ============================================

Route::middleware('auth:sanctum')->group(function () {

    // ============================================
    // AUTENTICACIÓN
    // ============================================
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/verify-token', [AuthController::class, 'verifyToken']);
    Route::get('/verify-role/{role}', [AuthController::class, 'verifyRole']);
    Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // ============================================
    // PERFIL DE USUARIO
    // ============================================
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::patch('/', [ProfileController::class, 'update']);
        Route::get('/limits', [ProfileController::class, 'limits']);
        Route::post('/change-password', [ProfileController::class, 'changePassword']);
    });

    // ============================================
    // CARRITO
    // ============================================
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'getCart']);
        Route::post('/add', [CartController::class, 'addToCart']);
        Route::put('/update', [CartController::class, 'updateCartItem']);
        Route::delete('/item/{productId}', [CartController::class, 'removeCartItem']);
        Route::delete('/clear', [CartController::class, 'clearCart']);
        Route::post('/sync', [CartController::class, 'syncCart']);
    });
    Route::post('/products/details', [CartController::class, 'getProductsDetails']);

    // ============================================
    // PEDIDOS
    // ============================================
    Route::prefix('orders')->group(function () {
        // Obtener todos los pedidos del usuario
        Route::get('/my-orders', [OrderController::class, 'getUserOrders']);

        // Obtener un pedido específico
        Route::get('/{order}', [OrderController::class, 'getOrder']);

        // Cancelar un pedido
        Route::post('/{order}/cancel', [OrderController::class, 'cancelOrder']);

        // Actualizar estado de pedido (admin)
        Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
    });

    // ============================================
    // STRIPE (protegido)
    // ============================================
    Route::prefix('stripe')->group(function () {
        Route::post('/checkout', [StripeController::class, 'createCheckoutSession']);
        Route::post('/confirm-payment', [StripeController::class, 'confirmPayment']);
    });

    // ============================================
    // PRODUCTOS (protegido - solo creación/edición)
    // ============================================
    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::put('/{id}', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);
        Route::post('/{product}/images', [ProductImageController::class, 'store']);
    });

    // ============================================
    // ADMIN - GESTIÓN DE PRODUCTOS
    // ============================================
    Route::prefix('admin/products')->group(function () {
        Route::get('/', [AdminProductController::class, 'index']);
        Route::post('/', [AdminProductController::class, 'store']);
        Route::put('/{id}', [AdminProductController::class, 'update']);
        Route::post('/{id}/images', [AdminProductController::class, 'manageImages']);
        Route::patch('/{id}/status', [AdminProductController::class, 'updateStatus']);
        Route::patch('/{id}/featured', [AdminProductController::class, 'updateFeatured']);
        Route::get('/{id}/statistics', [AdminProductController::class, 'statistics']);
        Route::post('/{id}/duplicate', [AdminProductController::class, 'duplicate']);
        Route::get('/low-stock', [AdminProductController::class, 'lowStock']);
        Route::post('/{id}/restore', [AdminProductController::class, 'restore']);
        Route::get('/dashboard', [AdminProductController::class, 'dashboard']);
    });
});
