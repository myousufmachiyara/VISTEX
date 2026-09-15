<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthApiController,
    ChallanApiController,
    PurchaseOrderApiController,
    PdcApiController,
};

Route::post('/login', [AuthApiController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthApiController::class, 'logout']);
    Route::get('/me', [AuthApiController::class, 'me']);

    // Challan
    Route::get('/challans', [ChallanApiController::class, 'index']);
    Route::get('/challans/vendors-for-type', [ChallanApiController::class, 'vendorsForType']);
    Route::get('/challans/pos-for-vendor', [ChallanApiController::class, 'posForVendor']);
    Route::get('/challans/po-expected-items/{poId}', [ChallanApiController::class, 'poExpectedItems']);
    Route::get('/challans/expense-accounts', [ChallanApiController::class, 'expenseAccounts']);
    Route::get('/challans/{id}', [ChallanApiController::class, 'show']);
    Route::post('/challans', [ChallanApiController::class, 'store']);
    Route::post('/challans/direct', [ChallanApiController::class, 'storeDirect']);

    // Purchase Orders (read-only for mobile, for now)
    Route::get('/purchase-orders', [PurchaseOrderApiController::class, 'index']);
    Route::get('/purchase-orders/{id}', [PurchaseOrderApiController::class, 'show']);

    // PDC (read-only for mobile, for now)
    Route::get('/pdcs', [PdcApiController::class, 'index']);
    Route::get('/pdcs/{id}', [PdcApiController::class, 'show']);
});