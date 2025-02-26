<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DesignController;

// API routes for authenticated users
Route::middleware('auth:sanctum')->group(function () {
    // User info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Design endpoints
    Route::prefix('designs')->group(function () {
        Route::get('/', [DesignController::class, 'index']);
        Route::post('/', [DesignController::class, 'store']);
        Route::get('/{design}', [DesignController::class, 'show']);
        Route::put('/{design}', [DesignController::class, 'update']);
        Route::delete('/{design}', [DesignController::class, 'destroy']);
        
        // Design versions
        Route::get('/{design}/versions', [DesignController::class, 'versions']);
        Route::post('/{design}/versions/{version}/restore', [DesignController::class, 'restoreVersion']);
    });
});