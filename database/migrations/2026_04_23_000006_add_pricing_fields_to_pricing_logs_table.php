<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('pricing_logs', 'wholesale_price')) {
                $table->decimal('wholesale_price', 10, 2)->nullable()->after('product_id');
            }

            if (!Schema::hasColumn('pricing_logs', 'retail_price')) {
                $table->decimal('retail_price', 10, 2)->nullable()->after('wholesale_price');
            }

            if (!Schema::hasColumn('pricing_logs', 'quantity')) {
                $table->decimal('quantity', 10, 2)->nullable()->after('retail_price');
            }

            if (!Schema::hasColumn('pricing_logs', 'product_supplier_id')) {
                $table->unsignedBigInteger('product_supplier_id')->nullable()->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pricing_logs', function (Blueprint $table) {
            $columns = ['wholesale_price', 'retail_price', 'quantity', 'product_supplier_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('pricing_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
