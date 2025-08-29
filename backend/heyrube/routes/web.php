<?php

use App\Http\Controllers\JournalController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');


Route::get('dashboard', [JournalController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');
Route::get('graph', function () { return Inertia::render('Graph'); })->middleware(['auth','verified'])->name('graph');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
