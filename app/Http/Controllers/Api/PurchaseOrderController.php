<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    /**
     * Display all purchase orders
     */
    public function index(): JsonResponse
    {
        try {
            $purchaseOrders = PurchaseOrder::with('supplier', 'purchaseOrderItems.product')
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            return response()->json([
                'success' => true,
                'message' => 'Purchase orders retrieved successfully',
                'data' => $purchaseOrders->items(),
                'pagination' => [
                    'total' => $purchaseOrders->total(),
                    'current_page' => $purchaseOrders->currentPage(),
                    'last_page' => $purchaseOrders->lastPage(),
                    'per_page' => $purchaseOrders->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving purchase orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a purchase order - CORE BUSINESS LOGIC
     */
    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            // Generate order number
            $date = now()->format('Ymd');
            $lastOrder = PurchaseOrder::whereDate('created_at', today())
                ->orderBy('id', 'desc')
                ->first();
            $sequence = $lastOrder ? (intval(substr($lastOrder->order_number, -4)) + 1) : 1;
            $orderNumber = sprintf('PO-%s-%04d', $date, $sequence);
            
            // Create purchase order
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $request->supplier_id,
                'order_number' => $orderNumber,
                'order_date' => $request->order_date,
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes' => $request->notes ?? null,
                'status' => 'pending',
                'total_amount' => 0,
                'ordered_quantity' => 0,
            ]);
            
            $totalAmount = 0;
            $totalQuantity = 0;
            
            // Add items to purchase order
            foreach ($request->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'purchase_price' => $item['unit_cost'],
                    'subtotal' => $item['quantity'] * $item['unit_cost'],
                ]);
                
                $totalAmount += $item['quantity'] * $item['unit_cost'];
                $totalQuantity += $item['quantity'];
            }
            
            // Update purchase order totals
            $purchaseOrder->update([
                'total_amount' => $totalAmount,
                'ordered_quantity' => $totalQuantity,
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Purchase order created successfully',
                'data' => $purchaseOrder->load('supplier', 'purchaseOrderItems.product')
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display specific purchase order
     */
    public function show(string $id): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::with('supplier', 'purchaseOrderItems.product')
                ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $purchaseOrder
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update purchase order status
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:pending,received,cancelled',
                'expected_delivery_date' => 'sometimes|date',
            ]);
            
            $purchaseOrder = PurchaseOrder::findOrFail($id);
            
            // If receiving the order, add items to inventory
            if ($validated['status'] === 'received' && $purchaseOrder->status !== 'received') {
                DB::beginTransaction();
                
                try {
                    foreach ($purchaseOrder->purchaseOrderItems as $item) {
                        // Update inventory quantity
                        $inventory = Inventory::where('product_id', $item->product_id)->firstOrFail();
                        $inventory->increment('quantity', $item->quantity);
                        
                        // Record stock movement
                        \App\Models\StockMovement::create([
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                            'type' => 'stock_in',
                            'reference' => "PO-{$purchaseOrder->id}",
                            'notes' => "Purchase Order #{$purchaseOrder->order_number} received",
                        ]);
                    }
                    
                    $purchaseOrder->update($validated);
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            } else {
                $purchaseOrder->update($validated);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Purchase order updated successfully',
                'data' => $purchaseOrder->fresh(['supplier', 'purchaseOrderItems.product'])
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found'
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
                'message' => 'Error updating purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a purchase order
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::findOrFail($id);
            
            if ($purchaseOrder->status === 'received') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete order that has been received'
                ], 422);
            }
            
            DB::beginTransaction();
            
            // Delete purchase order items first
            $purchaseOrder->purchaseOrderItems()->delete();
            
            // Delete the purchase order
            $purchaseOrder->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Purchase order deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
