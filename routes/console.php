<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Revert clients idle past the threshold to `inactive` (spec §10b).
Schedule::command('clients:mark-idle-inactive')->dailyAt('02:00');
