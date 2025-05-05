<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ProductController;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminProductController extends ProductController
{

    public function index(Request $request = null)
    {
        $perPage = $request->input('per_page', 15);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $search = $request->input('search', '');
        $categoryId = $request->input('category_id');

        $query = Product::with(['category', 'images']);

        if (!empty($search)) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }

        if (!empty($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'featured' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive,draft',
            'sku' => 'nullable|string|max:50|unique:products,sku',
        ]);

        $validated['status'] = $validated['status'] ?? 'active';
        $validated['featured'] = $validated['featured'] ?? false;

        $product = Product::create($validated);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');

                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $path,
                    'order' => 1,
                ]);
            }
        }

        return response()->json($product->load(['category', 'images']), 201);
    }

    public function update(Request $request, $id)
    {
        $this->validateUuid($id);
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'featured' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive,draft',
            'sku' => 'nullable|string|max:50|unique:products,sku,' . $id,
            'discount_price' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|string',
            'metadata' => 'nullable|json',
        ]);

        $product->update($validated);

        return response()->json($product->fresh(['category', 'images']));
    }

    public function manageImages(Request $request, $id)
    {
        $this->validateUuid($id);
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'deleted_images' => 'nullable|array',
            'deleted_images.*' => 'exists:product_images,id',
            'image_orders' => 'nullable|array',
            'image_orders.*.id' => 'exists:product_images,id',
            'image_orders.*.order' => 'integer|min:1',
        ]);

        if (!empty($validated['deleted_images'])) {
            $imagesToDelete = ProductImage::whereIn('id', $validated['deleted_images'])
                ->where('product_id', $id)
                ->get();

            foreach ($imagesToDelete as $image) {
                Storage::disk('public')->delete($image->path);
                $image->delete();
            }
        }

        if (!empty($validated['image_orders'])) {
            foreach ($validated['image_orders'] as $imageOrder) {
                ProductImage::where('id', $imageOrder['id'])
                    ->where('product_id', $id)
                    ->update(['order' => $imageOrder['order']]);
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');

                // Obtener el último orden para asignar uno nuevo
                $lastOrder = ProductImage::where('product_id', $id)
                    ->max('order') ?? 0;

                ProductImage::create([
                    'product_id' => $id,
                    'path' => $path,
                    'order' => $lastOrder + 1,
                ]);
            }
        }

        return response()->json($product->fresh(['images']));
    }

    public function updateStatus(Request $request, $id)
    {
        $this->validateUuid($id);
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:active,inactive,draft',
        ]);

        $product->update(['status' => $validated['status']]);

        return response()->json($product);
    }

    public function updateFeatured(Request $request, $id)
    {
        $this->validateUuid($id);
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'featured' => 'required|boolean',
        ]);

        $product->update(['featured' => $validated['featured']]);

        return response()->json($product);
    }

    public function statistics($id)
    {
        $this->validateUuid($id);
        $product = Product::findOrFail($id);


        $stats = [
            'views' => 0,
            'purchases' => 0,
            'conversions' => 0,
            'average_rating' => 0,
        ];

        return response()->json($stats);
    }


    public function duplicate($id)
    {
        $this->validateUuid($id);
        $originalProduct = Product::with(['images'])->findOrFail($id);

        $newProduct = $originalProduct->replicate(['id', 'created_at', 'updated_at']);
        $newProduct->name = $newProduct->name . ' (Copia)';
        $newProduct->sku = $newProduct->sku ? $newProduct->sku . '-copy' : null;
        $newProduct->save();

        foreach ($originalProduct->images as $image) {
            $newPath = $this->duplicateImage($image->path);

            ProductImage::create([
                'product_id' => $newProduct->id,
                'path' => $newPath,
                'order' => $image->order,
            ]);
        }

        return response()->json($newProduct->fresh(['category', 'images']));
    }

    private function duplicateImage($path)
    {
        if (Storage::disk('public')->exists($path)) {
            $content = Storage::disk('public')->get($path);
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $newPath = 'products/' . Str::uuid() . '.' . $extension;
            Storage::disk('public')->put($newPath, $content);
            return $newPath;
        }

        return null;
    }

    public function lowStock(Request $request)
    {
        $threshold = $request->input('threshold', 5);

        return Product::with(['category'])
            ->where('stock', '<=', $threshold)
            ->orderBy('stock', 'asc')
            ->paginate(15);
    }

    public function restore($id)
    {
        $this->validateUuid($id);

        // Nota: Requiere que Product use SoftDeletes
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();

        return response()->json($product);
    }

    public function dashboard()
    {
        $metrics = [
            'total_products' => Product::count(),
            'out_of_stock' => Product::where('stock', 0)->count(),
            'low_stock' => Product::where('stock', '>', 0)
                ->where('stock', '<=', 5)
                ->count(),
            'categories' => Category::withCount('products')->get(),
            'recent_products' => Product::with('category')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        return response()->json($metrics);
    }
}
