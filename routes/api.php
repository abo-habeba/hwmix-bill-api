<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TransactionController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('/auth/check', [AuthController::class, 'checkLogin']);
    //Auth Controller
    Route::get('me', [AuthController::class, 'me']);
    // User Controller
    Route::controller(UserController::class)
        ->group(function () {
            Route::get('users', 'index');
            Route::post('user', 'store');
            Route::get('user/{user}', 'show');
            Route::put('user/{user}', 'update');
            Route::put('change-company/{user}', 'changeCompany');
            Route::post('users/delete', 'destroy');
        });
    // company Controller
    Route::controller(CompanyController::class)
        ->group(function () {
            Route::get('companys', 'index');
            Route::post('company', 'store');
            Route::get('company/{company}', 'show');
            Route::put('company/{company}', 'update');
            Route::post('company/delete', 'destroy');
        });
    // Transaction Controller
    Route::controller(TransactionController::class)
        ->group(function () {
            Route::post('/transfer', 'transfer');
            Route::post('/deposit', 'deposit');
            Route::post('/withdraw', 'withdraw');
            Route::get('/transactions', 'transactions');
            Route::post('/transactions/{transaction}/reverse', 'reverseTransaction');
        });
    // Role Controller
    Route::controller(RoleController::class)
        ->group(function () {
            Route::get('roles', 'index');
            Route::post('role', 'store');
            Route::get('role/{role}', 'show');
            Route::put('role/{role}', 'update');
            Route::delete('role/{role}', 'destroy');
            Route::post('role/assignRole', 'assignRole');
        });
    // LOgs Controller
    Route::controller(LogController::class)
        ->group(function () {
            Route::get('logs', 'index');
            Route::post('logs/{log}/undo', 'undo');
        });
});
