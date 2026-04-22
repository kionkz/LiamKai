<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory') || ! Schema::hasColumn('inventory', 'reorder_point')) {
            return;
        }

        $standardReorderPoint = config('operations.inventory.default_reorder_point', 5);

        DB::table('inventory')
            ->whereIn('reorder_point', [0, 10])
            ->update(['reorder_point' => $standardReorderPoint]);
    }

    public function down(): void
    {
        //
    }
};
