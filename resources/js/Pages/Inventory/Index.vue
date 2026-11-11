<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { Boxes, ArrowDownRight, ArrowUpRight, History, MapPin, AlertCircle } from 'lucide-vue-next';

defineProps({
    locations: Array,
    stockItems: Array,
    recentMovements: Array,
});
</script>

<template>
    <AppLayout title="Stock & Inventory Ledger">
        <Head title="Stock Ledger — Royal Nest ERP" />

        <div class="space-y-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <Boxes class="w-7 h-7 text-emerald-400" />
                        <span>Perpetual Stock & Inventory Ledger</span>
                    </h1>
                    <p class="text-sm text-slate-400 mt-1">
                        Track raw ingredients and bar supplies across Main Store, Kitchen, and Bar with moving weighted-average costs.
                    </p>
                </div>
            </div>

            <!-- Locations Badges -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div
                    v-for="loc in locations"
                    :key="loc.id"
                    class="bg-slate-900 border border-slate-800 rounded-2xl p-4 flex items-center gap-3"
                >
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center shrink-0">
                        <MapPin class="w-5 h-5" />
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-white">{{ loc.name }}</h4>
                        <span class="text-xs text-slate-400">{{ loc.code }} • {{ loc.description }}</span>
                    </div>
                </div>
            </div>

            <!-- Current Stock Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
                <h3 class="text-lg font-bold text-white">Current On-Hand Inventory</h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 uppercase tracking-wider border-b border-slate-800 text-[11px]">
                            <tr>
                                <th class="py-3 pr-4">Code / Item Name</th>
                                <th class="py-3 px-4">Category</th>
                                <th class="py-3 px-4 text-right">Weighted Cost</th>
                                <th class="py-3 px-4 text-right">Total On-Hand</th>
                                <th class="py-3 px-4 text-center">Unit</th>
                                <th class="py-3 px-4 text-right">Total Valuation</th>
                                <th class="py-3 pl-4">Location Breakdown</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr
                                v-for="item in stockItems"
                                :key="item.id"
                                class="hover:bg-slate-800/30"
                            >
                                <td class="py-3.5 pr-4">
                                    <span class="font-bold text-white block">{{ item.name }}</span>
                                    <span class="text-[11px] font-mono text-slate-400">{{ item.code }}</span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-300">
                                    {{ item.category }}
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono text-slate-200">
                                    ৳{{ Number(item.cost_price).toFixed(2) }}
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-white">
                                    {{ Number(item.current_stock).toFixed(2) }}
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono text-slate-400">
                                    {{ item.stock_unit ? item.stock_unit.symbol : '' }}
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-400">
                                    ৳{{ (Number(item.current_stock) * Number(item.cost_price)).toFixed(2) }}
                                </td>
                                <td class="py-3.5 pl-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span
                                            v-for="lq in item.location_quantities"
                                            :key="lq.id"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] bg-slate-800 text-slate-300 font-mono"
                                        >
                                            {{ lq.stock_location ? lq.stock_location.name.split(' ')[0] : 'Loc' }}: {{ Number(lq.quantity).toFixed(1) }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Movement Audit History -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <History class="w-5 h-5 text-emerald-400" />
                        <span>Recent Stock Movement Ledger</span>
                    </h3>
                    <span class="text-xs text-slate-400">Last 20 transactions</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 uppercase tracking-wider border-b border-slate-800 text-[11px]">
                            <tr>
                                <th class="py-2.5 pr-4">Timestamp</th>
                                <th class="py-2.5 px-4">Type</th>
                                <th class="py-2.5 px-4">Item</th>
                                <th class="py-2.5 px-4">Location</th>
                                <th class="py-2.5 px-4 text-right">Quantity</th>
                                <th class="py-2.5 px-4 text-right">Cost Rate</th>
                                <th class="py-2.5 pl-4">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/40">
                            <tr
                                v-for="m in recentMovements"
                                :key="m.id"
                                class="hover:bg-slate-800/20"
                            >
                                <td class="py-2.5 pr-4 text-slate-400 font-mono text-[11px]">
                                    {{ new Date(m.created_at).toLocaleString() }}
                                </td>
                                <td class="py-2.5 px-4">
                                    <span :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider',
                                        Number(m.quantity) > 0 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'
                                    ]">
                                        {{ m.movement_type.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 font-medium text-white">
                                    {{ m.stock_item ? m.stock_item.name : 'Unknown' }}
                                </td>
                                <td class="py-2.5 px-4 text-slate-300">
                                    {{ m.stock_location ? m.stock_location.name : 'Location' }}
                                </td>
                                <td :class="[
                                    'py-2.5 px-4 text-right font-mono font-bold',
                                    Number(m.quantity) > 0 ? 'text-emerald-400' : 'text-rose-400'
                                ]">
                                    {{ Number(m.quantity) > 0 ? '+' : '' }}{{ Number(m.quantity).toFixed(2) }} {{ m.unit ? m.unit.symbol : '' }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono text-slate-300">
                                    ৳{{ Number(m.unit_cost).toFixed(2) }}
                                </td>
                                <td class="py-2.5 pl-4 text-slate-400 text-xs">
                                    {{ m.notes || '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
