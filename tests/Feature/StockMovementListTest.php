<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.cipher' => 'AES-256-CBC',
            'app.key' => 'base64:J63H4fSX8R2qxGn8pY/+bexdFv+xo5jBqFaUG2RglN8=',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'inventory']), 'sanctum');
    }

    public function test_stock_movements_can_be_filtered_by_date_after_joining_products(): void
    {
        $product = Product::create([
            'name' => 'Stock Movement Test Product',
            'category' => 'Fish',
            'description' => 'For stock movement tests',
            'unit_of_measure' => 'kg',
            'base_price' => 120,
            'reorder_quantity' => 10,
            'status' => 'active',
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'quantity' => 25,
            'reorder_point' => 10,
            'status' => 'available',
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'type' => 'stock_in',
            'movement_type' => 'purchase',
            'quantity' => 5,
            'reason' => 'Test movement',
            'reference' => 'TEST-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/inventory/movements?' . http_build_query([
            'from_date' => now()->subDay()->toDateString(),
            'to_date' => now()->toDateString(),
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.product.name', 'Stock Movement Test Product');
    }
}
