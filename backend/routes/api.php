<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Support\Facades\Route;

// Order matters: auth:sanctum resolves the user first, SetTenantContext
// derives the tenant from it next, and only then can SubstituteBindings
// (route-model-binding for {lead} etc., which the "api" group applies
// implicitly) query tenant-scoped models with the right company_id.
Route::prefix('v1/auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', SetTenantContext::class, EnsureAccountIsActive::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::prefix('v1')->middleware(['auth:sanctum', SetTenantContext::class, EnsureAccountIsActive::class])->group(function () {
    Route::get('leads', [LeadController::class, 'index']);
    Route::post('leads', [LeadController::class, 'store']);
    Route::get('leads/{lead}', [LeadController::class, 'show']);
    Route::patch('leads/{lead}', [LeadController::class, 'update']);
    Route::delete('leads/{lead}', [LeadController::class, 'destroy']);

    Route::get('clients', [ClientController::class, 'index']);
    Route::post('clients', [ClientController::class, 'store']);
    Route::get('clients/{client}', [ClientController::class, 'show']);
    Route::patch('clients/{client}', [ClientController::class, 'update']);
    Route::delete('clients/{client}', [ClientController::class, 'destroy']);

    Route::get('users/assignable', [UserController::class, 'assignable']);
});
