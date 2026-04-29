<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesReport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    public function generateSalesReport(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $orders = Order::query()
            ->with(['orderItems', 'customer'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $saved = 0;

        foreach ($orders as $order) {
            $quantitySold = (float) $order->orderItems->sum('quantity');
            $totalSales = (float) $order->total_amount;
            $outstanding = (float) $order->outstanding_balance;
            $totalPaid = max($totalSales - $outstanding, 0);

            SalesReport::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'total_sales' => $totalSales,
                    'quantity_sold' => $quantitySold,
                    'sale_date' => $order->created_at->toDateString(),
                    'customer_type' => $order->customer?->type ?? 'retail',
                    'total_paid' => $totalPaid,
                    'outstanding' => $outstanding,
                    'notes' => sprintf(
                        'Generated for %s (%s to %s)',
                        $request->query('period', 'month'),
                        $startDate->toDateString(),
                        $endDate->toDateString()
                    ),
                ]
            );

            $saved++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Sales report data saved successfully',
            'data' => [
                'saved_rows' => $saved,
                'from_date' => $startDate->toDateString(),
                'to_date' => $endDate->toDateString(),
            ],
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $reportType = (string) $request->query('report_type', 'sales');

        if ($request->user()?->role === 'purchasing' && $reportType !== 'inventory') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You do not have permission to perform this action.',
            ], 403);
        }

        [$startDate, $endDate] = $this->resolveDateRange($request);
        $inventoryQuantityColumn = Schema::hasColumn('inventory', 'quantity_on_hand') ? 'quantity_on_hand' : 'quantity';

        $salesSummary = Order::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalSales = (float) $salesSummary->sum('total_amount');
        $totalOrders = (int) $salesSummary->count();
        $avgOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

        $retailSales = (float) Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('order_type', 'retail')
            ->sum('total_amount');

        $wholesaleSales = (float) Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('order_type', 'wholesale')
            ->sum('total_amount');

        $retailPercentage = $totalSales > 0 ? round(($retailSales / $totalSales) * 100, 1) : 0;
        $wholesalePercentage = $totalSales > 0 ? round(($wholesaleSales / $totalSales) * 100, 1) : 0;

        $salesBreakdown = Order::query()
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw("SUM(CASE WHEN order_type = 'retail' THEN total_amount ELSE 0 END) as retail_sales")
            ->selectRaw("SUM(CASE WHEN order_type = 'wholesale' THEN total_amount ELSE 0 END) as wholesale_sales")
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('day')
            ->orderBy('day', 'desc')
            ->get()
            ->map(function ($row) {
                return [
                    'dateValue' => Carbon::parse($row->day)->toDateString(),
                    'date' => Carbon::parse($row->day)->format('M d'),
                    'orders' => (int) $row->orders,
                    'retail' => (float) $row->retail_sales,
                    'wholesale' => (float) $row->wholesale_sales,
                ];
            })
            ->values();

        $paymentsBase = Payment::query()
            ->whereNotNull('order_id')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalCollected = (float) $paymentsBase->sum('amount');
        $totalOutstanding = (float) Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('outstanding_balance');

        $collectionRate = ($totalCollected + $totalOutstanding) > 0
            ? round(($totalCollected / ($totalCollected + $totalOutstanding)) * 100, 1)
            : 0;

        $paymentMethods = Payment::query()
            ->select('payment_method')
            ->selectRaw('COUNT(*) as transactions')
            ->selectRaw('SUM(amount) as amount')
            ->whereNotNull('order_id')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('payment_method')
            ->orderByDesc('amount')
            ->get()
            ->map(function ($row) use ($totalCollected) {
                $amount = (float) $row->amount;
                return [
                    'method' => ucwords(str_replace('_', ' ', $row->payment_method)),
                    'transactions' => (int) $row->transactions,
                    'amount' => $amount,
                    'percentage' => $totalCollected > 0 ? round(($amount / $totalCollected) * 100, 1) : 0,
                ];
            })
            ->values();

        $inventoryRows = Inventory::query()
            ->with('product')
            ->get();

        $totalSkus = Product::query()->count();
        $lowStockItems = $inventoryRows
            ->filter(function ($item) {
                $quantity = (float) ($item->quantity_on_hand ?? $item->quantity ?? 0);
                return $quantity <= (float) $item->reorder_point;
            })
            ->map(function ($item) {
                return [
                    'product' => $item->product?->name ?? 'Unknown Product',
                    'current' => (float) ($item->quantity_on_hand ?? $item->quantity ?? 0),
                    'reorder' => (float) $item->reorder_point,
                ];
            })
            ->values();

        $totalInventoryValue = $inventoryRows->sum(function ($item) {
            $price = (float) ($item->product?->base_price ?? 0);
            return (float) ($item->quantity_on_hand ?? $item->quantity ?? 0) * $price;
        });

        $inventoryItems = $inventoryRows
            ->map(function ($item) {
                $quantityOnHand = (float) ($item->quantity_on_hand ?? $item->quantity ?? 0);
                $unitCost = (float) ($item->product?->base_price ?? 0);

                return [
                    'sku' => $item->product?->sku,
                    'name' => $item->product?->name ?? 'Unknown Product',
                    'description' => $item->product?->description ?? '',
                    'quantityOnHand' => $quantityOnHand,
                    'unitCost' => $unitCost,
                    'totalInventoryValue' => $quantityOnHand * $unitCost,
                    'reorderPoint' => (float) $item->reorder_point,
                ];
            })
            ->values();

        $topCustomers = Customer::query()
            ->leftJoin('orders', 'orders.customer_id', '=', 'customers.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('customers.id', 'customers.name', 'customers.type')
            ->select('customers.name', 'customers.type')
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->selectRaw('COALESCE(SUM(orders.total_amount), 0) as total_spent')
            ->selectRaw('MAX(orders.created_at) as last_order_date')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                return [
                    'name' => $row->name,
                    'type' => $row->type,
                    'orders' => (int) $row->orders_count,
                    'spent' => (float) $row->total_spent,
                    'lastOrderDate' => $row->last_order_date
                        ? Carbon::parse($row->last_order_date)->toDateString()
                        : null,
                    'lastOrder' => $row->last_order_date
                        ? Carbon::parse($row->last_order_date)->format('M d')
                        : '--',
                ];
            })
            ->values();

        $totalCustomers = Customer::query()->count();
        $newCustomers = Customer::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $customerOrderCounts = Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('customer_id', DB::raw('COUNT(*) as order_count'))
            ->groupBy('customer_id')
            ->get();

        $repeatCustomers = $customerOrderCounts->filter(fn ($row) => (int) $row->order_count > 1)->count();
        $customersWithOrders = $customerOrderCounts->count();
        $repeatRate = $customersWithOrders > 0
            ? round(($repeatCustomers / $customersWithOrders) * 100)
            : 0;

        $avgCustomerValue = $totalCustomers > 0 ? $totalSales / $totalCustomers : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'meta' => [
                    'period' => $request->query('period', 'month'),
                    'from_date' => $startDate->toDateString(),
                    'to_date' => $endDate->toDateString(),
                ],
                'sales' => [
                    'totalSales' => $totalSales,
                    'totalOrders' => $totalOrders,
                    'avgOrderValue' => $avgOrderValue,
                    'retailPercentage' => $retailPercentage,
                    'wholesalePercentage' => $wholesalePercentage,
                    'dailyBreakdown' => $salesBreakdown,
                ],
                'payments' => [
                    'totalCollected' => $totalCollected,
                    'totalOutstanding' => $totalOutstanding,
                    'collectionRate' => $collectionRate,
                    'methods' => $paymentMethods,
                ],
                'inventory' => [
                    'totalSKUs' => $totalSkus,
                    'currentStockQuantity' => (float) $inventoryRows->sum($inventoryQuantityColumn),
                    'lowStockCount' => $lowStockItems->count(),
                    'totalInventoryValue' => (float) $totalInventoryValue,
                    'items' => $inventoryItems,
                    'lowStockItems' => $lowStockItems,
                ],
                'customers' => [
                    'totalCustomers' => $totalCustomers,
                    'newCustomers' => $newCustomers,
                    'repeatRate' => $repeatRate,
                    'avgCustomerValue' => $avgCustomerValue,
                    'topCustomers' => $topCustomers,
                ],
            ],
        ]);
    }

    public function dashboardSummary(): JsonResponse
    {
        if (request()->user()?->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You do not have permission to perform this action.',
            ], 403);
        }

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $weekStart = now()->startOfWeek()->startOfDay();
        $monthStart = now()->startOfMonth()->startOfDay();
        $inventoryQuantityColumn = Schema::hasColumn('inventory', 'quantity_on_hand') ? 'quantity_on_hand' : 'quantity';

        $activeOrdersQuery = fn () => Order::query()
            ->where(function ($query) {
                $query->whereNull('fulfillment_status')
                    ->orWhere('fulfillment_status', '!=', 'cancelled');
            })
            ->where(function ($query) {
                $query->whereNull('delivery_status')
                    ->orWhere('delivery_status', '!=', 'cancelled');
            });

        $inventoryRows = Inventory::with('product:id,name,base_price')->get();
        $recentOrders = $activeOrdersQuery()
            ->with('customer')
            ->latest()
            ->limit(6)
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'order_number' => 'ORD-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
                    'customer' => $order->customer ? [
                        'id' => $order->customer->id,
                        'name' => $order->customer->name,
                    ] : null,
                    'order_type' => $order->order_type,
                    'total_amount' => (float) $order->total_amount,
                    'status' => $order->payment_status ?: $order->fulfillment_status,
                    'created_at' => optional($order->created_at)->toISOString(),
                ];
            })
            ->values();

        $topCustomers = Customer::select('customers.id', 'customers.name')
            ->selectRaw('COUNT(orders.id) as order_count, SUM(orders.total_amount) as total_spent')
            ->leftJoin('orders', 'customers.id', '=', 'orders.customer_id')
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'order_count' => (int) $c->order_count,
                'total_spent' => (float) $c->total_spent,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'headline' => [
                    'todaysSales' => (float) $activeOrdersQuery()->whereBetween('created_at', [$todayStart, $todayEnd])->sum('total_amount'),
                    'ordersToday' => (int) $activeOrdersQuery()->whereBetween('created_at', [$todayStart, $todayEnd])->count(),
                    'weekRevenue' => (float) $activeOrdersQuery()->whereBetween('created_at', [$weekStart, now()])->sum('total_amount'),
                    'monthRevenue' => (float) $activeOrdersQuery()->whereBetween('created_at', [$monthStart, now()])->sum('total_amount'),
                    'outstanding' => (float) $activeOrdersQuery()->sum('outstanding_balance'),
                ],
                'operations' => [
                    'pendingDeliveries' => (int) $activeOrdersQuery()->whereIn('fulfillment_type', ['delivery', 'pickup'])->where('fulfillment_status', 'pending')->count(),
                    'enRouteDeliveries' => (int) $activeOrdersQuery()->where('fulfillment_status', 'in_progress')->count(),
                    'openPurchaseOrders' => (int) PurchaseOrder::whereIn('status', ['pending', 'partially_received'])->count(),
                    'receivedToday' => (int) PurchaseOrder::whereDate('actual_delivery_date', today())->count(),
                ],
                'inventory' => [
                    'totalSkus' => (int) Product::count(),
                    'currentStockQuantity' => (float) $inventoryRows->sum($inventoryQuantityColumn),
                    'lowStockCount' => (int) $inventoryRows->filter(function ($item) {
                        return (float) ($item->quantity_on_hand ?? $item->quantity ?? 0) <= (float) $item->reorder_point;
                    })->count(),
                    'inventoryValue' => (float) $inventoryRows->sum(function ($item) {
                        return (float) ($item->quantity_on_hand ?? $item->quantity ?? 0) * (float) ($item->product?->base_price ?? 0);
                    }),
                    'lowStockItems' => $inventoryRows->filter(function ($item) {
                        return (float) ($item->quantity_on_hand ?? $item->quantity ?? 0) <= (float) $item->reorder_point;
                    })->sortBy(function ($item) {
                        return (float) ($item->quantity_on_hand ?? $item->quantity ?? 0) - (float) $item->reorder_point;
                    })->take(5)->map(function ($item) {
                        return [
                            'product' => $item->product?->name ?? 'Unknown Product',
                            'current' => (float) ($item->quantity_on_hand ?? $item->quantity ?? 0),
                            'reorder' => (float) $item->reorder_point,
                        ];
                    })->values(),
                ],
                'recentOrders' => $recentOrders,
                'customers' => [
                    'total'       => (int) Customer::count(),
                    'newToday'    => (int) Customer::whereBetween('created_at', [$todayStart, $todayEnd])->count(),
                    'newThisWeek' => (int) Customer::whereBetween('created_at', [$weekStart, now()])->count(),
                    'topCustomers' => $topCustomers,
                ],
            ],
        ]);
    }

    private function resolveDateRange(Request $request): array
    {
        $period = $request->query('period', 'month');

        if ($period === 'all') {
            $firstOrderDate = Order::query()->min('created_at');
            $start = $firstOrderDate ? Carbon::parse($firstOrderDate)->startOfDay() : now()->startOfMonth()->startOfDay();

            return [$start, now()->endOfDay()];
        }

        if ($period === 'today') {
            return [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()];
        }

        if ($period === 'week') {
            return [now()->startOfWeek()->startOfDay(), now()->endOfWeek()->endOfDay()];
        }

        if ($period === 'custom') {
            $fromDate = $request->query('from_date');
            $toDate = $request->query('to_date');

            if ($fromDate && $toDate) {
                return [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()];
            }
        }

        return [now()->startOfMonth()->startOfDay(), now()->endOfMonth()->endOfDay()];
    }
}
