<?php

use App\Http\Controllers\TaskController;
use App\Http\Controllers\TodayController;
use Illuminate\Support\Facades\Route;

Route::get('/today', TodayController::class)->name('today');
Route::apiResource('tasks', TaskController::class)->except('show');
