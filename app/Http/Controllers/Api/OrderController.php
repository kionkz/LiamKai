<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
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
            $orders = Order::with('customer', 'orderItems.product', 'payments', 'delivery')
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            $formattedOrders = $orders->getCollection()
                ->map(fn (Order $order) => $this->formatOrder($order))
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Orders retrieved successfully',
                'data' => $formattedOrders,
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

            $validated = $request->validated();
            $customer = Customer::findOrFail($validated['customer_id']);
            $orderType = $validated['order_type'] ?? $validated['type'] ?? 'retail';

            $order = Order::create([
                'customer_id' => $validated['customer_id'],
                'order_type' => $orderType,
                'notes' => $validated['notes'] ?? null,
                'payment_status' => 'pending',
                'delivery_status' => 'pending',
                'delivery_address' => $validated['delivery_address'] ?? $customer->address ?? 'No address provided',
                'total_amount' => 0,
                'outstanding_balance' => 0,
            ]);

            $totalAmount = 0;

            foreach ($validated['items'] as $item) {
                $inventory = Inventory::where('product_id', $item['product_id'])->firstOrFail();
                $availableQuantity = (float) ($inventory->quantity ?? $inventory->quantity_on_hand ?? 0);

                if ($availableQuantity < (float) $item['quantity']) {
                    throw new \Exception("Insufficient stock for product ID {$item['product_id']}. Available: {$availableQuantity}");
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                ]);

                $inventory->decrement('quantity', $item['quantity']);

                $inventory->stockMovements()->create([
                    'type' => 'stock_out',
                    'quantity' => $item['quantity'],
                    'reference' => "ORDER-{$order->id}",
                    'notes' => "Stock deducted for order #{$order->id}",
                ]);

                $totalAmount += $item['quantity'] * $item['unit_price'];
            }

            [$scheduledDelivery, $deliveryStatus] = $this->determineDeliverySchedule(Carbon::now());

            Delivery::create([
                'order_id' => $order->id,
                'employee_id' => null,
                'status' => 'pending',
                'scheduled_delivery' => $scheduledDelivery,
                'delivery_address' => $order->delivery_address,
                'notes' => 'Auto-created when order was placed',
            ]);

            $order->update([
                'total_amount' => $totalAmount,
                'outstanding_balance' => $totalAmount,
                'delivery_status' => $deliveryStatus,
                'delivery_date' => $scheduledDelivery->toDateString(),
            ]);

            DB::commit();

            $order->load('customer', 'orderItems.product', 'payments', 'delivery');

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $this->formatOrder($order)
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
                'data' => $this->formatOrder($order)
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
     * Update order details
     */
    public function update(UpdateOrderRequest $request, string $id): JsonResponse
    {
        try {
            $order = Order::findOrFail($id);
            $order->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
                'data' => $this->formatOrder($order->load('customer', 'orderItems.product', 'payments', 'delivery'))
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

            $order = Order::with('orderItems', 'delivery')->findOrFail($id);

            if (in_array($order->delivery_status, ['delivered', 'cancelled'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot cancel {$order->delivery_status} order"
                ], 422);
            }

            foreach ($order->orderItems as $item) {
                $inventory = Inventory::where('product_id', $item->product_id)->firstOrFail();
                $inventory->increment('quantity', $item->quantity);

                $inventory->stockMovements()->create([
                    'type' => 'stock_in',
                    'quantity' => $item->quantity,
                    'reference' => "ORDER-{$order->id}",
                    'notes' => "Stock restored for cancelled order #{$order->id}",
                ]);
            }

            if ($order->delivery) {
                $order->delivery->update(['status' => 'failed']);
            }

            $order->update(['delivery_status' => 'cancelled']);
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

    private function determineDeliverySchedule(Carbon $orderTime): array
    {
        $cutoffTime = $orderTime->copy()->setTime(15, 0, 0);

        if ($orderTime->lessThanOrEqualTo($cutoffTime)) {
            return [$orderTime->copy()->setTime(18, 0, 0), 'processing'];
        }

        return [$orderTime->copy()->addDay()->setTime(9, 0, 0), 'pending'];
    }

    private function formatOrder(Order $order): array
    {
        $formatted = $order->toArray();
        $formatted['type'] = $order->order_type;
        $formatted['status'] = $order->delivery_status;
        $formatted['items'] = $formatted['order_items'] ?? [];

        return $formatted;
    }
}
