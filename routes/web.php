<?php

use App\Http\Controllers\CompetitorObservationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistributorController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportLookupController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Products
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::resource('products', ProductController::class)->except(['destroy']);

    // POS (Kasir)
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('/pos/scan', [PosController::class, 'getProductByBarcode'])->name('pos.scan');

    // Reports & Finance
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/finance', [ReportController::class, 'finance'])->name('reports.finance');
    Route::post('/reports/observations', [CompetitorObservationController::class, 'store'])->name('reports.observations.store');
    Route::patch('/reports/observations/{observation}', [CompetitorObservationController::class, 'update'])->name('reports.observations.update');
    Route::post('/reports/workshops', [ReportLookupController::class, 'workshops'])->name('reports.workshops.store');
    Route::post('/reports/work-types', [ReportLookupController::class, 'workTypes'])->name('reports.work-types.store');
    Route::post('/reports/expenses', [ReportController::class, 'storeExpense'])->name('reports.expenses.store');

    // Users Management
    Route::resource('users', UserController::class)->except(['create', 'show', 'edit']);

    Route::get('/distributors/export', [DistributorController::class, 'export'])->name('distributors.export');
    Route::resource('distributors', DistributorController::class)->only(['index', 'store', 'update']);

    // Profiles
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
