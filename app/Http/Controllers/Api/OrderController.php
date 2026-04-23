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
use App\Models\Payment;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                'search' => ['sometimes', 'string', 'max:255'],
                'fulfillment_type' => ['sometimes', 'in:delivery,pickup'],
                'sort_by' => ['sometimes', 'in:id,customer,pricing,fulfillment_type,scheduled_for,total_amount,payment_status,fulfillment_status,created_at'],
                'sort_direction' => ['sometimes', 'in:asc,desc'],
            ]);

            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortDirection = $validated['sort_direction'] ?? 'desc';

            $ordersQuery = Order::with('customer', 'orderItems.product', 'payments', 'delivery', 'fulfillmentUpdatedBy')
                ->leftJoin('customers', 'customers.id', '=', 'orders.customer_id')
                ->select('orders.*');

            if (!empty($validated['search'])) {
                $search = trim($validated['search']);
                $ordersQuery->where(function ($query) use ($search) {
                    $query->where('orders.id', 'like', "%{$search}%")
                        ->orWhere('customers.name', 'like', "%{$search}%");
                });
            }

            if (!empty($validated['fulfillment_type'])) {
                $ordersQuery->where('orders.fulfillment_type', $validated['fulfillment_type']);
            }

            match ($sortBy) {
                'id' => $ordersQuery->orderBy('orders.id', $sortDirection),
                'customer' => $ordersQuery->orderBy('customers.name', $sortDirection),
                'pricing' => $ordersQuery->orderBy('orders.order_type', $sortDirection),
                'fulfillment_type' => $ordersQuery->orderBy('orders.fulfillment_type', $sortDirection),
                'scheduled_for' => $ordersQuery->orderByRaw('orders.scheduled_for IS NULL')->orderBy('orders.scheduled_for', $sortDirection),
                'total_amount' => $ordersQuery->orderBy('orders.total_amount', $sortDirection),
                'payment_status' => $ordersQuery->orderBy('orders.payment_status', $sortDirection),
                'fulfillment_status' => $ordersQuery->orderBy('orders.fulfillment_status', $sortDirection),
                default => $ordersQuery->orderBy('orders.created_at', $sortDirection),
            };

            $ordersQuery->orderBy('orders.id', $sortDirection === 'asc' ? 'asc' : 'desc');

            $orders = $ordersQuery->paginate($validated['per_page'] ?? 15);

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

                $this->deductInventoryForOrder($inventory, (float) $item['quantity'], $order->id);

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
                        number_format((float) $customer->credit_limit, 2)
                    ));
                }
            }

            $order->update([
                'total_amount' => $totalAmount,
                'outstanding_balance' => $totalAmount,
                'delivery_date' => $order->scheduled_for?->toDateString(),
            ]);

            DB::commit();

            $order->load('customer', 'orderItems.product', 'payments', 'delivery', 'fulfillmentUpdatedBy');

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
     * Create a paid walk-in POS order and deduct inventory immediately.
     */
    public function storePosTransaction(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_method' => ['required', 'in:cash,gcash'],
                'reference' => ['required_if:payment_method,gcash', 'nullable', 'string', 'max:255'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', 'exists:products,id'],
                'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            ]);

            DB::beginTransaction();

            $now = now();
            $order = Order::create([
                'customer_id' => null,
                'fulfillment_type' => 'pickup',
                'order_type' => 'retail',
                'notes' => 'Point of sale transaction',
                'payment_status' => 'paid',
                'fulfillment_status' => 'completed',
                'delivery_status' => 'delivered',
                'delivery_address' => null,
                'scheduled_for' => $now,
                'actual_fulfillment_at' => $now,
                'delivery_date' => $now->toDateString(),
                'total_amount' => 0,
                'outstanding_balance' => 0,
            ]);

            $totalAmount = 0;

            foreach ($validated['items'] as $item) {
                $inventory = Inventory::with('product.pricing')
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $quantity = (float) $item['quantity'];
                $availableQuantity = $inventory->availableQuantity();

                if ($availableQuantity < $quantity) {
                    $productName = $inventory->product?->name ?? "Product ID {$item['product_id']}";
                    throw new \Exception("Insufficient stock for {$productName}. Available: {$availableQuantity}");
                }

                $unitPrice = PricingMath::resolveOrderPrice($inventory->product, 'retail');
                $subtotal = $quantity * $unitPrice;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $this->deductInventoryForOrder(
                    inventory: $inventory,
                    quantity: $quantity,
                    orderId: $order->id,
                    movementType: 'stock_out_pos',
                    reason: 'Point of sale transaction'
                );

                $totalAmount += $subtotal;
            }

            $order->update([
                'total_amount' => $totalAmount,
                'outstanding_balance' => 0,
            ]);

            $payment = Payment::create([
                'order_id' => $order->id,
                'recorded_by_user_id' => $request->user()?->id,
                'amount' => $totalAmount,
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?: $this->generatePosPaymentReference(),
                'payment_date' => $now->toDateString(),
                'status' => 'completed',
                'notes' => 'Point of sale payment',
            ]);

            DB::commit();

            $order->load('customer', 'orderItems.product', 'payments.recordedBy', 'delivery', 'fulfillmentUpdatedBy');

            return response()->json([
                'success' => true,
                'message' => 'POS transaction recorded successfully',
                'data' => [
                    'order' => $this->formatOrder($order),
                    'payment' => [
                        'id' => $payment->id,
                        'reference' => $payment->reference,
                        'payment_method' => $payment->payment_method,
                        'amount' => (float) $payment->amount,
                        'payment_date' => optional($payment->payment_date)->toDateString(),
                    ],
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json([
                'success' => false,
                'message' => 'Error recording POS transaction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a specific order with all details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $order = Order::with('customer', 'orderItems.product', 'payments', 'delivery', 'fulfillmentUpdatedBy')
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
            $order = Order::with('customer', 'orderItems.product', 'payments')->findOrFail($id);
            $validated = $request->validated();
            $isEditingItems = array_key_exists('items', $validated);

            if ($this->isOrderLockedForEditing($order)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Completed or cancelled orders can no longer be edited.',
                ], 422);
            }

            if ($isEditingItems && ! $this->canFullyEditOrder($order)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order items can only be edited when no payments have been made and the order is not delivered, in progress, or cancelled.',
                ], 422);
            }

            $isEditingDeliveryDetails = array_key_exists('delivery_address', $validated)
                || array_key_exists('delivery_date', $validated)
                || array_key_exists('scheduled_for', $validated);

            if ($isEditingDeliveryDetails && (
                in_array($order->fulfillment_status, ['in_progress', 'completed'], true)
                || in_array($order->delivery_status, ['processing', 'delivered'], true)
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery details cannot be edited once logistics is en route or delivered.',
                ], 422);
            }

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

            DB::beginTransaction();

            if ($isEditingItems) {
                $this->replaceEditableOrderItems($order, $validated['items']);
                unset($validated['items']);
            }

            $order->update($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
                'data' => $this->formatOrder($order->load('customer', 'orderItems.product', 'payments', 'delivery', 'fulfillmentUpdatedBy'))
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'success' => false,
                'message' => 'Error updating order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel an order, restore inventory, and keep the record for audit history.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $order = Order::with('orderItems', 'delivery', 'payments')->findOrFail($id);

            if ($order->fulfillment_status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order is already cancelled.'
                ], 422);
            }

            if ($order->fulfillment_status !== 'pending' || $order->delivery_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only orders with no logistics progress can be cancelled.'
                ], 422);
            }

            $hasPaymentActivity = $order->payments->isNotEmpty()
                || in_array($order->payment_status, ['paid', 'partially_paid', 'utang'], true)
                || (float) $order->outstanding_balance < (float) $order->total_amount;

            if ($hasPaymentActivity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only orders with no payment activity can be cancelled.'
                ], 422);
            }

            DB::beginTransaction();

            foreach ($order->orderItems as $item) {
                $inventory = Inventory::where('product_id', $item->product_id)->firstOrFail();
                $this->restoreInventoryForCancelledOrder($inventory, (float) $item->quantity, $order->id);
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
        $formatted['actual_fulfillment_at'] = $order->actual_fulfillment_at?->toIso8601String();
        $formatted['fulfillment_action'] = $order->fulfillment_action;
        $formatted['fulfillment_updated_by'] = $order->fulfillmentUpdatedBy ? [
            'id' => $order->fulfillmentUpdatedBy->id,
            'name' => $order->fulfillmentUpdatedBy->name,
            'username' => $order->fulfillmentUpdatedBy->username,
        ] : null;
        $formatted['fulfillment_type'] = $order->fulfillment_type ?? 'delivery';
        $formatted['fulfillment_status'] = $order->fulfillment_status
            ?? $this->mapDeliveryStatusToFulfillmentStatus($order->delivery_status ?? 'pending');
        $formatted['order_status'] = $this->resolveOrderStatus($order);
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

    private function resolveOrderStatus(Order $order): string
    {
        if ($order->fulfillment_status === 'cancelled' || $order->delivery_status === 'cancelled') {
            return 'cancelled';
        }

        $isDelivered = $order->fulfillment_status === 'completed' || $order->delivery_status === 'delivered';
        $isPaid = $order->payment_status === 'paid' || (float) $order->outstanding_balance <= 0;

        return $isDelivered && $isPaid ? 'complete' : 'pending';
    }

    private function canFullyEditOrder(Order $order): bool
    {
        if ($this->isOrderLockedForEditing($order)) {
            return false;
        }

        $hasPaymentActivity = $order->payments->isNotEmpty()
            || in_array($order->payment_status, ['paid', 'partially_paid', 'utang'], true)
            || (float) $order->outstanding_balance < (float) $order->total_amount;

        $hasFulfillmentProgress = in_array($order->fulfillment_status, ['in_progress', 'completed', 'cancelled'], true)
            || in_array($order->delivery_status, ['processing', 'delivered', 'cancelled'], true);

        return ! $hasPaymentActivity && ! $hasFulfillmentProgress;
    }

    private function isOrderLockedForEditing(Order $order): bool
    {
        return $this->resolveOrderStatus($order) === 'complete'
            || in_array($order->fulfillment_status, ['completed', 'cancelled'], true)
            || in_array($order->delivery_status, ['delivered', 'cancelled'], true);
    }

    private function replaceEditableOrderItems(Order $order, array $items): void
    {
        $this->restoreSaleMovementsForOrderEdit($order);

        $order->orderItems()->delete();

        $totalAmount = 0;
        $orderType = $order->customer?->type ?? $order->order_type ?? 'retail';

        foreach ($items as $item) {
            $inventory = Inventory::with('product.pricing')
                ->where('product_id', $item['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $quantity = (float) $item['quantity'];

            if ($inventory->availableQuantity() < $quantity) {
                $productName = $inventory->product?->name ?? "Product ID {$item['product_id']}";
                throw new \Exception("Insufficient stock for {$productName}. Available: {$inventory->availableQuantity()}");
            }

            $resolvedUnitPrice = PricingMath::resolveOrderPrice($inventory->product, $orderType);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'unit_price' => $resolvedUnitPrice,
                'subtotal' => $quantity * $resolvedUnitPrice,
            ]);

            $this->deductInventoryForOrder($inventory, $quantity, $order->id);
            $totalAmount += $quantity * $resolvedUnitPrice;
        }

        $order->forceFill([
            'total_amount' => $totalAmount,
            'outstanding_balance' => $totalAmount,
        ])->save();
        $order->refresh();
    }

    private function restoreSaleMovementsForOrderEdit(Order $order): void
    {
        $saleMovements = StockMovement::query()
            ->where('type', 'stock_out')
            ->where('movement_type', 'sale')
            ->where('reference_id', $order->id)
            ->lockForUpdate()
            ->get();

        foreach ($saleMovements as $movement) {
            $inventory = Inventory::where('product_id', $movement->product_id)->lockForUpdate()->first();
            if (! $inventory) {
                $movement->update(['movement_type' => 'sale_reversed']);
                continue;
            }

            $restored = (float) $movement->quantity;

            if ($movement->source_stock_movement_id) {
                $batch = StockMovement::lockForUpdate()->find($movement->source_stock_movement_id);
                if ($batch) {
                    $batch->forceFill([
                        'remaining_quantity' => (float) ($batch->remaining_quantity ?? 0) + $restored,
                        'expired' => false,
                    ])->save();
                }

                $inventory->syncQuantityFromBatches();
            } else {
                $inventory->applyQuantityDelta($restored);
            }

            $inventory->stockMovements()->create([
                'type' => 'stock_in',
                'movement_type' => 'order_edit_restore',
                'quantity' => $restored,
                'reason' => 'Order edit stock restoration',
                'reference' => "ORDER-{$order->id}",
                'reference_id' => $order->id,
                'source_stock_movement_id' => $movement->source_stock_movement_id,
                'performed_by_user_id' => auth()->id(),
                'expiration_date' => $movement->expiration_date,
                'notes' => "Stock restored before editing order #{$order->id}",
            ]);

            $movement->update(['movement_type' => 'sale_reversed']);
        }
    }

    private function deductInventoryForOrder(
        Inventory $inventory,
        float $quantity,
        int $orderId,
        string $movementType = 'sale',
        string $reason = 'Customer order fulfilled'
    ): void
    {
        $startingQuantity = $inventory->availableQuantity();
        $remaining = $quantity;
        $batches = StockMovement::query()
            ->where('product_id', $inventory->product_id)
            ->where('type', 'stock_in')
            ->where('movement_type', 'purchase_receipt')
            ->where('remaining_quantity', '>', 0)
            ->orderByRaw('expiration_date IS NULL')
            ->orderBy('expiration_date')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $batch->remaining_quantity;
            $deducted = min($available, $remaining);

            $batch->forceFill(['remaining_quantity' => $available - $deducted])->save();

            $inventory->stockMovements()->create([
                'type' => 'stock_out',
                'movement_type' => $movementType,
                'quantity' => $deducted,
                'reason' => $reason,
                'reference' => "ORDER-{$orderId}",
                'reference_id' => $orderId,
                'source_stock_movement_id' => $batch->id,
                'performed_by_user_id' => auth()->id(),
                'expiration_date' => $batch->expiration_date,
                'notes' => $movementType === 'stock_out_pos'
                    ? "POS stock out for order #{$orderId}"
                    : "Stock deducted for order #{$orderId}",
            ]);

            $remaining -= $deducted;
        }

        if ($remaining > 0.005) {
            $nextQuantity = max(0, $startingQuantity - $quantity);
            $updates = ['quantity' => $nextQuantity];
            if (array_key_exists('quantity_on_hand', $inventory->getAttributes())) {
                $updates['quantity_on_hand'] = $nextQuantity;
            }
            $inventory->forceFill($updates)->save();
            $inventory->refresh();

            $inventory->stockMovements()->create([
                'type' => 'stock_out',
                'movement_type' => $movementType,
                'quantity' => $remaining,
                'reason' => $reason,
                'reference' => "ORDER-{$orderId}",
                'reference_id' => $orderId,
                'performed_by_user_id' => auth()->id(),
                'notes' => $movementType === 'stock_out_pos'
                    ? "POS stock out for order #{$orderId}"
                    : "Stock deducted for order #{$orderId}",
            ]);

            return;
        }

        $inventory->syncQuantityFromBatches();
    }

    private function generatePosPaymentReference(): string
    {
        do {
            $reference = 'POS-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (Payment::where('reference', $reference)->exists());

        return $reference;
    }

    private function restoreInventoryForCancelledOrder(Inventory $inventory, float $quantity, int $orderId): void
    {
        $saleMovements = StockMovement::query()
            ->where('product_id', $inventory->product_id)
            ->where('type', 'stock_out')
            ->where('movement_type', 'sale')
            ->where('reference_id', $orderId)
            ->whereNotNull('source_stock_movement_id')
            ->get();

        if ($saleMovements->isEmpty()) {
            $inventory->applyQuantityDelta($quantity);
            $inventory->stockMovements()->create([
                'type' => 'stock_in',
                'movement_type' => 'order_cancel',
                'quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'reason' => 'Cancelled order stock restoration',
                'reference' => "ORDER-{$orderId}",
                'reference_id' => $orderId,
                'performed_by_user_id' => auth()->id(),
                'notes' => "Stock restored for cancelled order #{$orderId}",
            ]);
            return;
        }

        foreach ($saleMovements as $movement) {
            $batch = StockMovement::lockForUpdate()->find($movement->source_stock_movement_id);
            if (!$batch) {
                continue;
            }

            $restored = (float) $movement->quantity;
            $batch->forceFill([
                'remaining_quantity' => (float) ($batch->remaining_quantity ?? 0) + $restored,
                'expired' => false,
            ])->save();

            $inventory->stockMovements()->create([
                'type' => 'stock_in',
                'movement_type' => 'order_cancel',
                'quantity' => $restored,
                'reason' => 'Cancelled order stock restoration',
                'reference' => "ORDER-{$orderId}",
                'reference_id' => $orderId,
                'source_stock_movement_id' => $batch->id,
                'performed_by_user_id' => auth()->id(),
                'expiration_date' => $batch->expiration_date,
                'notes' => "Stock restored for cancelled order #{$orderId}",
            ]);
        }

        $inventory->syncQuantityFromBatches();
    }
}
