<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\DieShopApiController;
use App\Http\Controllers\ESP32Controller;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

 // Suppliers
    Route::get('/suppliers', [ApiController::class, 'getSuppliers']);

    // Parts
    Route::get('/parts', [ApiController::class, 'getParts']);

    // NG Reports
    Route::get('/ng-reports', [ApiController::class, 'getNgReports']);
    Route::post('/ng-reports', [ApiController::class, 'createNgReport']);
    Route::delete('/ng-reports/{id}', [ApiController::class, 'deleteNgReport']);

// Route::post('/login', [AuthController::class, 'login']);
// Route::post('/register', [AuthController::class, 'register']);

// Route::middleware(['auth:sanctum'])->group(function () {



// });

// Die Shop System
Route::prefix('die-shop')->group(function () {
    // Die Parts
    Route::get('/die-parts', [DieShopApiController::class, 'getDieParts']);
    Route::post('/die-parts', [DieShopApiController::class, 'createDiePart']);

    // Die Shop Reports
    Route::get('/reports', [DieShopApiController::class, 'getDieShopReports']);
    Route::post('/reports', [DieShopApiController::class, 'createDieShopReport']);
    Route::put('/reports/{id}', [DieShopApiController::class, 'updateDieShopReport']);
    Route::delete('/reports/{id}', [DieShopApiController::class, 'deleteDieShopReport']);

    // Dashboard
    Route::get('/dashboard', [DieShopApiController::class, 'getDashboardStats']);


    Route::prefix('esp32')->group(function () {
    Route::post('/post', [ESP32Controller::class, 'postData']);
    Route::get('/status', [ESP32Controller::class, 'getStatus']);
    Route::get('/history/{device_id}', [ESP32Controller::class, 'getHistory']);
    Route::get('/devices', [ESP32Controller::class, 'getDevices']);
});
});
