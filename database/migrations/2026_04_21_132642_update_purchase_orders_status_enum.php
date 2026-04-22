<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Move any partially_received rows back to pending
        DB::table('purchase_orders')
            ->where('status', 'partially_received')
            ->update(['status' => 'pending']);

        DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status ENUM('pending','received','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status ENUM('pending','partially_received','received','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
