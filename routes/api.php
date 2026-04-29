<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

// Import all API Controllers
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LogisticsController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\LoginAuditLogController;

// === AUTH ROUTES ===
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/profile', [AuthController::class, 'profile'])->middleware('auth:sanctum');
Route::put('/profile', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum');
Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// === PRODUCTS API ===
Route::prefix('categories')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->middleware('role:admin,inventory,purchasing')->name('categories.index');
    Route::post('/', [CategoryController::class, 'store'])->middleware('role:admin')->name('categories.store');
    Route::put('{category}', [CategoryController::class, 'update'])->middleware('role:admin')->name('categories.update');
    Route::patch('{category}', [CategoryController::class, 'update'])->middleware('role:admin')->name('categories.update.patch');
    Route::delete('{category}', [CategoryController::class, 'destroy'])->middleware('role:admin')->name('categories.destroy');
});

// === PRODUCTS API ===
Route::prefix('products')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [ProductController::class, 'index'])->middleware('role:admin,inventory,purchasing,sales')->name('products.index');
    Route::get('{product}', [ProductController::class, 'show'])->middleware('role:admin,inventory,purchasing,sales')->name('products.show');
    Route::post('/', [ProductController::class, 'store'])->middleware('role:admin')->name('products.store');
    Route::put('{product}', [ProductController::class, 'update'])->middleware('role:admin')->name('products.update');
    Route::patch('{product}', [ProductController::class, 'update'])->middleware('role:admin')->name('products.update.patch');
    Route::delete('{product}', [ProductController::class, 'destroy'])->middleware('role:admin')->name('products.destroy');
});

// === CUSTOMERS API ===
Route::prefix('customers')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [CustomerController::class, 'index'])->middleware('role:admin,sales')->name('customers.index');
    Route::get('{customer}', [CustomerController::class, 'show'])->middleware('role:admin,sales')->name('customers.show');
    Route::post('/', [CustomerController::class, 'store'])->middleware('role:admin')->name('customers.store');
    Route::put('{customer}', [CustomerController::class, 'update'])->middleware('role:admin')->name('customers.update');
    Route::patch('{customer}', [CustomerController::class, 'update'])->middleware('role:admin')->name('customers.update.patch');
    Route::delete('{customer}', [CustomerController::class, 'destroy'])->middleware('role:admin')->name('customers.destroy');
});

// === SUPPLIERS API ===
Route::prefix('suppliers')->middleware(['auth:sanctum', 'role:admin,purchasing'])->group(function () {
    Route::get('/', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
    Route::post('/', [SupplierController::class, 'store'])->middleware('role:admin')->name('suppliers.store');
    Route::put('{supplier}', [SupplierController::class, 'update'])->middleware('role:admin')->name('suppliers.update');
    Route::patch('{supplier}', [SupplierController::class, 'update'])->middleware('role:admin')->name('suppliers.update.patch');
    Route::delete('{supplier}', [SupplierController::class, 'destroy'])->middleware('role:admin')->name('suppliers.destroy');
});

// === INVENTORY API ===
Route::prefix('inventory')->middleware(['auth:sanctum', 'role:admin,inventory,purchasing'])->group(function () {
    Route::get('/', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('movements', [InventoryController::class, 'movements'])->name('inventory.movements');
    Route::get('low-stock', [InventoryController::class, 'showLowStock'])->name('inventory.low-stock');
    Route::get('{product}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::put('{product}', [InventoryController::class, 'update'])->middleware('role:admin,inventory')->name('inventory.update');
    Route::patch('{product}', [InventoryController::class, 'update'])->middleware('role:admin,inventory')->name('inventory.update.patch');
});

// Logistics routes — defined BEFORE orders/{order} wildcard to prevent route shadowing
Route::middleware(['auth:sanctum', 'role:admin,delivery'])->group(function () {
    Route::get('/orders/logistics', [LogisticsController::class, 'index'])->name('orders.logistics.index');
    Route::patch('/orders/{order}/fulfillment-status', [LogisticsController::class, 'updateStatus'])->name('orders.logistics.status');
});

// === ORDERS API ===
Route::prefix('orders')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [OrderController::class, 'index'])->middleware('role:admin,sales')->name('orders.index');
    Route::get('{order}', [OrderController::class, 'show'])->middleware('role:admin,sales,delivery')->name('orders.show');
    Route::post('/', [OrderController::class, 'store'])->middleware('role:admin,sales')->name('orders.store');
    Route::post('pos', [OrderController::class, 'storePosTransaction'])->middleware('role:admin,sales')->name('orders.pos.store');
    Route::put('{order}', [OrderController::class, 'update'])->middleware('role:admin,sales')->name('orders.update');
    Route::patch('{order}', [OrderController::class, 'update'])->middleware('role:admin,sales')->name('orders.update.patch');
    Route::delete('{order}', [OrderController::class, 'destroy'])->middleware('role:admin')->name('orders.destroy');
});

// === PAYMENTS API ===
Route::prefix('payments')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/management', [PaymentController::class, 'management'])->name('payments.management');
    Route::post('/', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::delete('{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
});

// === PURCHASE ORDERS API ===
Route::prefix('purchase-orders')->middleware(['auth:sanctum', 'role:admin,purchasing,inventory'])->group(function () {
    Route::get('/', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::post('/', [PurchaseOrderController::class, 'store'])->middleware('role:admin,purchasing')->name('purchase-orders.store');
    Route::get('{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    Route::put('{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
    Route::patch('{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update.patch');
    Route::delete('{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->middleware('role:admin')->name('purchase-orders.destroy');
});

// === DELIVERIES API ===
Route::prefix('deliveries')->middleware(['auth:sanctum', 'role:admin,delivery'])->group(function () {
    Route::get('/', [DeliveryController::class, 'index'])->name('deliveries.index');
    Route::post('/', [DeliveryController::class, 'store'])->middleware('role:admin')->name('deliveries.store');
    Route::get('{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
    Route::put('{delivery}', [DeliveryController::class, 'update'])->name('deliveries.update');
    Route::patch('{delivery}', [DeliveryController::class, 'update'])->name('deliveries.update.patch');
    Route::delete('{delivery}', [DeliveryController::class, 'destroy'])->middleware('role:admin')->name('deliveries.destroy');
});

// === EMPLOYEES API — admin only ===
Route::prefix('employees')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::put('{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::patch('{employee}', [EmployeeController::class, 'update'])->name('employees.update.patch');
    Route::delete('{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::post('{employee}/account', [EmployeeController::class, 'createAccount'])->name('employees.account.create');
    Route::post('{employee}/account/reset-credentials', [EmployeeController::class, 'resetAccountCredentials'])->name('employees.account.reset-credentials');
    Route::delete('{employee}/account', [EmployeeController::class, 'revokeAccount'])->name('employees.account.revoke');
    Route::patch('{employee}/account/toggle-status', [EmployeeController::class, 'toggleAccountStatus'])->name('employees.account.toggle-status');
});

Route::get('/login-audit-logs', [LoginAuditLogController::class, 'index'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('login-audit-logs.index');

// === REPORTS API — admin and purchasing ===
Route::middleware(['auth:sanctum', 'role:admin,purchasing'])->group(function () {
    Route::get('/reports/analytics', [ReportController::class, 'analytics'])->name('reports.analytics');
    Route::get('/reports/dashboard-summary', [ReportController::class, 'dashboardSummary'])->name('reports.dashboard-summary');
    Route::post('/reports/sales/generate', [ReportController::class, 'generateSalesReport'])->name('reports.sales.generate');
});

// === HEALTH CHECK ===

Route::get('/health', function () {
    $startedAt = Cache::rememberForever('app_started_at', function () {
        return now();
    });

    $uptime = now()->diffInSeconds($startedAt);

    return response()->json([
        'status'  => 'ok',
        'version' => '1.0.0',
        'uptime'  => $uptime,
    ]);
});
