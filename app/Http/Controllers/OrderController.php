<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Retailer;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


class OrderController extends Controller
{
    /**
     * List all orders
     */

    
public function index()
{
    $orders = Order::with([
        'retailer',
        'items.product.images',
        'items.product.primaryImage'
    ])
    ->where('retailer_id', auth()->id())
    ->latest()
    ->get();

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
            'retailer_id' => auth()->id(),
            'status' => 'pending',
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
            'retailer',
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

        $order->update([
            'status' => $request->status
        ]);

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



  // Confirm order
 

public function confirm(Order $order)
{
    if ($order->status !== 'pending') {
        return response()->json([
            'message' => 'Order cannot be confirmed'
        ], 422);
    }

    DB::transaction(function () use ($order) {

        $order->update([
            'status' => 'confirmed',
            'confirmed_by' => auth()->id(), // ✅ IMPORTANT FIX
        ]);

        $order->delivery()->create([
            'status' => 'assigned',
            'assigned_by'=> auth()->id(),
        ]);
    });

    return response()->json([
        'message' => 'Order confirmed and sent to deliveries',
        'order' => $order->fresh()
    ]);
}




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

  


}