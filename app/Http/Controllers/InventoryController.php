<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(): Response
    {
        $locations = StockLocation::where('is_active', true)->get();

        $stockItems = StockItem::with([
            'stockUnit',
            'purchaseUnit',
            'recipeUnit',
            'locationQuantities.stockLocation',
        ])
        ->orderBy('name')
        ->get();

        $recentMovements = StockMovement::with([
            'stockItem',
            'stockLocation',
            'unit',
            'creator',
        ])
        ->latest('id')
        ->take(20)
        ->get();

        return Inertia::render('Inventory/Index', [
            'locations' => $locations,
            'stockItems' => $stockItems,
            'recentMovements' => $recentMovements,
        ]);
    }
}
