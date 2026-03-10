<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\Pricing;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Display a listing of all products
     */
    public function index(): JsonResponse
    {
        try {
            $products = Product::with('inventory', 'pricing', 'suppliers')
                ->paginate(15);
            
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
            // Prepare product data
            $productData = $request->validated();
            
            // Use retail_price as base_price (or default to 0)
            $productData['base_price'] = $productData['retail_price'] ?? 0;
            $productData['expiration_date'] = now()->addMonths(6)->toDateString();
            
            // Create the product
            $product = Product::create($productData);
            
            // Initialize inventory for this product
            // Stock starts at 0 and will be updated via purchase orders
            $product->inventory()->create([
                'product_id' => $product->id,
                'quantity' => 0,
                'reorder_point' => 5, // Default reorder point - can be updated via purchase orders
                'status' => 'available',
            ]);
            
            // Create pricing if provided
            $retail = $request->input('retail_price');
            $wholesale = $request->input('wholesale_price');
            if (!is_null($retail) || !is_null($wholesale)) {
                Pricing::create([
                    'product_id' => $product->id,
                    'retail_price' => $retail ?? 0,
                    'wholesale_price' => $wholesale ?? 0,
                    'effective_date' => now()->toDateString(),
                    'status' => 'active',
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product->load('inventory', 'pricing')
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
            $product = Product::with('inventory', 'pricing', 'suppliers', 'stockMovements')
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
            $product = Product::findOrFail($id);
            $product->update($request->validated());
            
                // If pricing values provided, create a new pricing entry effective now
                $retail = $request->input('retail_price');
                $wholesale = $request->input('wholesale_price');
                if (!is_null($retail) || !is_null($wholesale)) {
                    Pricing::create([
                        'product_id' => $product->id,
                        'retail_price' => $retail ?? 0,
                        'wholesale_price' => $wholesale ?? 0,
                        'effective_date' => now()->toDateString(),
                        'status' => 'active',
                    ]);
                }
            
            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product->load('inventory', 'pricing')
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
}
