<?php

use App\Http\Controllers\Api\WpJson\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| /wp-json/*  — the old WordPress REST address space, kept so the Flutter app
| keeps working after uaa.ae moves here. Loaded with the "wp-json" prefix
| (bootstrap/app.php). Answers use WordPress' reply shapes on purpose.
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:wpjson-login')->group(function () {
    Route::post('custom/v1/login', [AuthController::class, 'login']);
    Route::post('custom/v1/register', [AuthController::class, 'register']);
    Route::post('jwt-auth/v1/token', [AuthController::class, 'token']);
});
Route::post('jwt-auth/v1/token/validate', [AuthController::class, 'validateToken'])->middleware('throttle:60,1');
