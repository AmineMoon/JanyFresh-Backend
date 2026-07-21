<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Order;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Order status updates - only relevant users can listen
Broadcast::channel('order-status.{orderId}', function ($user, $orderId) {
    try {
        $order = Order::findOrFail($orderId);

        return $user->isAdmin()
            || ($user->isRetailer() && $order->retailer_id === $user->retailer->id)
            || ($user->isDriver() && $order->delivery && $order->delivery->driver_id === $user->driver->id);
    } catch (\Exception $e) {
        return false;
    }
});

// Retailer orders - retailer can listen to their own orders, admin can listen to all
Broadcast::channel('retailer-orders.{retailerId}', function ($user, $retailerId) {
    return $user->isAdmin()
        || ($user->isRetailer() && $user->retailer->id === $retailerId);
});

// Admin orders - only admins can listen
Broadcast::channel('admin-orders', function ($user) {
    return $user->isAdmin();
});

// Driver deliveries - only the assigned driver can listen
Broadcast::channel('driver-deliveries.{driverId}', function ($user, $driverId) {
    return $user->isDriver() && $user->driver->id == $driverId;
});

// Driver available orders - only the driver can listen for new available order notifications
Broadcast::channel('driver-available-orders.{driverId}', function ($user, $driverId) {
    return $user->isDriver() && $user->driver->id == $driverId;
});

// Delivery status updates
Broadcast::channel('delivery-status.{deliveryId}', function ($user, $deliveryId) {
    try {
        $delivery = \App\Models\Delivery::findOrFail($deliveryId);

        return $user->isAdmin()
            || ($user->isDriver() && $delivery->driver_id === $user->driver->id)
            || ($user->isRetailer() && $delivery->order->retailer_id === $user->retailer->id);
    } catch (\Exception $e) {
        return false;
    }
});
