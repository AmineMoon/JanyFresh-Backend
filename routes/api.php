

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\BoxRentalController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RetailerController;
use App\Http\Controllers\SubcategoryController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
| Routes accessible without authentication
|
*/

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register/retailer', [AuthController::class, 'registerRetailer']);
    Route::post('/register/driver', [AuthController::class, 'registerDriver'])->middleware('auth:sanctum', 'role:admin');
});

// Public Product Browsing (Optional - if you want guests to view products)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

// Public Categories
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/active', [CategoryController::class, 'active']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/subcategories', [SubcategoryController::class, 'index']);
Route::get('/subcategories/{subcategory}', [SubcategoryController::class, 'show']);

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES
|--------------------------------------------------------------------------
| Requires Sanctum Authentication
|
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATION & USER MANAGEMENT
    |--------------------------------------------------------------------------
    */

    // User Profile
    Route::get('/me', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'token' => $request->bearerToken(),
        ]);
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | RETAILER ROUTES
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:retailer')->prefix('retailer')->group(function () {

        // Profile Management
        Route::prefix('profile')->group(function () {
            Route::get('/', [RetailerController::class, 'show']);
            Route::put('/', [RetailerController::class, 'update']);
            Route::delete('/', [RetailerController::class, 'destroy']);
        });

        // Favorites
        Route::apiResource('favorites', FavoriteController::class)->only(['index', 'store', 'destroy']);

        // Orders Management
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store']);
            Route::get('/{order}', [OrderController::class, 'show']);
            Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
        });

        // Product Browsing
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::get('/{product}', [ProductController::class, 'show']);
        });

        // Delivery Tracking
        Route::get('/deliveries', [DeliveryController::class, 'index']);
        Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | DRIVER ROUTES
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:driver')->prefix('driver')->group(function () {

        // Profile Management
        Route::prefix('profile')->group(function () {
            Route::get('/', [DriverController::class, 'show']);
            Route::put('/', [DriverController::class, 'update']);
            Route::delete('/', [DriverController::class, 'destroy']);
        });

        // Order Management
        Route::prefix('orders')->group(function () {
            Route::get('/status', [OrderController::class, 'status']);
            Route::get('/info', [OrderController::class, 'order_info']);
            Route::get('/{order}', [OrderController::class, 'show']);
        });

        // Delivery Management
        Route::prefix('deliveries')->group(function () {
            Route::get('/my', [DeliveryController::class, 'myDeliveries']);
            Route::get('/available-retailers', [DeliveryController::class, 'getAvailableRetailers']);
            Route::post('/by-retailer', [DeliveryController::class, 'assignByRetailer']);
            Route::get('/{delivery}', [DeliveryController::class, 'show']);
            Route::put('/{delivery}/status', [DeliveryController::class, 'updateStatus']);
            Route::post('/{delivery}/assign', [DeliveryController::class, 'assignDriver']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN ROUTES
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->prefix('admin')->group(function () {

        // User Management
        Route::prefix('users')->group(function () {
            Route::apiResource('admins', AdminController::class);
            Route::apiResource('retailers', RetailerController::class);
            Route::apiResource('drivers', DriverController::class);
            Route::post('/register-driver', [AuthController::class, 'registerDriver']);
        });

        // Product Management
        Route::apiResource('products', ProductController::class);

        // Category Management
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{category}', [CategoryController::class, 'show']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        // Subcategory Management
        Route::post('/subcategories', [SubcategoryController::class, 'store']);
        Route::get('/subcategories', [SubcategoryController::class, 'index']);
        Route::get('/subcategories/{subcategory}', [SubcategoryController::class, 'show']);
        Route::put('/subcategories/{subcategory}', [SubcategoryController::class, 'update']);
        Route::delete('/subcategories/{subcategory}', [SubcategoryController::class, 'destroy']);

        // Order Management
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store']);
            // Static routes must come before wildcard {order} routes
            Route::get('/by-retailer', [OrderController::class, 'byRetailer']);
            Route::get('/retailer/{retailerId}', [OrderController::class, 'retailerOrders']);
            Route::patch('/bulk-status', [OrderController::class, 'bulkStatusUpdate']);
            Route::delete('/bulk', [OrderController::class, 'bulkDelete']);
            Route::get('/{order}', [OrderController::class, 'show']);
            Route::put('/{order}', [OrderController::class, 'update']);
            Route::delete('/{order}', [OrderController::class, 'destroy']);
            Route::post('/{order}/confirm', [OrderController::class, 'confirm']);
        });

        // Delivery Management
        Route::prefix('deliveries')->group(function () {
            Route::get('/', [DeliveryController::class, 'index']);
            Route::post('/', [DeliveryController::class, 'store']);
            Route::get('/{delivery}', [DeliveryController::class, 'show']);
            Route::put('/{delivery}', [DeliveryController::class, 'update']);
            Route::delete('/{delivery}', [DeliveryController::class, 'destroy']);
            Route::put('/{delivery}/status', [DeliveryController::class, 'updateStatus']);
            Route::post('/{delivery}/assign', [DeliveryController::class, 'assignDriver']);
        });

        // Payment Management
        Route::apiResource('payments', PaymentController::class);

        // Box Rental Management
        Route::apiResource('box-rentals', BoxRentalController::class);

        // Admin Profile (self-service)
        Route::prefix('profile')->group(function () {
            Route::get('/', [AdminController::class, 'showProfile']);
            Route::put('/', [AdminController::class, 'updateProfile']);
        });

        // Dashboard Statistics
        Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);
    });

    /*
    |--------------------------------------------------------------------------
    | SHARED ROUTES (Available to multiple roles)
    |--------------------------------------------------------------------------
    */

    // Delivery Routes accessible by both Retailers and Admins
    Route::prefix('deliveries')->group(function () {
        Route::get('/', [DeliveryController::class, 'index']);
        Route::get('/{delivery}', [DeliveryController::class, 'show']);
    });
});