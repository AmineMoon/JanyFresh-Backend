<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $delivery;
    public $order;

    public function __construct($delivery, $order)
    {
        $this->delivery = $delivery;
        $this->order = $order;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('delivery-status.' . $this->delivery->id),
            new PrivateChannel('order-status.' . $this->order->id),
            new PrivateChannel('admin-orders'),
        ];

        if ($this->delivery->driver_id) {
            $channels[] = new PrivateChannel('driver-deliveries.' . $this->delivery->driver_id);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'delivery_id' => $this->delivery->id,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'order_status' => $this->order->status,
            'delivery_status' => $this->delivery->status,
            'driver_id' => $this->delivery->driver_id,
            'retailer_id' => $this->order->retailer_id,
            'timestamps' => [
                'picked_up_at' => $this->delivery->picked_up_at?->toIso8601String(),
                'in_transit_at' => $this->delivery->in_transit_at?->toIso8601String(),
                'delivered_at' => $this->delivery->delivered_at?->toIso8601String(),
            ],
            'updated_at' => now()->toIso8601String(),
        ];
    }
}