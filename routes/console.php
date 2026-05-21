<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('installments:send-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground();
