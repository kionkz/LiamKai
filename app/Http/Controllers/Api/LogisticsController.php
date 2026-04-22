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
        $validated = $request->validate([
            'date' => ['sometimes', 'date'],
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:pending,in_progress,completed'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $selectedDate = Carbon::parse($validated['date'] ?? now())->toDateString();

        $baseQuery = Order::query()
            ->whereIn('fulfillment_type', ['delivery', 'pickup'])
            ->where('fulfillment_status', '!=', 'cancelled')
            ->where(function ($q) use ($selectedDate) {
                $q->whereDate('scheduled_for', $selectedDate)
                  ->orWhere(function ($q2) use ($selectedDate) {
                      $q2->whereNull('scheduled_for')
                         ->whereDate('created_at', $selectedDate);
                  });
            });

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $baseQuery->where(function ($query) use ($search) {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($validated['status'])) {
            $baseQuery->where('fulfillment_status', $validated['status']);
        }

        $orders = (clone $baseQuery)
            ->with('customer')
            ->orderBy('scheduled_for')
            ->orderBy('id')
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
                'counts' => [
                    'total' => (clone $baseQuery)->count(),
                    'pending' => (clone $baseQuery)->where('fulfillment_status', 'pending')->count(),
                    'in_progress' => (clone $baseQuery)->where('fulfillment_status', 'in_progress')->count(),
                    'completed' => (clone $baseQuery)->where('fulfillment_status', 'completed')->count(),
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
            ],
        ]);
    }
}