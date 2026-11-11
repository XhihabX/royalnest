<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    TrendingUp, 
    TrendingDown, 
    ShoppingBag, 
    Flame, 
    Receipt, 
    AlertTriangle, 
    Wallet, 
    Clock, 
    ArrowRight,
    ArrowUpRight,
    ArrowDownRight,
    PlusCircle
} from 'lucide-vue-next';

const props = defineProps({
    selectedDate: String,
    summary: Object,
    lowStockProducts: Array,
    accounts: Array,
    recentMovements: Array,
});

const currentDate = ref(props.selectedDate);

const onDateChange = () => {
    router.get('/', { date: currentDate.value }, { preserveState: true });
};
</script>

<template>
    <AppLayout title="Dashboard">
        <!-- Date Filter & Quick Actions Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Executive Operations & Profit Dashboard</h1>
                <p class="text-xs text-slate-400 mt-1">
                    Live perpetual inventory valuation, actual ingredient usage, and net operating profit for Royal Nest.
                </p>
            </div>

            <div class="flex items-center space-x-3">
                <div class="flex items-center bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5">
                    <span class="text-xs text-slate-400 mr-2 font-medium">Business Date:</span>
                    <input 
                        type="date" 
                        v-model="currentDate" 
                        @change="onDateChange"
                        class="bg-transparent text-sm text-amber-400 font-mono focus:outline-none cursor-pointer"
                    />
                </div>

                <Link 
                    href="/sales" 
                    class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2 rounded-xl text-xs flex items-center space-x-1.5 shadow-lg shadow-amber-500/10 transition"
                >
                    <PlusCircle class="w-4 h-4" />
                    <span>Enter Daily Sales</span>
                </Link>
            </div>
        </div>

        <!-- 4 Primary KPI Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Total Sales Card -->
            <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5 relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Gross Daily Sales</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <ShoppingBag class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-2xl font-black text-white font-mono">
                    ৳{{ Number(summary.sales.total_sales).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </div>
                <div class="mt-3 text-xs text-slate-400 flex items-center justify-between pt-2 border-t border-slate-800/60">
                    <span>Cash: ৳{{ summary.sales.cash_sales }}</span>
                    <span>bKash/Nagad: ৳{{ Number(summary.sales.bkash_sales + summary.sales.nagad_sales).toFixed(2) }}</span>
                </div>
                <div v-if="summary.sales.discrepancy !== 0" class="mt-2 text-[11px] text-amber-400 font-medium">
                    ⚠️ Discrepancy: ৳{{ summary.sales.discrepancy }}
                </div>
            </div>

            <!-- Material Usage Cost Card -->
            <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5 relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Material Cost (COGS)</span>
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <Flame class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-2xl font-black text-amber-300 font-mono">
                    ৳{{ Number(summary.cost_of_materials.total).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </div>
                <div class="mt-3 text-xs text-slate-400 flex items-center justify-between pt-2 border-t border-slate-800/60">
                    <span>Ingredients: ৳{{ summary.cost_of_materials.ingredient_consumption }}</span>
                    <span>Consumables: ৳{{ summary.cost_of_materials.consumables_allocated }}</span>
                </div>
            </div>

            <!-- Total Operating Costs Card -->
            <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5 relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Operating & Labour</span>
                    <div class="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                        <Receipt class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-2xl font-black text-slate-200 font-mono">
                    ৳{{ Number(summary.operating_costs.total).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </div>
                <div class="mt-3 text-xs text-slate-400 flex items-center justify-between pt-2 border-t border-slate-800/60">
                    <span>Expenses: ৳{{ summary.operating_costs.operating_expenses }}</span>
                    <span>Salary: ৳{{ summary.operating_costs.salary_cost }}</span>
                </div>
            </div>

            <!-- Net Profit Card -->
            <div 
                :class="[
                    'border rounded-2xl p-5 relative overflow-hidden',
                    summary.net_profit >= 0 
                        ? 'bg-emerald-950/20 border-emerald-500/30' 
                        : 'bg-rose-950/20 border-rose-500/30'
                ]"
            >
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider" :class="summary.net_profit >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                        True Net Profit
                    </span>
                    <div 
                        class="w-8 h-8 rounded-lg flex items-center justify-center"
                        :class="summary.net_profit >= 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'"
                    >
                        <component :is="summary.net_profit >= 0 ? TrendingUp : TrendingDown" class="w-4 h-4" />
                    </div>
                </div>
                <div 
                    class="text-2xl font-black font-mono"
                    :class="summary.net_profit >= 0 ? 'text-emerald-400' : 'text-rose-400'"
                >
                    ৳{{ Number(summary.net_profit).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </div>
                <div class="mt-3 text-xs text-slate-400 flex items-center justify-between pt-2 border-t border-slate-800/60">
                    <span>Net Margin: {{ summary.profit_margin }}%</span>
                    <span>Gross: ৳{{ summary.gross_profit }}</span>
                </div>
            </div>
        </div>

        <!-- Middle Section: Financial Accounts & Low Stock Alerts -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Financial Accounts & Liquidity Balances -->
            <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                        <Wallet class="w-4 h-4 text-amber-400" />
                        <span>Liquidity Accounts</span>
                    </h2>
                    <Link href="/accounts" class="text-xs text-amber-400 hover:text-amber-300 font-medium">Manage</Link>
                </div>

                <div class="space-y-3">
                    <div 
                        v-for="acc in accounts" 
                        :key="acc.id"
                        class="flex items-center justify-between p-3 rounded-xl bg-slate-950/60 border border-slate-800/60"
                    >
                        <div>
                            <span class="text-xs font-semibold text-white block">{{ acc.name }}</span>
                            <span class="text-[11px] text-slate-400 uppercase font-mono">{{ acc.type }}</span>
                        </div>
                        <span class="text-sm font-bold font-mono text-emerald-400">
                            ৳{{ Number(acc.current_balance).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Low Stock Warnings -->
            <div class="lg:col-span-2 bg-slate-900 border border-slate-800/80 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                        <AlertTriangle class="w-4 h-4 text-amber-400" />
                        <span>Inventory Alerts & Reorder Level</span>
                    </h2>
                    <Link href="/purchases" class="text-xs text-amber-400 hover:text-amber-300 font-medium">Record Purchase</Link>
                </div>

                <div v-if="lowStockProducts.length === 0" class="py-8 text-center text-slate-500 text-xs">
                    ✅ All raw materials and consumables are above minimum reorder threshold.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-800 font-semibold">
                                <th class="pb-2">Item Name</th>
                                <th class="pb-2">Category</th>
                                <th class="pb-2">Current On-Hand</th>
                                <th class="pb-2">Min Reorder</th>
                                <th class="pb-2 text-right">Avg Unit Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="item in lowStockProducts" :key="item.id" class="hover:bg-slate-800/30">
                                <td class="py-2.5 font-medium text-slate-200">{{ item.name }}</td>
                                <td class="py-2.5 text-slate-400">{{ item.category?.name || 'Raw' }}</td>
                                <td class="py-2.5 font-bold font-mono text-rose-400">
                                    {{ item.current_stock }} {{ item.base_unit }}
                                </td>
                                <td class="py-2.5 text-slate-400 font-mono">
                                    {{ item.min_reorder_level }} {{ item.base_unit }}
                                </td>
                                <td class="py-2.5 text-right font-mono text-slate-300">
                                    ৳{{ item.cost_price }} / {{ item.base_unit }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Bottom Section: Recent Perpetual Stock Movements -->
        <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                    <Clock class="w-4 h-4 text-amber-400" />
                    <span>Recent Perpetual Stock Ledger Movements</span>
                </h2>
                <Link href="/ledger" class="text-xs text-amber-400 hover:text-amber-300 font-medium">View Full Ledger</Link>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-800 font-semibold">
                            <th class="pb-2">Date</th>
                            <th class="pb-2">Product</th>
                            <th class="pb-2">Movement Type</th>
                            <th class="pb-2 text-right">Quantity In</th>
                            <th class="pb-2 text-right">Quantity Out</th>
                            <th class="pb-2 text-right">Balance After</th>
                            <th class="pb-2 text-right">Unit Cost</th>
                            <th class="pb-2 text-right">Total Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="m in recentMovements" :key="m.id" class="hover:bg-slate-800/30">
                            <td class="py-2.5 font-mono text-slate-400">{{ m.transaction_date }}</td>
                            <td class="py-2.5 font-medium text-slate-200">{{ m.product?.name }}</td>
                            <td class="py-2.5">
                                <span 
                                    :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider',
                                        m.movement_type === 'purchase_receipt' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                                        m.movement_type === 'consumption' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' :
                                        m.movement_type === 'wastage' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' :
                                        'bg-slate-800 text-slate-300'
                                    ]"
                                >
                                    {{ m.movement_type.replace('_', ' ') }}
                                </span>
                            </td>
                            <td class="py-2.5 text-right font-mono font-bold text-emerald-400">
                                {{ Number(m.quantity_in) > 0 ? `+${m.quantity_in}` : '-' }}
                            </td>
                            <td class="py-2.5 text-right font-mono font-bold text-rose-400">
                                {{ Number(m.quantity_out) > 0 ? `-${m.quantity_out}` : '-' }}
                            </td>
                            <td class="py-2.5 text-right font-mono font-semibold text-slate-200">
                                {{ m.balance_after }} {{ m.product?.base_unit }}
                            </td>
                            <td class="py-2.5 text-right font-mono text-slate-400">৳{{ m.unit_cost }}</td>
                            <td class="py-2.5 text-right font-mono font-bold text-slate-200">৳{{ m.total_value }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

