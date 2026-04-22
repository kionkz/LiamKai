<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'movement_type')) {
                $table->string('movement_type')->nullable()->after('type');
            }

            if (!Schema::hasColumn('stock_movements', 'reason')) {
                $table->string('reason')->nullable()->after('movement_type');
            }

            if (!Schema::hasColumn('stock_movements', 'reference_id')) {
                $table->unsignedBigInteger('reference_id')->nullable()->after('reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'reference_id')) {
                $table->dropColumn('reference_id');
            }

            if (Schema::hasColumn('stock_movements', 'reason')) {
                $table->dropColumn('reason');
            }

            if (Schema::hasColumn('stock_movements', 'movement_type')) {
                $table->dropColumn('movement_type');
            }
        });
    }
};