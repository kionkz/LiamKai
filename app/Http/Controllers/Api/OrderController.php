<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display all orders
     */
    public function index(): JsonResponse
    {
        try {
            $orders = Order::with('customer', 'orderItems.product', 'payments')
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            return response()->json([
                'success' => true,
                'message' => 'Orders retrieved successfully',
                'data' => $orders->items(),
                'pagination' => [
                    'total' => $orders->total(),
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new order - CORE BUSINESS LOGIC
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            // Create the order
            $order = Order::create([
                'customer_id' => $request->customer_id,
                'order_date' => $request->order_date,
                'notes' => $request->notes ?? null,
                'status' => 'pending',
                'total_amount' => 0,
                'balance_due' => 0,
            ]);
            
            $totalAmount = 0;
            
            // Create order items and deduct inventory
            foreach ($request->items as $item) {
                $inventory = Inventory::where('product_id', $item['product_id'])->firstOrFail();
                
                // Check stock availability
                if ($inventory->quantity_on_hand < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product ID {$item['product_id']}. Available: {$inventory->quantity_on_hand}");
                }
                
                // Create order item
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                ]);
                
                // Deduct from inventory
                $inventory->decrement('quantity_on_hand', $item['quantity']);
                
                // Record stock movement (outbound)
                $inventory->stockMovements()->create([
                    'movement_type' => 'stock_out',
                    'quantity' => $item['quantity'],
                    'reason' => "Order #{$order->id}",
                    'reference_id' => $order->id,
                ]);
                
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }
            
            // Update order totals
            $order->update([
                'total_amount' => $totalAmount,
                'balance_due' => $totalAmount,
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order->load('customer', 'orderItems.product')
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a specific order with all details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $order = Order::with('customer', 'orderItems.product', 'payments', 'delivery')
                ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $order
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order details (date, notes, status)
     */
    public function update(UpdateOrderRequest $request, string $id): JsonResponse
    {
        try {
            $order = Order::findOrFail($id);
            $order->update($request->validated());
            
            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
                'data' => $order->load('customer', 'orderItems', 'payments')
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel an order (restore inventory)
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $order = Order::findOrFail($id);
            
            if ($order->status === 'delivered' || $order->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot cancel {$order->status} order"
                ], 422);
            }
            
            // Restore inventory for all items
            foreach ($order->orderItems as $item) {
                $inventory = Inventory::where('product_id', $item->product_id)->firstOrFail();
                $inventory->increment('quantity_on_hand', $item->quantity);
                
                // Record stock movement (return)
                $inventory->stockMovements()->create([
                    'movement_type' => 'stock_in',
                    'quantity' => $item->quantity,
                    'reason' => "Order #{$order->id} cancelled",
                    'reference_id' => $order->id,
                ]);
            }
            
            $order->update(['status' => 'cancelled']);
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Order cancelled and inventory restored'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error cancelling order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
