<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DevicesController;
use App\Http\Controllers\Api\V1\EnquiriesController;
use App\Http\Controllers\Api\V1\EnquiryFollowUpsController;
use App\Http\Controllers\Api\V1\ExpensesController;
use App\Http\Controllers\Api\V1\FollowUpsController;
use App\Http\Controllers\Api\V1\InvoicesController;
use App\Http\Controllers\Api\V1\InvoiceTransactionsController;
use App\Http\Controllers\Api\V1\MembersController;
use App\Http\Controllers\Api\V1\PermissionsController;
use App\Http\Controllers\Api\V1\PlansController;
use App\Http\Controllers\Api\V1\RolesController;
use App\Http\Controllers\Api\V1\ServicesController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SubscriptionsController;
use App\Http\Controllers\Api\V1\UsersController;
use App\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

// Laravel's default scaffolding route. Kept at the same path for anyone
// depending on it, but converted from a closure to a controller method:
// php artisan route:cache hard-fails on any closure-based route ("Unable
// to prepare route ... for serialization. Uses Closure."), and the
// deploy script (scripts/coolify-deploy.sh) runs route:cache.
Route::get('/user', [AuthController::class, 'me'])->middleware('auth:sanctum');

// Meta calls these directly — no bearer token, authenticity comes from
// the X-Hub-Signature-256 check inside the controller instead. Two
// explicit routes (not Route::match with a closure) because closure
// routes can't be cached by `route:cache`, which the deploy script runs.
Route::get('/webhooks/whatsapp', [WhatsappWebhookController::class, 'verify'])
    ->middleware('throttle:whatsapp-webhook');
Route::post('/webhooks/whatsapp', [WhatsappWebhookController::class, 'handle'])
    ->middleware('throttle:whatsapp-webhook');

Route::prefix('v1')
    ->group(function (): void {
        Route::post('/auth/login', [AuthController::class, 'login'])
            ->middleware('throttle:api-login');

        Route::post('/devices/pair', [DevicesController::class, 'pair'])
            ->middleware('throttle:device-pairing');

        // Gate hardware: authenticates as a Device (not a User), scoped to
        // the 'attendance:write' Sanctum ability only.
        Route::middleware(['auth:sanctum', CheckAbilities::class.':attendance:write', 'throttle:attendance'])
            ->group(function (): void {
                Route::post('/devices/heartbeat', [DevicesController::class, 'heartbeat']);
                Route::post('/devices/enrol', [DevicesController::class, 'enrol']);
                Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
                Route::post('/attendance/sync', [AttendanceController::class, 'sync']);
            });

        Route::middleware('auth:sanctum')
            ->group(function (): void {
                Route::get('/me', [AuthController::class, 'me']);
                Route::post('/auth/logout', [AuthController::class, 'logout']);

                Route::get('/settings', [SettingsController::class, 'show']);
                Route::put('/settings', [SettingsController::class, 'update']);

                Route::prefix('analytics')->group(function (): void {
                    Route::get('/financial', [AnalyticsController::class, 'financial']);
                    Route::get('/membership', [AnalyticsController::class, 'membership']);
                    Route::get('/cashflow-trend', [AnalyticsController::class, 'cashflowTrend']);
                    Route::get('/expense-categories', [AnalyticsController::class, 'expenseCategories']);
                    Route::get('/top-plans', [AnalyticsController::class, 'topPlans']);
                    Route::get('/recent-transactions', [AnalyticsController::class, 'recentTransactions']);
                });

                Route::get('/roles', [RolesController::class, 'index']);
                Route::get('/permissions', [PermissionsController::class, 'index']);

                Route::apiResource('users', UsersController::class);
                Route::post('/users/{user}/restore', [UsersController::class, 'restore']);
                Route::delete('/users/{user}/force', [UsersController::class, 'forceDelete']);

                Route::apiResource('members', MembersController::class);
                Route::post('/members/{member}/restore', [MembersController::class, 'restore']);
                Route::delete('/members/{member}/force', [MembersController::class, 'forceDelete']);

                Route::apiResource('services', ServicesController::class);
                Route::post('/services/{service}/restore', [ServicesController::class, 'restore']);
                Route::delete('/services/{service}/force', [ServicesController::class, 'forceDelete']);

                Route::apiResource('plans', PlansController::class);
                Route::post('/plans/{plan}/restore', [PlansController::class, 'restore']);
                Route::delete('/plans/{plan}/force', [PlansController::class, 'forceDelete']);

                Route::apiResource('subscriptions', SubscriptionsController::class);
                Route::post('/subscriptions/{subscription}/restore', [SubscriptionsController::class, 'restore']);
                Route::delete('/subscriptions/{subscription}/force', [SubscriptionsController::class, 'forceDelete']);
                Route::post('/subscriptions/{subscription}/renew', [SubscriptionsController::class, 'renew']);

                Route::apiResource('invoices', InvoicesController::class);
                Route::post('/invoices/{invoice}/restore', [InvoicesController::class, 'restore']);
                Route::delete('/invoices/{invoice}/force', [InvoicesController::class, 'forceDelete']);
                Route::get('/invoices/{invoice}/pdf', [InvoicesController::class, 'pdf']);
                Route::get('/invoices/{invoice}/pdf/download', [InvoicesController::class, 'downloadPdf']);

                Route::get('/invoices/{invoice}/transactions', [InvoiceTransactionsController::class, 'index']);
                Route::post('/invoices/{invoice}/transactions', [InvoiceTransactionsController::class, 'store']);
                Route::delete('/invoices/{invoice}/transactions/{transaction}', [InvoiceTransactionsController::class, 'destroy']);

                Route::apiResource('expenses', ExpensesController::class);

                Route::apiResource('enquiries', EnquiriesController::class);
                Route::post('/enquiries/{enquiry}/restore', [EnquiriesController::class, 'restore']);
                Route::delete('/enquiries/{enquiry}/force', [EnquiriesController::class, 'forceDelete']);

                Route::get('/enquiries/{enquiry}/follow-ups', [EnquiryFollowUpsController::class, 'index']);
                Route::post('/enquiries/{enquiry}/follow-ups', [EnquiryFollowUpsController::class, 'store']);

                Route::apiResource('follow-ups', FollowUpsController::class)
                    ->parameters(['follow-ups' => 'followUp']);
                Route::post('/follow-ups/{followUp}/restore', [FollowUpsController::class, 'restore']);
                Route::delete('/follow-ups/{followUp}/force', [FollowUpsController::class, 'forceDelete']);

                Route::get('/attendance', [AttendanceController::class, 'index']);
            });
    });
