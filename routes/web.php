<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PermitController;
use App\Http\Controllers\RoadSegmentController;
use App\Http\Controllers\ScanController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('role:' . implode(',', User::dashboardRoles()))
        ->name('dashboard');

    Route::middleware('role:' . implode(',', User::rolesForRoute('road-segments.index')))->group(function () {
        Route::get('/road-segments', [RoadSegmentController::class, 'index'])->name('road-segments.index');
    });

    Route::middleware('role:' . implode(',', User::rolesForRoute('imports.index')))->group(function () {
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
        Route::get('/imports/{importBatch}', [ImportController::class, 'show'])->name('imports.show');
    });

    Route::middleware('role:' . implode(',', User::rolesForRoute('permits.index')))->group(function () {
        Route::get('/permits', [PermitController::class, 'index'])->name('permits.index');
    });

    Route::middleware('role:' . implode(',', User::rolesForRoute('scan.index')))->group(function () {
        Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    });
});
