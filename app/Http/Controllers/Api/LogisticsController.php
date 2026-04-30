<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogisticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->has('include_all')) {
            $request->merge([
                'include_all' => filter_var($request->query('include_all'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }

        $validated = $request->validate([
            'date' => ['sometimes', 'date'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'include_all' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:255'],
            'fulfillment_type' => ['sometimes', 'in:delivery,pickup'],
            'status' => ['sometimes', 'in:pending,in_progress,completed'],
            'priority' => ['sometimes', 'in:all,regular,urgent'],
            'sort_by' => ['sometimes', 'in:id,scheduled_for,created_at,total_amount,status,priority'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $includeAll = filter_var($validated['include_all'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sortBy = match ($validated['sort_by'] ?? 'scheduled_for') {
            'status' => 'fulfillment_status',
            'priority' => 'order_priority',
            default => $validated['sort_by'] ?? 'scheduled_for',
        };
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $selectedDate = array_key_exists('date', $validated)
            ? Carbon::parse($validated['date'])->toDateString()
            : now()->toDateString();
        $dateFrom = !empty($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : null;
        $dateTo = !empty($validated['date_to']) ? Carbon::parse($validated['date_to'])->endOfDay() : null;

        $baseQuery = Order::query()
            ->whereIn('fulfillment_type', ['delivery', 'pickup'])
            ->where('fulfillment_status', '!=', 'cancelled');

        if (!empty($validated['fulfillment_type'])) {
            $baseQuery->where('fulfillment_type', $validated['fulfillment_type']);
        }

        if (!empty($validated['priority']) && $validated['priority'] !== 'all') {
            $baseQuery->where('order_priority', $validated['priority']);
        }

        if (!$includeAll) {
            if ($dateFrom && $dateTo) {
                $baseQuery->where(function ($q) use ($dateFrom, $dateTo) {
                    $q->whereBetween('scheduled_for', [$dateFrom, $dateTo])
                        ->orWhere(function ($q2) use ($dateFrom, $dateTo) {
                            $q2->whereNull('scheduled_for')
                                ->whereBetween('created_at', [$dateFrom, $dateTo]);
                        });
                });
            } else {
                $baseQuery->where(function ($q) use ($selectedDate) {
                    $q->whereDate('scheduled_for', $selectedDate)
                        ->orWhere(function ($q2) use ($selectedDate) {
                            $q2->whereNull('scheduled_for')
                                ->whereDate('created_at', $selectedDate);
                        });
                });
            }
        }

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $baseQuery->where(function ($query) use ($search) {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $countsQuery = clone $baseQuery;

        if (!empty($validated['status'])) {
            $baseQuery->where('fulfillment_status', $validated['status']);
        }

        $orders = (clone $baseQuery)
            ->with('customer', 'fulfillmentUpdatedBy')
            ->orderByRaw("CASE WHEN order_priority = 'urgent' AND fulfillment_status != 'completed' THEN 0 ELSE 1 END")
            ->orderByRaw('scheduled_for IS NULL')
            ->orderBy($sortBy, $sortDirection)
            ->orderBy('id', $sortDirection)
            ->paginate($validated['per_page'] ?? 25);

        $items = $orders->getCollection()->map(function (Order $order) {
            return [
                'id'               => $order->id,
                'order_id'         => $order->id,
                'customer_name'    => $order->customer?->name ?? 'Walk-In Customer',
                'fulfillment_type' => $order->fulfillment_type,
                'order_priority'   => $order->order_priority ?? 'regular',
                'is_urgent'        => ($order->order_priority ?? 'regular') === 'urgent',
                'delivery_address' => $order->delivery_address,
                'scheduled_for'    => $order->scheduled_for?->toIso8601String(),
                'actual_fulfillment_at' => $order->actual_fulfillment_at?->toIso8601String(),
                'status'           => $order->fulfillment_status,
                'fulfillment_action' => $order->fulfillment_action,
                'fulfillment_updated_by' => $order->fulfillmentUpdatedBy ? [
                    'id' => $order->fulfillmentUpdatedBy->id,
                    'name' => $order->fulfillmentUpdatedBy->name,
                    'username' => $order->fulfillmentUpdatedBy->username,
                ] : null,
                'order_date'       => $order->created_at?->toDateString(),
                'total_amount'     => (float) $order->total_amount,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'selected_date' => $selectedDate,
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'include_all' => $includeAll,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
                'priority' => $validated['priority'] ?? 'all',
                'counts' => [
                    'total' => (clone $countsQuery)->count(),
                    'pending' => (clone $countsQuery)->where('fulfillment_status', 'pending')->count(),
                    'in_progress' => (clone $countsQuery)->where('fulfillment_status', 'in_progress')->count(),
                    'completed' => (clone $countsQuery)->where('fulfillment_status', 'completed')->count(),
                    'urgent' => (clone $countsQuery)->where('order_priority', 'urgent')->where('fulfillment_status', '!=', 'completed')->count(),
                ],
            ],
            'pagination' => [
                'total' => $orders->total(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
            ],
        ]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,completed'],
            'actual_fulfillment_at' => ['sometimes', 'nullable', 'date'],
        ]);

        if ($order->fulfillment_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cancelled orders cannot be updated from logistics.',
            ], 422);
        }

        if ($order->fulfillment_status === 'completed' && $validated['status'] !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Completed logistics orders cannot be moved back to an earlier status.',
            ], 422);
        }

        if (
            $order->fulfillment_type !== 'pickup'
            && $order->fulfillment_status === 'pending'
            && $validated['status'] === 'completed'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Move the order to en-route before marking it completed.',
            ], 422);
        }

        $completedAt = $validated['status'] === 'completed'
            ? Carbon::parse($validated['actual_fulfillment_at'] ?? now())
            : null;
        $action = $this->resolveFulfillmentAction($order->fulfillment_type, $validated['status']);

        $order->update([
            'fulfillment_status' => $validated['status'],
            'delivery_status' => match ($validated['status']) {
                'in_progress' => 'processing',
                'completed' => 'delivered',
                default => 'pending',
            },
            'delivery_date' => $completedAt?->toDateString(),
            'actual_fulfillment_at' => $completedAt,
            'fulfillment_action' => $action,
            'fulfillment_updated_by_user_id' => $request->user()?->id,
        ]);

        $order->load('fulfillmentUpdatedBy');

        return response()->json([
            'success' => true,
            'message' => 'Logistics status updated successfully.',
            'data' => [
                'id' => $order->id,
                'status' => $order->fulfillment_status,
                'delivery_status' => $order->delivery_status,
                'delivery_date' => $order->delivery_date?->toDateString(),
                'actual_fulfillment_at' => $order->actual_fulfillment_at?->toIso8601String(),
                'fulfillment_action' => $order->fulfillment_action,
                'fulfillment_updated_by' => $order->fulfillmentUpdatedBy ? [
                    'id' => $order->fulfillmentUpdatedBy->id,
                    'name' => $order->fulfillmentUpdatedBy->name,
                    'username' => $order->fulfillmentUpdatedBy->username,
                ] : null,
            ],
        ]);
    }

    private function resolveFulfillmentAction(string $fulfillmentType, string $status): string
    {
        if ($status === 'completed') {
            return $fulfillmentType === 'pickup' ? 'marked picked up' : 'marked delivered';
        }

        if ($status === 'in_progress') {
            return $fulfillmentType === 'pickup' ? 'marked ready for pickup' : 'marked en-route';
        }

        return 'marked pending';
    }
}
