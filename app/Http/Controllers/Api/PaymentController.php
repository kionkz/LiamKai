<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Display all payments
     */
    public function index(): JsonResponse
    {
        try {
            $payments = Payment::with('order.customer')
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            return response()->json([
                'success' => true,
                'message' => 'Payments retrieved successfully',
                'data' => $payments->items(),
                'pagination' => [
                    'total' => $payments->total(),
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record a payment for an order - CORE BUSINESS LOGIC
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $order = Order::findOrFail($request->order_id);
            
            // Validate payment amount doesn't exceed balance
            if ($request->amount > $order->balance_due) {
                return response()->json([
                    'success' => false,
                    'message' => "Payment amount exceeds balance due. Balance: {$order->balance_due}"
                ], 422);
            }
            
            // Create payment record
            $payment = Payment::create([
                'order_id' => $request->order_id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'reference' => $request->reference ?? null,
                'payment_date' => $request->payment_date ?? now(),
            ]);
            
            // Update order balance
            $order->update([
                'balance_due' => $order->balance_due - $request->amount,
            ]);
            
            // If fully paid, update order status
            if ($order->balance_due <= 0) {
                $order->update(['status' => 'confirmed']);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => [
                    'payment' => $payment,
                    'order_balance' => $order->balance_due,
                ]
            ], 201);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error recording payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display payment details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $payment = Payment::with('order.customer', 'order.orderItems')
                ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $payment
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payments for a specific order
     */
    public function update($id): JsonResponse
    {
        // Payments are typically not updated, only viewed or deleted
        return response()->json([
            'success' => false,
            'message' => 'Payments cannot be updated. Delete and create a new payment instead.'
        ], 405);
    }

    /**
     * Delete a payment and restore order balance
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $payment = Payment::findOrFail($id);
            $order = $payment->order;
            
            // Restore order balance
            $order->update([
                'balance_due' => $order->balance_due + $payment->amount,
                'status' => 'pending'
            ]);
            
            $payment->delete();
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Payment deleted and order balance restored'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
