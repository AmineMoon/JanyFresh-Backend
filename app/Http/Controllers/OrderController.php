<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Retailer;
use App\Models\OrderItem;
use App\Models\Product;
use App\Events\OrderStatusUpdated;
use App\Events\OrderCancelled;
use App\Events\OrderConfirmed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    /**
     * List orders — all orders for admin, own orders for retailer
     */
    public function index()
    {
        $query = Order::with([
            'retailer.user',
            'confirmedBy',
            'items.product.images',
            'items.product.primaryImage'
        ])
        ->latest();

        if (auth()->user()->role !== 'admin') {
            $query->where('retailer_id', auth()->user()->retailer->id);
        }

        if (request()->has('status') && request('status') !== '') {
            $query->where('status', request('status'));
        }

        $orders = $query->get();

        return response()->json($orders);
    }

    /**
     * Create new order
     */
    public function store(Request $request)
    {
        $isAdmin = auth()->user()->role === 'admin';

        $request->validate(array_merge([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ], $isAdmin ? ['retailer_id' => 'required|exists:retailers,id'] : []));

        $order = DB::transaction(function () use ($request, $isAdmin) {

            $subtotal = 0;

            $retailerId = $isAdmin
                ? $request->retailer_id
                : auth()->user()->retailer->id;

            $order = Order::create([
                'order_number' => 'ORD-' . time(),
                'retailer_id' => $retailerId,
                'status' => Order::STATUS_PENDING,
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

            $deliveryFee = $subtotal * 0.05;
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
     * Update order (admin)
     */
    public function update(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'sometimes|in:pending,available,assigned,out_for_delivery,delivered,cancelled,failed',
            'discount' => 'sometimes|numeric|min:0',
            'delivery_fee' => 'sometimes|numeric|min:0',
        ]);

        $previousStatus = $order->status;

        $data = $request->only(['status', 'discount', 'delivery_fee']);

        if (isset($data['discount']) || isset($data['delivery_fee'])) {
            $discount = $data['discount'] ?? $order->discount;
            $deliveryFee = $data['delivery_fee'] ?? $order->delivery_fee;
            $data['total'] = $order->subtotal + $deliveryFee - $discount;
        }

        $order->update($data);

        if (isset($data['status']) && $data['status'] !== $previousStatus) {
            OrderStatusUpdated::dispatch($order->fresh(), $previousStatus);
        }

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $order->fresh()->load(['items.product', 'retailer.user']),
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
     * Get order status summary for dashboard
     */
    public function status()
    {
        $stats = Order::selectRaw("
                COUNT(*) AS total_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available_orders,
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

        $statusCounts = Order::selectRaw("
                retailer_id,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
                SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) as out_for_delivery,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            ")
            ->whereIn('retailer_id', $retailerIds)
            ->groupBy('retailer_id')
            ->get()
            ->keyBy('retailer_id');

        $recentOrdersRaw = Order::selectRaw("
                id, retailer_id, order_number, status, total, created_at,
                ROW_NUMBER() OVER (PARTITION BY retailer_id ORDER BY created_at DESC) as rn
            ")
            ->whereIn('retailer_id', $retailerIds)
            ->get()
            ->filter(fn($o) => $o->rn <= 5)
            ->groupBy('retailer_id');

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
                    'available' => (int) ($counts->available ?? 0),
                    'assigned' => (int) ($counts->assigned ?? 0),
                    'out_for_delivery' => (int) ($counts->out_for_delivery ?? 0),
                    'delivered' => (int) ($counts->delivered ?? 0),
                    'cancelled' => (int) ($counts->cancelled ?? 0),
                    'failed' => (int) ($counts->failed ?? 0),
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
            'status' => 'required|in:pending,available,assigned,out_for_delivery,delivered,cancelled,failed'
        ]);

        $orderIds = $request->order_ids;
        $updated = Order::whereIn('id', $orderIds)
            ->update(['status' => $request->status]);

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

        OrderItem::whereIn('order_id', $request->order_ids)->delete();

        $deleted = Order::whereIn('id', $request->order_ids)->delete();

        return response()->json([
            'message' => "$deleted orders deleted successfully",
            'deleted_count' => $deleted
        ]);
    }

    /**
     * Cancel order within the cancellation window (retailer)
     */
    public function cancel(Request $request, Order $order)
    {
        $user = auth()->user();

        if ($order->retailer_id !== $user->retailer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return response()->json(['message' => 'Order cannot be cancelled at this stage'], 422);
        }

        $orderId = $order->id;

        $orderForBroadcast = $order->fresh()->load('items.product');

        OrderCancelled::dispatch($orderForBroadcast, auth()->user()->name);

        $order->delete();

        return response()->json([
            'message' => 'Order deleted successfully',
            'order_id' => $orderId,
        ]);
    }

    /**
     * Confirm order as available (retailer) — skip the 10-minute cancellation window
     */
    public function confirm(Request $request, Order $order)
    {
        $user = auth()->user();

        if ($order->retailer_id !== $user->retailer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return response()->json(['message' => 'Only pending orders can be confirmed'], 422);
        }

        $previousStatus = $order->status;

        $order->update([
            'status' => Order::STATUS_AVAILABLE,
            'cancellation_deadline' => null,
        ]);

        OrderStatusUpdated::dispatch($order->fresh()->load('items.product'), $previousStatus);

        return response()->json([
            'message' => 'Order confirmed and is now available for drivers',
            'order' => $order->fresh()->load(['items.product', 'retailer.user', 'confirmedBy']),
        ]);
    }
}
