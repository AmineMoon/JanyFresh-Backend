<?php


namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    // LIST PRODUCTS
    public function index(Request $request)
    {
        $query = Product::with(['images', 'category', 'subcategory']);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('subcategory_id')) {
            $query->where('subcategory_id', $request->subcategory_id);
        }

        $products = $query->latest()->paginate(10);

        // The 'url' accessor is automatically included via $appends in ProductImage model
        // No manual transformation needed - the accessor handles it
        
        return response()->json($products);
    }

    // STORE PRODUCT
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',

            // UPDATED (IMPORTANT)
            'category_id' => 'required|exists:categories,id',
            'subcategory_id' => 'nullable|exists:subcategories,id',

            'unit' => 'required|in:kg,box,piece',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0',

            'is_active' => 'boolean',
            'created_by' => 'required|integer',

            // images
            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        return DB::transaction(function () use ($request) {

            $product = Product::create($request->only([
                'name',
                'description',
                'category_id',
                'subcategory_id',
                'unit',
                'price',
                'quantity',
                'is_active',
                'created_by',
            ]));

            // Upload images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {

                    $path = $image->store('products', 'public');

                    $product->images()->create([
                        'image_path' => $path,
                        'position' => $index,
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            return response()->json($product->load(['images', 'category', 'subcategory']), 201);
        });
    }

    // SHOW PRODUCT
    public function show(Product $product)
    {
        $product->load(['images', 'category', 'subcategory']);

        // The 'url' accessor is automatically included via $appends in ProductImage model
        return $product;
    }

    // UPDATE PRODUCT
    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',

            // UPDATED
            'category_id' => 'sometimes|exists:categories,id',
            'subcategory_id' => 'nullable|exists:subcategories,id',

            'unit' => 'sometimes|in:kg,box,piece',
            'price' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|numeric|min:0',
            'is_active' => 'boolean',

            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        return DB::transaction(function () use ($request, $product) {

            $product->update($request->only([
                'name',
                'description',
                'category_id',
                'subcategory_id',
                'unit',
                'price',
                'quantity',
                'is_active',
            ]));

            // Replace images if new ones uploaded
            if ($request->hasFile('images')) {

                foreach ($product->images as $img) {
                    Storage::disk('public')->delete($img->image_path);
                }

                $product->images()->delete();

                foreach ($request->file('images') as $index => $image) {

                    $path = $image->store('products', 'public');

                    $product->images()->create([
                        'image_path' => $path,
                        'position' => $index,
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            return response()->json($product->load(['images', 'category', 'subcategory']));
        });
    }

    // DELETE PRODUCT
    public function destroy(Product $product)
    {
        return DB::transaction(function () use ($product) {

            foreach ($product->images as $img) {
                Storage::disk('public')->delete($img->image_path);
            }

            $product->delete();

            return response()->json(['message' => 'Product deleted']);
        });
    }
}















/*

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    //  List products
    public function index(Request $request)
{
    $query = Product::with('images');

    //  SEARCH
    if ($request->filled('search')) {
        $query->where('name', 'like', '%' . $request->search . '%');
    }

    //  PAGINATION
    $products = $query->latest()->paginate(10);

    //  Transform images to full URL
    $products->getCollection()->transform(function ($product) {
        $product->images->transform(function ($img) {
            $img->url = asset('storage/' . $img->image_path);
            return $img;
        });

        return $product;
    });

    return response()->json($products);
}

    //  Store product with images
    public function store(Request $request)
    {
           //dd($request);
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:255',
            'unit' => 'required|in:kg,box,piece',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'created_by' => 'required|integer',

            // images
            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        return DB::transaction(function () use ($request) {

            $product = Product::create($request->only([
                'name',
                'description',
                'category',
                'unit',
                'price',
                'quantity',
                'is_active',
                'created_by',
            ]));
              
          

            // Upload images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', 'public');

                    $product->images()->create([
                        'image_path' => $path,
                        'position' => $index,
                        'is_primary' => $index === 0, // first image = main
                    ]);
                }
            }

            return response()->json($product->load('images'), 201);
        });
    }

    //  Show single product
    public function show(Product $product)
    {
        return $product->load('images');
    }

    //  Update product
    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'sometimes|string|max:255',
            'unit' => 'sometimes|in:kg,box,piece',
            'price' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:0',
            'is_active' => 'boolean',

            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        return DB::transaction(function () use ($request, $product) {

            $product->update($request->only([
                'name',
                'description',
                'category',
                'unit',
                'price',
                'quantity',
                'is_active',
            ]));

            // Replace images if new ones uploaded
            if ($request->hasFile('images')) {

                // delete old images from storage
                foreach ($product->images as $img) {
                    Storage::disk('public')->delete($img->image_path);
                }

                $product->images()->delete();

                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', 'public');

                    $product->images()->create([
                        'image_path' => $path,
                        'position' => $index,
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            return response()->json($product->load('images'));
        });
    }

    //  Delete product
    public function destroy(Product $product)
    {
        return DB::transaction(function () use ($product) {

            foreach ($product->images as $img) {
                Storage::disk('public')->delete($img->image_path);
            }

            $product->delete();

            return response()->json(['message' => 'Product deleted']);
        });
    }
}*/