<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Revert clients idle past the threshold to `inactive` (spec §10b).
Schedule::command('clients:mark-idle-inactive')->dailyAt('02:00');

// M5 #231 — daily low-stock sweep so Jeff gets reorder alerts before running
// short mid-season (§5.6b).
Schedule::command('supplies:check-stock')->dailyAt('07:00');

// M9 #244 — the reminder scheduler/queue backbone. Runs hourly; finds due
// reminders, defers non-urgent ones inside quiet hours, and queues send jobs
// (idempotent, so re-runs never double-send) (§5.7).
Schedule::command('reminders:dispatch')->hourly();

// M9 #247 — the daily data-driven nurture sweep: due-date-passed care checks,
// storage renewals, unused straws, dormant clients (§5.7).
Schedule::command('reminders:nurture-sweep')->dailyAt('08:00');
