<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PrinterController as AdminPrinterController;
use App\Http\Controllers\Admin\PrintJobController as AdminPrintJobController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PrintJobController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()->isAdmin()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth', 'role:employee'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('print-jobs', PrintJobController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('/print-jobs/{printJob}/cancel', [PrintJobController::class, 'cancel'])->name('print-jobs.cancel');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
    Route::resource('users', AdminUserController::class)->except(['show']);
    Route::resource('printers', AdminPrinterController::class)->only(['index', 'create', 'store', 'show', 'update']);
    Route::resource('print-jobs', AdminPrintJobController::class)->only(['index', 'show']);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
