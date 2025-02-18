<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\CashBoxController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\CashBoxTypeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AttributeValueController;
use App\Http\Controllers\ProductVariantController;

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
            Route::get('users/search', 'usersSearch');
            Route::post('user', 'store');
            Route::get('user/{user}', 'show');
            Route::put('user/{user}', 'update');
            Route::put('change-company/{user}', 'changeCompany');
            Route::put('user/{user}/cashbox/{cashBoxId}/set-default', 'setDefaultCashBox');
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
            Route::get('/transactions/user', 'userTransactions');
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
    // cashBoxTypes Controller
    Route::controller(CashBoxTypeController::class)
        ->group(function () {
            Route::get('cashBoxTypes', 'index');
            Route::post('cashBoxType', 'store');
            Route::get('cashBoxType/{cashBoxType}', 'show');
            Route::put('cashBoxType/{cashBoxType}', 'update');
            Route::delete('cashBoxType/{cashBoxType}', 'destroy');
        });
    // CashBox Controller
    Route::controller(CashBoxController::class)
        ->group(function () {
            Route::get('cashBoxs', 'index');
            Route::post('cashBox', 'store');
            Route::get('cashBox/{cashBox}', 'show');
            Route::put('cashBox/{cashBox}', 'update');
            Route::delete('cashBox/{cashBox}', 'destroy');
            Route::post('cashBox/transfer', 'transferFunds');
        });
    // Logs Controller
    Route::controller(LogController::class)
        ->group(function () {
            Route::get('logs', 'index');
            Route::post('logs/{log}/undo', 'undo');
        });

    // Product Controller
    Route::controller(ProductController::class)
        ->group(function () {
            Route::get('products', 'index');
            Route::post('product', 'store');
            Route::get('product/{product}', 'show');
            Route::put('product/{product}', 'update');
            Route::post('product/delete', 'destroy');
        });
    // Attribute Controller
    Route::controller(AttributeController::class)
        ->group(function () {
            Route::get('attributes', 'index');
            Route::post('attribute', 'store');
            Route::get('attribute/{attribute}', 'show');
            Route::put('attribute/{attribute}', 'update');
            Route::delete('attribute/{attribute}', 'destroy');
            Route::post('attribute/deletes', 'deleteMultiple');
        });
    // Attribute Value Controller
    Route::controller(AttributeValueController::class)
        ->group(function () {
            Route::get('attribute-values', 'index');
            Route::post('attribute-value', 'store');
            Route::get('attribute-value/{attributeValue}', 'show');
            Route::put('attribute-value/{attributeValue}', 'update');
            Route::delete('attribute-value/{attributeValue}', 'destroy');
            Route::post('attribute-value/deletes', 'deleteMultiple');
        });
    // Product Variant Controller
    Route::controller(ProductVariantController::class)
        ->group(function () {
            Route::get('product-variants', 'index');
            Route::post('product-variant', 'store');
            Route::get('product-variant/{productVariant}', 'show');
            Route::put('product-variant/{productVariant}', 'update');
            Route::post('product-variant/delete', 'destroy');
        });
    // Warehouse Controller
    Route::controller(WarehouseController::class)
        ->group(function () {
            Route::get('warehouses', 'index');
            Route::post('warehouse', 'store');
            Route::get('warehouse/{warehouse}', 'show');
            Route::put('warehouse/{warehouse}', 'update');
            Route::post('warehouse/delete', 'destroy');
        });
    // Stock Controller
    Route::controller(StockController::class)
        ->group(function () {
            Route::get('stocks', 'index');
            Route::post('stock', 'store');
            Route::get('stock/{stock}', 'show');
            Route::put('stock/{stock}', 'update');
            Route::post('stock/delete', 'destroy');
        });
    // Category Controller
    Route::controller(CategoryController::class)
        ->group(function () {
            Route::get('categories', 'index');
            Route::post('category', 'store');
            Route::get('category/{category}', 'show');
            Route::put('category/{category}', 'update');
            Route::post('category/delete', 'destroy');
        });
    // Brand Controller
    Route::controller(BrandController::class)
        ->group(function () {
            Route::get('brands', 'index');
            Route::post('brand', 'store');
            Route::get('brand/{brand}', 'show');
            Route::put('brand/{brand}', 'update');
            Route::post('brand/delete', 'destroy');
        });

});
