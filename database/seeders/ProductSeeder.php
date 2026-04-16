<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Pricing;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now()->toDateString();

        $items = [
            // Tuna variants
            ['name' => 'Tuna - Steak (single)', 'category' => 'Tuna', 'unit_of_measure' => 'pc', 'retail' => 400, 'wholesale' => 380],
            ['name' => 'Tuna - Steak (pack)', 'category' => 'Tuna', 'unit_of_measure' => 'pack', 'retail' => 400, 'wholesale' => 380],
            ['name' => 'Tuna - Belly', 'category' => 'Tuna', 'unit_of_measure' => 'kg', 'retail' => 400, 'wholesale' => 380],
            ['name' => 'Tuna - Jaw', 'category' => 'Tuna', 'unit_of_measure' => 'kg', 'retail' => 280, 'wholesale' => 260],
            ['name' => 'Tuna - Tail', 'category' => 'Tuna', 'unit_of_measure' => 'kg', 'retail' => 260, 'wholesale' => 240],

            // Pompano
            ['name' => 'Pompano - Whole (500-600g)', 'category' => 'Pompano', 'unit_of_measure' => 'pc', 'retail' => 320, 'wholesale' => 300],

            // Seabass
            ['name' => 'Seabass - Whole (500-600g)', 'category' => 'Seabass', 'unit_of_measure' => 'pc', 'retail' => 400, 'wholesale' => 380],

            // Salmon
            ['name' => 'Salmon - Belly Strips (500g)', 'category' => 'Salmon', 'unit_of_measure' => 'pack', 'retail' => 220, 'wholesale' => 200],
            ['name' => 'Salmon - Fillet', 'category' => 'Salmon', 'unit_of_measure' => 'kg', 'retail' => 1750, 'wholesale' => 1700],
            ['name' => 'Salmon - Whole Fish', 'category' => 'Salmon', 'unit_of_measure' => 'pc', 'retail' => 900, 'wholesale' => 800],
            ['name' => 'Salmon - Steak', 'category' => 'Salmon', 'unit_of_measure' => 'kg', 'retail' => 1650, 'wholesale' => 1600],

            // Squid
            ['name' => 'Squid - Tube (pack)', 'category' => 'Squid', 'unit_of_measure' => 'pack', 'retail' => 320, 'wholesale' => 300],
            ['name' => 'Squid - Rings (pack)', 'category' => 'Squid', 'unit_of_measure' => 'pack', 'retail' => 350, 'wholesale' => 330],

            // Shell
            ['name' => 'Crab Meat', 'category' => 'Shell', 'unit_of_measure' => 'kg', 'retail' => 325, 'wholesale' => 325],
            ['name' => 'Scallops w shell (pack)', 'category' => 'Shell', 'unit_of_measure' => 'pack', 'retail' => 250, 'wholesale' => 250],
            ['name' => 'Scallops w/o shell (pack)', 'category' => 'Shell', 'unit_of_measure' => 'pack', 'retail' => 350, 'wholesale' => 350],
        ];

        foreach ($items as $item) {
            // make seeder idempotent: find or create by name
            $product = Product::firstOrCreate(
                ['name' => $item['name']],
                [
                    'category' => $item['category'],
                    'description' => null,
                    'unit_of_measure' => $item['unit_of_measure'],
                    'base_price' => $item['retail'],
                    'reorder_quantity' => 0,
                    'status' => 'active',
                ]
            );

            // ensure inventory exists
            if (!$product->inventory) {
                $product->inventory()->create([
                    'quantity' => 0,
                    'reorder_point' => 10,
                    'status' => 'available',
                ]);
            }

            // ensure pricing for today exists
            $existingPricing = Pricing::where('product_id', $product->id)
                ->where('effective_date', $now)
                ->first();

            if (!$existingPricing) {
                Pricing::create([
                    'product_id' => $product->id,
                    'retail_price' => $item['retail'],
                    'wholesale_price' => $item['wholesale'],
                    'effective_date' => $now,
                    'status' => 'active',
                ]);
            }
        }
    }
}
