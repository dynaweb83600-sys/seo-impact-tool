<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\ReportsPageController;
use App\Http\Controllers\ContentSuggestionController;
use App\Http\Controllers\DomainCheckerStatusController;

// ✅ API controllers (appelés par le JS du Domain Checker)
use App\Http\Controllers\Api\DomainCheckController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportItemContentSuggestionController;
use App\Http\Controllers\Api\ReportItemBacklinksController;
use App\Http\Controllers\Api\GscApiController;

Route::get('/', fn () => redirect()->route('login'));

// UI Domain Checker
Route::get('/domain-checker', [ToolController::class, 'index'])
    ->middleware(['auth'])
    ->name('domain.checker');

// ✅ Status public par token (ton test curl #1)
Route::get('/domain-checker/status/{token}', [DomainCheckerStatusController::class, 'show']);

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Reports pages (UI)
    Route::get('/reports', [ReportsPageController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [ReportsPageController::class, 'show'])->name('reports.show');

    // ✅ Domain checker (UI -> backend) : on garde en WEB car session + CSRF
    Route::post('/api/check', [DomainCheckController::class, 'check']);

    // ✅ API utilisées par l'UI (IMPORTANT : en WEB+AUTH pour garder la session)

    // ✅ génération HTML (reste en web car CSRF/session)
    Route::post('/content-suggestions/{id}/generate', [ContentSuggestionController::class, 'generate'])
        ->name('content_suggestions.generate');

    // ✅ OAuth GSC (IMPORTANT : en WEB+AUTH)
    Route::get('/gsc/connect', [\App\Http\Controllers\GscController::class, 'connect'])->name('gsc.connect');
    Route::get('/gsc/callback', [\App\Http\Controllers\GscController::class, 'callback'])->name('gsc.callback');
});

require __DIR__ . '/auth.php';
