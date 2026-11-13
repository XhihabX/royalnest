<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    Mic, 
    MicOff, 
    Sparkles, 
    Star, 
    Clock, 
    Flame, 
    Truck, 
    Receipt, 
    Users, 
    Wallet, 
    Plus, 
    Search, 
    Trash2, 
    CheckCircle2, 
    AlertCircle, 
    RotateCcw, 
    ArrowRight,
    Store,
    Layers,
    X,
    Check,
    Download,
    Printer,
    FileText,
    Calendar,
    Ban
} from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import UniversalVoiceModal from '@/Components/Voice/UniversalVoiceModal.vue';
import VoiceInputButton from '@/Components/Voice/VoiceInputButton.vue';

const props = defineProps({
    templates: Object,
    products: Array,
    categories: Array,
    suppliers: Array,
    employees: Array,
    accounts: Array,
    recentPurchases: Array,
    recentExpenses: Array,
    dailySummary: Object,
    selectedDate: String,
    filters: Object,
});

const { t } = useI18n();
const { 
    isListening, 
    transcript, 
    interimTranscript, 
    voiceLanguage, 
    errorMessage: voiceError, 
    isSupported: voiceSupported, 
    startListening, 
    stopListening, 
    clearVoiceInput, 
    parseCostSpeech 
} = useVoiceCostParser();

const searchShortcut = ref(props.filters.search || '');
const activeTab = ref('purchase'); // purchase, consumption, expense, salary_payment, salary_advance, supplier_payment, other
const voicePreview = ref(null);
const showVoiceBox = ref(false);

const form = useForm({
    transaction_type: 'purchase',
    transaction_date: props.selectedDate || new Date().toISOString().split('T')[0],
    name: '',
    product_id: '',
    category_id: '',
    supplier_id: '',
    employee_id: '',
    expense_category: 'electricity',
    quantity: 1,
    unit: 'kg',
    unit_price: 0,
    amount: 0,
    paid_amount: 0,
    payment_account_id: props.accounts[0]?.id || '',
    is_purchase_and_consume: false,
    recover_advances: true,
    salary_month: new Date().toISOString().slice(0, 7),
    notes: '',
    save_as_template: false,
    template_id: null,
    is_favorite: false,
    allocations: [],
});

// Sync form transaction type with active tab
watch(activeTab, (newTab) => {
    form.transaction_type = newTab;
});

// Auto calculate Total Amount = Qty * Unit Price for purchases
const computedTotal = computed(() => {
    if (['purchase', 'consumption'].includes(form.transaction_type)) {
        const q = parseFloat(form.quantity) || 0;
        const p = parseFloat(form.unit_price) || 0;
        return Number((q * p).toFixed(2));
    }
    return Number((parseFloat(form.amount) || 0).toFixed(2));
});

// Auto-sync computed total to paid_amount by default
watch(computedTotal, (newTotal) => {
    if (form.transaction_type === 'purchase') {
        form.paid_amount = newTotal;
    } else {
        form.amount = newTotal;
    }
});

// Voice Input: when transcript finishes, parse speech into form fields
watch(transcript, (newText) => {
    if (newText) {
        const parsed = parseCostSpeech(newText);
        voicePreview.value = parsed;
    }
});

const onVoiceApply = (parsed) => {
    if (parsed.item_name) form.name = parsed.item_name;
    if (parsed.product_id) {
        form.product_id = parsed.product_id;
        activeTab.value = 'purchase';
    }
    if (parsed.supplier_id) form.supplier_id = parsed.supplier_id;
    if (parsed.employee_id) {
        form.employee_id = parsed.employee_id;
        activeTab.value = 'salary_payment';
    }
    if (parsed.quantity) form.quantity = parsed.quantity;
    if (parsed.unit) form.unit = parsed.unit;
    if (parsed.amount) {
        form.amount = parsed.amount;
        form.paid_amount = parsed.amount;
    }
    if (parsed.note) form.notes = parsed.note;
};

const handleDateChange = (newDate) => {
    router.get('/daily-costs', { date: newDate }, { preserveState: true, preserveScroll: true });
};

const handleVoidPurchase = (purchase) => {
    const reason = prompt(`ইনভয়েস #${purchase.invoice_no} বাতিল (Void) করার কারণ লিখুন:`);
    if (reason && reason.trim()) {
        router.post(`/purchases/${purchase.id}/void`, {
            reason: reason.trim(),
            allow_partial_return: true,
        });
    }
};

const handleVoidExpense = (expense) => {
    const reason = prompt(`খরচ #${expense.id} (${expense.category}) বাতিল (Void) করার কারণ লিখুন:`);
    if (reason && reason.trim()) {
        router.post(`/expenses/${expense.id}/void`, {
            reason: reason.trim(),
        });
    }
};

const printSummary = () => {
    window.print();
};

// Apply saved shortcut template to the form
const selectShortcut = (tpl) => {
    activeTab.value = tpl.transaction_type;
    form.transaction_type = tpl.transaction_type;
    form.template_id = tpl.id;
    form.name = tpl.name;
    form.product_id = tpl.product_id || '';
    form.category_id = tpl.category_id || '';
    form.supplier_id = tpl.supplier_id || '';
    form.employee_id = tpl.employee_id || '';
    form.expense_category = tpl.expense_category || 'misc';
    form.unit = tpl.default_unit || 'kg';
    form.unit_price = Number(tpl.last_unit_price) || 0;
    form.quantity = 1;
    if (tpl.default_payment_account_id) {
        form.payment_account_id = tpl.default_payment_account_id;
    }

    // If product is linked, populate units and rates
    if (tpl.product) {
        form.unit = tpl.product.purchase_unit || tpl.product.base_unit;
        if (!tpl.last_unit_price || tpl.last_unit_price == 0) {
            const mult = tpl.product.unit_conversions?.[0]?.multiplier_to_base || 1;
            form.unit_price = Number((Number(tpl.product.cost_price) * mult).toFixed(2));
        }
    }
};

const onProductSelect = () => {
    const prod = props.products.find(p => p.id === form.product_id);
    if (prod) {
        form.name = prod.name;
        form.unit = prod.purchase_unit || prod.base_unit;
        const mult = prod.unit_conversions?.[0]?.multiplier_to_base || 1;
        form.unit_price = Number((Number(prod.cost_price) * mult).toFixed(2));
        if (prod.supplier_id) form.supplier_id = prod.supplier_id;
        if (prod.category_id) form.category_id = prod.category_id;
    }
};

const selectedEmployeeInfo = computed(() => {
    if (!form.employee_id) return null;
    return props.employees.find(e => e.id === form.employee_id) || null;
});

const selectedSupplierInfo = computed(() => {
    if (!form.supplier_id) return null;
    return props.suppliers.find(s => s.id === form.supplier_id) || null;
});

const onEmployeeSelect = () => {
    const emp = selectedEmployeeInfo.value;
    if (emp) {
        form.name = emp.name;
        if (form.transaction_type === 'salary_payment') {
            if (emp.unpaid_payable && Number(emp.unpaid_payable) > 0) {
                form.amount = Number(emp.unpaid_payable);
                form.paid_amount = Number(emp.unpaid_payable);
            } else if (emp.salary_type === 'daily') {
                form.amount = Number(emp.agreed_salary);
                form.paid_amount = Number(emp.agreed_salary);
            } else if (emp.salary_type === 'monthly') {
                const dailyRate = Number((Number(emp.agreed_salary) / 30).toFixed(2));
                form.amount = dailyRate;
                form.paid_amount = dailyRate;
            }
        }
        // Default to cash drawer account
        const cashAcc = props.accounts.find(a => a.type === 'cash') || props.accounts[0];
        if (cashAcc) form.payment_account_id = cashAcc.id;
    }
};

const onSupplierSelect = () => {
    const sup = selectedSupplierInfo.value;
    if (sup) {
        form.name = sup.name;
        form.allocations = [];
        if (Number(sup.current_balance) > 0) {
            form.amount = Number(sup.current_balance);
        }
    }
};

const setBillAllocation = (purchaseId, maxDue, amount) => {
    const val = Math.min(Number(maxDue), Math.max(0, parseFloat(amount) || 0));
    const idx = form.allocations.findIndex(a => a.purchase_id === purchaseId);
    if (idx >= 0) {
        if (val > 0) {
            form.allocations[idx].amount = val;
        } else {
            form.allocations.splice(idx, 1);
        }
    } else if (val > 0) {
        form.allocations.push({ purchase_id: purchaseId, amount: val });
    }
    // Update total form amount to match sum of allocations if allocations exist
    const sumAlloc = form.allocations.reduce((sum, a) => sum + (parseFloat(a.amount) || 0), 0);
    if (sumAlloc > 0) {
        form.amount = Number(sumAlloc.toFixed(2));
    }
};

const quickWorkerPayout = (type) => {
    activeTab.value = type;
    form.transaction_type = type;
    const cashAcc = props.accounts.find(a => a.type === 'cash') || props.accounts[0];
    if (cashAcc) form.payment_account_id = cashAcc.id;
};

const submit = () => {
    form.post('/daily-costs', {
        onSuccess: () => {
            form.reset('name', 'product_id', 'quantity', 'unit_price', 'amount', 'paid_amount', 'notes', 'save_as_template', 'template_id');
            form.quantity = 1;
            form.paid_amount = 0;
        }
    });
};

const toggleFav = (tplId) => {
    router.post(`/daily-costs/templates/${tplId}/favorite`, {}, { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Smart Daily Cost Entry">
        <Head title="Smart Cost Entry & Voice Typing — Royal Nest" />

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                    <Sparkles class="w-6 h-6 text-amber-400" />
                    <span>{{ t('smart_cost_entry', 'দৈনিক খরচ ও ক্রয় এন্ট্রি (Daily Costs)') }}</span>
                </h1>
                <p class="text-xs text-slate-400 mt-1">
                    ভয়েস টাইপিং, শর্টকাট এবং স্বয়ংক্রিয় হিসাব সমন্বয়
                </p>
            </div>

            <div class="flex items-center space-x-2">
                <VoiceInputButton
                    context-type="costs"
                    title="দৈনিক খরচ ভয়েস টাইপিং"
                    hint='যেমন: "আলু ৫ কেজি ৫০০ টাকা ক্যাশ" বা " রহিম কে ৫০০ টাকা বেতন দিয়েছি"'
                    @apply="onVoiceApply"
                />
            </div>
        </div>

        <!-- Instant Daily Breakdown Summary Card -->
        <div v-if="dailySummary" class="bg-slate-900 border border-slate-800 rounded-3xl p-5 mb-6 shadow-xl space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-800/80">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold">
                        <FileText class="w-4 h-4" />
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-white">দৈনিক আর্থিক সারাংশ (Daily Financial Breakdown)</h2>
                        <span class="text-[11px] text-slate-400 font-mono">{{ selectedDate }} এর হিসাব বিবরণী</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input
                        type="date"
                        :value="selectedDate"
                        @change="handleDateChange($event.target.value)"
                        class="bg-slate-950 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-white focus:border-amber-500 font-mono"
                    />

                    <button
                        type="button"
                        @click="printSummary"
                        class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold transition flex items-center gap-1.5"
                    >
                        <Printer class="w-3.5 h-3.5" />
                        <span>প্রিন্ট / সেভ</span>
                    </button>
                </div>
            </div>

            <!-- 6 Strict Accounting Metrics Tiles -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2.5 text-xs">
                <!-- 1. Raw Materials Purchases -->
                <div class="p-3 bg-slate-950 rounded-2xl border border-slate-800">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block mb-1">
                        কাঁচামাল ক্রয় (Asset)
                    </span>
                    <span class="text-sm font-extrabold text-amber-400 font-mono block">
                        ৳{{ Number(dailySummary.raw_material_purchases).toLocaleString() }}
                    </span>
                    <span class="text-[9px] text-slate-500">ইনভেন্টরি অ্যাসেট বৃদ্ধি</span>
                </div>

                <!-- 2. Packaging Purchases -->
                <div class="p-3 bg-slate-950 rounded-2xl border border-slate-800">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block mb-1">
                        প্যাকেজিং ক্রয় (Asset)
                    </span>
                    <span class="text-sm font-extrabold text-amber-300 font-mono block">
                        ৳{{ Number(dailySummary.packaging_purchases).toLocaleString() }}
                    </span>
                    <span class="text-[9px] text-slate-500">বক্স, ফয়েল, ক্যারিব্যাগ</span>
                </div>

                <!-- 3. Operating Expenses -->
                <div class="p-3 bg-slate-950 rounded-2xl border border-slate-800">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block mb-1">
                        পরিচালন খরচ (P&L)
                    </span>
                    <span class="text-sm font-extrabold text-rose-400 font-mono block">
                        ৳{{ Number(dailySummary.operating_expenses).toLocaleString() }}
                    </span>
                    <span class="text-[9px] text-slate-500">গ্যাস, বিদ্যুৎ, ভাড়া ইত্যাদি</span>
                </div>

                <!-- 4. Liability Outflows -->
                <div class="p-3 bg-slate-950 rounded-2xl border border-slate-800">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block mb-1">
                        দেনা পরিশোধ ও অগ্রিম
                    </span>
                    <span class="text-sm font-extrabold text-indigo-300 font-mono block">
                        ৳{{ Number(dailySummary.total_liability_outflows).toLocaleString() }}
                    </span>
                    <span class="text-[9px] text-slate-500">বাকি শোধ / বেতন / অগ্রিম</span>
                </div>

                <!-- 5. Gross Sales -->
                <div class="p-3 bg-slate-950 rounded-2xl border border-slate-800">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block mb-1">
                        মোট বিক্রয় (Sales)
                    </span>
                    <span class="text-sm font-extrabold text-emerald-400 font-mono block">
                        ৳{{ Number(dailySummary.gross_sales).toLocaleString() }}
                    </span>
                    <span class="text-[9px] text-slate-500">ক্যাশ ও ডিজিটাল বিক্রয়</span>
                </div>

                <!-- 6. FIFO COGS -->
                <div class="p-3 bg-slate-950 rounded-2xl border border-slate-800">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block mb-1">
                        খাবার খরচ (COGS)
                    </span>
                    <span class="text-sm font-extrabold text-cyan-400 font-mono block">
                        ৳{{ Number(dailySummary.cogs).toLocaleString() }}
                    </span>
                    <span class="text-[9px] text-slate-500">রান্নাঘরে ব্যবহৃত কাঁচামাল</span>
                </div>
            </div>
        </div>

        <!-- Saved Shortcuts & Quick Templates Bar -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <Star class="w-4 h-4 text-amber-400 fill-amber-400" />
                    <h2 class="text-xs font-bold text-slate-200 uppercase tracking-wider">{{ t('quick_shortcuts', 'Quick Entry Shortcuts') }}</h2>
                    <span class="text-[11px] text-slate-400 font-medium">(1-Tap pre-fill)</span>
                </div>

                <div class="relative w-48 sm:w-64">
                    <Search class="w-3.5 h-3.5 text-slate-500 absolute left-2.5 top-2.5" />
                    <input 
                        type="text" 
                        v-model="searchShortcut"
                        placeholder="Search shortcut items..."
                        class="w-full bg-slate-950 border border-slate-800 rounded-lg pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"
                    />
                </div>
            </div>

            <!-- Shortcuts Carousel / Pills -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
                <!-- Favorite Shortcuts -->
                <button
                    v-for="tpl in templates.favorites"
                    :key="'fav-' + tpl.id"
                    type="button"
                    @click="selectShortcut(tpl)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-amber-500/40 bg-amber-500/10 hover:bg-amber-500/20 text-xs font-medium text-amber-200 shrink-0 transition group cursor-pointer"
                >
                    <span class="text-amber-400">★</span>
                    <span class="font-bold text-white">{{ tpl.name }}</span>
                    <span v-if="tpl.last_unit_price > 0" class="text-[11px] text-amber-400 font-mono">৳{{ Number(tpl.last_unit_price).toFixed(0) }}/{{ tpl.default_unit }}</span>
                </button>

                <!-- Frequently & Recently Used -->
                <button
                    v-for="tpl in templates.frequently_used"
                    :key="'freq-' + tpl.id"
                    type="button"
                    @click="selectShortcut(tpl)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-800 bg-slate-950 hover:bg-slate-800 text-xs font-medium text-slate-200 shrink-0 transition cursor-pointer"
                >
                    <span class="text-slate-400">⚡</span>
                    <span class="font-semibold text-white">{{ tpl.name }}</span>
                    <span v-if="tpl.last_unit_price > 0" class="text-[11px] text-slate-400 font-mono">৳{{ Number(tpl.last_unit_price).toFixed(0) }}</span>
                </button>

                <div v-if="templates.favorites.length === 0 && templates.frequently_used.length === 0" class="text-xs text-slate-500 italic py-1">
                    No shortcuts yet. Check "Save as Quick Template" below to pin your everyday purchases.
                </div>
            </div>
        </div>

        <!-- 2 Column Layout: Form Left, Recent Entries Right -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Main Fast Entry Form -->
            <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-6">
                <!-- 1-Tap Fast Worker Payout Buttons -->
                <div class="flex items-center gap-2 mb-4 overflow-x-auto pb-1 scrollbar-none">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider shrink-0">⚡ দ্রুত কর্মী পেমেন্ট:</span>
                    <button
                        type="button"
                        @click="quickWorkerPayout('salary_payment')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-emerald-300 text-xs font-bold transition shrink-0 active:scale-95 cursor-pointer"
                    >
                        <Users class="w-3.5 h-3.5" />
                        <span>দৈনিক মজুরি প্রদান (Daily Wage)</span>
                    </button>
                    <button
                        type="button"
                        @click="quickWorkerPayout('salary_advance')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-amber-300 text-xs font-bold transition shrink-0 active:scale-95 cursor-pointer"
                    >
                        <Wallet class="w-3.5 h-3.5" />
                        <span>নগদ অগ্রিম প্রদান (Staff Advance)</span>
                    </button>
                </div>

                <!-- Transaction Type Tabs -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-3 mb-5 border-b border-slate-800/80 scrollbar-none">
                    <button
                        type="button"
                        @click="activeTab = 'purchase'"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 cursor-pointer',
                            activeTab === 'purchase' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'bg-slate-950 border border-slate-800 text-slate-300 hover:text-white'
                        ]"
                    >
                        <Truck class="w-3.5 h-3.5" />
                        <span>{{ t('raw_material_purchase', 'Inventory Purchase') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'expense'"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 cursor-pointer',
                            activeTab === 'expense' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'bg-slate-950 border border-slate-800 text-slate-300 hover:text-white'
                        ]"
                    >
                        <Receipt class="w-3.5 h-3.5" />
                        <span>{{ t('operating_expense', 'General Expense') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'consumption'"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 cursor-pointer',
                            activeTab === 'consumption' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'bg-slate-950 border border-slate-800 text-slate-300 hover:text-white'
                        ]"
                    >
                        <Flame class="w-3.5 h-3.5" />
                        <span>{{ t('kitchen_consumption', 'Kitchen Usage') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'salary_payment'"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 cursor-pointer',
                            activeTab === 'salary_payment' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'bg-slate-950 border border-slate-800 text-slate-300 hover:text-white'
                        ]"
                    >
                        <Users class="w-3.5 h-3.5" />
                        <span>{{ t('salary_payment', 'Staff Salary') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'salary_advance'"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 cursor-pointer',
                            activeTab === 'salary_advance' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'bg-slate-950 border border-slate-800 text-slate-300 hover:text-white'
                        ]"
                    >
                        <Wallet class="w-3.5 h-3.5" />
                        <span>{{ t('salary_advance', 'Staff Advance') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'supplier_payment'"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 cursor-pointer',
                            activeTab === 'supplier_payment' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'bg-slate-950 border border-slate-800 text-slate-300 hover:text-white'
                        ]"
                    >
                        <Store class="w-3.5 h-3.5" />
                        <span>{{ t('supplier_payment', 'Supplier Due Payment') }}</span>
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-4 text-xs">
                    <!-- Row 1: Date & Primary Entity -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">{{ t('date', 'Transaction Date') }}</label>
                            <input 
                                type="date" 
                                v-model="form.transaction_date" 
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <!-- Product Selector (For Purchase or Consumption) -->
                        <div v-if="['purchase', 'consumption'].includes(form.transaction_type)">
                            <label class="block text-slate-300 font-medium mb-1">{{ t('product_name', 'Select Product / Ingredient') }}</label>
                            <select 
                                v-model="form.product_id" 
                                @change="onProductSelect"
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option value="" disabled>Choose Product</option>
                                <option v-for="p in products" :key="p.id" :value="p.id">
                                    {{ p.name }} (Stock: {{ Number(p.current_stock).toFixed(1) }} {{ p.base_unit }})
                                </option>
                            </select>
                        </div>

                        <!-- Employee Selector (For Salary or Advance) -->
                        <div v-else-if="['salary_payment', 'salary_advance'].includes(form.transaction_type)">
                            <label class="block text-slate-300 font-medium mb-1">{{ t('staff_name', 'Select Staff / Employee') }}</label>
                            <select 
                                v-model="form.employee_id" 
                                @change="onEmployeeSelect"
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option value="" disabled>Choose Employee</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">
                                    {{ e.name }} ({{ e.role }} • {{ e.department || 'General' }} • ৳{{ e.agreed_salary }})
                                </option>
                            </select>

                            <!-- Staff Designation & Payable Info Card -->
                            <div v-if="selectedEmployeeInfo" class="mt-2 p-2.5 rounded-xl bg-slate-950/80 border border-slate-800 flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div>
                                    <span class="text-slate-400">Designation: </span>
                                    <span class="font-bold text-white">{{ selectedEmployeeInfo.role }}</span>
                                    <span class="text-slate-500"> • {{ selectedEmployeeInfo.department || 'Kitchen' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-slate-400">Unpaid Payable: </span>
                                    <span class="font-bold text-amber-400 font-mono">৳{{ selectedEmployeeInfo.unpaid_payable }}</span>
                                    <span v-if="selectedEmployeeInfo.outstanding_advance > 0" class="text-rose-400 text-[10px]">
                                        (Adv: ৳{{ selectedEmployeeInfo.outstanding_advance }})
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Supplier Selector (For Supplier Payment) -->
                        <div v-else-if="form.transaction_type === 'supplier_payment'" class="col-span-full">
                            <label class="block text-slate-300 font-medium mb-1">{{ t('supplier', 'Select Supplier / Dealer') }}</label>
                            <select 
                                v-model="form.supplier_id" 
                                @change="onSupplierSelect"
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option value="" disabled>Choose Supplier</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">
                                    {{ s.name }} (Due: ৳{{ Number(s.current_balance).toFixed(2) }})
                                </option>
                            </select>

                            <!-- Multi-Bill Allocation Selector -->
                            <div v-if="selectedSupplierInfo?.purchases?.length" class="mt-3 p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-300">Open Purchase Bills to Settle (Optional Multi-Bill Allocation):</span>
                                    <span class="text-slate-400">Total Due: <strong class="text-amber-400">৳{{ selectedSupplierInfo.current_balance }}</strong></span>
                                </div>
                                <div class="space-y-1.5 max-h-48 overflow-y-auto">
                                    <div v-for="b in selectedSupplierInfo.purchases" :key="b.id" class="flex items-center justify-between p-2 rounded-lg bg-slate-900 border border-slate-800 text-xs">
                                        <div>
                                            <span class="font-bold text-white font-mono">#{{ b.invoice_no }}</span>
                                            <span class="text-slate-400 text-[11px] ml-1.5">{{ b.purchase_date }} • Bill: ৳{{ b.total_amount }} • Remaining Due: <strong class="text-rose-400 font-mono">৳{{ b.due_amount }}</strong></span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-slate-400 text-[11px]">Pay ৳:</span>
                                            <input
                                                type="number"
                                                step="any"
                                                :max="b.due_amount"
                                                :value="form.allocations.find(a => a.purchase_id === b.id)?.amount || ''"
                                                @input="setBillAllocation(b.id, b.due_amount, $event.target.value)"
                                                placeholder="0.00"
                                                class="w-24 bg-slate-950 border border-slate-700 rounded px-2 py-1 text-white font-mono text-xs focus:border-amber-400 focus:outline-none"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Expense Category (For General Expense) -->
                        <div v-else-if="form.transaction_type === 'expense'">
                            <label class="block text-slate-300 font-medium mb-1">{{ t('expense_category', 'Expense Category') }}</label>
                            <select 
                                v-model="form.expense_category" 
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option value="electricity">Electricity Bill (বিদ্যুৎ বিল)</option>
                                <option value="gas">Gas Bill (গ্যাস বিল)</option>
                                <option value="rent">Shop Rent (দোকান ভাড়া)</option>
                                <option value="cleaning">Cleaning Supplies (পরিচ্ছন্নতা)</option>
                                <option value="transport">Transportation (যাতায়াত)</option>
                                <option value="repairs">Repairs & Maintenance (মেরামত)</option>
                                <option value="marketing">Marketing & Promotion (বিজ্ঞাপন)</option>
                                <option value="misc">Miscellaneous (বিবিধ)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row 2: Purchase Specifics (Qty, Unit, Rate, Total) -->
                    <div v-if="['purchase', 'consumption'].includes(form.transaction_type)" class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-950/60 p-3 rounded-xl border border-slate-800">
                        <div>
                            <label class="block text-slate-400 mb-1 font-medium">{{ t('quantity', 'Quantity') }}</label>
                            <input 
                                type="number" 
                                step="any" 
                                v-model="form.quantity" 
                                required
                                class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono font-bold focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-400 mb-1 font-medium">{{ t('purchase_unit', 'Unit') }}</label>
                            <input 
                                type="text" 
                                v-model="form.unit" 
                                placeholder="kg, pcs, L"
                                required
                                class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div v-if="form.transaction_type === 'purchase'">
                            <label class="block text-slate-400 mb-1 font-medium">{{ t('unit_price', 'Unit Price (৳)') }}</label>
                            <input 
                                type="number" 
                                step="any" 
                                v-model="form.unit_price" 
                                required
                                class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div v-if="form.transaction_type === 'purchase'">
                            <label class="block text-slate-400 mb-1 font-medium">{{ t('total', 'Total Cost (৳)') }}</label>
                            <div class="w-full bg-amber-500/10 border border-amber-500/30 rounded-lg px-2.5 py-1.5 text-amber-300 font-mono font-bold">
                                ৳ {{ computedTotal }}
                            </div>
                        </div>
                    </div>

                    <!-- Direct Amount Entry for Expenses, Salaries, Supplier Payments -->
                    <div v-if="!['purchase', 'consumption'].includes(form.transaction_type)" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">{{ t('amount', 'Amount (৳)') }}</label>
                            <input 
                                type="number" 
                                step="any" 
                                v-model="form.amount" 
                                required
                                placeholder="0.00"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono font-bold text-sm focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Description / Payee Name</label>
                            <input 
                                type="text" 
                                v-model="form.name" 
                                placeholder="e.g. DESCO Electric, Staff name, Vendor"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Row 3: Payment Account & Supplier / Cash Options -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div v-if="form.transaction_type !== 'consumption'">
                            <label class="block text-slate-300 font-medium mb-1">{{ t('payment_account', 'Payment Account') }}</label>
                            <select 
                                v-model="form.payment_account_id" 
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                    {{ acc.name }} ({{ acc.type.toUpperCase() }} • Bal: ৳{{ Number(acc.current_balance).toFixed(2) }})
                                </option>
                            </select>
                        </div>

                        <!-- Immediate Cash Paid vs Credit for Purchase -->
                        <div v-if="form.transaction_type === 'purchase'">
                            <label class="block text-slate-300 font-medium mb-1">Immediate Cash Paid (৳)</label>
                            <input 
                                type="number" 
                                step="any" 
                                v-model="form.paid_amount" 
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                            <span class="text-[10px] text-slate-400 mt-1 block">
                                Remaining ৳{{ Math.max(0, computedTotal - (parseFloat(form.paid_amount) || 0)).toFixed(2) }} recorded as supplier payable due.
                            </span>
                        </div>

                        <!-- Direct Purchase & Consume Checkbox -->
                        <div v-if="form.transaction_type === 'purchase'" class="col-span-full pt-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer text-slate-300">
                                <input type="checkbox" v-model="form.is_purchase_and_consume" class="rounded border-slate-700 bg-slate-950 text-amber-500 focus:ring-0" />
                                <span class="font-medium text-amber-300">{{ t('direct_consume', 'Direct Purchase & Consume (Fresh market goods used today, ending stock +0)') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">{{ t('notes', 'Notes / Remarks') }}</label>
                        <input 
                            type="text" 
                            v-model="form.notes" 
                            placeholder="Optional reference, voucher #, or invoice details"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <!-- Save as Shortcut Toggle -->
                    <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between">
                        <label class="inline-flex items-center gap-2 cursor-pointer text-slate-300">
                            <input type="checkbox" v-model="form.save_as_template" class="rounded border-slate-700 bg-slate-950 text-amber-500 focus:ring-0" />
                            <span class="font-medium">{{ t('save_as_shortcut', 'Save this item as Quick Shortcut template for 1-tap future entry') }}</span>
                        </label>

                        <div v-if="form.save_as_template" class="flex items-center gap-2">
                            <label class="inline-flex items-center gap-1.5 text-xs text-amber-400">
                                <input type="checkbox" v-model="form.is_favorite" class="rounded border-slate-700 bg-slate-950 text-amber-500 focus:ring-0" />
                                <span>Star as Favorite</span>
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-xl text-sm transition shadow-lg shadow-amber-500/10 flex items-center justify-center gap-2 cursor-pointer mt-4"
                    >
                        <CheckCircle2 class="w-5 h-5" />
                        <span>Confirm & Record Transaction (নিশ্চিত করুন)</span>
                    </button>
                </form>
            </div>

            <!-- Right 1 Col: Recent Entries Stream for Today -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 flex flex-col h-full">
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <Clock class="w-4 h-4 text-amber-400" />
                        <h3 class="text-xs font-bold text-white uppercase tracking-wider">Recorded Today</h3>
                    </div>
                    <span class="text-[11px] text-slate-400 font-mono">{{ selectedDate }}</span>
                </div>

                <div class="space-y-3 overflow-y-auto max-h-[580px] pr-1 flex-1">
                    <!-- Purchases -->
                    <div v-for="p in recentPurchases" :key="'p-' + p.id" class="p-3 bg-slate-950 border border-slate-800/80 rounded-xl text-xs">
                        <div class="flex items-center justify-between font-bold text-white mb-1">
                            <span>{{ p.supplier?.name || 'Stock Purchase' }}</span>
                            <span class="text-amber-400 font-mono">৳{{ p.total_amount }}</span>
                        </div>
                        <div class="text-[11px] text-slate-400">
                            <span v-for="it in p.items" :key="it.id">
                                {{ it.product?.name }} ({{ Number(it.purchase_quantity).toFixed(1) }} {{ it.purchase_unit }})
                            </span>
                        </div>
                        <div class="flex items-center justify-between mt-2 pt-1 border-t border-slate-900 text-[10px] text-slate-500">
                            <span>Paid: ৳{{ p.paid_amount }}</span>
                            <div class="flex items-center gap-2">
                                <span v-if="p.is_purchase_and_consume" class="text-amber-400">Direct Consumed</span>
                                <button
                                    v-if="p.reversal_status !== 'voided'"
                                    type="button"
                                    @click="handleVoidPurchase(p)"
                                    class="text-rose-400 hover:text-rose-300 font-bold underline"
                                >
                                    বাতিল (Void)
                                </button>
                                <span v-else class="text-rose-500 font-bold uppercase">বাতিলকৃত (Voided)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Expenses -->
                    <div v-for="e in recentExpenses" :key="'e-' + e.id" class="p-3 bg-slate-950 border border-slate-800/80 rounded-xl text-xs">
                        <div class="flex items-center justify-between font-bold text-white mb-1">
                            <span class="capitalize">{{ e.category }} Expense</span>
                            <span class="text-slate-300 font-mono">৳{{ e.amount }}</span>
                        </div>
                        <p class="text-[11px] text-slate-400">{{ e.payee || e.notes || 'Operating expense' }}</p>
                        <div class="flex items-center justify-end mt-2 pt-1 border-t border-slate-900 text-[10px]">
                            <button
                                v-if="e.reversal_status !== 'voided'"
                                type="button"
                                @click="handleVoidExpense(e)"
                                class="text-rose-400 hover:text-rose-300 font-bold underline"
                            >
                                বাতিল (Void)
                            </button>
                            <span v-else class="text-rose-500 font-bold uppercase">বাতিলকৃত (Voided)</span>
                        </div>
                    </div>

                    <div v-if="recentPurchases.length === 0 && recentExpenses.length === 0" class="text-center py-8 text-xs text-slate-500 italic">
                        No transactions recorded for this date yet.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

