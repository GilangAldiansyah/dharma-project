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
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\PartMaterialController;
use App\Http\Controllers\TransaksiMaterialController;
use App\Http\Controllers\PengembalianMaterialController;
use App\Http\Controllers\DashboardTransaksiController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\LineOperationController;
use App\Http\Controllers\OeeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\KanbanController;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('welcome');
    }
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/welcome', [WelcomeController::class, 'index'])->name('welcome');

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

    Route::get('/forecast', [StockController::class, 'forecastIndex'])->name('forecast.index');
    Route::post('/forecast/update', [StockController::class, 'forecastUpdate'])->name('forecast.update');
    Route::post('/forecast/bulk-import', [StockController::class, 'forecastBulkImport'])->name('forecast.bulk-import');
    Route::delete('/forecast/{id}', [StockController::class, 'forecastDelete'])->name('forecast.delete');
    Route::get('/forecast/summary', [StockController::class, 'forecastSummary'])->name('forecast.summary');
    Route::post('/forecast/copy', [StockController::class, 'forecastCopy'])->name('forecast.copy');

    Route::get('/ng-reports/dashboard/export', [NgReportController::class, 'exportDashboard'])->name('ng-reports.dashboard.export');
    Route::get('/ng-reports', [NgReportController::class, 'index'])->name('ng-reports.index');
    Route::post('/ng-reports', [NgReportController::class, 'store'])->name('ng-reports.store');
    Route::get('/ng-reports/dashboard', [NgReportController::class, 'dashboard'])->name('ng-reports.dashboard');
    Route::post('/ng-reports/upload-template', [NgReportController::class, 'uploadPicaTemplate'])->name('ng-reports.upload-template');
    Route::delete('/ng-reports/delete-template', [NgReportController::class, 'deletePicaTemplate'])->name('ng-reports.delete-template');
    Route::get('/ng-reports/download-template', [NgReportController::class, 'downloadTemplate'])->name('ng-reports.download-template');
    Route::post('/ng-reports/{ngReport}/temporary-action', [NgReportController::class, 'submitTemporaryAction'])->name('ng-reports.submit-ta');
    Route::post('/ng-reports/{ngReport}/temporary-action/approve', [NgReportController::class, 'approveTemporaryAction'])->name('ng-reports.approve-ta');
    Route::post('/ng-reports/{ngReport}/temporary-action/reject', [NgReportController::class, 'rejectTemporaryAction'])->name('ng-reports.reject-ta');
    Route::post('/ng-reports/{ngReport}/upload-pica', [NgReportController::class, 'uploadPica'])->name('ng-reports.upload-pica');
    Route::post('/ng-reports/{ngReport}/pica/approve', [NgReportController::class, 'approvePica'])->name('ng-reports.approve-pica');
    Route::post('/ng-reports/{ngReport}/pica/reject', [NgReportController::class, 'rejectPica'])->name('ng-reports.reject-pica');
    Route::post('/ng-reports/{ngReport}/cancel-pica', [NgReportController::class, 'cancelPica'])->name('ng-reports.cancel-pica');
    Route::post('/ng-reports/{ngReport}/close', [NgReportController::class, 'closeReport'])->name('ng-reports.close');
    Route::resource('ng-reports', NgReportController::class)->only(['destroy']);

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::post('/suppliers/import', [SupplierController::class, 'import']);
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

    Route::get('/parts', [PartController::class, 'index'])->name('parts.index');
    Route::post('/parts', [PartController::class, 'store'])->name('parts.store');
    Route::put('/parts/{part}', [PartController::class, 'update'])->name('parts.update');
    Route::post('/parts/import', [PartController::class, 'import'])->name('parts.import');
    Route::delete('/parts/{part}', [PartController::class, 'destroy'])->name('parts.destroy');

    Route::get('die-shop-dashboard', [DieShopDashboardController::class, 'index'])->name('die-shop-dashboard');
    Route::resource('die-parts', DiePartController::class);
    Route::resource('die-shop-reports', DieShopReportController::class);
    Route::post('die-shop-reports/{dieShopReport}/quick-complete', [DieShopReportController::class, 'quickComplete'])->name('die-shop-reports.quick-complete');

    Route::get('/esp32/monitor', [ESP32Controller::class, 'monitor'])->name('esp32.monitor');
    Route::get('/esp32/monitor/{device_id}', [ESP32Controller::class, 'detail'])->name('esp32.detail');
    Route::post('/esp32/monitor/update-settings', [ESP32Controller::class, 'updateSettings'])->name('esp32.update-settings');
    Route::post('/esp32/monitor/update-schedule', [ESP32Controller::class, 'updateSchedule'])->name('esp32.update-schedule');

    Route::get('materials/search/api', [MaterialController::class, 'searchApi'])->name('materials.search');
    Route::post('/materials/import', [MaterialController::class, 'import'])->name('materials.import');
    Route::get('/materials/download-template', [MaterialController::class, 'downloadTemplate'])->name('materials.download-template');
    Route::resource('materials', MaterialController::class);

    Route::post('/part-materials/import', [PartMaterialController::class, 'import'])->name('part-materials.import');
    Route::post('/part-materials/delete-multiple', [PartMaterialController::class, 'deleteMultiple'])->name('part-materials.delete-multiple');
    Route::resource('part-materials', PartMaterialController::class);

    Route::get('/transaksi/export-data', [TransaksiMaterialController::class, 'exportData'])->name('transaksi.export-data');
    Route::get('/transaksi/dashboard', [DashboardTransaksiController::class, 'index'])->name('transaksi.dashboard');
    Route::get('transaksi/history/view', [TransaksiMaterialController::class, 'history'])->name('transaksi.history');
    Route::post('transaksi/delete-multiple', [TransaksiMaterialController::class, 'deleteMultiple'])->name('transaksi.delete-multiple');
    Route::get('/transaksi/search-for-return', [TransaksiMaterialController::class, 'searchForReturn'])->name('transaksi.search-for-return');
    Route::resource('transaksi', TransaksiMaterialController::class)->except(['edit', 'update']);

    Route::post('/pengembalian', [PengembalianMaterialController::class, 'store'])->name('pengembalian.store');
    Route::delete('/pengembalian/{pengembalian}', [PengembalianMaterialController::class, 'destroy'])->name('pengembalian.destroy');
    Route::get('/transaksi/{transaksi}/pengembalian-history', [PengembalianMaterialController::class, 'getPengembalianHistory']);

    Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('/maintenance/dashboard', [MaintenanceController::class, 'dashboard'])->name('maintenance.dashboard');
    Route::post('/maintenance', [MaintenanceController::class, 'store'])->name('maintenance.store');
    Route::post('/maintenance/scan-barcode', [MaintenanceController::class, 'scanQrCode'])->name('maintenance.scan-barcode');
    Route::post('/maintenance/{id}/start', [MaintenanceController::class, 'startRepair'])->name('maintenance.start')->where('id', '[0-9]+');
    Route::post('/maintenance/{id}/complete', [MaintenanceController::class, 'completeRepair'])->name('maintenance.complete')->where('id', '[0-9]+');
    Route::delete('/maintenance/{id}', [MaintenanceController::class, 'destroy'])->name('maintenance.destroy')->where('id', '[0-9]+');

    Route::prefix('maintenance/mesin')->name('maintenance.mesin.')->group(function () {
        Route::get('/', [MachineController::class, 'index'])->name('index');
        Route::post('/', [MachineController::class, 'store'])->name('store');
        Route::put('/{id}', [MachineController::class, 'update'])->name('update');
        Route::delete('/{id}', [MachineController::class, 'destroy'])->name('destroy');
        Route::get('/{id}/metrics', [MachineController::class, 'metrics'])->name('metrics');
    });

    Route::prefix('maintenance/lines')->name('maintenance.lines.')->group(function () {
        Route::get('/', [LineController::class, 'index'])->name('index');
        Route::post('/', [LineController::class, 'store'])->name('store');
        Route::get('/{id}', [LineController::class, 'show'])->name('show');
        Route::put('/{id}', [LineController::class, 'update'])->name('update');
        Route::delete('/{id}', [LineController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/reset', [LineController::class, 'resetMetrics'])->name('reset');
        Route::post('/{id}/schedule', [LineController::class, 'updateSchedule'])->name('update-schedule');
        Route::post('/line-stop', [LineController::class, 'lineStop'])->name('line-stop');
        Route::post('/scan-qr', [LineController::class, 'scanQrCode'])->name('scan-qr');
        Route::post('/reports/{reportId}/complete', [LineController::class, 'quickComplete'])->name('reports.complete');
        Route::get('/{lineId}/active-reports', [LineController::class, 'getActiveReports'])->name('active-reports');
        Route::post('/{line}/reset-metrics', [LineController::class, 'resetMetrics'])->name('reset-metrics');
        Route::get('/{line}/history', [LineController::class, 'history'])->name('history');
        Route::get('/{line}/summary', [LineController::class, 'getSummary'])->name('summary');
    });

    Route::prefix('maintenance/operations')->group(function () {
        Route::post('/start', [LineOperationController::class, 'startOperation'])->name('maintenance.operations.start');
        Route::post('/{operationId}/pause', [LineOperationController::class, 'pauseOperation'])->name('maintenance.operations.pause');
        Route::post('/{operationId}/resume', [LineOperationController::class, 'resumeOperation'])->name('maintenance.operations.resume');
        Route::post('/{operationId}/stop', [LineOperationController::class, 'stopOperation'])->name('maintenance.operations.stop');
        Route::get('/lines/{lineId}/current', [LineOperationController::class, 'getCurrentOperation'])->name('maintenance.operations.current');
    });

    Route::prefix('oee')->name('oee.')->group(function () {
        Route::get('/', [OeeController::class, 'index'])->name('index');
        Route::post('/calculate', [OeeController::class, 'calculate'])->name('calculate');
        Route::get('/{oeeRecord}', [OeeController::class, 'show'])->name('show');
        Route::delete('/{oeeRecord}', [OeeController::class, 'destroy'])->name('destroy');
        Route::get('/export/data', [OeeController::class, 'export'])->name('export');
        Route::post('/compare', [OeeController::class, 'compare'])->name('compare');
        Route::get('/chart/data', [OeeController::class, 'chartData'])->name('chart-data');
    });

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::get('/kanbans', [KanbanController::class, 'index'])->name('kanbans.index');
    Route::post('/kanbans', [KanbanController::class, 'store'])->name('kanbans.store');
    Route::get('/kanbans/{kanban}', [KanbanController::class, 'show'])->name('kanbans.show');
    Route::delete('/kanbans/{kanban}', [KanbanController::class, 'destroy'])->name('kanbans.destroy');

    Route::post('/kanbans/rfid-master', [KanbanController::class, 'getRfidMasterData']);
    Route::post('/kanbans/master', [KanbanController::class, 'storeMaster'])->name('kanbans.master.store');
    Route::put('/kanbans/master/{rfidMaster}', [KanbanController::class, 'updateMaster'])->name('kanbans.master.update');
    Route::delete('/kanbans/master/{rfidMaster}', [KanbanController::class, 'destroyMaster'])->name('kanbans.master.destroy');

    Route::post('/kanbans/scan', [KanbanController::class, 'scan'])->name('kanbans.scan');
    Route::get('/kanbans/history/{rfidTag}', [KanbanController::class, 'history'])->name('kanbans.history');
    Route::post('/kanbans/check-rfid', [KanbanController::class, 'checkRfid'])->name('kanbans.check');
});

require __DIR__.'/settings.php';
