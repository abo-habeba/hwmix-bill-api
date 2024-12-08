<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::controller(UserController::class)
        ->group(function () {
            Route::get('users', 'index');
            Route::post('users', 'store');
            Route::get('users/{user}', 'show');
            Route::put('users/{user}', 'update');
            Route::put('users-theme/{user}', 'usersTheme');
            Route::post('users/delete', 'destroy');
        });
});