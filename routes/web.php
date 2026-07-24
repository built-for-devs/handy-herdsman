<?php

use App\Http\Controllers\Public\AiTimingCalculatorController;
use App\Http\Controllers\Public\DueDateCalculatorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

/*
 | Public calculators — the primary lead magnets (spec §5.4, §5.4b; issues
 | 2.5/2.6). Standalone public routes; when the M2 marketing shell lands its
 | "Resources ▾" dropdown should link to these named routes.
 */
Route::prefix('calculators')->name('calculators.')->group(function () {
    Route::get('ai-timing', [AiTimingCalculatorController::class, 'show'])->name('ai-timing');
    Route::post('ai-timing', [AiTimingCalculatorController::class, 'calculate'])->name('ai-timing.calculate');
    Route::post('ai-timing/send', [AiTimingCalculatorController::class, 'send'])->name('ai-timing.send');

    Route::get('due-date', [DueDateCalculatorController::class, 'show'])->name('due-date');
    Route::post('due-date', [DueDateCalculatorController::class, 'calculate'])->name('due-date.calculate');
});

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
