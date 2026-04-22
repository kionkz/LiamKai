<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE orders
            MODIFY payment_status ENUM('pending', 'partial', 'paid', 'overdue', 'unpaid', 'partially_paid')
            NOT NULL DEFAULT 'pending'
        ");

        DB::statement("
            UPDATE orders
            SET payment_status = CASE payment_status
                WHEN 'pending' THEN 'unpaid'
                WHEN 'partial' THEN 'partially_paid'
                WHEN 'overdue' THEN 'partially_paid'
                ELSE payment_status
            END
        ");

        DB::statement("
            ALTER TABLE orders
            MODIFY payment_status ENUM('paid', 'unpaid', 'partially_paid')
            NOT NULL DEFAULT 'unpaid'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE orders
            MODIFY payment_status ENUM('pending', 'partial', 'paid', 'overdue', 'unpaid', 'partially_paid')
            NOT NULL DEFAULT 'unpaid'
        ");

        DB::statement("
            UPDATE orders
            SET payment_status = CASE payment_status
                WHEN 'unpaid' THEN 'pending'
                WHEN 'partially_paid' THEN 'partial'
                ELSE payment_status
            END
        ");

        DB::statement("
            ALTER TABLE orders
            MODIFY payment_status ENUM('pending', 'partial', 'paid', 'overdue')
            NOT NULL DEFAULT 'pending'
        ");
    }
};