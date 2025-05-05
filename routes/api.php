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
    Route::get('/', [ProductController::class, 'index']); // Obtener todos los productos
    Route::get('/{id}/category', [ProductController::class, 'showWithCategory']); // Obtener producto con categoría
    Route::get('/{id}/images', [ProductController::class, 'showWithImages']); // Obtener producto con imágenes
    Route::get('/{id}', [ProductController::class, 'show']); // Obtener un producto específico
    Route::post('/', [ProductController::class, 'store']); // Crear un nuevo producto
    Route::put('/{id}', [ProductController::class, 'update']); // Actualizar un producto
    Route::delete('/{id}', [ProductController::class, 'destroy']); // Eliminar un producto
});

// Rutas para subir imágenes de productos
Route::post('/products/{product}/images', [ProductImageController::class, 'store']); // Subir imagen a un producto


Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);           // GET /api/categories
    Route::post('/', [CategoryController::class, 'store']);          // POST /api/categories
    Route::get('/{id}', [CategoryController::class, 'show']);        // GET /api/categories/{id}
    Route::put('/{id}', [CategoryController::class, 'update']);      // PUT /api/categories/{id}
    Route::delete('/{id}', [CategoryController::class, 'destroy']);  // DELETE /api/categories/{id}
});

Route::prefix('cart')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CartController::class, 'viewCart']); // Ver el carrito del usuario actual
    Route::post('/add', [CartController::class, 'addToCart']); // Añadir un producto al carrito
    Route::delete('/remove/{itemId}', [CartController::class, 'removeFromCart']); // Eliminar un producto del carrito
    Route::put('/update/{itemId}', [CartController::class, 'updateCartItem']); // Actualizar cantidad de un producto
});

Route::prefix('orders')->middleware('auth:sanctum')->group(function () {
    Route::post('/checkout', [OrderController::class, 'checkout']); // Realizar una compra
    Route::get('/', [OrderController::class, 'index']); // Ver todas las órdenes del usuario
    Route::get('/{id}', [OrderController::class, 'show']); // Ver una orden específica
});

Route::prefix('stripe')->group(function () {
    Route::post('/checkout', [OrderController::class, 'checkout'])->middleware('auth:sanctum'); // Añade esta línea
    Route::get('/success', [OrderController::class, 'stripeSuccess'])->name('stripe.success');
    Route::get('/cancel', [OrderController::class, 'stripeCancel'])->name('stripe.cancel');
    Route::post('/webhook', [OrderController::class, 'stripeWebhook'])->name('stripe.webhook');
});

Route::middleware('auth:sanctum')->group(function () {
    // Obtener todos los pedidos del usuario autenticado
    Route::get('/orders', [OrderController::class, 'getUserOrders']);

    // Obtener un pedido específico
    Route::get('/orders/{order}', [OrderController::class, 'getOrder'])->middleware('can:view,order');
});

Route::middleware('auth:sanctum')->group(function () {
    // Rutas del carrito
    Route::get('/cart', [CartController::class, 'getCart']);
    Route::post('/cart/add', [CartController::class, 'addToCart']);
    Route::put('/cart/update', [CartController::class, 'updateCartItem']);
    Route::delete('/cart/item/{productId}', [CartController::class, 'removeCartItem']);
    Route::delete('/cart/clear', [CartController::class, 'clearCart']);
    Route::post('/cart/sync', [CartController::class, 'syncCart']);

    // Obtener detalles de productos específicos (para sincronización)
    Route::post('/products/details', [CartController::class, 'getProductsDetails']);
});


