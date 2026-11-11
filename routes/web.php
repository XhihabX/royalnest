<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'restaurantName' => config('app.name', 'Royal Nest Café & Restaurant'),
        'version' => '2.0.0',
    ]);
});

