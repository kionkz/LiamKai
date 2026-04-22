<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'fulfillment_type')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->enum('fulfillment_type', ['delivery', 'pickup'])->default('delivery')->after('customer_id');
            });
        }

        if (! Schema::hasColumn('orders', 'fulfillment_status')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->enum('fulfillment_status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending')->after('payment_status');
            });
        }

        if (! Schema::hasColumn('orders', 'scheduled_for')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dateTime('scheduled_for')->nullable()->after('delivery_date');
            });
        }

        if (! $this->indexExists('orders', 'orders_logistics_schedule_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['scheduled_for', 'fulfillment_type', 'fulfillment_status'], 'orders_logistics_schedule_index');
            });
        }

        $scheduleExpression = DB::getDriverName() === 'sqlite'
            ? "(delivery_date || ' 09:00:00')"
            : "CONCAT(delivery_date, ' 09:00:00')";

        DB::statement("
            UPDATE orders
            SET
                fulfillment_type = CASE
                    WHEN delivery_address IS NULL OR delivery_address = '' THEN 'pickup'
                    ELSE 'delivery'
                END,
                fulfillment_status = CASE delivery_status
                    WHEN 'processing' THEN 'in_progress'
                    WHEN 'delivered' THEN 'completed'
                    WHEN 'cancelled' THEN 'cancelled'
                    ELSE 'pending'
                END,
                scheduled_for = CASE
                    WHEN delivery_date IS NOT NULL THEN {$scheduleExpression}
                    ELSE created_at
                END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if ($this->indexExists('orders', 'orders_logistics_schedule_index')) {
                $table->dropIndex('orders_logistics_schedule_index');
            }
        });

        $columnsToDrop = array_filter([
            Schema::hasColumn('orders', 'fulfillment_type') ? 'fulfillment_type' : null,
            Schema::hasColumn('orders', 'fulfillment_status') ? 'fulfillment_status' : null,
            Schema::hasColumn('orders', 'scheduled_for') ? 'scheduled_for' : null,
        ]);

        if ($columnsToDrop !== []) {
            Schema::table('orders', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list({$table})");
            foreach ($indexes as $index) {
                if ($index->name === $indexName) {
                    return true;
                }
            }
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};