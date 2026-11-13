<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    Calculator, 
    Flame, 
    ShoppingBag, 
    Users, 
    Receipt, 
    Plus, 
    Trash2, 
    Check, 
    Search, 
    Calendar, 
    Sparkles,
    TrendingUp,
    TrendingDown,
    AlertCircle,
    ArrowRight,
    Layers,
    Boxes
} from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    date: String,
    products: Array,
    salesData: Object,
    recordedConsumptions: Array,
    alreadyConsumedTotal: Number,
    operatingExpenses: Array,
    operatingExpensesTotal: Number,
    staffSalaryCost: Number,
    consumablesAllocated: Number,
    wastageCost: Number,
    accounts: Array,
});

const { t } = useI18n();

const selectedDate = ref(props.date);
const productSearch = ref('');

// Dynamic material usage items for today
const usageItems = ref([
    { product_id: '', quantity_used: '', unit_cost: 0, unit: '', name: '', available_stock: 0 }
]);

// Sales form
const sales = ref({
    cash_sales: props.salesData.cash_sales || 0,
    bkash_sales: props.salesData.bkash_sales || 0,
    nagad_sales: props.salesData.nagad_sales || 0,
    card_bank_sales: props.salesData.card_bank_sales || 0,
    other_sales: props.salesData.other_sales || 0,
    total_sales: props.salesData.total_sales || 0,
});

const notes = ref('');

// Switch date
const changeDate = () => {
    router.get('/calculator', { date: selectedDate.value }, { preserveState: false });
};

// Filtered products for dropdown
const filteredProducts = computed(() => {
    if (!productSearch.value) return props.products;
    const q = productSearch.value.toLowerCase();
    return props.products.filter(p => 
        p.name.toLowerCase().includes(q) || 
        p.code?.toLowerCase().includes(q) ||
        p.category_name?.toLowerCase().includes(q)
    );
});

// On product selection in row
const onSelectProduct = (index) => {
    const item = usageItems.value[index];
    const product = props.products.find(p => p.id === item.product_id);
    if (product) {
        item.name = product.name;
        item.unit = product.base_unit;
        item.unit_cost = product.fifo_unit_cost;
        item.available_stock = product.current_stock;
    }
};

const addItemRow = () => {
    usageItems.value.push({
        product_id: '',
        quantity_used: '',
        unit_cost: 0,
        unit: '',
        name: '',
        available_stock: 0
    });
};

const removeItemRow = (index) => {
    if (usageItems.value.length > 1) {
        usageItems.value.splice(index, 1);
    } else {
        usageItems.value[0] = { product_id: '', quantity_used: '', unit_cost: 0, unit: '', name: '', available_stock: 0 };
    }
};

// Live Computed Totals
const computedSalesTotal = computed(() => {
    const sum = (parseFloat(sales.value.cash_sales) || 0) +
                (parseFloat(sales.value.bkash_sales) || 0) +
                (parseFloat(sales.value.nagad_sales) || 0) +
                (parseFloat(sales.value.card_bank_sales) || 0) +
                (parseFloat(sales.value.other_sales) || 0);
    return Number(sum.toFixed(2));
});

const computedNewUsageCost = computed(() => {
    let total = 0;
    for (const item of usageItems.value) {
        const q = parseFloat(item.quantity_used) || 0;
        const c = parseFloat(item.unit_cost) || 0;
        total += (q * c);
    }
    return Number(total.toFixed(2));
});

const totalMaterialCostToday = computed(() => {
    return Number((computedNewUsageCost.value + props.alreadyConsumedTotal + props.consumablesAllocated).toFixed(2));
});

const grossProfit = computed(() => {
    return Number((computedSalesTotal.value - totalMaterialCostToday.value).toFixed(2));
});

const totalOperatingExpenses = computed(() => {
    return Number((props.operatingExpensesTotal + props.staffSalaryCost + props.wastageCost).toFixed(2));
});

const netProfit = computed(() => {
    return Number((grossProfit.value - totalOperatingExpenses.value).toFixed(2));
});

const profitMargin = computed(() => {
    if (computedSalesTotal.value <= 0) return 0;
    return Number(((netProfit.value / computedSalesTotal.value) * 100).toFixed(1));
});

// Form submission
const isSubmitting = ref(false);

const submit = () => {
    isSubmitting.value = true;
    
    // Filter only valid items
    const validItems = usageItems.value
        .filter(item => item.product_id && parseFloat(item.quantity_used) > 0)
        .map(item => ({
            product_id: item.product_id,
            quantity_used: parseFloat(item.quantity_used)
        }));

    router.post('/calculator', {
        date: selectedDate.value,
        sales: {
            total_sales: computedSalesTotal.value,
            cash_sales: parseFloat(sales.value.cash_sales) || 0,
            bkash_sales: parseFloat(sales.value.bkash_sales) || 0,
            nagad_sales: parseFloat(sales.value.nagad_sales) || 0,
            card_bank_sales: parseFloat(sales.value.card_bank_sales) || 0,
            other_sales: parseFloat(sales.value.other_sales) || 0,
        },
        items: validItems,
        notes: notes.value
    }, {
        onSuccess: () => {
            isSubmitting.value = false;
            usageItems.value = [{ product_id: '', quantity_used: '', unit_cost: 0, unit: '', name: '', available_stock: 0 }];
            notes.value = '';
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};
</script>

<template>
    <AppLayout title="Daily Profit & Loss Calculator">
        <Head title="Daily Profit & Loss Calculator — Royal Nest" />

        <!-- Top Header & Date Navigation -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
            <div>
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
                        <Calculator class="w-5 h-5" />
                    </div>
                    <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight">
                        {{ t('daily_pnl_calculator', 'দৈনিক লাভ-ক্ষতি ক্যালকুলেটর') }}
                    </h1>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">
                    {{ t('calculator_tagline', 'প্রতিদিনের কাঁচামাল (মাংস, তেল ইত্যাদি) ব্যবহার এন্ট্রি করুন এবং তাৎক্ষণিক নিট লাভ দেখুন।') }}
                </p>
            </div>

            <!-- Date Selector Pill -->
            <div class="flex items-center space-x-2 bg-slate-900 border border-slate-800 rounded-xl p-1.5 self-start sm:self-auto">
                <Calendar class="w-4 h-4 text-amber-400 ml-1.5" />
                <input
                    type="date"
                    v-model="selectedDate"
                    @change="changeDate"
                    class="bg-transparent border-none text-xs text-white font-mono focus:outline-none cursor-pointer"
                />
            </div>
        </div>

        <!-- LIVE PROFIT SUMMARY HERO CARD (Mobile-First Sticky/Top Overview) -->
        <div class="bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 border border-slate-800 rounded-2xl p-4 sm:p-6 mb-6 shadow-xl relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-36 h-36 bg-amber-500/5 rounded-full blur-2xl"></div>

            <div class="flex items-center justify-between mb-3 border-b border-slate-800/80 pb-3">
                <div class="flex items-center space-x-2">
                    <Sparkles class="w-4 h-4 text-amber-400" />
                    <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">
                        {{ t('live_profit_preview', 'আজকের লাইভ লাভ-ক্ষতি প্রিভিউ') }}
                    </span>
                </div>
                <div class="flex items-center space-x-1.5">
                    <span class="text-xs text-slate-400">মার্জিন:</span>
                    <span 
                        :class="[
                            'text-xs font-mono font-bold px-2 py-0.5 rounded-md',
                            profitMargin >= 20 ? 'bg-emerald-500/20 text-emerald-300' :
                            profitMargin > 0 ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300'
                        ]"
                    >
                        {{ profitMargin }}%
                    </span>
                </div>
            </div>

            <!-- 4 Metric Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- 1. Total Sales -->
                <div class="bg-slate-950/70 border border-slate-800/80 rounded-xl p-3">
                    <span class="text-[11px] text-slate-400 block mb-0.5">{{ t('sales_revenue', 'মোট বিক্রয়') }}</span>
                    <span class="text-lg sm:text-xl font-bold font-mono text-emerald-400">৳{{ computedSalesTotal.toLocaleString() }}</span>
                    <span class="block text-[10px] text-slate-500 mt-0.5">{{ sales.cash_sales > 0 ? 'ক্যাশ + ডিজিটাল' : 'এন্ট্রি করুন' }}</span>
                </div>

                <!-- 2. Cost of Materials (COGS) -->
                <div class="bg-slate-950/70 border border-slate-800/80 rounded-xl p-3">
                    <span class="text-[11px] text-slate-400 block mb-0.5">{{ t('cost_of_materials', 'কাঁচামাল খরচ (COGS)') }}</span>
                    <span class="text-lg sm:text-xl font-bold font-mono text-amber-400">৳{{ totalMaterialCostToday.toLocaleString() }}</span>
                    <span class="block text-[10px] text-slate-500 mt-0.5">FIFO ব্যাচ মূল্য অনুযায়ী</span>
                </div>

                <!-- 3. Total Operating Costs -->
                <div class="bg-slate-950/70 border border-slate-800/80 rounded-xl p-3">
                    <span class="text-[11px] text-slate-400 block mb-0.5">{{ t('operating_expenses', 'পরিচালন ও বেতন') }}</span>
                    <span class="text-lg sm:text-xl font-bold font-mono text-rose-300">৳{{ totalOperatingExpenses.toLocaleString() }}</span>
                    <span class="block text-[10px] text-slate-500 mt-0.5">বেতন: ৳{{ staffSalaryCost }} • বিল: ৳{{ operatingExpensesTotal }}</span>
                </div>

                <!-- 4. True Net Profit -->
                <div 
                    :class="[
                        'border rounded-xl p-3 flex flex-col justify-between',
                        netProfit >= 0 
                            ? 'bg-emerald-950/30 border-emerald-500/40 text-emerald-200' 
                            : 'bg-rose-950/30 border-rose-500/40 text-rose-200'
                    ]"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider block">
                            {{ netProfit >= 0 ? t('net_profit', 'প্রকৃত নিট লাভ') : 'প্রকৃত নিট ক্ষতি' }}
                        </span>
                        <component :is="netProfit >= 0 ? TrendingUp : TrendingDown" class="w-4 h-4" />
                    </div>
                    <span class="text-xl sm:text-2xl font-black font-mono mt-1">৳{{ netProfit.toLocaleString() }}</span>
                    <span class="text-[10px] opacity-80 mt-0.5">সব খরচ বাদ দিয়ে</span>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- SECTION 1: TODAY'S SALES ENTRY -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-5">
                <div class="flex items-center justify-between mb-3 border-b border-slate-800/80 pb-2.5">
                    <div class="flex items-center space-x-2">
                        <ShoppingBag class="w-4 h-4 text-emerald-400" />
                        <h2 class="text-xs sm:text-sm font-bold text-white uppercase tracking-wider">
                            ১. আজকের বিক্রয় এন্ট্রি (Today's Sales)
                        </h2>
                    </div>
                    <span class="text-xs font-mono font-bold text-emerald-400">
                        মোট: ৳{{ computedSalesTotal.toLocaleString() }}
                    </span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs">
                    <div>
                        <label class="block text-slate-400 font-medium mb-1">ক্যাশ বিক্রয় (Cash)</label>
                        <input
                            type="number"
                            step="0.01"
                            v-model="sales.cash_sales"
                            placeholder="0.00"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-slate-400 font-medium mb-1">বিকাশ (bKash)</label>
                        <input
                            type="number"
                            step="0.01"
                            v-model="sales.bkash_sales"
                            placeholder="0.00"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-slate-400 font-medium mb-1">নগদ (Nagad)</label>
                        <input
                            type="number"
                            step="0.01"
                            v-model="sales.nagad_sales"
                            placeholder="0.00"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-slate-400 font-medium mb-1">কার্ড / ব্যাংক (Card)</label>
                        <input
                            type="number"
                            step="0.01"
                            v-model="sales.card_bank_sales"
                            placeholder="0.00"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-slate-400 font-medium mb-1">অন্যান্য (Other)</label>
                        <input
                            type="number"
                            step="0.01"
                            v-model="sales.other_sales"
                            placeholder="0.00"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 2: RAW MATERIALS USED TODAY (THE CORE CALCULATOR FEATURE) -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3 border-b border-slate-800/80 pb-2.5">
                    <div class="flex items-center space-x-2">
                        <Flame class="w-4 h-4 text-amber-400" />
                        <div>
                            <h2 class="text-xs sm:text-sm font-bold text-white uppercase tracking-wider">
                                ২. আজ ব্যবহৃত কাঁচামাল (মাংস, তেল, চাল ইত্যাদি)
                            </h2>
                            <p class="text-[11px] text-slate-400">
                                আজ কত কেজি মাংস বা লিটার তেল ব্যবহার হয়েছে লিখুন। সিস্টেম স্বয়ংক্রিয়ভাবে FIFO ক্রয়মূল্য বসিয়ে খরচ বের করবে।
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="addItemRow"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-amber-300 text-xs font-bold transition self-start sm:self-auto cursor-pointer"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>আইটেম যুক্ত করুন</span>
                    </button>
                </div>

                <!-- Input Rows -->
                <div class="space-y-3">
                    <div 
                        v-for="(item, idx) in usageItems" 
                        :key="'usage-' + idx"
                        class="bg-slate-950 border border-slate-800/90 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center gap-2.5 text-xs"
                    >
                        <!-- Product Selection -->
                        <div class="flex-1">
                            <label class="block text-slate-400 font-medium mb-1 sm:hidden">পণ্য / কাঁচামাল</label>
                            <select
                                v-model="item.product_id"
                                @change="onSelectProduct(idx)"
                                class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option value="" disabled>কাঁচামাল নির্বাচন করুন (যেমন: গরুর মাংস, তেল)</option>
                                <option v-for="p in products" :key="p.id" :value="p.id">
                                    {{ p.name }} (মজুদ: {{ p.current_stock }} {{ p.base_unit }} • FIFO: ৳{{ p.fifo_unit_cost }}/{{ p.base_unit }})
                                </option>
                            </select>
                        </div>

                        <!-- Quantity Used -->
                        <div class="w-full sm:w-36">
                            <label class="block text-slate-400 font-medium mb-1 sm:hidden">ব্যবহৃত পরিমাণ</label>
                            <div class="relative">
                                <input
                                    type="number"
                                    step="0.01"
                                    v-model="item.quantity_used"
                                    placeholder="পরিমাণ"
                                    class="w-full bg-slate-900 border border-slate-800 rounded-lg pl-3 pr-10 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                                />
                                <span class="absolute right-2.5 top-2 text-[11px] text-slate-400 font-medium">
                                    {{ item.unit || 'একক' }}
                                </span>
                            </div>
                        </div>

                        <!-- Live Row Cost Calculation -->
                        <div class="w-full sm:w-36 text-right sm:text-center bg-slate-900/60 sm:bg-transparent p-2 sm:p-0 rounded-lg flex items-center justify-between sm:justify-center">
                            <span class="text-slate-400 sm:hidden">খরচ:</span>
                            <span class="font-mono font-bold text-amber-300 text-sm">
                                ৳{{ ((parseFloat(item.quantity_used) || 0) * (parseFloat(item.unit_cost) || 0)).toFixed(2) }}
                            </span>
                        </div>

                        <!-- Remove Row -->
                        <div class="flex justify-end sm:justify-center">
                            <button
                                type="button"
                                @click="removeItemRow(idx)"
                                class="p-2 text-slate-500 hover:text-rose-400 rounded-lg transition"
                                title="মুছুন"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Already Recorded Kitchen Usage Today -->
                <div v-if="recordedConsumptions.length > 0" class="mt-4 pt-3 border-t border-slate-800/80">
                    <span class="text-[11px] font-bold text-slate-400 block mb-2">
                        আজ পূর্বে রেকর্ডকৃত রান্নাঘরের খরচ: ৳{{ alreadyConsumedTotal.toLocaleString() }}
                    </span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div 
                            v-for="rec in recordedConsumptions" 
                            :key="'rec-' + rec.id"
                            class="bg-slate-950/60 border border-slate-800/60 rounded-lg p-2.5 flex items-center justify-between text-xs"
                        >
                            <div>
                                <span class="font-medium text-slate-200 block">
                                    {{ rec.items?.map(i => `${i.product?.name} (${i.quantity_used} ${i.unit})`).join(', ') }}
                                </span>
                                <span class="text-[10px] text-slate-500 font-mono">{{ rec.notes || 'রান্নাঘরের ব্যবহার' }}</span>
                            </div>
                            <span class="font-mono font-bold text-amber-400">৳{{ rec.total_cost }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: STAFF & OPERATING COSTS (AUTO-POPULATED) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Staff Salary Accrual Card -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 text-xs">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center space-x-2">
                            <Users class="w-4 h-4 text-amber-400" />
                            <h3 class="font-bold text-white">৩. আজকের কর্মী বেতন (হাজিরা অনুযায়ী)</h3>
                        </div>
                        <span class="font-mono font-bold text-rose-300">৳{{ staffSalaryCost.toLocaleString() }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400">
                        আজকের দৈনিক ও মাসিক কর্মীদের হাজিরা অনুযায়ী বেতন স্বয়ংক্রিয়ভাবে ধার্য করা হয়েছে।
                    </p>
                </div>

                <!-- Operating Expenses & Wastage Card -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 text-xs">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center space-x-2">
                            <Receipt class="w-4 h-4 text-amber-400" />
                            <h3 class="font-bold text-white">৪. দোকান বিল ও অপচয় ক্ষতি</h3>
                        </div>
                        <span class="font-mono font-bold text-rose-300">৳{{ (operatingExpensesTotal + wastageCost).toLocaleString() }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400">
                        আজকের বিদ্যুৎ, গ্যাস, যাতায়াত বিল (৳{{ operatingExpensesTotal }}) ও অপচয় (৳{{ wastageCost }})।
                    </p>
                </div>
            </div>

            <!-- Notes -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 text-xs">
                <label class="block text-slate-400 font-medium mb-1">অতিরিক্ত মন্তব্য / নোট (ঐচ্ছিক)</label>
                <input
                    type="text"
                    v-model="notes"
                    placeholder="যেমন: দুপুরের স্পেশাল ক্যাটারিং বা অতিরিক্ত মাংস ব্যবহার..."
                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                />
            </div>

            <!-- FINAL 1-CLICK SAVE & SYNC BUTTON -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
                <div class="text-xs text-slate-400 text-center sm:text-left">
                    "সংরক্ষণ ও সিঙ্ক করুন" চাপলে কাঁচামাল ইনভেন্টরি থেকে FIFO ব্যাচে মাইনাস হবে এবং বিক্রয় রেকর্ড হবে।
                </div>

                <button
                    type="submit"
                    :disabled="isSubmitting"
                    class="w-full sm:w-auto px-6 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-sm shadow-lg shadow-amber-500/20 flex items-center justify-center space-x-2 transition active:scale-95 cursor-pointer disabled:opacity-50"
                >
                    <Check class="w-4 h-4" />
                    <span>{{ isSubmitting ? 'সংরক্ষণ হচ্ছে...' : 'সংরক্ষণ ও সিঙ্ক করুন (Save & Sync P&L)' }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>

