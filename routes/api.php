<?php

use App\Http\Controllers\CategoryTypeController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\CopyController;
use App\Http\Controllers\FindingController;
use App\Http\Controllers\PendingUserController;
use App\Http\Controllers\PublishChecklistController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);

Route::middleware(['api-key'])->group(function () {
    Route::resource('pending-users', PendingUserController::class)->only(['index', 'store']);
    Route::post('changepass/{employeeId}', [PendingUserController::class, 'changePassword']);
    Route::patch('reset/{employeeId}', [PendingUserController::class, 'resetPassword']);
});

Route::middleware(['auth:sanctum'])->group(function () {

    Route::group(['middleware' => 'can:admin'], function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);
        Route::get('pending-users', [PendingUserController::class, 'index']);
        Route::post('suppliers/import', [SupplierController::class, 'import']);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('category-types', CategoryTypeController::class);
        Route::post('checklists/{checklist}/publish', [CopyController::class, 'publish']);
        Route::delete('checklists/multiple', [ChecklistController::class, 'multipleDestroy']);
        Route::apiResource('checklists', ChecklistController::class);
        Route::get('publish-checklists', [PublishChecklistController::class, 'index']);
        Route::delete('publish-checklists/close/{id}', [CopyController::class, 'destroy']);
    });

    //DROPDOWN
    Route::get('dropdown/category-types', [CategoryTypeController::class, 'index']);

    Route::get('publish-checklists/{copy}', [CopyController::class, 'show']);
    Route::get('published-checklist/mine', [CopyController::class, 'showPublishedPerUser']);
    Route::apiResource('published-checklist/mine/response', ResponseController::class);

    Route::apiResource('findings', FindingController::class)->only(['store']);

    //COUNTS
    Route::get('count-checklists', [CopyController::class, 'countChecklist']);

    Route::post('logout', [AuthController::class, 'logout']);
});

