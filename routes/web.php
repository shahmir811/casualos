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
use App\Http\Controllers\ProductionTrackerController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\ActiveCatalogueController;

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
Route::get('/order/{token}',  [PublicOrderController::class, 'show'])->name('order.public');
Route::post('/order/{token}', [PublicOrderController::class, 'submit'])->name('order.submit');
Route::get('/order/{token}/thankyou', [PublicOrderController::class, 'thankyou'])->name('order.thankyou');

// Customer self-service portal (unique link per customer)
Route::get('/portal/{token}',        [CustomerPortalController::class, 'show'])->name('portal.show');
Route::post('/portal/{token}/verify',[CustomerPortalController::class, 'verify'])->name('portal.verify');

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

    /*
    |------------------------------------------------------------------
    | CUSTOMER MANAGEMENT (admin + accountant only)
    |------------------------------------------------------------------
    */
    Route::middleware('role:admin|accountant')->group(function () {
        Route::resource('customers', CustomerController::class)->except(['destroy']);
        Route::get('customers/{customer}/ledger', [LedgerController::class, 'show'])->name('customers.ledger');
    });

    /*
    |------------------------------------------------------------------
    | ORDERS & PAYMENTS (admin + accountant)
    |------------------------------------------------------------------
    */
    Route::middleware('role:admin|accountant')->group(function () {
        Route::get('orders/pdf', [OrderController::class, 'downloadPdf'])->name('orders.pdf');
        Route::resource('orders', OrderController::class)->except(['create','store','destroy']);
        Route::post('orders/{order}/confirm',  [OrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order}/stitch',   [OrderController::class, 'markStitching'])->name('orders.stitch');
        // Payments
        Route::resource('orders.payments', PaymentController::class)->only(['store']);
        Route::post('orders/{order}/apply-credit', [PaymentController::class, 'applyCredit'])->name('orders.apply-credit');
    });

    // Order Reductions (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('orders/{order}/reduce',  [OrderReductionController::class, 'create'])->name('orders.reduce');
        Route::post('orders/{order}/reduce', [OrderReductionController::class, 'store'])->name('orders.reduce.store');
    });

    /*
    |------------------------------------------------------------------
    | IN-HOUSE PRODUCTION TRACKING (manager only)
    |------------------------------------------------------------------
    */
    Route::middleware('role:admin|manager')->group(function () {
        // Fabric batch arrivals
        Route::resource('fabric-batches', FabricBatchController::class)->only(['index','create','store','show']);

        // Production route assignments (Naeem Pakki / Stitching Unit)
        Route::resource('production-assignments', ProductionAssignmentController::class)->only(['index','create','store','show']);

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
        Route::post('dispatch/{order}',                [DispatchController::class, 'store'])->name('dispatch.store');

        // Worker wages
        Route::get('wages',          [WagesController::class, 'index'])->name('wages.index');
        Route::get('wages/create',   [WagesController::class, 'create'])->name('wages.create');
        Route::post('wages',         [WagesController::class, 'store'])->name('wages.store');
        Route::post('wages/{wage}/confirm', [WagesController::class, 'confirm'])->name('wages.confirm');
    });

    // Packed Inventory (manager + admin)
    Route::get('packed-inventory', [PressController::class, 'inventory'])->name('packed-inventory.index');

    // Production Tracker (manager + admin)
    Route::get('production-tracker', [ProductionTrackerController::class, 'index'])->name('production.tracker');

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
        Route::get('dispatch-history',      [ReportController::class, 'dispatchHistory'])->name('dispatch-history');
        Route::get('activity-log',          [ReportController::class, 'activityLog'])->name('activity-log');
        Route::get('damage-reductions',     [ReportController::class, 'damageReductions'])->name('damage-reductions');
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
    });

    /*
    |------------------------------------------------------------------
    | USER MANAGEMENT (admin only)
    |------------------------------------------------------------------
    */
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
