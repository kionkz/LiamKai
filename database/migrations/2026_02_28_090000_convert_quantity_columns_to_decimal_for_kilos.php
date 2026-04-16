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
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE order_items MODIFY quantity DECIMAL(10,2) NOT NULL');
            DB::statement('ALTER TABLE purchase_order_items MODIFY quantity DECIMAL(10,2) NOT NULL');
            DB::statement('ALTER TABLE purchase_order_items MODIFY received_quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00');
            DB::statement('ALTER TABLE purchase_orders MODIFY ordered_quantity DECIMAL(10,2) NOT NULL');
            DB::statement('ALTER TABLE purchase_orders MODIFY received_quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00');
            DB::statement('ALTER TABLE inventory MODIFY quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00');
            DB::statement('ALTER TABLE stock_movements MODIFY quantity DECIMAL(10,2) NOT NULL');
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE order_items ALTER COLUMN quantity TYPE NUMERIC(10,2) USING quantity::numeric');
            DB::statement('ALTER TABLE purchase_order_items ALTER COLUMN quantity TYPE NUMERIC(10,2) USING quantity::numeric');
            DB::statement('ALTER TABLE purchase_order_items ALTER COLUMN received_quantity TYPE NUMERIC(10,2) USING received_quantity::numeric');
            DB::statement('ALTER TABLE purchase_orders ALTER COLUMN ordered_quantity TYPE NUMERIC(10,2) USING ordered_quantity::numeric');
            DB::statement('ALTER TABLE purchase_orders ALTER COLUMN received_quantity TYPE NUMERIC(10,2) USING received_quantity::numeric');
            DB::statement('ALTER TABLE inventory ALTER COLUMN quantity TYPE NUMERIC(10,2) USING quantity::numeric');
            DB::statement('ALTER TABLE stock_movements ALTER COLUMN quantity TYPE NUMERIC(10,2) USING quantity::numeric');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE order_items MODIFY quantity INT NOT NULL');
            DB::statement('ALTER TABLE purchase_order_items MODIFY quantity INT NOT NULL');
            DB::statement('ALTER TABLE purchase_order_items MODIFY received_quantity INT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE purchase_orders MODIFY ordered_quantity INT NOT NULL');
            DB::statement('ALTER TABLE purchase_orders MODIFY received_quantity INT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE inventory MODIFY quantity INT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_movements MODIFY quantity INT NOT NULL');
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE order_items ALTER COLUMN quantity TYPE INTEGER USING quantity::integer');
            DB::statement('ALTER TABLE purchase_order_items ALTER COLUMN quantity TYPE INTEGER USING quantity::integer');
            DB::statement('ALTER TABLE purchase_order_items ALTER COLUMN received_quantity TYPE INTEGER USING received_quantity::integer');
            DB::statement('ALTER TABLE purchase_orders ALTER COLUMN ordered_quantity TYPE INTEGER USING ordered_quantity::integer');
            DB::statement('ALTER TABLE purchase_orders ALTER COLUMN received_quantity TYPE INTEGER USING received_quantity::integer');
            DB::statement('ALTER TABLE inventory ALTER COLUMN quantity TYPE INTEGER USING quantity::integer');
            DB::statement('ALTER TABLE stock_movements ALTER COLUMN quantity TYPE INTEGER USING quantity::integer');
        }
    }
};
