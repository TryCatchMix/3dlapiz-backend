<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Category;

class ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Eliminar todos los productos existentes
        DB::table('products')->delete();

        // Obtener todas las categorías
        $categories = Category::all();
        $categoryMap = $categories->pluck('id', 'name');

        // Lista de nombres de productos (sin .jpg)
        $productNames = [
            'shera',
            'Vi & Caitlyn',
            'Caitlyn & Vi',
            'rubi zafiro',
            'good omens',
            'Alastor Vox',
            'hearthstopper',
            'Warwick & Vi',
            'luzamity',
            'Viktor & Jayce',
            'chicle',
            'huskangel'
        ];

        $defaultCategory = $categoryMap->first(); // Usa la primera categoría como fallback
        $products = [];

        foreach ($productNames as $name) {
            $products[] = [
                'id' => Str::uuid(),
                'name' => ucfirst($name),
                'description' => 'Producto impreso en 3D: ' . ucfirst($name),
                'price' => rand(1500, 5000) / 100, // Precio entre 15.00 y 50.00
                'stock' => rand(1, 20),
                'category_id' => $defaultCategory,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        DB::table('products')->insert($products);

    }
}
