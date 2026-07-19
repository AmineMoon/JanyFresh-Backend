<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Admin;
use App\Models\Driver;
use App\Models\Retailer;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $adminUser;
    private $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an admin user
        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        Admin::factory()->create([
            'user_id' => $this->adminUser->id,
        ]);

        $this->adminToken = $this->adminUser->createToken('test-token')->plainTextToken;
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/admin/dashboard/stats');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_admin_role()
    {
        // Create a non-admin user
        $retailerUser = User::factory()->create([
            'role' => 'retailer',
            'is_active' => true,
        ]);
        $token = $retailerUser->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/admin/dashboard/stats');

        $response->assertStatus(403);
    }

    /** @test */
    public function it_returns_zero_stats_when_no_data_exists()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/dashboard/stats');

        $response->assertStatus(200)
            ->assertJson([
                'total_orders' => 0,
                'pending_orders' => 0,
                'confirmed_orders' => 0,
                'preparing_orders' => 0,
                'out_for_delivery_orders' => 0,
                'delivered_orders' => 0,
                'cancelled_orders' => 0,
                'total_products' => 0,
                'total_retailers' => 0,
                'total_drivers' => 0,
                'total_admins' => 1, // The admin we created
                'total_revenue' => 0.0,
                'total_delivery_fees' => 0.0,
            ]);

        // Verify recent_orders is an empty array
        $response->assertJsonCount(0, 'recent_orders');
    }

    /** @test */
    public function it_returns_correct_stats_with_seeded_data()
    {
        // Create products
        Product::factory(10)->create();

        // Create retailers
        $retailer1 = Retailer::factory()->create();
        $retailer2 = Retailer::factory()->create();

        // Create drivers
        Driver::factory(3)->create();

        // Create orders with various statuses
        $order1 = Order::factory()->create([
            'retailer_id' => $retailer1->id,
            'status' => 'delivered',
            'total' => 100.00,
            'delivery_fee' => 5.00,
        ]);

        $order2 = Order::factory()->create([
            'retailer_id' => $retailer1->id,
            'status' => 'delivered',
            'total' => 200.00,
            'delivery_fee' => 10.00,
        ]);

        $order3 = Order::factory()->create([
            'retailer_id' => $retailer2->id,
            'status' => 'pending',
            'total' => 50.00,
            'delivery_fee' => 2.50,
        ]);

        $order4 = Order::factory()->create([
            'retailer_id' => $retailer1->id,
            'status' => 'confirmed',
            'total' => 75.00,
            'delivery_fee' => 3.75,
        ]);

        $order5 = Order::factory()->create([
            'retailer_id' => $retailer2->id,
            'status' => 'cancelled',
            'total' => 30.00,
            'delivery_fee' => 1.50,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/dashboard/stats');

        $response->assertStatus(200)
            ->assertJson([
                'total_orders' => 5,
                'pending_orders' => 1,
                'confirmed_orders' => 1,
                'preparing_orders' => 0,
                'out_for_delivery_orders' => 0,
                'delivered_orders' => 2,
                'cancelled_orders' => 1,
                'total_products' => 10,
                'total_retailers' => 2,
                'total_drivers' => 3,
                'total_admins' => 1,
                'total_revenue' => 300.00, // Only delivered orders
                'total_delivery_fees' => 15.00, // Only delivered orders
            ]);

        // Verify recent_orders contains 5 orders
        $response->assertJsonCount(5, 'recent_orders');
    }

    /** @test */
    public function it_returns_recent_orders_limited_to_five()
    {
        $retailer = Retailer::factory()->create();

        // Create 10 orders
        Order::factory(10)->create([
            'retailer_id' => $retailer->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/dashboard/stats');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'recent_orders');
    }

    /** @test */
    public function it_returns_total_retailers_even_without_orders()
    {
        // Create retailers but no orders
        Retailer::factory(5)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'total_retailers' => 5,
                'total_orders' => 0,
            ]);
    }
}