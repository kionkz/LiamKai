<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\Product;
use App\Models\Pricing;
use App\Models\PricingLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

use App\Support\PricingMath;

class ProductController extends Controller
{
    /**
     * Display a listing of all products
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->integer('per_page', 15);
            $perPage = max(1, min($perPage, 100));
            $sortBy = (string) $request->input('sort_by', 'name');
            $sortDirection = strtolower((string) $request->input('sort_direction', 'asc')) === 'desc' ? 'desc' : 'asc';
            $productHasSku = Schema::hasColumn('products', 'sku');

            $products = Product::with('inventory', 'pricing', 'suppliers', 'productCategory')
                ->when($sortBy === 'sku' && $productHasSku, fn ($query) => $query->orderBy('sku', $sortDirection))
                ->when($sortBy === 'category', fn ($query) => $query->orderBy('category', $sortDirection))
                ->when($sortBy === 'unit_of_measure', fn ($query) => $query->orderBy('unit_of_measure', $sortDirection))
                ->when($sortBy === 'retail_price', fn ($query) => $query->orderBy('base_price', $sortDirection))
                ->when(!in_array($sortBy, ['sku', 'category', 'unit_of_measure', 'retail_price'], true), fn ($query) => $query->orderBy('name', $sortDirection))
                ->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => $products->items(),
                'pagination' => [
                    'total' => $products->total(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created product
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $product = DB::transaction(function () use ($request) {
                $productData = $request->validated();
                $category = Category::findOrFail($productData['category_id']);
                $productData['category'] = $category->name;
                $productData['base_price'] = $productData['retail_price'];
                $productData['expiration_date'] = now()
                    ->addMonths(config('operations.inventory.default_product_expiration_months', 6))
                    ->toDateString();

                $product = Product::create($productData);

                $product->inventory()->create([
                    'product_id' => $product->id,
                    'quantity' => 0,
                    'reorder_point' => config('operations.inventory.default_reorder_point', 5),
                    'status' => 'available',
                ]);

                $this->syncPricing(
                    $product,
                    (float) $request->input('retail_price'),
                    $request->input('discount_percent')
                );

                return $product;
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product->load('inventory', 'pricing', 'pricingLogs', 'productCategory')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a specific product
     */
    public function show(string $id): JsonResponse
    {
        try {
            $product = Product::with('inventory', 'pricing', 'pricingLogs', 'suppliers', 'stockMovements', 'productCategory')
                ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $product
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a specific product
     */
    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        try {
            $product = DB::transaction(function () use ($request, $id) {
                $product = Product::findOrFail($id);
                $validated = $request->validated();

                if (array_key_exists('category_id', $validated)) {
                    $validated['category'] = Category::findOrFail($validated['category_id'])->name;
                }

                if (array_key_exists('retail_price', $validated)) {
                    $validated['base_price'] = $validated['retail_price'];
                }

                $product->update($validated);

                if (array_key_exists('retail_price', $validated) || array_key_exists('discount_percent', $validated)) {
                    $this->syncPricing(
                        $product,
                        $validated['retail_price'] ?? null,
                        $validated['discount_percent'] ?? null
                    );
                }

                return $product;
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product->load('inventory', 'pricing', 'pricingLogs', 'productCategory')
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a product
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function syncPricing(Product $product, float|int|string|null $retailPrice = null, float|int|string|null $discountPercent = null): void
    {
        /** @var Pricing|null $activePricing */
        $activePricing = $product->pricing()->first();
        $resolvedRetailPrice = (float) ($retailPrice ?? $activePricing?->retail_price ?? $product->base_price ?? 0);
        $resolvedDiscountPercent = PricingMath::normalizeDiscountPercent($discountPercent ?? $activePricing?->discount_percent ?? 0);
        $resolvedDiscountedPrice = PricingMath::calculateDiscountedPrice($resolvedRetailPrice, $resolvedDiscountPercent);

        if (
            $activePricing
            && (float) $activePricing->retail_price === $resolvedRetailPrice
            && (float) ($activePricing->discount_percent ?? 0) === $resolvedDiscountPercent
            && (float) ($activePricing->discounted_price ?? 0) === $resolvedDiscountedPrice
        ) {
            return;
        }

        if ($activePricing) {
            $activePricing->update([
                'status' => 'inactive',
                'end_date' => now()->toDateString(),
            ]);
        }

        Pricing::create([
            'product_id' => $product->id,
            'retail_price' => $resolvedRetailPrice,
            'discount_percent' => $resolvedDiscountPercent,
            'discounted_price' => $resolvedDiscountedPrice,
            'effective_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        PricingLog::create([
            'product_id' => $product->id,
            'old_retail_price' => $activePricing?->retail_price,
            'new_retail_price' => $resolvedRetailPrice,
            'old_discount_percent' => $activePricing?->discount_percent,
            'new_discount_percent' => $resolvedDiscountPercent,
            'old_discounted_price' => $activePricing?->discounted_price,
            'new_discounted_price' => $resolvedDiscountedPrice,
            'changed_at' => now(),
        ]);
    }
}
