<?php

use App\Http\Controllers\CategoryTypeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);
Route::middleware(['auth:sanctum'])->group(function () {

    Route::group(['middleware' => 'can:admin'], function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('category-types', CategoryTypeController::class);
    });

    Route::post('logout', [AuthController::class, 'logout']);
});
