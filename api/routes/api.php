<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GateController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\TaskCompletionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskItemController;
use App\Http\Controllers\TimerController;
use App\Http\Controllers\TodayController;
use Illuminate\Support\Facades\Route;

// The password curtain (SPEC §5.1): these stay reachable without a token.
// Five password tries a minute per IP (SPEC addendum §14).
Route::get('/gate', [GateController::class, 'show'])->name('gate.show');
Route::post('/gate', [GateController::class, 'store'])->middleware('throttle:5,1')->name('gate.store');
Route::delete('/gate', [GateController::class, 'destroy'])->name('gate.destroy');

Route::middleware('gate')->group(function () {
    Route::get('/today', TodayController::class)->name('today');
    Route::get('/stats', StatsController::class)->name('stats');
    Route::apiResource('categories', CategoryController::class)->except('show');
    Route::apiResource('sub-categories', SubCategoryController::class)
        ->only(['store', 'update', 'destroy'])
        ->parameters(['sub-categories' => 'subCategory']);
    Route::apiResource('tasks', TaskController::class)->except('show');

    // A task's checklist: /tasks/{task}/items to list and add, /items/{item} for one item.
    Route::apiResource('tasks.items', TaskItemController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->shallow()
        ->parameters(['items' => 'taskItem']);

    Route::post('/tasks/{task}/complete', [TaskCompletionController::class, 'store'])->name('tasks.complete');
    Route::post('/tasks/{task}/uncomplete', [TaskCompletionController::class, 'destroy'])->name('tasks.uncomplete');

    Route::get('/timer/running', [TimerController::class, 'running'])->name('timer.running');
    Route::controller(TimerController::class)
        ->prefix('/tasks/{task}/timer')
        ->name('timer.')
        ->group(function () {
            Route::post('/start', 'start')->name('start');
            Route::post('/pause', 'pause')->name('pause');
            Route::post('/resume', 'resume')->name('resume');
            Route::post('/stop', 'stop')->name('stop');
            Route::post('/reset', 'reset')->name('reset');
        });
});
