<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderQuantityValidationTest extends TestCase
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

    public function test_retail_orders_reject_items_at_or_above_ten_kilos(): void
    {
        $customer = $this->createCustomer('retail');
        $product = $this->createProductWithInventory();

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'fulfillment_type' => 'delivery',
            'scheduled_for' => now()->addDay()->toDateTimeString(),
            'delivery_address' => 'Test Address',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);

        $errors = $response->json('errors');

        $this->assertSame(
            'Retail orders must be below 10kg per item.',
            $errors['items.0.quantity'][0] ?? null
        );
    }

    public function test_wholesale_orders_reject_items_below_ten_kilos(): void
    {
        $customer = $this->createCustomer('wholesale');
        $product = $this->createProductWithInventory();

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'fulfillment_type' => 'delivery',
            'scheduled_for' => now()->addDay()->toDateTimeString(),
            'delivery_address' => 'Test Address',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 9.99,
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);

        $errors = $response->json('errors');

        $this->assertSame(
            'Wholesale orders must be at least 10kg per item.',
            $errors['items.0.quantity'][0] ?? null
        );
    }

    public function test_order_cannot_be_switched_to_retail_when_an_item_is_ten_kilos_or_more(): void
    {
        $order = $this->createOrderWithItem('wholesale', 10);

        $response = $this->putJson("/api/orders/{$order->id}", [
            'order_type' => 'retail',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_type']);

        $this->assertSame(
            'Cannot set order type to retail when any item has 10kg or more.',
            $response->json('errors.order_type.0')
        );
    }

    public function test_order_cannot_be_switched_to_wholesale_when_an_item_is_below_ten_kilos(): void
    {
        $order = $this->createOrderWithItem('retail', 9.99);

        $response = $this->putJson("/api/orders/{$order->id}", [
            'order_type' => 'wholesale',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_type']);

        $this->assertSame(
            'Cannot set order type to wholesale when any item is below 10kg.',
            $response->json('errors.order_type.0')
        );
    }

    private function createCustomer(string $type = 'retail'): Customer
    {
        return Customer::create([
            'name' => 'Validation Test Customer',
            'type' => $type,
            'email' => 'validation@example.com',
            'phone' => '+639123456789',
            'address' => 'Test Address',
            'credit_limit' => 0,
            'current_balance' => 0,
            'status' => 'active',
        ]);
    }

    private function createProductWithInventory(): Product
    {
        $product = Product::create([
            'name' => 'Validation Test Product',
            'category' => 'Fish',
            'description' => 'For validation tests',
            'unit_of_measure' => 'kg',
            'base_price' => 125,
            'reorder_quantity' => 10,
            'status' => 'active',
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'quantity' => 50,
            'reorder_point' => 10,
            'status' => 'available',
        ]);

        return $product;
    }

    private function createOrderWithItem(string $orderType, float $quantity): Order
    {
        $customer = $this->createCustomer();
        $product = $this->createProductWithInventory();

        $order = Order::create([
            'customer_id' => $customer->id,
            'order_type' => $orderType,
            'total_amount' => 1000,
            'outstanding_balance' => 1000,
            'payment_status' => 'pending',
            'delivery_status' => 'pending',
            'delivery_address' => 'Test Address',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => 100,
            'subtotal' => $quantity * 100,
        ]);

        return $order;
    }
}
