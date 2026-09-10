<?php

use App\Http\Controllers\Api\PrinterHeartbeatController;
use App\Http\Controllers\Api\PrinterJobController;
use Illuminate\Support\Facades\Route;

Route::prefix('printer')
    ->middleware(['printer.auth', 'throttle:120,1'])
    ->name('api.printer.')
    ->group(function () {
        Route::post('/heartbeat', PrinterHeartbeatController::class)->name('heartbeat');
        Route::get('/jobs/next', [PrinterJobController::class, 'next'])->name('jobs.next');
        Route::post('/jobs/{printJob}/claim', [PrinterJobController::class, 'claim'])->name('jobs.claim');
        Route::get('/jobs/{printJob}/download', [PrinterJobController::class, 'download'])->name('jobs.download');
        Route::post('/jobs/{printJob}/printing', [PrinterJobController::class, 'printing'])->name('jobs.printing');
        Route::post('/jobs/{printJob}/printed', [PrinterJobController::class, 'printed'])->name('jobs.printed');
        Route::post('/jobs/{printJob}/failed', [PrinterJobController::class, 'failed'])->name('jobs.failed');
    });
