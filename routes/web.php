<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PermitController;
use App\Http\Controllers\PermitQrController;
use App\Http\Controllers\PermitRouteMapController;
use App\Http\Controllers\RoadSegmentController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\UserController;
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

    Route::resource('users', UserController::class)
        ->middleware('role:' . implode(',', User::rolesForRoute('users.index')));

    Route::middleware('role:' . implode(',', User::rolesForRoute('road-segments.index')))->group(function () {
        Route::get('/road-segments', [RoadSegmentController::class, 'index'])->name('road-segments.index');
    });

    Route::get('/road-segments/{roadSegment}/map', [RoadSegmentController::class, 'map'])
        ->middleware('role:' . implode(',', User::rolesForRoute('road-segments.map')))
        ->name('road-segments.map');

    Route::post('/road-segments/{roadSegment}/map', [RoadSegmentController::class, 'updateMap'])
        ->middleware('role:' . implode(',', User::rolesForRoute('road-segments.map.update')))
        ->name('road-segments.map.update');

    Route::delete('/road-segments/{roadSegment}/map', [RoadSegmentController::class, 'resetMap'])
        ->middleware('role:' . implode(',', User::rolesForRoute('road-segments.map.reset')))
        ->name('road-segments.map.reset');

    Route::middleware('role:' . implode(',', User::rolesForRoute('imports.index')))->group(function () {
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::get('/imports/{importBatch}', [ImportController::class, 'show'])->name('imports.show');
    });

    Route::post('/imports', [ImportController::class, 'store'])
        ->middleware('role:' . implode(',', User::rolesForRoute('imports.store')))
        ->name('imports.store');

    Route::post('/imports/{importBatch}/commit', [ImportController::class, 'commit'])
        ->middleware('role:' . implode(',', User::rolesForRoute('imports.commit')))
        ->name('imports.commit');

    Route::middleware('role:' . implode(',', User::rolesForRoute('permits.index')))->group(function () {
        Route::get('/permits', [PermitController::class, 'index'])->name('permits.index');
    });

    Route::get('/permits/{permit}/route-map', [PermitRouteMapController::class, 'show'])
        ->middleware('role:' . implode(',', User::rolesForRoute('permits.route-map.show')))
        ->name('permits.route-map.show');

    Route::post('/permits/qr/bulk-generate', [PermitQrController::class, 'bulkGenerate'])
        ->middleware('role:' . implode(',', User::rolesForRoute('permits.qr.bulk-generate')))
        ->name('permits.qr.bulk-generate');

    Route::post('/permits/{permit}/qr/generate', [PermitQrController::class, 'generate'])
        ->middleware('role:' . implode(',', User::rolesForRoute('permits.qr.generate')))
        ->name('permits.qr.generate');

    Route::get('/permits/{permit}/qr', [PermitQrController::class, 'show'])
        ->middleware('role:' . implode(',', User::rolesForRoute('permits.qr.show')))
        ->name('permits.qr.show');

    Route::post('/permits/{permit}/qr/print', [PermitQrController::class, 'print'])
        ->middleware('role:' . implode(',', User::rolesForRoute('permits.qr.print')))
        ->name('permits.qr.print');

    Route::post('/permits/{permit}/qr/renew', [PermitQrController::class, 'renew'])
        ->middleware('role:' . implode(',', User::rolesForRoute('permits.qr.renew')))
        ->name('permits.qr.renew');

    Route::middleware('role:' . implode(',', User::rolesForRoute('scan.index')))->group(function () {
        Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    });

    Route::post('/scan/verify', [ScanController::class, 'verify'])
        ->middleware([
            'role:' . implode(',', User::rolesForRoute('scan.verify')),
            'throttle:60,1',
        ])
        ->name('scan.verify');
});
