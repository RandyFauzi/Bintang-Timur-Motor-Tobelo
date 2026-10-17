<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BusinessSettingController;
use App\Http\Controllers\CompetitorObservationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistributorController;
use App\Http\Controllers\DistributorInvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductReturnController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportLookupController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShippingServiceController;
use App\Http\Controllers\TransactionHistoryController;
use App\Http\Controllers\UserAvatarController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/settings/business/logo', [BusinessSettingController::class, 'logo'])->name('business.logo');

Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('shipments', [ShipmentController::class, 'index'])->name('shipments.index');
    Route::get('shipments/notifications', [ShipmentController::class, 'notifications'])->name('shipments.notifications');
    Route::post('shipments', [ShipmentController::class, 'store'])->name('shipments.store');
    Route::patch('shipments/{shipment}', [ShipmentController::class, 'update'])->name('shipments.update');
    Route::patch('shipments/{shipment}/arrive', [ShipmentController::class, 'arrive'])->name('shipments.arrive');
    Route::post('shipping-services', [ShippingServiceController::class, 'store'])->name('shipping-services.store');
    Route::patch('shipping-services/{shippingService}', [ShippingServiceController::class, 'update'])->name('shipping-services.update');

    Route::get('distributor-invoices', [DistributorInvoiceController::class, 'index'])->name('distributor-invoices.index');
    Route::get('distributor-invoices/{distributorInvoice}', [DistributorInvoiceController::class, 'show'])->name('distributor-invoices.show');
    Route::patch('distributor-invoices/{distributorInvoice}', [DistributorInvoiceController::class, 'update'])->name('distributor-invoices.update');
    Route::post('distributor-invoices/{distributorInvoice}/payments', [DistributorInvoiceController::class, 'pay'])->name('distributor-invoices.pay');

    Route::post('purchase-orders/distributors', [PurchaseOrderController::class, 'distributor'])->name('purchase-orders.distributors.store');
    Route::patch('purchase-orders/{purchaseOrder}/receipt', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
    Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show'])->parameters(['purchase-orders' => 'purchaseOrder']);

    // Products
    Route::get('products/import/template', [ProductImportController::class, 'template'])->name('products.import.template');
    Route::post('products/import', [ProductImportController::class, 'store'])->middleware('throttle:10,1')->name('products.import');
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::patch('products/bulk', [ProductController::class, 'bulkUpdate'])->name('products.bulk.update');
    Route::delete('products/bulk', [ProductController::class, 'bulkDestroy'])->name('products.bulk.destroy');
    Route::resource('products', ProductController::class);

    // POS (Kasir)
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('/pos/scan', [PosController::class, 'getProductByBarcode'])->name('pos.scan');

    Route::get('/transactions', [TransactionHistoryController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/export', [TransactionHistoryController::class, 'export'])->name('transactions.export');
    Route::get('/transactions/products', [TransactionHistoryController::class, 'products'])->name('transactions.products');
    Route::patch('/transactions/{sale}', [TransactionHistoryController::class, 'update'])->whereUuid('sale')->name('transactions.update');
    Route::get('/transactions/{sale}', [TransactionHistoryController::class, 'show'])->whereUuid('sale')->name('transactions.show');

    Route::get('/returns', [ProductReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/products', [ProductReturnController::class, 'products'])->name('returns.products');
    Route::get('/returns/export', [ProductReturnController::class, 'export'])->name('returns.export');
    Route::get('/returns/activity', [ProductReturnController::class, 'activity'])->name('returns.activity');
    Route::post('/returns', [ProductReturnController::class, 'store'])->name('returns.store');
    Route::patch('/returns/bulk', [ProductReturnController::class, 'bulk'])->name('returns.bulk');
    Route::patch('/returns/{productReturn}/cancel', [ProductReturnController::class, 'cancel'])->whereUuid('productReturn')->name('returns.cancel');
    Route::patch('/returns/{productReturn}', [ProductReturnController::class, 'update'])->whereUuid('productReturn')->name('returns.update');
    Route::delete('/returns/{productReturn}', [ProductReturnController::class, 'destroy'])->whereUuid('productReturn')->name('returns.destroy');

    // Reports & Finance
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/finance', [ReportController::class, 'finance'])->name('reports.finance');
    Route::post('/reports/observations', [CompetitorObservationController::class, 'store'])->name('reports.observations.store');
    Route::patch('/reports/observations/{observation}', [CompetitorObservationController::class, 'update'])->name('reports.observations.update');
    Route::post('/reports/workshops', [ReportLookupController::class, 'workshops'])->name('reports.workshops.store');
    Route::post('/reports/work-types', [ReportLookupController::class, 'workTypes'])->name('reports.work-types.store');
    Route::post('/reports/expenses', [ReportController::class, 'storeExpense'])->name('reports.expenses.store');

    Route::get('/activity-logs/export', [ActivityLogController::class, 'export'])->name('activity-logs.export');
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    Route::get('/users/{user}/avatar', UserAvatarController::class)->name('users.avatar');
    Route::patch('/settings/business', [BusinessSettingController::class, 'update'])->name('business.update');

    // Users Management
    Route::get('/users/export', [UserController::class, 'export'])->name('users.export');
    Route::resource('users', UserController::class)->except(['create', 'show', 'edit']);

    Route::get('/distributors/export', [DistributorController::class, 'export'])->name('distributors.export');
    Route::resource('distributors', DistributorController::class)->only(['index', 'store', 'update']);

    // Profiles
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
