<?php

use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\InvoiceDocumentController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

// Controller-based (not a closure): php artisan route:cache hard-fails on
// any closure route, and the deploy script (scripts/coolify-deploy.sh)
// runs route:cache.
Route::get('/healthz', HealthCheckController::class);

Route::middleware([Authenticate::class])
    ->group(function (): void {
        Route::get('/invoices/{invoice}/preview', [InvoiceDocumentController::class, 'preview'])
            ->name('invoices.preview');

        Route::get('/invoices/{invoice}/download', [InvoiceDocumentController::class, 'download'])
            ->name('invoices.download');
    });
