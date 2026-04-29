<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrders\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderController extends Controller
{
    /**
     * Display all purchase orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = max(1, min((int) $request->input('per_page', 15), 100));
            $sortBy = (string) $request->input('sort_by', 'created_at');
            $sortDirection = strtolower((string) $request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

            $purchaseOrders = PurchaseOrder::with('supplier', 'purchaseOrderItems.product', 'payments.recordedBy')
                ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
                ->select('purchase_orders.*')
                ->when($sortBy === 'id', fn ($query) => $query->orderBy('purchase_orders.id', $sortDirection))
                ->when($sortBy === 'supplier', fn ($query) => $query->orderBy('suppliers.name', $sortDirection))
                ->when($sortBy === 'total_amount', fn ($query) => $query->orderBy('purchase_orders.total_amount', $sortDirection))
                ->when($sortBy === 'status', fn ($query) => $query->orderBy('purchase_orders.status', $sortDirection))
                ->when($sortBy === 'expected_delivery_date', fn ($query) => $query->orderByRaw('purchase_orders.expected_delivery_date IS NULL')->orderBy('purchase_orders.expected_delivery_date', $sortDirection))
                ->when(!in_array($sortBy, ['id', 'supplier', 'total_amount', 'status', 'expected_delivery_date'], true), fn ($query) => $query->orderBy('purchase_orders.created_at', $sortDirection))
                ->orderBy('purchase_orders.id', $sortDirection === 'asc' ? 'asc' : 'desc')
                ->paginate($perPage);
            
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
            $purchaseOrder = PurchaseOrder::with('supplier', 'purchaseOrderItems.product', 'payments.recordedBy')
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
                'status' => 'sometimes|required|in:pending,received,cancelled',
                'supplier_id' => 'sometimes|required|exists:suppliers,id',
                'order_date' => 'sometimes|date',
                'expected_delivery_date' => 'sometimes|date',
                'notes' => 'sometimes|nullable|string',
                'recipient_name' => 'sometimes|required_with:received_items|string|max:255',
                'damage_notes' => 'sometimes|nullable|string',
                'shortage_notes' => 'sometimes|nullable|string',
                'payment_method' => 'sometimes|required_with:received_items|in:cash,bank_transfer',
                'payment_reference' => 'required_if:payment_method,bank_transfer|nullable|string|max:255',
                'received_items' => 'sometimes|required|array|min:1',
                'received_items.*.purchase_order_item_id' => 'required_with:received_items|exists:purchase_order_items,id',
                'received_items.*.received_quantity' => 'nullable|numeric|min:0',
                'received_items.*.damaged_quantity' => 'nullable|numeric|min:0',
                'received_items.*.short_quantity' => 'nullable|numeric|min:0',
                'received_items.*.notes' => 'nullable|string|max:500',
                'received_items.*.instances' => 'nullable|array',
                'received_items.*.instances.*.quantity' => 'required_with:received_items.*.instances|numeric|min:0.01',
                'received_items.*.instances.*.expiration_date' => 'nullable|date',
                'items' => 'sometimes|required|array|min:1',
                'items.*.product_id' => 'required_with:items|exists:products,id',
                'items.*.quantity' => 'required_with:items|numeric|min:0.01',
                'items.*.unit_cost' => 'required_with:items|numeric|min:0',
            ]);
            
            $purchaseOrder = PurchaseOrder::with('purchaseOrderItems.product', 'supplier')->findOrFail($id);
            $role = $request->user()?->role;
            $isReceiving = !empty($validated['received_items']);
            $isEditingPurchaseOrder = !empty(array_intersect(
                array_keys($validated),
                ['status', 'supplier_id', 'order_date', 'expected_delivery_date', 'notes', 'items']
            ));

            if ($isReceiving && !in_array($role, ['admin', 'inventory'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You do not have permission to perform this action.',
                ], 403);
            }

            if (!$isReceiving && $isEditingPurchaseOrder && !in_array($role, ['admin', 'purchasing'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You do not have permission to perform this action.',
                ], 403);
            }

            if ($isReceiving && $isEditingPurchaseOrder && $role === 'inventory') {
                $nonReceivingFields = array_diff(
                    array_keys($validated),
                    ['recipient_name', 'damage_notes', 'shortage_notes', 'payment_method', 'payment_reference', 'received_items']
                );

                if (!empty($nonReceivingFields)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied. You do not have permission to perform this action.',
                    ], 403);
                }
            }

            // Block ALL modifications once a PO is fully received
            if ($purchaseOrder->status === 'received') {
                return response()->json([
                    'success' => false,
                    'message' => 'This purchase order has already been fully received and cannot be modified.',
                ], 422);
            }

            DB::beginTransaction();

            if (!empty($validated['received_items'])) {
                // Block receiving on cancelled orders
                if ($purchaseOrder->status === 'cancelled') {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Cancelled purchase orders cannot be received.',
                    ], 422);
                }

                $this->processReceipt($purchaseOrder, $validated);
            }

            $updates = [];

            if (array_key_exists('status', $validated) && empty($validated['received_items'])) {
                $currentStatus = $purchaseOrder->status;
                $newStatus     = $validated['status'];

                $allowedTransitions = [
                    'pending'            => ['received', 'cancelled'],
                    'received'           => [],
                    'cancelled'          => [],
                ];

                if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Invalid status transition: '{$currentStatus}' → '{$newStatus}'.",
                    ], 422);
                }

                $updates['status'] = $newStatus;
            }

            if (array_key_exists('expected_delivery_date', $validated)) {
                $updates['expected_delivery_date'] = $validated['expected_delivery_date'];
            }

            if (array_key_exists('supplier_id', $validated)) {
                $updates['supplier_id'] = $validated['supplier_id'];
            }

            if (array_key_exists('order_date', $validated)) {
                $updates['order_date'] = $validated['order_date'];
            }

            if (array_key_exists('notes', $validated) && empty($validated['received_items'])) {
                $updates['notes'] = $validated['notes'];
            }

            // Replace line items when sent from the edit form
            if (!empty($validated['items'])) {
                $purchaseOrder->purchaseOrderItems()->delete();

                $totalAmount   = 0;
                $totalQuantity = 0;

                foreach ($validated['items'] as $item) {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id'        => $item['product_id'],
                        'quantity'          => $item['quantity'],
                        'purchase_price'    => $item['unit_cost'],
                        'subtotal'          => $item['quantity'] * $item['unit_cost'],
                    ]);
                    $totalAmount   += $item['quantity'] * $item['unit_cost'];
                    $totalQuantity += $item['quantity'];
                }

                $updates['total_amount']      = $totalAmount;
                $updates['ordered_quantity']  = $totalQuantity;
                $updates['received_quantity'] = 0; // reset since items changed
            }

            if (!empty($updates)) {
                $purchaseOrder->update($updates);
            }

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Purchase order updated successfully',
                'data' => $purchaseOrder->fresh(['supplier', 'purchaseOrderItems.product', 'payments.recordedBy'])
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

    private function processReceipt(PurchaseOrder $purchaseOrder, array $validated): void
    {
        // Guards are already enforced in update() before this method is called.

        $itemsById = $purchaseOrder->purchaseOrderItems->keyBy('id');

        foreach ($validated['received_items'] as $receivedItem) {
            $item = $itemsById->get($receivedItem['purchase_order_item_id']);

            if (!$item) {
                continue;
            }

            $acceptedQuantity = (float) ($receivedItem['received_quantity'] ?? 0);
            $instances = $receivedItem['instances'] ?? [];

            // If instances are provided but received_quantity is not, derive from instance sum.
            // If received_quantity is provided, use it (instances are just the expiration breakdown).
            if (empty($receivedItem['received_quantity']) && !empty($instances)) {
                $acceptedQuantity = array_sum(array_column($instances, 'quantity'));
            }

            $damagedQuantity = (float) ($receivedItem['damaged_quantity'] ?? 0);
            $shortQuantity = (float) ($receivedItem['short_quantity'] ?? 0);
            // Since partial receiving is removed, cap is the full ordered quantity per item.
            $orderedQty = (float) $item->quantity;

            // Strict check: accepted + damaged + short must not exceed ordered quantity.
            if (($acceptedQuantity + $damagedQuantity + $shortQuantity) > ($orderedQty + 0.005)) {
                abort(response()->json([
                    'success' => false,
                    'message' => "Receipt quantities exceed the ordered amount for {$item->product?->name}. Ordered: {$orderedQty} kg.",
                ], 422));
            }

            // Individual field guards
            if ($acceptedQuantity < 0 || $damagedQuantity < 0 || $shortQuantity < 0) {
                abort(response()->json([
                    'success' => false,
                    'message' => "Quantities cannot be negative for {$item->product?->name}.",
                ], 422));
            }

            if (($acceptedQuantity + $damagedQuantity + $shortQuantity) <= 0) {
                continue;
            }

            $inventory = Inventory::firstOrCreate(
                ['product_id' => $item->product_id],
                [
                    'quantity' => 0,
                    'quantity_on_hand' => 0,
                    'reorder_point' => config('operations.inventory.default_reorder_point', 5),
                    'status' => 'available',
                ]
            );

            if ($acceptedQuantity > 0) {
                // If instances with expiration dates are provided, create one StockMovement per batch.
                if (!empty($instances)) {
                    foreach ($instances as $batch) {
                        $batchQty = (float) $batch['quantity'];
                        if ($batchQty <= 0) continue;

                        $inventory->applyQuantityDelta($batchQty);

                        StockMovement::create([
                            'product_id'      => $item->product_id,
                            'quantity'         => $batchQty,
                            'remaining_quantity' => $batchQty,
                            'type'            => 'stock_in',
                            'movement_type'   => 'purchase_receipt',
                            'reason'          => 'Purchase order receipt',
                            'reference'       => $purchaseOrder->order_number,
                            'reference_id'    => $purchaseOrder->id,
                            'performed_by_user_id' => auth()->id(),
                            'expiration_date' => $batch['expiration_date'] ?? null,
                            'notes'           => trim("Accepted by {$validated['recipient_name']}" . (!empty($receivedItem['notes']) ? " | {$receivedItem['notes']}" : '')),
                        ]);
                    }
                } else {
                    // Legacy: single entry with no expiration date.
                    $inventory->applyQuantityDelta($acceptedQuantity);

                    StockMovement::create([
                        'product_id'    => $item->product_id,
                        'quantity'       => $acceptedQuantity,
                        'remaining_quantity' => $acceptedQuantity,
                        'type'          => 'stock_in',
                        'movement_type' => 'purchase_receipt',
                        'reason'        => 'Purchase order receipt',
                        'reference'     => $purchaseOrder->order_number,
                        'reference_id'  => $purchaseOrder->id,
                        'performed_by_user_id' => auth()->id(),
                        'notes'         => trim("Accepted by {$validated['recipient_name']}" . (!empty($receivedItem['notes']) ? " | {$receivedItem['notes']}" : '')),
                    ]);
                }
            }

            if ($damagedQuantity > 0) {
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'quantity' => $damagedQuantity,
                    'type' => 'adjustment',
                    'movement_type' => 'defect',
                    'reason' => 'Damaged on receiving',
                    'reference' => $purchaseOrder->order_number,
                    'reference_id' => $purchaseOrder->id,
                    'performed_by_user_id' => auth()->id(),
                    'notes' => trim(($validated['damage_notes'] ?? 'Damaged quantity recorded during receiving') . (!empty($receivedItem['notes']) ? " | {$receivedItem['notes']}" : '')),
                ]);
            }

            if ($shortQuantity > 0) {
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'quantity' => $shortQuantity,
                    'type' => 'adjustment',
                    'movement_type' => 'shortage',
                    'reason' => 'Supplier short shipment',
                    'reference' => $purchaseOrder->order_number,
                    'reference_id' => $purchaseOrder->id,
                    'performed_by_user_id' => auth()->id(),
                    'notes' => $validated['shortage_notes'] ?? 'Short quantity recorded during receiving',
                ]);
            }

            $receivedForItem = $acceptedQuantity + $damagedQuantity;
            // SET (not accumulate) since partial receiving is no longer supported.
            $item->update([
                'received_quantity' => $receivedForItem,
            ]);
        }

        $purchaseOrder->refresh()->load('purchaseOrderItems.product');

        $receivedQuantity = (float) $purchaseOrder->purchaseOrderItems->sum('received_quantity');
        $orderedQuantity = (float) $purchaseOrder->purchaseOrderItems->sum('quantity');

        $status = 'pending';
        if ($receivedQuantity >= $orderedQuantity && $orderedQuantity > 0) {
            $status = 'received';
        }

        $receiptNotes = array_filter([
            $validated['notes'] ?? null,
            !empty($validated['damage_notes']) ? 'Damage: ' . $validated['damage_notes'] : null,
            !empty($validated['shortage_notes']) ? 'Shortage: ' . $validated['shortage_notes'] : null,
        ]);

        $purchaseOrder->update([
            'received_quantity' => $receivedQuantity,
            'actual_delivery_date' => now()->toDateString(),
            'status' => $status,
            'received_by' => $validated['recipient_name'] ?? $purchaseOrder->received_by,
            'notes' => $receiptNotes ? implode(' | ', $receiptNotes) : $purchaseOrder->notes,
        ]);

        $this->recordReceivingPayment($purchaseOrder->fresh(), $validated);
    }

    private function recordReceivingPayment(PurchaseOrder $purchaseOrder, array $validated): void
    {
        $method = $validated['payment_method'] ?? null;
        if (!$method) {
            $purchaseOrder->update(['payment_status' => 'pending']);
            return;
        }

        $existingPayment = Payment::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->where('status', 'completed')
            ->first();

        if ($existingPayment) {
            $purchaseOrder->update(['payment_status' => 'paid']);
            return;
        }

        Payment::create([
            'purchase_order_id' => $purchaseOrder->id,
            'recorded_by_user_id' => auth()->id(),
            'amount' => $purchaseOrder->total_amount,
            'payment_method' => $method,
            'reference' => $method === 'cash'
                ? $this->generatePurchasePaymentReference($purchaseOrder)
                : $validated['payment_reference'],
            'payment_date' => $purchaseOrder->actual_delivery_date?->toDateString() ?? now()->toDateString(),
            'status' => 'completed',
            'notes' => "Quick payment recorded during receiving for {$purchaseOrder->order_number}",
        ]);

        $purchaseOrder->update(['payment_status' => 'paid']);
    }

    private function generatePurchasePaymentReference(PurchaseOrder $purchaseOrder): string
    {
        do {
            $reference = sprintf('POPAY-%s-%s', now()->format('Ymd'), Str::upper(Str::random(5)));
        } while (Payment::where('reference', $reference)->exists());

        return $reference;
    }
}
