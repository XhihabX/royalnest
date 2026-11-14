<?php

namespace App\Http\Controllers;

use App\Models\DailySale;
use App\Services\DailySalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailySalesController extends Controller
{
    public function __construct(
        protected DailySalesService $dailySalesService
    ) {}

    public function index(): Response
    {
        $sales = DailySale::with('creator')
            ->orderBy('sales_date', 'desc')
            ->limit(60)
            ->get();

        return Inertia::render('Sales/Daily', [
            'sales' => $sales,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sales_date' => 'required|date',
            'total_sales' => 'required|numeric|min:0',
            'cash_sales' => 'nullable|numeric|min:0',
            'bkash_sales' => 'nullable|numeric|min:0',
            'nagad_sales' => 'nullable|numeric|min:0',
            'card_bank_sales' => 'nullable|numeric|min:0',
            'other_sales' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $result = $this->dailySalesService->recordDailySale(
            data: array_merge($validated, ['source' => 'manual_entry']),
            userId: auth()->id() ?: 1
        );

        return redirect()->back()->with('success', $result['message']);
    }
}
