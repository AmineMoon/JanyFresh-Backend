<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Retailer;
use App\Models\OrderItem;
use App\Models\Product;
use App\Events\OrderStatusUpdated;
use App\Events\OrderConfirmed;
use App\Events\OrderCancelled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


class OrderController extends Controller
{
    /**
     * List all orders for the authenticated retailer
     */
    public function index()
    {
        $orders = Order::with([
            'retailer.user',
            'items.product.images',
            'items.product.primaryImage'
        ])
        ->where('retailer_id', auth()->user()->retailer->id)
        ->latest()
        ->get();

        \Log::info('[OrderController@index] Returning orders', [
            'count' => $orders->count(),
            'sample' => $orders->first() ? [
                'id' => $orders->first()->id,
                'status' => $orders->first()->status,
                'cancellation_deadline' => $orders->first()->cancellation_deadline,
                'can_cancel' => $orders->first()->can_cancel,
            ] : null
        ]);

        return response()->json($orders);
    }

    /**
     * Create new order
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $order = DB::transaction(function () use ($request) {

            $subtotal = 0;

            $order = Order::create([

                'order_number' => 'ORD-' . time(),
                'retailer_id' => auth()->user()->retailer->id,
                'status' => 'pending',
                'cancellation_deadline' => now()->addMinutes(10),
                'subtotal' => 0,
                'discount' => 0,
                'delivery_fee' => 0,
                'total' => 0,
            ]);

            foreach ($request->items as $item) {

                $product = Product::findOrFail($item['product_id']);

                $lineSubtotal = $product->price * $item['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $lineSubtotal,
                ]);

                $subtotal += $lineSubtotal;
            }

            $deliveryFee = $subtotal * 0.05; // 5%
            $discount = 0;

            $total = $subtotal + $deliveryFee - $discount;

            $order->update([
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'discount' => $discount,
                'total' => $total,
            ]);

            return $order;
        });

        // Broadcast the new order (use OrderStatusUpdated with null previous status)
        OrderStatusUpdated::dispatch($order->load('items.product'), null);

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order->load('items.product')
        ], 201);
    }

    /**
     * Show single order
     */
    public function show(Order $order)
    {
        return $order->load([
            'retailer.user',
            'confirmedBy',
            'items.product'
        ]);
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,assigned,out_for_delivery,delivered,cancelled'
        ]);

        $previousStatus = $order->status;
        
        $order->update([
            'status' => $request->status
        ]);

        // Broadcast the status update
        OrderStatusUpdated::dispatch($order, $previousStatus);

        return response()->json([
            'message' => 'Status updated successfully',
            'order' => $order
        ]);
    }

    /**
     * Delete order
     */
    public function destroy(Order $order)
    {
        $order->delete();

        return response()->json([
            'message' => 'Order deleted successfully'
        ]);
    }

    /**
     * Confirm order (admin)
     */
    public function confirm(Order $order)
    {
        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Order cannot be confirmed'
            ], 422);
        }

        $delivery = null;
        
        DB::transaction(function () use ($order) {

            $order->update([
                'status' => 'confirmed',
                'confirmed_by' => auth()->id(),
            ]);

            $delivery = $order->delivery()->create([
                'status' => 'assigned',
                'assigned_by'=> auth()->id(),
            ]);
        });

        // Broadcast the confirmation
        OrderConfirmed::dispatch($order->fresh(), $delivery);

        return response()->json([
            'message' => 'Order confirmed and sent to deliveries',
            'order' => $order->fresh()
        ]);
    }

    /**
     * Get order status summary for dashboard
     */
    public function status()
    {
        $stats = Order::selectRaw("
                COUNT(*) AS total_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_orders,
                COUNT(DISTINCT retailer_id) AS total_retailers
            ")
            ->first();

        return response()->json($stats);
    }

    /**
     * Get retailers with their orders statistics (for admin by-retailer view)
     */
    public function byRetailer()
    {
        // Get all retailers with orders in a single query with counts
        $retailers = Retailer::with(['user'])
            ->whereHas('orders')
            ->withCount('orders')
            ->withSum('orders', 'total')
            ->withSum('orders', 'delivery_fee')
            ->get();

        if ($retailers->isEmpty()) {
            return response()->json(['retailers' => []]);
        }

        $retailerIds = $retailers->pluck('id');

        // Single query: get status counts for all retailers at once
        $statusCounts = Order::selectRaw("
                retailer_id,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) as preparing,
                SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
                SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) as out_for_delivery,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            ")
            ->whereIn('retailer_id', $retailerIds)
            ->groupBy('retailer_id')
            ->get()
            ->keyBy('retailer_id');

        // Single query: get latest 5 orders per retailer using window functions
        $recentOrdersRaw = Order::selectRaw("
                id, retailer_id, order_number, status, total, created_at,
                ROW_NUMBER() OVER (PARTITION BY retailer_id ORDER BY created_at DESC) as rn
            ")
            ->whereIn('retailer_id', $retailerIds)
            ->get()
            ->filter(fn($o) => $o->rn <= 5)
            ->groupBy('retailer_id');

        // Single query: get latest order date per retailer
        $latestDates = Order::selectRaw("retailer_id, MAX(created_at) as latest_date")
            ->whereIn('retailer_id', $retailerIds)
            ->groupBy('retailer_id')
            ->get()
            ->keyBy('retailer_id');

        $result = $retailers->map(function ($retailer) use ($statusCounts, $recentOrdersRaw, $latestDates) {
            $counts = $statusCounts->get($retailer->id);
            $orders = $recentOrdersRaw->get($retailer->id, collect());
            $latest = $latestDates->get($retailer->id);

            return [
                'id' => $retailer->id,
                'shop_name' => $retailer->shop_name,
                'user_name' => $retailer->user->name ?? 'Unknown',
                'email' => $retailer->user->email ?? null,
                'phone' => $retailer->user->phone ?? null,
                'image' => $retailer->image ?? null,
                'address' => $retailer->address,
                'city' => $retailer->city,
                'total_orders' => $retailer->orders_count,
                'total_revenue' => (float) ($retailer->orders_sum_total ?? 0),
                'total_delivery_fees' => (float) ($retailer->orders_sum_delivery_fee ?? 0),
                'latest_order_date' => $latest?->latest_date,
                'orders_count_by_status' => [
                    'pending' => (int) ($counts->pending ?? 0),
                    'confirmed' => (int) ($counts->confirmed ?? 0),
                    'preparing' => (int) ($counts->preparing ?? 0),
                    'assigned' => (int) ($counts->assigned ?? 0),
                    'out_for_delivery' => (int) ($counts->out_for_delivery ?? 0),
                    'delivered' => (int) ($counts->delivered ?? 0),
                    'cancelled' => (int) ($counts->cancelled ?? 0),
                ],
                'recent_orders' => $orders->map(fn($o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'status' => $o->status,
                    'total' => (float) ($o->total ?? 0),
                    'created_at' => $o->created_at,
                ])->values(),
            ];
        })
            ->sortByDesc('latest_order_date')
            ->values();

        return response()->json([
            'retailers' => $result
        ]);
    }

    /**
     * Get orders for a specific retailer (for admin retailer detail page)
     */
    public function retailerOrders($retailerId)
    {
        $retailer = Retailer::with(['user'])
            ->findOrFail($retailerId);

        $orders = Order::with([
            'items.product',
            'confirmedBy'
        ])
        ->where('retailer_id', $retailerId)
        ->latest()
        ->get()
        ->map(function ($order) {
            $products = $order->items->map(function ($item) {
                return [
                    'name' => $item->product->name ?? 'Unknown Product',
                    'quantity' => $item->quantity,
                    'price' => (float) ($item->price ?? 0),
                    'subtotal' => (float) ($item->subtotal ?? 0),
                ];
            })->toArray();

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'cancellation_deadline' => $order->cancellation_deadline,
                'can_cancel' => $order->can_cancel,
                'subtotal' => (float) ($order->subtotal ?? 0),
                'discount' => (float) ($order->discount ?? 0),
                'delivery_fee' => (float) ($order->delivery_fee ?? 0),
                'total' => (float) ($order->total ?? 0),
                'products' => $products,
                'created_at' => $order->created_at,
                'confirmed_by_name' => $order->confirmedBy->name ?? null,
            ];
        });

        $totalOrders = $orders->count();
        $totalRevenue = $orders->sum('total');

        return response()->json([
            'retailer' => [
                'id' => $retailer->id,
                'shop_name' => $retailer->shop_name,
                'image' => $retailer->image ?? null,
                'address' => $retailer->address,
                'city' => $retailer->city,
                'owner_name' => $retailer->user->name ?? 'Unknown',
                'owner_email' => $retailer->user->email ?? null,
                'owner_phone' => $retailer->user->phone ?? null,
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
            ],
            'orders' => $orders
        ]);
    }

    /**
     * Get order info grouped by retailer (for driver dashboard)
     */
    public function order_info()
    {
        $retailers = Retailer::with([
            'user',
            'orders.items.product.images',
            'orders.items.product.primaryImage',
        ])
        ->has('orders')
        ->withCount('orders')
        ->withSum('orders', 'delivery_fee')
        ->withSum('orders', 'subtotal')
        ->get();

        return response()->json($retailers);
    }

    /**
     * Bulk update order status
     */
    public function bulkStatusUpdate(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
            'status' => 'required|in:pending,confirmed,preparing,assigned,out_for_delivery,delivered,cancelled'
        ]);

        $orderIds = $request->order_ids;
        $updated = Order::whereIn('id', $orderIds)
            ->update(['status' => $request->status]);

        // Broadcast updates for each updated order
        $orders = Order::whereIn('id', $orderIds)->get();
        foreach ($orders as $order) {
            OrderStatusUpdated::dispatch($order, null);
        }

        return response()->json([
            'message' => "$updated orders updated successfully",
            'updated_count' => $updated
        ]);
    }

    /**
     * Bulk delete orders
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id'
        ]);

        // Delete order items first (cascade should handle this, but being explicit)
        OrderItem::whereIn('order_id', $request->order_ids)->delete();

        $deleted = Order::whereIn('id', $request->order_ids)->delete();

        return response()->json([
            'message' => "$deleted orders deleted successfully",
            'deleted_count' => $deleted
        ]);
    }

    /**
     * Cancel order within the 10-second cancellation window (retailer)
     */
    public function cancel(Request $request, Order $order)
    {
        // Log incoming request details
        \Log::info('[OrderController@cancel] Received cancel request', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_status' => $order->status,
            'cancellation_deadline' => $order->cancellation_deadline,
            'server_time' => now()->toIso8601String(),
        ]);

        // Log authenticated user
        $user = auth()->user();
        \Log::info('[OrderController@cancel] Authenticated user', [
            'user_id' => $user?->id,
            'user_role' => $user?->role,
            'retailer_id' => $user?->retailer?->id,
        ]);

        // Ensure the order belongs to the authenticated retailer
        if ($order->retailer_id !== $user->retailer->id) {
            \Log::warning('[OrderController@cancel] Unauthorized: order retailer_id does not match user retailer_id', [
                'order_retailer_id' => $order->retailer_id,
                'user_retailer_id' => $user->retailer->id,
            ]);
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->status !== 'pending') {
            \Log::warning('[OrderController@cancel] Rejected: order status is not pending', [
                'order_status' => $order->status,
            ]);
            return response()->json(['message' => 'Order cannot be cancelled at this stage'], 422);
        }

        if (!$order->cancellation_deadline || now()->greaterThan($order->cancellation_deadline)) {
            \Log::warning('[OrderController@cancel] Rejected: cancellation deadline passed', [
                'cancellation_deadline' => $order->cancellation_deadline,
                'server_time' => now()->toIso8601String(),
                'deadline_passed' => true,
            ]);
            return response()->json(['message' => 'Cancellation window has expired'], 422);
        }

        \Log::info('[OrderController@cancel] Accepted: proceeding with cancellation');

        // Capture IDs before deletion (needed for broadcast channel auth + response)
        $orderId = $order->id;
        $orderNumber = $order->order_number;
        $retailerId = $order->retailer_id;

        // Load relations for broadcast payload BEFORE deletion
        $orderForBroadcast = $order->fresh()->load('items.product');

        // Broadcast the cancellation (must happen before delete so channel auth works)
        OrderCancelled::dispatch($orderForBroadcast, auth()->user()->name);

        // Delete the order (cascade removes order_items via FK constraint)
        $order->delete();

        \Log::info('[OrderController@cancel] Order deleted successfully', [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
        ]);

        return response()->json([
            'message' => 'Order cancelled successfully',
            'order_id' => $orderId,
        ]);
    }

}