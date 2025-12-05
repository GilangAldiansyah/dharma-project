<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\DieShopApiController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // NG Reports System
    Route::prefix('ng-reports')->group(function () {
        Route::get('/', [ApiController::class, 'getNgReports']);
        Route::post('/', [ApiController::class, 'createNgReport']);
        Route::delete('/{id}', [ApiController::class, 'deleteNgReport']);
    });
    Route::prefix('suppliers')->group(function () {
        Route::get('/', [ApiController::class, 'getSuppliers']);
    });
    Route::prefix('parts')->group(function () {
        Route::get('/', [ApiController::class, 'getParts']);
    });

    // Die Shop System
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
});
