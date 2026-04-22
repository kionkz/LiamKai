<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'remaining_quantity')) {
                $table->decimal('remaining_quantity', 10, 2)->nullable()->after('quantity');
            }

            if (!Schema::hasColumn('stock_movements', 'source_stock_movement_id')) {
                $table->foreignId('source_stock_movement_id')
                    ->nullable()
                    ->after('reference_id')
                    ->constrained('stock_movements')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('stock_movements', 'performed_by_user_id')) {
                $table->foreignId('performed_by_user_id')
                    ->nullable()
                    ->after('source_stock_movement_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        DB::table('stock_movements')
            ->where('type', 'stock_in')
            ->whereNull('remaining_quantity')
            ->update(['remaining_quantity' => 0]);

        $currentQuantityExpression = Schema::hasColumn('inventory', 'quantity_on_hand')
            ? 'COALESCE(quantity_on_hand, quantity, 0)'
            : 'COALESCE(quantity, 0)';

        DB::table('inventory')
            ->select('id', 'product_id', DB::raw("{$currentQuantityExpression} as current_quantity"))
            ->orderBy('product_id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $remainingToAllocate = (float) $row->current_quantity;

                    $batches = DB::table('stock_movements')
                        ->where('product_id', $row->product_id)
                        ->where('type', 'stock_in')
                        ->where('movement_type', 'purchase_receipt')
                        ->orderByRaw('expiration_date IS NULL')
                        ->orderByDesc('expiration_date')
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->get(['id', 'quantity']);

                    foreach ($batches as $batch) {
                        $batchRemaining = min((float) $batch->quantity, max($remainingToAllocate, 0));

                        DB::table('stock_movements')
                            ->where('id', $batch->id)
                            ->update(['remaining_quantity' => $batchRemaining]);

                        $remainingToAllocate -= $batchRemaining;
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'performed_by_user_id')) {
                $table->dropConstrainedForeignId('performed_by_user_id');
            }

            if (Schema::hasColumn('stock_movements', 'source_stock_movement_id')) {
                $table->dropConstrainedForeignId('source_stock_movement_id');
            }

            if (Schema::hasColumn('stock_movements', 'remaining_quantity')) {
                $table->dropColumn('remaining_quantity');
            }
        });
    }
};
