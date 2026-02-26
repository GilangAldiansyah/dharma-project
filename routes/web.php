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
use App\Http\Controllers\AiChatController;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('welcome');
    }
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/welcome', [WelcomeController::class, 'index'])->name('welcome');

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::middleware('permission:dashboard.view')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
        Route::get('/dashboard/export', [DashboardController::class, 'export'])->name('dashboard.export');
        Route::get('/dashboard/stock-trend', [DashboardController::class, 'getStockTrend'])->name('dashboard.trend');
        Route::get('/dashboard/critical-alerts', [DashboardController::class, 'getCriticalAlerts'])->name('dashboard.alerts');
    });

    // ── Stock ─────────────────────────────────────────────────────────────────
    Route::get('/stock', [StockController::class, 'index'])->middleware('permission:stock.view')->name('stock.index');
    Route::post('/stock/update', [StockController::class, 'update'])->middleware('permission:stock.edit')->name('stock.update');
    Route::delete('/stock/{id}', [StockController::class, 'deleteRow'])->middleware('permission:stock.delete')->name('stock.deleteRow');
    Route::post('/stock/delete-multiple', [StockController::class, 'deleteMultiple'])->middleware('permission:stock.delete')->name('stock.delete-multiple');
    Route::post('/stock/delete-all-by-date', [StockController::class, 'deleteAllByDate'])->middleware('permission:stock.delete')->name('stock.delete-all-by-date');

    // ── Output ────────────────────────────────────────────────────────────────
    Route::get('/output', [StockController::class, 'output'])->middleware('permission:output.view')->name('output.index');
    Route::post('/stock/output/update', [StockController::class, 'updateOutput'])->middleware('permission:output.edit')->name('stock.output.update');
    Route::delete('/stock/output/{id}', [StockController::class, 'deleteOutput'])->middleware('permission:stock.delete')->name('stock.output.delete');
    Route::post('/stock/delete-multiple-output', [StockController::class, 'deleteMultipleOutput'])->middleware('permission:stock.delete')->name('stock.delete-multiple-output');
    Route::post('/stock/output/delete-all-by-date', [StockController::class, 'deleteAllOutputByDate'])->middleware('permission:stock.delete')->name('stock.output.delete-all-by-date');
    Route::get('/stock/output/{outputProduct}/materials', [StockController::class, 'getOutputMaterials'])->middleware('permission:output.view');
    Route::post('/stock/output/{outputProduct}/materials', [StockController::class, 'updateOutputMaterials'])->middleware('permission:output.edit');
    Route::get('/stock/available-materials', [StockController::class, 'getAvailableMaterials'])->middleware('permission:output.view');

    // ── Forecast ──────────────────────────────────────────────────────────────
    Route::middleware('permission:forecast.view')->group(function () {
        Route::get('/forecast', [StockController::class, 'forecastIndex'])->name('forecast.index');
        Route::get('/forecast/summary', [StockController::class, 'forecastSummary'])->name('forecast.summary');
    });
    Route::middleware('permission:forecast.edit')->group(function () {
        Route::post('/forecast/update', [StockController::class, 'forecastUpdate'])->name('forecast.update');
        Route::post('/forecast/bulk-import', [StockController::class, 'forecastBulkImport'])->name('forecast.bulk-import');
        Route::post('/forecast/copy', [StockController::class, 'forecastCopy'])->name('forecast.copy');
        Route::delete('/forecast/{id}', [StockController::class, 'forecastDelete'])->name('forecast.delete');
    });

    // ── NG Reports ────────────────────────────────────────────────────────────
    Route::middleware('permission:ng.view')->group(function () {
        Route::get('/ng-reports', [NgReportController::class, 'index'])->name('ng-reports.index');
        Route::get('/ng-reports/dashboard', [NgReportController::class, 'dashboard'])->name('ng-reports.dashboard');
        Route::get('/ng-reports/dashboard/export', [NgReportController::class, 'exportDashboard'])->name('ng-reports.dashboard.export');
        Route::get('/ng-reports/download-template', [NgReportController::class, 'downloadTemplate'])->name('ng-reports.download-template');
    });
    Route::middleware('permission:ng.create')->group(function () {
        Route::post('/ng-reports', [NgReportController::class, 'store'])->name('ng-reports.store');
        Route::post('/ng-reports/upload-template', [NgReportController::class, 'uploadPicaTemplate'])->name('ng-reports.upload-template');
        Route::post('/ng-reports/{ngReport}/temporary-action', [NgReportController::class, 'submitTemporaryAction'])->name('ng-reports.submit-ta');
        Route::post('/ng-reports/{ngReport}/upload-pica', [NgReportController::class, 'uploadPica'])->name('ng-reports.upload-pica');
    });
    Route::middleware('permission:ng.approve')->group(function () {
        Route::post('/ng-reports/{ngReport}/temporary-action/approve', [NgReportController::class, 'approveTemporaryAction'])->name('ng-reports.approve-ta');
        Route::post('/ng-reports/{ngReport}/temporary-action/reject', [NgReportController::class, 'rejectTemporaryAction'])->name('ng-reports.reject-ta');
        Route::post('/ng-reports/{ngReport}/pica/approve', [NgReportController::class, 'approvePica'])->name('ng-reports.approve-pica');
        Route::post('/ng-reports/{ngReport}/pica/reject', [NgReportController::class, 'rejectPica'])->name('ng-reports.reject-pica');
        Route::post('/ng-reports/{ngReport}/cancel-pica', [NgReportController::class, 'cancelPica'])->name('ng-reports.cancel-pica');
    });
    Route::post('/ng-reports/{ngReport}/close', [NgReportController::class, 'closeReport'])->middleware('permission:ng.close')->name('ng-reports.close');
    Route::delete('/ng-reports/delete-template', [NgReportController::class, 'deletePicaTemplate'])->middleware('permission:ng.delete')->name('ng-reports.delete-template');
    Route::resource('ng-reports', NgReportController::class)->only(['destroy'])->middleware('permission:ng.delete');

    // ── Suppliers ─────────────────────────────────────────────────────────────
    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('permission:suppliers.view')->name('suppliers.index');
    Route::middleware('permission:suppliers.edit')->group(function () {
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::post('/suppliers/import', [SupplierController::class, 'import']);
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    // ── Parts ─────────────────────────────────────────────────────────────────
    Route::get('/parts', [PartController::class, 'index'])->middleware('permission:parts.view')->name('parts.index');
    Route::middleware('permission:parts.edit')->group(function () {
        Route::post('/parts', [PartController::class, 'store'])->name('parts.store');
        Route::put('/parts/{part}', [PartController::class, 'update'])->name('parts.update');
        Route::post('/parts/import', [PartController::class, 'import'])->name('parts.import');
        Route::delete('/parts/{part}', [PartController::class, 'destroy'])->name('parts.destroy');
    });

    // ── Die Shop ──────────────────────────────────────────────────────────────
    Route::get('die-shop-dashboard', [DieShopDashboardController::class, 'index'])->middleware('permission:die-shop.view')->name('die-shop-dashboard');
    Route::middleware('permission:die-shop.view')->group(function () {
        Route::resource('die-parts', DiePartController::class)->only(['index', 'show']);
        Route::resource('die-shop-reports', DieShopReportController::class)->only(['index', 'show']);
    });
    Route::middleware('permission:die-shop.create')->group(function () {
        Route::resource('die-parts', DiePartController::class)->only(['store']);
        Route::resource('die-shop-reports', DieShopReportController::class)->only(['store']);
        Route::post('die-shop-reports/{dieShopReport}/quick-complete', [DieShopReportController::class, 'quickComplete'])->name('die-shop-reports.quick-complete');
    });
    Route::middleware('permission:die-shop.edit')->group(function () {
        Route::resource('die-parts', DiePartController::class)->only(['update']);
        Route::resource('die-shop-reports', DieShopReportController::class)->only(['update']);
    });
    Route::middleware('permission:die-shop.delete')->group(function () {
        Route::resource('die-parts', DiePartController::class)->only(['destroy']);
        Route::resource('die-shop-reports', DieShopReportController::class)->only(['destroy']);
    });

    // ── ESP32 / Robot ─────────────────────────────────────────────────────────
    Route::middleware('permission:esp32.view')->group(function () {
        Route::get('/esp32/monitor', [ESP32Controller::class, 'monitor'])->name('esp32.monitor');
        Route::get('/esp32/monitor/{device_id}', [ESP32Controller::class, 'detail'])->name('esp32.detail');
    });
    Route::middleware('permission:esp32.edit')->group(function () {
        Route::post('/esp32/monitor/update-settings', [ESP32Controller::class, 'updateSettings'])->name('esp32.update-settings');
        Route::post('/esp32/monitor/update-history-ng', [ESP32Controller::class, 'updateHistoryNg'])->name('esp32.update-history-ng');
        Route::post('/esp32/monitor/update-schedule', [ESP32Controller::class, 'updateSchedule'])->name('esp32.update-schedule');
    });

    // ── Materials ─────────────────────────────────────────────────────────────
    Route::middleware('permission:materials.view')->group(function () {
        Route::get('materials/search/api', [MaterialController::class, 'searchApi'])->name('materials.search');
        Route::get('/materials/download-template', [MaterialController::class, 'downloadTemplate'])->name('materials.download-template');
        Route::resource('materials', MaterialController::class)->only(['index', 'show']);
    });
    Route::middleware('permission:materials.edit')->group(function () {
        Route::post('/materials/import', [MaterialController::class, 'import'])->name('materials.import');
        Route::resource('materials', MaterialController::class)->only(['store', 'update', 'destroy']);
    });

    // ── Part Materials ────────────────────────────────────────────────────────
    Route::middleware('permission:materials.view')->group(function () {
        Route::resource('part-materials', PartMaterialController::class)->only(['index', 'show']);
    });
    Route::middleware('permission:materials.edit')->group(function () {
        Route::post('/part-materials/import', [PartMaterialController::class, 'import'])->name('part-materials.import');
        Route::post('/part-materials/delete-multiple', [PartMaterialController::class, 'deleteMultiple'])->name('part-materials.delete-multiple');
        Route::resource('part-materials', PartMaterialController::class)->only(['store', 'update', 'destroy']);
    });

    // ── Transaksi Material ────────────────────────────────────────────────────
    Route::get('/transaksi/dashboard', [DashboardTransaksiController::class, 'index'])->middleware('permission:transaksi.dashboard')->name('transaksi.dashboard');
    Route::middleware('permission:transaksi.view')->group(function () {
        Route::get('transaksi/history/view', [TransaksiMaterialController::class, 'history'])->name('transaksi.history');
        Route::get('/transaksi/export-data', [TransaksiMaterialController::class, 'exportData'])->name('transaksi.export-data');
        Route::get('/transaksi/search-for-return', [TransaksiMaterialController::class, 'searchForReturn'])->name('transaksi.search-for-return');
        Route::resource('transaksi', TransaksiMaterialController::class)->only(['index', 'show']);
        Route::get('/transaksi/{transaksi}/pengembalian-history', [PengembalianMaterialController::class, 'getPengembalianHistory']);
    });
    Route::middleware('permission:transaksi.create')->group(function () {
        Route::resource('transaksi', TransaksiMaterialController::class)->only(['create', 'store']);
        Route::post('/pengembalian', [PengembalianMaterialController::class, 'store'])->name('pengembalian.store');
    });
    Route::middleware('permission:transaksi.delete')->group(function () {
        Route::post('transaksi/delete-multiple', [TransaksiMaterialController::class, 'deleteMultiple'])->name('transaksi.delete-multiple');
        Route::resource('transaksi', TransaksiMaterialController::class)->only(['destroy']);
        Route::delete('/pengembalian/{pengembalian}', [PengembalianMaterialController::class, 'destroy'])->name('pengembalian.destroy');
    });

    // ── Maintenance ───────────────────────────────────────────────────────────
    Route::middleware('permission:maintenance.view')->group(function () {
        Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
        Route::get('/maintenance/dashboard', [MaintenanceController::class, 'dashboard'])->name('maintenance.dashboard');
    });
    Route::middleware('permission:maintenance.create')->group(function () {
        Route::post('/maintenance', [MaintenanceController::class, 'store'])->name('maintenance.store');
        Route::post('/maintenance/scan-barcode', [MaintenanceController::class, 'scanQrCode'])->name('maintenance.scan-barcode');
        Route::post('/maintenance/{id}/start', [MaintenanceController::class, 'startRepair'])->name('maintenance.start')->where('id', '[0-9]+');
        Route::post('/maintenance/{id}/complete', [MaintenanceController::class, 'completeRepair'])->name('maintenance.complete')->where('id', '[0-9]+');
    });
    Route::delete('/maintenance/{id}', [MaintenanceController::class, 'destroy'])->middleware('permission:maintenance.delete')->name('maintenance.destroy')->where('id', '[0-9]+');

    // ── Machines ──────────────────────────────────────────────────────────────
    Route::prefix('maintenance/mesin')->name('maintenance.mesin.')->group(function () {
        Route::get('/', [MachineController::class, 'index'])->middleware('permission:lines.view')->name('index');
        Route::get('/{id}/metrics', [MachineController::class, 'metrics'])->middleware('permission:lines.view')->name('metrics');
        Route::middleware('permission:machines.edit')->group(function () {
            Route::post('/', [MachineController::class, 'store'])->name('store');
            Route::put('/{id}', [MachineController::class, 'update'])->name('update');
            Route::delete('/{id}', [MachineController::class, 'destroy'])->name('destroy');
        });
    });

    // ── Lines ─────────────────────────────────────────────────────────────────
    Route::prefix('maintenance/lines')->name('maintenance.lines.')->group(function () {
        Route::middleware('permission:lines.view')->group(function () {
            Route::get('/', [LineController::class, 'index'])->name('index');
            Route::get('/{id}', [LineController::class, 'show'])->name('show');
            Route::get('/{lineId}/active-reports', [LineController::class, 'getActiveReports'])->name('active-reports');
            Route::get('/{line}/history', [LineController::class, 'history'])->name('history');
            Route::get('/{line}/summary', [LineController::class, 'getSummary'])->name('summary');
        });
        Route::middleware('permission:lines.edit')->group(function () {
            Route::post('/', [LineController::class, 'store'])->name('store');
            Route::put('/{id}', [LineController::class, 'update'])->name('update');
            Route::delete('/{id}', [LineController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/reset', [LineController::class, 'resetMetrics'])->name('reset');
            Route::post('/{id}/schedule', [LineController::class, 'updateSchedule'])->name('update-schedule');
            Route::post('/line-stop', [LineController::class, 'lineStop'])->name('line-stop');
            Route::post('/scan-qr', [LineController::class, 'scanQrCode'])->name('scan-qr');
            Route::post('/reports/{reportId}/complete', [LineController::class, 'quickComplete'])->name('reports.complete');
            Route::post('/{line}/reset-metrics', [LineController::class, 'resetMetrics'])->name('reset-metrics');
        });
    });

    // ── Line Operations ───────────────────────────────────────────────────────
    Route::prefix('maintenance/operations')->middleware('permission:maintenance.create')->group(function () {
        Route::post('/start', [LineOperationController::class, 'startOperation'])->name('maintenance.operations.start');
        Route::post('/{operationId}/pause', [LineOperationController::class, 'pauseOperation'])->name('maintenance.operations.pause');
        Route::post('/{operationId}/resume', [LineOperationController::class, 'resumeOperation'])->name('maintenance.operations.resume');
        Route::post('/{operationId}/stop', [LineOperationController::class, 'stopOperation'])->name('maintenance.operations.stop');
        Route::get('/lines/{lineId}/current', [LineOperationController::class, 'getCurrentOperation'])->middleware('permission:maintenance.view')->name('maintenance.operations.current');
    });

    // ── OEE ───────────────────────────────────────────────────────────────────
    Route::prefix('oee')->name('oee.')->group(function () {
        Route::middleware('permission:oee.view')->group(function () {
            Route::get('/', [OeeController::class, 'index'])->name('index');
            Route::get('/{oeeRecord}', [OeeController::class, 'show'])->name('show');
            Route::get('/chart/data', [OeeController::class, 'chartData'])->name('chart-data');
        });
        Route::post('/calculate', [OeeController::class, 'calculate'])->middleware('permission:oee.calculate')->name('calculate');
        Route::delete('/{oeeRecord}', [OeeController::class, 'destroy'])->middleware('permission:oee.delete')->name('destroy');
        Route::get('/export/data', [OeeController::class, 'export'])->middleware('permission:oee.export')->name('export');
        Route::post('/compare', [OeeController::class, 'compare'])->middleware('permission:oee.view')->name('compare');
    });

    // ── Products ──────────────────────────────────────────────────────────────
    Route::middleware('permission:stock.view')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    });
    Route::middleware('permission:stock.edit')->group(function () {
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });

    // ── Kanban ────────────────────────────────────────────────────────────────
    Route::middleware('permission:stock.view')->group(function () {
        Route::get('/kanbans', [KanbanController::class, 'index'])->name('kanbans.index');
        Route::get('/kanbans/{kanban}', [KanbanController::class, 'show'])->name('kanbans.show');
        Route::get('/kanbans/history/{rfidTag}', [KanbanController::class, 'history'])->name('kanbans.history');
        Route::post('/kanbans/rfid-master', [KanbanController::class, 'getRfidMasterData']);
        Route::post('/kanbans/check-rfid', [KanbanController::class, 'checkRfid'])->name('kanbans.check');
    });
    Route::middleware('permission:stock.edit')->group(function () {
        Route::post('/kanbans', [KanbanController::class, 'store'])->name('kanbans.store');
        Route::delete('/kanbans/{kanban}', [KanbanController::class, 'destroy'])->name('kanbans.destroy');
        Route::post('/kanbans/master', [KanbanController::class, 'storeMaster'])->name('kanbans.master.store');
        Route::put('/kanbans/master/{rfidMaster}', [KanbanController::class, 'updateMaster'])->name('kanbans.master.update');
        Route::delete('/kanbans/master/{rfidMaster}', [KanbanController::class, 'destroyMaster'])->name('kanbans.master.destroy');
        Route::post('/kanbans/scan', [KanbanController::class, 'scan'])->name('kanbans.scan');
    });

    Route::post('/ai/chat', [AiChatController::class, 'chat']);
    Route::get('/ai/alerts', [AiChatController::class, 'alerts']);
    Route::get('/ai/export-data', [AiChatController::class, 'exportData']);
});

require __DIR__.'/settings.php';
