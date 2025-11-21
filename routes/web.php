<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\NgReportController;
use App\Http\Controllers\ProductionProblemController;
use App\Http\Controllers\ProductionMonitoringController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\StockController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/export', [DashboardController::class, 'export'])->name('dashboard.export');
    Route::get('/dashboard/stock-trend', [DashboardController::class, 'getStockTrend'])->name('dashboard.trend');
    Route::get('/dashboard/critical-alerts', [DashboardController::class, 'getCriticalAlerts'])->name('dashboard.alerts');

    // Master Parts
    // Route::resource('parts', PartController::class);

    // Stock Control Routes
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::post('/stock/update', [StockController::class, 'update'])->name('stock.update');
    Route::delete('/stock/{id}', [StockController::class, 'deleteRow'])->name('stock.deleteRow');
      // Stock Delete Routes
    Route::post('/stock/delete-multiple', [StockController::class, 'deleteMultiple'])->name('stock.delete-multiple');
    Route::post('/stock/delete-all-by-date', [StockController::class, 'deleteAllByDate'])->name('stock.delete-all-by-date');


    // Output Products Routes
    Route::get('/output', [StockController::class, 'output'])->name('output.index');
    Route::post('/stock/output/update', [StockController::class, 'updateOutput'])->name('stock.output.update');
    Route::delete('/stock/output/{id}', [StockController::class, 'deleteOutput'])->name('stock.output.delete');

    // Output Delete Routes
    Route::post('/stock/delete-multiple-output', [StockController::class, 'deleteMultipleOutput'])->name('stock.delete-multiple-output');
    Route::post('/stock/output/delete-all-by-date', [StockController::class, 'deleteAllOutputByDate'])->name('stock.output.delete-all-by-date');

    // Output Product Materials Management
    Route::get('/stock/output/{outputProduct}/materials', [StockController::class, 'getOutputMaterials']);
    Route::post('/stock/output/{outputProduct}/materials', [StockController::class, 'updateOutputMaterials']);

    // Get available materials from Control Stock
    Route::get('/stock/available-materials', [StockController::class, 'getAvailableMaterials']);



    // Production Monitoring (Real-time dari PLC)
    // Route::get('/production-monitoring', [ProductionMonitoringController::class, 'index'])->name('production-monitoring');

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

    // Parts
    Route::get('/parts', [PartController::class, 'index'])->name('parts.index');
    Route::post('/parts', [PartController::class, 'store'])->name('parts.store');
    Route::put('/parts/{part}', [PartController::class, 'update'])->name('parts.update');
    Route::delete('/parts/{part}', [PartController::class, 'destroy'])->name('parts.destroy');

    // NG Reports
    Route::get('/ng-reports', [NgReportController::class, 'index'])->name('ng-reports.index');
    Route::post('/ng-reports', [NgReportController::class, 'store'])->name('ng-reports.store');
    Route::delete('/ng-reports/{ngReport}', [NgReportController::class, 'destroy'])->name('ng-reports.destroy');



});

require __DIR__.'/settings.php';
