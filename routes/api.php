<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

// Import all API Controllers
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\PaymentController;

// === AUTH ROUTES ===
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

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
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index'])
        ->name('products.index');
    Route::post('/', [ProductController::class, 'store'])
        ->name('products.store');
    Route::get('{product}', [ProductController::class, 'show'])
        ->name('products.show');
    Route::put('{product}', [ProductController::class, 'update'])
        ->name('products.update');
    Route::patch('{product}', [ProductController::class, 'update'])
        ->name('products.update.patch');
    Route::delete('{product}', [ProductController::class, 'destroy'])
        ->name('products.destroy');
});

// === CUSTOMERS API ===
Route::prefix('customers')->group(function () {
    Route::get('/', [CustomerController::class, 'index'])
        ->name('customers.index');
    Route::post('/', [CustomerController::class, 'store'])
        ->name('customers.store');
    Route::get('{customer}', [CustomerController::class, 'show'])
        ->name('customers.show');
    Route::put('{customer}', [CustomerController::class, 'update'])
        ->name('customers.update');
    Route::patch('{customer}', [CustomerController::class, 'update'])
        ->name('customers.update.patch');
    Route::delete('{customer}', [CustomerController::class, 'destroy'])
        ->name('customers.destroy');
});

// === SUPPLIERS API ===
Route::prefix('suppliers')->group(function () {
    Route::get('/', [SupplierController::class, 'index'])
        ->name('suppliers.index');
    Route::post('/', [SupplierController::class, 'store'])
        ->name('suppliers.store');
    Route::get('{supplier}', [SupplierController::class, 'show'])
        ->name('suppliers.show');
    Route::put('{supplier}', [SupplierController::class, 'update'])
        ->name('suppliers.update');
    Route::patch('{supplier}', [SupplierController::class, 'update'])
        ->name('suppliers.update.patch');
    Route::delete('{supplier}', [SupplierController::class, 'destroy'])
        ->name('suppliers.destroy');
});

// === INVENTORY API ===
Route::prefix('inventory')->group(function () {
    Route::get('/', [InventoryController::class, 'index'])
        ->name('inventory.index');
    Route::get('low-stock', [InventoryController::class, 'showLowStock'])
        ->name('inventory.low-stock');
    Route::get('{product}', [InventoryController::class, 'show'])
        ->name('inventory.show');
    Route::put('{product}', [InventoryController::class, 'update'])
        ->name('inventory.update');
    Route::patch('{product}', [InventoryController::class, 'update'])
        ->name('inventory.update.patch');
});

// === ORDERS API ===
Route::prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index'])
        ->name('orders.index');
    Route::post('/', [OrderController::class, 'store'])
        ->name('orders.store');
    Route::get('{order}', [OrderController::class, 'show'])
        ->name('orders.show');
    Route::put('{order}', [OrderController::class, 'update'])
        ->name('orders.update');
    Route::patch('{order}', [OrderController::class, 'update'])
        ->name('orders.update.patch');
    Route::delete('{order}', [OrderController::class, 'destroy'])
        ->name('orders.destroy');
});

// === PAYMENTS API ===
Route::prefix('payments')->group(function () {
    Route::get('/', [PaymentController::class, 'index'])
        ->name('payments.index');
    Route::post('/', [PaymentController::class, 'store'])
        ->name('payments.store');
    Route::get('{payment}', [PaymentController::class, 'show'])
        ->name('payments.show');
    Route::delete('{payment}', [PaymentController::class, 'destroy'])
        ->name('payments.destroy');
});

// === PURCHASE ORDERS API ===
Route::prefix('purchase-orders')->group(function () {
    Route::get('/', [PurchaseOrderController::class, 'index'])
        ->name('purchase-orders.index');
    Route::post('/', [PurchaseOrderController::class, 'store'])
        ->name('purchase-orders.store');
    Route::get('{purchaseOrder}', [PurchaseOrderController::class, 'show'])
        ->name('purchase-orders.show');
    Route::put('{purchaseOrder}', [PurchaseOrderController::class, 'update'])
        ->name('purchase-orders.update');
    Route::patch('{purchaseOrder}', [PurchaseOrderController::class, 'update'])
        ->name('purchase-orders.update.patch');
    Route::delete('{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])
        ->name('purchase-orders.destroy');
});

// === DELIVERIES API ===
Route::prefix('deliveries')->group(function () {
    Route::get('/', [DeliveryController::class, 'index'])
        ->name('deliveries.index');
    Route::post('/', [DeliveryController::class, 'store'])
        ->name('deliveries.store');
    Route::get('{delivery}', [DeliveryController::class, 'show'])
        ->name('deliveries.show');
    Route::put('{delivery}', [DeliveryController::class, 'update'])
        ->name('deliveries.update');
    Route::patch('{delivery}', [DeliveryController::class, 'update'])
        ->name('deliveries.update.patch');
    Route::delete('{delivery}', [DeliveryController::class, 'destroy'])
        ->name('deliveries.destroy');
});

// === HEALTH CHECK ===

Route::get('/health', function () {
    $startedAt = Cache::rememberForever('app_started_at', function () {
        return now();
    });

    $uptime = now()->diffInSeconds($startedAt);

    return response()->json([
        'status'  => 'ok',
        'version' => '1.0.0',  // optional, but matches your Postman tests
        'uptime'  => $uptime,  // optional
    ]);
});