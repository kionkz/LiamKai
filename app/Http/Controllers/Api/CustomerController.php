<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display all customers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = trim((string) $request->input('search', ''));
            $perPage = (int) $request->input('per_page', 15);
            $sortBy = (string) $request->input('sort_by', 'name');
            $sortDirection = strtolower((string) $request->input('sort_direction', 'asc')) === 'desc' ? 'desc' : 'asc';

            $query = Customer::withSum(
                ['orders as credit_used' => fn ($q) => $q->whereNotIn('payment_status', ['paid'])],
                'outstanding_balance'
            );

            if ($search !== '') {
                $query->where(function ($customerQuery) use ($search) {
                    $customerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            }

            match ($sortBy) {
                'email' => $query->orderBy('email', $sortDirection),
                'phone' => $query->orderBy('phone', $sortDirection),
                'type' => $query->orderBy('type', $sortDirection),
                'credit_used' => $query->orderBy('credit_used', $sortDirection),
                default => $query->orderBy('name', $sortDirection),
            };

            $customers = $query->paginate($perPage);

            $data = collect($customers->items())->map(function ($customer) {
                $creditUsed = (float) ($customer->credit_used ?? 0);
                $creditLimit = (float) ($customer->credit_limit ?? 0);
                $customer->credit_used = $creditUsed;
                $customer->credit_available = $creditLimit > 0 ? max($creditLimit - $creditUsed, 0) : null;
                $customer->credit_limit_exceeded = $creditLimit > 0 && $creditUsed >= $creditLimit;
                return $customer;
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Customers retrieved successfully',
                'data' => $data,
                'pagination' => [
                    'total' => $customers->total(),
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving customers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new customer
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['credit_limit'] = $data['type'] === 'wholesale' ? 15000 : 0;
            $customer = Customer::create($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => $customer
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a specific customer
     */
    public function show(string $id): JsonResponse
    {
        try {
            $customer = Customer::with([
                    'orders' => fn ($query) => $query->latest(),
                    'orders.orderItems.product',
                    'orders.payments',
                    'orders.delivery',
                ])
                ->findOrFail($id);
            
            // Credit tracking: sum of outstanding_balance on non-paid orders
            $creditUsed = $customer->orders
                ->filter(fn ($o) => !in_array($o->payment_status, ['paid']))
                ->sum('outstanding_balance');
            $creditLimit = (float) $customer->credit_limit;
            $customer->credit_used = (float) $creditUsed;
            $customer->credit_available = $creditLimit > 0 ? max($creditLimit - $creditUsed, 0) : null;
            $customer->credit_limit_exceeded = $creditLimit > 0 && $creditUsed >= $creditLimit;
            $customer->orders->each(function (Order $order) {
                $order->setAttribute('order_status', $this->resolveOrderStatus($order));
                $order->setAttribute('items', $order->orderItems->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => $item->product,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                ])->values());
            });
            
            return response()->json([
                'success' => true,
                'data' => $customer
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a customer
     */
    public function update(UpdateCustomerRequest $request, string $id): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($id);
            $data = $request->validated();
            $effectiveType = $data['type'] ?? $customer->type;
            $data['credit_limit'] = $effectiveType === 'wholesale' ? 15000 : 0;
            $customer->update($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $customer
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a customer
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($id);
            
            // Prevent deletion if customer has orders
            if ($customer->orders()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete customer with existing orders'
                ], 422);
            }
            
            $customer->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function resolveOrderStatus(Order $order): string
    {
        if ($order->fulfillment_status === 'cancelled' || $order->delivery_status === 'cancelled') {
            return 'cancelled';
        }

        $isDelivered = $order->fulfillment_status === 'completed' || $order->delivery_status === 'delivered';
        $isPaid = $order->payment_status === 'paid' || (float) $order->outstanding_balance <= 0;

        return $isDelivered && $isPaid ? 'complete' : 'pending';
    }
}
