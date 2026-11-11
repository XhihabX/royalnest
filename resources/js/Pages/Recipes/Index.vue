<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { ChefHat, ArrowRight, Scale, TrendingUp, AlertTriangle } from 'lucide-vue-next';

defineProps({
    recipes: Array,
});
</script>

<template>
    <AppLayout title="Recipe BOM & Food Costing">
        <Head title="Recipe BOM & Food Costing — Royal Nest ERP" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <ChefHat class="w-7 h-7 text-emerald-400" />
                        <span>Recipe Bill of Materials (BOM) & Food Costing</span>
                    </h1>
                    <p class="text-sm text-slate-400 mt-1">
                        Theoretical food cost breakdowns, ingredient yields, trim loss allowances, and margin analytics.
                    </p>
                </div>
            </div>

            <!-- Recipes List -->
            <div class="space-y-6">
                <div
                    v-for="recipe in recipes"
                    :key="recipe.id"
                    class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-6"
                >
                    <!-- Recipe Header -->
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="px-2 py-0.5 rounded text-xs font-semibold uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    {{ recipe.recipe_type === 'menu_item' ? 'Menu Dish' : 'Sub-Recipe' }}
                                </span>
                                <span v-if="recipe.active_version" class="px-2 py-0.5 rounded text-xs font-semibold bg-slate-800 text-slate-400">
                                    Version {{ recipe.active_version.version_no }}
                                </span>
                            </div>
                            <h2 class="text-xl font-bold text-white">{{ recipe.name }}</h2>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Yield: {{ Number(recipe.batch_yield_quantity).toFixed(0) }} {{ recipe.yield_unit ? recipe.yield_unit.name : 'portion' }}
                            </p>
                        </div>

                        <!-- Cost Summary Cards -->
                        <div v-if="recipe.active_version" class="flex items-center gap-4">
                            <div class="bg-slate-800/80 px-4 py-2.5 rounded-xl border border-slate-700/60 text-right">
                                <span class="text-[11px] text-slate-400 block font-medium">Theoretical Cost:</span>
                                <span class="text-lg font-black text-white">৳{{ Number(recipe.active_version.calculated_cost_price).toFixed(2) }}</span>
                            </div>

                            <div v-if="recipe.menu_item" class="bg-slate-800/80 px-4 py-2.5 rounded-xl border border-slate-700/60 text-right">
                                <span class="text-[11px] text-slate-400 block font-medium">Food Cost %:</span>
                                <span :class="[
                                    'text-lg font-black',
                                    recipe.active_version.food_cost_percentage <= 35 ? 'text-emerald-400' : 'text-amber-400'
                                ]">
                                    {{ recipe.active_version.food_cost_percentage }}%
                                </span>
                            </div>

                            <div v-if="recipe.menu_item" class="bg-slate-800/80 px-4 py-2.5 rounded-xl border border-slate-700/60 text-right">
                                <span class="text-[11px] text-slate-400 block font-medium">Gross Margin:</span>
                                <span class="text-lg font-black text-emerald-400">
                                    {{ (100 - recipe.active_version.food_cost_percentage).toFixed(1) }}%
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Ingredients Table -->
                    <div v-if="recipe.active_version && recipe.active_version.lines && recipe.active_version.lines.length" class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-slate-400 uppercase tracking-wider border-b border-slate-800 text-[11px]">
                                <tr>
                                    <th class="py-2.5 pr-4">Ingredient / Raw Material</th>
                                    <th class="py-2.5 px-4 text-right">Quantity</th>
                                    <th class="py-2.5 px-4 text-center">Unit</th>
                                    <th class="py-2.5 px-4 text-right">Trim/Waste %</th>
                                    <th class="py-2.5 px-4 text-right">Inventory Rate</th>
                                    <th class="py-2.5 pl-4 text-right">Calculated Cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/50">
                                <tr
                                    v-for="line in recipe.active_version.lines"
                                    :key="line.id"
                                    class="hover:bg-slate-800/20"
                                >
                                    <td class="py-3 pr-4 font-medium text-white">
                                        <span v-if="line.stock_item">{{ line.stock_item.name }}</span>
                                        <span v-else-if="line.sub_recipe" class="text-purple-400 flex items-center gap-1">
                                            <span>Sub-Recipe:</span> {{ line.sub_recipe.name }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono text-slate-200">
                                        {{ Number(line.quantity).toFixed(2) }}
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono text-slate-400">
                                        {{ line.unit ? line.unit.symbol : '' }}
                                    </td>
                                    <td class="py-3 px-4 text-right text-slate-400 font-mono">
                                        {{ Number(line.waste_percent).toFixed(1) }}%
                                    </td>
                                    <td class="py-3 px-4 text-right text-slate-400 font-mono">
                                        ৳{{ line.stock_item ? Number(line.stock_item.cost_price).toFixed(2) : '-' }} / {{ line.stock_item && line.stock_item.stock_unit ? line.stock_item.stock_unit.symbol : '' }}
                                    </td>
                                    <td class="py-3 pl-4 text-right font-bold text-white font-mono">
                                        ৳{{ Number(line.calculated_cost).toFixed(2) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
