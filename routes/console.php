<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Scheduler.
 *
 * When tenancy is enabled, run maintenance across all branches.
 * Otherwise, run the single-tenant commands.
 */
if ((bool) config('fitcrm-tenancy.enabled', false)) {
    // Mark subscriptions expired every day at 00:00
    Schedule::command('fitcrm:tenants:subscriptions')
        ->name('fitcrm-tenants-subscriptions')
        ->withoutOverlapping(30)
        ->onOneServer()
        ->dailyAt('00:00');

    // Mark invoices overdue every day at 00:00
    Schedule::command('fitcrm:tenants:invoices --mark-overdue')
        ->name('fitcrm-tenants-invoices-overdue')
        ->withoutOverlapping(30)
        ->onOneServer()
        ->dailyAt('00:00');
} else {
    // Mark subscriptions expired every day at 00:00
    Schedule::command('fitcrm:subscriptions')
        ->name('fitcrm-subscriptions')
        ->withoutOverlapping(30)
        ->onOneServer()
        ->dailyAt('00:00');

    // Mark invoices overdue every day at 00:00
    Schedule::command('fitcrm:invoices --mark-overdue')
        ->name('fitcrm-invoices-overdue')
        ->withoutOverlapping(30)
        ->onOneServer()
        ->dailyAt('00:00');
}
