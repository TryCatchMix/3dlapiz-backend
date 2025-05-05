<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Llamar a otros seeders
        $this->call([
            CategoriesSeeder::class,
            ProductsSeeder::class,
            ImagesSeeder::class,
        ]);

        // Crear usuarios de prueba con UUID
        User::factory(10)->create(); // Crea 10 usuarios aleatorios

        // Crear un usuario admin específico
        User::factory()->admin()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminSecurePassword123!'),
            'phone_country_code' => '+1',
            'phone_number' => '5559876543',
            'street' => '456 Admin St',
            'city' => 'Admin City',
            'state' => 'AD',
            'postal_code' => '12345',
            'country_code' => 'US',
            'email_verified_at' => now(),
        ]);

        // Crear un usuario de prueba específico
        User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => Hash::make('SecurePassword123!'),
            'phone_country_code' => '+1',
            'phone_number' => '5551234567',
            'street' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country_code' => 'US',
            'role' => User::ROLE_USER,
            'email_verified_at' => now(),
        ]);
    }
}
