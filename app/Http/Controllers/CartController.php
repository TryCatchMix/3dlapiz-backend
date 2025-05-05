<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Exception;

class CartController extends Controller
{

    public function getCart()
    {
        $cart = Cart::with('items.product')->where('user_id', Auth::id())->where('status', 'active')->first();

        if (!$cart) {
            return response()->json([
                'id' => null,
                'user_id' => Auth::id(),
                'status' => 'active',
                'total_amount' => 0,
                'items' => []
            ]);
        }

        $totalAmount = $cart->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        if ($cart->total_amount != $totalAmount) {
            $cart->total_amount = $totalAmount;
            $cart->save();
        }

        return response()->json($cart);
    }

    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        try {
            DB::beginTransaction();

            $cart = Cart::firstOrCreate([
                'user_id' => Auth::id(),
                'status' => 'active'
            ], [
                'id' => Str::uuid(),
                'total_amount' => 0
            ]);

            $product = Product::findOrFail($request->product_id);

            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->first();

            if ($cartItem) {
                $newQuantity = $cartItem->quantity + $request->quantity;
                if ($newQuantity > $product->stock) {
                    return response()->json([
                        'error' => 'No hay suficiente stock disponible'
                    ], 422);
                }

                $cartItem->quantity = $newQuantity;
                $cartItem->save();
            } else {
                if ($request->quantity > $product->stock) {
                    return response()->json([
                        'error' => 'No hay suficiente stock disponible'
                    ], 422);
                }

                $cartItem = new CartItem([
                    'id' => Str::uuid(),
                    'product_id' => $product->id,
                    'quantity' => $request->quantity,
                    'price' => $product->price
                ]);

                $cart->items()->save($cartItem);
            }

            $cart->total_amount = $cart->items->sum(function ($item) {
                return $item->price * $item->quantity;
            });
            $cart->save();

            DB::commit();

            return response()->json([
                'message' => 'Producto agregado al carrito',
                'cart_item' => $cartItem->load('product')
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al agregar producto al carrito: ' . $e->getMessage()], 500);
        }
    }

    public function updateCartItem(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        try {
            DB::beginTransaction();

            $cart = Cart::where('user_id', Auth::id())
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                return response()->json(['error' => 'Carrito no encontrado'], 404);
            }

            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $request->product_id)
                ->first();

            if (!$cartItem) {
                return response()->json(['error' => 'Producto no encontrado en el carrito'], 404);
            }

            $product = Product::findOrFail($request->product_id);

            if ($request->quantity > $product->stock) {
                return response()->json([
                    'error' => 'No hay suficiente stock disponible'
                ], 422);
            }

            $cartItem->quantity = $request->quantity;
            $cartItem->save();

            $cart->total_amount = $cart->items->sum(function ($item) {
                return $item->price * $item->quantity;
            });
            $cart->save();

            DB::commit();

            return response()->json([
                'message' => 'Cantidad actualizada',
                'cart_item' => $cartItem->load('product')
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al actualizar el carrito: ' . $e->getMessage()], 500);
        }
    }

    public function removeCartItem($productId)
    {
        try {
            DB::beginTransaction();

            $cart = Cart::where('user_id', Auth::id())
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                return response()->json(['error' => 'Carrito no encontrado'], 404);
            }

            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $productId)
                ->first();

            if (!$cartItem) {
                return response()->json(['error' => 'Producto no encontrado en el carrito'], 404);
            }

            $cartItem->delete();

            $cart->total_amount = $cart->items->sum(function ($item) {
                return $item->price * $item->quantity;
            });
            $cart->save();

            DB::commit();

            return response()->json([
                'message' => 'Producto eliminado del carrito'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al eliminar producto del carrito: ' . $e->getMessage()], 500);
        }
    }

    public function clearCart()
    {
        try {
            DB::beginTransaction();

            $cart = Cart::where('user_id', Auth::id())
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                return response()->json(['message' => 'No hay carrito activo para limpiar']);
            }

            $cart->items()->delete();
            $cart->total_amount = 0;
            $cart->save();

            DB::commit();

            return response()->json([
                'message' => 'Carrito vaciado correctamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al vaciar el carrito: ' . $e->getMessage()], 500);
        }
    }

    public function syncCart(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1'
        ]);

        try {
            DB::beginTransaction();

            $cart = Cart::firstOrCreate([
                'user_id' => Auth::id(),
                'status' => 'active'
            ], [
                'id' => Str::uuid(),
                'total_amount' => 0
            ]);

            $cart->items()->delete();

            $totalAmount = 0;

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                $quantity = min($item['quantity'], $product->stock);

                $cartItem = new CartItem([
                    'id' => Str::uuid(),
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $product->price
                ]);

                $cart->items()->save($cartItem);

                $totalAmount += $product->price * $quantity;
            }

            $cart->total_amount = $totalAmount;
            $cart->save();

            DB::commit();

            return response()->json([
                'message' => 'Carrito sincronizado correctamente',
                'cart' => $cart->load('items.product')
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al sincronizar el carrito: ' . $e->getMessage()], 500);
        }
    }

    public function getProductsDetails(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:products,id'
        ]);

        $products = Product::with('images')->whereIn('id', $request->ids)->get();

        return response()->json($products);
    }
}
