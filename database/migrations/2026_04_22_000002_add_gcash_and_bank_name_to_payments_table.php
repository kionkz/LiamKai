<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the enum to include gcash
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash','check','bank_transfer','credit','gcash') DEFAULT 'cash'");

        Schema::table('payments', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('check_from');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('bank_name');
        });

        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash','check','bank_transfer','credit') DEFAULT 'cash'");
    }
};
