<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Order;
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

    $deliveries = Delivery::with([
        'driver.user',
        'assignedBy',
        'order.retailer.retailer',
        'order.items.product.images',
        'order.items.product.primaryImage',
    ])
    ->latest()
    ->paginate($perPage);

    $grouped = $deliveries->getCollection()
        ->groupBy(fn ($delivery) => $delivery->order->retailer_id)
        ->map(function ($deliveries) {

            $user = $deliveries->first()->order->retailer;
            $retailer = $user->retailer;

            // Get the assigned delivery for this retailer (if any)
           $assignedDelivery = $deliveries->first(function ($delivery) {
    return !is_null($delivery->driver_id);
});

            return [
                'retailer_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'shop_name' => $retailer?->shop_name,
                'address' => $retailer?->address,
                'city' => $retailer?->city,

                'orders_count' => $deliveries->count(),
                'delivery_fee_sum' => $deliveries->sum(fn ($d) => $d->order->delivery_fee),
                'subtotal_sum' => $deliveries->sum(fn ($d) => $d->order->subtotal),

                // Whether this retailer has a driver assigned
                'is_assigned' => $assignedDelivery !== null,

                // Driver assigned to this retailer
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
            'retailer_id' => 'required_if:assign_type,retailer|exists:users,id|exists:retailers,user_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Route to appropriate assignment method
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

            // Check if order already has a delivery
            if ($order->delivery) {
                return response()->json([
                    'message' => 'Order already has a delivery assigned.'
                ], 422);
            }

            // Get the authenticated driver
            $user = Auth::user();
            $driver = $user->driver;

            if (!$driver) {
                return response()->json([
                    'message' => 'Driver profile not found. Please complete your driver profile first.'
                ], 403);
            }

            // Check if driver is available
            if ($driver->status !== 'available') {
                return response()->json([
                    'message' => 'Driver is not available for new deliveries.'
                ], 422);
            }

            $delivery = Delivery::create([
                'order_id' => $order->id,
                'driver_id' => $driver->id,
                'assigned_by' => auth()->id(),
                'status' => 'assigned',
            ]);

            $order->update(['status' => 'assigned']);

            return response()->json([
                'message' => 'Delivery assigned successfully.',
                'data' => $this->formatDelivery($delivery->load('driver.user'))
            ], 201);
        });
    }

    /**
     * Assign all pending orders from a retailer to the driver
     */
    public function assignByRetailer(Request $request)
    {
        return DB::transaction(function () use ($request) {
            // Get authenticated driver
            $user = Auth::user();
            $driver = $user->driver;

            if (!$driver) {
                return response()->json([
                    'message' => 'Driver profile not found. Please complete your driver profile first.'
                ], 403);
            }

            // Check if driver is available
            if ($driver->status !== 'available') {
                return response()->json([
                    'message' => 'Driver is not available for new deliveries.'
                ], 422);
            }

            // Get retailer details
            $retailer = \App\Models\User::with('retailer')
                ->find($request->retailer_id);

            if (!$retailer || !$retailer->retailer) {
                return response()->json([
                    'message' => 'Retailer not found.'
                ], 404);
            }

            // Find all pending orders for this retailer (without deliveries)
            $orders = Order::where('retailer_id', $request->retailer_id)
                ->where('status', 'pending')
                ->whereDoesntHave('delivery')
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                return response()->json([
                    'message' => 'No pending orders available for this retailer.'
                ], 404);
            }

            // Create deliveries for all orders
            $assignedCount = 0;
            $deliveries = [];

            foreach ($orders as $order) {
                $delivery = Delivery::create([
                    'order_id' => $order->id,
                    'driver_id' => $driver->id,
                    'assigned_by' => auth()->id(),
                    'status' => 'assigned',
                ]);

                $order->update(['status' => 'confirmed']);
                
                $deliveries[] = $this->formatDelivery(
                    $delivery->load(['order.retailer.retailer', 'driver.user'])
                );
                $assignedCount++;
            }

            return response()->json([
                'message' => "Successfully assigned {$assignedCount} orders from {$retailer->name}.",
                'data' => [
                    'retailer' => [
                        'id' => $retailer->id,
                        'name' => $retailer->name,
                        'shop_name' => $retailer->retailer->shop_name,
                        'address' => $retailer->retailer->address,
                        'phone' => $retailer->phone,
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
        $retailers = Order::where('status', 'pending')
            ->whereDoesntHave('delivery')
            ->with(['retailer.retailer'])
            ->get()
            ->groupBy('retailer_id')
            ->map(function ($orders) {
                $retailer = $orders->first()->retailer;
                return [
                    'retailer_id' => $retailer->id,
                    'name' => $retailer->name,
                    'shop_name' => $retailer->retailer->shop_name ?? 'N/A',
                    'address' => $retailer->retailer->address ?? 'N/A',
                    'city' => $retailer->retailer->city ?? 'N/A',
                    'phone' => $retailer->phone,
                    'pending_orders_count' => $orders->count(),
                    'total_amount' => $orders->sum('total_price'),
                    'orders' => $orders->map(function ($order) {
                        return [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'total_price' => $order->total_price,
                            'delivery_fee' => $order->delivery_fee,
                            'created_at' => $order->created_at->toDateTimeString()
                        ];
                    })
                ];
            })
            ->values()
            ->sortByDesc('pending_orders_count')
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
                'order.retailer.retailer',
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
        // Authenticated user
        $user = Auth::user();

        // Driver profile
        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'message' => 'Driver profile not found.'
            ], 403);
        }

        // Ensure the delivery belongs to the authenticated driver
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

            switch ($status) {
                case 'picked_up':
                    $updateData['picked_up_at'] = now();
                    break;

                case 'in_transit':
                    $updateData['in_transit_at'] = now();
                    $delivery->order->update([
                        'status' => 'out_for_delivery'
                    ]);
                    break;

                case 'delivered':
                    $updateData['delivered_at'] = now();
                    $delivery->order->update([
                        'status' => 'delivered'
                    ]);
                    break;

                case 'failed':
                case 'cancelled':
                    $delivery->order->update([
                        'status' => $status
                    ]);
                    break;
            }

            $delivery->update($updateData);
        });

        return response()->json([
            'message' => 'Status updated successfully.',
            'data' => $this->formatDelivery($delivery->fresh()->load('driver.user'))
        ]);
    }

    /**
     * Delete a delivery (Admin only)
     */
    public function destroy(Delivery $delivery)
    {
        // Check if user has admin role
        $user = Auth::user();
        if (!$user->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized. Only admins can delete deliveries.'
            ], 403);
        }

        // Only allow deletion if delivery is not completed
        if (in_array($delivery->status, ['delivered', 'in_transit', 'picked_up'])) {
            return response()->json([
                'message' => 'Cannot delete an active or completed delivery.'
            ], 422);
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

        // Check if user has permission to assign drivers (admin/dispatcher)
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'dispatcher'])) {
            return response()->json([
                'message' => 'Unauthorized. Only admins and dispatchers can assign drivers.'
            ], 403);
        }

        if ($delivery->status === 'delivered') {
            return response()->json([
                'message' => 'Cannot reassign a delivered order.'
            ], 422);
        }

        $delivery->update([
            'driver_id' => $request->driver_id,
            'assigned_by' => auth()->id(),
            'status' => 'assigned',
            'notes' => $request->notes
        ]);

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
            ], 403);
        }

        $perPage = $request->input('per_page', 20);
        $status = $request->input('status');

        $deliveries = Delivery::with([
            'order.retailer.retailer',
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
     * Format delivery response */
    
    private function formatDelivery(Delivery $delivery): array
    {
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
                'total_price' => $delivery->order?->total_price,
                'subtotal' => $delivery->order?->subtotal,
                'delivery_fee' => $delivery->order?->delivery_fee,
                'retailer' => [
                    'id' => $delivery->order?->retailer?->id,
                    'name' => $delivery->order?->retailer?->name,
                    'shop_name' => $delivery->order?->retailer?->retailer?->shop_name,
                    'address' => $delivery->order?->retailer?->retailer?->address,
                    'phone' => $delivery->order?->retailer?->phone,
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











 


  /*
namespace App\Http\Controllers;

use App\Http\Requests\AssignDeliveryRequest;
use App\Http\Requests\UpdateDeliveryStatusRequest;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
   public function index()
    {
        return Delivery::query()
            ->with([
                'order',
                'driver.user',
                'assignedBy'
            ])
            ->latest()
            ->paginate(15);
    }   


        
  
    
          public function index()
{
    $deliveries = Delivery::query()
        ->with([
            'order.retailer.retailer',
            'driver.user',
            'assignedBy'
        ])
        ->latest()
        ->paginate(50); // 👈 pagination here

    // group only current page results
    $grouped = $deliveries->getCollection()
        ->groupBy(fn ($delivery) => $delivery->order->retailer_id)
        ->map(function ($deliveries) {

            $user = $deliveries->first()->order->retailer;
            $retailer = $user->retailer;

            return [
                'retailer_id' => $user->id,

                'name' => $user->name,
                'phone' => $user->phone,

                'shop_name' => $retailer?->shop_name,
                'address' => $retailer?->address,
                'city' => $retailer?->city,

                'orders_count' => $deliveries->count(),

                'orders' => $deliveries->map(function ($delivery) {
                    return [
                        'delivery_id' => $delivery->id,
                        'order_id' => $delivery->order->id,
                        'order_number' => $delivery->order->order_number,
                        'status' => $delivery->status,
                        'total_price' => $delivery->order->total_price,
                    ];
                })->values()
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
        ]
    ]);
}
   

 
 

    public function store(AssignDeliveryRequest $request)
    {
        return DB::transaction(function () use ($request) {

            $order = Order::lockForUpdate()
                ->findOrFail($request->order_id);

            if ($order->delivery) {
                abort(422, 'Order already assigned.');
            }

            $delivery = Delivery::create([
                'order_id' => $order->id,
                'driver_id' => $request->driver_id,
                'assigned_by' => auth()->id(),
                'status' => 'assigned',
            ]);

            $order->update([
                'status' => 'assigned',
            ]);

            return response()->json([
                'message' => 'Delivery assigned.',
                'data' => $delivery->load('driver.user')
            ], 201);
        });
    }

    public function show(Delivery $delivery)
    {
        return $delivery->load([
            'order',
            'driver.user',
            'assignedBy'
        ]);
    }

    public function updateStatus(
        UpdateDeliveryStatusRequest $request,
        Delivery $delivery
    ) {
        DB::transaction(function () use ($request, $delivery) {

            switch ($request->status) {

                case 'picked_up':

                    $delivery->update([
                        'status' => 'picked_up',
                        'picked_up_at' => now(),
                    ]);

                    break;

                case 'in_transit':

                    $delivery->update([
                        'status' => 'in_transit',
                        'in_transit_at' => now(),
                    ]);

                    $delivery->order->update([
                        'status' => 'out_for_delivery'
                    ]);

                    break;

                case 'delivered':

                    $delivery->update([
                        'status' => 'delivered',
                        'delivered_at' => now(),
                    ]);

                    $delivery->order->update([
                        'status' => 'delivered'
                    ]);

                    break;

                default:

                    $delivery->update([
                        'status' => $request->status
                    ]);
            }
        });

        return response()->json([
            'message' => 'Status updated successfully.',
            'data' => $delivery->fresh()
        ]);
    }

    public function destroy(Delivery $delivery)
    {
        $delivery->delete();

        return response()->json([
            'message' => 'Delivery removed successfully.'
        ]);
    }
}*/ 