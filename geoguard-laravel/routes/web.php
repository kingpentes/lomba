<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncidentController;

Route::redirect('/', '/dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/prediction', [DashboardController::class, 'prediction'])->name('prediction');
Route::get('/early-warning', [DashboardController::class, 'earlyWarning'])->name('early-warning');
Route::get('/dispatcher', [DashboardController::class, 'dispatcher'])->name('dispatcher');
Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents');
Route::get('/incidents/export', [IncidentController::class, 'export'])->name('incidents.export');
