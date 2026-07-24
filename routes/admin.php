<?php

use App\Http\Controllers\Admin\BlackoutController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BookingVisitController;
use App\Http\Controllers\Admin\PregCheckController;
use App\Http\Controllers\Admin\SemenCustodyController;
use App\Http\Controllers\Admin\SupplyController;
use App\Http\Controllers\Admin\VisitCompletionController;
use App\Http\Middleware\EnsureStaff;
use Illuminate\Support\Facades\Route;

// M5 — staff-only inventory & custody back office (§5.6, §5.6b).
Route::middleware(['auth', 'verified', EnsureStaff::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // #231 — supplies & usage profiles.
        Route::get('supplies', [SupplyController::class, 'index'])->name('supplies.index');
        Route::post('supplies', [SupplyController::class, 'store'])->name('supplies.store');
        Route::patch('supplies/{supply}', [SupplyController::class, 'update'])->name('supplies.update');
        Route::delete('supplies/{supply}', [SupplyController::class, 'destroy'])->name('supplies.destroy');
        Route::post('supply-usage-profiles', [SupplyController::class, 'saveUsageProfile'])->name('supplies.usage-profile');

        // #230 — semen custody ledger.
        Route::get('semen', [SemenCustodyController::class, 'index'])->name('semen.index');
        Route::post('semen', [SemenCustodyController::class, 'store'])->name('semen.store');
        Route::post('semen/{lot}/receive', [SemenCustodyController::class, 'receive'])->name('semen.receive');
        Route::post('semen/{lot}/use', [SemenCustodyController::class, 'use'])->name('semen.use');
        Route::post('semen/{lot}/relocate', [SemenCustodyController::class, 'relocate'])->name('semen.relocate');
        Route::delete('semen/{lot}', [SemenCustodyController::class, 'destroy'])->name('semen.destroy');

        // M6 — staff booking review & manual override (#238), blackouts (#234).
        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
        Route::post('bookings/{booking}/decline', [BookingController::class, 'decline'])->name('bookings.decline');
        Route::patch('visits/{visit}', [BookingVisitController::class, 'update'])->name('visits.update');
        Route::post('visits/{visit}/fail', [BookingVisitController::class, 'fail'])->name('visits.fail');
        Route::post('blackouts', [BlackoutController::class, 'store'])->name('blackouts.store');

        // M7 — appointment completion (#239) + async preg-check results (#241).
        Route::get('visits/{visit}/completion', [VisitCompletionController::class, 'create'])->name('visits.completion.create');
        Route::post('visits/{visit}/completion', [VisitCompletionController::class, 'store'])->name('visits.completion.store');

        Route::get('preg-checks', [PregCheckController::class, 'index'])->name('preg-checks.index');
        Route::post('preg-checks', [PregCheckController::class, 'store'])->name('preg-checks.store');
        Route::post('preg-checks/{pregCheck}/result', [PregCheckController::class, 'result'])->name('preg-checks.result');
    });
