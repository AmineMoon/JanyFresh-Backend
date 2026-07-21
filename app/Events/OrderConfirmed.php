<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated No longer dispatched. Use OrderStatusUpdated instead.
 * Kept for backwards compatibility with any remaining listeners.
 */
class OrderConfirmed implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $order;
    public $delivery;

    public function __construct($order, $delivery = null)
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
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'retailer_id' => $this->order->retailer_id,
            'confirmed_by' => $this->order->confirmed_by,
            'delivery' => $this->delivery ? [
                'id' => $this->delivery->id,
                'status' => $this->delivery->status,
                'driver_id' => $this->delivery->driver_id,
            ] : null,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}