<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    
    // Products
    Route::resource('products', \App\Http\Controllers\ProductController::class);
    
    // POS (Kasir)
    Route::get('/pos', [\App\Http\Controllers\PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [\App\Http\Controllers\PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('/pos/scan', [\App\Http\Controllers\PosController::class, 'getProductByBarcode'])->name('pos.scan');
    
    // Reports & Finance
    Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/expenses', [\App\Http\Controllers\ReportController::class, 'storeExpense'])->name('reports.expenses.store');
    
    // Distributors (Coming Soon / Mockup)
    Route::get('/distributors', function() {
        return view('distributors.index');
    })->name('distributors.index');
    
    // Profiles
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
