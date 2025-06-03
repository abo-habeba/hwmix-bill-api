<?php
use Illuminate\Support\Facades\Route;

Route::apiResource('installment-plans', App\Http\Controllers\InstallmentPlanController::class);
Route::apiResource('installment-payments', App\Http\Controllers\InstallmentPaymentController::class);
Route::apiResource('installment-plan-schedules', App\Http\Controllers\InstallmentPlanScheduleController::class);
Route::apiResource('services', App\Http\Controllers\ServiceController::class);
Route::apiResource('subscriptions', App\Http\Controllers\SubscriptionController::class);
