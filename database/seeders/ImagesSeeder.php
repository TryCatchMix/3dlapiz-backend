<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Product;

class ImagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('product_images')->delete();

        $products = Product::all()->keyBy(fn ($p) => strtolower(str_replace(' ', '', $p->name)));

        $images = [];
        $path = public_path('images/3d_figures');

        $files = File::files($path);

        foreach ($files as $file) {
            $filename = $file->getFilename();
            $nameOnly = strtolower(pathinfo($filename, PATHINFO_FILENAME));
            $baseName = preg_replace('//', '', $nameOnly);
            $baseName = str_replace(' ', '', $baseName);

            if ($products->has($baseName)) {
                $images[] = [
                    'id' => Str::uuid(),
                    'product_id' => $products[$baseName]->id,
                    'image_url' => 'images/3d_figures/' . $filename,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        DB::table('product_images')->insert($images);
    }
}
