<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    TrendingUp, 
    TrendingDown, 
    Calendar, 
    Filter, 
    ArrowRight, 
    Info, 
    Printer 
} from 'lucide-vue-next';

const props = defineProps({
    mode: String,
    selectedDate: String,
    startDate: String,
    endDate: String,
    summary: Object,
});

const reportMode = ref(props.mode);
const dateVal = ref(props.selectedDate);
const startVal = ref(props.startDate);
const endVal = ref(props.endDate);

const applyDate = () => {
    router.get('/reports/profit-loss', {
        mode: reportMode.value,
        date: dateVal.value,
        start_date: startVal.value,
        end_date: endVal.value,
    }, { preserveState: true });
};

const switchMode = (newMode) => {
    reportMode.value = newMode;
    applyDate();
};

const printReport = () => {
    window.print();
};
</script>

<template>
    <AppLayout title="Profit & Loss Statement">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">True Profit & Loss Statement</h1>
                <p class="text-xs text-slate-400 mt-1">
                    Accurate restaurant financial performance isolating material usage, amortized consumables, and operating costs.
                </p>
            </div>

            <div class="flex items-center space-x-3">
                <!-- Mode Toggle -->
                <div class="bg-slate-900 border border-slate-800 rounded-xl p-1 flex space-x-1 text-xs">
                    <button 
                        @click="switchMode('daily')"
                        :class="[
                            'px-3 py-1.5 rounded-lg font-bold transition cursor-pointer',
                            reportMode === 'daily' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-white'
                        ]"
                    >
                        Daily Report
                    </button>
                    <button 
                        @click="switchMode('range')"
                        :class="[
                            'px-3 py-1.5 rounded-lg font-bold transition cursor-pointer',
                            reportMode === 'range' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-white'
                        ]"
                    >
                        Date Range / Monthly
                    </button>
                </div>

                <!-- Date Controls -->
                <div v-if="reportMode === 'daily'" class="flex items-center bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-xs">
                    <span class="text-slate-400 mr-2 font-medium">Date:</span>
                    <input 
                        type="date" 
                        v-model="dateVal" 
                        @change="applyDate"
                        class="bg-transparent text-amber-400 font-mono focus:outline-none cursor-pointer"
                    />
                </div>

                <div v-else class="flex items-center space-x-2 bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-xs">
                    <input 
                        type="date" 
                        v-model="startVal" 
                        @change="applyDate"
                        class="bg-transparent text-amber-400 font-mono focus:outline-none cursor-pointer"
                    />
                    <span class="text-slate-500">to</span>
                    <input 
                        type="date" 
                        v-model="endVal" 
                        @change="applyDate"
                        class="bg-transparent text-amber-400 font-mono focus:outline-none cursor-pointer"
                    />
                </div>

                <button 
                    @click="printReport"
                    class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-3.5 py-2 rounded-xl text-xs flex items-center space-x-1.5 transition cursor-pointer"
                >
                    <Printer class="w-4 h-4" />
                    <span>Print</span>
                </button>
            </div>
        </div>

        <!-- Plain-Language Explanation Alert -->
        <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 mb-6 flex items-start space-x-3 text-xs text-amber-200/90 print:hidden">
            <Info class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" />
            <div>
                <strong class="text-amber-300 font-semibold block mb-0.5">How Royal Nest Computes Your True Profit:</strong>
                Purchasing bulk rice or spices does NOT hit today's P&L — only the ingredients actually consumed in cooking are charged as material cost. Multi-day consumables (like tissue boxes) are amortized evenly over their usage days. Staff salaries accrue daily rather than spiking on payday. Internal account transfers have ৳0 profit effect.
            </div>
        </div>

        <!-- Statement Sheet -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-8 max-w-4xl mx-auto shadow-xl font-sans">
            <!-- Statement Header -->
            <div class="text-center pb-6 border-b border-slate-800">
                <h2 class="text-xl font-bold text-white tracking-tight uppercase">Royal Nest Café & Restaurant</h2>
                <h3 class="text-sm font-semibold text-amber-400 mt-1">Income & Expenditure Statement (Profit & Loss)</h3>
                <p class="text-xs text-slate-400 font-mono mt-1">
                    {{ reportMode === 'daily' ? `For Period: ${selectedDate}` : `For Period: ${startDate} to ${endDate}` }}
                    • Currency: BDT (৳)
                </p>
            </div>

            <!-- Revenue Section -->
            <div class="py-6 border-b border-slate-800/80">
                <div class="flex items-center justify-between text-sm font-bold text-white mb-3">
                    <span class="uppercase tracking-wider">1. Operating Revenue / Sales</span>
                    <span class="font-mono text-emerald-400 text-base">
                        ৳{{ Number(reportMode === 'daily' ? summary.sales.total_sales : summary.total_sales).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                    </span>
                </div>

                <div v-if="reportMode === 'daily'" class="pl-4 space-y-1.5 text-xs text-slate-400 font-mono">
                    <div class="flex justify-between">
                        <span>• Cash Drawer Sales</span>
                        <span>৳{{ Number(summary.sales.cash_sales).toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>• bKash Merchant Receipts</span>
                        <span>৳{{ Number(summary.sales.bkash_sales).toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>• Nagad Merchant Receipts</span>
                        <span>৳{{ Number(summary.sales.nagad_sales).toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>• Card / Bank Settlement Receipts</span>
                        <span>৳{{ Number(summary.sales.card_bank_sales).toFixed(2) }}</span>
                    </div>
                    <div v-if="summary.sales.discrepancy !== 0" class="flex justify-between text-amber-400 font-semibold pt-1 border-t border-slate-800/40">
                        <span>⚠️ Reconciliation Discrepancy</span>
                        <span>৳{{ Number(summary.sales.discrepancy).toFixed(2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Cost of Goods / Materials Section -->
            <div class="py-6 border-b border-slate-800/80">
                <div class="flex items-center justify-between text-sm font-bold text-white mb-3">
                    <span class="uppercase tracking-wider">2. Cost of Materials & Ingredients (COGS)</span>
                    <span class="font-mono text-amber-300 text-base">
                        (৳{{ Number(reportMode === 'daily' ? summary.cost_of_materials.total : summary.cost_of_materials).toLocaleString('en-US', { minimumFractionDigits: 2 }) }})
                    </span>
                </div>

                <div class="pl-4 space-y-1.5 text-xs text-slate-400 font-mono">
                    <div class="flex justify-between">
                        <span>• Actual Kitchen Ingredient Usage</span>
                        <span>৳{{ Number(reportMode === 'daily' ? summary.cost_of_materials.ingredient_consumption : summary.ingredient_consumption).toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>• Consumables Allocated Amortization</span>
                        <span>৳{{ Number(reportMode === 'daily' ? summary.cost_of_materials.consumables_allocated : summary.consumables_allocated).toFixed(2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Gross Profit Highlight -->
            <div class="py-4 bg-slate-950/60 -mx-8 px-8 border-b border-slate-800 flex items-center justify-between">
                <div>
                    <span class="text-sm font-bold text-white uppercase tracking-wider block">Gross Profit</span>
                    <span class="text-[11px] text-slate-400">Total Sales minus Cost of Materials</span>
                </div>
                <span class="text-lg font-black font-mono text-emerald-400">
                    ৳{{ Number(summary.gross_profit).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </span>
            </div>

            <!-- Operating & Labor Costs Section -->
            <div class="py-6 border-b border-slate-800/80">
                <div class="flex items-center justify-between text-sm font-bold text-white mb-3">
                    <span class="uppercase tracking-wider">3. Operating, Staff & Spoilage Expenses</span>
                    <span class="font-mono text-rose-300 text-base">
                        (৳{{ Number(reportMode === 'daily' ? summary.operating_costs.total : summary.total_operating_cost).toLocaleString('en-US', { minimumFractionDigits: 2 }) }})
                    </span>
                </div>

                <div class="pl-4 space-y-1.5 text-xs text-slate-400 font-mono">
                    <div class="flex justify-between">
                        <span>• Daily Operating Expenses (Utilities, Rent, Transport)</span>
                        <span>৳{{ Number(reportMode === 'daily' ? summary.operating_costs.operating_expenses : summary.operating_expenses).toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>• Staff Salary Cost Accrual</span>
                        <span>৳{{ Number(reportMode === 'daily' ? summary.operating_costs.salary_cost : summary.salary_cost).toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>• Confirmed Ingredient Wastage & Spoilage</span>
                        <span>৳{{ Number(reportMode === 'daily' ? summary.operating_costs.wastage_cost : summary.wastage_cost).toFixed(2) }}</span>
                    </div>
                </div>
            </div>

            <!-- True Net Operating Profit Final Block -->
            <div 
                :class="[
                    'py-6 -mx-8 px-8 rounded-b-2xl flex items-center justify-between border-t-2',
                    summary.net_profit >= 0 
                        ? 'bg-emerald-950/30 border-emerald-500/50' 
                        : 'bg-rose-950/30 border-rose-500/50'
                ]"
            >
                <div>
                    <span 
                        class="text-base font-black uppercase tracking-wider block"
                        :class="summary.net_profit >= 0 ? 'text-emerald-400' : 'text-rose-400'"
                    >
                        True Net Profit / (Loss)
                    </span>
                    <span class="text-xs text-slate-400">
                        Net Profit Margin: <strong class="text-white">{{ summary.profit_margin }}%</strong>
                    </span>
                </div>

                <div 
                    class="text-2xl font-black font-mono"
                    :class="summary.net_profit >= 0 ? 'text-emerald-400' : 'text-rose-400'"
                >
                    ৳{{ Number(summary.net_profit).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </div>
            </div>
        </div>
    </AppLayout>
</template>

