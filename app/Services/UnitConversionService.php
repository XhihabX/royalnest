<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\UnitConversion;
use InvalidArgumentException;

class UnitConversionService
{
    /**
     * Convert a quantity from one unit to another.
     */
    public function convert(float|string $quantity, Unit|int $fromUnit, Unit|int $toUnit): float
    {
        $qty = (float) $quantity;
        $fromId = $fromUnit instanceof Unit ? $fromUnit->id : (int) $fromUnit;
        $toId = $toUnit instanceof Unit ? $toUnit->id : (int) $toUnit;

        if ($fromId === $toId) {
            return $qty;
        }

        // 1. Direct forward conversion (e.g. kg -> g)
        $direct = UnitConversion::where('from_unit_id', $fromId)
            ->where('to_unit_id', $toId)
            ->first();

        if ($direct) {
            return round($qty * (float) $direct->multiplier, 6);
        }

        // 2. Inverse backward conversion (e.g. g -> kg using kg -> g multiplier)
        $inverse = UnitConversion::where('from_unit_id', $toId)
            ->where('to_unit_id', $fromId)
            ->first();

        if ($inverse && (float) $inverse->multiplier > 0) {
            return round($qty / (float) $inverse->multiplier, 6);
        }

        // 3. Indirect multi-hop conversion via common base unit (e.g. ctn -> pcs -> g)
        // Check if there is an intermediate unit
        $fromConversions = UnitConversion::where('from_unit_id', $fromId)->get();
        foreach ($fromConversions as $fConv) {
            $intermediateToTarget = UnitConversion::where('from_unit_id', $fConv->to_unit_id)
                ->where('to_unit_id', $toId)
                ->first();
            if ($intermediateToTarget) {
                $totalMultiplier = (float) $fConv->multiplier * (float) $intermediateToTarget->multiplier;
                return round($qty * $totalMultiplier, 6);
            }
        }

        $fromName = $fromUnit instanceof Unit ? $fromUnit->name : "Unit #{$fromId}";
        $toName = $toUnit instanceof Unit ? $toUnit->name : "Unit #{$toId}";

        throw new InvalidArgumentException("No unit conversion path exists between {$fromName} and {$toName}.");
    }
}
