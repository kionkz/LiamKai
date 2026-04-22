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
            'status' => ['sometimes', 'in:pending,in_progress,completed'],
            'sort_by' => ['sometimes', 'in:id,scheduled_for,created_at,total_amount,status'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $includeAll = filter_var($validated['include_all'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sortBy = match ($validated['sort_by'] ?? 'scheduled_for') {
            'status' => 'fulfillment_status',
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
            ->with('customer')
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
                'delivery_address' => $order->delivery_address,
                'scheduled_for'    => $order->scheduled_for?->toIso8601String(),
                'status'           => $order->fulfillment_status,
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
                'counts' => [
                    'total' => (clone $countsQuery)->count(),
                    'pending' => (clone $countsQuery)->where('fulfillment_status', 'pending')->count(),
                    'in_progress' => (clone $countsQuery)->where('fulfillment_status', 'in_progress')->count(),
                    'completed' => (clone $countsQuery)->where('fulfillment_status', 'completed')->count(),
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

        if ($order->fulfillment_status === 'pending' && $validated['status'] === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Move the order to en-route before marking it completed.',
            ], 422);
        }

        $order->update([
            'fulfillment_status' => $validated['status'],
            'delivery_status' => match ($validated['status']) {
                'in_progress' => 'processing',
                'completed' => 'delivered',
                default => 'pending',
            },
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Logistics status updated successfully.',
            'data' => [
                'id' => $order->id,
                'status' => $order->fulfillment_status,
                'delivery_status' => $order->delivery_status,
            ],
        ]);
    }
}
