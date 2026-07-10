<?php

use App\Http\Controllers\Api\AuthenticatedUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| SIRIKA is currently a session-based web application. This authenticated
| user endpoint remains controller-based so production route caching works.
|
*/

Route::middleware('auth:sanctum')->get('/user', AuthenticatedUserController::class);
