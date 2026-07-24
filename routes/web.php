<?php

use App\Http\Controllers\Client\CommunicationPreferenceController;
use App\Http\Controllers\Client\StaffChannelOverrideController;
use App\Http\Controllers\Onboarding\ClientOnboardingController;
use App\Http\Controllers\Team\TeamInvitationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// M3 — Clients, teams, onboarding (spec §4, §5.6, §5.7, §10b).
Route::middleware('auth')->group(function () {
    // Client onboarding + agreement/waiver (#222, §4, §5.6).
    Route::get('onboarding', [ClientOnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding', [ClientOnboardingController::class, 'store'])->name('onboarding.store');

    // Team invitations — member + vet (#224, §4, §10b).
    Route::get('team/invitations', [TeamInvitationController::class, 'index'])->name('team-invitations.index');
    Route::post('team/invitations', [TeamInvitationController::class, 'store'])->name('team-invitations.store');
    Route::delete('team/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('team-invitations.destroy');
    Route::get('team-invitations/{token}', [TeamInvitationController::class, 'show'])->name('team-invitations.show');
    Route::post('team-invitations/{token}', [TeamInvitationController::class, 'accept'])->name('team-invitations.accept');

    // Communication preferences & consent (#225, §5.7).
    Route::get('settings/communications', [CommunicationPreferenceController::class, 'edit'])->name('communications.edit');
    Route::put('settings/communications', [CommunicationPreferenceController::class, 'update'])->name('communications.update');

    // Staff channel override per client (#225, §5.7).
    Route::put('clients/{client}/channel-override', [StaffChannelOverrideController::class, 'update'])->name('clients.channel-override.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
