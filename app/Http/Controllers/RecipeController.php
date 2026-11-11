<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function index(): Response
    {
        $recipes = Recipe::with([
            'menuItem',
            'yieldUnit',
            'activeVersion.lines.stockItem.stockUnit',
            'activeVersion.lines.unit',
            'activeVersion.lines.subRecipe',
        ])
        ->get();

        return Inertia::render('Recipes/Index', [
            'recipes' => $recipes,
        ]);
    }
}
