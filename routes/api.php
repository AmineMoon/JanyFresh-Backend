<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductexController;
use App\Http\Controllers\AuthenticationController;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\JaniEmployeeController;
use App\Http\Controllers\RetailerController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BoxRentalController;

 use App\Http\Controllers\CategoryController;
 use App\Http\Controllers\SubcategoryController;



 

 

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
| Routes accessible without authentication
|
*/

 //Route::prefix('auth')->group(function () {

    // Authentication
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register/retailer', [AuthController::class, 'registerRetailer']);
   
    // Optional: Driver self-registration

     //Route::post('/register/driver', [AuthController::class, 'registerDriver']);

 //});


/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
| Requires Sanctum Authentication
|
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */



    Route::post('/logout', [AuthController::class, 'logout']);

     /*
    Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
        return [
            'user' => $request->user(),
            'token' => $request->bearerToken(),
        ];
    });*/ 




    /*
    |--------------------------------------------------------------------------
    | Retailer Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:retailer')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Profile
        |--------------------------------------------------------------------------
        */

        
        Route::get('/retailer/profile', [RetailerController::class, 'show']);
        Route::put('/retailer/profile', [RetailerController::class, 'update']);
        Route::delete('/retailer/profile', [RetailerController::class, 'destroy']);



        /*
        |--------------------------------------------------------------------------
        | Favorites
        |--------------------------------------------------------------------------
        */

        Route::apiResource('favorites', FavoriteController::class)
            ->only(['index', 'store', 'destroy']);



        /*
        |--------------------------------------------------------------------------
        | Orders
        |--------------------------------------------------------------------------
        */

     Route::post('/retailer/orders', [OrderController::class, 'store']);
     Route::get('/retailer/orders', [OrderController::class, 'index']);
     Route::get('/retailer/orders/{order}', [OrderController::class, 'show']);

   




        /*
        |--------------------------------------------------------------------------
        | Products (Browse Only)
        |--------------------------------------------------------------------------
        */

        Route::get('/b/products', [ProductController::class, 'index']);
        Route::get('/b/products/{product}', [ProductController::class, 'show']);

        
    });







    

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | User Management
        |--------------------------------------------------------------------------
        */

        Route::apiResource('admins', AdminController::class);
        
        Route::apiResource('retailers', RetailerController::class);

        Route::post('/register/driver', [AuthController::class, 'registerDriver']);
        Route::apiResource('drivers', DriverController::class);



        /*
        |--------------------------------------------------------------------------
        | Product Management
        |--------------------------------------------------------------------------
        */

        Route::apiResource('products', ProductController::class);



        /*
        |--------------------------------------------------------------------------
        | Order Management
        |--------------------------------------------------------------------------
        */

        Route::apiResource('orders', OrderController::class);
        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm']);


        /*
        |--------------------------------------------------------------------------
        | Delivery Management
        |--------------------------------------------------------------------------
        */

        Route::apiResource('deliveries', DeliveryController::class);
        


        /*
        |--------------------------------------------------------------------------
        | Payment Management
        |--------------------------------------------------------------------------
        */

        Route::apiResource('payments', PaymentController::class);
    });



    
        /*
        |--------------------------------------------------------------------------
        | category
        |--------------------------------------------------------------------------
        */
  

        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('subcategories', SubcategoryController::class);



     /*
        |--------------------------------------------------------------------------
        | Renting
        |--------------------------------------------------------------------------
        */
    
           Route::apiResource('box-rentals', BoxRentalController::class);











    /*
    |--------------------------------------------------------------------------
    | Driver Routes
    |--------------------------------------------------------------------------
    */
 Route::get('/', [DeliveryController::class, 'index']);

       Route::post('/deliveries', [DeliveryController::class, 'store']);
       
       
       //Route::post('/deliveries', [DeliveryController::class, 'store']);
    //Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show']);
    Route::put('/deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus']);
    Route::delete('/deliveries/{delivery}', [DeliveryController::class, 'destroy']);
    Route::post('/deliveries/{delivery}/assign', [DeliveryController::class, 'assignDriver']);
    Route::get('/deliveries/my', [DeliveryController::class, 'myDeliveries']);
    
    // NEW ROUTES
    Route::get('/deliveries/available-retailers', [DeliveryController::class, 'getAvailableRetailers']);
    Route::post('/deliveries/by-retailer', [DeliveryController::class, 'assignByRetailer']);
      Route::middleware('role:driver')->group(function () {


        /*
        |--------------------------------------------------------------------------
        | Driver Profile
        |--------------------------------------------------------------------------
        */

        Route::get('/driver/profile', [DriverController::class, 'show']);
        Route::put('/driver/profile', [DriverController::class, 'update']);
        Route::delete('/driver/profile', [DriverController::class, 'destroy']);



            /*
        |--------------------------------------------------------------------------
        |  Orders 
        |--------------------------------------------------------------------------
        */
       
        Route::get('/driver/status', [OrderController::class, 'status']);
       
        Route::get('/driver/order_info', [OrderController::class, 'order_info']);
        Route::get('/driver/orders/{order}', [OrderController::class, 'show']);

    

        /*
        |--------------------------------------------------------------------------
        | Assigned Deliveries
        |--------------------------------------------------------------------------
        */

           // Route::apiResource('deliveries', DeliveryController::class);
            
           //Route::put('/deliveries/{delivery}/assign-driver', [DeliveryController::class, 'assignDriver']);
            
    
           
   /*

//  ✅ MUST be before {delivery}
Route::get('/deliveries/my', [DeliveryController::class, 'myDeliveries']);

Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show']);

Route::delete('/{delivery}', [DeliveryController::class, 'destroy']);

// Status update route
Route::put('/deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus']);

// Assign driver route
Route::post('/deliveries/{delivery}/assign', [DeliveryController::class, 'assignDriver']);
    });
    */

    

    });

});