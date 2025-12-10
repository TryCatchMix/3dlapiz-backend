<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ProductsSeeder::class,
            ImagesSeeder::class,
        ]);

        DB::table('users')->delete();
        User::factory(10)->create();

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
    }
}
