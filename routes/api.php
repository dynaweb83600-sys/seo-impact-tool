<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DomainCheckController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportItemController;
use App\Http\Controllers\Api\ReportItemContentSuggestionController;
use App\Http\Controllers\Api\ReportItemBacklinksController;

// ⚠️ si tu gardes POST /api/check dans web.php, enlève celle-ci pour éviter doublons
// Route::post('/check', [DomainCheckController::class, 'check']);

Route::get('/reports/{report}', [ReportController::class, 'show']);
Route::get('/reports/{report}/export.csv', [ReportController::class, 'exportCsv']);
// report items
Route::get('/report-items/{item}/content-suggestions', [ReportItemContentSuggestionController::class, 'index']);
Route::get('/report-items/{item}/backlinks-advice', [ReportItemBacklinksController::class, 'show']);
Route::post('/gsc/disconnect', [\App\Http\Controllers\Api\GscApiController::class, 'disconnect']);
