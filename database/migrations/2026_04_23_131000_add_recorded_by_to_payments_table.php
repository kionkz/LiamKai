<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'recorded_by_user_id')) {
                $table->foreignId('recorded_by_user_id')
                    ->nullable()
                    ->after('purchase_order_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'recorded_by_user_id')) {
                $table->dropConstrainedForeignId('recorded_by_user_id');
            }
        });
    }
};
