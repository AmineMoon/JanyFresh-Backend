<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Display all admins
     */
    public function index()
    {
        return Admin::with('user')->get();
    }

    /**
     * Create a new admin
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $admin = Admin::create([
            'user_id' => $user->id,
            'position' => $validated['position'] ?? null,
        ]);

          $token = $user->createToken('api-token')->plainTextToken;
          
        return response()->json([
            'message' => 'Admin created successfully',
            'admin' => $admin->load('user'),
        ], 201);
    }

    /**
     * Show one admin
     */
    public function show(string $id)
    {
        $admin = Admin::with('user')->findOrFail($id);

        return response()->json($admin);
    }

    /**
     * Update admin
     */
    public function update(Request $request, string $id)
    {
        $admin = Admin::with('user')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'position' => 'sometimes|nullable|string|max:255',
        ]);

        if (isset($validated['name'])) {
            $admin->user->update([
                'name' => $validated['name'],
            ]);
        }

        if (isset($validated['phone'])) {
            $admin->user->update([
                'phone' => $validated['phone'],
            ]);
        }

        if (isset($validated['position'])) {
            $admin->update([
                'position' => $validated['position'],
            ]);
        }

        return response()->json([
            'message' => 'Admin updated successfully',
            'admin' => $admin->fresh()->load('user'),
        ]);
    }

    /**
     * Show current admin profile (self-service)
     */
    public function showProfile(Request $request)
    {
        $user = $request->user();
        $admin = Admin::where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'admin' => $admin,
            ],
        ]);
    }

    /**
     * Update current admin profile (self-service)
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $admin = Admin::where('user_id', $user->id)->first();

        if (!$admin) {
            return response()->json(['message' => 'Admin profile not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'position' => 'sometimes|nullable|string|max:255',
        ]);

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }
        $user->save();

        if (array_key_exists('position', $validated)) {
            $admin->position = $validated['position'];
            $admin->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $user->fresh(),
                'admin' => $admin->fresh(),
            ],
        ]);
    }

    /**
     * Delete admin
     */
    public function destroy(string $id)
    {
        $admin = Admin::findOrFail($id);

        $user = $admin->user;

        $admin->delete();
        $user->delete();

        return response()->json([
            'message' => 'Admin deleted successfully'
        ]);
    }
}