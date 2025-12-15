<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\DieShopApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ESP32ApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// ESP32
Route::prefix('esp32')->group(function () {
    Route::post('/post', [ESP32ApiController::class, 'postData'])
        ->name('api.esp32.post');
});


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // NG Reports
    Route::prefix('ng-reports')->group(function () {
        Route::get('/', [ApiController::class, 'getNgReports']);
        Route::post('/', [ApiController::class, 'createNgReport']);
        Route::delete('/{id}', [ApiController::class, 'deleteNgReport']);
    });

    // Suppliers
    Route::prefix('suppliers')->group(function () {
        Route::get('/', [ApiController::class, 'getSuppliers']);
    });

    // Parts
    Route::prefix('parts')->group(function () {
        Route::get('/', [ApiController::class, 'getParts']);
    });

    // Die Shop
    Route::prefix('die-shop')->group(function () {
        Route::prefix('die-parts')->group(function () {
            Route::get('/', [DieShopApiController::class, 'getDieParts']);
            Route::post('/', [DieShopApiController::class, 'createDiePart']);
        });
        Route::prefix('reports')->group(function () {
            Route::get('/', [DieShopApiController::class, 'getDieShopReports']);
            Route::post('/', [DieShopApiController::class, 'createDieShopReport']);
            Route::put('/{id}', [DieShopApiController::class, 'updateDieShopReport']);
            Route::delete('/{id}', [DieShopApiController::class, 'deleteDieShopReport']);
        });
        Route::get('/dashboard', [DieShopApiController::class, 'getDashboardStats']);
    });

    // ESP32 Monitor API
    Route::prefix('esp32')->group(function () {
        Route::get('/status', [ESP32ApiController::class, 'getStatus'])
            ->name('api.esp32.status');
        Route::get('/history/{deviceId}', [ESP32ApiController::class, 'getHistory'])
            ->name('api.esp32.history');
        Route::get('/devices', [ESP32ApiController::class, 'getDevices'])
            ->name('api.esp32.devices');
    });
});
