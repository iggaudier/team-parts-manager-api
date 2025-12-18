<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SystemPartController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamPartController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // System Admin only routes
    Route::middleware('role:system_admin')->group(function () {
        Route::apiResource('system-parts', SystemPartController::class);
        Route::post('system-parts/import', [SystemPartController::class, 'import']);
        Route::get('system-parts/export', [SystemPartController::class, 'export']);
        Route::apiResource('teams', TeamController::class);
    });

    // Team Admin routes
    Route::middleware('role:team_admin')->group(function () {
        Route::post('team-parts/associate', [TeamPartController::class, 'associate']);
        Route::post('team-parts/import', [TeamPartController::class, 'import']);
        Route::delete('team-parts/{teamPart}', [TeamPartController::class, 'destroy']);
    });

    // Team Member and Team Admin routes
    Route::middleware('role:team_member,team_admin')->group(function () {
        Route::get('team-parts', [TeamPartController::class, 'index']);
        Route::get('team-parts/search', [TeamPartController::class, 'search']);
    });
});
