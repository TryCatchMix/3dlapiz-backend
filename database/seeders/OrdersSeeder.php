<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Product;

class OrdersSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('order_items')->delete();
        DB::table('orders')->delete();

        $users = User::all();
        $products = Product::all();

        if ($users->isEmpty() || $products->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            // Cada usuario tendrá entre 1 y 5 pedidos
            $ordersCount = rand(1, 5);

            for ($i = 0; $i < $ordersCount; $i++) {
                $orderId = Str::uuid();

                $status = collect([
                    'pending',
                    'processing',
                    'paid',
                    'shipped',
                    'delivered',
                    'cancelled'
                ])->random();

                $paymentStatus = in_array($status, ['paid', 'shipped', 'delivered'])
                    ? 'paid'
                    : ($status === 'cancelled' ? 'cancelled' : 'pending');

                $orderItems = [];
                $total = 0;

                // Cada pedido tendrá entre 1 y 4 items
                $items = $products->random(rand(1, 4));

                foreach ($items as $product) {
                    $quantity = rand(1, 3);
                    $price = $product->price;

                    $total += $quantity * $price;

                    $orderItems[] = [
                        'id' => Str::uuid(),
                        'order_id' => $orderId,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'price' => $price,
                        'product_name' => $product->name,
                        'product_image' => $product->image_url ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                DB::table('orders')->insert([
                    'id' => $orderId,
                    'user_id' => $user->id,
                    'status' => $status,
                    'payment_status' => $paymentStatus,
                    'total' => round($total, 2),
                    'shipping_info' => json_encode([
                        'address' => 'Calle Falsa 123',
                        'city' => 'Madrid',
                        'postal_code' => '28001',
                        'country' => 'España',
                    ]),
                    'shipping_method' => json_encode([
                        'type' => 'standard',
                        'price' => 4.99,
                    ]),
                    'created_at' => now()->subDays(rand(1, 60)),
                    'updated_at' => now(),
                ]);

                DB::table('order_items')->insert($orderItems);
            }
        }
    }
}
