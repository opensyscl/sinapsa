<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purga registros de API de más de 30 días
Schedule::command('model:prune', ['--model' => [App\Models\ApiRequestLog::class]])->daily();
