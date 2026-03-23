<?php

use App\Jobs\CheckSlaJob;
use App\Jobs\ProcessTimeBasedAutomationsJob;
use App\Jobs\SendScheduledReportJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new CheckSlaJob)->everyFifteenMinutes();
Schedule::job(new ProcessTimeBasedAutomationsJob)->everyFifteenMinutes();
Schedule::job(new SendScheduledReportJob)->everyMinute();
