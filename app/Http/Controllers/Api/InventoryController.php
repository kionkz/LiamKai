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
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = max(1, min((int) $request->input('per_page', 15), 100));
            $sortBy = (string) $request->input('sort_by', 'product_name');
            $sortDirection = strtolower((string) $request->input('sort_direction', 'asc')) === 'desc' ? 'desc' : 'asc';
            $productHasSku = Schema::hasColumn('products', 'sku');

            $inventory = Inventory::with('product.productCategory', 'product.pricing', 'product.pricingLogs', 'stockMovements')
                ->leftJoin('products', 'products.id', '=', 'inventory.product_id')
                ->select('inventory.*')
                ->when($sortBy === 'product_name', fn ($query) => $query->orderBy('products.name', $sortDirection))
                ->when($sortBy === 'sku' && $productHasSku, fn ($query) => $query->orderBy('products.sku', $sortDirection))
                ->when($sortBy === 'quantity', fn ($query) => $query->orderByRaw('COALESCE(inventory.quantity_on_hand, inventory.quantity, 0) ' . $sortDirection))
                ->when($sortBy === 'reorder_point', fn ($query) => $query->orderBy('inventory.reorder_point', $sortDirection))
                ->when($sortBy === 'retail_price', fn ($query) => $query->orderBy('products.base_price', $sortDirection))
                ->when(!in_array($sortBy, ['product_name', 'sku', 'quantity', 'reorder_point', 'retail_price'], true), fn ($query) => $query->orderBy('products.name', $sortDirection))
                ->orderBy('inventory.product_id', $sortDirection)
                ->paginate($perPage);
            
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
                ->with('product.productCategory', 'product.pricing', 'product.pricingLogs', 'stockMovements')
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
                'adjustment_quantity' => 'sometimes|numeric|min:0',
                'adjustment_reason' => 'required_with:adjustment_quantity|in:damage,theft',
                'adjustment_note' => 'sometimes|nullable|string|max:1000',
            ]);
            
            $inventory = Inventory::where('product_id', $id)->firstOrFail();
            
            // Update reorder point if provided
            if (isset($validated['reorder_point'])) {
                $inventory->update(['reorder_point' => $validated['reorder_point']]);
            }
            
            // Record manual adjustment if provided
            if (isset($validated['adjustment_quantity'])) {
                $adjustmentQty = (float) $validated['adjustment_quantity'];
                
                if ($adjustmentQty > 0) {
                    $currentQuantity = (float) ($inventory->quantity_on_hand ?? $inventory->quantity ?? 0);

                    if ($adjustmentQty > $currentQuantity) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Adjustment quantity cannot exceed current stock.'
                        ], 422);
                    }

                    $inventory->applyQuantityDelta(-$adjustmentQty);
                    $movementType = $validated['adjustment_reason'] === 'damage' ? 'adjustment' : 'stock_out';
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Adjustment quantity cannot be zero'
                    ], 422);
                }
                
                // Record stock movement
                $movementLabels = [
                    'damage' => 'Damage/Defect',
                    'theft' => 'Theft/Loss',
                ];
                $movementNotes = $movementLabels[$validated['adjustment_reason']] ?? $validated['adjustment_reason'];
                if (!empty($validated['adjustment_note'])) {
                    $movementNotes .= ' | ' . $validated['adjustment_note'];
                }

                $inventory->stockMovements()->create([
                    'type' => $movementType,
                    'quantity' => abs($adjustmentQty),
                    'movement_type' => $validated['adjustment_reason'] === 'damage' ? 'defect' : 'theft',
                    'reason' => $movementLabels[$validated['adjustment_reason']] ?? $validated['adjustment_reason'],
                    'reference' => 'MANUAL-ADJUSTMENT',
                    'notes' => $movementNotes,
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Inventory updated successfully',
                'data' => $inventory->fresh(['product.productCategory', 'product.pricing', 'product.pricingLogs', 'stockMovements'])
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
            $lowStock = Inventory::whereRaw('quantity <= reorder_point')
                ->with('product.productCategory')
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
                'type' => 'nullable|string|in:stock_in,stock_out,adjustment,defect',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:created_at,product,quantity,expiration_date,reference,type',
                'sort_direction' => 'nullable|string|in:asc,desc',
            ]);

            $perPage = $validated['per_page'] ?? 15;
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortDirection = $validated['sort_direction'] ?? 'desc';

            $query = StockMovement::with('product')
                ->leftJoin('products', 'products.id', '=', 'stock_movements.product_id')
                ->select('stock_movements.*');

            if (!empty($validated['type'])) {
                if ($validated['type'] === 'defect') {
                    $query->where('movement_type', 'defect');
                } else {
                    $query->where('type', $validated['type']);
                }
            }

            if (!empty($validated['from_date'])) {
                $query->whereDate('stock_movements.created_at', '>=', $validated['from_date']);
            }

            if (!empty($validated['to_date'])) {
                $query->whereDate('stock_movements.created_at', '<=', $validated['to_date']);
            }

            match ($sortBy) {
                'product' => $query->orderBy('products.name', $sortDirection),
                'quantity' => $query->orderBy('stock_movements.quantity', $sortDirection),
                'expiration_date' => $query->orderByRaw('stock_movements.expiration_date IS NULL')->orderBy('stock_movements.expiration_date', $sortDirection),
                'reference' => $query->orderBy('stock_movements.reference', $sortDirection),
                'type' => $query->orderBy('stock_movements.type', $sortDirection)->orderBy('stock_movements.movement_type', $sortDirection),
                default => $query->orderBy('stock_movements.created_at', $sortDirection),
            };

            $query->orderBy('stock_movements.id', $sortDirection === 'asc' ? 'asc' : 'desc');

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
