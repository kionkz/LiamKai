<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesReport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        [$startDate, $endDate] = $this->resolveDateRange($request);

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
            ->with('product:id,name,base_price')
            ->get();

        $totalSkus = Product::query()->count();
        $lowStockItems = $inventoryRows
            ->filter(function ($item) {
                return (float) $item->quantity <= (float) $item->reorder_point;
            })
            ->map(function ($item) {
                return [
                    'product' => $item->product?->name ?? 'Unknown Product',
                    'current' => (float) $item->quantity,
                    'reorder' => (float) $item->reorder_point,
                ];
            })
            ->values();

        $totalInventoryValue = $inventoryRows->sum(function ($item) {
            $price = (float) ($item->product?->base_price ?? 0);
            return (float) $item->quantity * $price;
        });

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
                    'lowStockCount' => $lowStockItems->count(),
                    'totalInventoryValue' => (float) $totalInventoryValue,
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

    private function resolveDateRange(Request $request): array
    {
        $period = $request->query('period', 'month');

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
