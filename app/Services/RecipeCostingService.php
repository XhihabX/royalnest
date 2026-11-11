<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\RecipeVersion;
use InvalidArgumentException;

class RecipeCostingService
{
    public function __construct(
        protected UnitConversionService $unitConverter
    ) {}

    /**
     * Calculate and update the theoretical cost for a given recipe version.
     *
     * @param array<int> $visitedRecipeIds Tracks recursion to prevent circular dependencies.
     */
    public function calculateVersionCost(RecipeVersion $version, array $visitedRecipeIds = []): float
    {
        $recipe = $version->recipe;

        if (in_array($recipe->id, $visitedRecipeIds, true)) {
            throw new InvalidArgumentException("Circular sub-recipe dependency detected on Recipe ID #{$recipe->id} ({$recipe->name}).");
        }

        $visitedRecipeIds[] = $recipe->id;
        $totalBatchCost = 0.0;

        foreach ($version->lines as $line) {
            $lineCost = $this->calculateLineCost($line, $visitedRecipeIds);
            $line->update(['calculated_cost' => $lineCost]);
            $totalBatchCost += $lineCost;
        }

        $yieldQty = (float) $recipe->batch_yield_quantity > 0 ? (float) $recipe->batch_yield_quantity : 1.0;
        $costPerUnit = round($totalBatchCost / $yieldQty, 4);

        // Calculate food cost % if attached to a menu item
        $foodCostPercent = 0.00;
        $menuItem = $recipe->menuItem;
        if ($menuItem && (float) $menuItem->selling_price > 0) {
            $netSellingPrice = (float) $menuItem->selling_price;
            // If inclusive VAT, strip VAT from selling price to calculate true net margin
            if ($menuItem->vat_mode === 'inclusive' && (float) $menuItem->vat_rate > 0) {
                $netSellingPrice = $netSellingPrice / (1 + ((float) $menuItem->vat_rate / 100));
            }
            if ($netSellingPrice > 0) {
                $foodCostPercent = round(($costPerUnit / $netSellingPrice) * 100, 2);
            }
        }

        $version->update([
            'calculated_cost_price' => $costPerUnit,
            'food_cost_percentage' => $foodCostPercent,
        ]);

        return $costPerUnit;
    }

    /**
     * Calculate cost for a single recipe line.
     */
    protected function calculateLineCost(RecipeLine $line, array $visitedRecipeIds): float
    {
        // 1. Raw stock item ingredient
        if ($line->stock_item_id && $line->stockItem) {
            $stockItem = $line->stockItem;
            $unitCost = (float) $stockItem->cost_price;

            // Convert line unit to stock item's stock_unit
            $convertedQty = $this->unitConverter->convert(
                (float) $line->quantity,
                $line->unit_id,
                $stockItem->stock_unit_id
            );

            // Factor in trim waste (e.g., 10% trim waste means we need 1 / (1 - 0.10) or qty * (1 + waste%))
            $wasteFactor = 1 + ((float) $line->waste_percent / 100);
            $usableYield = (float) $line->yield_percent > 0 ? ((float) $line->yield_percent / 100) : 1.0;

            $effectiveQty = ($convertedQty * $wasteFactor) / $usableYield;
            return round($effectiveQty * $unitCost, 4);
        }

        // 2. Sub-recipe preparation
        if ($line->sub_recipe_id && $line->subRecipe) {
            $subRecipe = $line->subRecipe;
            $activeSubVersion = $subRecipe->activeVersion;

            if (! $activeSubVersion) {
                return 0.0;
            }

            $subUnitCost = $this->calculateVersionCost($activeSubVersion, $visitedRecipeIds);

            // Convert line quantity unit to sub-recipe yield unit
            $convertedSubQty = $this->unitConverter->convert(
                (float) $line->quantity,
                $line->unit_id,
                $subRecipe->yield_unit_id
            );

            return round($convertedSubQty * $subUnitCost, 4);
        }

        return 0.0;
    }

    /**
     * Calculate suggested selling price based on a target food cost percentage.
     */
    public function getSuggestedPrice(float $costPrice, float $targetFoodCostPercent = 30.0, float $vatRate = 15.0): array
    {
        if ($targetFoodCostPercent <= 0) {
            $targetFoodCostPercent = 30.0;
        }

        $netSuggestedPrice = round($costPrice / ($targetFoodCostPercent / 100), 2);
        $grossInclusivePrice = round($netSuggestedPrice * (1 + ($vatRate / 100)), 2);
        $grossMarginPercent = round((( $netSuggestedPrice - $costPrice ) / $netSuggestedPrice) * 100, 2);

        return [
            'theoretical_cost' => $costPrice,
            'target_food_cost_percent' => $targetFoodCostPercent,
            'net_suggested_price' => $netSuggestedPrice,
            'gross_suggested_price_vat_inclusive' => $grossInclusivePrice,
            'gross_margin_percent' => $grossMarginPercent,
        ];
    }
}
