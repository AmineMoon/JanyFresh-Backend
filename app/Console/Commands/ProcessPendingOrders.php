<?php

namespace App\Console\Commands;

use App\Events\OrderStatusUpdated;
use App\Events\OrdersAvailable;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessPendingOrders extends Command
{
    protected $signature = 'orders:process-pending';
    protected $description = 'Transition pending orders past their cancellation deadline to available status';

    public function handle(): int
    {
        $this->info('[' . now()->toDateTimeString() . '] Processing pending orders...');

        $transitionedOrders = DB::transaction(function () {
            $orders = Order::pending()
                ->whereNotNull('cancellation_deadline')
                ->where('cancellation_deadline', '<=', now())
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                return collect();
            }

            $orderIds = $orders->pluck('id');
            Order::whereIn('id', $orderIds)->update(['status' => Order::STATUS_AVAILABLE]);

            return $orders->each(function ($order) {
                $order->status = Order::STATUS_AVAILABLE;
                OrderStatusUpdated::dispatch($order, Order::STATUS_PENDING);
            });
        });

        if ($transitionedOrders->isNotEmpty()) {
            $this->info("Transitioned {$transitionedOrders->count()} orders from pending to available.");

            // Notify all available drivers about new available orders
            $availableDrivers = Driver::where('status', 'available')->get();
            $retailerGroups = $transitionedOrders->groupBy('retailer_id');

            foreach ($availableDrivers as $driver) {
                foreach ($retailerGroups as $retailerId => $orders) {
                    OrdersAvailable::dispatch(
                        $driver->id,
                        $retailerId,
                        $orders->count(),
                        (float) $orders->sum('total')
                    );
                }
            }
        } else {
            $this->info('No pending orders to process.');
        }

        return Command::SUCCESS;
    }
}
