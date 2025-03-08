<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\DesignChatController;

// API routes
Route::middleware(['web', 'auth'])->prefix('api')->group(function () {
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
        
        // Lambda-based PDF generation endpoints
        Route::post('/{design}/pdf/generate', [DesignController::class, 'generatePdf']);
        
        // Design versions
        Route::get('/{design}/versions', [DesignController::class, 'versions']);
        Route::post('/{design}/versions/{version}/restore', [DesignController::class, 'restoreVersion']);

        // Design chat
        Route::get('/{design}/chat', [DesignChatController::class, 'getMessages']);
        Route::post('/{design}/chat', [DesignChatController::class, 'sendMessage']);


        
    });

    // PDF Job status and download endpoints
    Route::prefix('pdf-jobs')->group(function () {
        Route::get('/{jobId}/status', [DesignController::class, 'checkPdfStatus']);
        Route::get('/{jobId}/download', [DesignController::class, 'downloadPdfFile']);
    });

    
});