<?php

use App\Http\Controllers\MerchantController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('merchants', MerchantController::class)->only(['index', 'store', 'show']);
Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'show']);
