<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    Calendar, 
    Filter, 
    ShoppingBag, 
    Truck, 
    Receipt, 
    DollarSign, 
    Package, 
    Box, 
    UserCheck, 
    Building2, 
    AlertCircle, 
    ArrowDownRight, 
    CheckCircle2, 
    Clock, 
    CreditCard 
} from 'lucide-vue-next';

const props = defineProps({
    mode: { type: String, default: 'daily' },
    selectedDate: { type: String, required: true },
    startDate: { type: String, required: true },
    endDate: { type: String, required: true },
    activeTab: { type: String, default: 'purchases' },
    categoryFilter: { type: String, default: 'all' },
    purchaseLines: { type: Array, default: () => [] },
    allOutflows: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
});

const currentTab = ref(props.activeTab);
const currentFilter = ref(props.categoryFilter);
const dateMode = ref(props.mode);
const dateInput = ref(props.selectedDate);
const startInput = ref(props.startDate);
const endInput = ref(props.endDate);

const applyFilters = () => {
    router.get('/reports/transactions', {
        mode: dateMode.value,
        date: dateInput.value,
        start_date: startInput.value,
        end_date: endInput.value,
        tab: currentTab.value,
        category: currentFilter.value,
    }, { preserveState: true, preserveScroll: true });
};

const switchTab = (tab) => {
    currentTab.value = tab;
};

// Filtered purchase lines
const filteredPurchases = computed(() => {
    if (currentFilter.value === 'all') return props.purchaseLines;
    if (currentFilter.value === 'raw_material') {
        return props.purchaseLines.filter(p => p.category_type === 'raw_material');
    }
    if (currentFilter.value === 'packaging') {
        return props.purchaseLines.filter(p => p.category_type === 'packaging');
    }
    if (currentFilter.value === 'consumable') {
        return props.purchaseLines.filter(p => p.category_type !== 'raw_material' && p.category_type !== 'packaging');
    }
    return props.purchaseLines;
});

// Filtered outflows
const filteredOutflows = computed(() => {
    if (currentFilter.value === 'all') return props.allOutflows;
    return props.allOutflows.filter(o => o.type === currentFilter.value);
});

// Combined items stream
const combinedItems = computed(() => {
    const pItems = props.purchaseLines.map(p => ({
        id: `p-${p.purchase_id}-${p.item_name}`,
        kind: 'purchase',
        date: p.date,
        title: p.item_name,
        subtitle: `${p.supplier_name} • Inv #${p.invoice_no}`,
        categoryType: p.category_type,
        categoryName: p.category_name,
        amount: p.line_total,
        details: `${p.quantity} ${p.unit} @ ৳${p.unit_price}`,
        isPnlExpense: false,
        reversalStatus: p.reversal_status || 'active',
    }));

    const oItems = props.allOutflows.map(o => ({
        id: `o-${o.type}-${o.id}`,
        kind: o.type,
        date: o.date,
        title: o.name,
        subtitle: `${o.type_label} • ${o.account_name}`,
        categoryType: o.type,
        categoryName: o.type_label,
        amount: o.amount,
        details: o.notes || (o.allocations?.length ? `${o.allocations.length} bills settled` : ''),
        isPnlExpense: o.is_pnl_expense,
        reversalStatus: o.reversal_status || 'active',
    }));

    let combined = [...pItems, ...oItems].sort((a, b) => b.date.localeCompare(a.date));

    if (currentFilter.value !== 'all') {
        if (currentFilter.value === 'raw_material' || currentFilter.value === 'packaging') {
            combined = combined.filter(c => c.categoryType === currentFilter.value);
        } else if (currentFilter.value === 'consumable') {
            combined = combined.filter(c => c.kind === 'purchase' && c.categoryType !== 'raw_material' && c.categoryType !== 'packaging');
        } else {
            combined = combined.filter(c => c.kind === currentFilter.value);
        }
    }

    return combined;
});
</script>

<template>
    <AppLayout title="Daily Purchases & Outflows Report">
        <Head title="Daily Purchases & Outflows" />

        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 space-y-4 sm:space-y-6">
            <!-- Header & Filter Form -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-lg sm:text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                            <Receipt class="w-5 h-5 sm:w-6 sm:h-6 text-amber-400" />
                            Daily Purchases & Expenses Report
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                            Audit itemized daily purchases, operating expenses, and cash liability settlements.
                        </p>
                    </div>

                    <!-- Date Mode Toggle -->
                    <div class="inline-flex rounded-xl bg-slate-950 p-1 border border-slate-800 self-start sm:self-auto">
                        <button
                            type="button"
                            @click="dateMode = 'daily'; applyFilters()"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                                dateMode === 'daily' ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-400 hover:text-white'
                            ]"
                        >
                            Single Date
                        </button>
                        <button
                            type="button"
                            @click="dateMode = 'range'; applyFilters()"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                                dateMode === 'range' ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-400 hover:text-white'
                            ]"
                        >
                            Date Range
                        </button>
                    </div>
                </div>

                <!-- Date Inputs Bar -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-3 pt-2 border-t border-slate-800/80">
                    <div v-if="dateMode === 'daily'" class="flex items-center gap-2">
                        <span class="text-xs text-slate-400">Date:</span>
                        <input
                            type="date"
                            v-model="dateInput"
                            @change="applyFilters"
                            class="bg-slate-950 border border-slate-700 text-white text-xs sm:text-sm rounded-xl px-3 py-1.5 focus:border-amber-400 focus:outline-none"
                        />
                    </div>
                    <div v-else class="flex flex-wrap items-center gap-2">
                        <span class="text-xs text-slate-400">From:</span>
                        <input
                            type="date"
                            v-model="startInput"
                            @change="applyFilters"
                            class="bg-slate-950 border border-slate-700 text-white text-xs sm:text-sm rounded-xl px-3 py-1.5 focus:border-amber-400 focus:outline-none"
                        />
                        <span class="text-xs text-slate-400">To:</span>
                        <input
                            type="date"
                            v-model="endInput"
                            @change="applyFilters"
                            class="bg-slate-950 border border-slate-700 text-white text-xs sm:text-sm rounded-xl px-3 py-1.5 focus:border-amber-400 focus:outline-none"
                        />
                    </div>
                </div>
            </div>

            <!-- Financial Metric Highlights (Mobile Grid) -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
                <!-- Total Purchases -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 sm:p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-400 text-xs font-medium">
                        <span>Total Purchases</span>
                        <Truck class="w-4 h-4 text-emerald-400" />
                    </div>
                    <p class="text-base sm:text-2xl font-bold text-white mt-1">৳{{ summary.total_purchases?.toLocaleString() || 0 }}</p>
                    <div class="text-[10px] sm:text-xs text-slate-400 mt-1 flex flex-wrap gap-x-2">
                        <span class="text-emerald-400">Raw: ৳{{ summary.total_raw_materials?.toLocaleString() || 0 }}</span>
                        <span class="text-blue-400">Pkg: ৳{{ summary.total_packaging?.toLocaleString() || 0 }}</span>
                    </div>
                </div>

                <!-- True Operating Expenses -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 sm:p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-400 text-xs font-medium">
                        <span>Operating Expenses</span>
                        <Receipt class="w-4 h-4 text-amber-400" />
                    </div>
                    <p class="text-base sm:text-2xl font-bold text-amber-400 mt-1">৳{{ summary.total_operating_expenses?.toLocaleString() || 0 }}</p>
                    <p class="text-[10px] text-slate-400 mt-1">P&L Operating Cost</p>
                </div>

                <!-- Supplier Due Settlements -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 sm:p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-400 text-xs font-medium">
                        <span>Supplier Due Paid</span>
                        <Building2 class="w-4 h-4 text-cyan-400" />
                    </div>
                    <p class="text-base sm:text-2xl font-bold text-cyan-400 mt-1">৳{{ summary.total_supplier_payments?.toLocaleString() || 0 }}</p>
                    <p class="text-[10px] text-slate-400 mt-1">Payables Settlement</p>
                </div>

                <!-- Total Cash Outflow -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 sm:p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-400 text-xs font-medium">
                        <span>Total Cash Outflow</span>
                        <ArrowDownRight class="w-4 h-4 text-rose-400" />
                    </div>
                    <p class="text-base sm:text-2xl font-bold text-rose-400 mt-1">৳{{ summary.total_cash_outflows?.toLocaleString() || 0 }}</p>
                    <div class="text-[10px] text-slate-400 mt-1 flex gap-2">
                        <span>Salary: ৳{{ summary.total_salary_payments?.toLocaleString() || 0 }}</span>
                        <span>Adv: ৳{{ summary.total_advances_issued?.toLocaleString() || 0 }}</span>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-800 pb-3">
                <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
                    <button
                        type="button"
                        @click="switchTab('purchases')"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center gap-1.5 whitespace-nowrap transition',
                            currentTab === 'purchases'
                                ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20'
                                : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'
                        ]"
                    >
                        <Truck class="w-4 h-4" />
                        Daily Purchases ({{ purchaseLines.length }})
                    </button>
                    <button
                        type="button"
                        @click="switchTab('expenses')"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center gap-1.5 whitespace-nowrap transition',
                            currentTab === 'expenses'
                                ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20'
                                : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'
                        ]"
                    >
                        <Receipt class="w-4 h-4" />
                        Expenses & Outflows ({{ allOutflows.length }})
                    </button>
                    <button
                        type="button"
                        @click="switchTab('combined')"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center gap-1.5 whitespace-nowrap transition',
                            currentTab === 'combined'
                                ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20'
                                : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'
                        ]"
                    >
                        <ShoppingBag class="w-4 h-4" />
                        Combined Stream
                    </button>
                </div>

                <!-- 1-Tap Category Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none text-xs">
                    <button
                        type="button"
                        @click="currentFilter = 'all'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-medium whitespace-nowrap transition',
                            currentFilter === 'all' ? 'bg-slate-700 text-white' : 'bg-slate-900 text-slate-400 hover:text-white'
                        ]"
                    >
                        All
                    </button>
                    <button
                        type="button"
                        @click="currentFilter = 'raw_material'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-medium whitespace-nowrap transition',
                            currentFilter === 'raw_material' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-slate-900 text-slate-400 hover:text-emerald-400'
                        ]"
                    >
                        Raw Materials
                    </button>
                    <button
                        type="button"
                        @click="currentFilter = 'packaging'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-medium whitespace-nowrap transition',
                            currentFilter === 'packaging' ? 'bg-blue-500/20 text-blue-300 border border-blue-500/40' : 'bg-slate-900 text-slate-400 hover:text-blue-400'
                        ]"
                    >
                        Packaging
                    </button>
                    <button
                        type="button"
                        @click="currentFilter = 'operating_expense'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-medium whitespace-nowrap transition',
                            currentFilter === 'operating_expense' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : 'bg-slate-900 text-slate-400 hover:text-amber-400'
                        ]"
                    >
                        Operating Exp
                    </button>
                    <button
                        type="button"
                        @click="currentFilter = 'supplier_payment'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-medium whitespace-nowrap transition',
                            currentFilter === 'supplier_payment' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40' : 'bg-slate-900 text-slate-400 hover:text-cyan-400'
                        ]"
                    >
                        Supplier Due
                    </button>
                    <button
                        type="button"
                        @click="currentFilter = 'salary_payment'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-medium whitespace-nowrap transition',
                            currentFilter === 'salary_payment' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/40' : 'bg-slate-900 text-slate-400 hover:text-purple-400'
                        ]"
                    >
                        Salary
                    </button>
                </div>
            </div>

            <!-- Tab 1: Daily Purchases -->
            <div v-if="currentTab === 'purchases'" class="space-y-3">
                <div v-if="filteredPurchases.length === 0" class="bg-slate-900 border border-slate-800 rounded-2xl p-8 text-center text-slate-400">
                    <Package class="w-10 h-10 mx-auto text-slate-600 mb-2" />
                    <p class="text-sm font-semibold text-slate-300">No purchase records found for this period.</p>
                </div>

                <!-- Desktop Table -->
                <div v-else class="hidden sm:block bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-950/80 text-slate-400 uppercase tracking-wider text-[11px] border-b border-slate-800">
                            <tr>
                                <th class="p-3 font-semibold">Date & Invoice</th>
                                <th class="p-3 font-semibold">Item & Category</th>
                                <th class="p-3 font-semibold">Supplier</th>
                                <th class="p-3 font-semibold text-right">Quantity</th>
                                <th class="p-3 font-semibold text-right">Unit Price</th>
                                <th class="p-3 font-semibold text-right">Line Total</th>
                                <th class="p-3 font-semibold text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="(p, idx) in filteredPurchases" :key="idx" class="hover:bg-slate-800/40 transition">
                                <td class="p-3 font-medium text-slate-200">
                                    <div>{{ p.date }}</div>
                                    <span class="text-[11px] text-slate-400 font-mono">#{{ p.invoice_no }}</span>
                                </td>
                                <td class="p-3">
                                    <div class="font-bold text-white">{{ p.item_name }}</div>
                                    <span :class="[
                                        'inline-block px-2 py-0.5 rounded text-[10px] font-semibold mt-0.5',
                                        p.category_type === 'raw_material' ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' :
                                        p.category_type === 'packaging' ? 'bg-blue-500/15 text-blue-400 border border-blue-500/30' :
                                        'bg-purple-500/15 text-purple-400 border border-purple-500/30'
                                    ]">
                                        {{ p.category_name }}
                                    </span>
                                </td>
                                <td class="p-3 text-slate-300">{{ p.supplier_name }}</td>
                                <td class="p-3 text-right font-medium text-slate-200">{{ p.quantity }} {{ p.unit }}</td>
                                <td class="p-3 text-right text-slate-400">৳{{ p.unit_price }}</td>
                                <td class="p-3 text-right font-bold text-amber-400 text-sm">৳{{ p.line_total.toLocaleString() }}</td>
                                <td class="p-3 text-center">
                                    <span v-if="p.reversal_status === 'voided'" class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-rose-950/80 text-rose-300 border border-rose-800">
                                        বাতিল / Voided
                                    </span>
                                    <span v-else :class="[
                                        'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase',
                                        p.payment_status === 'paid' ? 'bg-emerald-500/15 text-emerald-400' :
                                        p.payment_status === 'partial' ? 'bg-amber-500/15 text-amber-400' :
                                        'bg-rose-500/15 text-rose-400'
                                    ]">
                                        {{ p.payment_status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards List -->
                <div class="sm:hidden space-y-2.5">
                    <div
                        v-for="(p, idx) in filteredPurchases"
                        :key="idx"
                        class="bg-slate-900 border border-slate-800 rounded-xl p-3 space-y-2 shadow-sm"
                        :class="p.reversal_status === 'voided' ? 'opacity-70 border-rose-900/40' : ''"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-white text-sm block">{{ p.item_name }}</span>
                                    <span v-if="p.reversal_status === 'voided'" class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase bg-rose-950 text-rose-300 border border-rose-800">
                                        বাতিল
                                    </span>
                                </div>
                                <span class="text-xs text-slate-400">{{ p.supplier_name }} • <span class="font-mono">#{{ p.invoice_no }}</span></span>
                            </div>
                            <span class="text-sm font-bold" :class="p.reversal_status === 'voided' ? 'text-slate-500 line-through' : 'text-amber-400'">৳{{ p.line_total.toLocaleString() }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-800/80">
                            <span :class="[
                                'px-2 py-0.5 rounded text-[10px] font-semibold',
                                p.category_type === 'raw_material' ? 'bg-emerald-500/15 text-emerald-400' :
                                p.category_type === 'packaging' ? 'bg-blue-500/15 text-blue-400' :
                                'bg-purple-500/15 text-purple-400'
                            ]">
                                {{ p.category_name }}
                            </span>
                            <span class="text-slate-400">{{ p.quantity }} {{ p.unit }} @ ৳{{ p.unit_price }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Expenses & Outflows -->
            <div v-if="currentTab === 'expenses'" class="space-y-3">
                <div v-if="filteredOutflows.length === 0" class="bg-slate-900 border border-slate-800 rounded-2xl p-8 text-center text-slate-400">
                    <Receipt class="w-10 h-10 mx-auto text-slate-600 mb-2" />
                    <p class="text-sm font-semibold text-slate-300">No expense or outflow records found for this period.</p>
                </div>

                <!-- Desktop Table -->
                <div v-else class="hidden sm:block bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-950/80 text-slate-400 uppercase tracking-wider text-[11px] border-b border-slate-800">
                            <tr>
                                <th class="p-3 font-semibold">Date</th>
                                <th class="p-3 font-semibold">Classification</th>
                                <th class="p-3 font-semibold">Description</th>
                                <th class="p-3 font-semibold">Account</th>
                                <th class="p-3 font-semibold text-right">Amount</th>
                                <th class="p-3 font-semibold">Accounting Nature</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="o in filteredOutflows" :key="o.id" class="hover:bg-slate-800/40 transition">
                                <td class="p-3 text-slate-300 font-mono text-[11px]">{{ o.date }}</td>
                                <td class="p-3">
                                    <span :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-bold uppercase inline-block',
                                        o.type === 'operating_expense' ? 'bg-amber-500/15 text-amber-400 border border-amber-500/30' :
                                        o.type === 'supplier_payment' ? 'bg-cyan-500/15 text-cyan-400 border border-cyan-500/30' :
                                        o.type === 'salary_payment' ? 'bg-purple-500/15 text-purple-400 border border-purple-500/30' :
                                        'bg-orange-500/15 text-orange-400 border border-orange-500/30'
                                    ]">
                                        {{ o.type_label }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    <div class="font-bold text-white">{{ o.name }}</div>
                                    <div v-if="o.notes" class="text-[11px] text-slate-400 mt-0.5">{{ o.notes }}</div>
                                    <div v-if="o.allocations?.length" class="text-[10px] text-cyan-300 mt-0.5">
                                        Allocated to: {{ o.allocations.map(a => `${a.purchase_invoice} (৳${a.allocated_amount})`).join(', ') }}
                                    </div>
                                </td>
                                <td class="p-3 text-slate-300 flex items-center gap-1 mt-1">
                                    <CreditCard class="w-3.5 h-3.5 text-slate-400" />
                                    {{ o.account_name }}
                                </td>
                                <td class="p-3 text-right font-bold text-sm" :class="o.is_pnl_expense ? 'text-amber-400' : 'text-slate-200'">
                                    ৳{{ o.amount.toLocaleString() }}
                                </td>
                                <td class="p-3">
                                    <span v-if="o.reversal_status === 'voided'" class="text-[10px] text-rose-300 font-bold bg-rose-950/80 border border-rose-800 px-2 py-0.5 rounded">
                                        বাতিল / Voided
                                    </span>
                                    <span v-else-if="o.is_pnl_expense" class="text-[10px] text-emerald-400 font-semibold bg-emerald-500/10 px-2 py-0.5 rounded">
                                        Direct P&L Expense
                                    </span>
                                    <span v-else class="text-[10px] text-slate-400 font-medium bg-slate-800 px-2 py-0.5 rounded">
                                        Balance Sheet Outflow
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards List -->
                <div class="sm:hidden space-y-2.5">
                    <div
                        v-for="o in filteredOutflows"
                        :key="o.id"
                        class="bg-slate-900 border border-slate-800 rounded-xl p-3 space-y-2 shadow-sm"
                        :class="o.reversal_status === 'voided' ? 'opacity-70 border-rose-900/40' : ''"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-white text-sm block">{{ o.name }}</span>
                                    <span v-if="o.reversal_status === 'voided'" class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase bg-rose-950 text-rose-300 border border-rose-800">
                                        বাতিল
                                    </span>
                                </div>
                                <span class="text-xs text-slate-400">{{ o.date }} • {{ o.account_name }}</span>
                            </div>
                            <span class="text-sm font-bold" :class="o.reversal_status === 'voided' ? 'text-slate-500 line-through' : (o.is_pnl_expense ? 'text-amber-400' : 'text-slate-100')">
                                ৳{{ o.amount.toLocaleString() }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-800/80">
                            <span :class="[
                                'px-2 py-0.5 rounded text-[10px] font-bold uppercase',
                                o.type === 'operating_expense' ? 'bg-amber-500/15 text-amber-400' :
                                o.type === 'supplier_payment' ? 'bg-cyan-500/15 text-cyan-400' :
                                o.type === 'salary_payment' ? 'bg-purple-500/15 text-purple-400' :
                                'bg-orange-500/15 text-orange-400'
                            ]">
                                {{ o.type_label }}
                            </span>
                            <span v-if="o.reversal_status === 'voided'" class="text-[10px] text-rose-400 font-bold">বাতিলকৃত (Voided)</span>
                            <span v-else-if="o.is_pnl_expense" class="text-[10px] text-emerald-400 font-semibold">P&L Expense</span>
                            <span v-else class="text-[10px] text-slate-400">Balance Sheet</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Combined Stream -->
            <div v-if="currentTab === 'combined'" class="space-y-3">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl divide-y divide-slate-800/60 shadow-sm overflow-hidden">
                    <div
                        v-for="item in combinedItems"
                        :key="item.id"
                        class="p-3 sm:p-4 hover:bg-slate-800/40 transition flex items-center justify-between gap-3"
                        :class="item.reversalStatus === 'voided' ? 'opacity-60 bg-rose-950/10' : ''"
                    >
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-white text-xs sm:text-sm">{{ item.title }}</span>
                                <span v-if="item.reversalStatus === 'voided'" class="px-2 py-0.2 rounded text-[10px] font-bold uppercase bg-rose-950 text-rose-300 border border-rose-800">
                                    বাতিল / Voided
                                </span>
                                <span v-else :class="[
                                    'px-2 py-0.2 rounded text-[10px] font-semibold',
                                    item.kind === 'purchase' ? 'bg-emerald-500/15 text-emerald-400' :
                                    item.kind === 'operating_expense' ? 'bg-amber-500/15 text-amber-400' :
                                    item.kind === 'supplier_payment' ? 'bg-cyan-500/15 text-cyan-400' :
                                    'bg-purple-500/15 text-purple-400'
                                ]">
                                    {{ item.categoryName }}
                                </span>
                            </div>
                            <div class="text-[11px] sm:text-xs text-slate-400">
                                <span>{{ item.date }}</span> • <span>{{ item.subtitle }}</span>
                                <span v-if="item.details" class="hidden sm:inline"> • {{ item.details }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs sm:text-sm font-bold" :class="item.reversalStatus === 'voided' ? 'text-slate-500 line-through' : 'text-amber-400'">
                                ৳{{ item.amount.toLocaleString() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
