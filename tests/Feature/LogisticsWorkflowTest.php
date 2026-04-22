<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.cipher' => 'AES-256-CBC',
            'app.key' => 'base64:J63H4fSX8R2qxGn8pY/+bexdFv+xo5jBqFaUG2RglN8=',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'delivery']), 'sanctum');
    }

    public function test_logistics_counts_are_not_limited_by_status_filter(): void
    {
        $this->createOrder(['fulfillment_status' => 'pending']);
        $this->createOrder(['fulfillment_status' => 'in_progress']);
        $this->createOrder(['fulfillment_status' => 'completed']);

        $response = $this->getJson('/api/orders/logistics?include_all=1&status=pending');

        $response
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('meta.counts.total', 3)
            ->assertJsonPath('meta.counts.pending', 1)
            ->assertJsonPath('meta.counts.in_progress', 1)
            ->assertJsonPath('meta.counts.completed', 1);
    }

    public function test_pending_order_cannot_jump_directly_to_completed(): void
    {
        $order = $this->createOrder(['fulfillment_status' => 'pending']);

        $response = $this->patchJson("/api/orders/{$order->id}/fulfillment-status", [
            'status' => 'completed',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Move the order to en-route before marking it completed.');

        $this->assertSame('pending', $order->fresh()->fulfillment_status);
    }

    public function test_completed_order_cannot_be_moved_back(): void
    {
        $order = $this->createOrder([
            'fulfillment_status' => 'completed',
            'delivery_status' => 'delivered',
        ]);

        $response = $this->patchJson("/api/orders/{$order->id}/fulfillment-status", [
            'status' => 'in_progress',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Completed logistics orders cannot be moved back to an earlier status.');

        $this->assertSame('completed', $order->fresh()->fulfillment_status);
    }

    public function test_order_can_progress_from_pending_to_in_progress_to_completed(): void
    {
        $order = $this->createOrder(['fulfillment_status' => 'pending']);

        $this->patchJson("/api/orders/{$order->id}/fulfillment-status", [
            'status' => 'in_progress',
        ])->assertOk()
            ->assertJsonPath('data.delivery_status', 'processing');

        $this->patchJson("/api/orders/{$order->id}/fulfillment-status", [
            'status' => 'completed',
        ])->assertOk()
            ->assertJsonPath('data.delivery_status', 'delivered');

        $order->refresh();

        $this->assertSame('completed', $order->fulfillment_status);
        $this->assertSame('delivered', $order->delivery_status);
    }

    private function createOrder(array $overrides = []): Order
    {
        $customer = Customer::create([
            'name' => 'Logistics Test Customer',
            'type' => 'retail',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+639123456789',
            'address' => 'Test Address',
            'credit_limit' => 0,
            'current_balance' => 0,
            'status' => 'active',
        ]);

        return Order::create(array_merge([
            'customer_id' => $customer->id,
            'fulfillment_type' => 'delivery',
            'order_type' => 'retail',
            'total_amount' => 1000,
            'outstanding_balance' => 1000,
            'payment_status' => 'pending',
            'fulfillment_status' => 'pending',
            'delivery_status' => 'pending',
            'delivery_address' => 'Test Address',
            'scheduled_for' => now()->addHour(),
        ], $overrides));
    }
}
