<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE order_items MODIFY quantity DECIMAL(10, 2) NOT NULL');
        DB::statement('ALTER TABLE purchase_order_items MODIFY quantity DECIMAL(10, 2) NOT NULL');
        DB::statement('ALTER TABLE purchase_order_items MODIFY received_quantity DECIMAL(10, 2) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE order_items MODIFY quantity INT NOT NULL');
        DB::statement('ALTER TABLE purchase_order_items MODIFY quantity INT NOT NULL');
        DB::statement('ALTER TABLE purchase_order_items MODIFY received_quantity INT NOT NULL DEFAULT 0');
    }
};