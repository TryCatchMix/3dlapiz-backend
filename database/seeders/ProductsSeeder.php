<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('products')->delete();

        $productNames = [
            'shera',
            'Vi_y_Caitlyn',
            'Caitlyn_y_Vi',
            'rubi_zafiro',
            'good_omens',
            'Alastor_Vox',
            'hearthstopper',
            'Warwick_y_Vi',
            'luzamity',
            'Viktor_y_Jayce',
            'chicle',
            'huskangel'
        ];

        $products = [];

        foreach ($productNames as $name) {
            $products[] = [
                'id' => Str::uuid(),
                'name' => ucfirst($name),
                'description' => 'Producto impreso en 3D: ' . ucfirst($name),
                'price' => rand(1500, 5000) / 100,
                'stock' => rand(1, 20),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        DB::table('products')->insert($products);

    }
}
