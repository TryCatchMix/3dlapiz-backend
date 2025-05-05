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
        DB::table('products')->delete();
        $categories = Category::all();
        $categoryMap = $categories->pluck('id', 'name');

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

        $defaultCategory = $categoryMap->first();
        $products = [];

        foreach ($productNames as $name) {
            $products[] = [
                'id' => Str::uuid(),
                'name' => ucfirst($name),
                'description' => 'Producto impreso en 3D: ' . ucfirst($name),
                'price' => rand(1500, 5000) / 100,
                'stock' => rand(1, 20),
                'category_id' => $defaultCategory,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        DB::table('products')->insert($products);

    }
}
