<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncidentController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents');
Route::get('/incidents/export', [IncidentController::class, 'export'])->name('incidents.export');
