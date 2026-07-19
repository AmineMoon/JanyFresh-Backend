<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DriverController extends Controller
{
    /**
     * List all drivers (Admin)
     */
    public function index()
    {
        $users = User::where('role', 'driver')
            ->with('driver')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'user' => $user,
                    'vehicle_type' => $user->driver->vehicle_type ?? null,
                    'license_number' => $user->driver->license_number ?? null,
                    'current_location' => $user->driver->current_location ?? null,
                    'is_available' => $user->driver->is_available ?? false,
                ];
            }),
        ]);
    }

    /**
     * Show driver profile (self-service or admin)
     */
    public function show(Request $request, $id = null)
    {
        $authUser = $request->user();

        if ($authUser->isAdmin() && $id) {
            $user = User::with('driver')->findOrFail($id);
            if ($user->role !== 'driver') {
                return response()->json(['message' => 'User is not a driver'], 400);
            }
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'user' => $user,
                    'driver' => $user->driver,
                ],
            ]);
        }

        if (!$authUser->isDriver()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $authUser,
                'driver' => $authUser->driver
            ]
        ]);
    }

    /**
     * Update driver (self-service or admin)
     */
    public function update(Request $request, $id = null)
    {
        $authUser = $request->user();

        if ($authUser->isAdmin() && $id) {
            $user = User::findOrFail($id);
            if ($user->role !== 'driver') {
                return response()->json(['message' => 'User is not a driver'], 400);
            }
        } else {
            if (!$authUser->isDriver()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            $user = $authUser;
        }

        $driver = $user->driver;
        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:6',
            'phone' => 'sometimes|string|max:20',
            'vehicle_type' => 'sometimes|string|max:255',
            'license_number' => 'sometimes|string|unique:drivers,license_number,' . $driver->id,
            'current_location' => 'sometimes|nullable|string|max:255',
            'is_available' => 'sometimes|boolean',
        ]);

        if (isset($validated['name'])) $user->name = $validated['name'];
        if (isset($validated['email'])) $user->email = $validated['email'];
        if (isset($validated['phone'])) $user->phone = $validated['phone'];
        if (isset($validated['password'])) $user->password = Hash::make($validated['password']);
        $user->save();

        if (isset($validated['vehicle_type'])) $driver->vehicle_type = $validated['vehicle_type'];
        if (isset($validated['license_number'])) $driver->license_number = $validated['license_number'];
        if (array_key_exists('current_location', $validated)) $driver->current_location = $validated['current_location'];
        if (array_key_exists('is_available', $validated)) $driver->is_available = $validated['is_available'];
        $driver->save();

        $user->load('driver');

        return response()->json([
            'success' => true,
            'message' => 'Driver updated successfully',
            'data' => [
                'user' => $user,
                'driver' => $driver,
            ],
        ]);
    }

    /**
     * Delete driver (self-service or admin)
     */
    public function destroy(Request $request, $id = null)
    {
        $authUser = $request->user();

        if ($authUser->isAdmin() && $id) {
            $user = User::findOrFail($id);
        } else {
            if (!$authUser->isDriver()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            $user = $authUser;
        }

        if ($user->role !== 'driver') {
            return response()->json(['message' => 'User is not a driver'], 400);
        }

        $driver = $user->driver;
        if ($driver) {
            $driver->delete();
        }
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Driver deleted successfully'
        ]);
    }
}