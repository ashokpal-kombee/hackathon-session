<?php

use App\Http\Controllers\Api\LogAnalysisController;
use Illuminate\Support\Facades\Route;

// Log Analysis endpoints
Route::post('/analyze', [LogAnalysisController::class, 'analyze']);
Route::post('/analyze-file', [LogAnalysisController::class, 'analyzeFromFile']);
Route::get('/analysis/{id}', [LogAnalysisController::class, 'show']);
Route::get('/analyses', [LogAnalysisController::class, 'index']);

// Log Import endpoint
Route::post('/logs/import', [LogAnalysisController::class, 'importLogFile']);
Route::post('/logs/debug-import', [LogAnalysisController::class, 'debugImport']);

