<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ExclusiveOffer;
use App\Http\Resources\ExclusiveOfferResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ExclusiveOfferController extends Controller
{
    // GET /api/exclusive-offers (public)
    public function index()
    {
        $offers = ExclusiveOffer::active()
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ExclusiveOfferResource::collection($offers),
        ]);
    }

    // GET /api/exclusive-offers/{id} (public)
    public function show($id)
    {
        $offer = ExclusiveOffer::find($id);

        if (!$offer) {
            return response()->json(['message' => 'Offer not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ExclusiveOfferResource($offer),
        ]);
    }

    // GET /admin/exclusive-offers (admin - all offers including inactive)
    public function adminIndex()
    {
        $offers = ExclusiveOffer::orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ExclusiveOfferResource::collection($offers),
        ]);
    }

    // POST /admin/exclusive-offers
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'discount_percentage' => 'required|integer|min:0|max:100',
            'badge_text' => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:255',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'nullable|in:active,inactive',
            'sort_order' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {
            $imagePath = $request->file('image')->store('offers', 'public');
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Image upload failed: ' . $e->getMessage(),
            ], 422);
        }

        $offer = ExclusiveOffer::create([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'],
            'description' => $validated['description'],
            'discount_percentage' => $validated['discount_percentage'],
            'badge_text' => $validated['badge_text'],
            'button_text' => $validated['button_text'],
            'image' => $imagePath,
            'status' => $validated['status'] ?? ExclusiveOffer::STATUS_ACTIVE,
            'sort_order' => $validated['sort_order'] ?? 0,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        return response()->json([
            'message' => 'Offer created successfully',
            'data' => new ExclusiveOfferResource($offer),
        ], 201);
    }

    // PUT /admin/exclusive-offers/{id}
    public function update(Request $request, $id)
    {
        $offer = ExclusiveOffer::find($id);

        if (!$offer) {
            return response()->json(['message' => 'Offer not found'], 404);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'discount_percentage' => 'required|integer|min:0|max:100',
            'badge_text' => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'nullable|in:active,inactive',
            'sort_order' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $data = [
            'title' => $request->title,
            'subtitle' => $request->subtitle,
            'description' => $request->description,
            'discount_percentage' => $request->discount_percentage,
            'badge_text' => $request->badge_text,
            'button_text' => $request->button_text,
            'status' => $request->status ?? $offer->status,
            'sort_order' => $request->sort_order ?? $offer->sort_order,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ];

        if ($request->hasFile('image')) {
            if ($offer->image) {
                Storage::disk('public')->delete($offer->image);
            }
            $data['image'] = $request->file('image')->store('offers', 'public');
        }

        $offer->update($data);

        return response()->json([
            'message' => 'Offer updated successfully',
            'data' => new ExclusiveOfferResource($offer),
        ]);
    }

    // DELETE /admin/exclusive-offers/{id}
    public function destroy($id)
    {
        $offer = ExclusiveOffer::find($id);

        if (!$offer) {
            return response()->json(['message' => 'Offer not found'], 404);
        }

        if ($offer->image) {
            Storage::disk('public')->delete($offer->image);
        }

        $offer->delete();

        return response()->json(['message' => 'Offer deleted successfully']);
    }

    // PUT /admin/exclusive-offers/{id}/toggle-status
    public function toggleStatus($id)
    {
        $offer = ExclusiveOffer::find($id);

        if (!$offer) {
            return response()->json(['message' => 'Offer not found'], 404);
        }

        $offer->status = $offer->status === ExclusiveOffer::STATUS_ACTIVE
            ? ExclusiveOffer::STATUS_INACTIVE
            : ExclusiveOffer::STATUS_ACTIVE;
        $offer->save();

        return response()->json([
            'message' => 'Status updated successfully',
            'data' => new ExclusiveOfferResource($offer),
        ]);
    }
}
