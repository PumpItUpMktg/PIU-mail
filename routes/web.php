<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Placeholder Inertia page. Relay 02 adds the Target Area map.
Route::get('/', fn () => Inertia::render('Home', ['appName' => config('app.name')]));
