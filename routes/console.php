<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-batal order website yang belum dibayar setelah 24 jam.
Schedule::command('orders:cancel-unpaid')->hourly()->withoutOverlapping()->onOneServer();

// Backup database setiap hari jam 01:30.
Schedule::command('backup:run')->dailyAt('01:30');

// Hapus backup lama (lebih dari 7 hari) setiap hari jam 01:00.
Schedule::command('backup:clean')->dailyAt('01:00');
