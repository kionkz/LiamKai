<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing', function (Blueprint $table) {
            if (!Schema::hasColumn('pricing', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('retail_price');
            }

            if (!Schema::hasColumn('pricing', 'discounted_price')) {
                $table->decimal('discounted_price', 10, 2)->default(0)->after('discount_percent');
            }
        });

        if (Schema::hasColumn('pricing', 'wholesale_price')) {
            $discountExpression = DB::getDriverName() === 'sqlite'
                ? 'ROUND(MAX(0, MIN(100, (1 - (wholesale_price / retail_price)) * 100)), 2)'
                : 'ROUND(GREATEST(0, LEAST(100, (1 - (wholesale_price / retail_price)) * 100)), 2)';

            DB::statement(
                "UPDATE pricing
                 SET discount_percent = CASE
                        WHEN retail_price > 0 AND wholesale_price IS NOT NULL AND wholesale_price >= 0
                            THEN {$discountExpression}
                        ELSE 0
                    END,
                    discounted_price = CASE
                        WHEN wholesale_price IS NOT NULL AND wholesale_price >= 0 THEN wholesale_price
                        ELSE retail_price
                    END"
            );

            Schema::table('pricing', function (Blueprint $table) {
                $table->dropColumn('wholesale_price');
            });
        } else {
            DB::statement('UPDATE pricing SET discounted_price = ROUND(retail_price * (1 - (discount_percent / 100)), 2)');
        }
    }

    public function down(): void
    {
        Schema::table('pricing', function (Blueprint $table) {
            if (!Schema::hasColumn('pricing', 'wholesale_price')) {
                $table->decimal('wholesale_price', 10, 2)->default(0)->after('retail_price');
            }
        });

        DB::statement('UPDATE pricing SET wholesale_price = discounted_price');

        Schema::table('pricing', function (Blueprint $table) {
            if (Schema::hasColumn('pricing', 'discounted_price')) {
                $table->dropColumn('discounted_price');
            }
            if (Schema::hasColumn('pricing', 'discount_percent')) {
                $table->dropColumn('discount_percent');
            }
        });
    }
};