<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\NgReportController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\DiePartController;
use App\Http\Controllers\DieShopReportController;
use App\Http\Controllers\DieShopDashboardController;
use App\Http\Controllers\ESP32Controller;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('welcome');
    }
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/welcome', [WelcomeController::class, 'index'])->name('welcome');

    // Stock Control Routes
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/export', [DashboardController::class, 'export'])->name('dashboard.export');
    Route::get('/dashboard/stock-trend', [DashboardController::class, 'getStockTrend'])->name('dashboard.trend');
    Route::get('/dashboard/critical-alerts', [DashboardController::class, 'getCriticalAlerts'])->name('dashboard.alerts');
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::post('/stock/update', [StockController::class, 'update'])->name('stock.update');
    Route::delete('/stock/{id}', [StockController::class, 'deleteRow'])->name('stock.deleteRow');
    Route::post('/stock/delete-multiple', [StockController::class, 'deleteMultiple'])->name('stock.delete-multiple');
    Route::post('/stock/delete-all-by-date', [StockController::class, 'deleteAllByDate'])->name('stock.delete-all-by-date');
    Route::get('/output', [StockController::class, 'output'])->name('output.index');
    Route::post('/stock/output/update', [StockController::class, 'updateOutput'])->name('stock.output.update');
    Route::delete('/stock/output/{id}', [StockController::class, 'deleteOutput'])->name('stock.output.delete');
    Route::post('/stock/delete-multiple-output', [StockController::class, 'deleteMultipleOutput'])->name('stock.delete-multiple-output');
    Route::post('/stock/output/delete-all-by-date', [StockController::class, 'deleteAllOutputByDate'])->name('stock.output.delete-all-by-date');
    Route::get('/stock/output/{outputProduct}/materials', [StockController::class, 'getOutputMaterials']);
    Route::post('/stock/output/{outputProduct}/materials', [StockController::class, 'updateOutputMaterials']);
    Route::get('/stock/available-materials', [StockController::class, 'getAvailableMaterials']);
    // Forecast
    Route::get('/forecast', [StockController::class, 'forecastIndex'])->name('forecast.index');
    Route::post('/forecast/update', [StockController::class, 'forecastUpdate'])->name('forecast.update');
    Route::post('/forecast/bulk-import', [StockController::class, 'forecastBulkImport'])->name('forecast.bulk-import');
    Route::delete('/forecast/{id}', [StockController::class, 'forecastDelete'])->name('forecast.delete');
    Route::get('/forecast/summary', [StockController::class, 'forecastSummary'])->name('forecast.summary');

    // NG Reports System
    Route::get('/ng-reports', [NgReportController::class, 'index'])->name('ng-reports.index');
    Route::post('/ng-reports', [NgReportController::class, 'store'])->name('ng-reports.store');
    // Route::delete('/ng-reports/{ngReport}', [NgReportController::class, 'destroys'])->name('ng-reports.destroys');
    Route::get('/ng-reports/dashboard', [NgReportController::class, 'dashboard'])->name('ng-reports.dashboard');
    Route::resource('ng-reports', NgReportController::class);
    Route::post('/ng-reports/{ngReport}/upload-pica', [NgReportController::class, 'uploadPica'])->name('ng-reports.upload-pica');
    Route::post('/ng-reports/{ngReport}/close', [NgReportController::class, 'closeReport'])->name('ng-reports.close');
    Route::post('/ng-reports/{ngReport}/cancel-pica', [NgReportController::class, 'cancelPica'])->name('ng-reports.cancel-pica');

    // Suppliers
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::post('/suppliers/import', [SupplierController::class, 'import']);
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

    // Parts
    Route::get('/parts', [PartController::class, 'index'])->name('parts.index');
    Route::post('/parts', [PartController::class, 'store'])->name('parts.store');
    Route::put('/parts/{part}', [PartController::class, 'update'])->name('parts.update');
    Route::post('/parts/import', [PartController::class, 'import'])->name('parts.import');
    Route::delete('/parts/{part}', [PartController::class, 'destroy'])->name('parts.destroy');

    // Die Shop System
    Route::get('die-shop-dashboard', [DieShopDashboardController::class, 'index'])->name('die-shop-dashboard');
    Route::resource('die-parts', DiePartController::class);
    Route::resource('die-shop-reports', DieShopReportController::class);

    Route::get('/esp32/monitor', [ESP32Controller::class, 'monitor'])->name('esp32.monitor');
    Route::get('/esp32/monitor/{device_id}', [ESP32Controller::class, 'detail'])->name('esp32.detail');
});

require __DIR__.'/settings.php';
