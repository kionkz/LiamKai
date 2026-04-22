<?php

use App\Models\Product;
use App\Support\ProductSku;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'sku')) {
            return;
        }

        Product::with('productCategory')
            ->where(function ($query) {
                $query->whereNull('sku')->orWhere('sku', '');
            })
            ->orderBy('id')
            ->chunkById(100, function ($products) {
                foreach ($products as $product) {
                    $product->forceFill(['sku' => ProductSku::forProduct($product)])->save();
                }
            });
    }

    public function down(): void
    {
        //
    }
};
