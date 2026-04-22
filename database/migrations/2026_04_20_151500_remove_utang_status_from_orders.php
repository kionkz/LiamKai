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
            MODIFY payment_status ENUM('paid', 'unpaid', 'partially_paid', 'utang')
            NOT NULL DEFAULT 'unpaid'
        ");

        DB::table('orders')
            ->where('payment_status', 'utang')
            ->update(['payment_status' => 'partially_paid']);

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
            MODIFY payment_status ENUM('paid', 'unpaid', 'partially_paid', 'utang')
            NOT NULL DEFAULT 'unpaid'
        ");
    }
};