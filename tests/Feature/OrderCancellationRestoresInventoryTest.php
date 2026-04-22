<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancellationRestoresInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.cipher' => 'AES-256-CBC',
            'app.key' => 'base64:J63H4fSX8R2qxGn8pY/+bexdFv+xo5jBqFaUG2RglN8=',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'sales']), 'sanctum');
    }

    public function test_cancelling_order_restores_inventory_and_records_stock_movement(): void
    {
        $product = Product::create([
            'name' => 'Cancellation Test Fish',
            'category' => 'Fish',
            'description' => 'For cancellation tests',
            'unit_of_measure' => 'kg',
            'base_price' => 150,
            'reorder_quantity' => 10,
            'status' => 'active',
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'quantity' => 15,
            'reorder_point' => 10,
            'status' => 'available',
        ]);

        $customer = Customer::create([
            'name' => 'Cancellation Test Customer',
            'type' => 'retail',
            'email' => 'cancel-test@example.com',
            'phone' => '+639123456789',
            'address' => 'Test Address',
            'credit_limit' => 0,
            'current_balance' => 0,
            'status' => 'active',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'fulfillment_type' => 'delivery',
            'order_type' => 'retail',
            'total_amount' => 750,
            'outstanding_balance' => 750,
            'payment_status' => 'pending',
            'fulfillment_status' => 'pending',
            'delivery_status' => 'pending',
            'delivery_address' => 'Test Address',
            'scheduled_for' => now()->addDay(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 150,
            'subtotal' => 750,
        ]);

        $response = $this->deleteJson("/api/orders/{$order->id}");

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Order cancelled and inventory restored');

        $this->assertSame('20.00', Inventory::where('product_id', $product->id)->first()->quantity);
        $this->assertSame('cancelled', $order->fresh()->fulfillment_status);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'stock_in',
            'movement_type' => 'order_cancel',
            'reference' => "ORDER-{$order->id}",
            'reference_id' => $order->id,
        ]);
    }

    public function test_order_with_logistics_progress_cannot_be_cancelled(): void
    {
        $order = $this->createCancelableOrder([
            'fulfillment_status' => 'in_progress',
            'delivery_status' => 'processing',
        ]);

        $this->deleteJson("/api/orders/{$order->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only orders with no logistics progress can be cancelled.');
    }

    public function test_order_with_reduced_outstanding_balance_cannot_be_cancelled(): void
    {
        $order = $this->createCancelableOrder([
            'outstanding_balance' => 50,
        ]);

        $this->deleteJson("/api/orders/{$order->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only orders with no payment activity can be cancelled.');
    }

    public function test_order_with_payment_record_cannot_be_cancelled(): void
    {
        $order = $this->createCancelableOrder();

        Payment::create([
            'order_id' => $order->id,
            'amount' => 25,
            'payment_method' => 'cash',
            'reference' => 'PAY-TEST',
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $this->deleteJson("/api/orders/{$order->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only orders with no payment activity can be cancelled.');
    }

    public function test_cancelled_orders_remain_visible_in_order_list(): void
    {
        $order = $this->createCancelableOrder([
            'fulfillment_status' => 'cancelled',
            'delivery_status' => 'cancelled',
        ]);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.fulfillment_status', 'cancelled');
    }

    public function test_order_status_reflects_payment_and_delivery_state(): void
    {
        $completeOrder = $this->createCancelableOrder([
            'total_amount' => 100,
            'outstanding_balance' => 0,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
            'delivery_status' => 'delivered',
        ]);

        $paidOnlyOrder = $this->createCancelableOrder([
            'total_amount' => 100,
            'outstanding_balance' => 0,
            'payment_status' => 'paid',
            'fulfillment_status' => 'pending',
            'delivery_status' => 'pending',
        ]);

        $deliveredOnlyOrder = $this->createCancelableOrder([
            'total_amount' => 100,
            'outstanding_balance' => 25,
            'payment_status' => 'pending',
            'fulfillment_status' => 'completed',
            'delivery_status' => 'delivered',
        ]);

        $cancelledOrder = $this->createCancelableOrder([
            'fulfillment_status' => 'cancelled',
            'delivery_status' => 'cancelled',
        ]);

        $this->getJson("/api/orders/{$completeOrder->id}")
            ->assertOk()
            ->assertJsonPath('data.order_status', 'complete');

        $this->getJson("/api/orders/{$paidOnlyOrder->id}")
            ->assertOk()
            ->assertJsonPath('data.order_status', 'pending');

        $this->getJson("/api/orders/{$deliveredOnlyOrder->id}")
            ->assertOk()
            ->assertJsonPath('data.order_status', 'pending');

        $this->getJson("/api/orders/{$cancelledOrder->id}")
            ->assertOk()
            ->assertJsonPath('data.order_status', 'cancelled');
    }

    private function createCancelableOrder(array $overrides = []): Order
    {
        $customer = Customer::create([
            'name' => fake()->name(),
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
            'fulfillment_type' => 'pickup',
            'order_type' => 'retail',
            'total_amount' => 100,
            'outstanding_balance' => 100,
            'payment_status' => 'pending',
            'fulfillment_status' => 'pending',
            'delivery_status' => 'pending',
            'scheduled_for' => now()->addDay(),
        ], $overrides));
    }
}
