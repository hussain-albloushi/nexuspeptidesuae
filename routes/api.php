<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountingReportController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AiBusinessChatController;
use App\Http\Controllers\AiOverviewController;
use App\Http\Controllers\AiRecommendationsController;
use App\Http\Controllers\AiReportsController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessAnalyticsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\ContentEditorController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryIntelligenceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductIntelligenceController;
use App\Http\Controllers\ProfitIntelligenceController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SalesIntelligenceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\VerificationController;
use App\Http\Middleware\EnsureCustomerAccess;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:6,1');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1');
Route::post('/coupons/validate', [CouponController::class, 'validateCoupon']);
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1');
Route::get('/store-settings', [StorefrontController::class, 'storeSettings']);

// Stripe webhook (signature-verified; public by necessity)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

Route::middleware(['auth:sanctum', EnsureCustomerAccess::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);
    Route::put('/me/address', [AccountController::class, 'updateAddress']);
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->middleware('throttle:6,1');

    // Customer orders (authenticated customer can view own orders)
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{id}/return', [OrderController::class, 'requestReturn']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

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
        Route::patch('/orders/{id}/refund', [AdminOrderController::class, 'recordRefund']);
        Route::patch('/orders/{id}/returns/{returnId}', [AdminOrderController::class, 'respondReturn']);
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
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/export', [CustomerController::class, 'export']);
        Route::post('/customers/bulk', [CustomerController::class, 'bulk']);
        Route::get('/customers/{id}', [CustomerController::class, 'show']);
        Route::put('/customers/{id}', [CustomerController::class, 'update']);
        Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);

        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::get('/purchase-orders/{id}', [PurchaseOrderController::class, 'show']);
        Route::put('/purchase-orders/{id}', [PurchaseOrderController::class, 'update']);
        Route::delete('/purchase-orders/{id}', [PurchaseOrderController::class, 'destroy']);
        Route::post('/purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive']);

        Route::get('/suppliers', [SupplierController::class, 'index']);
        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
        Route::put('/suppliers/{id}', [SupplierController::class, 'update']);
        Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

        Route::get('/coupons', [CouponController::class, 'index']);
        Route::get('/coupons/{id}', [CouponController::class, 'show']);

        Route::post('/coupons', [CouponController::class, 'store']);
        Route::put('/coupons/{id}', [CouponController::class, 'update']);
        Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);

        Route::get('/expenses', [ExpenseController::class, 'index']);
        Route::post('/expenses', [ExpenseController::class, 'store']);
        Route::get('/expenses/{id}', [ExpenseController::class, 'show']);
        Route::put('/expenses/{id}', [ExpenseController::class, 'update']);
        Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);

        Route::get('/reports/accounting', [AccountingReportController::class, 'dashboard']);

        Route::get('/settings', [SettingsController::class, 'index']);
        Route::post('/settings', [SettingsController::class, 'store']);
        Route::get('/settings/{key}', [SettingsController::class, 'show']);

        Route::get('/email-templates', [EmailTemplateController::class, 'index']);
        Route::get('/global-search', [GlobalSearchController::class, 'index']);
        Route::put('/email-templates/{key}', [EmailTemplateController::class, 'update']);
        Route::delete('/email-templates/{key}', [EmailTemplateController::class, 'destroy']);
        Route::post('/email-templates/{key}/preview', [EmailTemplateController::class, 'preview']);
        Route::post('/email-templates/{key}/test', [EmailTemplateController::class, 'test'])->middleware('throttle:6,1');

        Route::get('/content-editor', [ContentEditorController::class, 'index']);
        Route::post('/content-editor/upload', [ContentEditorController::class, 'upload']);
        Route::put('/content-editor/{key}', [ContentEditorController::class, 'update']);

        Route::get('/contents', [ContentController::class, 'index']);
        Route::post('/contents', [ContentController::class, 'store']);
        Route::get('/contents/{key}', [ContentController::class, 'show']);
        Route::put('/contents/{key}', [ContentController::class, 'update']);

        Route::middleware('throttle:60,1')->group(function () {
            Route::get('/ai/overview', AiOverviewController::class);
            Route::get('/ai/business-analytics', BusinessAnalyticsController::class);
            Route::get('/ai/products', [ProductIntelligenceController::class, 'index']);
            Route::get('/ai/products/{id}', [ProductIntelligenceController::class, 'show']);
            Route::post('/ai/products/compare', [ProductIntelligenceController::class, 'compare']);
            Route::get('/ai/sales/trend', [SalesIntelligenceController::class, 'trend']);
            Route::get('/ai/sales/breakdown', [SalesIntelligenceController::class, 'breakdown']);
            Route::get('/ai/sales/compare', [SalesIntelligenceController::class, 'compare']);
            Route::get('/ai/sales/forecast', [SalesIntelligenceController::class, 'forecast']);
            Route::get('/ai/profit/pnl', [ProfitIntelligenceController::class, 'pnl']);
            Route::get('/ai/profit/products', [ProfitIntelligenceController::class, 'products']);
            Route::get('/ai/profit/categories', [ProfitIntelligenceController::class, 'categories']);
            Route::get('/ai/profit/discounts-impact', [ProfitIntelligenceController::class, 'discountsImpact']);
            Route::get('/ai/inventory/stock', [InventoryIntelligenceController::class, 'stock']);
            Route::get('/ai/inventory/movement', [InventoryIntelligenceController::class, 'movement']);
            Route::get('/ai/inventory/alerts', [InventoryIntelligenceController::class, 'alerts']);
            Route::get('/ai/inventory/valuation', [InventoryIntelligenceController::class, 'valuation']);
            Route::get('/ai/recommendations', [AiRecommendationsController::class, 'index']);
            Route::put('/ai/recommendations/{id}/status', [AiRecommendationsController::class, 'updateStatus']);
            Route::put('/ai/recommendations/{id}/note', [AiRecommendationsController::class, 'updateNote']);
            Route::post('/ai/recommendations/regenerate', [AiRecommendationsController::class, 'regenerate']);
            Route::post('/ai/chat', [AiBusinessChatController::class, 'chat'])->middleware('throttle:30,1');
            Route::get('/ai/chats', [AiBusinessChatController::class, 'index']);
            Route::post('/ai/chats', [AiBusinessChatController::class, 'store']);
            Route::get('/ai/chats/{id}', [AiBusinessChatController::class, 'show']);
            Route::delete('/ai/chats/{id}', [AiBusinessChatController::class, 'destroy']);
            Route::get('/ai/reports', [AiReportsController::class, 'index']);
            Route::post('/ai/reports/generate', [AiReportsController::class, 'generate']);
            Route::post('/ai/reports/schedule', [AiReportsController::class, 'updateSchedule']);
            Route::get('/ai/reports/{id}', [AiReportsController::class, 'show']);
            Route::delete('/ai/reports/{id}', [AiReportsController::class, 'destroy']);
            Route::get('/ai/settings', [AiSettingsController::class, 'index']);
            Route::post('/ai/settings', [AiSettingsController::class, 'store']);
            Route::post('/ai/settings/test', [AiSettingsController::class, 'testConnection'])->middleware('throttle:6,1');
        });
    });
});
