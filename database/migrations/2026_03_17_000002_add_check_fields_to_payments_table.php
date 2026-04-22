<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'deposit_date')) {
                $table->date('deposit_date')->nullable()->after('payment_date');
            }

            if (! Schema::hasColumn('payments', 'check_from')) {
                $table->string('check_from')->nullable()->after('deposit_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = array_filter([
            Schema::hasColumn('payments', 'deposit_date') ? 'deposit_date' : null,
            Schema::hasColumn('payments', 'check_from') ? 'check_from' : null,
        ]);

        if ($columns === []) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn($columns);
        });
    }
};
