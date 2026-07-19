<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Driver;
use App\Models\Admin;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    /**
     * Get comprehensive dashboard statistics.
     *
     * Returns all key metrics for the admin dashboard:
     * - Order counts by status
     * - Total products, retailers, drivers, admins
     * - Revenue and delivery fees
     * - Recent orders list
     */
    public function stats()
    {
        try {
            // Order status counts via single query
            $orderStats = Order::selectRaw("
                COUNT(*) AS total_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_orders,
                SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) AS preparing_orders,
                SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) AS out_for_delivery_orders,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
                COUNT(DISTINCT retailer_id) AS retailers_with_orders
            ")
            ->first();

            // Financial aggregates (only delivered orders for revenue accuracy)
            $financialStats = Order::selectRaw("
                COALESCE(SUM(total), 0) AS total_revenue,
                COALESCE(SUM(delivery_fee), 0) AS total_delivery_fees
            ")
            ->where('status', 'delivered')
            ->first();

            // Entity counts
            $totalProducts = Product::count();
            $totalRetailers = Retailer::count();
            $totalDrivers = Driver::count();
            $totalAdmins = Admin::count();

            // Recent orders with relationships
            $recentOrders = Order::with([
                'retailer.user',
                'items.product'
            ])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total' => $order->total,
                    'created_at' => $order->created_at,
                    'retailer' => $order->retailer ? [
                        'name' => $order->retailer->user->name ?? 'Unknown',
                    ] : null,
                ];
            });

            // Build response
            $stats = [
                'total_orders'           => (int) ($orderStats->total_orders ?? 0),
                'pending_orders'         => (int) ($orderStats->pending_orders ?? 0),
                'confirmed_orders'       => (int) ($orderStats->confirmed_orders ?? 0),
                'preparing_orders'       => (int) ($orderStats->preparing_orders ?? 0),
                'out_for_delivery_orders' => (int) ($orderStats->out_for_delivery_orders ?? 0),
                'delivered_orders'       => (int) ($orderStats->delivered_orders ?? 0),
                'cancelled_orders'       => (int) ($orderStats->cancelled_orders ?? 0),
                'retailers_with_orders'  => (int) ($orderStats->retailers_with_orders ?? 0),
                'total_products'         => $totalProducts,
                'total_retailers'        => $totalRetailers,
                'total_drivers'          => $totalDrivers,
                'total_admins'           => $totalAdmins,
                'total_revenue'          => (float) ($financialStats->total_revenue ?? 0),
                'total_delivery_fees'    => (float) ($financialStats->total_delivery_fees ?? 0),
                'recent_orders'          => $recentOrders,
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error('AdminDashboardController::stats error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to load dashboard statistics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}