<script setup>
import { ref, computed } from 'vue';
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
    Store, 
    Users, 
    CheckCircle2, 
    Calculator,
    Sparkles,
    Calendar,
    ChefHat,
    BadgePercent,
    CreditCard,
    CircleDot
} from 'lucide-vue-next';
import { useI18n } from '../Composables/useI18n';
import VoiceInputButton from '@/Components/Voice/VoiceInputButton.vue';

const props = defineProps({
    selectedDate: String,
    summary: Object,
    lowStockProducts: Array,
    accounts: Array,
    recentMovements: Array,
    supplierStats: Object,
    staffStats: Object,
});

const { t, currentLocale } = useI18n();

const currentDate = ref(props.selectedDate);

const onDateChange = () => {
    router.get('/', { date: currentDate.value }, { preserveState: true });
};

const setToday = () => {
    const today = new Date().toISOString().split('T')[0];
    currentDate.value = today;
    router.get('/', { date: today }, { preserveState: true });
};

// Greeting based on hour
const greeting = computed(() => {
    const hour = new Date().getHours();
    if (currentLocale.value === 'bn') {
        if (hour < 12) return 'শুভ সকাল';
        if (hour < 16) return 'শুভ দুপুর';
        if (hour < 19) return 'শুভ বিকাল';
        return 'শুভ সন্ধ্যা';
    } else {
        if (hour < 12) return 'Good Morning';
        if (hour < 17) return 'Good Afternoon';
        return 'Good Evening';
    }
});

const isTodaySelected = computed(() => {
    const today = new Date().toISOString().split('T')[0];
    return currentDate.value === today;
});

const handleVoiceApply = (parsed) => {
    router.get('/daily-costs');
};
</script>

<template>
    <AppLayout title="Dashboard">
        <Head :title="t('dashboard', 'Dashboard') + ' — Royal Nest Café & Restaurant'" />

        <!-- 1. HOSPITALITY HERO GREETING & DATE BAR -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-900/90 to-amber-950/30 border border-amber-500/20 p-5 sm:p-7 mb-6 shadow-xl">
            <!-- Decorative Glow Background Elements -->
            <div class="absolute -right-12 -top-12 w-48 h-48 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-12 -bottom-12 w-48 h-48 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-5">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-bold mb-2">
                        <Sparkles class="w-3.5 h-3.5 text-amber-400" />
                        <span>{{ greeting }}, Royal Nest Team</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-white tracking-tight flex items-center gap-2">
                        <span>রেস্টুরেন্ট অপারেশনস ও লাভ-ক্ষতি ড্যাশবোর্ড</span>
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-300/80 mt-1 max-w-xl">
                        স্বয়ংক্রিয় কাঁচামাল মূল্যায়ন (FIFO), মাল্টি-চ্যানেল বিক্রয় সমন্বয় এবং রিয়েল-টাইম নিট লাভ বিশ্লেষণ।
                    </p>
                </div>

                <!-- Date Navigation Bar -->
                <div class="flex flex-wrap items-center gap-2 self-start md:self-auto bg-slate-950/80 p-2 rounded-2xl border border-slate-800 shadow-inner">
                    <button
                        type="button"
                        v-if="!isTodaySelected"
                        @click="setToday"
                        class="px-3 py-1.5 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 text-xs font-bold transition active:scale-95"
                    >
                        আজকের হিসাব
                    </button>

                    <div class="flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700/80">
                        <Calendar class="w-4 h-4 text-amber-400 shrink-0" />
                        <input 
                            type="date" 
                            v-model="currentDate" 
                            @change="onDateChange"
                            class="bg-transparent text-xs sm:text-sm text-amber-300 font-mono font-bold focus:outline-none cursor-pointer"
                        />
                    </div>
                </div>
            </div>

            <!-- ONE-TOUCH FAST ACTIONS BAR -->
            <div class="relative z-10 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5 mt-6 pt-5 border-t border-slate-800/80">
                <!-- Action 1: Voice Costs & Purchases -->
                <Link
                    href="/daily-costs"
                    class="flex items-center gap-2.5 p-3 rounded-2xl bg-gradient-to-r from-amber-500/15 to-amber-600/5 hover:from-amber-500/25 hover:to-amber-600/15 border border-amber-500/30 text-amber-300 font-bold text-xs transition active:scale-95 group shadow-sm"
                >
                    <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition">
                        <Sparkles class="w-4 h-4" />
                    </div>
                    <div class="truncate">
                        <span class="block text-white leading-tight">দৈনিক খরচ</span>
                        <span class="text-[10px] text-amber-400 font-normal">ভয়েস টাইপিং</span>
                    </div>
                </Link>

                <!-- Action 2: Daily Sales Entry -->
                <Link
                    href="/sales"
                    class="flex items-center gap-2.5 p-3 rounded-2xl bg-gradient-to-r from-emerald-500/15 to-emerald-600/5 hover:from-emerald-500/25 hover:to-emerald-600/15 border border-emerald-500/30 text-emerald-300 font-bold text-xs transition active:scale-95 group shadow-sm"
                >
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition">
                        <ShoppingBag class="w-4 h-4" />
                    </div>
                    <div class="truncate">
                        <span class="block text-white leading-tight">বিক্রি এন্ট্রি</span>
                        <span class="text-[10px] text-emerald-400 font-normal">ক্যাশ ও বিকাশ</span>
                    </div>
                </Link>

                <!-- Action 3: P&L Calculator -->
                <Link
                    href="/calculator"
                    class="flex items-center gap-2.5 p-3 rounded-2xl bg-gradient-to-r from-cyan-500/15 to-teal-600/5 hover:from-cyan-500/25 hover:to-teal-600/15 border border-cyan-500/30 text-cyan-300 font-bold text-xs transition active:scale-95 group shadow-sm"
                >
                    <div class="w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition">
                        <Calculator class="w-4 h-4" />
                    </div>
                    <div class="truncate">
                        <span class="block text-white leading-tight">ক্যালকুলেটর</span>
                        <span class="text-[10px] text-cyan-400 font-normal">লাইভ প্রফিট</span>
                    </div>
                </Link>

                <!-- Action 4: Bulk Staff Roll Call -->
                <Link
                    href="/employees/attendance/bulk"
                    class="flex items-center gap-2.5 p-3 rounded-2xl bg-gradient-to-r from-indigo-500/15 to-purple-600/5 hover:from-indigo-500/25 hover:to-purple-600/15 border border-indigo-500/30 text-indigo-300 font-bold text-xs transition active:scale-95 group shadow-sm"
                >
                    <div class="w-8 h-8 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition">
                        <Users class="w-4 h-4" />
                    </div>
                    <div class="truncate">
                        <span class="block text-white leading-tight">টিম হাজিরা</span>
                        <span class="text-[10px] text-indigo-400 font-normal">শিফট রোল-কল</span>
                    </div>
                </Link>

                <!-- Action 5: Cash Reconciliation -->
                <Link
                    href="/cash-reconciliation"
                    class="col-span-2 sm:col-span-1 flex items-center gap-2.5 p-3 rounded-2xl bg-gradient-to-r from-rose-500/15 to-pink-600/5 hover:from-rose-500/25 hover:to-pink-600/15 border border-rose-500/30 text-rose-300 font-bold text-xs transition active:scale-95 group shadow-sm"
                >
                    <div class="w-8 h-8 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition">
                        <Wallet class="w-4 h-4" />
                    </div>
                    <div class="truncate">
                        <span class="block text-white leading-tight">ক্যাশ ড্রয়ার</span>
                        <span class="text-[10px] text-rose-400 font-normal">হাতে গুনে মিলান</span>
                    </div>
                </Link>
            </div>
        </div>

        <!-- 2. PRIMARY 4 KPI SUMMARY CARDS (ELEVATED VISUAL HIERARCHY) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Total Sales Card -->
            <div class="bg-slate-900/90 border border-slate-800/80 hover:border-emerald-500/40 rounded-3xl p-5 relative overflow-hidden transition shadow-lg group">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ t('today_sales', 'মোট বিক্রি (Gross Sales)') }}</span>
                    <div class="w-9 h-9 rounded-xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center text-emerald-400 group-hover:scale-110 transition">
                        <ShoppingBag class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-2xl sm:text-3xl font-black text-white font-mono tracking-tight">
                    ৳ {{ Number(summary?.sales?.total_sales || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </div>
                <div class="mt-3 text-xs text-slate-400 flex items-center justify-between pt-2.5 border-t border-slate-800/80">
                    <span class="font-medium">ক্যাশ: <strong class="text-slate-200">৳{{ summary?.sales?.cash_sales || '0.00' }}</strong></span>
                    <span class="font-medium">ডিজিটাল: <strong class="text-emerald-400">৳{{ Number((summary?.sales?.bkash_sales || 0) + (summary?.sales?.nagad_sales || 0) + (summary?.sales?.card_bank_sales || 0)).toFixed(2) }}</strong></span>
                </div>
            </div>

            <!-- Material Usage Cost Card -->
            <div class="bg-slate-900/90 border border-slate-800/80 hover:border-amber-500/40 rounded-3xl p-5 relative overflow-hidden transition shadow-lg group">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ t('today_cogs', 'কাঁচামাল খরচ (COGS)') }}</span>
                    <div class="w-9 h-9 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-400 group-hover:scale-110 transition">
                        <Flame class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-2xl sm:text-3xl font-black text-amber-300 font-mono tracking-tight">
                    ৳ {{ Number(summary?.cost_of_materials?.total || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </div>
                <div class="mt-3 text-xs text-slate-400 flex items-center justify-between pt-2.5 border-t border-slate-800/80">
                    <span class="font-medium">উপাদান: <strong class="text-slate-200">৳{{ summary?.cost_of_materials?.ingredient_consumption || '0.00' }}</strong></span>
                    <span class="font-medium">প্যাকেজিং: <strong class="text-amber-300">৳{{ summary?.cost_of_materials?.consumables_allocated || '0.00' }}</strong></span>
                </div>
            </div>

            <!-- Total Operating Costs Card -->
            <div class="bg-slate-900/90 border border-slate-800/80 hover:border-indigo-500/40 rounded-3xl p-5 relative overflow-hidden transition shadow-lg group">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ t('operating_costs', 'পরিচালন ও বেতন') }}</span>
                    <div class="w-9 h-9 rounded-xl bg-indigo-500/15 border border-indigo-500/30 flex items-center justify-center text-indigo-400 group-hover:scale-110 transition">
                        <Receipt class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-2xl sm:text-3xl font-black text-slate-200 font-mono tracking-tight">
                    ৳ {{ Number(summary?.operating_costs?.total || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </div>
                <div class="mt-3 text-xs text-slate-400 flex items-center justify-between pt-2.5 border-t border-slate-800/80">
                    <span class="font-medium">বিল/খরচ: <strong class="text-slate-200">৳{{ summary?.operating_costs?.operating_expenses || '0.00' }}</strong></span>
                    <span class="font-medium">আজকের বেতন: <strong class="text-indigo-300">৳{{ summary?.operating_costs?.salary_cost || '0.00' }}</strong></span>
                </div>
            </div>

            <!-- Net Profit Card (Hero Card with glowing ring) -->
            <div 
                :class="[
                    'border rounded-3xl p-5 relative overflow-hidden transition shadow-xl group',
                    (summary?.net_profit ?? 0) >= 0 
                        ? 'bg-gradient-to-br from-slate-900 via-emerald-950/20 to-emerald-950/40 border-emerald-500/40' 
                        : 'bg-gradient-to-br from-slate-900 via-rose-950/20 to-rose-950/40 border-rose-500/40'
                ]"
            >
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-extrabold uppercase tracking-wider flex items-center gap-1.5" :class="(summary?.net_profit ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                        <span>{{ t('today_net_profit', 'প্রকৃত নিট লাভ (Net Profit)') }}</span>
                    </span>
                    <div 
                        class="w-9 h-9 rounded-xl flex items-center justify-center group-hover:scale-110 transition"
                        :class="(summary?.net_profit ?? 0) >= 0 ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-400 border border-rose-500/40'"
                    >
                        <component :is="(summary?.net_profit ?? 0) >= 0 ? TrendingUp : TrendingDown" class="w-4 h-4" />
                    </div>
                </div>
                <div 
                    class="text-2xl sm:text-3xl font-black font-mono tracking-tight"
                    :class="(summary?.net_profit ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400'"
                >
                    ৳ {{ Number(summary?.net_profit || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </div>
                <div class="mt-3 text-xs text-slate-300 flex items-center justify-between pt-2.5 border-t border-slate-800/80">
                    <span class="font-bold flex items-center gap-1">
                        <BadgePercent class="w-3.5 h-3.5 text-amber-400" />
                        <span>মার্জিন: {{ summary?.profit_margin || 0 }}%</span>
                    </span>
                    <span class="text-slate-400">গ্রস প্রফিট: ৳{{ summary?.gross_profit || '0.00' }}</span>
                </div>
            </div>
        </div>

        <!-- 3. CROSS-MODULE ROW: SUPPLIERS & STAFF -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Suppliers & Dues Card -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-5 flex flex-col justify-between shadow-md">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                            <Store class="w-4 h-4 text-amber-400" />
                            <span>{{ t('suppliers', 'সাপ্লায়ার ও মহাজন হিসাব (Vendor Dues)') }}</span>
                        </h2>
                        <div class="flex items-center gap-2 text-xs">
                            <Link href="/suppliers/directory" class="text-slate-400 hover:text-white transition">ডিরেক্টরি</Link>
                            <span class="text-slate-700">•</span>
                            <Link href="/suppliers/active" class="text-amber-400 hover:text-amber-300 font-bold transition">বকেয়া তালিকা</Link>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800">
                            <span class="text-[11px] text-slate-400 block mb-1">অ্যাক্টিভ ভেন্ডর</span>
                            <span class="text-xl font-black font-mono text-slate-200">{{ supplierStats.active_count }} জন</span>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-rose-950/20 border border-rose-900/40">
                            <span class="text-[11px] text-rose-300 block mb-1">মোট বকেয়া দেনা</span>
                            <span class="text-xl font-black font-mono text-rose-400">৳ {{ supplierStats.total_payable.toLocaleString() }}</span>
                        </div>
                    </div>

                    <!-- Recent Supplier Bills -->
                    <div>
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-2">সাম্প্রতিক সাপ্লায়ার বিল ও মালপত্র</span>
                        <div class="space-y-2">
                            <div
                                v-for="pur in supplierStats.recent_purchases"
                                :key="pur.id"
                                class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800/80 text-xs"
                            >
                                <div>
                                    <span class="font-bold text-white">{{ pur.supplier ? pur.supplier.name : 'Vendor' }}</span>
                                    <span class="text-slate-500 text-[10px] ml-2">#{{ pur.invoice_no }}</span>
                                </div>
                                <span class="font-mono text-amber-400 font-bold">৳ {{ Number(pur.total_amount).toLocaleString() }}</span>
                            </div>
                            <div v-if="!supplierStats.recent_purchases || supplierStats.recent_purchases.length === 0" class="text-slate-500 text-xs py-3 italic text-center">
                                আজকের কোনো নতুন সাপ্লায়ার বিল নেই।
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-800/80 flex items-center justify-between text-xs mt-4">
                    <span class="text-slate-400">ভেন্ডর বিল পরিশোধ বা অগ্রিম এন্ট্রি</span>
                    <Link href="/suppliers/active" class="text-amber-400 hover:text-amber-300 flex items-center gap-1 font-bold">
                        <span>সাপ্লায়ার প্যানেল</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                </div>
            </div>

            <!-- Staff Operations & Attendance Card -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-5 flex flex-col justify-between shadow-md">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                            <Users class="w-4 h-4 text-emerald-400" />
                            <span>{{ t('employees', 'টিম পরিচালনা ও আজকের হাজিরা') }}</span>
                        </h2>
                        <div class="flex items-center gap-2 text-xs">
                            <Link href="/employees/attendance/bulk" class="text-emerald-400 hover:text-emerald-300 font-bold transition">রোল-কল</Link>
                            <span class="text-slate-700">•</span>
                            <Link href="/employees" class="text-slate-400 hover:text-white transition">কর্মী তালিকা</Link>
                        </div>
                    </div>

                    <!-- Pending Attendance Warning Box -->
                    <div 
                        v-if="staffStats.attendance.unmarked > 0" 
                        class="mb-3 p-3.5 rounded-2xl bg-gradient-to-r from-amber-500/20 via-slate-950 to-amber-500/10 border border-amber-500/40 text-xs text-amber-200 space-y-2 shadow-inner"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <Clock class="w-4 h-4 text-amber-400 shrink-0 animate-spin" />
                                <span><strong class="text-white">{{ staffStats.attendance.unmarked }} জন কর্মীর হাজিরা এখনো দেওয়া হয়নি!</strong></span>
                            </div>
                            <Link 
                                href="/employees/attendance/bulk" 
                                class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold rounded-xl text-xs transition shadow shrink-0 active:scale-95"
                            >
                                হাজিরা দিন →
                            </Link>
                        </div>
                    </div>

                    <!-- Attendance Pill Badges -->
                    <div class="grid grid-cols-4 gap-2 mb-4">
                        <div class="p-2.5 rounded-2xl bg-slate-950/60 border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block">উপস্থিত</span>
                            <span class="text-lg font-black font-mono text-emerald-400">{{ staffStats.attendance.present }}</span>
                        </div>
                        <div class="p-2.5 rounded-2xl bg-slate-950/60 border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block">হাফ ডে</span>
                            <span class="text-lg font-black font-mono text-amber-400">{{ staffStats.attendance.half_day }}</span>
                        </div>
                        <div class="p-2.5 rounded-2xl bg-slate-950/60 border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block">ছুটি</span>
                            <span class="text-lg font-black font-mono text-blue-400">{{ staffStats.attendance.leave }}</span>
                        </div>
                        <div class="p-2.5 rounded-2xl bg-slate-950/60 border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block">অনুপস্থিত</span>
                            <span class="text-lg font-black font-mono text-rose-400">{{ staffStats.attendance.absent }}</span>
                        </div>
                    </div>

                    <!-- Staff Advance Outstanding & Recent Payouts -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">সাম্প্রতিক বেতন ও অগ্রিম</span>
                            <span class="text-xs text-amber-400 font-mono font-bold">বকেয়া অগ্রিম: ৳{{ staffStats.outstanding_advances.toLocaleString() }}</span>
                        </div>
                        <div class="space-y-2">
                            <div
                                v-for="sp in staffStats.recent_payments"
                                :key="sp.id"
                                class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800/80 text-xs"
                            >
                                <div>
                                    <span class="font-bold text-white">{{ sp.employee ? sp.employee.name : 'Staff' }}</span>
                                    <span class="text-slate-500 text-[10px] ml-2">{{ sp.month_year }}</span>
                                </div>
                                <span class="font-mono text-emerald-400 font-bold">৳ {{ Number(sp.amount).toLocaleString() }}</span>
                            </div>
                            <div v-if="!staffStats.recent_payments || staffStats.recent_payments.length === 0" class="text-slate-500 text-xs py-3 italic text-center">
                                সম্প্রতি কোনো বেতন প্রদান করা হয়নি।
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-800/80 flex items-center justify-between text-xs mt-4">
                    <span class="text-slate-400">মোট নিয়মিত কর্মী: {{ staffStats.active_count }} জন</span>
                    <Link href="/employees" class="text-emerald-400 hover:text-emerald-300 flex items-center gap-1 font-bold">
                        <span>কর্মী ডিরেক্টরি</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- 4. BOTTOM SECTION: CASH ACCOUNTS & INVENTORY ALERTS -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Financial Accounts & Liquidity Balances -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-5 shadow-md">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                        <Wallet class="w-4 h-4 text-amber-400" />
                        <span>ক্যাশ ড্রয়ার ও ব্যাংক অ্যাকাউন্ট</span>
                    </h2>
                    <Link href="/accounts" class="text-xs text-amber-400 hover:text-amber-300 font-bold">ট্রান্সফার</Link>
                </div>

                <div class="space-y-2.5">
                    <div 
                        v-for="acc in accounts" 
                        :key="acc.id"
                        class="flex items-center justify-between p-3 rounded-2xl bg-slate-950/60 border border-slate-800/80 hover:border-slate-700 transition"
                    >
                        <div>
                            <span class="text-xs font-bold text-white block">{{ acc.name }}</span>
                            <span class="text-[10px] text-slate-400 uppercase font-mono">{{ acc.type }}</span>
                        </div>
                        <span class="text-sm font-black font-mono text-emerald-400">
                            ৳ {{ Number(acc.current_balance).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Low Stock Warnings -->
            <div class="lg:col-span-2 bg-slate-900/90 border border-slate-800 rounded-3xl p-5 shadow-md">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                        <AlertTriangle class="w-4 h-4 text-amber-400" />
                        <span>ইনভেন্টরি অ্যালার্ট ও রিরিডার লেভেল</span>
                    </h2>
                    <Link href="/purchases" class="text-xs text-amber-400 hover:text-amber-300 font-bold">নতুন ক্রয় এন্ট্রি</Link>
                </div>

                <div v-if="lowStockProducts.length === 0" class="py-10 text-center text-slate-400 text-xs flex flex-col items-center justify-center space-y-2">
                    <CheckCircle2 class="w-8 h-8 text-emerald-400" />
                    <span>সকল কাঁচামাল ও প্যাকেজিং ন্যূনতম রিরিডার সীমার উপরে আছে।</span>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-800 text-slate-400 font-bold uppercase text-[10px]">
                            <tr>
                                <th class="pb-2.5">উপাদান</th>
                                <th class="pb-2.5">বর্তমান স্টক</th>
                                <th class="pb-2.5">রিরিডার লেভেল</th>
                                <th class="pb-2.5 text-right">স্ট্যাটাস</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="item in lowStockProducts" :key="item.id" class="text-slate-300">
                                <td class="py-3 font-bold text-white">{{ item.name }}</td>
                                <td class="py-3 font-mono text-rose-400 font-bold">
                                    {{ Number(item.current_stock).toFixed(2) }} {{ item.base_unit }}
                                </td>
                                <td class="py-3 font-mono text-slate-400">
                                    {{ Number(item.min_reorder_level).toFixed(2) }} {{ item.base_unit }}
                                </td>
                                <td class="py-3 text-right">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-950/60 text-rose-400 border border-rose-800/40">
                                        Low Stock
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 5. RECENT STOCK MOVEMENTS LEDGER -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-5 shadow-md">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                    <Clock class="w-4 h-4 text-slate-400" />
                    <span>সাম্প্রতিক পারপেচুয়াল স্টক মুভমেন্ট</span>
                </h2>
                <Link href="/ledger" class="text-xs text-amber-400 hover:text-amber-300 font-bold">সম্পূর্ণ লেজার</Link>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="border-b border-slate-800 text-slate-400 font-bold uppercase text-[10px]">
                        <tr>
                            <th class="pb-2.5">তারিখ</th>
                            <th class="pb-2.5">মুভমেন্টের ধরন</th>
                            <th class="pb-2.5">পণ্য / উপাদান</th>
                            <th class="pb-2.5 text-right">ইন (In)</th>
                            <th class="pb-2.5 text-right">আউট (Out)</th>
                            <th class="pb-2.5 text-right">অবশিষ্ট স্টক</th>
                            <th class="pb-2.5 text-right">ইউনিট কস্ট</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="mv in recentMovements" :key="mv.id" class="hover:bg-slate-800/40 transition">
                            <td class="py-3 font-mono text-slate-400">{{ mv.transaction_date }}</td>
                            <td class="py-3">
                                <span 
                                    :class="[
                                        'px-2 py-0.5 rounded-md text-[10px] font-bold uppercase',
                                        mv.quantity_in > 0 ? 'bg-emerald-950/60 text-emerald-400 border border-emerald-800/40' : 'bg-amber-950/60 text-amber-400 border border-amber-800/40'
                                    ]"
                                >
                                    {{ mv.movement_type.replace('_', ' ') }}
                                </span>
                            </td>
                            <td class="py-3 font-bold text-white">{{ mv.product ? mv.product.name : 'Product' }}</td>
                            <td class="py-3 text-right font-mono text-emerald-400 font-bold">
                                {{ mv.quantity_in > 0 ? '+' + Number(mv.quantity_in).toFixed(2) : '—' }}
                            </td>
                            <td class="py-3 text-right font-mono text-amber-400 font-bold">
                                {{ mv.quantity_out > 0 ? '-' + Number(mv.quantity_out).toFixed(2) : '—' }}
                            </td>
                            <td class="py-3 text-right font-mono font-black text-slate-200">
                                {{ Number(mv.balance_after ?? mv.ending_stock ?? 0).toFixed(2) }}
                            </td>
                            <td class="py-3 text-right font-mono text-slate-300">
                                ৳{{ Number(mv.unit_cost).toFixed(2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
