<?php

use App\Http\Controllers\Api\ApiController;
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

//     // Suppliers
//     Route::get('/suppliers', [ApiController::class, 'getSuppliers']);

//     // Parts
//     Route::get('/parts', [ApiController::class, 'getParts']);

//     // NG Reports
//     Route::get('/ng-reports', [ApiController::class, 'getNgReports']);
//     Route::post('/ng-reports', [ApiController::class, 'createNgReport']);
//     Route::delete('/ng-reports/{id}', [ApiController::class, 'deleteNgReport']);

// });
