<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Subcategory;
use App\Http\Resources\SubcategoryResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubcategoryController extends Controller
{
    // GET /api/subcategories
    public function index()
    {
        $subcategories = Subcategory::with('category')
            ->where('is_active', true)
            ->latest()
            ->get();

        return SubcategoryResource::collection($subcategories);
    }

    // POST /api/subcategories
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'is_active' => 'boolean',
        ]);

        // Prevent duplicate subcategory within same category
        $exists = Subcategory::where('category_id', $request->category_id)
            ->where('name', $request->name)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'A subcategory with this name already exists in this category.'
            ], 409);
        }

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('subcategories', 'public');
        }

        $subcategory = Subcategory::create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'image' => $imagePath,
            'is_active' => $request->is_active ?? true,
        ]);

        $subcategory->load('category');

        return response()->json([
            'message' => 'Subcategory created successfully',
            'data' => new SubcategoryResource($subcategory)
        ], 201);
    }

    // GET /api/subcategories/{id}
    public function show($id)
    {
        $subcategory = Subcategory::with('category')->find($id);

        if (!$subcategory) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return new SubcategoryResource($subcategory);
    }

    // PUT /api/subcategories/{id}
    public function update(Request $request, $id)
    {
        $subcategory = Subcategory::find($id);

        if (!$subcategory) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'is_active' => 'boolean',
        ]);

        // Prevent duplicate subcategory within same category (excluding current)
        $exists = Subcategory::where('category_id', $request->category_id)
            ->where('name', $request->name)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'A subcategory with this name already exists in this category.'
            ], 409);
        }

        $data = [
            'category_id' => $request->category_id,
            'name' => $request->name,
            'is_active' => $request->is_active ?? $subcategory->is_active,
        ];

        // handle image update
        if ($request->hasFile('image')) {
            // delete old image
            if ($subcategory->image) {
                Storage::disk('public')->delete($subcategory->image);
            }

            $data['image'] = $request->file('image')->store('subcategories', 'public');
        }

        $subcategory->update($data);
        $subcategory->load('category');

        return response()->json([
            'message' => 'Subcategory updated successfully',
            'data' => new SubcategoryResource($subcategory)
        ]);
    }

    // DELETE /api/subcategories/{id}
    public function destroy($id)
    {
        $subcategory = Subcategory::withCount('products')->find($id);

        if (!$subcategory) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Soft-delete: hide from admin/product lists instead of hard-deleting,
        // so deletion always succeeds even when products still reference it.
        if ($subcategory->image) {
            Storage::disk('public')->delete($subcategory->image);
        }

        $subcategory->is_active = false;
        $subcategory->save();

        return response()->json([
            'message' => 'Subcategory deleted successfully'
        ]);
    }
}