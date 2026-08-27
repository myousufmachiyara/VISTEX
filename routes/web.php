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
    VoucherController,
    ForecastController,
    PurchaseOrderController,
    PurchaseOrderObjectionController,
    PurchaseReceivingController,
    ConversionPurchaseOrderController,
    YarnIssueController,
    GreigeReceiveController,
};

Auth::routes();

Route::middleware(['auth'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ════════════════════════════════════════════════════════════════
    // MODULE 1-2: CHART OF ACCOUNTS, SUB HEADS, ACCOUNT MAPPINGS, TAX MASTER
    // ════════════════════════════════════════════════════════════════
    Route::get('coa', [COAController::class, 'index'])->name('coa.index')->middleware('check.permission:coa.index');
    Route::post('coa', [COAController::class, 'store'])->name('coa.store')->middleware('check.permission:coa.create');
    Route::get('coa/{id}/edit', [COAController::class, 'edit'])->name('coa.edit')->middleware('check.permission:coa.edit');
    Route::put('coa/{id}', [COAController::class, 'update'])->name('coa.update')->middleware('check.permission:coa.edit');
    Route::delete('coa/{id}', [COAController::class, 'destroy'])->name('coa.destroy')->middleware('check.permission:coa.delete');
    Route::get('/coa/import', [COAController::class, 'importForm'])->name('coa.import.form')->middleware('check.permission:coa.create');
    Route::post('/coa/import', [COAController::class, 'import'])->name('coa.import')->middleware('check.permission:coa.create');
    Route::get('/coa/import/template', [COAController::class, 'downloadImportTemplate'])->name('coa.import.template')->middleware('check.permission:coa.create');

    Route::get('shoa', [SubHeadOfAccController::class, 'index'])->name('shoa.index')->middleware('check.permission:shoa.index');
    Route::post('shoa', [SubHeadOfAccController::class, 'store'])->name('shoa.store')->middleware('check.permission:shoa.create');
    Route::get('shoa/{id}/edit', [SubHeadOfAccController::class, 'edit'])->name('shoa.edit')->middleware('check.permission:shoa.edit');
    Route::put('shoa/{id}', [SubHeadOfAccController::class, 'update'])->name('shoa.update')->middleware('check.permission:shoa.edit');
    Route::delete('shoa/{id}', [SubHeadOfAccController::class, 'destroy'])->name('shoa.destroy')->middleware('check.permission:shoa.delete');

    Route::get('accounts/mapping', [AccountMappingController::class, 'index'])->name('account-mappings.index')->middleware('check.permission:coa.edit');
    Route::put('accounts/mapping', [AccountMappingController::class, 'update'])->name('account-mappings.update')->middleware('check.permission:coa.edit');

    Route::get('tax-masters', [TaxMasterController::class, 'index'])->name('tax-masters.index')->middleware('check.permission:tax_masters.index');
    Route::post('tax-masters', [TaxMasterController::class, 'store'])->name('tax-masters.store')->middleware('check.permission:tax_masters.create');
    Route::get('tax-masters/{id}/edit', [TaxMasterController::class, 'edit'])->name('tax-masters.edit')->middleware('check.permission:tax_masters.edit');
    Route::put('tax-masters/{id}', [TaxMasterController::class, 'update'])->name('tax-masters.update')->middleware('check.permission:tax_masters.edit');
    Route::delete('tax-masters/{id}', [TaxMasterController::class, 'destroy'])->name('tax-masters.destroy')->middleware('check.permission:tax_masters.delete');

    // ════════════════════════════════════════════════════════════════
    // MODULE 3: PARTIES (CUSTOMER & VENDOR)
    // ════════════════════════════════════════════════════════════════
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index')->middleware('check.permission:customers.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store')->middleware('check.permission:customers.create');
    Route::get('customers/{id}/edit', [CustomerController::class, 'edit'])->name('customers.edit')->middleware('check.permission:customers.edit');
    Route::put('customers/{id}', [CustomerController::class, 'update'])->name('customers.update')->middleware('check.permission:customers.edit');
    Route::delete('customers/{id}', [CustomerController::class, 'destroy'])->name('customers.destroy')->middleware('check.permission:customers.delete');

    Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index')->middleware('check.permission:vendors.index');
    Route::post('vendors', [VendorController::class, 'store'])->name('vendors.store')->middleware('check.permission:vendors.create');
    Route::get('vendors/{id}/edit', [VendorController::class, 'edit'])->name('vendors.edit')->middleware('check.permission:vendors.edit');
    Route::put('vendors/{id}', [VendorController::class, 'update'])->name('vendors.update')->middleware('check.permission:vendors.edit');
    Route::delete('vendors/{id}', [VendorController::class, 'destroy'])->name('vendors.destroy')->middleware('check.permission:vendors.delete');

    Route::prefix('helpers')->name('helpers.')->group(function () {
        Route::get('vendors/search',   [VendorController::class, 'search'])->name('vendors.search');
        Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
        Route::get('accounts/search',  [COAController::class, 'search'])->name('accounts.search');
    });

    Route::get('/{type}/import', [PartyImportController::class, 'form'])->whereIn('type', ['customer', 'vendor'])->name('parties.import.form')->middleware('check.permission:customers.create');
    Route::post('/{type}/import', [PartyImportController::class, 'import'])->whereIn('type', ['customer', 'vendor'])->name('parties.import')->middleware('check.permission:customers.create');

    // ════════════════════════════════════════════════════════════════
    // MODULE 4: LOCATIONS
    // ════════════════════════════════════════════════════════════════
    Route::prefix('locations')->name('locations.')->group(function () {
        Route::get('/',          [LocationController::class, 'index'])   ->name('index')  ->middleware('check.permission:locations.index');
        Route::post('/',         [LocationController::class, 'store'])   ->name('store')  ->middleware('check.permission:locations.create');
        Route::get('{id}/edit',  [LocationController::class, 'edit'])    ->name('edit')   ->middleware('check.permission:locations.edit');
        Route::put('{id}',       [LocationController::class, 'update'])  ->name('update') ->middleware('check.permission:locations.edit');
        Route::delete('{id}',    [LocationController::class, 'destroy']) ->name('destroy')->middleware('check.permission:locations.delete');
    });

    // ════════════════════════════════════════════════════════════════
    // MODULE 5: PRODUCT CATEGORIES + CATEGORY IN-CHARGES
    // ════════════════════════════════════════════════════════════════
    Route::get('product-categories', [ProductCategoryController::class, 'index'])->name('product_categories.index')->middleware('check.permission:product_categories.index');
    Route::post('product-categories', [ProductCategoryController::class, 'store'])->name('product_categories.store')->middleware('check.permission:product_categories.create');
    Route::get('product-categories/{id}/edit', [ProductCategoryController::class, 'edit'])->name('product_categories.edit')->middleware('check.permission:product_categories.edit');
    Route::put('product-categories/{id}', [ProductCategoryController::class, 'update'])->name('product_categories.update')->middleware('check.permission:product_categories.edit');
    Route::delete('product-categories/{id}', [ProductCategoryController::class, 'destroy'])->name('product_categories.destroy')->middleware('check.permission:product_categories.delete');

    Route::get('measurement-units', [MeasurementUnitController::class, 'index'])->name('measurement_units.index')->middleware('check.permission:measurement_units.index');
    Route::post('measurement-units', [MeasurementUnitController::class, 'store'])->name('measurement_units.store')->middleware('check.permission:measurement_units.create');
    Route::get('measurement-units/{id}/edit', [MeasurementUnitController::class, 'edit'])->name('measurement_units.edit')->middleware('check.permission:measurement_units.edit');
    Route::put('measurement-units/{id}', [MeasurementUnitController::class, 'update'])->name('measurement_units.update')->middleware('check.permission:measurement_units.edit');
    Route::delete('measurement-units/{id}', [MeasurementUnitController::class, 'destroy'])->name('measurement_units.destroy')->middleware('check.permission:measurement_units.delete');

    // ════════════════════════════════════════════════════════════════
    // MODULE 7: PRODUCTS
    // ════════════════════════════════════════════════════════════════
    Route::get('products', [ProductController::class, 'index'])->name('products.index')->middleware('check.permission:products.index');
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create')->middleware('check.permission:products.create');
    Route::post('products', [ProductController::class, 'store'])->name('products.store')->middleware('check.permission:products.create');
    Route::get('products/{id}/edit', [ProductController::class, 'edit'])->name('products.edit')->middleware('check.permission:products.edit');
    Route::put('products/{id}', [ProductController::class, 'update'])->name('products.update')->middleware('check.permission:products.edit');
    Route::delete('products/{id}', [ProductController::class, 'destroy'])->name('products.destroy')->middleware('check.permission:products.delete');
    Route::get('helpers/products/attribute-schema/{categoryId}', [ProductController::class, 'attributeSchema'])->name('helpers.products.attribute_schema');
    Route::get('/products/import', [ProductController::class, 'importForm'])->name('products.import.form')->middleware('check.permission:products.create');
    Route::post('/products/import', [ProductController::class, 'import'])->name('products.import')->middleware('check.permission:products.create');
    Route::get('/products/import-xero', [ProductController::class, 'xeroImportForm'])->name('products.import_xero.form')->middleware('check.permission:products.create');
    Route::post('/products/import-xero', [ProductController::class, 'xeroImport'])->name('products.import_xero')->middleware('check.permission:products.create');
    
    // ════════════════════════════════════════════════════════════════
    // MODULE 8: USERS, ROLES & PERMISSIONS
    // ════════════════════════════════════════════════════════════════
    Route::prefix('users')->name('users.')->group(function () {
        Route::put('{id}/change-password', [UserController::class, 'changePassword'])->name('changePassword');
        Route::put('{id}/toggle-active',   [UserController::class, 'toggleActive'])->name('toggleActive');
        Route::post('change-my-password',  [UserController::class, 'changeMyPassword'])->name('changeMyPassword');
    });
    Route::resource('roles', RoleController::class)->except(['create', 'edit', 'show'])->middleware('check.permission:user_roles.index');
    Route::resource('permissions', PermissionController::class)->except(['create', 'edit', 'show'])->middleware('check.permission:user_roles.index');
    Route::get('users', [UserController::class, 'index'])->name('users.index')->middleware('check.permission:users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store')->middleware('check.permission:users.create');
    Route::get('users/{id}/edit', [UserController::class, 'edit'])->name('users.edit')->middleware('check.permission:users.edit');
    Route::put('users/{id}', [UserController::class, 'update'])->name('users.update')->middleware('check.permission:users.edit');
    Route::delete('users/{id}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('check.permission:users.delete');

    // ════════════════════════════════════════════════════════════════
    // MODULE 9: SERVICE TYPES
    // ════════════════════════════════════════════════════════════════
    Route::get('service-types', [ServiceTypeController::class, 'index'])->name('service_types.index')->middleware('check.permission:service_types.index');
    Route::post('service-types', [ServiceTypeController::class, 'store'])->name('service_types.store')->middleware('check.permission:service_types.create');
    Route::get('service-types/{id}/edit', [ServiceTypeController::class, 'edit'])->name('service_types.edit')->middleware('check.permission:service_types.edit');
    Route::put('service-types/{id}', [ServiceTypeController::class, 'update'])->name('service_types.update')->middleware('check.permission:service_types.edit');
    Route::delete('service-types/{id}', [ServiceTypeController::class, 'destroy'])->name('service_types.destroy')->middleware('check.permission:service_types.delete');

    // ════════════════════════════════════════════════════════════════
    // MODULE 10: CUSTOMER SKU RATES
    // ════════════════════════════════════════════════════════════════
    Route::prefix('customer-sku-rates')->name('customer_sku_rates.')->group(function () {
        Route::get('/',                               [CustomerSkuRateController::class, 'index'])   ->name('index')  ->middleware('check.permission:customer_sku_rates.index');
        Route::post('/',                               [CustomerSkuRateController::class, 'store'])   ->name('store')  ->middleware('check.permission:customer_sku_rates.create');
        Route::get('history/{customerId}/{productId}', [CustomerSkuRateController::class, 'history']) ->name('history')->middleware('check.permission:customer_sku_rates.index');
        Route::get('suggest',                          [CustomerSkuRateController::class, 'suggest']) ->name('suggest')->middleware('check.permission:customer_sku_rates.index');
        Route::delete('{id}',                          [CustomerSkuRateController::class, 'destroy']) ->name('destroy')->middleware('check.permission:customer_sku_rates.delete');
    });

    // ════════════════════════════════════════════════════════════════
    // MODULE 11: VOUCHER ENGINE
    // ════════════════════════════════════════════════════════════════
    Route::prefix('vouchers/{type}')->name('vouchers.')->group(function () {
        Route::get('/',        [VoucherController::class, 'index'])   ->name('index')  ->middleware('check.permission:vouchers.index');
        Route::get('/create',  [VoucherController::class, 'create'])  ->name('create') ->middleware('check.permission:vouchers.create');
        Route::post('/',       [VoucherController::class, 'store'])   ->name('store')  ->middleware('check.permission:vouchers.create');
        Route::get('/{id}',    [VoucherController::class, 'show'])    ->name('show')   ->middleware('check.permission:vouchers.index');
        Route::delete('/{id}', [VoucherController::class, 'destroy']) ->name('destroy')->middleware('check.permission:vouchers.delete');
    });

    // ════════════════════════════════════════════════════════════════
    // MODULE 12: FORECASTING / PLANNING
    // ════════════════════════════════════════════════════════════════
    Route::prefix('forecasts')->name('forecasts.')->group(function () {
        Route::get('/',                                 [ForecastController::class, 'index'])   ->name('index')  ->middleware('check.permission:forecasts.index');
        Route::get('create',                             [ForecastController::class, 'create'])  ->name('create') ->middleware('check.permission:forecasts.create');
        Route::post('/',                                 [ForecastController::class, 'store'])   ->name('store')  ->middleware('check.permission:forecasts.create');
        Route::post('{id}/approve',                      [ForecastController::class, 'approve']) ->name('approve')->middleware('check.permission:forecasts.edit');
        Route::post('{id}/reject',                       [ForecastController::class, 'reject'])  ->name('reject') ->middleware('check.permission:forecasts.edit');
        Route::delete('{id}',                             [ForecastController::class, 'destroy']) ->name('destroy')->middleware('check.permission:forecasts.delete');
        Route::get('approved-for-product/{productId}',   [ForecastController::class, 'approvedForProduct'])->name('approved_for_product')->middleware('check.permission:forecasts.index');
    });

    // ════════════════════════════════════════════════════════════════
    // MODULE 13: PURCHASE ORDER
    // ════════════════════════════════════════════════════════════════
    Route::prefix('purchase-orders')->name('purchase_orders.')->group(function () {
        Route::get('/',                                [PurchaseOrderController::class, 'index'])              ->name('index')  ->middleware('check.permission:purchase_orders.index');
        Route::get('create',                           [PurchaseOrderController::class, 'create'])             ->name('create') ->middleware('check.permission:purchase_orders.create');
        Route::get('vendor-locations/{vendorId}',      [PurchaseOrderController::class, 'vendorLocations'])    ->name('vendor_locations') ->middleware('check.permission:purchase_orders.index');
        Route::get('category-products/{categoryId}',   [PurchaseOrderController::class, 'categoryProducts'])   ->name('category_products') ->middleware('check.permission:purchase_orders.index');
        Route::get('forecasts-for-product/{productId}', [PurchaseOrderController::class, 'forecastsForProduct'])->name('forecasts_for_product')->middleware('check.permission:purchase_orders.index');
        Route::post('/',                                [PurchaseOrderController::class, 'store'])              ->name('store')  ->middleware('check.permission:purchase_orders.create');
        Route::get('{id}/edit',                         [PurchaseOrderController::class, 'edit'])               ->name('edit')   ->middleware('check.permission:purchase_orders.edit');
        Route::put('{id}',                              [PurchaseOrderController::class, 'update'])             ->name('update') ->middleware('check.permission:purchase_orders.edit');
        Route::delete('{id}',                           [PurchaseOrderController::class, 'destroy'])            ->name('destroy')->middleware('check.permission:purchase_orders.delete');
        Route::get('{id}/object',                       [PurchaseReceivingController::class, 'objectionForm'])  ->name('object')       ->middleware('check.permission:purchase_orders.index');
        Route::post('{id}/object',                      [PurchaseReceivingController::class, 'objectionStore']) ->name('object.store') ->middleware('check.permission:purchase_orders.index');
        Route::get('/{id}/print',                       [PurchaseOrderController::class, 'print'])              ->name('print')->middleware('check.permission:purchase_orders.print');
    });

    Route::get('purchase-order-objections', [PurchaseOrderObjectionController::class, 'index'])->name('purchase_order_objections.index')->middleware('check.permission:purchase_orders.index');
    // ════════════════════════════════════════════════════════════════
    // MODULE 14: PURCHASE RECEIVING
    // ════════════════════════════════════════════════════════════════
    Route::prefix('purchase-receivings')->name('purchase_receivings.')->group(function () {
        Route::get('/',                  [PurchaseReceivingController::class, 'index'])      ->name('index')  ->middleware('check.permission:purchase_receivings.index');
        Route::get('create',             [PurchaseReceivingController::class, 'create'])     ->name('create') ->middleware('check.permission:purchase_receivings.create');
        Route::get('outstanding/{poId}', [PurchaseReceivingController::class, 'outstanding']) ->name('outstanding')->middleware('check.permission:purchase_receivings.index');
        Route::get('history/{poId}',     [PurchaseReceivingController::class, 'history'])     ->name('history')->middleware('check.permission:purchase_receivings.index');
        Route::post('/',                 [PurchaseReceivingController::class, 'store'])      ->name('store')  ->middleware('check.permission:purchase_receivings.create');
        Route::post('{id}/approve',      [PurchaseReceivingController::class, 'approve'])    ->name('approve')->middleware('check.permission:purchase_receivings.edit');
        Route::post('{id}/reject',       [PurchaseReceivingController::class, 'reject'])     ->name('reject') ->middleware('check.permission:purchase_receivings.edit');
        Route::get('{id}/print',         [PurchaseReceivingController::class, 'print'])      ->name('print')  ->middleware('check.permission:purchase_receivings.print');
        Route::delete('{id}',            [PurchaseReceivingController::class, 'destroy'])    ->name('destroy')->middleware('check.permission:purchase_receivings.delete');
        Route::get('{id}',               [PurchaseReceivingController::class, 'show'])       ->name('show')   ->middleware('check.permission:purchase_receivings.index');
    });

    // ════════════════════════════════════════════════════════════════
    // MODULE 15: CPO (CONVERSION PURCHASE ORDER / WEAVING PO)
    // ════════════════════════════════════════════════════════════════
    Route::prefix('cpo')->name('cpo.')->group(function () {
        Route::get('/',          [ConversionPurchaseOrderController::class, 'index'])     ->name('index')  ->middleware('check.permission:cpo.index');
        Route::get('create',     [ConversionPurchaseOrderController::class, 'create'])    ->name('create') ->middleware('check.permission:cpo.create');
        Route::post('calculate', [ConversionPurchaseOrderController::class, 'calculate']) ->name('calculate')->middleware('check.permission:cpo.index');
        Route::post('/',         [ConversionPurchaseOrderController::class, 'store'])     ->name('store')  ->middleware('check.permission:cpo.create');
        Route::delete('{id}',    [ConversionPurchaseOrderController::class, 'destroy'])   ->name('destroy')->middleware('check.permission:cpo.delete');
        Route::get('cpo/{id}/edit', [ConversionPurchaseOrderController::class, 'edit'])->name('cpo.edit')->middleware('check.permission:cpo.edit');
        Route::put('cpo/{id}', [ConversionPurchaseOrderController::class, 'update'])->name('cpo.update')->middleware('check.permission:cpo.edit');
    });

    // ════════════════════════════════════════════════════════════════
    // MODULE 16: YARN ISSUE
    // ════════════════════════════════════════════════════════════════
    Route::prefix('yarn-issues')->name('yarn_issues.')->group(function () {
        Route::get('/',                    [YarnIssueController::class, 'index'])      ->name('index')  ->middleware('check.permission:yarn_issues.index');
        Route::get('create',               [YarnIssueController::class, 'create'])     ->name('create') ->middleware('check.permission:yarn_issues.create');
        Route::get('cpo-details/{cpoId}',  [YarnIssueController::class, 'cpoDetails']) ->name('cpo_details')->middleware('check.permission:yarn_issues.index');
        Route::post('/',                    [YarnIssueController::class, 'store'])      ->name('store')  ->middleware('check.permission:yarn_issues.create');
        Route::delete('{id}',               [YarnIssueController::class, 'destroy'])    ->name('destroy')->middleware('check.permission:yarn_issues.delete');
        Route::get('yarn-issues/{id}/edit', [YarnIssueController::class, 'edit'])->name('yarn_issues.edit')->middleware('check.permission:yarn_issues.edit');
        Route::put('yarn-issues/{id}', [YarnIssueController::class, 'update'])->name('yarn_issues.update')->middleware('check.permission:yarn_issues.edit');
    
    });

    // ════════════════════════════════════════════════════════════════
    // MODULE 17: GREIGE RECEIVE (AGAINST CPO)
    // ════════════════════════════════════════════════════════════════
    Route::prefix('greige-receives')->name('greige_receives.')->group(function () {
        Route::get('/',                          [GreigeReceiveController::class, 'index'])          ->name('index')  ->middleware('check.permission:greige_receives.index');
        Route::get('create',                     [GreigeReceiveController::class, 'create'])         ->name('create') ->middleware('check.permission:greige_receives.create');
        Route::get('cpo-yarn-balance/{cpoId}',   [GreigeReceiveController::class, 'cpoYarnBalance']) ->name('cpo_yarn_balance')->middleware('check.permission:greige_receives.index');
        Route::post('/',                          [GreigeReceiveController::class, 'store'])          ->name('store')  ->middleware('check.permission:greige_receives.create');
        Route::post('{id}/approve',               [GreigeReceiveController::class, 'approve'])        ->name('approve')->middleware('check.permission:greige_receives.edit');
        Route::post('{id}/reject',                [GreigeReceiveController::class, 'reject'])         ->name('reject') ->middleware('check.permission:greige_receives.edit');
        Route::delete('{id}',                     [GreigeReceiveController::class, 'destroy'])        ->name('destroy')->middleware('check.permission:greige_receives.delete');
    });
});