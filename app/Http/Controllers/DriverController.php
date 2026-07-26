<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Delivery;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

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

        $driver = $authUser->driver;
        if (!$driver) {
            $driver = $authUser->driver()->create([
                'status' => 'available',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $authUser,
                'driver' => $driver
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
            $driver = $user->driver()->create([
                'status' => 'available',
            ]);
        }

        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|nullable|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|nullable|string|min:6',
            'phone' => 'sometimes|nullable|string|max:20',
            'vehicle_type' => 'sometimes|nullable|string|max:255',
            'license_number' => 'sometimes|nullable|string|unique:drivers,license_number,' . $driver->id,
            'current_location' => 'sometimes|nullable|string|max:255',
            'is_available' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if (isset($validated['name'])) $user->name = $validated['name'];
        if (isset($validated['email'])) $user->email = $validated['email'];
        if (isset($validated['phone'])) $user->phone = $validated['phone'];
        if (isset($validated['password'])) $user->password = Hash::make($validated['password']);

        if ($request->hasFile('image')) {
            if ($user->image && Storage::disk('public')->exists($user->image)) {
                Storage::disk('public')->delete($user->image);
            }
            $user->image = $request->file('image')->store('users', 'public');
        }

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
     * Get driver home dashboard data
     */
    public function home()
    {
        $user = Auth::user();
        $driver = $user->driver;

        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found.'], 404);
        }

        $deliveries = Delivery::where('driver_id', $driver->id);

        $statusCounts = (clone $deliveries)
            ->selectRaw("
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS total_completed,
                SUM(CASE WHEN status IN ('assigned', 'picked_up') THEN 1 ELSE 0 END) AS total_pending,
                SUM(CASE WHEN status = 'in_transit' THEN 1 ELSE 0 END) AS total_in_transit
            ")
            ->first();

        $earnings = (clone $deliveries)
            ->where('status', 'delivered')
            ->selectRaw("
                COALESCE(SUM(driver_earnings), 0) AS total_earnings,
                COALESCE(SUM(CASE WHEN DATE(delivered_at) = CURDATE() THEN driver_earnings ELSE 0 END), 0) AS today_earnings,
                COALESCE(SUM(CASE WHEN YEARWEEK(delivered_at, 1) = YEARWEEK(CURDATE(), 1) THEN driver_earnings ELSE 0 END), 0) AS this_week_earnings,
                COALESCE(SUM(CASE WHEN MONTH(delivered_at) = MONTH(CURDATE()) AND YEAR(delivered_at) = YEAR(CURDATE()) THEN driver_earnings ELSE 0 END), 0) AS this_month_earnings
            ")
            ->first();

        $periodCounts = (clone $deliveries)
            ->where('status', 'delivered')
            ->selectRaw("
                SUM(CASE WHEN DATE(delivered_at) = CURDATE() THEN 1 ELSE 0 END) AS today_completed,
                SUM(CASE WHEN YEARWEEK(delivered_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) AS this_week_completed,
                SUM(CASE WHEN MONTH(delivered_at) = MONTH(CURDATE()) AND YEAR(delivered_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS this_month_completed
            ")
            ->first();

        $recentDeliveries = Delivery::where('driver_id', $driver->id)
            ->where('status', 'delivered')
            ->with('order:id,order_number')
            ->latest('delivered_at')
            ->limit(5)
            ->get()
            ->map(fn ($d) => [
                'order_number' => $d->order->order_number,
                'delivered_at' => $d->delivered_at,
                'driver_earnings' => $d->driver_earnings,
                'status' => $d->status,
            ]);

        return response()->json([
            'total_completed_deliveries' => (int) $statusCounts->total_completed,
            'total_pending_deliveries' => (int) $statusCounts->total_pending,
            'total_in_transit_deliveries' => (int) $statusCounts->total_in_transit,
            'total_earnings' => (float) $earnings->total_earnings,
            'today_earnings' => (float) $earnings->today_earnings,
            'this_week_earnings' => (float) $earnings->this_week_earnings,
            'this_month_earnings' => (float) $earnings->this_month_earnings,
            'today_completed_deliveries' => (int) $periodCounts->today_completed,
            'this_week_completed_deliveries' => (int) $periodCounts->this_week_completed,
            'this_month_completed_deliveries' => (int) $periodCounts->this_month_completed,
            'recent_deliveries' => $recentDeliveries,
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

        if ($user->image && Storage::disk('public')->exists($user->image)) {
            Storage::disk('public')->delete($user->image);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Driver deleted successfully'
        ]);
    }
}