<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'order_priority')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('order_priority', ['regular', 'urgent'])
                ->default('regular')
                ->after('fulfillment_type');
            $table->index(['order_priority', 'fulfillment_status', 'scheduled_for'], 'orders_priority_schedule_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'order_priority')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_priority_schedule_index');
            $table->dropColumn('order_priority');
        });
    }
};
