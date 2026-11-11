<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'restaurantName' => config('app.name', 'Royal Nest Café & Restaurant'),
        'version' => '2.0.0',
    ]);
});

Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes.index');
Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
