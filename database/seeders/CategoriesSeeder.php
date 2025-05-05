<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('categories')->insert([
            [
                'id' => Str::uuid(),
                'name' => 'Figuras de acción',
                'description' => 'Figuras impresas en 3D de personajes de acción.',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Animales',
                'description' => 'Modelos 3D de animales.',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Decoración',
                'description' => 'Figuras decorativas para el hogar.',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
