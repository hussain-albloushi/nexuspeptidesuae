<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountingReportController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\ContentEditorController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\VerificationController;
use App\Http\Middleware\EnsureCustomerAccess;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:6,1');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1');
Route::post('/coupons/validate', [CouponController::class, 'validateCoupon']);
Route::get('/store-settings', [StorefrontController::class, 'storeSettings']);

// Stripe webhook (signature-verified; public by necessity)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

Route::middleware(['auth:sanctum', EnsureCustomerAccess::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me/address', [AccountController::class, 'updateAddress']);
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->middleware('throttle:6,1');

    // Customer orders (authenticated customer can view own orders)
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::post('/orders', [OrderController::class, 'store']);

    // Admin endpoints (admin role required)
    Route::middleware('admin')->group(function () {
        Route::get('/dashboard', DashboardController::class);
        Route::get('/dashboard/notifications', [DashboardController::class, 'notifications']);
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/export', [AdminOrderController::class, 'export']);
        Route::post('/orders/bulk-status', [AdminOrderController::class, 'bulkStatus']);
        Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
        Route::patch('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::patch('/orders/{id}/payment', [AdminOrderController::class, 'collectPayment']);
        Route::get('/orders/{id}/invoice', [AdminOrderController::class, 'invoice']);

        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);
        Route::get('/inventory/history', [InventoryController::class, 'history']);
        Route::put('/inventory/products/{product}/threshold', [InventoryController::class, 'updateThreshold']);

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{id}', [CategoryController::class, 'show']);

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/export', [CustomerController::class, 'export']);
        Route::post('/customers/bulk', [CustomerController::class, 'bulk']);
        Route::get('/customers/{id}', [CustomerController::class, 'show']);
        Route::put('/customers/{id}', [CustomerController::class, 'update']);
        Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);

        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::get('/purchase-orders/{id}', [PurchaseOrderController::class, 'show']);

        Route::get('/suppliers', [SupplierController::class, 'index']);
        Route::get('/suppliers/{id}', [SupplierController::class, 'show']);

        Route::get('/coupons', [CouponController::class, 'index']);
        Route::get('/coupons/{id}', [CouponController::class, 'show']);

        Route::post('/coupons', [CouponController::class, 'store']);
        Route::put('/coupons/{id}', [CouponController::class, 'update']);
        Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);

        Route::get('/expenses', [ExpenseController::class, 'index']);
        Route::get('/expenses/{id}', [ExpenseController::class, 'show']);

        Route::get('/reports/accounting', [AccountingReportController::class, 'dashboard']);

        Route::get('/settings', [SettingsController::class, 'index']);
        Route::post('/settings', [SettingsController::class, 'store']);
        Route::get('/settings/{key}', [SettingsController::class, 'show']);

        Route::get('/content-editor', [ContentEditorController::class, 'index']);
        Route::post('/content-editor/upload', [ContentEditorController::class, 'upload']);
        Route::put('/content-editor/{key}', [ContentEditorController::class, 'update']);

        Route::get('/contents', [ContentController::class, 'index']);
        Route::post('/contents', [ContentController::class, 'store']);
        Route::get('/contents/{key}', [ContentController::class, 'show']);
        Route::put('/contents/{key}', [ContentController::class, 'update']);
    });
});
