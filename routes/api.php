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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;




Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Auth required routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth endpoints
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/verify-token', [AuthController::class, 'verifyToken']);
    Route::get('/verify-role/{role}', [AuthController::class, 'verifyRole']);

    // Additional auth endpoints from updated AuthController
    Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Cart endpoints
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'getCart']);
        Route::post('/add', [CartController::class, 'addToCart']);
        Route::put('/update', [CartController::class, 'updateCartItem']);
        Route::delete('/item/{productId}', [CartController::class, 'removeCartItem']);
        Route::delete('/clear', [CartController::class, 'clearCart']);
        Route::post('/sync', [CartController::class, 'syncCart']);
    });

    // Orders endpoints
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'getUserOrders']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::get('/{order}', [OrderController::class, 'getOrder'])->middleware('can:view,order');
        Route::post('/{order}/cancel', [OrderController::class, 'cancelOrder']);
        Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
    });

    Route::post('/products/details', [CartController::class, 'getProductsDetails']);

    // Stripe endpoints (protegidos por auth)
    Route::prefix('stripe')->group(function () {
        Route::post('/checkout', [StripeController::class, 'createCheckoutSession']);
        Route::post('/confirm-payment', [StripeController::class, 'confirmPayment']);
    });

    // Admin product management
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

// Public endpoints
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}/images', [ProductController::class, 'showWithImages']);
    Route::get('/{id}', [ProductController::class, 'show']);
    Route::post('/', [ProductController::class, 'store']);
    Route::put('/{id}', [ProductController::class, 'update']);
    Route::delete('/{id}', [ProductController::class, 'destroy']);
});

Route::post('/products/{product}/images', [ProductImageController::class, 'store']);

// Stripe endpoints públicos (webhooks y redirecciones)
Route::prefix('stripe')->group(function () {
    Route::post('/webhook', [StripeController::class, 'handleWebhook'])->name('stripe.webhook');
    Route::get('/success', [StripeController::class, 'success'])->name('stripe.success');
    Route::get('/cancel', [StripeController::class, 'cancel'])->name('stripe.cancel');
});


Route::prefix('countries')->group(function () {
    Route::get('/', [CountriesController::class, 'index']);
    Route::get('/search', [CountriesController::class, 'search']);
});

// Rutas públicas
Route::prefix('countries')->group(function () {
    Route::get('/', [CountriesController::class, 'index']);
    Route::get('/search', [CountriesController::class, 'search']);
});

// Rutas protegidas por autenticación
Route::middleware(['auth:sanctum'])->prefix('profile')->group(function () {
    // Obtener perfil del usuario
    Route::get('/', [ProfileController::class, 'show']);

    // Actualizar perfil del usuario
    Route::put('/', [ProfileController::class, 'update']);
    Route::patch('/', [ProfileController::class, 'update']);

    // Obtener límites de cambios
    Route::get('/limits', [ProfileController::class, 'limits']);

    // Cambiar contraseña
    Route::post('/change-password', [ProfileController::class, 'changePassword']);
});
