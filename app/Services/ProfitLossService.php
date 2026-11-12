<?php

namespace App\Services;

use App\Models\Consumption;
use App\Models\DailySale;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\OperatingExpense;
use App\Models\Wastage;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ProfitLossService
{
    public function __construct(
        protected ConsumableAllocationService $consumableAllocationService
    ) {}

    /**
     * Compute True Profit & Loss Summary for a specific single date.
     */
    public function getDailySummary(string $date): array
    {
        $carbonDate = Carbon::parse($date);
        $daysInMonth = $carbonDate->daysInMonth;

        // 1. Sales
        $sale = DailySale::whereDate('sales_date', $date)->first();
        $totalSales = $sale ? (float) $sale->total_sales : 0.00;
        $cashSales = $sale ? (float) $sale->cash_sales : 0.00;
        $bkashSales = $sale ? (float) $sale->bkash_sales : 0.00;
        $nagadSales = $sale ? (float) $sale->nagad_sales : 0.00;
        $cardBankSales = $sale ? (float) $sale->card_bank_sales : 0.00;
        $otherSales = $sale ? (float) $sale->other_sales : 0.00;
        $discrepancy = $sale ? (float) $sale->discrepancy_amount : 0.00;

        // 2. Ingredient Consumption Cost (Direct usage)
        $consumptionCost = (float) Consumption::whereDate('consumption_date', $date)->sum('total_cost');

        // 3. Consumable Allocated Cost (Multi-day amortized)
        $consumableCost = $this->consumableAllocationService->getAllocatedCostForDate($date);

        // Cost of Goods / Materials
        $costOfMaterials = round($consumptionCost + $consumableCost, 2);

        // Gross Profit
        $grossProfit = round($totalSales - $costOfMaterials, 2);

        // 4. Operating Expenses
        $operatingExpenses = (float) OperatingExpense::whereDate('expense_date', $date)->sum('amount');

        // 5. Wastage Cost
        $wastageCost = (float) Wastage::whereDate('wastage_date', $date)->sum('total_cost');

        // 6. Accrued Staff Salary Cost
        // Active monthly employees: agreed_salary / days_in_month; daily employees: daily agreed_salary
        $activeEmployees = Employee::where('is_active', true)->get();
        $salaryCost = 0.00;
        foreach ($activeEmployees as $emp) {
            // Do not accrue salary prior to employee joining date
            if ($emp->joining_date && $carbonDate->lt(Carbon::parse($emp->joining_date)->startOfDay())) {
                continue;
            }

            if ($emp->salary_type === 'monthly') {
                $salaryCost += ((float) $emp->agreed_salary / $daysInMonth);
            } else {
                // Daily: check if present or marked attendance
                $attended = EmployeeAttendance::where('employee_id', $emp->id)
                    ->whereDate('attendance_date', $date)
                    ->whereIn('status', ['present', 'half_day'])
                    ->first();
                if ($attended) {
                    $multiplier = $attended->status === 'half_day' ? 0.5 : 1.0;
                    $salaryCost += ((float) $emp->agreed_salary * $multiplier);
                }
            }
        }
        $salaryCost = round($salaryCost, 2);

        // Total Operating Costs
        $totalOperatingCost = round($operatingExpenses + $wastageCost + $salaryCost, 2);

        // True Net Profit
        $netProfit = round($grossProfit - $totalOperatingCost, 2);
        $profitMargin = $totalSales > 0 ? round(($netProfit / $totalSales) * 100, 2) : 0.00;

        return [
            'date' => $date,
            'sales' => [
                'total_sales' => $totalSales,
                'cash_sales' => $cashSales,
                'bkash_sales' => $bkashSales,
                'nagad_sales' => $nagadSales,
                'card_bank_sales' => $cardBankSales,
                'other_sales' => $otherSales,
                'discrepancy' => $discrepancy,
            ],
            'cost_of_materials' => [
                'ingredient_consumption' => $consumptionCost,
                'consumables_allocated' => $consumableCost,
                'total' => $costOfMaterials,
            ],
            'gross_profit' => $grossProfit,
            'operating_costs' => [
                'operating_expenses' => $operatingExpenses,
                'wastage_cost' => $wastageCost,
                'salary_cost' => $salaryCost,
                'total' => $totalOperatingCost,
            ],
            'net_profit' => $netProfit,
            'profit_margin' => $profitMargin,
        ];
    }

    /**
     * Compute True Profit & Loss Summary for a date range (monthly/yearly/custom).
     */
    public function getRangeSummary(string $startDate, string $endDate): array
    {
        $period = CarbonPeriod::create($startDate, $endDate);

        $totalSales = (float) DailySale::whereBetween('sales_date', [$startDate, $endDate])->sum('total_sales');
        $consumptionCost = (float) Consumption::whereBetween('consumption_date', [$startDate, $endDate])->sum('total_cost');
        $consumableCost = $this->consumableAllocationService->getAllocatedCostForRange($startDate, $endDate);
        $operatingExpenses = (float) OperatingExpense::whereBetween('expense_date', [$startDate, $endDate])->sum('amount');
        $wastageCost = (float) Wastage::whereBetween('wastage_date', [$startDate, $endDate])->sum('total_cost');

        // Sum daily salary accruals across the range
        $totalSalaryCost = 0.00;
        $activeEmployees = Employee::where('is_active', true)->get();
        foreach ($period as $date) {
            $daysInMonth = $date->daysInMonth;
            $dateStr = $date->toDateString();
            foreach ($activeEmployees as $emp) {
                if ($emp->joining_date && $date->lt(Carbon::parse($emp->joining_date)->startOfDay())) {
                    continue;
                }

                if ($emp->salary_type === 'monthly') {
                    $totalSalaryCost += ((float) $emp->agreed_salary / $daysInMonth);
                } else {
                    $attended = EmployeeAttendance::where('employee_id', $emp->id)
                        ->whereDate('attendance_date', $dateStr)
                        ->whereIn('status', ['present', 'half_day'])
                        ->first();
                    if ($attended) {
                        $multiplier = $attended->status === 'half_day' ? 0.5 : 1.0;
                        $totalSalaryCost += ((float) $emp->agreed_salary * $multiplier);
                    }
                }
            }
        }
        $totalSalaryCost = round($totalSalaryCost, 2);

        $costOfMaterials = round($consumptionCost + $consumableCost, 2);
        $grossProfit = round($totalSales - $costOfMaterials, 2);
        $totalOperatingCost = round($operatingExpenses + $wastageCost + $totalSalaryCost, 2);
        $netProfit = round($grossProfit - $totalOperatingCost, 2);
        $profitMargin = $totalSales > 0 ? round(($netProfit / $totalSales) * 100, 2) : 0.00;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_sales' => $totalSales,
            'ingredient_consumption' => $consumptionCost,
            'consumables_allocated' => $consumableCost,
            'cost_of_materials' => $costOfMaterials,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $operatingExpenses,
            'wastage_cost' => $wastageCost,
            'salary_cost' => $totalSalaryCost,
            'total_operating_cost' => $totalOperatingCost,
            'net_profit' => $netProfit,
            'profit_margin' => $profitMargin,
        ];
    }
}
