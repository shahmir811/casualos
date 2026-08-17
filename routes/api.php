<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogueController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PushTokenController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/verify', [AuthController::class, 'verify'])->name('api.auth.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    Route::get('/orders', [OrderController::class, 'index'])->name('api.orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('api.orders.show');
    Route::post('/orders', [OrderController::class, 'store'])->name('api.orders.store');

    Route::get('/ledger', [LedgerController::class, 'index'])->name('api.ledger.index');

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('api.announcements.index');
    Route::post('/announcements/{id}/read', [AnnouncementController::class, 'markRead'])->name('api.announcements.read');

    Route::get('/catalogues', [CatalogueController::class, 'index'])->name('api.catalogues.index');
    Route::get('/catalogues/{catalogue}', [CatalogueController::class, 'show'])->name('api.catalogues.show');
    Route::post('/catalogues/{catalogue}/quote', [CatalogueController::class, 'quote'])->name('api.catalogues.quote');

    Route::post('/push-tokens', [PushTokenController::class, 'store'])->name('api.push-tokens.store');
    Route::delete('/push-tokens', [PushTokenController::class, 'destroy'])->name('api.push-tokens.destroy');
});
