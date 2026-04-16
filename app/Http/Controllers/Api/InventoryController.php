<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
                'adjustment_note' => 'sometimes|nullable|string|max:1000',
            ]);
            
            $inventory = Inventory::where('product_id', $id)->firstOrFail();
            
            // Update reorder point if provided
            if (isset($validated['reorder_point'])) {
                $inventory->update(['reorder_point' => $validated['reorder_point']]);
            }
            
            // Record manual adjustment if provided
            if (isset($validated['adjustment_quantity'])) {
                $adjustmentQty = $validated['adjustment_quantity'];
                $quantityColumn = Schema::hasColumn('inventory', 'quantity_on_hand') ? 'quantity_on_hand' : 'quantity';
                
                if ($adjustmentQty > 0) {
                    $inventory->increment($quantityColumn, $adjustmentQty);
                    $movementType = 'stock_in';
                } elseif ($adjustmentQty < 0) {
                    $inventory->decrement($quantityColumn, abs($adjustmentQty));
                    $movementType = 'stock_out';
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Adjustment quantity cannot be zero'
                    ], 422);
                }
                
                // Record stock movement
                $movementNotes = $validated['adjustment_reason'];
                if (!empty($validated['adjustment_note'])) {
                    $movementNotes .= ' | ' . $validated['adjustment_note'];
                }

                $inventory->stockMovements()->create([
                    'type' => $movementType,
                    'quantity' => abs($adjustmentQty),
                    'notes' => $movementNotes,
                ]);
                
                // Keep this conditional for databases that include last_restock_date.
                if ($movementType === 'stock_in' && Schema::hasColumn('inventory', 'last_restock_date')) {
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

    /**
     * List stock movements with optional filters and pagination.
     */
    public function movements(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'nullable|string|in:stock_in,stock_out,adjustment',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $perPage = $validated['per_page'] ?? 15;

            $query = StockMovement::with('product')->orderByDesc('created_at');

            if (!empty($validated['type'])) {
                $query->where('type', $validated['type']);
            }

            if (!empty($validated['from_date'])) {
                $query->whereDate('created_at', '>=', $validated['from_date']);
            }

            if (!empty($validated['to_date'])) {
                $query->whereDate('created_at', '<=', $validated['to_date']);
            }

            $movements = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Stock movements retrieved successfully',
                'data' => $movements->items(),
                'pagination' => [
                    'total' => $movements->total(),
                    'current_page' => $movements->currentPage(),
                    'last_page' => $movements->lastPage(),
                    'per_page' => $movements->perPage(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving stock movements',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
