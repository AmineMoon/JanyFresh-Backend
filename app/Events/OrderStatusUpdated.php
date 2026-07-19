<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $previousStatus;
    public $status;

    /**
     * Create a new event instance.
     */
    public function __construct($order, $previousStatus = null)
    {
        $this->order = $order;
        $this->previousStatus = $previousStatus;
        $this->status = $order->status;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('order-status.' . $this->order->id),
            new PrivateChannel('retailer-orders.' . $this->order->retailer_id),
            new PrivateChannel('admin-orders'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'previous_status' => $this->previousStatus,
            'retailer_id' => $this->order->retailer_id,
            'total' => (float) $this->order->total,
            'cancellation_deadline' => $this->order->cancellation_deadline?->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];
    }
}