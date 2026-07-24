<?php

use App\Http\Controllers\Cattle\CattleController;
use App\Http\Controllers\Cattle\HealthRecordController;
use App\Http\Controllers\Cattle\MediaController;
use App\Http\Controllers\Client\CommunicationPreferenceController;
use App\Http\Controllers\Client\StaffChannelOverrideController;
use App\Http\Controllers\Onboarding\ClientOnboardingController;
use App\Http\Controllers\Public\AiTimingCalculatorController;
use App\Http\Controllers\Public\DueDateCalculatorController;
use App\Http\Controllers\Records\ExportController;
use App\Http\Controllers\Team\TeamInvitationController;
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

// M4 — Herd records portal (spec §5.5, §6, §10b).
Route::middleware(['auth', 'verified'])->group(function () {
    // Cattle profiles CRUD (#226).
    Route::resource('cattle', CattleController::class)->parameters(['cattle' => 'cattle']);

    // Health records nested under an animal (#227).
    Route::post('cattle/{cattle}/records', [HealthRecordController::class, 'store'])->name('cattle.records.store');
    Route::put('records/{record}', [HealthRecordController::class, 'update'])->name('records.update');
    Route::delete('records/{record}', [HealthRecordController::class, 'destroy'])->name('records.destroy');

    // Photo/media uploads (#228).
    Route::post('cattle/{cattle}/media', [MediaController::class, 'store'])->name('cattle.media.store');
    Route::get('media/{media}', [MediaController::class, 'show'])->name('cattle.media.show');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('cattle.media.destroy');

    // Data export — scoped to the requesting client's team (#229).
    Route::get('records/export', [ExportController::class, 'show'])->name('records.export');
    Route::get('records/export/download', [ExportController::class, 'download'])->name('records.export.download');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
