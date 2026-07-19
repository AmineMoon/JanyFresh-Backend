<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Retailer;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class RetailerController extends Controller
{
    /**
     * List all retailers (Admin)
     */
    public function index()
    {
        $users = User::where('role', 'retailer')
            ->with('retailer')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'user' => $user,
                    'shop_name' => $user->retailer->shop_name ?? null,
                    'address' => $user->retailer->address ?? null,
                    'city' => $user->retailer->city ?? null,
                    'is_active' => $user->is_active ?? true,
                    'created_at' => $user->created_at,
                ];
            }),
        ]);
    }

    /**
     * Create a new retailer (Admin)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20',
            'shop_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'role' => 'retailer',
        ]);

        $retailer = Retailer::create([
            'user_id' => $user->id,
            'shop_name' => $validated['shop_name'],
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Retailer created successfully',
            'data' => [
                'id' => $user->id,
                'user' => $user,
                'retailer' => $retailer,
            ],
        ]);
    }

    /**
     * Show retailer (self-service or admin)
     */
    public function show(Request $request, $id = null)
    {
        $authUser = $request->user();

        if ($authUser->isAdmin() && $id) {
            $user = User::with('retailer')->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'user' => $user,
                    'retailer' => $user->retailer,
                ],
            ]);
        }

        if (!$authUser->isRetailer()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = $authUser;
        $retailer = $user->retailer;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'retailer' => $retailer
            ]
        ]);
    }

    /**
     * Update retailer account (self-service or admin)
     */
    public function update(Request $request, $id = null)
    {
        $authUser = $request->user();

        if ($authUser->isAdmin() && $id) {
            $user = User::findOrFail($id);
            if ($user->role !== 'retailer') {
                return response()->json(['message' => 'User is not a retailer'], 400);
            }
        } else {
            if (!$authUser->isRetailer()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            $user = $authUser;
        }

        $retailer = $user->retailer;
        if (!$retailer) {
            return response()->json(['message' => 'Retailer profile not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:6',
            'phone' => 'sometimes|string|max:20',
            'shop_name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'city' => 'sometimes|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['name'])) $user->name = $validated['name'];
        if (isset($validated['email'])) $user->email = $validated['email'];
        if (isset($validated['phone'])) $user->phone = $validated['phone'];
        if (isset($validated['password'])) $user->password = Hash::make($validated['password']);
        $user->save();

        if (isset($validated['shop_name'])) $retailer->shop_name = $validated['shop_name'];
        if (isset($validated['address'])) $retailer->address = $validated['address'];
        if (isset($validated['city'])) $retailer->city = $validated['city'];
        if (array_key_exists('is_active', $validated)) $user->is_active = $validated['is_active'];
        $retailer->save();
        $user->save();

        $user->load('retailer');

        return response()->json([
            'success' => true,
            'message' => 'Retailer updated successfully',
            'data' => [
                'user' => $user,
                'retailer' => $retailer,
            ],
        ]);
    }

    /**
     * Delete retailer account (self-service or admin)
     */
    public function destroy(Request $request, $id = null)
    {
        $authUser = $request->user();

        if ($authUser->isAdmin() && $id) {
            $user = User::findOrFail($id);
        } else {
            if (!$authUser->isRetailer()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            $user = $authUser;
        }

        if ($user->role !== 'retailer') {
            return response()->json(['message' => 'User is not a retailer'], 400);
        }

        $retailer = $user->retailer;
        if ($retailer) {
            $retailer->delete();
        }
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Retailer deleted successfully'
        ]);
    }
}
 