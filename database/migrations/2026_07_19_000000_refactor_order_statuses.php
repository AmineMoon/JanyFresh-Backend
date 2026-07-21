<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing data before changing the enum
        // confirmed → assigned (driver was already assigned)
        DB::table('orders')->where('status', 'confirmed')->update(['status' => 'assigned']);

        // preparing → pending (never actually assigned to a driver)
        DB::table('orders')->where('status', 'preparing')->update(['status' => 'pending']);

        // Update the enum column to the new set of statuses
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'pending', 'available', 'assigned', 'out_for_delivery',
            'delivered', 'cancelled', 'failed'
        ) DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert data
        DB::table('orders')->where('status', 'available')->update(['status' => 'pending']);

        // Restore old enum
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'pending', 'confirmed', 'preparing', 'assigned',
            'out_for_delivery', 'delivered', 'cancelled'
        ) DEFAULT 'pending'");
    }
};
