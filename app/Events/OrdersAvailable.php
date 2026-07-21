<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrdersAvailable implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $driverId;
    public $retailerId;
    public $orderCount;
    public $totalAmount;

    public function __construct(int $driverId, int $retailerId, int $orderCount, float $totalAmount)
    {
        $this->driverId = $driverId;
        $this->retailerId = $retailerId;
        $this->orderCount = $orderCount;
        $this->totalAmount = $totalAmount;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('driver-available-orders.' . $this->driverId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'retailer_id' => $this->retailerId,
            'order_count' => $this->orderCount,
            'total_amount' => $this->totalAmount,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
