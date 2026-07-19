<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderDelivered implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $order;
    public $delivery;

    public function __construct($order, $delivery)
    {
        $this->order = $order;
        $this->delivery = $delivery;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('order-status.' . $this->order->id),
            new PrivateChannel('retailer-orders.' . $this->order->retailer_id),
            new PrivateChannel('admin-orders'),
            new PrivateChannel('delivery-status.' . $this->delivery->id),
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
            'delivered_at' => $this->delivery->delivered_at?->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];
    }
}