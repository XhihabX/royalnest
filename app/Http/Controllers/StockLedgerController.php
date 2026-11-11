<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockLedgerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = StockLedger::with(['product', 'user', 'stockBatch']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->input('movement_type'));
        }

        if ($request->filled('from_date')) {
            $query->where('transaction_date', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->where('transaction_date', '<=', $request->input('to_date'));
        }

        $ledgers = $query->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(50)
            ->withQueryString();

        $products = Product::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Ledger/Index', [
            'ledgers' => $ledgers,
            'products' => $products,
            'filters' => $request->only(['product_id', 'movement_type', 'from_date', 'to_date']),
        ]);
    }
}

