<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCancelled implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $order;
    public $cancelledBy;

    public function __construct($order, $cancelledBy = null)
    {
        $this->order = $order;
        $this->cancelledBy = $cancelledBy;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('order-status.' . $this->order->id),
            new PrivateChannel('retailer-orders.' . $this->order->retailer_id),
            new PrivateChannel('admin-orders'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'previous_status' => $this->order->getOriginal('status'),
            'retailer_id' => $this->order->retailer_id,
            'cancelled_by' => $this->cancelledBy,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}