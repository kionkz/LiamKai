<?php

use App\Console\Commands\ExpireStock;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run every midnight to mark expired stock batches as losses
Schedule::command(ExpireStock::class)->dailyAt('00:01');
