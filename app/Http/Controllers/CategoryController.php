<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    // GET /api/categories
    public function index()
    {
        $categories = Category::with(['subcategories' => function ($query) {
            $query->where('is_active', true)->orderBy('name');
        }])->where('is_active', true)->latest()->get();

        return CategoryResource::collection($categories);
    }

    // GET /api/categories/active
    public function active()
    {
        $categories = Category::with(['activeSubcategories' => function ($query) {
            $query->orderBy('name');
        }])->where('is_active', true)->latest()->get();

        return CategoryResource::collection($categories);
    }

    // POST /api/categories
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'image' => 'nullable|image',
            'is_active' => 'boolean',
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create([
            'name' => $request->name,
            'image' => $imagePath,
            'is_active' => $request->is_active ?? true,
        ]);

        $category->load('subcategories');

        return response()->json([
            'message' => 'Category created successfully',
            'data' => new CategoryResource($category)
        ], 201);
    }

    // GET /api/categories/{id}
    public function show($id)
    {
        $category = Category::with('subcategories')->find($id);

        if (!$category) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return new CategoryResource($category);
    }

    // PUT /api/categories/{id}
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $id,
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'is_active' => 'boolean',
        ]);

        $data = [
            'name' => $request->name,
            'is_active' => $request->is_active ?? $category->is_active,
        ];

        // handle image update
        if ($request->hasFile('image')) {
            // delete old image if exists
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }

            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);
        $category->load('subcategories');

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => new CategoryResource($category)
        ]);
    }

    // DELETE /api/categories/{id}
    public function destroy($id)
    {
        $category = Category::withCount('products')->find($id);

        if (!$category) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Soft-delete: hide from admin/product lists instead of hard-deleting,
        // so deletion always succeeds even when products still reference it.
        // Products keep their category_id (FK is cascadeOnDelete, but we avoid
        // breaking active listings by simply deactivating).
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->is_active = false;
        $category->save();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}