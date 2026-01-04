<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\ReportsPageController;
use App\Http\Controllers\Api\DomainCheckController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\ContentSuggestionController;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/domain-checker', [ToolController::class, 'index'])
    ->middleware(['auth'])
    ->name('domain.checker');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/reports', [ReportsPageController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [ReportsPageController::class, 'show'])->name('reports.show');

    Route::post('/api/check', [DomainCheckController::class, 'check']);
    Route::get('/api/reports/{report}', [ReportController::class, 'show']);
    Route::get('/api/reports/{report}/export.csv', [ReportController::class, 'exportCsv']);

    Route::post('/content-suggestions/{id}/generate', [ContentSuggestionController::class, 'generate'])
        ->name('content_suggestions.generate');
});

require __DIR__.'/auth.php';
