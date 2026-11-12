<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useI18n } from '../../Composables/useI18n';
import {
    Calculator,
    Calendar,
    ArrowUpRight,
    ArrowDownLeft,
    AlertTriangle,
    CheckCircle2,
    Clock,
    RefreshCw,
    Wallet,
    Banknote,
    HelpCircle,
    Send,
    Lock,
    Save,
    Info,
    ChevronDown,
    ChevronUp
} from 'lucide-vue-next';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
    selectedDate: {
        type: String,
        required: true,
    },
});

const { t, formatCurrency } = useI18n();

const showWithdrawals = ref(false);
const showReceipts = ref(false);
const showFormulaModal = ref(false);

const currentDate = ref(props.selectedDate);

const onDateChange = () => {
    router.get('/cash-reconciliation', { date: currentDate.value }, { preserveState: false });
};

// Form for saving reconciliation
const form = useForm({
    reconciliation_date: props.data.date,
    opening_cash: props.data.opening_cash ?? 0,
    yesterday_closing_cash: props.data.yesterday_closing_cash ?? 0,
    counted_closing_cash: props.data.counted_closing_cash !== null ? props.data.counted_closing_cash : '',
    other_cash_receipts: props.data.other_cash_receipts ?? 0,
    today_cash_payments: props.data.today_cash_payments ?? 0,
    recorded_non_cash_sales: props.data.recorded_non_cash_sales ?? 0,
    status: props.data.reconciliation?.status || 'draft',
    notes: props.data.reconciliation?.notes || '',
});

// Sync form when props change
watch(() => props.data, (newData) => {
    form.reconciliation_date = newData.date;
    form.opening_cash = newData.opening_cash ?? 0;
    form.yesterday_closing_cash = newData.yesterday_closing_cash ?? 0;
    form.counted_closing_cash = newData.counted_closing_cash !== null ? newData.counted_closing_cash : '';
    form.other_cash_receipts = newData.other_cash_receipts ?? 0;
    form.today_cash_payments = newData.today_cash_payments ?? 0;
    form.recorded_non_cash_sales = newData.recorded_non_cash_sales ?? 0;
    form.status = newData.reconciliation?.status || 'draft';
    form.notes = newData.reconciliation?.notes || '';
}, { deep: true });

// Live estimated cash sales
const liveEstimatedCashSales = computed(() => {
    if (form.counted_closing_cash === '' || form.counted_closing_cash === null || isNaN(form.counted_closing_cash)) {
        return null;
    }
    const closing = parseFloat(form.counted_closing_cash) || 0;
    const opening = parseFloat(form.opening_cash) || 0;
    const otherReceipts = parseFloat(form.other_cash_receipts) || 0;
    const payments = parseFloat(form.today_cash_payments) || 0;

    return Math.round((closing - opening - otherReceipts + payments) * 100) / 100;
});

// Live estimated total sales
const liveEstimatedTotalSales = computed(() => {
    if (liveEstimatedCashSales.value === null) return null;
    const nonCash = parseFloat(form.recorded_non_cash_sales) || 0;
    return Math.round((liveEstimatedCashSales.value + nonCash) * 100) / 100;
});

// Difference with recorded daily cash sales
const varianceWithRecordedCash = computed(() => {
    if (liveEstimatedCashSales.value === null) return null;
    const recorded = props.data.today_recorded_cash_sales || 0;
    return Math.round((liveEstimatedCashSales.value - recorded) * 100) / 100;
});

// Warnings calculation
const activeWarnings = computed(() => {
    const list = [];
    const opening = parseFloat(form.opening_cash) || 0;
    if (opening <= 0 && (props.data.yesterday_closing_cash || 0) <= 0) {
        list.push(t('warn_opening_cash_zero', 'Opening cash balance is zero or not confirmed. Please enter and verify the morning drawer balance.'));
    }
    if (form.counted_closing_cash === '' || form.counted_closing_cash === null) {
        list.push(t('warn_closing_cash_missing', 'Closing cash has not been physically counted yet. Please count the drawer cash to compute estimated sales.'));
    } else if (liveEstimatedCashSales.value !== null && liveEstimatedCashSales.value < 0) {
        list.push(t('warn_negative_estimated_sales', 'Warning: Estimated cash sales is negative. This indicates unrecorded cash expenses, unaccounted outflows, or an inaccurate drawer count.'));
    }
    if (varianceWithRecordedCash.value !== null && Math.abs(varianceWithRecordedCash.value) > 0.01 && props.data.today_recorded_cash_sales > 0) {
        list.push(t('warn_sales_variance', 'Reconciliation Variance: Estimated cash sales differs from entered POS daily cash sales by ') + `৳${Math.abs(varianceWithRecordedCash.value).toFixed(2)}.`);
    }
    return list;
});

const submitReconciliation = (newStatus = 'draft') => {
    form.status = newStatus;
    form.post('/cash-reconciliation', {
        preserveScroll: true,
    });
};

const syncToDailySales = () => {
    if (liveEstimatedCashSales.value === null || liveEstimatedCashSales.value < 0) {
        alert(t('error_invalid_estimated_sales', 'Please enter a valid closing cash count before syncing to Daily Sales.'));
        return;
    }

    if (confirm(t('confirm_sync_sales', `Sync estimated cash sales (৳${liveEstimatedCashSales.value}) to today's Daily Sales record? This will update cash sales and recompute total sales.`))) {
        router.post('/cash-reconciliation/sync-sales', {
            date: props.data.date,
            estimated_cash_sales: liveEstimatedCashSales.value,
        }, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppLayout :title="t('cash_reconciliation', 'Daily Cash Reconciliation')">
        <Head :title="t('cash_reconciliation', 'Daily Cash Reconciliation') + ' - Royal Nest'" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
            <!-- Header & Date Bar -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg">
                <div class="flex items-center space-x-3.5">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center text-slate-950 shadow-md shadow-amber-500/20">
                        <Calculator class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white flex items-center gap-2">
                            <span>{{ t('cash_reconciliation', 'Daily Cash Reconciliation') }}</span>
                            <span
                                v-if="data.reconciliation?.status === 'confirmed'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30"
                            >
                                <Lock class="w-3 h-3" /> {{ t('confirmed', 'Confirmed & Locked') }}
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-400 border border-amber-500/30"
                            >
                                <Clock class="w-3 h-3" /> {{ t('draft', 'Draft') }}
                            </span>
                        </h1>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ t('cash_recon_desc', 'Audit cash drawer movements, calculate estimated cash sales and synchronize with daily revenue records.') }}
                        </p>
                    </div>
                </div>

                <!-- Date Picker & Quick Actions -->
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 bg-slate-800/80 border border-slate-700 px-3 py-1.5 rounded-xl">
                        <Calendar class="w-4 h-4 text-amber-400" />
                        <input
                            type="date"
                            v-model="currentDate"
                            @change="onDateChange"
                            class="bg-transparent text-sm text-slate-100 font-medium focus:outline-none cursor-pointer"
                        />
                    </div>
                    <button
                        type="button"
                        @click="showFormulaModal = !showFormulaModal"
                        class="p-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl border border-slate-700 text-xs transition flex items-center gap-1.5"
                        :title="t('how_it_works', 'How Formula Works')"
                    >
                        <HelpCircle class="w-4 h-4 text-amber-400" />
                        <span class="hidden sm:inline">{{ t('formula', 'Formula') }}</span>
                    </button>
                </div>
            </div>

            <!-- Formula Info Banner (Toggleable) -->
            <div v-if="showFormulaModal" class="bg-amber-950/30 border border-amber-500/30 rounded-2xl p-4 text-sm text-amber-200 space-y-2">
                <div class="font-bold flex items-center gap-2 text-amber-300">
                    <Info class="w-4 h-4" />
                    <span>{{ t('reconciliation_formula_title', 'Core Cash Sales Estimation Formulas') }}</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                    <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-800 font-mono">
                        <div class="text-slate-400 text-[11px] mb-1">Standard Method (Using Opening Cash):</div>
                        <span class="text-emerald-300 font-semibold">Estimated Cash Sales</span> = Closing Cash − Opening Cash − Other Receipts + Cash Payments
                    </div>
                    <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-800 font-mono">
                        <div class="text-slate-400 text-[11px] mb-1">Previous-Day Carryforward Method:</div>
                        <span class="text-emerald-300 font-semibold">Estimated Cash Sales</span> = Today's Closing Cash − Yesterday's Closing Cash − Other Receipts + Cash Payments
                    </div>
                </div>
                <p class="text-[11px] text-amber-300/80">
                    * Cash Payments include all cash purchases, kitchen expenses, cash salaries, and supplier dues paid from the physical cash drawer. Bank transfers and digital wallets (bKash/Nagad) are isolated and do not distort physical drawer cash.
                </p>
            </div>

            <!-- Warning Banners -->
            <div v-if="activeWarnings.length > 0" class="space-y-2">
                <div
                    v-for="(warn, idx) in activeWarnings"
                    :key="idx"
                    class="bg-amber-500/10 border border-amber-500/30 text-amber-300 px-4 py-3 rounded-xl flex items-start gap-3 text-sm shadow-sm"
                >
                    <AlertTriangle class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" />
                    <div class="flex-1">{{ warn }}</div>
                </div>
            </div>

            <!-- Main Calculation Dashboard & Drawer Inputs -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left 7 Cols: Cash Count & Form Inputs -->
                <div class="lg:col-span-7 space-y-5">
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-md space-y-5">
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <Wallet class="w-5 h-5 text-amber-400" />
                            <span>{{ t('cash_drawer_counts', 'Physical Cash Drawer Entries') }}</span>
                        </h2>

                        <!-- Opening Cash Row -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1.5">
                                    {{ t('opening_cash', 'Today\'s Opening Cash') }} (৳) *
                                </label>
                                <div class="relative">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model.number="form.opening_cash"
                                        placeholder="0.00"
                                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-white font-mono text-base focus:border-amber-500 focus:outline-none"
                                    />
                                    <span class="absolute right-3 top-3 text-xs text-slate-500 font-mono">BDT</span>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
                                    <span>{{ t('yesterday_closing', 'Yesterday\'s Closing Cash') }}:</span>
                                    <button
                                        type="button"
                                        @click="form.opening_cash = data.yesterday_closing_cash"
                                        class="text-amber-400 hover:underline font-mono text-xs"
                                    >
                                        ৳{{ Number(data.yesterday_closing_cash).toFixed(2) }} ({{ t('use', 'Use') }})
                                    </button>
                                </p>
                            </div>

                            <!-- Counted Closing Cash Row -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1.5 flex items-center justify-between">
                                    <span>{{ t('counted_closing_cash', 'Physically Counted Closing Cash') }} (৳) *</span>
                                    <span class="text-[10px] text-emerald-400 font-normal">{{ t('night_drawer_count', 'End of Shift Count') }}</span>
                                </label>
                                <div class="relative">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model.number="form.counted_closing_cash"
                                        placeholder="0.00"
                                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-emerald-400 font-mono text-base font-bold focus:border-emerald-500 focus:outline-none"
                                    />
                                    <span class="absolute right-3 top-3 text-xs text-slate-500 font-mono">BDT</span>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-1">
                                    {{ t('counted_closing_cash_hint', 'Total cash physically in the drawer tonight.') }}
                                </p>
                            </div>
                        </div>

                        <!-- Cash Movements Breakdown Bar -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-800/80">
                            <!-- Today Cash Payments / Outflows -->
                            <div class="bg-slate-950 p-4 rounded-xl border border-slate-800/80 relative">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-semibold text-rose-300 flex items-center gap-1.5">
                                        <ArrowUpRight class="w-4 h-4 text-rose-400" />
                                        {{ t('today_cash_payments', 'Today\'s Cash Payments') }}
                                    </span>
                                    <button
                                        type="button"
                                        @click="showWithdrawals = !showWithdrawals"
                                        class="text-[11px] text-slate-400 hover:text-slate-200 flex items-center gap-0.5"
                                    >
                                        <span>{{ data.payments_breakdown?.length || 0 }} items</span>
                                        <component :is="showWithdrawals ? ChevronUp : ChevronDown" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                                <div class="text-xl font-bold font-mono text-rose-400">
                                    ৳{{ Number(form.today_cash_payments).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">
                                    {{ t('cash_expenses_purchases_salaries', 'Purchases, expenses & salaries from cash') }}
                                </div>

                                <!-- Collapsible list of payments -->
                                <div v-if="showWithdrawals" class="mt-3 pt-3 border-t border-slate-800 max-h-48 overflow-y-auto space-y-1.5">
                                    <div
                                        v-for="(item, i) in data.payments_breakdown"
                                        :key="i"
                                        class="flex items-center justify-between text-xs py-1 px-2 rounded bg-slate-900/90 text-slate-300"
                                    >
                                        <div class="truncate max-w-[180px]">
                                            <span class="text-slate-200 font-medium">{{ item.description }}</span>
                                            <span class="text-[10px] text-slate-500 block">{{ item.time }}</span>
                                        </div>
                                        <span class="font-mono text-rose-400 font-semibold shrink-0">৳{{ Number(item.amount).toFixed(2) }}</span>
                                    </div>
                                    <div v-if="!data.payments_breakdown?.length" class="text-xs text-slate-500 text-center py-2">
                                        {{ t('no_cash_payments_today', 'No cash payments recorded today.') }}
                                    </div>
                                </div>
                            </div>

                            <!-- Other Cash Receipts / Inflows -->
                            <div class="bg-slate-950 p-4 rounded-xl border border-slate-800/80 relative">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-semibold text-emerald-300 flex items-center gap-1.5">
                                        <ArrowDownLeft class="w-4 h-4 text-emerald-400" />
                                        {{ t('other_cash_receipts', 'Other Cash Receipts') }}
                                    </span>
                                    <button
                                        type="button"
                                        @click="showReceipts = !showReceipts"
                                        class="text-[11px] text-slate-400 hover:text-slate-200 flex items-center gap-0.5"
                                    >
                                        <span>{{ data.receipts_breakdown?.length || 0 }} items</span>
                                        <component :is="showReceipts ? ChevronUp : ChevronDown" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                                <div class="text-xl font-bold font-mono text-emerald-400">
                                    ৳{{ Number(form.other_cash_receipts).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">
                                    {{ t('capital_transfers_non_sales', 'Transfers-in or owner capital (non-sales)') }}
                                </div>

                                <!-- Collapsible list of other receipts -->
                                <div v-if="showReceipts" class="mt-3 pt-3 border-t border-slate-800 max-h-48 overflow-y-auto space-y-1.5">
                                    <div
                                        v-for="(item, i) in data.receipts_breakdown"
                                        :key="i"
                                        class="flex items-center justify-between text-xs py-1 px-2 rounded bg-slate-900/90 text-slate-300"
                                    >
                                        <div class="truncate max-w-[180px]">
                                            <span class="text-slate-200 font-medium">{{ item.description }}</span>
                                            <span class="text-[10px] text-slate-500 block">{{ item.time }}</span>
                                        </div>
                                        <span class="font-mono text-emerald-400 font-semibold shrink-0">৳{{ Number(item.amount).toFixed(2) }}</span>
                                    </div>
                                    <div v-if="!data.receipts_breakdown?.length" class="text-xs text-slate-500 text-center py-2">
                                        {{ t('no_other_receipts_today', 'No other cash receipts today.') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes Textarea -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">
                                {{ t('reconciliation_notes', 'Reconciliation Notes & Discrepancy Reason') }}
                            </label>
                            <textarea
                                v-model="form.notes"
                                rows="2"
                                placeholder="E.g., Opening cash had ৳500 small notes; evening rush had ৳20 cash tip change..."
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-white text-xs focus:border-amber-500 focus:outline-none"
                            ></textarea>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-800">
                            <div class="text-xs text-slate-400 flex items-center gap-1.5">
                                <Clock class="w-3.5 h-3.5 text-slate-500" />
                                <span>{{ t('last_updated', 'Last Saved') }}: {{ data.reconciliation?.updated_at ? new Date(data.reconciliation.updated_at).toLocaleTimeString() : 'Not yet saved' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="submitReconciliation('draft')"
                                    :disabled="form.processing"
                                    class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold border border-slate-700 transition flex items-center gap-1.5 disabled:opacity-50"
                                >
                                    <Save class="w-3.5 h-3.5 text-slate-400" />
                                    <span>{{ t('save_draft', 'Save Draft') }}</span>
                                </button>
                                <button
                                    type="button"
                                    @click="submitReconciliation('confirmed')"
                                    :disabled="form.processing || liveEstimatedCashSales === null"
                                    class="px-5 py-2 bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 text-slate-950 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-emerald-600/20 disabled:opacity-50"
                                >
                                    <CheckCircle2 class="w-4 h-4 text-slate-950" />
                                    <span>{{ t('confirm_lock', 'Confirm & Lock') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right 5 Cols: Prominent Calculated Results & Sync -->
                <div class="lg:col-span-5 space-y-5">
                    <!-- Prominent Estimated Cash Sales Box -->
                    <div class="bg-gradient-to-br from-slate-900 to-slate-950 border-2 border-amber-500/40 rounded-2xl p-6 shadow-xl relative overflow-hidden">
                        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>

                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs uppercase tracking-wider font-bold text-amber-400 flex items-center gap-1.5">
                                <Banknote class="w-4 h-4" />
                                {{ t('estimated_cash_sales', 'Estimated Cash Sales') }}
                            </span>
                            <span class="text-[10px] text-slate-400 font-mono">
                                Formula Result
                            </span>
                        </div>

                        <div class="my-4">
                            <div v-if="liveEstimatedCashSales !== null" class="flex items-baseline gap-2">
                                <span class="text-4xl font-extrabold font-mono text-white tracking-tight">
                                    ৳{{ Number(liveEstimatedCashSales).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </span>
                            </div>
                            <div v-else class="text-2xl font-bold text-slate-500 italic py-1">
                                {{ t('enter_closing_to_calculate', 'Count drawer closing cash to calculate') }}
                            </div>

                            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                                = (Closing Cash ৳{{ form.counted_closing_cash || 0 }}) − (Opening Cash ৳{{ form.opening_cash || 0 }}) − (Other ৳{{ form.other_cash_receipts || 0 }}) + (Payments ৳{{ form.today_cash_payments || 0 }})
                            </p>
                        </div>

                        <!-- Revenue Composition -->
                        <div class="pt-4 border-t border-slate-800 space-y-2 text-xs">
                            <div class="flex items-center justify-between text-slate-400">
                                <span>{{ t('recorded_non_cash_sales', 'Recorded Digital / Non-Cash Sales') }}:</span>
                                <span class="font-mono font-semibold text-slate-200">৳{{ Number(form.recorded_non_cash_sales).toFixed(2) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-300 font-medium">
                                <span>{{ t('estimated_total_sales', 'Estimated Total Daily Sales') }}:</span>
                                <span class="font-mono text-sm font-bold text-emerald-400">
                                    ৳{{ liveEstimatedTotalSales !== null ? Number(liveEstimatedTotalSales).toFixed(2) : '—' }}
                                </span>
                            </div>
                        </div>

                        <!-- Comparison With Recorded POS Cash Sales -->
                        <div class="mt-4 p-3 bg-slate-950/80 rounded-xl border border-slate-800 text-xs space-y-1.5">
                            <div class="flex items-center justify-between text-slate-400">
                                <span>{{ t('entered_pos_cash_sales', 'Entered POS Cash Sales') }}:</span>
                                <span class="font-mono font-semibold text-slate-200">৳{{ Number(data.today_recorded_cash_sales).toFixed(2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">{{ t('reconciliation_variance', 'Reconciliation Variance') }}:</span>
                                <span
                                    v-if="varianceWithRecordedCash !== null"
                                    :class="[
                                        'font-mono font-bold',
                                        varianceWithRecordedCash === 0 ? 'text-emerald-400' : (varianceWithRecordedCash > 0 ? 'text-amber-400' : 'text-rose-400')
                                    ]"
                                >
                                    {{ varianceWithRecordedCash > 0 ? '+' : '' }}৳{{ Number(varianceWithRecordedCash).toFixed(2) }}
                                </span>
                                <span v-else class="text-slate-500">—</span>
                            </div>
                        </div>

                        <!-- 1-Click Sync Button -->
                        <div class="mt-5">
                            <button
                                type="button"
                                @click="syncToDailySales"
                                :disabled="liveEstimatedCashSales === null || liveEstimatedCashSales < 0"
                                class="w-full py-3 px-4 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-xs rounded-xl shadow-lg shadow-amber-500/20 transition flex items-center justify-center gap-2 active:scale-98 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <RefreshCw class="w-4 h-4 text-slate-950" />
                                <span>{{ t('sync_to_daily_sales', 'Sync Estimated Cash Sales to Daily Sales') }}</span>
                            </button>
                            <p class="text-[11px] text-slate-400 text-center mt-2">
                                {{ t('sync_hint', 'Transfers estimated cash sales to the Daily Sales module without altering digital sales.') }}
                            </p>
                        </div>
                    </div>

                    <!-- Quick Guide Card -->
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm text-xs text-slate-400 space-y-3">
                        <div class="font-bold text-slate-200 flex items-center gap-1.5">
                            <Info class="w-4 h-4 text-amber-400" />
                            <span>{{ t('daily_routine_guide', 'Cashier Daily Closing Routine') }}</span>
                        </div>
                        <ol class="list-decimal list-inside space-y-1.5 text-slate-300">
                            <li>{{ t('routine_step_1', 'Confirm the morning opening cash before taking orders.') }}</li>
                            <li>{{ t('routine_step_2', 'Record any purchases or vendor payouts using the Daily Cost Entry module.') }}</li>
                            <li>{{ t('routine_step_3', 'At night, physically count all cash in the drawer and enter it above.') }}</li>
                            <li>{{ t('routine_step_4', 'Review the automatically estimated cash sales.') }}</li>
                            <li>{{ t('routine_step_5', 'Click "Sync to Daily Sales" to reconcile POS revenue, then Confirm & Lock.') }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

