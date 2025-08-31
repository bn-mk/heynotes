<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TrashController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function() {
    Route::get('journals', [JournalController::class, 'index']);
    Route::post('journals', [JournalController::class, 'store']);
    Route::get('journals/{journal}', [JournalController::class, 'show']);
    Route::put('journals/{journal}', [JournalController::class, 'update']);
    Route::delete('journals/{journal}', [JournalController::class, 'destroy']);

    // TODO: Move entry routes to EntryController
    Route::get('journals/{journal}/entries', [JournalController::class, 'entries']);
    Route::post('journals/{journal}/entries', [JournalController::class, 'storeEntry']);
    Route::post('journals/{journal}/entries/reorder', [JournalController::class, 'reorderEntries']);
    Route::delete('journals/{journal}/entries/{entry}', [JournalController::class, 'destroyEntry']);
    Route::put('journals/{journal}/entries/{entry}', [JournalController::class, 'updateEntry']);
    Route::post('journals/{journal}/entries/{entry}/pin', [JournalController::class, 'pinEntry']);
    
    // Tag routes
    Route::get('tags', [TagController::class, 'index']);
    Route::post('tags', [TagController::class, 'store']);
   
    // Trash routes
    Route::get('trash/journals', [TrashController::class, 'journals']);
    Route::post('trash/journals/{id}/restore', [TrashController::class, 'restoreJournal']);
    Route::delete('trash/journals/{id}', [TrashController::class, 'forceDestroyJournal']);

    Route::get('trash/entries', [TrashController::class, 'entries']);
    Route::post('trash/entries/{id}/restore', [TrashController::class, 'restoreEntry']);
    Route::delete('trash/entries/{id}', [TrashController::class, 'forceDestroyEntry']);

    Route::delete('trash/empty', [TrashController::class, 'empty']);

    // Links and graph
    Route::post('links', [LinkController::class, 'store']);
    Route::get('links', [LinkController::class, 'index']);
    Route::delete('links', [LinkController::class, 'destroy']);
    Route::get('graph', [LinkController::class, 'graph']);
    Route::get('search', [LinkController::class, 'search']);
});
