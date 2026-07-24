<?php

use App\Http\Controllers\Admin\DirectoryEntryController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Cattle\CattleController;
use App\Http\Controllers\Cattle\HealthRecordController;
use App\Http\Controllers\Cattle\MediaController;
use App\Http\Controllers\Client\CommunicationPreferenceController;
use App\Http\Controllers\Client\StaffChannelOverrideController;
use App\Http\Controllers\ForSale\ForSaleBoardController;
use App\Http\Controllers\ForSale\ForSaleListingController;
use App\Http\Controllers\Marketing\BlogController;
use App\Http\Controllers\Marketing\FaqController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\PageController;
use App\Http\Controllers\Marketing\PlaceholderController;
use App\Http\Controllers\Marketing\PricingController;
use App\Http\Controllers\Marketing\RedirectController;
use App\Http\Controllers\Marketing\ResourceDirectoryController;
use App\Http\Controllers\Marketing\ServiceController;
use App\Http\Controllers\Onboarding\ClientOnboardingController;
use App\Http\Controllers\Public\AiTimingCalculatorController;
use App\Http\Controllers\Public\DueDateCalculatorController;
use App\Http\Controllers\Records\ExportController;
use App\Http\Controllers\Reporting\AiSuccessReportController;
use App\Http\Controllers\Reporting\ProfitabilityReportController;
use App\Http\Controllers\Team\TeamInvitationController;
use App\Http\Middleware\EnsureUserIsStaff;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * Public marketing site (M2 — spec §5.1). Nav: Home · About · Services ·
 * Pricing · Resources ▾ · Blog · Contact.
 */
Route::get('/', HomeController::class)->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/services', ServiceController::class)->name('services');
Route::get('/pricing', PricingController::class)->name('pricing');
Route::get('/faq', FaqController::class)->name('faq');

// Resources ▾ dropdown (§5.1). AI Timing (2.5) + Due Date (2.6) are the live
// calculators (M1 engine landed); the marketing nav points here. Cattle-for-Sale
// (§5.9) is a later milestone; Resource Directory (2.7) is live below.
Route::prefix('resources')->name('resources.')->group(function () {
    Route::get('/ai-timing-calculator', [AiTimingCalculatorController::class, 'show'])->name('ai-timing');
    Route::get('/due-date-calculator', [DueDateCalculatorController::class, 'show'])->name('due-date');
    Route::get('/directory', ResourceDirectoryController::class)->name('directory');
    Route::get('/cattle-for-sale', [PlaceholderController::class, 'cattleForSale'])->name('cattle-for-sale');
});

// Blog / education engine (§5.3). Category (pillar) pages sit under /blog/category.
Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('/category/{category:slug}', [BlogController::class, 'category'])->name('category');
    Route::get('/{post:slug}', [BlogController::class, 'show'])->name('show');
});

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

    // M11 — Cattle-for-sale board (#250, §5.9). Clients-only bulletin board:
    // the board is visible across authenticated clients; managing a listing is
    // gated to the animal's team (or staff) via the CattlePolicy.
    Route::get('for-sale', [ForSaleBoardController::class, 'index'])->name('for-sale.index');
    Route::get('for-sale/{cattle}', [ForSaleBoardController::class, 'show'])->name('for-sale.show');
    Route::put('cattle/{cattle}/for-sale', [ForSaleListingController::class, 'update'])->name('cattle.for-sale.update');

    // M6 — Booking (#232–#237). Team-scoped client booking flow.
    Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::get('bookings/candidates', [BookingController::class, 'candidates'])->name('bookings.candidates');
    Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');

    // M10 — Reporting (#248, #249). Staff see the aggregate; a client sees only
    // their own team's numbers (scope decided in-controller from the staff flag).
    Route::get('reports/profitability', [ProfitabilityReportController::class, 'index'])->name('reports.profitability');
    Route::get('reports/breeding', [AiSuccessReportController::class, 'index'])->name('reports.breeding');
});

// Staff admin — resource directory CRUD (§5.8). Soft-deletes only (§10b).
Route::middleware(['auth', EnsureUserIsStaff::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('directory', [DirectoryEntryController::class, 'index'])->name('directory.index');
    Route::get('directory/create', [DirectoryEntryController::class, 'create'])->name('directory.create');
    Route::post('directory', [DirectoryEntryController::class, 'store'])->name('directory.store');
    Route::get('directory/{directoryEntry}/edit', [DirectoryEntryController::class, 'edit'])->name('directory.edit');
    Route::put('directory/{directoryEntry}', [DirectoryEntryController::class, 'update'])->name('directory.update');
    Route::delete('directory/{directoryEntry}', [DirectoryEntryController::class, 'destroy'])->name('directory.destroy');
    Route::put('directory/{directoryEntry}/restore', [DirectoryEntryController::class, 'restore'])->name('directory.restore');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Old Homestead Herds URLs → migrated posts (§8, §10b). MUST stay last so it
// only catches paths no other route claimed.
Route::fallback(RedirectController::class);
