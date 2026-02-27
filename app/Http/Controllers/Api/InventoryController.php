<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * Display all inventory
     */
    public function index(): JsonResponse
    {
        try {
            $inventory = Inventory::with('product', 'stockMovements')
                ->paginate(15);
            
            return response()->json([
                'success' => true,
                'message' => 'Inventory retrieved successfully',
                'data' => $inventory->items(),
                'pagination' => [
                    'total' => $inventory->total(),
                    'current_page' => $inventory->currentPage(),
                    'last_page' => $inventory->lastPage(),
                    'per_page' => $inventory->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving inventory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Inventory creation is handled automatically when product is created
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Inventory is created automatically with products. Use manual adjustment endpoint instead.'
        ], 405);
    }

    /**
     * Get inventory for a specific product
     */
    public function show(string $id): JsonResponse
    {
        try {
            $inventory = Inventory::where('product_id', $id)
                ->with('product', 'stockMovements')
                ->firstOrFail();
            
            return response()->json([
                'success' => true,
                'data' => $inventory
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory not found for this product'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving inventory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual adjustment to inventory (reorder point, etc)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reorder_point' => 'sometimes|required|numeric|min:0',
                'adjustment_quantity' => 'sometimes|numeric',
                'adjustment_reason' => 'required_with:adjustment_quantity|string|max:255',
            ]);
            
            $inventory = Inventory::where('product_id', $id)->firstOrFail();
            
            // Update reorder point if provided
            if (isset($validated['reorder_point'])) {
                $inventory->update(['reorder_point' => $validated['reorder_point']]);
            }
            
            // Record manual adjustment if provided
            if (isset($validated['adjustment_quantity'])) {
                $adjustmentQty = $validated['adjustment_quantity'];
                
                if ($adjustmentQty > 0) {
                    $inventory->increment('quantity_on_hand', $adjustmentQty);
                    $movementType = 'stock_in';
                } elseif ($adjustmentQty < 0) {
                    $inventory->decrement('quantity_on_hand', abs($adjustmentQty));
                    $movementType = 'stock_out';
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Adjustment quantity cannot be zero'
                    ], 422);
                }
                
                // Record stock movement
                $inventory->stockMovements()->create([
                    'movement_type' => $movementType,
                    'quantity' => abs($adjustmentQty),
                    'reason' => $validated['adjustment_reason'],
                ]);
                
                // Update last restock if stock in
                if ($movementType === 'stock_in') {
                    $inventory->update(['last_restock_date' => now()]);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Inventory updated successfully',
                'data' => $inventory->fresh(['product', 'stockMovements'])
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory not found for this product'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating inventory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Inventory is not deleted (use adjustments instead)
     */
    public function destroy(string $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Inventory cannot be deleted. Use adjustments instead.'
        ], 405);
    }

    /**
     * Get inventory status (low stock items)
     */
    public function showLowStock(): JsonResponse
    {
        try {
            $lowStock = Inventory::whereRaw('quantity_on_hand <= reorder_point')
                ->with('product')
                ->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Low stock items retrieved',
                'count' => count($lowStock),
                'data' => $lowStock
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving low stock items',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
