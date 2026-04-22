<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('category_id')->nullable()->after('category')->constrained('categories')->nullOnDelete();
                $table->index(['category_id', 'status'], 'products_category_id_status_index');
            });
        }

        $existingCategories = DB::table('products')
            ->select('category')
            ->whereNotNull('category')
            ->get()
            ->pluck('category')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        foreach ($existingCategories as $categoryName) {
            DB::table('categories')->updateOrInsert(
                ['name' => $categoryName],
                ['description' => null, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $uncategorized = DB::table('categories')->where('name', 'Uncategorized')->first();
        if (!$uncategorized) {
            $uncategorizedId = DB::table('categories')->insertGetId([
                'name' => 'Uncategorized',
                'description' => 'Default category for legacy or uncategorized products.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $uncategorizedId = $uncategorized->id;
        }

        DB::table('products')->where(function ($query) {
            $query->whereNull('category')->orWhere('category', '');
        })->update(['category' => 'Uncategorized']);

        $categoryMap = DB::table('categories')
            ->pluck('id', 'name');

        DB::table('products')
            ->select('id', 'category', 'category_id')
            ->whereNull('category_id')
            ->orderBy('id')
            ->get()
            ->each(function ($product) use ($categoryMap, $uncategorizedId) {
                $categoryName = trim((string) $product->category);
                $resolvedCategoryId = $categoryMap[$categoryName] ?? $uncategorizedId;

                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['category_id' => $resolvedCategoryId]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropIndex('products_category_id_status_index');
                $table->dropColumn('category_id');
            });
        }

        Schema::dropIfExists('categories');
    }
};