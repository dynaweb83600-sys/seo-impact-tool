<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DomainCheckController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportItemContentSuggestionController;
use App\Http\Controllers\Api\ReportItemController;
use App\Http\Controllers\Api\ReportItemBacklinksController;

Route::get('/report-items/{item}/content-suggestions', [ReportItemController::class, 'contentSuggestions']);
Route::get('/report-items/{item}/content-suggestions', [ReportItemContentSuggestionController::class, 'index']);
Route::post('/check', [DomainCheckController::class, 'check']);
Route::get('/reports/{report}', [ReportController::class, 'show']);
Route::get('/reports/{report}/export.csv', [ReportController::class, 'exportCsv']);
Route::get('/report-items/{item}/backlinks-advice',[ReportItemBacklinksController::class, 'show']);

