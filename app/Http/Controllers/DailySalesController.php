<?php

namespace App\Http\Controllers;

use App\Models\DailySale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailySalesController extends Controller
{
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

        $total = (float)$validated['total_sales'];
        $cash = (float)($validated['cash_sales'] ?? 0);
        $bkash = (float)($validated['bkash_sales'] ?? 0);
        $nagad = (float)($validated['nagad_sales'] ?? 0);
        $card = (float)($validated['card_bank_sales'] ?? 0);
        $other = (float)($validated['other_sales'] ?? 0);

        $breakdownSum = $cash + $bkash + $nagad + $card + $other;
        $discrepancy = round($total - $breakdownSum, 2);

        DailySale::updateOrCreate(
            ['sales_date' => $validated['sales_date']],
            [
                'total_sales' => $total,
                'cash_sales' => $cash,
                'bkash_sales' => $bkash,
                'nagad_sales' => $nagad,
                'card_bank_sales' => $card,
                'other_sales' => $other,
                'discrepancy_amount' => $discrepancy,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id() ?: 1,
            ]
        );

        $msg = $discrepancy == 0 
            ? 'Daily sales entry saved successfully. Payment methods reconciled with ৳0 discrepancy.'
            : "Daily sales saved with discrepancy of ৳{$discrepancy} flagged for manager review.";

        return redirect()->back()->with('success', $msg);
    }
}

