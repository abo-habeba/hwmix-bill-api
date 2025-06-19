<?php

use App\Http\Controllers\Backup\DatabaseBackupController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AttributeValueController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CashBoxController;
use App\Http\Controllers\CashBoxTypeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\InstallmentPaymentController;
use App\Http\Controllers\InstallmentPaymentDetailController;
use App\Http\Controllers\InstallmentPlanController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\InvoiceTypeController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\ProfitController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/dump', function () {
    try {
        // شغل أمر composer dump-autoload من خلال shell_exec
        $output = shell_exec('composer dump-autoload');

        return response()->json([
            'status' => '✅ dump-autoload تم بنجاح',
            'output' => $output
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => '❌ حصل خطأ',
            'message' => $e->getMessage()
        ]);
    }
});

// Route to run migrations and seeders without authentication (for development only)
Route::get('db/seed', function (Request $request) {
    try {
        \Artisan::call('migrate:fresh', ['--force' => true]);
        \Artisan::call('db:seed', [
            '--force' => true
        ]);
        return response()->json([
            'migrate' => \Artisan::output(),
            'seed' => 'Seeders executed successfully',
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});
Route::get('db/seed-permissions', function (\Illuminate\Http\Request $request) {
    try {
        \Artisan::call('db:seed', [
            '--class' => 'Database\Seeders\PermissionsSeeder',
            '--force' => true
        ]);
        return response()->json([
            'seed' => 'PermissionsSeeder executed successfully',
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});
Route::prefix('db')->controller(DatabaseBackupController::class)->group(function () {
    // 🔄 [GET] /db/export: إنشاء نسخة احتياطية من البيانات كسيدر
    Route::get('export', 'export');

    // 🚀 [GET] /db/seed-export: تشغيل السيدرز اللي اتولدت قبل كده
    Route::get('seed-export', 'runSeeders');

    // 🧨 [GET] /db/refresh:   عمل فريش للقاعدة واسترجاع النسخة الاحتياطية
    Route::get('refresh', 'restoreAndFresh');
});

// Route to get all JSON files data for permissions
Route::get('permissions-json', function () {
    $permissionsJsonArray = [];
    $jsonDir = database_path('seeders/permission-json');
    foreach (glob($jsonDir . '/*.json') as $file) {
        $permissionsJsonArray[] = json_decode(file_get_contents($file), true);
    }
    return response()->json($permissionsJsonArray);
});

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('/auth/check', [AuthController::class, 'checkLogin']);
    // Auth Controller
    Route::get('me', [AuthController::class, 'me']);
    // User Controller
    Route::controller(UserController::class)
        ->group(function () {
            Route::get('users', 'index');
            Route::get('users/search', 'usersSearch');
            Route::get('users/search-advanced', 'indexWithSearch');
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
            Route::delete('product/delete/{product}', 'destroy');
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
            Route::delete('product-variant/{productVariant}', 'destroy');
            Route::post('product-variant/delete', 'deleteMultiple');
            Route::get('product-variants/search-by-product', 'searchByProduct');
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
            Route::delete('brand/delete/{brand}', 'destroy');
        });
    // InvoiceType Controller
    Route::controller(InvoiceTypeController::class)->group(function () {
        Route::get('invoice-types', 'index');
        Route::post('invoice-type', 'store');
        Route::get('invoice-type/{invoiceType}', 'show');
        Route::put('invoice-type/{invoiceType}', 'update');
        Route::delete('invoice-type/{invoiceType}', 'destroy');
    });
    // Invoice Controller
    Route::controller(InvoiceController::class)->group(function () {
        Route::get('invoices', 'index');
        Route::post('invoice', 'store');
        Route::get('invoice/{invoice}', 'show');
        Route::put('invoice/{invoice}', 'update');
        Route::delete('invoice/{invoice}', 'destroy');
    });
    // InvoiceItem Controller
    Route::controller(InvoiceItemController::class)->group(function () {
        Route::get('invoice-items', 'index');
        Route::post('invoice-item', 'store');
        Route::get('invoice-item/{invoiceItem}', 'show');
        Route::put('invoice-item/{invoiceItem}', 'update');
        Route::delete('invoice-item/{invoiceItem}', 'destroy');
    });
    // InstallmentPlan Controller
    Route::controller(InstallmentPlanController::class)->group(function () {
        Route::get('installment-plans', 'index');
        Route::post('installment-plan', 'store');
        Route::get('installment-plan/{installmentPlan}', 'show');
        Route::put('installment-plan/{installmentPlan}', 'update');
        Route::delete('installment-plan/{installmentPlan}', 'destroy');
    });
    // Installment Controller
    Route::controller(InstallmentController::class)->group(function () {
        Route::get('installments', 'index');
        Route::post('installment', 'store');
        Route::get('installment/{installment}', 'show');
        Route::put('installment/{installment}', 'update');
        Route::delete('installment/{installment}', 'destroy');
    });
    // Payment Controller
    Route::controller(PaymentController::class)->group(function () {
        Route::get('payments', 'index');
        Route::post('payment', 'store');
        Route::get('payment/{payment}', 'show');
        Route::put('payment/{payment}', 'update');
        Route::delete('payment/{payment}', 'destroy');
    });
    // PaymentMethod Controller
    Route::controller(PaymentMethodController::class)
        ->group(function () {
            Route::get('payment-methods', 'index');
            Route::post('payment-method', 'store');

            Route::get('payment-method/{paymentMethod}', 'show');
            Route::put('payment-method/{paymentMethod}', 'update');
            Route::delete('payment-method/delete/{paymentMethod}', 'destroy');
        });
    Route::controller(InstallmentPaymentController::class)
        ->group(function () {
            Route::get('installment-payments', 'index');
            Route::post('installment-payment', 'store');
            Route::post('installment-payment/pay', 'payInstallments');
            Route::get('installment-payment/{installmentPayment}', 'show');
            Route::put('installment-payment/{installmentPayment}', 'update');
            Route::delete('installment-payment/delete/{installmentPayment}', 'destroy');
        });
    // Revenue Controller
    Route::controller(RevenueController::class)
        ->group(function () {
            Route::get('revenues', 'index');
            Route::post('revenue', 'store');
            Route::get('revenue/{revenue}', 'show');
            Route::put('revenue/{revenue}', 'update');
            Route::delete('revenue/delete/{revenue}', 'destroy');
        });
    // Profit Controller
    Route::controller(ProfitController::class)
        ->group(function () {
            Route::get('profits', 'index');
            Route::post('profit', 'store');
            Route::get('profit/{profit}', 'show');
            Route::put('profit/{profit}', 'update');
            Route::delete('profit/delete/{profit}', 'destroy');
        });
    // InstallmentPaymentDetail Controller
    Route::controller(InstallmentPaymentDetailController::class)
        ->group(function () {
            Route::get('installment-payment-details', 'index');
            Route::post('installment-payment-detail', 'store');
            Route::get('installment-payment-detail/{installmentPaymentDetail}', 'show');
            Route::put('installment-payment-detail/{installmentPaymentDetail}', 'update');
            Route::delete('installment-payment-detail/delete/{installmentPaymentDetail}', 'destroy');
        });
});

require __DIR__ . '/installments.php';
