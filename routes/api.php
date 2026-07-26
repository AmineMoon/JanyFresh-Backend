

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
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\ExclusiveOfferController;

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

// Public Exclusive Offers
Route::get('/exclusive-offers', [ExclusiveOfferController::class, 'index']);
Route::get('/exclusive-offers/{id}', [ExclusiveOfferController::class, 'show']);

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
    | SHARED SUPPORT TICKET ROUTES (Retailer + Driver mobile apps)
    |--------------------------------------------------------------------------
    */

    Route::post('/support-tickets', [SupportTicketController::class, 'store']);
    Route::get('/support-tickets', [SupportTicketController::class, 'index']);
    Route::get('/support-tickets/{id}', [SupportTicketController::class, 'show']);

    /*
    |--------------------------------------------------------------------------
    | RETAILER ROUTES
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:retailer')->prefix('retailer')->group(function () {

        // Profile Management
        Route::prefix('profile')->group(function () {
            Route::get('/', [RetailerController::class, 'show']);
            Route::match(['put', 'post'], '/', [RetailerController::class, 'update']);
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
            Route::post('/{order}/confirm', [OrderController::class, 'confirm']);
        });

        // Product Browsing
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::get('/{product}', [ProductController::class, 'show']);
        });

        // Delivery Tracking
        Route::get('/deliveries', [DeliveryController::class, 'index']);
        Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show']);

        // Support Tickets
        Route::post('/support-tickets', [SupportTicketController::class, 'store']);
        Route::get('/support-tickets', [SupportTicketController::class, 'index']);
        Route::get('/support-tickets/{id}', [SupportTicketController::class, 'show']);

        // Notifications
        Route::get('/notifications', [DeviceTokenController::class, 'notifications']);
        Route::get('/notifications/unread-count', [DeviceTokenController::class, 'unreadCount']);
        Route::put('/notifications/{id}/read', [DeviceTokenController::class, 'markAsRead']);
        Route::put('/notifications/read-all', [DeviceTokenController::class, 'markAllAsRead']);
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
            Route::match(['put', 'post'], '/', [DriverController::class, 'update']);
            Route::delete('/', [DriverController::class, 'destroy']);
        });

        // Home Dashboard
        Route::get('/home', [DriverController::class, 'home']);

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

        // Support Tickets
        Route::post('/support-tickets', [SupportTicketController::class, 'store']);
        Route::get('/support-tickets', [SupportTicketController::class, 'index']);
        Route::get('/support-tickets/{id}', [SupportTicketController::class, 'show']);

        // Notifications
        Route::get('/notifications', [DeviceTokenController::class, 'notifications']);
        Route::get('/notifications/unread-count', [DeviceTokenController::class, 'unreadCount']);
        Route::put('/notifications/{id}/read', [DeviceTokenController::class, 'markAsRead']);
        Route::put('/notifications/read-all', [DeviceTokenController::class, 'markAllAsRead']);
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

        // Support Ticket Management
        Route::get('/support-tickets', [SupportTicketController::class, 'adminIndex']);
        Route::get('/support-tickets/{id}', [SupportTicketController::class, 'adminShow']);
        Route::put('/support-tickets/{id}', [SupportTicketController::class, 'adminUpdate']);
        Route::post('/support-tickets/{id}/respond', [SupportTicketController::class, 'adminRespond']);
        Route::delete('/support-tickets/{id}', [SupportTicketController::class, 'adminDestroy']);

        // Notification Management
        Route::post('/notifications', [NotificationController::class, 'store']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/stats', [NotificationController::class, 'stats']);
        Route::get('/notifications/{id}', [NotificationController::class, 'show']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

        // Exclusive Offer Management
        Route::get('/exclusive-offers', [ExclusiveOfferController::class, 'adminIndex']);
        Route::post('/exclusive-offers', [ExclusiveOfferController::class, 'store']);
        Route::put('/exclusive-offers/{id}/toggle-status', [ExclusiveOfferController::class, 'toggleStatus']);
        Route::get('/exclusive-offers/{id}', [ExclusiveOfferController::class, 'show']);
        Route::put('/exclusive-offers/{id}', [ExclusiveOfferController::class, 'update']);
        Route::delete('/exclusive-offers/{id}', [ExclusiveOfferController::class, 'destroy']);

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

    // Device Token Management (accessible by retailer and driver)
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::put('/device-tokens', [DeviceTokenController::class, 'update']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);
});