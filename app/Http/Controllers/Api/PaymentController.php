<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            $currentOutstanding = (float) $order->outstanding_balance;
            
            // Validate payment amount doesn't exceed balance
            if ((float) $request->amount > $currentOutstanding) {
                return response()->json([
                    'success' => false,
                    'message' => "Payment amount exceeds outstanding balance. Balance: {$order->outstanding_balance}"
                ], 422);
            }
            
            // Create payment record
            $method = $request->input('payment_method', 'cash');
            $payment = Payment::create([
                'order_id' => $request->order_id,
                'amount' => $request->amount,
                'payment_method' => $method,
                'reference' => $this->generatePaymentReference(),
                'payment_date' => $request->payment_date ?? now()->toDateString(),
                'deposit_date' => $method === 'check' ? $request->deposit_date : null,
                'check_from' => $method === 'check' ? $request->check_from : null,
            ]);
            
            $newOutstanding = max($currentOutstanding - (float) $request->amount, 0);

            $order->update([
                'outstanding_balance' => $newOutstanding,
                'payment_status' => $newOutstanding <= 0
                    ? 'paid'
                    : ($newOutstanding < (float) $order->total_amount ? 'partial' : 'pending'),
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => [
                    'payment' => $payment,
                    'order_balance' => $newOutstanding,
                    'payment_status' => $order->fresh()->payment_status,
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

    private function generatePaymentReference(): string
    {
        do {
            $reference = 'PAY-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (Payment::where('reference', $reference)->exists());

        return $reference;
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
            $restoredOutstanding = (float) $order->outstanding_balance + (float) $payment->amount;
            $totalAmount = (float) $order->total_amount;
            $order->update([
                'outstanding_balance' => $restoredOutstanding,
                'payment_status' => $restoredOutstanding >= $totalAmount
                    ? 'pending'
                    : ($restoredOutstanding > 0 ? 'partial' : 'paid'),
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
