<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('employee_id')
                  ->nullable()
                  ->unique()
                  ->after('id')
                  ->constrained('employees')
                  ->nullOnDelete();

            $table->enum('role', ['admin', 'sales', 'delivery', 'inventory', 'purchasing'])
                  ->default('sales')
                  ->after('email');

            $table->enum('account_status', ['active', 'inactive'])
                  ->default('active')
                  ->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn(['employee_id', 'role', 'account_status']);
        });
    }
};
