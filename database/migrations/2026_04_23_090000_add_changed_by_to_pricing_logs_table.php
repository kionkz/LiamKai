<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('pricing_logs', 'changed_by_user_id')) {
                $table->foreignId('changed_by_user_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('pricing_logs', 'changed_by_name')) {
                $table->string('changed_by_name')->nullable()->after('changed_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pricing_logs', function (Blueprint $table) {
            if (Schema::hasColumn('pricing_logs', 'changed_by_user_id')) {
                $table->dropConstrainedForeignId('changed_by_user_id');
            }

            if (Schema::hasColumn('pricing_logs', 'changed_by_name')) {
                $table->dropColumn('changed_by_name');
            }
        });
    }
};
