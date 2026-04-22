<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('old_retail_price', 10, 2)->nullable();
            $table->decimal('new_retail_price', 10, 2)->nullable();
            $table->decimal('old_discount_percent', 5, 2)->nullable();
            $table->decimal('new_discount_percent', 5, 2)->nullable();
            $table->decimal('old_discounted_price', 10, 2)->nullable();
            $table->decimal('new_discounted_price', 10, 2)->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_logs');
    }
};