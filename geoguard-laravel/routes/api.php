<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelemetryApiController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/v1/telemetry', [TelemetryApiController::class, 'ingest']);
Route::post('/v1/incidents', [TelemetryApiController::class, 'triggerIncident']);
Route::get('/v1/telemetry/latest', [TelemetryApiController::class, 'latest']);
Route::get('/v1/predictions/latest', [TelemetryApiController::class, 'latestPrediction']);
