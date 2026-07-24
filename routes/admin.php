<?php

use App\Http\Controllers\Admin\SemenCustodyController;
use App\Http\Controllers\Admin\SupplyController;
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
    });
