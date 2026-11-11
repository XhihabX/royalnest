<?php

namespace App\Services;

use App\Models\ConsumableAllocation;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumableAllocationService
{
    /**
     * Set up a multi-day consumable cost allocation.
     * Example: ৳120 tissue box over 4 days = ৳30/day.
     */
    public function createAllocation(
        int $productId,
        ?int $purchaseId,
        string $startDate,
        int $durationDays,
        float $totalCost,
        ?string $notes = null
    ): ConsumableAllocation {
        if ($durationDays <= 0) {
            throw ValidationException::withMessages([
                'duration_days' => 'Duration days must be at least 1 day.'
            ]);
        }

        $product = Product::findOrFail($productId);
        $dailyAllocatedCost = round($totalCost / $durationDays, 2);
        $endDate = Carbon::parse($startDate)->addDays($durationDays - 1)->toDateString();

        return ConsumableAllocation::create([
            'product_id' => $product->id,
            'purchase_id' => $purchaseId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_cost' => round($totalCost, 2),
            'duration_days' => $durationDays,
            'daily_allocated_cost' => $dailyAllocatedCost,
            'status' => 'active',
            'notes' => $notes,
        ]);
    }

    /**
     * Calculate allocated consumable cost recognized on a specific date.
     */
    public function getAllocatedCostForDate(string $date): float
    {
        return (float) ConsumableAllocation::where('status', 'active')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->sum('daily_allocated_cost');
    }

    /**
     * Calculate total allocated consumable cost across a date range.
     */
    public function getAllocatedCostForRange(string $startDate, string $endDate): float
    {
        $period = CarbonPeriod::create($startDate, $endDate);
        $total = 0.00;

        foreach ($period as $date) {
            $total += $this->getAllocatedCostForDate($date->toDateString());
        }

        return round($total, 2);
    }
}

