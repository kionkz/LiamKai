<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    /**
     * Display all deliveries
     */
    public function index(): JsonResponse
    {
        try {
            $deliveries = Delivery::with('order.customer', 'employee')
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            return response()->json([
                'success' => true,
                'message' => 'Deliveries retrieved successfully',
                'data' => $deliveries->items(),
                'pagination' => [
                    'total' => $deliveries->total(),
                    'current_page' => $deliveries->currentPage(),
                    'last_page' => $deliveries->lastPage(),
                    'per_page' => $deliveries->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving deliveries',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a delivery for an order
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|exists:orders,id',
                'assigned_employee_id' => 'required|exists:employees,id',
                'delivery_date' => 'required|date',
                'notes' => 'nullable|string',
            ]);
            
            DB::beginTransaction();
            
            // Check if order exists and hasn't been delivered
            $order = Order::findOrFail($validated['order_id']);
            
            if ($order->delivery) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order already has a delivery record'
                ], 422);
            }
            
            // Create delivery
            $delivery = Delivery::create([
                'order_id' => $validated['order_id'],
                'assigned_employee_id' => $validated['assigned_employee_id'],
                'delivery_date' => $validated['delivery_date'],
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);
            
            // Update order status
            $order->update(['status' => 'shipped']);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Delivery created successfully',
                'data' => $delivery->load('order.customer', 'employee')
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Order or employee not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating delivery',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get delivery details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $delivery = Delivery::with('order.customer', 'order.orderItems.product', 'employee')
                ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $delivery
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving delivery',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update delivery status (mark as in-transit, delivered, etc)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:pending,in_transit,delivered,failed',
                'actual_delivery_date' => 'required_if:status,delivered|date',
                'notes' => 'nullable|string',
            ]);
            
            DB::beginTransaction();
            
            $delivery = Delivery::findOrFail($id);
            $delivery->update([
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? $delivery->notes,
            ]);
            
            // If marked as delivered, update order status
            if ($validated['status'] === 'delivered') {
                $delivery->order->update([
                    'status' => 'delivered',
                    'delivery_date' => $validated['actual_delivery_date'] ?? now()
                ]);
            }
            
            // If marked as failed, revert to pending
            if ($validated['status'] === 'failed') {
                $delivery->order->update(['status' => 'pending']);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Delivery updated successfully',
                'data' => $delivery->fresh(['order.customer', 'employee'])
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Delivery not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating delivery',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a delivery
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $delivery = Delivery::findOrFail($id);
            
            if ($delivery->status === 'delivered') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel a delivery that has been completed'
                ], 422);
            }
            
            $delivery->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Delivery cancelled successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error cancelling delivery',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
