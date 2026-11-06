<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Admin endpoints (admin role required)
    Route::middleware('admin')->group(function () {
        Route::get('/orders', [App\Http\Controllers\OrderController::class, 'index']);
        Route::get('/orders/{id}', [App\Http\Controllers\OrderController::class, 'show']);

        Route::get('/products', [App\Http\Controllers\ProductController::class, 'index']);
        Route::get('/products/{id}', [App\Http\Controllers\ProductController::class, 'show']);

        Route::get('/inventory', [App\Http\Controllers\InventoryController::class, 'index']);
        Route::post('/inventory/adjust', [App\Http\Controllers\InventoryController::class, 'adjust']);
        Route::get('/inventory/history', [App\Http\Controllers\InventoryController::class, 'history']);
        Route::put('/inventory/products/{product}/threshold', [App\Http\Controllers\InventoryController::class, 'updateThreshold']);

        Route::get('/categories', [App\Http\Controllers\CategoryController::class, 'index']);
        Route::get('/categories/{id}', [App\Http\Controllers\CategoryController::class, 'show']);

        Route::get('/customers', [App\Http\Controllers\CustomerController::class, 'index']);
        Route::get('/customers/{id}', [App\Http\Controllers\CustomerController::class, 'show']);

        Route::get('/purchase-orders', [App\Http\Controllers\PurchaseOrderController::class, 'index']);
        Route::get('/purchase-orders/{id}', [App\Http\Controllers\PurchaseOrderController::class, 'show']);

        Route::get('/suppliers', [App\Http\Controllers\SupplierController::class, 'index']);
        Route::get('/suppliers/{id}', [App\Http\Controllers\SupplierController::class, 'show']);

        Route::get('/coupons', [App\Http\Controllers\CouponController::class, 'index']);
        Route::get('/coupons/{id}', [App\Http\Controllers\CouponController::class, 'show']);

        Route::get('/expenses', [App\Http\Controllers\ExpenseController::class, 'index']);
        Route::get('/expenses/{id}', [App\Http\Controllers\ExpenseController::class, 'show']);

        Route::get('/reports/accounting', [App\Http\Controllers\AccountingReportController::class, 'dashboard']);

        Route::get('/settings', [App\Http\Controllers\SettingsController::class, 'index']);
        Route::post('/settings', [App\Http\Controllers\SettingsController::class, 'store']);
        Route::get('/settings/{key}', [App\Http\Controllers\SettingsController::class, 'show']);

        Route::get('/content-editor', [App\Http\Controllers\ContentEditorController::class, 'index']);
        Route::post('/content-editor/upload', [App\Http\Controllers\ContentEditorController::class, 'upload']);
        Route::put('/content-editor/{key}', [App\Http\Controllers\ContentEditorController::class, 'update']);

        Route::get('/contents', [App\Http\Controllers\ContentController::class, 'index']);
        Route::post('/contents', [App\Http\Controllers\ContentController::class, 'store']);
        Route::get('/contents/{key}', [App\Http\Controllers\ContentController::class, 'show']);
        Route::put('/contents/{key}', [App\Http\Controllers\ContentController::class, 'update']);
    });
});
