<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE payments SET payment_method = 'cash' WHERE payment_method = 'credit'");
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash','check','bank_transfer','gcash') DEFAULT 'cash'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash','check','bank_transfer','credit','gcash') DEFAULT 'cash'");
    }
};
