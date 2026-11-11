<?php

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = MenuCategory::with(['menuItems.recipe.activeVersion'])
            ->orderBy('display_order')
            ->get();

        $menuItems = MenuItem::with(['category', 'variants', 'recipe.activeVersion'])
            ->when($request->category, fn ($q, $cat) => $q->where('menu_category_id', $cat))
            ->orderBy('name')
            ->get();

        return Inertia::render('Menu/Index', [
            'categories' => $categories,
            'menuItems' => $menuItems,
            'filters' => $request->only('category'),
        ]);
    }
}
