<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Support\PricingMath;

class OrderController extends Controller
{
    /**
     * Display all orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page' => ['sometimes', 'integer', 'min:1'],
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);

            $orders = Order::with('customer', 'orderItems.product', 'payments', 'delivery')
                ->where('fulfillment_status', '!=', 'cancelled')
                ->orderBy('created_at', 'desc')
                ->paginate($validated['per_page'] ?? 15);

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
            $orderType = $customer->type ?? 'retail';
            $fulfillmentType = $validated['fulfillment_type'];
            $scheduledFor = $validated['scheduled_for'];
            $deliveryAddress = $fulfillmentType === 'delivery'
                ? ($validated['delivery_address'] ?? $customer->address ?? 'No address provided')
                : null;

            $order = Order::create([
                'customer_id' => $validated['customer_id'],
                'fulfillment_type' => $fulfillmentType,
                'order_type' => $orderType,
                'notes' => $validated['notes'] ?? null,
                'payment_status' => 'unpaid',
                'fulfillment_status' => 'pending',
                'delivery_status' => 'pending',
                'delivery_address' => $deliveryAddress,
                'scheduled_for' => $scheduledFor,
                'total_amount' => 0,
                'outstanding_balance' => 0,
            ]);

            $totalAmount = 0;

            foreach ($validated['items'] as $item) {
                $inventory = Inventory::with('product.pricing')->where('product_id', $item['product_id'])->firstOrFail();
                $availableQuantity = $inventory->availableQuantity();

                if ($availableQuantity < (float) $item['quantity']) {
                    $productName = $inventory->product?->name ?? "Product ID {$item['product_id']}";
                    throw new \Exception("Insufficient stock for {$productName}. Available: {$availableQuantity}");
                }

                $resolvedUnitPrice = PricingMath::resolveOrderPrice($inventory->product, $orderType);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $resolvedUnitPrice,
                    'subtotal' => $item['quantity'] * $resolvedUnitPrice,
                ]);

                $inventory->applyQuantityDelta(-(float) $item['quantity']);

                $inventory->stockMovements()->create([
                    'type' => 'stock_out',
                    'movement_type' => 'sale',
                    'quantity' => $item['quantity'],
                    'reason' => 'Customer order fulfilled',
                    'reference' => "ORDER-{$order->id}",
                    'reference_id' => $order->id,
                    'notes' => "Stock deducted for order #{$order->id}",
                ]);

                $totalAmount += $item['quantity'] * $resolvedUnitPrice;
            }

            // Credit limit check: block if the customer's current unpaid balance is already at or over the limit.
            // The new order amount is irrelevant — a customer can order any amount as long as
            // their existing outstanding balance is below the limit.
            if ((float) $customer->credit_limit > 0) {
                $currentCreditUsed = Order::where('customer_id', $customer->id)
                    ->whereNotIn('payment_status', ['paid'])
                    ->sum('outstanding_balance');
                if ((float) $currentCreditUsed >= (float) $customer->credit_limit) {
                    throw new \Exception(sprintf(
                        '%s has ₱%s in unpaid orders, which meets or exceeds the ₱%s outstanding balance limit. They must make a payment before placing new orders.',
                        $customer->name,
                        number_format($currentCreditUsed, 2),
                        number_format($customer->credit_limit, 2)
                    ));
                }
            }

            $order->update([
                'total_amount' => $totalAmount,
                'outstanding_balance' => $totalAmount,
                'delivery_date' => $order->scheduled_for?->toDateString(),
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
            $validated = $request->validated();

            if (array_key_exists('fulfillment_type', $validated) && $validated['fulfillment_type'] === 'pickup') {
                $validated['delivery_address'] = null;
            }

            if (array_key_exists('scheduled_for', $validated)) {
                $validated['delivery_date'] = $validated['scheduled_for']
                    ? date('Y-m-d', strtotime((string) $validated['scheduled_for']))
                    : null;
            }

            if (isset($validated['fulfillment_status']) && !isset($validated['delivery_status'])) {
                $validated['delivery_status'] = $this->mapFulfillmentStatusToDeliveryStatus($validated['fulfillment_status']);
            }

            if (isset($validated['delivery_status']) && !isset($validated['fulfillment_status'])) {
                $validated['fulfillment_status'] = $this->mapDeliveryStatusToFulfillmentStatus($validated['delivery_status']);
            }

            $order->update($validated);

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
     * Archive an order (restores inventory and marks as cancelled)
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $order = Order::with('orderItems', 'delivery')->findOrFail($id);

            if (in_array($order->fulfillment_status, ['completed', 'cancelled'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot archive {$order->fulfillment_status} order"
                ], 422);
            }

            foreach ($order->orderItems as $item) {
                $inventory = Inventory::where('product_id', $item->product_id)->firstOrFail();
                $inventory->applyQuantityDelta((float) $item->quantity);

                $inventory->stockMovements()->create([
                    'type' => 'stock_in',
                    'movement_type' => 'order_archive',
                    'quantity' => $item->quantity,
                    'reason' => 'Archived order stock restoration',
                    'reference' => "ORDER-{$order->id}",
                    'reference_id' => $order->id,
                    'notes' => "Stock restored for cancelled order #{$order->id}",
                ]);
            }

            if ($order->delivery) {
                $order->delivery->update(['status' => 'failed']);
            }

            $order->update([
                'fulfillment_status' => 'cancelled',
                'delivery_status' => 'cancelled',
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order archived and inventory restored'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error archiving order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function mapFulfillmentStatusToDeliveryStatus(string $status): string
    {
        return match ($status) {
            'in_progress' => 'processing',
            'completed' => 'delivered',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    private function mapDeliveryStatusToFulfillmentStatus(string $status): string
    {
        return match ($status) {
            'processing' => 'in_progress',
            'delivered' => 'completed',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    private function formatOrder(Order $order): array
    {
        $formatted = $order->toArray();
        $formatted['type'] = $order->order_type;
        $formatted['status'] = $order->payment_status;
        $formatted['amount_paid'] = max((float) $order->total_amount - (float) $order->outstanding_balance, 0);
        $formatted['remaining_balance'] = (float) $order->outstanding_balance;
        $formatted['order_date'] = $order->created_at?->toDateString();
        $formatted['scheduled_for'] = $order->scheduled_for?->toIso8601String();
        $formatted['fulfillment_type'] = $order->fulfillment_type ?? 'delivery';
        $formatted['fulfillment_status'] = $order->fulfillment_status
            ?? $this->mapDeliveryStatusToFulfillmentStatus($order->delivery_status ?? 'pending');
        $formatted['items'] = $order->orderItems->map(function (OrderItem $item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product' => $item->product,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
                'total' => (float) $item->subtotal,
                'unit' => $item->product?->unit_of_measure ?? '',
            ];
        })->values()->all();

        return $formatted;
    }
}
