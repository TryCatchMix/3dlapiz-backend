<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar tablas en orden correcto
        DB::table('order_items')->delete();
        DB::table('orders')->delete();
        DB::table('products')->delete();
        DB::table('users')->delete();

        // 1️⃣ Productos e imágenes
        $this->call([
            ProductsSeeder::class,
            ImagesSeeder::class,
        ]);

        // 2️⃣ Usuarios normales
        User::factory(10)->create();

        // 3️⃣ Admin
        User::factory()->admin()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminSecurePassword123!'),
            'phone_country_code' => '+1',
            'phone_number' => '555987654',
            'street' => '456 Admin St',
            'city' => 'Admin City',
            'state' => 'AD',
            'postal_code' => '12345',
            'country_code' => 'US',
            'email_verified_at' => now(),
        ]);

        // 4️⃣ Usuario fijo para testing frontend
        User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => Hash::make('SecurePassword123!'),
            'phone_country_code' => '+1',
            'phone_number' => '555123456',
            'street' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country_code' => 'US',
            'role' => User::ROLE_USER,
            'email_verified_at' => now(),
        ]);

        // 5️⃣ Pedidos (DESPUÉS de users y products)
        $this->call([
            OrdersSeeder::class,
        ]);
    }
}
