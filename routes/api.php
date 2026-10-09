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
    // Incharge inspection (registered before /challans/{id})
    Route::get('/challans/pending-review', [ChallanApiController::class, 'pendingReview']);
    Route::get('/challans/{id}/review', [ChallanApiController::class, 'reviewData']);
    Route::post('/challans/{id}/review', [ChallanApiController::class, 'review']);

    Route::get('/challans', [ChallanApiController::class, 'index']);
    Route::get('/challans/categories', [ChallanApiController::class, 'categories']);
    Route::get('/challans/category-pos', [ChallanApiController::class, 'categoryPos']);
    Route::get('/challans/po-expected-items/{poId}', [ChallanApiController::class, 'poExpectedItems']);
    Route::get('/challans/{id}', [ChallanApiController::class, 'show']);
    Route::post('/challans', [ChallanApiController::class, 'store']);
    Route::post('/challans/direct', [ChallanApiController::class, 'storeDirect']);
    Route::post('/challans/{id}/update', [ChallanApiController::class, 'update']); // multipart, so POST

    // Purchase Orders (read-only for mobile, for now)
    Route::get('/purchase-orders', [PurchaseOrderApiController::class, 'index']);
    Route::get('/purchase-orders/{id}', [PurchaseOrderApiController::class, 'show']);

    // PDC (read-only for mobile, for now)
    Route::get('/pdcs', [PdcApiController::class, 'index']);
    Route::get('/pdcs/{id}', [PdcApiController::class, 'show']);
});