<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{

    public function index(Product $product)
    {
        $images = $product->images()->orderBy('created_at', 'asc')->get();
        return response()->json($images);
    }

    public function store(Request $request, Product $product)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        $path = $request->file('image')->store('products', 'public');

        $image = $product->images()->create([
            'image_url' => asset("storage/{$path}"),
        ]);

        return response()->json($image, 201);
    }
}
