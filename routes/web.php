<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\OrderReductionController;
use App\Http\Controllers\OrderPieceReassignmentController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\FabricBatchController;
use App\Http\Controllers\ProductionAssignmentController;
use App\Http\Controllers\NaeemPakkiController;
use App\Http\Controllers\StitchingReturnController;
use App\Http\Controllers\TarpaiController;
use App\Http\Controllers\PressController;
use App\Http\Controllers\OutsourcedBatchController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\WagesController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StitchingUnitController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductionAlertController;
use App\Http\Controllers\ProductionTrackerController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\ActiveCatalogueController;
use App\Http\Controllers\AdvancePaymentController;
use App\Http\Controllers\TarpaiPaymentController;
use App\Http\Controllers\CronLogController;
use App\Http\Controllers\OrderBankAssignmentController;
use App\Http\Controllers\BankCollectionReportController;
use App\Http\Controllers\OrderAdjustController;
use App\Http\Controllers\DesignCountryPriceController;
use App\Http\Controllers\PieceTagController;
use App\Http\Controllers\DispatchOptimizerController;
use App\Http\Controllers\CostEstimationController;
use App\Http\Controllers\CatalogueHdImageController;
use App\Http\Controllers\GalleryController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (no login required)
|--------------------------------------------------------------------------
*/

// Login
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Public catalogue order form (shared via WhatsApp)
Route::get('/og-image/{token}', [OgImageController::class, 'show'])->name('og.image');
Route::get('/order/{token}',  [PublicOrderController::class, 'show'])->name('order.public');
Route::post('/order/{token}', [PublicOrderController::class, 'submit'])->name('order.submit');
Route::get('/order/{token}/thankyou', [PublicOrderController::class, 'thankyou'])->name('order.thankyou');

// Customer self-service portal (unique link per customer)
Route::get('/portal/{token}',              [CustomerPortalController::class, 'show'])->name('portal.show');
Route::post('/portal/{token}/verify',      [CustomerPortalController::class, 'verify'])->name('portal.verify');
Route::get('/portal/{token}/manifest.json',[CustomerPortalController::class, 'manifest'])->name('portal.manifest');
Route::post('/portal/{token}/push-subscribe',[CustomerPortalController::class, 'pushSubscribe'])->name('portal.push-subscribe');

// Piece tag barcode scan result (read by any barcode scanner/phone camera)
Route::get('/tags/{barcode}', [PieceTagController::class, 'scan'])->name('tags.scan');

// HD image gallery — shareable link customers use to browse and download full-res photos
Route::get('/gallery/{token}', [GalleryController::class, 'show'])->name('gallery.show');
Route::get('/gallery/{token}/images/{hdImage}/download', [GalleryController::class, 'download'])->name('gallery.download');

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Active Catalogue session setter (manager + admin — visible via sidebar widget)
    Route::post('/active-catalogue', [ActiveCatalogueController::class, 'store'])->name('active-catalogue.store');

    /*
    |------------------------------------------------------------------
    | CATALOGUE MANAGEMENT (admin + designer view, admin creates)
    |------------------------------------------------------------------
    */
    Route::resource('catalogues', CatalogueController::class);
    Route::post('catalogues/{catalogue}/close',  [CatalogueController::class, 'close'])->name('catalogues.close');
    Route::post('catalogues/{catalogue}/reopen', [CatalogueController::class, 'reopen'])->name('catalogues.reopen');
    Route::resource('catalogues.designs', DesignController::class)->shallow();

    // HD Image Gallery management (admin + creative_head only)
    Route::middleware('role:admin|creative_head')->group(function () {
        // Sidebar entry point — resolves the active catalogue from the session picker
        Route::get('hd-gallery', [CatalogueHdImageController::class, 'active'])->name('hd-gallery.index');

        Route::get('catalogues/{catalogue}/hd-images',    [CatalogueHdImageController::class, 'index'])->name('catalogues.hd-images.index');
        Route::post('catalogues/{catalogue}/hd-images/presign', [CatalogueHdImageController::class, 'presign'])->name('catalogues.hd-images.presign');
        Route::post('catalogues/{catalogue}/hd-images',   [CatalogueHdImageController::class, 'store'])->name('catalogues.hd-images.store');
        Route::delete('catalogues/{catalogue}/hd-images/{hdImage}', [CatalogueHdImageController::class, 'destroy'])->name('catalogues.hd-images.destroy');
    });

    /*
    |------------------------------------------------------------------
    | CUSTOMER MANAGEMENT
    | View/edit: admin + accountant + production_manager (no financials for production_manager)
    | Create + all financial sub-routes (ledger, advance payments): admin + accountant only
    |------------------------------------------------------------------
    */
    Route::middleware('role:admin|accountant|production_manager')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    });

    Route::middleware('role:admin|accountant')->group(function () {
        Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    });

    Route::middleware('role:admin|accountant|production_manager')->group(function () {
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::patch('customers/{customer}', [CustomerController::class, 'update']);
    });

    Route::middleware('role:admin|accountant')->group(function () {
        Route::get('customers/{customer}/ledger',     [LedgerController::class, 'show'])->name('customers.ledger');
        Route::get('customers/{customer}/ledger/pdf', [LedgerController::class, 'pdf'])->name('customers.ledger.pdf');

        Route::post('customers/{customer}/advance-payments', [AdvancePaymentController::class, 'store'])->name('advance-payments.store');
        Route::get('customers/{customer}/advance-payments/{advancePayment}/edit', [AdvancePaymentController::class, 'edit'])->name('advance-payments.edit');
        Route::put('customers/{customer}/advance-payments/{advancePayment}', [AdvancePaymentController::class, 'update'])->name('advance-payments.update');
        Route::delete('customers/{customer}/advance-payments/{advancePayment}', [AdvancePaymentController::class, 'destroy'])->name('advance-payments.destroy');
    });

    /*
    |------------------------------------------------------------------
    | ORDERS & PAYMENTS (admin + accountant)
    | Index + downloads also accessible to production_manager (no financials shown)
    |------------------------------------------------------------------
    */

    // Index + downloads — production_manager and creative_head allowed (financials hidden in view/export)
    Route::middleware('role:admin|accountant|production_manager|creative_head')->group(function () {
        Route::get('orders',       [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/pdf',   [OrderController::class, 'downloadPdf'])->name('orders.pdf');
        Route::get('orders/excel', [OrderController::class, 'downloadExcel'])->name('orders.excel');
    });

    Route::middleware('role:admin|accountant')->group(function () {
        Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
        Route::resource('orders', OrderController::class)->only(['show', 'edit', 'update']);
        Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
        Route::post('orders/{order}/confirm',  [OrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order}/stitch',   [OrderController::class, 'markStitching'])->name('orders.stitch');
        // Payments
        Route::resource('orders.payments', PaymentController::class)->only(['store']);
        Route::get('orders/{order}/payments/{payment}/edit', [PaymentController::class, 'edit'])->name('orders.payments.edit');
        Route::put('orders/{order}/payments/{payment}', [PaymentController::class, 'update'])->name('orders.payments.update');
        Route::delete('orders/{order}/payments/{payment}', [PaymentController::class, 'destroy'])->name('orders.payments.destroy');
        Route::post('orders/{order}/apply-credit', [PaymentController::class, 'applyCredit'])->name('orders.apply-credit');

        // Bank assignment (per order + bulk)
        Route::patch('orders/{order}/assign-bank', [OrderBankAssignmentController::class, 'update'])->name('orders.assign-bank');
        Route::post('orders/bulk-assign-bank', [OrderBankAssignmentController::class, 'bulkAssign'])->name('orders.bulk-assign-bank');
    });

    // Order Reductions (admin + accountant)
    Route::middleware('role:admin|accountant')->group(function () {
        Route::get('orders/{order}/reduce',  [OrderReductionController::class, 'create'])->name('orders.reduce');
        Route::post('orders/{order}/reduce', [OrderReductionController::class, 'store'])->name('orders.reduce.store');
        Route::get('orders/{order}/reductions/{reduction}', [OrderReductionController::class, 'show'])->name('orders.reductions.show');

        // Adjust Order (admin + accountant — change size quantities before dispatch)
        Route::get('orders/{order}/adjust',  [OrderAdjustController::class, 'create'])->name('orders.adjust');
        Route::post('orders/{order}/adjust', [OrderAdjustController::class, 'store'])->name('orders.adjust.store');
    });

    // Piece Reassignment (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('orders/{order}/reassign-pieces',  [OrderPieceReassignmentController::class, 'create'])->name('orders.reassign.create');
        Route::post('orders/{order}/reassign-pieces', [OrderPieceReassignmentController::class, 'store'])->name('orders.reassign.store');
    });

    /*
    |------------------------------------------------------------------
    | IN-HOUSE PRODUCTION TRACKING (production_manager + creative_head read-only)
    |------------------------------------------------------------------
    */
    Route::middleware('role:admin|production_manager|creative_head')->group(function () {
        // Fabric batch arrivals
        Route::resource('fabric-batches', FabricBatchController::class)->only(['index','create','store','show']);

        // Production route assignments (Naeem Pakki / Stitching Unit)
        Route::resource('production-assignments', ProductionAssignmentController::class)->only(['index','create','store','show']);
        Route::patch('production-assignments/{productionAssignment}/np-designs/{npDesign}/rate', [ProductionAssignmentController::class, 'updateNpRate'])->name('production-assignments.np-designs.update-rate');

        // Naeem Pakki (one row per assignment; batch returns with per-design breakdown)
        Route::get('naeem-pakki-sends', [NaeemPakkiController::class, 'index'])->name('naeem-pakki-sends.index');
        Route::get('naeem-pakki-sends/{productionAssignment}', [NaeemPakkiController::class, 'show'])->name('naeem-pakki-sends.show');
        Route::post('naeem-pakki-sends/{productionAssignment}/return', [NaeemPakkiController::class, 'logReturn'])->name('naeem-pakki.return');

        // Stitching returns (assignment-centric)
        Route::resource('stitching-returns', StitchingReturnController::class)->only(['index', 'show']);
        Route::get('stitching-assignments/{productionAssignment}', [StitchingReturnController::class, 'showAssignment'])->name('stitching-assignments.show');
        Route::get('stitching-assignments/{productionAssignment}/report', [StitchingReturnController::class, 'reportAssignment'])->name('stitching-assignments.report');
        Route::post('stitching-assignments/{productionAssignment}/return', [StitchingReturnController::class, 'storeReturn'])->name('stitching-assignments.return');

        // Tarpai finishing
        Route::resource('tarpai-sends', TarpaiController::class)->only(['index','create','store','show','destroy']);
        Route::post('tarpai-sends/{send}/return', [TarpaiController::class, 'logReturn'])->name('tarpai.return');
        Route::delete('tarpai-sends/{send}/returns/{return}', [TarpaiController::class, 'destroyReturn'])->name('tarpai.return.destroy');
        Route::get('tarpai-sends/{tarpaiSend}/gate-pass', [TarpaiController::class, 'gatePass'])->name('tarpai.gate-pass');

        // Press sends and returns
        Route::resource('press-sends', PressController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('press-sends/{pressSend}/return', [PressController::class, 'logReturn'])->name('press.return');

        // Outsourced batch arrivals
        Route::resource('outsourced-batches', OutsourcedBatchController::class)->only(['index','create','store','show']);

        // Dispatch
        Route::get('dispatch',                         [DispatchController::class, 'index'])->name('dispatch.index');
        Route::get('dispatch/{order}',                 [DispatchController::class, 'show'])->name('dispatch.show');
        Route::get('dispatch/{order}/create',          [DispatchController::class, 'create'])->name('dispatch.create');
        Route::get('dispatch/{order}/workspace',       [DispatchController::class, 'workspace'])->name('dispatch.workspace');
        Route::post('dispatch/{order}',                [DispatchController::class, 'store'])->name('dispatch.store');
        Route::get('dispatch/{order}/sack-label',      [DispatchController::class, 'sackLabel'])->name('dispatch.sack-label');
        Route::get('dispatch/{order}/print-tags',      [DispatchController::class, 'printTags'])->name('dispatch.print-tags');
        Route::post('dispatch-batches/{dispatchBatch}/cargo-document', [DispatchController::class, 'updateCargoDocument'])->name('dispatch-batches.cargo-document');

        // Dispatch Optimizer — recommends which pending orders to dispatch to best clear packed inventory
        Route::get('dispatch-optimizer', [DispatchOptimizerController::class, 'index'])->name('dispatch-optimizer.index');

        // Cost Estimation (per design) — production qty and Pakki Embroidery rate are system-derived,
        // the rest is filled in by the production manager
        Route::get('designs/{design}/cost-estimation',     [CostEstimationController::class, 'edit'])->name('designs.cost-estimation.edit');
        Route::post('designs/{design}/cost-estimation',    [CostEstimationController::class, 'update'])->name('designs.cost-estimation.update');
        Route::get('designs/{design}/cost-estimation/pdf', [CostEstimationController::class, 'pdf'])->name('designs.cost-estimation.pdf');

    });

    // Worker wages (admin, production_manager, accountant, creative_head read-only)
    Route::middleware('role:admin|production_manager|accountant|creative_head')->group(function () {
        Route::get('wages',                    [WagesController::class, 'index'])->name('wages.index');
        Route::get('wages/{wage}',             [WagesController::class, 'show'])->name('wages.show');
        Route::post('wages/recalculate',       [WagesController::class, 'recalculate'])->name('wages.recalculate');
        Route::post('wages/{wage}/confirm',    [WagesController::class, 'confirm'])->name('wages.confirm');
    });

    // Tarpai charges (admin, production_manager, accountant, creative_head read-only)
    Route::middleware('role:admin|production_manager|accountant|creative_head')->group(function () {
        Route::get('tarpai-charges',                             [TarpaiPaymentController::class, 'index'])->name('tarpai-charges.index');
        Route::get('tarpai-charges/{tarpaiPayment}',             [TarpaiPaymentController::class, 'show'])->name('tarpai-charges.show');
        Route::post('tarpai-charges/recalculate',                [TarpaiPaymentController::class, 'recalculate'])->name('tarpai-charges.recalculate');
        Route::post('tarpai-charges/{tarpaiPayment}/confirm',    [TarpaiPaymentController::class, 'confirm'])->name('tarpai-charges.confirm');
    });

    // Packed Inventory (manager + admin)
    Route::get('packed-inventory', [PressController::class, 'inventory'])->name('packed-inventory.index');

    // Production Tracker (manager + admin)
    Route::get('production-tracker', [ProductionTrackerController::class, 'index'])->name('production.tracker');

    // Production Alerts — resolving a stale-assignment alert is a write action (admin + production_manager only)
    Route::middleware('role:admin|production_manager|creative_head')->group(function () {
        Route::post('production-alerts/{alert}/resolve', [ProductionAlertController::class, 'resolve'])->name('production-alerts.resolve');
    });

    /*
    |------------------------------------------------------------------
    | REPORTS (admin + accountant)
    |------------------------------------------------------------------
    */
    Route::middleware('role:admin|accountant')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/',                     [ReportController::class, 'index'])->name('index');
        Route::get('catalogue-summary',     [ReportController::class, 'catalogueSummary'])->name('catalogue-summary');
        Route::get('customer-master-list',  [ReportController::class, 'customerMasterList'])->name('customer-master-list');
        Route::get('customer-orders',       [ReportController::class, 'customerOrders'])->name('customer-orders');
        Route::get('customer-ledger',       [ReportController::class, 'customerLedger'])->name('customer-ledger');
        Route::get('production-status',     [ReportController::class, 'productionStatus'])->name('production-status');
        Route::get('stitching-reconciliation', [ReportController::class, 'stitchingReconciliation'])->name('stitching-reconciliation');
        Route::get('packed-inventory',      [ReportController::class, 'packedInventory'])->name('packed-inventory');
        Route::get('payroll-history',       [ReportController::class, 'payrollHistory'])->name('payroll-history');
        Route::get('outsourced-designs',    [ReportController::class, 'outsourcedDesigns'])->name('outsourced-designs');
        Route::get('dispatch-history',       [ReportController::class, 'dispatchHistory'])->name('dispatch-history');
        Route::get('dispatch-history/pdf',   [ReportController::class, 'dispatchHistoryPdf'])->name('dispatch-history.pdf');
        Route::get('dispatch-history/excel', [ReportController::class, 'dispatchHistoryExcel'])->name('dispatch-history.excel');
        Route::get('activity-log',          [ReportController::class, 'activityLog'])->name('activity-log');
        Route::get('damage-reductions',     [ReportController::class, 'damageReductions'])->name('damage-reductions');
        Route::get('bank-collection',       [BankCollectionReportController::class, 'index'])->name('bank-collection');
        Route::get('bank-collection/pdf',   [BankCollectionReportController::class, 'pdf'])->name('bank-collection.pdf');
        Route::get('bank-collection/excel', [BankCollectionReportController::class, 'excel'])->name('bank-collection.excel');

        // Payment reports
        Route::get('customer-order-bill',           [ReportController::class, 'customerOrderBill'])->name('customer-order-bill');
        Route::get('customer-order-bill/pdf',       [ReportController::class, 'customerOrderBillPdf'])->name('customer-order-bill.pdf');
        Route::get('customer-order-bill/excel',     [ReportController::class, 'customerOrderBillExcel'])->name('customer-order-bill.excel');
        Route::get('bank-account-breakdown',        [ReportController::class, 'bankAccountBreakdown'])->name('bank-account-breakdown');
        Route::get('bank-account-breakdown/pdf',    [ReportController::class, 'bankAccountBreakdownPdf'])->name('bank-account-breakdown.pdf');
        Route::get('bank-account-breakdown/excel',  [ReportController::class, 'bankAccountBreakdownExcel'])->name('bank-account-breakdown.excel');
        Route::get('receivables-by-bank',           [ReportController::class, 'receivablesByBank'])->name('receivables-by-bank');
        Route::get('receivables-by-bank/pdf',       [ReportController::class, 'receivablesByBankPdf'])->name('receivables-by-bank.pdf');
        Route::get('receivables-by-bank/excel',     [ReportController::class, 'receivablesByBankExcel'])->name('receivables-by-bank.excel');
    });

    /*
    |------------------------------------------------------------------
    | STITCHING UNIT MANAGEMENT (admin only)
    |------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {
        Route::resource('stitching-units', StitchingUnitController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::post('stitching-units/{stitchingUnit}/toggle', [StitchingUnitController::class, 'toggle'])->name('stitching-units.toggle');

        // Bank account management
        Route::get('bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
        Route::post('bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store');
        Route::post('bank-accounts/{bankAccount}/toggle', [BankAccountController::class, 'toggle'])->name('bank-accounts.toggle');

        // Country pricing for tags (per design, per destination country)
        Route::get('country-pricing', [DesignCountryPriceController::class, 'index'])->name('country-pricing.index');
        Route::post('country-pricing/{catalogue}', [DesignCountryPriceController::class, 'store'])->name('country-pricing.store');
    });

    /*
    |------------------------------------------------------------------
    | USER MANAGEMENT (admin only)
    |------------------------------------------------------------------
    */
    // Cron job execution log (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('cron-logs', [CronLogController::class, 'index'])->name('cron-logs.index');
    });

    Route::middleware('role:admin')->prefix('users')->name('users.')->group(function () {
        Route::get('/',                          [UserManagementController::class, 'index'])->name('index');
        Route::get('/create',                    [UserManagementController::class, 'create'])->name('create');
        Route::post('/',                         [UserManagementController::class, 'store'])->name('store');
        Route::post('{user}/enable',             [UserManagementController::class, 'enable'])->name('enable');
        Route::post('{user}/disable',            [UserManagementController::class, 'disable'])->name('disable');
        Route::post('{user}/reset-password',     [UserManagementController::class, 'resetPassword'])->name('reset-password');
    });

    /*
    |------------------------------------------------------------------
    | BACKUPS (admin only)
    |------------------------------------------------------------------
    */
    Route::middleware('role:admin')->prefix('backups')->name('backups.')->group(function () {
        Route::get('/',                           [BackupController::class, 'index'])->name('index');
        Route::post('/',                          [BackupController::class, 'store'])->name('store');
        Route::get('{filename}/download',         [BackupController::class, 'download'])->name('download');
        Route::delete('{filename}',               [BackupController::class, 'destroy'])->name('destroy');
    });

    /*
    |------------------------------------------------------------------
    | PROFILE (all authenticated users)
    |------------------------------------------------------------------
    */
    Route::get('profile',         [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile',         [ProfileController::class, 'update'])->name('profile.update');
});
