<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\ChartOfAccountApiController;
use App\Http\Controllers\Api\AccountMappingApiController;
use App\Http\Controllers\Api\VendorApiController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\ProductCategoryApiController;
use App\Http\Controllers\Api\MeasurementUnitApiController;
use App\Http\Controllers\Api\PurchaseApiController;
use App\Http\Controllers\Api\JobOrderApiController;
use App\Http\Controllers\Api\JobOrderReceiveApiController;

Route::post('login', [AuthApiController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthApiController::class, 'me']);
    Route::post('logout', [AuthApiController::class, 'logout']);
    Route::post('change-password', [AuthApiController::class, 'changePassword']);

    Route::get('chart-of-accounts', [ChartOfAccountApiController::class, 'index'])
        ->middleware('check.permission:coa.index');

    Route::get('account-mappings', [AccountMappingApiController::class, 'index'])
        ->middleware('check.permission:coa.index');

    Route::get('vendors',        [VendorApiController::class, 'index'])   ->middleware('check.permission:vendors.index');
    Route::get('vendors/search', [VendorApiController::class, 'search'])  ->middleware('check.permission:vendors.index');
    Route::get('vendors/{id}',   [VendorApiController::class, 'show'])    ->middleware('check.permission:vendors.index');
    Route::post('vendors',       [VendorApiController::class, 'store'])   ->middleware('check.permission:vendors.create');
    Route::put('vendors/{id}',   [VendorApiController::class, 'update'])  ->middleware('check.permission:vendors.edit');
    Route::delete('vendors/{id}',[VendorApiController::class, 'destroy']) ->middleware('check.permission:vendors.delete');

    Route::get('customers',        [CustomerApiController::class, 'index'])   ->middleware('check.permission:customers.index');
    Route::get('customers/search', [CustomerApiController::class, 'search'])  ->middleware('check.permission:customers.index');
    Route::get('customers/{id}',   [CustomerApiController::class, 'show'])    ->middleware('check.permission:customers.index');
    Route::post('customers',       [CustomerApiController::class, 'store'])   ->middleware('check.permission:customers.create');
    Route::put('customers/{id}',   [CustomerApiController::class, 'update'])  ->middleware('check.permission:customers.edit');
    Route::delete('customers/{id}',[CustomerApiController::class, 'destroy']) ->middleware('check.permission:customers.delete');

    Route::get('products',              [ProductApiController::class, 'index'])       ->middleware('check.permission:products.index');
    Route::get('products/suggest-sku',  [ProductApiController::class, 'suggestSku'])   ->middleware('check.permission:products.create');
    Route::get('products/{id}',         [ProductApiController::class, 'show'])         ->middleware('check.permission:products.index');
    Route::post('products',             [ProductApiController::class, 'store'])        ->middleware('check.permission:products.create');
    Route::put('products/{id}',         [ProductApiController::class, 'update'])       ->middleware('check.permission:products.edit');
    Route::delete('products/{id}',      [ProductApiController::class, 'destroy'])      ->middleware('check.permission:products.delete');

    Route::get('product-categories',                    [ProductCategoryApiController::class, 'index'])         ->middleware('check.permission:products.index');
    Route::get('product-categories/{id}/subcategories',  [ProductCategoryApiController::class, 'subcategories']) ->middleware('check.permission:products.index');

    Route::get('units', [MeasurementUnitApiController::class, 'index'])->middleware('check.permission:products.index');

    // ── Purchase Invoice (mobile-facing; Order/Return remain web-only) ──
    Route::get('purchases',              [PurchaseApiController::class, 'index'])   ->middleware('check.permission:purchase.index');
    Route::get('purchases/{id}/items',   [PurchaseApiController::class, 'items'])   ->middleware('check.permission:purchase.index');
    Route::post('purchases',             [PurchaseApiController::class, 'store'])   ->middleware('check.permission:purchase.create');
    Route::put('purchases/{id}',         [PurchaseApiController::class, 'update'])  ->middleware('check.permission:purchase.edit');
    Route::delete('purchases/{id}',      [PurchaseApiController::class, 'destroy']) ->middleware('check.permission:purchase.delete');

    Route::get('jobs',                    [JobOrderApiController::class, 'index'])          ->middleware('check.permission:jobs.index');
    Route::get('jobs/available-stock',    [JobOrderApiController::class, 'availableStock'])  ->middleware('check.permission:jobs.index');
    Route::get('jobs/{id}',               [JobOrderApiController::class, 'show'])            ->middleware('check.permission:jobs.index');
    Route::post('jobs',                   [JobOrderApiController::class, 'store'])           ->middleware('check.permission:jobs.create');
    Route::delete('jobs/{id}',            [JobOrderApiController::class, 'destroy'])         ->middleware('check.permission:jobs.delete');
    Route::get('jobs/{id}/comments',      [JobOrderApiController::class, 'getComments'])     ->middleware('check.permission:jobs.index');
    Route::post('jobs/{id}/comments',     [JobOrderApiController::class, 'addComment'])      ->middleware('check.permission:jobs.index');

    Route::get('job-receives',                          [JobOrderReceiveApiController::class, 'index'])           ->middleware('check.permission:job_receives.index');
    Route::get('job-receives/pending-jobs',              [JobOrderReceiveApiController::class, 'pendingJobOrders'])->middleware('check.permission:job_receives.index');
    Route::get('job-receives/outstanding/{jobOrderId}',  [JobOrderReceiveApiController::class, 'outstanding'])     ->middleware('check.permission:job_receives.index');
    Route::get('job-receives/{id}',                      [JobOrderReceiveApiController::class, 'show'])           ->middleware('check.permission:job_receives.index');
    Route::post('job-receives',                          [JobOrderReceiveApiController::class, 'store'])          ->middleware('check.permission:job_receives.create');
    Route::delete('job-receives/{id}',                   [JobOrderReceiveApiController::class, 'destroy'])        ->middleware('check.permission:job_receives.delete');
});