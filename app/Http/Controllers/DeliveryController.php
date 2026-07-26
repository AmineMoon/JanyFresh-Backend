<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Retailer;
use App\Events\OrderAssigned;
use App\Events\OrderStatusUpdated;
use App\Events\DeliveryStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class DeliveryController extends Controller
{
    /**
     * List all deliveries grouped by retailer
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);

        $query = Delivery::with([
            'driver.user',
            'assignedBy',
            'order.retailer.user',
            'order.items.product.images',
            'order.items.product.primaryImage',
        ]);

        if (auth()->user()->role === 'retailer') {
            $retailerId = auth()->user()->retailer->id;
            $query->whereHas('order', function ($q) use ($retailerId) {
                $q->where('retailer_id', $retailerId);
            });
        }

        $deliveries = $query
            ->latest()
            ->paginate($perPage);

        $grouped = $deliveries->getCollection()
            ->groupBy(fn ($delivery) => $delivery->order->retailer_id)
            ->map(function ($deliveries) {

                $retailer = $deliveries->first()->order->retailer;
                $user = $retailer->user;

                $assignedDelivery = $deliveries->first(function ($delivery) {
                    return !is_null($delivery->driver_id);
                });

                return [
                    'retailer_id' => $retailer->id,
                    'name' => $user?->name,
                    'phone' => $user?->phone,
                    'shop_name' => $retailer->shop_name,
                    'address' => $retailer->address,
                    'city' => $retailer->city,

                    'orders_count' => $deliveries->count(),
                    'delivery_fee_sum' => $deliveries->sum(fn ($d) => $d->order->delivery_fee),
                    'subtotal_sum' => $deliveries->sum(fn ($d) => $d->order->subtotal),

                    'is_assigned' => $assignedDelivery !== null,

                    'driver' => $assignedDelivery ? [
                        'id' => $assignedDelivery->driver->id,
                        'name' => $assignedDelivery->driver->user?->name,
                        'phone' => $assignedDelivery->driver->user?->phone,
                        'email' => $assignedDelivery->driver->user?->email,
                    ] : null,

                    'orders' => $deliveries->map(function ($delivery) {

                        $order = $delivery->order;

                        return [
                            'delivery_id' => $delivery->id,
                            'order_id' => $order->id,
                            'status' => $order->status,
                            'subtotal' => $order->subtotal,
                            'delivery_fee' => $order->delivery_fee,
                            'total' => $order->total,
                            'created_at' => $order->created_at,

                            'assigned_by' => $delivery->assignedBy ? [
                                'id' => $delivery->assignedBy->id,
                                'name' => $delivery->assignedBy->name,
                            ] : null,

                            'products' => $order->items->map(function ($item) {
                                return [
                                    'id' => $item->id,
                                    'quantity' => $item->quantity,
                                    'price' => $item->price,

                                    'product' => [
                                        'id' => $item->product->id,
                                        'name' => $item->product->name,
                                        'description' => $item->product->description,
                                        'price' => $item->product->price,
                                        'primary_image' => $item->product->primaryImage,
                                        'images' => $item->product->images,
                                    ],
                                ];
                            })->values(),
                        ];
                    })->values(),
                ];
            })
            ->values();

        return response()->json([
            'data' => $grouped,
            'meta' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'per_page' => $deliveries->perPage(),
                'total' => $deliveries->total(),
            ],
        ]);
    }


    /**
     * Assign delivery (supports both single order and retailer batch)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assign_type' => 'required|in:single,retailer',
            'order_id' => 'required_if:assign_type,single|exists:orders,id',
            'retailer_id' => 'required_if:assign_type,retailer|exists:retailers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->assign_type === 'retailer') {
            return $this->assignByRetailer($request);
        }

        return $this->assignSingleOrder($request);
    }

    /**
     * Assign single order to driver
     */
    private function assignSingleOrder(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $order = Order::lockForUpdate()->findOrFail($request->order_id);

            if ($order->status !== Order::STATUS_AVAILABLE) {
                return response()->json([
                    'message' => 'Order is not available for assignment.'
                ], 422);
            }

            if ($order->delivery) {
                return response()->json([
                    'message' => 'Order already has a delivery assigned.'
                ], 422);
            }

            $user = Auth::user();
            $driver = $user->driver;

            if (!$driver) {
                return response()->json([
                    'message' => 'Driver profile not found. Please complete your driver profile first.'
                ], 404);
            }

            if ($driver->status !== 'available') {
                return response()->json([
                    'message' => 'Driver is not available for new deliveries.'
                ], 422);
            }

            $delivery = Delivery::create([
                'order_id' => $order->id,
                'driver_id' => $driver->id,
                'assigned_by' => auth()->id(),
                'status' => Delivery::STATUS_ASSIGNED,
            ]);

            $previousStatus = $order->status;
            $order->update([
                'status' => Order::STATUS_ASSIGNED,
                'confirmed_by' => $user->id,
            ]);

            OrderAssigned::dispatch($order->fresh(), $delivery, $driver);
            OrderStatusUpdated::dispatch($order->fresh(), $previousStatus);

            return response()->json([
                'message' => 'Delivery assigned successfully.',
                'data' => $this->formatDelivery($delivery->load('driver.user'))
            ], 201);
        });
    }

    /**
     * Assign all available orders from a retailer to the driver
     */
    public function assignByRetailer(Request $request)
    {
        return DB::transaction(function () use ($request) {

            $request->validate([
                'retailer_id' => 'required|integer|exists:retailers,id'
            ]);

            $user = Auth::user();
            $driver = $user->driver;

            if (!$driver) {
                return response()->json([
                    'message' => 'Driver profile not found. Please complete your driver profile first.'
                ], 404);
            }

            if ($driver->status !== 'available') {
                return response()->json([
                    'message' => 'Driver is not available for new deliveries.'
                ], 422);
            }

            $retailerProfile = Retailer::find($request->retailer_id);

            if (!$retailerProfile) {
                return response()->json([
                    'message' => 'Retailer not found.'
                ], 404);
            }

            $retailer = $retailerProfile->user;

            // Find all available orders for this retailer
            $orders = Order::where('retailer_id', $retailerProfile->id)
                ->where('status', Order::STATUS_AVAILABLE)
                ->whereDoesntHave('delivery')
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                return response()->json([
                    'message' => 'No available orders for this retailer.'
                ], 404);
            }

            $assignedCount = 0;
            $deliveries = [];

            foreach ($orders as $order) {
                $previousStatus = $order->status;

                $delivery = Delivery::create([
                    'order_id' => $order->id,
                    'driver_id' => $driver->id,
                    'assigned_by' => auth()->id(),
                    'status' => Delivery::STATUS_ASSIGNED,
                ]);

                $order->update([
                    'status' => Order::STATUS_ASSIGNED,
                    'confirmed_by' => $user->id,
                ]);

                OrderStatusUpdated::dispatch($order->fresh(), $previousStatus);

                $deliveries[] = $this->formatDelivery(
                    $delivery->load([
                        'order.retailer.user',
                        'driver.user'
                    ])
                );

                $assignedCount++;
            }

            // Dispatch OrderAssigned for each order
            foreach ($orders as $order) {
                $freshOrder = $order->fresh();
                $freshDelivery = $freshOrder->delivery;
                if ($freshDelivery) {
                    OrderAssigned::dispatch($freshOrder, $freshDelivery, $driver);
                }
            }

            return response()->json([
                'message' => "Successfully assigned {$assignedCount} orders from {$retailerProfile->shop_name}.",
                'data' => [
                    'retailer' => [
                        'id' => $retailerProfile->id,
                        'name' => $retailer?->name,
                        'shop_name' => $retailerProfile->shop_name,
                        'address' => $retailerProfile->address,
                        'phone' => $retailer?->phone,
                    ],
                    'driver' => [
                        'id' => $driver->id,
                        'name' => $user->name,
                    ],
                    'orders_assigned' => $assignedCount,
                    'deliveries' => $deliveries
                ]
            ], 201);
        });
    }

    /**
     * Get retailers with available orders for drivers
     */
    public function getAvailableRetailers(Request $request)
    {
        $retailers = Order::where('status', Order::STATUS_AVAILABLE)
            ->whereDoesntHave('delivery')
            ->with(['retailer.user'])
            ->get()
            ->groupBy('retailer_id')
            ->map(function ($orders) {
                $retailer = $orders->first()->retailer;
                $user = $retailer->user;
                return [
                    'retailer_id' => $retailer->id,
                    'name' => $user?->name,
                    'shop_name' => $retailer->shop_name ?? 'N/A',
                    'address' => $retailer->address ?? 'N/A',
                    'city' => $retailer->city ?? 'N/A',
                    'phone' => $user?->phone,
                    'available_orders_count' => $orders->count(),
                    'total_amount' => $orders->sum('total'),
                    'orders' => $orders->map(function ($order) {
                        return [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'total' => $order->total,
                            'delivery_fee' => $order->delivery_fee,
                            'created_at' => $order->created_at->toDateTimeString()
                        ];
                    })
                ];
            })
            ->values()
            ->sortByDesc('available_orders_count')
            ->values();

        return response()->json([
            'data' => $retailers
        ]);
    }

    /**
     * Get a specific delivery
     */
    public function show(Delivery $delivery)
    {
        return response()->json([
            'data' => $this->formatDelivery($delivery->load([
                'order.retailer.user',
                'driver.user',
                'assignedBy'
            ]))
        ]);
    }

    /**
     * Update delivery status (Driver only)
     */
    public function updateStatus(Request $request, Delivery $delivery)
    {
        $user = Auth::user();
        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'message' => 'Driver profile not found.'
            ], 404);
        }

        if ($delivery->driver_id !== $driver->id) {
            return response()->json([
                'message' => 'Unauthorized. This delivery does not belong to you.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:assigned,picked_up,in_transit,delivered,failed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        DB::transaction(function () use ($request, $delivery) {
            $status = $request->status;

            $updateData = [
                'status' => $status,
            ];

            $previousOrderStatus = $delivery->order->status;

            switch ($status) {
                case Delivery::STATUS_PICKED_UP:
                    $updateData['picked_up_at'] = now();
                    break;

                case Delivery::STATUS_IN_TRANSIT:
                    $updateData['in_transit_at'] = now();
                    $delivery->order->update([
                        'status' => Order::STATUS_OUT_FOR_DELIVERY
                    ]);
                    break;

                case Delivery::STATUS_DELIVERED:
                    $updateData['delivered_at'] = now();

                    if (!$delivery->driver_earnings || $delivery->driver_earnings == 0) {
                        $earningsPercentage = 1.0;
                        $updateData['driver_earnings'] = $delivery->order->delivery_fee * $earningsPercentage;
                    }

                    $delivery->order->update([
                        'status' => Order::STATUS_DELIVERED
                    ]);
                    break;

                case Delivery::STATUS_FAILED:
                    $delivery->order->update([
                        'status' => Order::STATUS_FAILED
                    ]);
                    break;

                case Delivery::STATUS_CANCELLED:
                    $delivery->order->update([
                        'status' => Order::STATUS_CANCELLED
                    ]);
                    break;
            }

            $delivery->update($updateData);
        });

        $freshDelivery = $delivery->fresh();
        $freshOrder = $freshDelivery->order;

        DeliveryStatusUpdated::dispatch($freshDelivery, $freshOrder);
        OrderStatusUpdated::dispatch($freshOrder, $freshOrder->getOriginal('status'));

        return response()->json([
            'message' => 'Status updated successfully.',
            'data' => $this->formatDelivery($freshDelivery->load('driver.user'))
        ]);
    }

    /**
     * Delete a delivery (Admin only)
     */
    public function destroy(Delivery $delivery)
    {
        $user = Auth::user()->isAdmin();
        if (!$user){
            return response()->json([
                'message' => 'Unauthorized. Only admins can delete deliveries.'
            ], 403);
        }

        $delivery->delete();

        return response()->json([
            'message' => 'Delivery removed successfully.'
        ]);
    }

    /**
     * Assign or reassign a driver to a delivery (Admin/Dispatcher only)
     */
    public function assignDriver(Request $request, Delivery $delivery)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:drivers,id',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        if (!$user->hasRole(['admin', 'dispatcher'])) {
            return response()->json([
                'message' => 'Unauthorized. Only admins and dispatchers can assign drivers.'
            ], 403);
        }

        if ($delivery->status === Delivery::STATUS_DELIVERED) {
            return response()->json([
                'message' => 'Cannot reassign a delivered order.'
            ], 422);
        }

        $delivery->update([
            'driver_id' => $request->driver_id,
            'assigned_by' => auth()->id(),
            'status' => Delivery::STATUS_ASSIGNED,
            'notes' => $request->notes
        ]);

        $freshOrder = $delivery->order;
        OrderStatusUpdated::dispatch($freshOrder, $freshOrder->getOriginal('status'));

        return response()->json([
            'message' => 'Driver assigned successfully.',
            'data' => $this->formatDelivery($delivery->fresh()->load('driver.user'))
        ]);
    }

    /**
     * Get current driver's deliveries
     */
    public function myDeliveries(Request $request)
    {
        $user = Auth::user();
        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'message' => 'Driver profile not found.'
            ], 404);
        }

        $perPage = $request->input('per_page', 20);
        $status = $request->input('status');

        $deliveries = Delivery::with([
            'order.retailer.user',
            'assignedBy'
        ])
        ->where('driver_id', $driver->id)
        ->when($status, function ($query, $status) {
            return $query->where('status', $status);
        })
        ->latest()
        ->paginate($perPage);

        return response()->json([
            'data' => $deliveries->map(function ($delivery) {
                return $this->formatDelivery($delivery);
            }),
            'meta' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'per_page' => $deliveries->perPage(),
                'total' => $deliveries->total(),
            ]
        ]);
    }

    /**
     * Format delivery response
     */
    private function formatDelivery(Delivery $delivery): array
    {
        $retailerModel = $delivery->order?->retailer;
        $user = $retailerModel?->user;

        return [
            'id' => $delivery->id,
            'status' => $delivery->status,
            'driver' => [
                'id' => $delivery->driver?->id,
                'name' => $delivery->driver?->user?->name,
                'phone' => $delivery->driver?->user?->phone,
            ],
            'order' => [
                'id' => $delivery->order?->id,
                'order_number' => $delivery->order?->order_number,
                'status' => $delivery->order?->status,
                'total' => $delivery->order?->total,
                'subtotal' => $delivery->order?->subtotal,
                'delivery_fee' => $delivery->order?->delivery_fee,
                'retailer' => [
                    'id' => $retailerModel?->id,
                    'name' => $user?->name,
                    'shop_name' => $retailerModel?->shop_name,
                    'address' => $retailerModel?->address,
                    'phone' => $user?->phone,
                ]
            ],
            'timestamps' => [
                'picked_up_at' => $delivery->picked_up_at,
                'in_transit_at' => $delivery->in_transit_at ?? null,
                'delivered_at' => $delivery->delivered_at,
                'created_at' => $delivery->created_at,
                'updated_at' => $delivery->updated_at,
            ]
        ];
    }
}
