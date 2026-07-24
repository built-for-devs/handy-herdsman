<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// M5 #231 — daily low-stock sweep so Jeff gets reorder alerts before running
// short mid-season (§5.6b).
Schedule::command('supplies:check-stock')->dailyAt('07:00');
