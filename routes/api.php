<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\StripeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
});

Route::middleware('auth:sanctum')->get('/verify-token', [AuthController::class, 'verifyToken']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);


Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}/category', [ProductController::class, 'showWithCategory']);
    Route::get('/{id}/images', [ProductController::class, 'showWithImages']);
    Route::get('/{id}', [ProductController::class, 'show']);
    Route::post('/', [ProductController::class, 'store']);
    Route::put('/{id}', [ProductController::class, 'update']);
    Route::delete('/{id}', [ProductController::class, 'destroy']);
});


Route::post('/products/{product}/images', [ProductImageController::class, 'store']);


Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::post('/', [CategoryController::class, 'store']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::put('/{id}', [CategoryController::class, 'update']);
    Route::delete('/{id}', [CategoryController::class, 'destroy']);
});

Route::prefix('cart')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CartController::class, 'viewCart']);
    Route::post('/add', [CartController::class, 'addToCart']);
    Route::delete('/remove/{itemId}', [CartController::class, 'removeFromCart']);
    Route::put('/update/{itemId}', [CartController::class, 'updateCartItem']);
});

Route::prefix('orders')->middleware('auth:sanctum')->group(function () {
    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/{id}', [OrderController::class, 'show']);
});

Route::prefix('stripe')->group(function () {
    Route::post('/checkout', [OrderController::class, 'checkout'])->middleware('auth:sanctum');
    Route::get('/success', [OrderController::class, 'stripeSuccess'])->name('stripe.success');
    Route::get('/cancel', [OrderController::class, 'stripeCancel'])->name('stripe.cancel');
    Route::post('/webhook', [OrderController::class, 'stripeWebhook'])->name('stripe.webhook');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrderController::class, 'getUserOrders']);

    Route::get('/orders/{order}', [OrderController::class, 'getOrder'])->middleware('can:view,order');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cart', [CartController::class, 'getCart']);
    Route::post('/cart/add', [CartController::class, 'addToCart']);
    Route::put('/cart/update', [CartController::class, 'updateCartItem']);
    Route::delete('/cart/item/{productId}', [CartController::class, 'removeCartItem']);
    Route::delete('/cart/clear', [CartController::class, 'clearCart']);
    Route::post('/cart/sync', [CartController::class, 'syncCart']);

    Route::post('/products/details', [CartController::class, 'getProductsDetails']);
});


