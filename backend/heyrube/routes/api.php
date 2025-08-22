<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JournalController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function() {
    Route::get('journals', [JournalController::class, 'index']);
    Route::post('journals', [JournalController::class, 'store']);
    Route::get('journals/{journal}', [JournalController::class, 'show']);
    Route::put('journals/{journal}', [JournalController::class, 'update']);
    Route::delete('journals/{journal}', [JournalController::class, 'destroy']);
    Route::get('journals/{journal}/entries', [JournalController::class, 'entries']);
    Route::post('journals/{journal}/entries', [JournalController::class, 'storeEntry']);
    Route::post('journals/{journal}/entries/reorder', [JournalController::class, 'reorderEntries']);
    Route::delete('journals/{journal}/entries/{entry}', [JournalController::class, 'destroyEntry']);
    Route::put('journals/{journal}/entries/{entry}', [JournalController::class, 'updateEntry']);
    
    // Trash routes for journals
    Route::get('trash/journals', [JournalController::class, 'trash']);
    Route::post('trash/journals/{id}/restore', [JournalController::class, 'restore']);
    Route::delete('trash/journals/{id}', [JournalController::class, 'forceDestroy']);
    
    // Trash routes for entries
    Route::get('trash/entries', [JournalController::class, 'trashedEntries']);
    Route::post('trash/entries/{id}/restore', [JournalController::class, 'restoreEntry']);
    Route::delete('trash/entries/{id}', [JournalController::class, 'forceDestroyEntry']);
    
    // Empty all trash
    Route::delete('trash/empty', [JournalController::class, 'emptyTrash']);
});
