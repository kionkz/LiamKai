<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'inventory_id')) {
                $table->foreignId('inventory_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('inventory')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('stock_movements', 'movement_date')) {
                $table->date('movement_date')->nullable()->after('product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'inventory_id')) {
                $table->dropForeign(['inventory_id']);
                $table->dropColumn('inventory_id');
            }

            if (Schema::hasColumn('stock_movements', 'movement_date')) {
                $table->dropColumn('movement_date');
            }
        });
    }
};
