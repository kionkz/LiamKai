<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('inventory', 'last_restock_date')) {
            Schema::table('inventory', function (Blueprint $table) {
                $table->date('last_restock_date')->nullable()->after('status');
            });
        }

        if (!Schema::hasColumn('inventory', 'quantity_on_hand')) {
            Schema::table('inventory', function (Blueprint $table) {
                $table->decimal('quantity_on_hand', 10, 2)->nullable()->after('quantity');
            });
        }
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            if (Schema::hasColumn('inventory', 'last_restock_date')) {
                $table->dropColumn('last_restock_date');
            }
            if (Schema::hasColumn('inventory', 'quantity_on_hand')) {
                $table->dropColumn('quantity_on_hand');
            }
        });
    }
};
