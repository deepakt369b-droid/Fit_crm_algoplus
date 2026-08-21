<?php

use App\Http\Controllers\InvoiceDocumentController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

/**
 * Shallow health check for Coolify's zero-downtime deploy monitor.
 *
 * Deliberately does not touch the database — a slow/degraded DB
 * shouldn't cause Coolify to kill an otherwise-healthy container.
 * The deep check (including DB) is Laravel's own /up route.
 */
Route::get('/healthz', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
    ]);
});

Route::middleware([Authenticate::class])
    ->group(function (): void {
        Route::get('/invoices/{invoice}/preview', [InvoiceDocumentController::class, 'preview'])
            ->name('invoices.preview');

        Route::get('/invoices/{invoice}/download', [InvoiceDocumentController::class, 'download'])
            ->name('invoices.download');
    });
