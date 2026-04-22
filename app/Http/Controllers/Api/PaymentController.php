<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Display all payment records.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page' => ['sometimes', 'integer', 'min:1'],
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);

            $payments = Payment::with('order.customer', 'recordedBy')
                ->orderByDesc('payment_date')
                ->orderByDesc('created_at')
                ->paginate($validated['per_page'] ?? 15);

            return response()->json([
                'success' => true,
                'message' => 'Payments retrieved successfully',
                'data' => $payments->items(),
                'pagination' => [
                    'total' => $payments->total(),
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display orders that need payment management attention.
     */
    public function management(Request $request): JsonResponse
    {
        try {
            if ($request->input('status') === 'utang') {
                $request->merge(['status' => 'partially_paid']);
            }

            $validated = $request->validate([
                'page' => ['sometimes', 'integer', 'min:1'],
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'search' => ['sometimes', 'string', 'max:255'],
                'status' => ['sometimes', 'in:paid,unpaid,partially_paid'],
                'sort_by' => ['sometimes', 'in:id,customer,total_amount,amount_paid,remaining_balance,payment_status,scheduled_for,created_at'],
                'sort_direction' => ['sometimes', 'in:asc,desc'],
            ]);

            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortDirection = $validated['sort_direction'] ?? 'desc';

            $query = Order::with([
                'customer',
                'payments' => function ($paymentQuery) {
                    $paymentQuery->with('recordedBy')->latest('payment_date')->latest('created_at');
                },
            ])
                ->leftJoin('customers', 'customers.id', '=', 'orders.customer_id')
                ->select('orders.*');

            if (!empty($validated['search'])) {
                $search = trim($validated['search']);
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('id', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            if (!empty($validated['status'])) {
                $status = $validated['status'];
                $query->where(function ($innerQuery) use ($status) {
                    if ($status === 'partially_paid') {
                        $innerQuery->whereIn('payment_status', ['partially_paid', 'utang']);
                        return;
                    }

                    $innerQuery->where('payment_status', $status);
                });
            }

            match ($sortBy) {
                'id' => $query->orderBy('orders.id', $sortDirection),
                'customer' => $query->orderBy('customers.name', $sortDirection),
                'total_amount' => $query->orderBy('orders.total_amount', $sortDirection),
                'amount_paid' => $query->orderByRaw('(orders.total_amount - orders.outstanding_balance) ' . $sortDirection),
                'remaining_balance' => $query->orderBy('orders.outstanding_balance', $sortDirection),
                'payment_status' => $query->orderBy('orders.payment_status', $sortDirection),
                'scheduled_for' => $query->orderByRaw('orders.scheduled_for IS NULL')->orderBy('orders.scheduled_for', $sortDirection),
                default => $query->orderBy('orders.created_at', $sortDirection),
            };

            $query->orderBy('orders.id', $sortDirection === 'asc' ? 'asc' : 'desc');

            $orders = $query->paginate($validated['per_page'] ?? 20);

            $data = $orders->getCollection()->map(function (Order $order) {
                $totalAmount = (float) $order->total_amount;
                $remainingBalance = (float) $order->outstanding_balance;
                $amountPaid = max($totalAmount - $remainingBalance, 0);

                return [
                    'id' => $order->id,
                    'order_id' => $order->id,
                    'customer_name' => $order->customer?->name ?? 'Walk-In Customer',
                    'total_amount' => $totalAmount,
                    'amount_paid' => $amountPaid,
                    'remaining_balance' => $remainingBalance,
                    'payment_status' => $this->normalizePaymentStatus($order->payment_status),
                    'fulfillment_type' => $order->fulfillment_type,
                    'scheduled_for' => $order->scheduled_for?->toIso8601String(),
                    'payments' => $order->payments->map(function (Payment $payment) {
                        return [
                            'id' => $payment->id,
                            'amount' => (float) $payment->amount,
                            'payment_method' => $payment->payment_method,
                            'payment_date' => optional($payment->payment_date)->toDateString(),
                            'reference' => $payment->reference,
                            'bank_name' => $payment->bank_name,
                            'status' => $payment->status,
                            'recorded_by' => $payment->recordedBy ? [
                                'id' => $payment->recordedBy->id,
                                'name' => $payment->recordedBy->name,
                                'username' => $payment->recordedBy->username,
                            ] : null,
                        ];
                    })->values(),
                ];
            })->values();

            $countsBase = Order::query();
            if (!empty($validated['search'])) {
                $search = trim($validated['search']);
                $countsBase->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('id', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'counts' => [
                        'paid' => (clone $countsBase)->where('payment_status', 'paid')->count(),
                        'unpaid' => (clone $countsBase)->where('payment_status', 'unpaid')->count(),
                        'partially_paid' => (clone $countsBase)->whereIn('payment_status', ['partially_paid', 'utang'])->count(),
                    ],
                ],
                'pagination' => [
                    'total' => $orders->total(),
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment management data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Record a payment for an order.
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $order = Order::with('payments')->findOrFail($request->order_id);
            $currentOutstanding = (float) $order->outstanding_balance;
            $totalAmount = (float) $order->total_amount;

            $method = $request->input('payment_method', 'cash');
            $isCredit = $method === 'credit';

            // Credit = deferred payment (pay later). Non-credit payments cannot exceed outstanding balance.
            if (!$isCredit && (float) $request->amount > $currentOutstanding) {
                return response()->json([
                    'success' => false,
                    'message' => "Payment amount exceeds outstanding balance. Balance: {$order->outstanding_balance}",
                ], 422);
            }

            $payment = Payment::create([
                'order_id' => $request->order_id,
                'recorded_by_user_id' => $request->user()?->id,
                'amount' => $request->amount,
                'payment_method' => $method,
                'reference' => $request->reference ?: $this->generatePaymentReference(),
                'payment_date' => $request->payment_date ?? now()->toDateString(),
                'deposit_date' => $method === 'check' ? $request->deposit_date : null,
                'check_from' => $method === 'check' ? $request->check_from : null,
                'bank_name' => $method === 'bank_transfer' ? $request->bank_name : null,
                'status' => 'completed',
            ]);

            // Credit payments do not reduce outstanding balance — they are recorded IOUs.
            // Only cash/gcash/bank/check payments settle the debt.
            if (!$isCredit) {
                $newOutstanding = max($currentOutstanding - (float) $request->amount, 0);
                $order->update([
                    'outstanding_balance' => $newOutstanding,
                    'payment_status' => $this->resolvePaymentStatus(
                        totalAmount: $totalAmount,
                        remainingBalance: $newOutstanding,
                        paymentMethod: $method,
                        existingPaymentCount: $order->payments->count() + 1,
                    ),
                ]);
            } else {
                $newOutstanding = $currentOutstanding;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => [
                    'payment' => $payment,
                    'order_balance' => $newOutstanding,
                    'payment_status' => $order->fresh()->payment_status,
                ],
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error recording payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display payment details.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $payment = Payment::with('order.customer', 'order.orderItems', 'recordedBy')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $payment,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Payments are immutable after creation.
     */
    public function update($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Payments cannot be updated. Delete and create a new payment instead.',
        ], 405);
    }

    /**
     * Delete a payment and restore order balance.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $payment = Payment::findOrFail($id);
            $order = $payment->order;
            $restoredOutstanding = (float) $order->outstanding_balance + (float) $payment->amount;
            $totalAmount = (float) $order->total_amount;

            $remainingPayments = $order->payments()->whereKeyNot($payment->id)->get();
            $latestMethod = $remainingPayments->sortByDesc(function (Payment $paymentRecord) {
                return sprintf('%s-%s', optional($paymentRecord->payment_date)->toDateString() ?? '', $paymentRecord->created_at);
            })->first()?->payment_method;

            $order->update([
                'outstanding_balance' => $restoredOutstanding,
                'payment_status' => $this->resolvePaymentStatus(
                    totalAmount: $totalAmount,
                    remainingBalance: $restoredOutstanding,
                    paymentMethod: $latestMethod,
                    existingPaymentCount: $remainingPayments->count(),
                ),
            ]);

            $payment->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment deleted and order balance restored',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting payment',
                'error' => $e->getMessage(),
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

    private function resolvePaymentStatus(float $totalAmount, float $remainingBalance, ?string $paymentMethod, int $existingPaymentCount): string
    {
        if ($remainingBalance <= 0) {
            return 'paid';
        }

        if ($existingPaymentCount === 0 || $remainingBalance >= $totalAmount) {
            return 'unpaid';
        }

        return 'partially_paid';
    }

    private function normalizePaymentStatus(?string $status): string
    {
        return $status === 'utang' ? 'partially_paid' : ($status ?: 'unpaid');
    }
}
