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

Route::get('/home', function () {
    return redirect()->route('dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:' . User::ROLE_ADMIN_HR . ',' . User::ROLE_AUDITOR)->group(function () {
        Route::get('/road-segments', [RoadSegmentController::class, 'index'])->name('road-segments.index');
    });

    Route::middleware('role:' . User::ROLE_ADMIN_HR)->group(function () {
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::get('/permits', [PermitController::class, 'index'])->name('permits.index');
    });

    Route::middleware('role:' . User::ROLE_SECURITY)->group(function () {
        Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    });
});
