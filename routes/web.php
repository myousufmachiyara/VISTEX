<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\{
    DashboardController,
    UserController,
    RoleController,
    PermissionController,
    COAController,
    SubHeadOfAccController,
    AccountMappingController,
    TaxMasterController,
    CustomerController,
    VendorController,
    PartyImportController,
    LocationController,
    ProductCategoryController,
    MeasurementUnitController,
    ProductController,
    ServiceTypeController,
    CustomerSkuRateController,
    BrokerController,
    TermAndConditionController,
    VoucherController,
    ForecastController,
    JobController,
    PurchaseOrderController,
    PurchaseOrderObjectionController,
    PurchaseOrderAmendmentController,
    ConversionPurchaseOrderController,
    ChallanController,
    PurchaseReceivingController,
    PurchaseReturnController,
    YarnIssueController,
    StockMovementController,
    ProcessingIssueController,
    PdcController,
};

Auth::routes();

Route::middleware(['auth'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ════════════════════════════════════════════════════════════════
    // MASTER SETUP 1 — CHART OF ACCOUNTS, SUB HEADS, MAPPINGS, TAX
    // ════════════════════════════════════════════════════════════════
    Route::prefix('coa')->name('coa.')->group(function () {
        Route::get('/',                 [COAController::class, 'index'])                  ->name('index')  ->middleware('check.permission:coa.index');
        Route::post('/',                [COAController::class, 'store'])                  ->name('store')  ->middleware('check.permission:coa.create');
        Route::get('{id}/edit',         [COAController::class, 'edit'])                   ->name('edit')   ->middleware('check.permission:coa.edit');
        Route::put('{id}',              [COAController::class, 'update'])                 ->name('update') ->middleware('check.permission:coa.edit');
        Route::delete('{id}',           [COAController::class, 'destroy'])                ->name('destroy')->middleware('check.permission:coa.delete');
        Route::get('import',            [COAController::class, 'importForm'])             ->name('import.form')->middleware('check.permission:coa.create');
        Route::post('import',           [COAController::class, 'import'])                 ->name('import')->middleware('check.permission:coa.create');
        Route::get('import/template',   [COAController::class, 'downloadImportTemplate']) ->name('import.template')->middleware('check.permission:coa.create');
    });

    Route::prefix('shoa')->name('shoa.')->group(function () {
        Route::get('/',          [SubHeadOfAccController::class, 'index'])   ->name('index')  ->middleware('check.permission:shoa.index');
        Route::post('/',         [SubHeadOfAccController::class, 'store'])   ->name('store')  ->middleware('check.permission:shoa.create');
        Route::get('{id}/edit',  [SubHeadOfAccController::class, 'edit'])    ->name('edit')   ->middleware('check.permission:shoa.edit');
        Route::put('{id}',       [SubHeadOfAccController::class, 'update'])  ->name('update') ->middleware('check.permission:shoa.edit');
        Route::delete('{id}',    [SubHeadOfAccController::class, 'destroy']) ->name('destroy')->middleware('check.permission:shoa.delete');
    });

    Route::get('accounts/mapping', [AccountMappingController::class, 'index'])->name('account-mappings.index')->middleware('check.permission:coa.edit');
    Route::put('accounts/mapping', [AccountMappingController::class, 'update'])->name('account-mappings.update')->middleware('check.permission:coa.edit');

    Route::prefix('tax-masters')->name('tax-masters.')->group(function () {
        Route::get('/',          [TaxMasterController::class, 'index'])   ->name('index')  ->middleware('check.permission:tax_masters.index');
        Route::post('/',         [TaxMasterController::class, 'store'])   ->name('store')  ->middleware('check.permission:tax_masters.create');
        Route::get('{id}/edit',  [TaxMasterController::class, 'edit'])    ->name('edit')   ->middleware('check.permission:tax_masters.edit');
        Route::put('{id}',       [TaxMasterController::class, 'update'])  ->name('update') ->middleware('check.permission:tax_masters.edit');
        Route::delete('{id}',    [TaxMasterController::class, 'destroy']) ->name('destroy')->middleware('check.permission:tax_masters.delete');
    });

    // ════════════════════════════════════════════════════════════════
    // MASTER SETUP 2 — PARTIES (CUSTOMERS, VENDORS, BROKERS)
    // ════════════════════════════════════════════════════════════════
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/',          [CustomerController::class, 'index'])   ->name('index')  ->middleware('check.permission:customers.index');
        Route::post('/',         [CustomerController::class, 'store'])   ->name('store')  ->middleware('check.permission:customers.create');
        Route::get('{id}/edit',  [CustomerController::class, 'edit'])    ->name('edit')   ->middleware('check.permission:customers.edit');
        Route::put('{id}',       [CustomerController::class, 'update'])  ->name('update') ->middleware('check.permission:customers.edit');
        Route::delete('{id}',    [CustomerController::class, 'destroy']) ->name('destroy')->middleware('check.permission:customers.delete');
    });

    Route::prefix('vendors')->name('vendors.')->group(function () {
        Route::get('/',          [VendorController::class, 'index'])   ->name('index')  ->middleware('check.permission:vendors.index');
        Route::post('/',         [VendorController::class, 'store'])   ->name('store')  ->middleware('check.permission:vendors.create');
        Route::get('{id}/edit',  [VendorController::class, 'edit'])    ->name('edit')   ->middleware('check.permission:vendors.edit');
        Route::put('{id}',       [VendorController::class, 'update'])  ->name('update') ->middleware('check.permission:vendors.edit');
        Route::delete('{id}',    [VendorController::class, 'destroy']) ->name('destroy')->middleware('check.permission:vendors.delete');
    });

    Route::prefix('brokers')->name('brokers.')->group(function () {
        Route::get('/',          [BrokerController::class, 'index'])   ->name('index')  ->middleware('check.permission:brokers.index');
        Route::post('/',         [BrokerController::class, 'store'])   ->name('store')  ->middleware('check.permission:brokers.create');
        Route::get('{id}/edit',  [BrokerController::class, 'edit'])    ->name('edit')   ->middleware('check.permission:brokers.edit');
        Route::put('{id}',       [BrokerController::class, 'update'])  ->name('update') ->middleware('check.permission:brokers.edit');
        Route::delete('{id}',    [BrokerController::class, 'destroy']) ->name('destroy')->middleware('check.permission:brokers.delete');
    });

    Route::get('/{type}/import',  [PartyImportController::class, 'form'])  ->whereIn('type', ['customer', 'vendor'])->name('parties.import.form')->middleware('check.permission:customers.create');
    Route::post('/{type}/import', [PartyImportController::class, 'import'])->whereIn('type', ['customer', 'vendor'])->name('parties.import')->middleware('check.permission:customers.create');

    // ════════════════════════════════════════════════════════════════
    // MASTER SETUP 3 — LOCATIONS
    // ════════════════════════════════════════════════════════════════
    Route::prefix('locations')->name('locations.')->group(function () {
        Route::get('/',          [LocationController::class, 'index'])   ->name('index')  ->middleware('check.permission:locations.index');
        Route::post('/',         [LocationController::class, 'store'])   ->name('store')  ->middleware('check.permission:locations.create');
        Route::get('{id}/edit',  [LocationController::class, 'edit'])    ->name('edit')   ->middleware('check.permission:locations.edit');
        Route::put('{id}',       [LocationController::class, 'update'])  ->name('update') ->middleware('check.permission:locations.edit');
        Route::delete('{id}',    [LocationController::class, 'destroy']) ->name('destroy')->middleware('check.permission:locations.delete');
    });

    // ════════════════════════════════════════════════════════════════
    // MASTER SETUP 4 — PRODUCT CATEGORIES, MEASUREMENT UNITS, PRODUCTS
    // ════════════════════════════════════════════════════════════════
    Route::prefix('product-categories')->name('product_categories.')->group(function () {
        Route::get('/',          [ProductCategoryController::class, 'index'])   ->name('index')  ->middleware('check.permission:product_categories.index');
        Route::post('/',         [ProductCategoryController::class, 'store'])   ->name('store')  ->middleware('check.permission:product_categories.create');
        Route::get('{id}/edit',  [ProductCategoryController::class, 'edit'])    ->name('edit')   ->middleware('check.permission:product_categories.edit');
        Route::put('{id}',       [ProductCategoryController::class, 'update'])  ->name('update') ->middleware('check.permission:product_categories.edit');
        Route::delete('{id}',    [ProductCategoryController::class, 'destroy']) ->name('destroy')->middleware('check.permission:product_categories.delete');
    });

    Route::prefix('measurement-units')->name('measurement_units.')->group(function () {
        Route::get('/',          [MeasurementUnitController::class, 'index'])   ->name('index')  ->middleware('check.permission:measurement_units.index');
        Route::post('/',         [MeasurementUnitController::class, 'store'])   ->name('store')  ->middleware('check.permission:measurement_units.create');
        Route::get('{id}/edit',  [MeasurementUnitController::class, 'edit'])    ->name('edit')   ->middleware('check.permission:measurement_units.edit');
        Route::put('{id}',       [MeasurementUnitController::class, 'update'])  ->name('update') ->middleware('check.permission:measurement_units.edit');
        Route::delete('{id}',    [MeasurementUnitController::class, 'destroy']) ->name('destroy')->middleware('check.permission:measurement_units.delete');
    });

    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/',                              [ProductController::class, 'index'])          ->name('index')  ->middleware('check.permission:products.index');
        Route::get('create',                         [ProductController::class, 'create'])         ->name('create') ->middleware('check.permission:products.create');
        Route::post('/',                              [ProductController::class, 'store'])          ->name('store')  ->middleware('check.permission:products.create');
        Route::get('{id}/edit',                       [ProductController::class, 'edit'])           ->name('edit')   ->middleware('check.permission:products.edit');
        Route::put('{id}',                            [ProductController::class, 'update'])         ->name('update') ->middleware('check.permission:products.edit');
        Route::delete('{id}',                         [ProductController::class, 'destroy'])        ->name('destroy')->middleware('check.permission:products.delete');
        Route::get('import',                          [ProductController::class, 'importForm'])     ->name('import.form')->middleware('check.permission:products.create');
        Route::post('import',                         [ProductController::class, 'import'])         ->name('import')->middleware('check.permission:products.create');
        Route::get('import-xero',                     [ProductController::class, 'xeroImportForm']) ->name('import_xero.form')->middleware('check.permission:products.create');
        Route::post('import-xero',                    [ProductController::class, 'xeroImport'])     ->name('import_xero')->middleware('check.permission:products.create');
    });

    Route::get('helpers/products/attribute-schema/{categoryId}', [ProductController::class, 'attributeSchema'])->name('helpers.products.attribute_schema');

    // ════════════════════════════════════════════════════════════════
    // MASTER SETUP 5 — USERS, ROLES & PERMISSIONS
    // ════════════════════════════════════════════════════════════════
    Route::resource('roles', RoleController::class)->except(['show'])->middleware('check.permission:user_roles.index');
    Route::resource('permissions', PermissionController::class)->except(['show'])->middleware('check.permission:user_roles.index');

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/',                    [UserController::class, 'index'])          ->name('index')  ->middleware('check.permission:users.index');
        Route::post('/',                   [UserController::class, 'store'])          ->name('store')  ->middleware('check.permission:users.create');
        Route::get('{id}/edit',            [UserController::class, 'edit'])           ->name('edit')   ->middleware('check.permission:users.edit');
        Route::put('{id}',                 [UserController::class, 'update'])         ->name('update') ->middleware('check.permission:users.edit');
        Route::delete('{id}',              [UserController::class, 'destroy'])        ->name('destroy')->middleware('check.permission:users.delete');
        Route::put('{id}/change-password', [UserController::class, 'changePassword']) ->name('changePassword');
        Route::put('{id}/toggle-active',   [UserController::class, 'toggleActive'])   ->name('toggleActive');
        Route::post('change-my-password',  [UserController::class, 'changeMyPassword'])->name('changeMyPassword');
    });

    // ════════════════════════════════════════════════════════════════
    // MASTER SETUP 6 — SERVICE TYPES
    // ════════════════════════════════════════════════════════════════
    Route::prefix('service-types')->name('service_types.')->group(function () {
        Route::get('/',          [ServiceTypeController::class, 'index'])   ->name('index')  ->middleware('check.permission:service_types.index');
        Route::post('/',         [ServiceTypeController::class, 'store'])   ->name('store')  ->middleware('check.permission:service_types.create');
        Route::get('{id}/edit',  [ServiceTypeController::class, 'edit'])    ->name('edit')   ->middleware('check.permission:service_types.edit');
        Route::put('{id}',       [ServiceTypeController::class, 'update'])  ->name('update') ->middleware('check.permission:service_types.edit');
        Route::delete('{id}',    [ServiceTypeController::class, 'destroy']) ->name('destroy')->middleware('check.permission:service_types.delete');
    });

    // ════════════════════════════════════════════════════════════════
    // MASTER SETUP 7 — CUSTOMER SKU RATES
    // ════════════════════════════════════════════════════════════════
    Route::prefix('customer-sku-rates')->name('customer_sku_rates.')->group(function () {
        Route::get('/',                               [CustomerSkuRateController::class, 'index'])   ->name('index')  ->middleware('check.permission:customer_sku_rates.index');
        Route::post('/',                               [CustomerSkuRateController::class, 'store'])   ->name('store')  ->middleware('check.permission:customer_sku_rates.create');
        Route::get('history/{customerId}/{productId}', [CustomerSkuRateController::class, 'history']) ->name('history')->middleware('check.permission:customer_sku_rates.index');
        Route::get('suggest',                          [CustomerSkuRateController::class, 'suggest']) ->name('suggest')->middleware('check.permission:customer_sku_rates.index');
        Route::delete('{id}',                          [CustomerSkuRateController::class, 'destroy']) ->name('destroy')->middleware('check.permission:customer_sku_rates.delete');
    });

    // ════════════════════════════════════════════════════════════════
    // MASTER SETUP 8 — TERMS & CONDITIONS
    // ════════════════════════════════════════════════════════════════
    Route::prefix('terms-and-conditions')->name('terms_and_conditions.')->group(function () {
        Route::get('/',          [TermAndConditionController::class, 'index'])   ->name('index')  ->middleware('check.permission:terms_and_conditions.index');
        Route::post('/',         [TermAndConditionController::class, 'store'])   ->name('store')  ->middleware('check.permission:terms_and_conditions.create');
        Route::get('{id}/edit',  [TermAndConditionController::class, 'edit'])    ->name('edit')   ->middleware('check.permission:terms_and_conditions.edit');
        Route::put('{id}',       [TermAndConditionController::class, 'update'])  ->name('update') ->middleware('check.permission:terms_and_conditions.edit');
        Route::delete('{id}',    [TermAndConditionController::class, 'destroy']) ->name('destroy')->middleware('check.permission:terms_and_conditions.delete');
    });

    Route::get('helpers/terms-and-conditions/{type}', [TermAndConditionController::class, 'forType'])->name('terms_and_conditions.for_type');

    // Cross-module helper search endpoints
    Route::prefix('helpers')->name('helpers.')->group(function () {
        Route::get('vendors/search',   [VendorController::class, 'search'])->name('vendors.search');
        Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
        Route::get('accounts/search',  [COAController::class, 'search'])->name('accounts.search');
        Route::get('brokers/search',   [BrokerController::class, 'search'])->name('brokers.search');
    });

    // ════════════════════════════════════════════════════════════════
    // OPERATIONAL 1 — FORECASTING / PLANNING
    // ════════════════════════════════════════════════════════════════
    Route::prefix('forecasts')->name('forecasts.')->group(function () {
        Route::get('/',                               [ForecastController::class, 'index'])            ->name('index')  ->middleware('check.permission:forecasts.index');
        Route::get('create',                          [ForecastController::class, 'create'])           ->name('create') ->middleware('check.permission:forecasts.create');
        Route::post('/',                               [ForecastController::class, 'store'])            ->name('store')  ->middleware('check.permission:forecasts.create');
        Route::post('{id}/approve',                    [ForecastController::class, 'approve'])          ->name('approve')->middleware('check.permission:forecasts.edit');
        Route::post('{id}/reject',                     [ForecastController::class, 'reject'])           ->name('reject') ->middleware('check.permission:forecasts.edit');
        Route::delete('{id}',                          [ForecastController::class, 'destroy'])          ->name('destroy')->middleware('check.permission:forecasts.delete');
        Route::get('approved-for-product/{productId}', [ForecastController::class, 'approvedForProduct'])->name('approved_for_product')->middleware('check.permission:forecasts.index');
    });

    // ════════════════════════════════════════════════════════════════
    // OPERATIONAL 2 — JOBS / CUSTOMER ORDERS
    // ════════════════════════════════════════════════════════════════
    Route::prefix('jobs')->name('jobs.')->group(function () {
        Route::get('/',              [JobController::class, 'index'])       ->name('index')  ->middleware('check.permission:jobs.index');
        Route::get('create',         [JobController::class, 'create'])      ->name('create') ->middleware('check.permission:jobs.create');
        Route::get('suggest-rate',   [JobController::class, 'suggestRate']) ->name('suggest_rate')->middleware('check.permission:jobs.index');
        Route::post('/',             [JobController::class, 'store'])       ->name('store')  ->middleware('check.permission:jobs.create');
        Route::get('{id}',           [JobController::class, 'show'])        ->name('show')   ->middleware('check.permission:jobs.index');
        Route::get('{id}/edit',      [JobController::class, 'edit'])        ->name('edit')   ->middleware('check.permission:jobs.edit');
        Route::put('{id}',           [JobController::class, 'update'])      ->name('update') ->middleware('check.permission:jobs.edit');
        Route::post('{id}/approve',  [JobController::class, 'approve'])     ->name('approve')->middleware('check.permission:jobs.edit');
        Route::post('{id}/reject',   [JobController::class, 'reject'])      ->name('reject') ->middleware('check.permission:jobs.edit');
        Route::delete('{id}',        [JobController::class, 'destroy'])     ->name('destroy')->middleware('check.permission:jobs.delete');
    });

    // ════════════════════════════════════════════════════════════════
    // OPERATIONAL 3 — PURCHASE ORDER (Purchasing / Weaving / Processing)
    //                 + OBJECTIONS + AMENDMENTS
    // ════════════════════════════════════════════════════════════════
    Route::prefix('purchase-orders')->name('purchase_orders.')->group(function () {
        Route::get('/',                                 [PurchaseOrderController::class, 'index'])              ->name('index')  ->middleware('check.permission:purchase_orders.index');
        Route::get('create',                            [PurchaseOrderController::class, 'create'])             ->name('create') ->middleware('check.permission:purchase_orders.create');
        Route::post('calculate',                        [ConversionPurchaseOrderController::class, 'calculate'])->name('calculate')->middleware('check.permission:purchase_orders.create');
        Route::get('vendor-locations/{vendorId}',       [PurchaseOrderController::class, 'vendorLocations'])    ->name('vendor_locations')->middleware('check.permission:purchase_orders.index');
        Route::get('category-products/{categoryId}',    [PurchaseOrderController::class, 'categoryProducts'])   ->name('category_products')->middleware('check.permission:purchase_orders.index');
        Route::get('forecasts-for-product/{productId}', [PurchaseOrderController::class, 'forecastsForProduct'])->name('forecasts_for_product')->middleware('check.permission:purchase_orders.index');
        Route::get('job-items/{jobId}',                 [PurchaseOrderController::class, 'jobItems'])           ->name('job_items')->middleware('check.permission:purchase_orders.index');
        Route::post('/',                                 [PurchaseOrderController::class, 'store'])              ->name('store')  ->middleware('check.permission:purchase_orders.create');
        Route::get('{id}/edit',                          [PurchaseOrderController::class, 'edit'])               ->name('edit')   ->middleware('check.permission:purchase_orders.edit');
        Route::put('{id}',                               [PurchaseOrderController::class, 'update'])             ->name('update') ->middleware('check.permission:purchase_orders.edit');
        Route::delete('{id}',                            [PurchaseOrderController::class, 'destroy'])            ->name('destroy')->middleware('check.permission:purchase_orders.delete');
        Route::post('{id}/approve',                      [PurchaseOrderController::class, 'approve'])            ->name('approve')->middleware('check.permission:purchase_orders.edit');
        Route::post('{id}/reject',                       [PurchaseOrderController::class, 'reject'])             ->name('reject') ->middleware('check.permission:purchase_orders.edit');
        Route::get('{id}/print',                         [PurchaseOrderController::class, 'print'])              ->name('print')  ->middleware('check.permission:purchase_orders.print');
        Route::get('{id}',                               [PurchaseOrderController::class, 'show'])               ->name('show')   ->middleware('check.permission:purchase_orders.index');

        // Amendments
        Route::get('{poId}/amend',  [PurchaseOrderAmendmentController::class, 'create']) ->name('amendments.create')->middleware('check.permission:purchase_orders.edit');
        Route::post('{poId}/amend', [PurchaseOrderAmendmentController::class, 'store'])  ->name('amendments.store') ->middleware('check.permission:purchase_orders.edit');
    });

    Route::prefix('purchase-order-objections')->name('purchase_order_objections.')->group(function () {
        Route::get('/',                    [PurchaseOrderObjectionController::class, 'index'])   ->name('index')  ->middleware('check.permission:purchase_orders.index');
        Route::get('{poId}/create',        [PurchaseOrderObjectionController::class, 'create'])  ->name('create') ->middleware('check.permission:purchase_orders.index');
        Route::post('{poId}',              [PurchaseOrderObjectionController::class, 'store'])   ->name('store')  ->middleware('check.permission:purchase_orders.index');
        Route::post('{id}/resolve',        [PurchaseOrderObjectionController::class, 'resolve']) ->name('resolve')->middleware('check.permission:purchase_orders.edit');
    });

    Route::prefix('purchase-order-amendments')->name('purchase_order_amendments.')->group(function () {
        Route::post('{id}/approve', [PurchaseOrderAmendmentController::class, 'approve'])->name('approve')->middleware('check.permission:purchase_orders.edit');
        Route::post('{id}/reject',  [PurchaseOrderAmendmentController::class, 'reject']) ->name('reject') ->middleware('check.permission:purchase_orders.edit');
    });

    Route::get('purchase-order-objections', [PurchaseOrderObjectionController::class, 'index'])->name('purchase_order_objections.index')->middleware('check.permission:purchase_orders.index');

    // ════════════════════════════════════════════════════════════════
    // OPERATIONAL 4 — CHALLAN
    // ════════════════════════════════════════════════════════════════
    Route::prefix('challans')->name('challans.')->group(function () {
        Route::get('/',               [ChallanController::class, 'index'])   ->name('index')  ->middleware('check.permission:challans.index');
        Route::get('pending',         [ChallanController::class, 'pending']) ->name('pending')->middleware('check.permission:challans.index');
        Route::get('create',          [ChallanController::class, 'create'])  ->name('create') ->middleware('check.permission:challans.create');
        Route::get('po-items/{poId}', [ChallanController::class, 'poItems']) ->name('po_items')->middleware('check.permission:challans.index');
        Route::post('/',              [ChallanController::class, 'store'])   ->name('store')  ->middleware('check.permission:challans.create');
        Route::get('{id}',            [ChallanController::class, 'show'])    ->name('show')   ->middleware('check.permission:challans.index');
    });

    // ════════════════════════════════════════════════════════════════
    // OPERATIONAL 5 — PURCHASE RECEIVING (all 3 types) + RETURNS
    // ════════════════════════════════════════════════════════════════
    Route::prefix('purchase-receivings')->name('purchase_receivings.')->group(function () {
        Route::get('/',                  [PurchaseReceivingController::class, 'index'])       ->name('index')  ->middleware('check.permission:purchase_receivings.index');
        Route::get('create/{challanId}', [PurchaseReceivingController::class, 'create'])       ->name('create') ->middleware('check.permission:purchase_receivings.create');
        Route::post('/',                 [PurchaseReceivingController::class, 'store'])        ->name('store')  ->middleware('check.permission:purchase_receivings.create');
        Route::post('store-weaving',     [PurchaseReceivingController::class, 'storeWeaving'])  ->name('store_weaving')->middleware('check.permission:purchase_receivings.create');
        Route::get('{id}',               [PurchaseReceivingController::class, 'show'])         ->name('show')   ->middleware('check.permission:purchase_receivings.index');
        Route::post('{id}/approve',      [PurchaseReceivingController::class, 'approve'])      ->name('approve')->middleware('check.permission:purchase_receivings.edit');
        Route::post('{id}/reject',       [PurchaseReceivingController::class, 'reject'])       ->name('reject') ->middleware('check.permission:purchase_receivings.edit');
    });

    Route::prefix('purchase-returns')->name('purchase_returns.')->group(function () {
        Route::get('/',                    [PurchaseReturnController::class, 'index'])  ->name('index')  ->middleware('check.permission:purchase_returns.index');
        Route::get('create/{receivingId}', [PurchaseReturnController::class, 'create']) ->name('create') ->middleware('check.permission:purchase_returns.create');
        Route::post('/',                    [PurchaseReturnController::class, 'store'])  ->name('store')  ->middleware('check.permission:purchase_returns.create');
        Route::get('{id}/edit',             [PurchaseReturnController::class, 'edit'])   ->name('edit')   ->middleware('check.permission:purchase_returns.edit');
        Route::put('{id}',                  [PurchaseReturnController::class, 'update']) ->name('update') ->middleware('check.permission:purchase_returns.edit');
    });

    // ════════════════════════════════════════════════════════════════
    // OPERATIONAL 6 — YARN ISSUE (Weaving)
    // ════════════════════════════════════════════════════════════════
    Route::prefix('yarn-issues')->name('yarn_issues.')->group(function () {
        Route::get('/',                   [YarnIssueController::class, 'index'])      ->name('index')  ->middleware('check.permission:yarn_issues.index');
        Route::get('create',              [YarnIssueController::class, 'create'])     ->name('create') ->middleware('check.permission:yarn_issues.create');
        Route::get('cpo-details/{poId}',  [YarnIssueController::class, 'cpoDetails']) ->name('cpo_details')->middleware('check.permission:yarn_issues.index');
        Route::post('/',                   [YarnIssueController::class, 'store'])      ->name('store')  ->middleware('check.permission:yarn_issues.create');
        Route::get('{id}/edit',           [YarnIssueController::class, 'edit'])       ->name('edit')   ->middleware('check.permission:yarn_issues.edit');
        Route::put('{id}',                [YarnIssueController::class, 'update'])     ->name('update') ->middleware('check.permission:yarn_issues.edit');
        Route::get('{id}/print',          [YarnIssueController::class, 'print'])      ->name('print')  ->middleware('check.permission:yarn_issues.print');
        Route::delete('{id}',              [YarnIssueController::class, 'destroy'])    ->name('destroy')->middleware('check.permission:yarn_issues.delete');
    });

    // ════════════════════════════════════════════════════════════════
    // OPERATIONAL 7 — STOCK MOVEMENT (Warehouse ↔ Warehouse ↔ Vendor)
    // ════════════════════════════════════════════════════════════════
    Route::prefix('stock-movements')->name('stock_movements.')->group(function () {
        Route::get('/',              [StockMovementController::class, 'index'])         ->name('index')  ->middleware('check.permission:stock_movements.index');
        Route::get('create',         [StockMovementController::class, 'create'])        ->name('create') ->middleware('check.permission:stock_movements.create');
        Route::get('available-lots', [StockMovementController::class, 'availableLots']) ->name('available_lots')->middleware('check.permission:stock_movements.index');
        Route::post('/',             [StockMovementController::class, 'store'])         ->name('store')  ->middleware('check.permission:stock_movements.create');
        Route::get('{id}',           [StockMovementController::class, 'show'])          ->name('show')   ->middleware('check.permission:stock_movements.index');
        Route::post('{id}/approve',  [StockMovementController::class, 'approve'])       ->name('approve')->middleware('check.permission:stock_movements.edit');
        Route::post('{id}/reject',   [StockMovementController::class, 'reject'])        ->name('reject') ->middleware('check.permission:stock_movements.edit');
    });

    // ════════════════════════════════════════════════════════════════
    // OPERATIONAL 8 — PROCESSING ISSUE (Mill employee, lot-tracked)
    // ════════════════════════════════════════════════════════════════
    Route::prefix('processing-issues')->name('processing_issues.')->group(function () {
        Route::get('/',                 [ProcessingIssueController::class, 'index'])         ->name('index')  ->middleware('check.permission:processing_issues.index');
        Route::get('create',            [ProcessingIssueController::class, 'create'])        ->name('create') ->middleware('check.permission:processing_issues.create');
        Route::get('po-details/{poId}', [ProcessingIssueController::class, 'poDetails'])      ->name('po_details')->middleware('check.permission:processing_issues.index');
        Route::get('available-stock',   [ProcessingIssueController::class, 'availableStock']) ->name('available_stock')->middleware('check.permission:processing_issues.index');
        Route::post('/',                 [ProcessingIssueController::class, 'store'])          ->name('store')  ->middleware('check.permission:processing_issues.create');
        Route::delete('{id}',            [ProcessingIssueController::class, 'destroy'])        ->name('destroy')->middleware('check.permission:processing_issues.delete');
    });

    // ════════════════════════════════════════════════════════════════
    // OPERATIONAL 9 — VOUCHER ENGINE
    // ════════════════════════════════════════════════════════════════
    Route::prefix('vouchers/{type}')->name('vouchers.')->group(function () {
        Route::get('/',        [VoucherController::class, 'index'])   ->name('index')  ->middleware('check.permission:vouchers.index');
        Route::get('create',   [VoucherController::class, 'create'])  ->name('create') ->middleware('check.permission:vouchers.create');
        Route::post('/',       [VoucherController::class, 'store'])   ->name('store')  ->middleware('check.permission:vouchers.create');
        Route::get('{id}',     [VoucherController::class, 'show'])    ->name('show')   ->middleware('check.permission:vouchers.index');
        Route::delete('{id}',  [VoucherController::class, 'destroy']) ->name('destroy')->middleware('check.permission:vouchers.delete');
    });

    // ════════════════════════════════════════════════════════════════
    // OPERATIONAL 10 — PDC (POST DATED CHEQUES)
    // ════════════════════════════════════════════════════════════════
    Route::prefix('pdcs')->name('pdcs.')->group(function () {
        Route::get('/',              [PdcController::class, 'index'])       ->name('index')  ->middleware('check.permission:pdcs.index');
        Route::get('uncleared',      [PdcController::class, 'uncleared'])   ->name('uncleared')->middleware('check.permission:pdcs.index');
        Route::get('{id}',           [PdcController::class, 'show'])        ->name('show')   ->middleware('check.permission:pdcs.index');
        Route::post('{id}/created',  [PdcController::class, 'markCreated']) ->name('mark_created')->middleware('check.permission:pdcs.edit');
        Route::post('{id}/signed',   [PdcController::class, 'markSigned'])  ->name('mark_signed') ->middleware('check.permission:pdcs.edit');
        Route::post('{id}/issued',   [PdcController::class, 'markIssued'])  ->name('mark_issued') ->middleware('check.permission:pdcs.edit');
        Route::post('{id}/cleared',  [PdcController::class, 'markCleared']) ->name('mark_cleared')->middleware('check.permission:pdcs.edit');
        Route::post('{id}/bounced',  [PdcController::class, 'markBounced']) ->name('mark_bounced')->middleware('check.permission:pdcs.edit');
    });
});