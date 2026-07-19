<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderAssigned implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $order;
    public $delivery;
    public $driver;

    public function __construct($order, $delivery, $driver)
    {
        $this->order = $order;
        $this->delivery = $delivery;
        $this->driver = $driver;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('order-status.' . $this->order->id),
            new PrivateChannel('retailer-orders.' . $this->order->retailer_id),
            new PrivateChannel('admin-orders'),
            new PrivateChannel('driver-deliveries.' . $this->driver->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'retailer_id' => $this->order->retailer_id,
            'delivery_id' => $this->delivery->id,
            'driver' => [
                'id' => $this->driver->id,
                'name' => $this->driver->user?->name,
                'phone' => $this->driver->user?->phone,
            ],
            'updated_at' => now()->toIso8601String(),
        ];
    }
}