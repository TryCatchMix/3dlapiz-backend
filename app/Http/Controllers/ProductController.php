<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Valida que el ID sea un UUID válido
     *
     * @param string $id
     * @throws ValidationException
     */
    protected function validateUuid(string $id): void
    {
        if (!Str::isUuid($id)) {
            throw ValidationException::withMessages([
                'id' => ['El ID debe ser un UUID válido.']
            ]);
        }
    }

    public function index(Request $request = null)
    {
        return Product::with(['category', 'images'])->get();
    }

    public function show($id)
    {
        $this->validateUuid($id);
        $product = Product::with(['category', 'images'])->findOrFail($id);
        return response()->json($product);
    }

    public function showWithCategory($id)
    {
        $this->validateUuid($id);
        $product = Product::with('category')->findOrFail($id);
        return response()->json($product);
    }

    public function showWithImages($id)
    {
        $this->validateUuid($id);
        $product = Product::with('images')->findOrFail($id);
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $this->validateUuid($id);
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric',
            'stock' => 'sometimes|integer',
            'category_id' => 'sometimes|exists:categories,id',
        ]);

        $product->update($validated);
        return response()->json($product);
    }

    public function destroy($id)
    {
        $this->validateUuid($id);
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
}
