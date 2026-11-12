<script setup>
import { ref } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
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
    PlusCircle,
    Store,
    Users,
    CalendarCheck,
    CreditCard,
    CheckCircle2
} from 'lucide-vue-next';
import { useI18n } from '../Composables/useI18n';

const props = defineProps({
    selectedDate: String,
    summary: Object,
    lowStockProducts: Array,
    accounts: Array,
    recentMovements: Array,
    supplierStats: Object,
    staffStats: Object,
});

const { t } = useI18n();

const currentDate = ref(props.selectedDate);

const onDateChange = () => {
    router.get('/', { date: currentDate.value }, { preserveState: true });
};
</script>

<template>
    <AppLayout title="Dashboard">
        <Head :title="t('dashboard', 'Dashboard') + ' — Royal Nest'" />

        <!-- Date Filter & Quick Actions Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                    <span>{{ t('app_name', 'Royal Nest Café & Restaurant') }}</span>
                </h1>
                <p class="text-xs text-slate-400 mt-1">
                    {{ t('overview', 'Perpetual inventory FIFO costing, daily sales reconciliation, and net operating profit.') }}
                </p>
            </div>

            <div class="flex items-center space-x-3">
                <div class="flex items-center bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5">
                    <span class="text-xs text-slate-400 mr-2 font-medium">{{ t('date', 'Business Date') }}:</span>
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
                    <span>{{ t('daily_sales', 'Enter Daily Sales') }}</span>
                </Link>
            </div>
        </div>

        <!-- 4 Primary KPI Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Total Sales Card -->
            <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5 relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">{{ t('today_sales', 'Gross Daily Sales') }}</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <ShoppingBag class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-2xl font-black text-white font-mono">
                    ৳ {{ Number(summary.sales.total_sales).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </div>
                <div class="mt-3 text-xs text-slate-400 flex items-center justify-between pt-2 border-t border-slate-800/60">
                    <span>Cash: ৳{{ summary.sales.cash_sales }}</span>
                    <span>MFS: ৳{{ Number(summary.sales.bkash_sales + summary.sales.nagad_sales).toFixed(2) }}</span>
                </div>
            </div>

            <!-- Material Usage Cost Card -->
            <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5 relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">{{ t('today_cogs', 'Material Cost (COGS)') }}</span>
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <Flame class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-2xl font-black text-amber-300 font-mono">
                    ৳ {{ Number(summary.cost_of_materials.total).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </div>
                <div class="mt-3 text-xs text-slate-400 flex items-center justify-between pt-2 border-t border-slate-800/60">
                    <span>Usage: ৳{{ summary.cost_of_materials.ingredient_consumption }}</span>
                    <span>Consumables: ৳{{ summary.cost_of_materials.consumables_allocated }}</span>
                </div>
            </div>

            <!-- Total Operating Costs Card -->
            <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5 relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">{{ t('operating_costs', 'Operating & Labour') }}</span>
                    <div class="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                        <Receipt class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-2xl font-black text-slate-200 font-mono">
                    ৳ {{ Number(summary.operating_costs.total).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
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
                        {{ t('today_net_profit', 'True Net Profit') }}
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
                    ৳ {{ Number(summary.net_profit).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </div>
                <div class="mt-3 text-xs text-slate-400 flex items-center justify-between pt-2 border-t border-slate-800/60">
                    <span>{{ t('net_margin', 'Margin') }}: {{ summary.profit_margin }}%</span>
                    <span>Gross: ৳{{ summary.gross_profit }}</span>
                </div>
            </div>
        </div>

        <!-- Cross-Module Integration Row: Suppliers & Staff Operations -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Suppliers & Dues Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                            <Store class="w-4 h-4 text-amber-500" />
                            <span>{{ t('suppliers', 'Suppliers & Raw Material Dealers') }}</span>
                        </h2>
                        <div class="flex items-center gap-2">
                            <Link href="/suppliers/directory" class="text-xs text-slate-400 hover:text-slate-200">Directory</Link>
                            <span class="text-slate-600">•</span>
                            <Link href="/suppliers/active" class="text-xs text-amber-400 hover:text-amber-300 font-semibold">Active Dues</Link>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800">
                            <span class="text-[11px] text-slate-400 block">Active Vendors</span>
                            <span class="text-xl font-bold font-mono text-slate-200">{{ supplierStats.active_count }}</span>
                        </div>
                        <div class="p-3 rounded-xl bg-rose-950/20 border border-rose-900/40">
                            <span class="text-[11px] text-rose-300 block">{{ t('total_payable_due', 'Total Outstanding Due') }}</span>
                            <span class="text-xl font-bold font-mono text-rose-400">৳ {{ supplierStats.total_payable.toLocaleString() }}</span>
                        </div>
                    </div>

                    <!-- Recent Supplier Activity -->
                    <div>
                        <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-2">Recent Dealer Purchases & Bills</span>
                        <div class="space-y-2">
                            <div
                                v-for="pur in supplierStats.recent_purchases"
                                :key="pur.id"
                                class="flex items-center justify-between p-2 rounded-lg bg-slate-950/40 border border-slate-800/60 text-xs"
                            >
                                <div>
                                    <span class="font-semibold text-slate-200">{{ pur.supplier ? pur.supplier.name : 'Vendor' }}</span>
                                    <span class="text-slate-500 text-[10px] ml-2">#{{ pur.invoice_no }}</span>
                                </div>
                                <span class="font-mono text-amber-400 font-semibold">৳ {{ Number(pur.total_amount).toLocaleString() }}</span>
                            </div>
                            <div v-if="!supplierStats.recent_purchases || supplierStats.recent_purchases.length === 0" class="text-slate-500 text-xs py-2 italic text-center">
                                No supplier purchases recorded yet.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-800/60 flex items-center justify-between text-xs mt-3">
                    <span class="text-slate-400">Manage dealer accounts and record vouchers</span>
                    <Link href="/suppliers/active" class="text-amber-400 hover:underline flex items-center gap-1 font-semibold">
                        <span>Go to Active Suppliers</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                </div>
            </div>

            <!-- Staff Operations & Attendance Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                            <Users class="w-4 h-4 text-emerald-400" />
                            <span>{{ t('employees', 'Staff & Team Attendance') }}</span>
                        </h2>
                        <div class="flex items-center gap-2">
                            <Link href="/employees/attendance/bulk" class="text-xs text-emerald-400 hover:text-emerald-300 font-semibold">Bulk Attendance</Link>
                            <span class="text-slate-600">•</span>
                            <Link href="/employees" class="text-xs text-slate-400 hover:text-slate-200">Staff List</Link>
                        </div>
                    </div>

                    <!-- Attendance Pill Badges -->
                    <div class="grid grid-cols-4 gap-2 mb-4">
                        <div class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-center">
                            <span class="text-[10px] text-slate-400 block">Present</span>
                            <span class="text-base font-bold font-mono text-emerald-400">{{ staffStats.attendance.present }}</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-center">
                            <span class="text-[10px] text-slate-400 block">Half Day</span>
                            <span class="text-base font-bold font-mono text-amber-400">{{ staffStats.attendance.half_day }}</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-center">
                            <span class="text-[10px] text-slate-400 block">Leave</span>
                            <span class="text-base font-bold font-mono text-blue-400">{{ staffStats.attendance.leave }}</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-center">
                            <span class="text-[10px] text-slate-400 block">Absent</span>
                            <span class="text-base font-bold font-mono text-rose-400">{{ staffStats.attendance.absent }}</span>
                        </div>
                    </div>

                    <!-- Staff Advance Outstanding & Recent Payouts -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Recent Salary Disbursals</span>
                            <span class="text-xs text-amber-400 font-mono">Advances Due: ৳{{ staffStats.outstanding_advances.toLocaleString() }}</span>
                        </div>
                        <div class="space-y-2">
                            <div
                                v-for="sp in staffStats.recent_payments"
                                :key="sp.id"
                                class="flex items-center justify-between p-2 rounded-lg bg-slate-950/40 border border-slate-800/60 text-xs"
                            >
                                <div>
                                    <span class="font-semibold text-slate-200">{{ sp.employee ? sp.employee.name : 'Staff' }}</span>
                                    <span class="text-slate-500 text-[10px] ml-2">{{ sp.month_year }}</span>
                                </div>
                                <span class="font-mono text-emerald-400 font-semibold">৳ {{ Number(sp.amount).toLocaleString() }}</span>
                            </div>
                            <div v-if="!staffStats.recent_payments || staffStats.recent_payments.length === 0" class="text-slate-500 text-xs py-2 italic text-center">
                                No salary disbursals recorded yet.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-800/60 flex items-center justify-between text-xs mt-3">
                    <span class="text-slate-400">Total active team members: {{ staffStats.active_count }}</span>
                    <Link href="/employees" class="text-emerald-400 hover:underline flex items-center gap-1 font-semibold">
                        <span>Staff Directory & Profiles</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
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
                            ৳ {{ Number(acc.current_balance).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
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
                        <thead class="border-b border-slate-800 text-slate-400 font-semibold">
                            <tr>
                                <th class="pb-2">Product</th>
                                <th class="pb-2">Current Stock</th>
                                <th class="pb-2">Reorder Level</th>
                                <th class="pb-2 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/50">
                            <tr v-for="item in lowStockProducts" :key="item.id" class="text-slate-300">
                                <td class="py-2.5 font-medium">{{ item.name }}</td>
                                <td class="py-2.5 font-mono text-rose-400 font-bold">
                                    {{ Number(item.current_stock).toFixed(2) }} {{ item.base_unit }}
                                </td>
                                <td class="py-2.5 font-mono text-slate-400">
                                    {{ Number(item.min_reorder_level).toFixed(2) }} {{ item.base_unit }}
                                </td>
                                <td class="py-2.5 text-right">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-950/60 text-rose-400 border border-rose-800/40">
                                        Low Stock
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Stock Ledger Movements -->
        <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                    <Clock class="w-4 h-4 text-slate-400" />
                    <span>Recent Perpetual Stock Movements</span>
                </h2>
                <Link href="/ledger" class="text-xs text-amber-400 hover:text-amber-300 font-medium">Full Ledger</Link>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="border-b border-slate-800 text-slate-400 font-semibold uppercase text-[10px]">
                        <tr>
                            <th class="pb-2">Date</th>
                            <th class="pb-2">Movement Type</th>
                            <th class="pb-2">Product</th>
                            <th class="pb-2 text-right">Qty In</th>
                            <th class="pb-2 text-right">Qty Out</th>
                            <th class="pb-2 text-right">Balance</th>
                            <th class="pb-2 text-right">Batch Unit Cost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <tr v-for="mv in recentMovements" :key="mv.id" class="hover:bg-slate-800/30 transition">
                            <td class="py-2.5 font-mono text-slate-400">{{ mv.transaction_date }}</td>
                            <td class="py-2.5">
                                <span 
                                    :class="[
                                        'px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase',
                                        mv.quantity_in > 0 ? 'bg-emerald-950/60 text-emerald-400 border border-emerald-800/40' : 'bg-amber-950/60 text-amber-400 border border-amber-800/40'
                                    ]"
                                >
                                    {{ mv.movement_type.replace('_', ' ') }}
                                </span>
                            </td>
                            <td class="py-2.5 font-medium text-white">{{ mv.product ? mv.product.name : 'Product' }}</td>
                            <td class="py-2.5 text-right font-mono text-emerald-400">
                                {{ mv.quantity_in > 0 ? '+' + Number(mv.quantity_in).toFixed(2) : '—' }}
                            </td>
                            <td class="py-2.5 text-right font-mono text-amber-400">
                                {{ mv.quantity_out > 0 ? '-' + Number(mv.quantity_out).toFixed(2) : '—' }}
                            </td>
                            <td class="py-2.5 text-right font-mono font-bold text-slate-200">
                                {{ Number(mv.balance_after ?? mv.ending_stock ?? 0).toFixed(2) }}
                            </td>
                            <td class="py-2.5 text-right font-mono text-slate-300">
                                ৳{{ Number(mv.unit_cost).toFixed(2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
