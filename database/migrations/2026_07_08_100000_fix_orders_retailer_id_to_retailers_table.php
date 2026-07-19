<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Convert orders.retailer_id from users.id to retailers.id
     */
    public function up(): void
    {
        // 1. Drop the existing foreign key (it points to users table)
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['retailer_id']);
        });

        // 2. Update existing orders: convert user_id values to retailer_id values
        //    Join orders with retailers where orders.retailer_id = retailers.user_id
        //    Then set orders.retailer_id = retailers.id
        DB::statement('
            UPDATE orders
            INNER JOIN retailers ON retailers.user_id = orders.retailer_id
            SET orders.retailer_id = retailers.id
        ');

        // 3. Re-add the foreign key now pointing to retailers.id instead of users.id
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('retailer_id')
                ->references('id')
                ->on('retailers')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop the new foreign key (points to retailers)
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['retailer_id']);
        });

        // 2. Convert back: retailer_id values back to user_id values
        DB::statement('
            UPDATE orders
            INNER JOIN retailers ON retailers.id = orders.retailer_id
            SET orders.retailer_id = retailers.user_id
        ');

        // 3. Restore original foreign key pointing to users
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('retailer_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};