<?php

use Illuminate\Support\Facades\Schedule;

// Re-import any county NJOGIS has republished since our last load.
Schedule::command('piu:check-nj-parcels-freshness')
    ->weeklyOn(1, '04:17')
    ->withoutOverlapping()
    ->onOneServer();
