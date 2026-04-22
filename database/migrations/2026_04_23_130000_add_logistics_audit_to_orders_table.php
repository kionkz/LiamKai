<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'actual_fulfillment_at')) {
                $table->dateTime('actual_fulfillment_at')->nullable()->after('scheduled_for');
            }

            if (! Schema::hasColumn('orders', 'fulfillment_action')) {
                $table->string('fulfillment_action')->nullable()->after('actual_fulfillment_at');
            }

            if (! Schema::hasColumn('orders', 'fulfillment_updated_by_user_id')) {
                $table->foreignId('fulfillment_updated_by_user_id')
                    ->nullable()
                    ->after('fulfillment_action')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'fulfillment_updated_by_user_id')) {
                $table->dropConstrainedForeignId('fulfillment_updated_by_user_id');
            }

            $columns = array_filter([
                Schema::hasColumn('orders', 'fulfillment_action') ? 'fulfillment_action' : null,
                Schema::hasColumn('orders', 'actual_fulfillment_at') ? 'actual_fulfillment_at' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
