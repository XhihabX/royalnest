<?php

namespace App\Http\Controllers;

use App\Services\ProfitLossService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ProfitLossService $profitLossService
    ) {}

    public function profitLoss(Request $request): Response
    {
        $mode = $request->input('mode', 'daily'); // 'daily' or 'range'
        $date = $request->input('date', now()->toDateString());
        $startDate = $request->input('start_date', Carbon::parse($date)->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::parse($date)->endOfMonth()->toDateString());

        if ($mode === 'range') {
            $data = $this->profitLossService->getRangeSummary($startDate, $endDate);
        } else {
            $data = $this->profitLossService->getDailySummary($date);
        }

        return Inertia::render('Reports/ProfitLoss', [
            'mode' => $mode,
            'selectedDate' => $date,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'summary' => $data,
        ]);
    }
}

