<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelemetryApiController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/v1/telemetry', [TelemetryApiController::class, 'ingest']);
Route::post('/v1/incidents', [TelemetryApiController::class, 'triggerIncident']);
Route::post('/v1/nodes/{node_code}/mute-buzzer', [TelemetryApiController::class, 'muteBuzzer']);
Route::post('/v1/nodes/{node_code}/set-interval', [TelemetryApiController::class, 'setInterval']);
Route::get('/v1/telemetry/latest', [TelemetryApiController::class, 'latest']);
Route::get('/v1/predictions/latest', [TelemetryApiController::class, 'latestPrediction']);
Route::post('/v1/nodes/{node_code}/set-location', [TelemetryApiController::class, 'setLocation']);
Route::get('/v1/insar/mode', [TelemetryApiController::class, 'getInsarMode']);
Route::post('/v1/insar/mode', [TelemetryApiController::class, 'setInsarMode']);
