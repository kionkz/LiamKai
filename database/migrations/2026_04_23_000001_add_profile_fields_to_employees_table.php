<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('fname')->nullable()->after('name');
            $table->string('lname')->nullable()->after('fname');
            $table->date('date_hired')->nullable()->after('address');
            $table->boolean('can_edit_transactions')->default(false)->after('date_hired');
            $table->boolean('view_proof_of_payments')->default(false)->after('can_edit_transactions');
        });

        // Populate fname from existing name (split on first space)
        DB::table('employees')->orderBy('id')->each(function ($employee): void {
            $parts = explode(' ', trim((string) $employee->name), 2);
            DB::table('employees')->where('id', $employee->id)->update([
                'fname' => $parts[0] ?? $employee->name,
                'lname' => $parts[1] ?? null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['fname', 'lname', 'date_hired', 'can_edit_transactions', 'view_proof_of_payments']);
        });
    }
};
