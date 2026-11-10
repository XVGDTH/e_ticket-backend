<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billets:rappels')->everyFiveMinutes();

// routes de validation de l'algorithme de preferences
Schedule::command('marketing:preferences')->dailyAt('03:00');
Schedule::command('marketing:envoyer')->everyMinute()->withoutOverlapping(10);
