<?php

use App\Http\Controllers\Admin\DirectoryEntryController;
use App\Http\Controllers\Marketing\BlogController;
use App\Http\Controllers\Marketing\FaqController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\PageController;
use App\Http\Controllers\Marketing\PlaceholderController;
use App\Http\Controllers\Marketing\PricingController;
use App\Http\Controllers\Marketing\RedirectController;
use App\Http\Controllers\Marketing\ResourceDirectoryController;
use App\Http\Controllers\Marketing\ServiceController;
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

// Resources ▾ dropdown (§5.1). AI Timing (2.5) + Due Date (2.6) calculators are
// placeholder shells until the M1 timing engine lands; Cattle-for-Sale (§5.9)
// is a later milestone. Resource Directory (2.7) is live below.
Route::prefix('resources')->name('resources.')->group(function () {
    Route::get('/ai-timing-calculator', [PlaceholderController::class, 'aiTiming'])->name('ai-timing');
    Route::get('/due-date-calculator', [PlaceholderController::class, 'dueDate'])->name('due-date');
    Route::get('/directory', ResourceDirectoryController::class)->name('directory');
    Route::get('/cattle-for-sale', [PlaceholderController::class, 'cattleForSale'])->name('cattle-for-sale');
});

// Blog / education engine (§5.3). Category (pillar) pages sit under /blog/category.
Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('/category/{category:slug}', [BlogController::class, 'category'])->name('category');
    Route::get('/{post:slug}', [BlogController::class, 'show'])->name('show');
});

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Old Homestead Herds URLs → migrated posts (§8, §10b). MUST stay last so it
// only catches paths no other route claimed.
Route::fallback(RedirectController::class);
