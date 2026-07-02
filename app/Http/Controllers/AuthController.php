<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Retailer;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
class AuthController extends Controller
{
    
public function registerRetailer(Request $request)
{ 

    // 1. VALIDATION
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'phone' => 'nullable|string|max:10',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6',
        
        /*
        'shop_name' => 'nullable|string|max:255',
        'address' => 'nullable|string',
        'city' => 'nullable|string',
        'image' => 'nullable|image|max:3200',
        'age' => 'nullable|integer',*/
    ]);

    // 2. CREATE USER
    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'phone' => $validated['phone'] ?? null,
        'role' => 'retailer', // IMPORTANT
        'is_active' => true,
    ]);

    // 3. HANDLE IMAGE (optional)
    $imagePath = null;
    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('retailers', 'public');
    }

    // 4. CREATE RETAILER (SHOP)
    $retailer = Retailer::create([
        'user_id' => $user->id,
        'shop_name' => $validated['shop_name'] ?? null,
        'address' => $validated['address'] ?? null,
        'city' => $validated['city'] ?? null,
        'image' => $imagePath,
        'age' => $validated['age'] ?? null,
    ]);
 
    // 5. CREATE TOKEN IMMEDIATELY
    $token = $user->createToken('retailer-token')->plainTextToken;
   
    // 6. RESPONSE
    return response()->json([
        'message' => 'Retailer registered successfully',
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'role' => $user->retailer,
        ]
    ], 201);
}











public function registerDriver(Request $request)
{
    // Validate request
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6',
        'phone' => 'nullable|string|max:20',
        'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

        'vehicle_type' => 'nullable|string|max:255',
        'license_number' => 'nullable|string|max:255|unique:drivers,license_number',
        'current_location' => 'nullable|string',
    ]);

    // Upload image
    $imagePath = null;

    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('users', 'public');
    }

    // Create user
    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'phone' => $validated['phone'] ?? null,
        'image' => $imagePath,
        'role' => 'driver',
        'is_active' => true,
    ]);

    // Create driver profile
    $driver = $user->driver()->create([
        'vehicle_type' => $validated['vehicle_type'] ?? null,
        'license_number' => $validated['license_number'] ?? null,
        'status' => 'available',
        'current_location' => $validated['current_location'] ?? null,
    ]);

    // Create Sanctum token
    $token = $user->createToken('driver-token')->plainTextToken;

    return response()->json([
        'message' => 'Driver registered successfully',
        'token_type' => 'Bearer',
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'image' => $user->image
                ? asset('storage/' . $user->image)
                : null,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ],
        'driver' => $driver,
    ], 201);
}















































public function login(Request $request)
{
    // 1. VALIDATION
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    // 2. ATTEMPT LOGIN
    if (!Auth::attempt($credentials)) {
        return response()->json([
            'message' => 'Invalid credentials'
        ], 401);
    }

    // 3. GET AUTHENTICATED USER
    $user = Auth::user();

    // 4. CHECK ACCOUNT STATUS
    if (!$user->is_active) {
        return response()->json([
            'message' => 'Account is inactive'
        ], 403);
    }

    // 5. DELETE OLD TOKENS (optional but recommended for MVP)
    $user->tokens()->delete();

    // 6. CREATE NEW TOKEN
    
     //$token = $user->createToken('api-token')->plainTextToken;

      
    $token = $user->createToken('auth-token',[$user->role])->plainTextToken;
    
    




    // 7. RETURN RESPONSE WITH ROLE
    return response()->json([
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'image' => $user->image,
        ]
    ]);
}



















  public function logout(Request $request)
   {
    $request->user()->currentAccessToken()->delete();

    return response()->json([
        'message' => 'Logged out successfully'
    ]); 
  }
 
   

}















































/*
public function register(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6',
        'phone' => 'required',
        'role' => 'required|in:jani,retailer,driver',
    ]);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'phone' => $request->phone,
        'role' => $request->role,
    ]);

    // 🔥 Create related profile automatically
    if ($user->role === 'retailer') {
        $user->retailer()->create([
            'shop_name' => $request->shop_name ?? 'Default Shop',
        ]);
    }

    if ($user->role === 'driver') {
        $user->driver()->create([
            'vehicle_type' => $request->vehicle_type ?? 'unknown',
        ]);
    }

    if ($user->role === 'employee') {
        $user->janiEmployee()->create([
            'role' => 'operator',
        ]);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'user' => $user,
        'token' => $token,
    ], 201);
}*/

