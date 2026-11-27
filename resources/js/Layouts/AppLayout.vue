<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { 
    UtensilsCrossed, 
    LayoutDashboard, 
    ShoppingBag, 
    Truck, 
    Flame, 
    PackageCheck, 
    Layers, 
    Trash2, 
    ClipboardCheck, 
    Boxes, 
    Receipt, 
    Landmark, 
    Users, 
    TrendingUp, 
    AlertCircle,
    CheckCircle2,
    Store,
    Globe,
    LogOut,
    Sparkles,
    Calculator,
    Menu,
    X,
    ChevronDown,
    Clock,
    ArrowUpRight,
    ShieldCheck,
    Archive,
    UserCog,
    ChefHat,
    CircleDot
} from 'lucide-vue-next';
import { useI18n } from '../Composables/useI18n';
import VoiceInputButton from '@/Components/Voice/VoiceInputButton.vue';

defineProps({
    title: String,
});

const page = usePage();
const flash = computed(() => page.props.flash || {});
const authUser = computed(() => page.props.auth?.user || null);
const unmarkedStaffCount = computed(() => page.props.unmarked_staff_count || 0);
const { t, currentLocale, toggleLocale } = useI18n();

const showMobileMenu = ref(false);
const activeDropdown = ref(null); // 'kitchen', 'team', 'finance'

// Close dropdown on outside click
const closeDropdowns = (e) => {
    if (!e.target.closest('.nav-dropdown-container')) {
        activeDropdown.value = null;
    }
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('click', closeDropdowns);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('click', closeDropdowns);
    }
});

const toggleDropdown = (name) => {
    activeDropdown.value = activeDropdown.value === name ? null : name;
};

// 1. Core Direct Links
const coreNav = computed(() => [
    { label: t('dashboard', 'Overview'), href: '/', icon: LayoutDashboard },
    { label: t('daily_costs', 'Daily Costs'), href: '/daily-costs', icon: Sparkles, badge: 'Voice' },
    { label: t('pnl_calculator', 'Calculator'), href: '/calculator', icon: Calculator },
    { label: t('daily_sales', 'Daily Sales'), href: '/sales', icon: ShoppingBag },
]);

// 2. Kitchen & Inventory Group
const kitchenItems = computed(() => [
    { label: t('purchases', 'Stock In / Purchases'), desc: 'Supplier bills & receiving', href: '/purchases', icon: Truck },
    { label: t('consumption', 'Kitchen Usage (FIFO)'), desc: 'Daily ingredient deduction', href: '/consumption', icon: Flame },
    { label: t('products', 'Ingredients Catalog'), desc: 'Unit costs & stock alerts', href: '/products', icon: Boxes },
    { label: t('wastage', 'Wastage & Spoilage'), desc: 'Burnt/expired waste trace', href: '/wastage', icon: Trash2 },
    { label: t('consumables', 'Consumables Allocation'), desc: 'Gas, packaging, cleaning', href: '/consumables', icon: PackageCheck },
    { label: t('stock_counts', 'Physical Stock Count'), desc: 'Drawer & shelf audit', href: '/stock-counts', icon: ClipboardCheck },
    { label: t('ledger', 'Stock Movement Ledger'), desc: 'Immutable audit trail', href: '/ledger', icon: Layers },
]);

// 3. Team & Suppliers Group
const teamItems = computed(() => [
    { label: t('employees', 'Staff & Payroll'), desc: 'Attendance, salary & hold deposit', href: '/employees', icon: Users, badge: unmarkedStaffCount.value },
    { label: t('bulk_attendance', 'Bulk Daily Roll Call'), desc: 'One-click staff check-in', href: '/employees/attendance/bulk', icon: CircleDot, badge: unmarkedStaffCount.value },
    { label: t('suppliers', 'Active Suppliers & Dues'), desc: 'Pay balances & advances', href: '/suppliers/active', icon: Store },
    { label: t('supplier_directory', 'Supplier Directory'), desc: 'Contacts & price catalogs', href: '/suppliers/directory', icon: Store },
]);

// 4. Finance & Reports Group
const financeItems = computed(() => [
    { label: t('cash_reconciliation', 'Cash Drawer Reconciliation'), desc: 'Audit closing physical cash', href: '/cash-reconciliation', icon: Calculator },
    { label: t('expenses', 'Operating Expenses'), desc: 'Rent, electricity, repairs', href: '/expenses', icon: Receipt },
    { label: t('accounts', 'Cash Drawer & Bank'), desc: 'Transfers & balance ledger', href: '/accounts', icon: Landmark },
    { label: t('reports', 'Daily Profit & Loss'), desc: 'True Net Profit statement', href: '/reports/profit-loss', icon: TrendingUp },
    { label: t('transactions_report', 'Purchases & Expense History'), desc: 'Daily accounting ledger', href: '/reports/transactions', icon: Receipt },
    { label: t('audit_logs', 'Activity Audit Trail'), desc: 'Tamper-proof user logs', href: '/audit-logs', icon: ShieldCheck },
    { label: t('recycle_bin', 'Recycle Bin'), desc: 'Restore deleted records', href: '/recycle-bin', icon: Archive },
    { label: t('users', 'User Roles & Access'), desc: 'Admin, manager, viewer', href: '/users', icon: UserCog },
]);

const isActive = (href) => {
    if (href === '/') return page.url === '/';
    if (href.startsWith('/suppliers')) return page.url.startsWith('/suppliers');
    return page.url.startsWith(href);
};

const isGroupActive = (items) => {
    return items.some(it => isActive(it.href));
};

const handleLogout = () => {
    router.post('/logout');
};

const handleGlobalVoiceApply = (parsed) => {
    // Navigate user directly to Daily Costs with their spoken data
    router.get('/daily-costs');
};
</script>

<template>
    <div class="min-h-screen bg-gradient-to-b from-slate-950 via-[#0a0f18] to-slate-950 text-slate-100 flex flex-col font-sans select-none sm:select-auto">
        <!-- Top Sticky Header -->
        <header class="border-b border-amber-500/10 bg-slate-950/90 backdrop-blur-md sticky top-0 z-40 shadow-lg shadow-black/20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <!-- Brand / Logo -->
                <div class="flex items-center space-x-3">
                    <Link href="/" class="flex items-center space-x-3 group">
                        <div class="w-10 h-10 bg-gradient-to-br from-amber-400 via-amber-500 to-amber-600 rounded-2xl flex items-center justify-center shadow-lg shadow-amber-500/25 ring-1 ring-amber-300/40 group-hover:scale-105 transition duration-200">
                            <UtensilsCrossed class="w-5 h-5 text-slate-950 font-black" />
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-base font-extrabold text-white tracking-tight group-hover:text-amber-300 transition">Royal Nest</span>
                                <span class="px-1.5 py-0.5 text-[9px] font-extrabold tracking-wider bg-amber-500/15 border border-amber-500/30 text-amber-300 rounded-md uppercase">ERP</span>
                            </div>
                            <span class="block text-[11px] text-amber-400/80 font-medium -mt-0.5">Café & Restaurant Suite</span>
                        </div>
                    </Link>
                </div>

                <!-- Right Quick Actions & Profile -->
                <div class="flex items-center space-x-2 sm:space-x-3">
                    <!-- Quick Voice Assistant Pill in Top Bar -->
                    <VoiceInputButton
                        context-type="costs"
                        title="রয়্যাল নেস্ট দ্রুত ভয়েস এন্ট্রি"
                        hint='যেমন: "আলু ৫ কেজি ৫০০ টাকা ক্যাশ" বা "বিক্রি ২৫০০০ টাকা"'
                        button-class="px-3 py-1.5 bg-gradient-to-r from-amber-500/20 to-amber-600/10 hover:from-amber-500/30 hover:to-amber-600/20 text-amber-300 border border-amber-500/40 rounded-xl text-xs font-bold transition flex items-center gap-1.5 min-h-[38px] shadow-sm shadow-amber-500/10"
                        @apply="handleGlobalVoiceApply"
                    >
                        <span class="hidden sm:inline">ভয়েস এন্ট্রি</span>
                    </VoiceInputButton>

                    <!-- Attendance Alert Pill (If pending) -->
                    <Link
                        v-if="unmarkedStaffCount > 0"
                        href="/employees/attendance/bulk"
                        class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-500/15 border border-rose-500/40 text-rose-300 hover:bg-rose-500/25 text-xs font-bold transition animate-pulse"
                        title="Today's attendance pending"
                    >
                        <Clock class="w-3.5 h-3.5 text-rose-400 shrink-0" />
                        <span>{{ unmarkedStaffCount }} {{ t('attendance_pending', 'হাজিরা বাকি') }}</span>
                    </Link>

                    <!-- Language Switcher Toggle -->
                    <button
                        type="button"
                        @click="toggleLocale"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl border border-slate-800 bg-slate-900/90 hover:bg-slate-800 text-xs font-semibold text-slate-200 transition shadow-sm active:scale-95"
                        :title="currentLocale === 'bn' ? 'Switch to English' : 'বাংলা ভাষায় পরিবর্তন করুন'"
                    >
                        <Globe class="w-3.5 h-3.5 text-amber-400" />
                        <span class="text-xs font-bold">{{ currentLocale === 'bn' ? 'বাংলা' : 'EN' }}</span>
                    </button>

                    <!-- User Profile & Logout (Desktop) -->
                    <div v-if="authUser" class="hidden sm:flex items-center gap-2.5 pl-2.5 border-l border-slate-800">
                        <div class="text-right">
                            <span class="text-xs font-bold text-white block leading-tight">{{ authUser.name }}</span>
                            <span class="text-[10px] text-amber-400 font-mono uppercase tracking-wider">{{ authUser.role }}</span>
                        </div>
                        <button
                            type="button"
                            @click="handleLogout"
                            class="p-2 rounded-xl text-slate-400 hover:text-rose-400 hover:bg-rose-950/30 border border-transparent hover:border-rose-900/40 transition cursor-pointer"
                            :title="t('logout', 'Logout')"
                        >
                            <LogOut class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Desktop Clean Organized Navigation Bar (Grouped & Charming) -->
            <div class="hidden sm:block border-t border-slate-800/80 bg-slate-950/70">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between py-2">
                    <!-- Left: Core Shortcuts + 3 Clean Dropdown Hubs -->
                    <div class="flex items-center space-x-1 nav-dropdown-container">
                        <!-- Direct Core Links -->
                        <Link
                            v-for="item in coreNav"
                            :key="item.href"
                            :href="item.href"
                            :class="[
                                'px-3 py-1.5 rounded-xl text-xs font-semibold transition flex items-center space-x-1.5 relative',
                                isActive(item.href)
                                    ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-bold'
                                    : 'text-slate-300 hover:text-white hover:bg-slate-800/60'
                            ]"
                        >
                            <component :is="item.icon" class="w-3.5 h-3.5" />
                            <span>{{ item.label }}</span>
                            <span 
                                v-if="item.badge" 
                                :class="[
                                    'text-[9px] px-1 py-0.2 rounded-md font-mono uppercase',
                                    isActive(item.href) ? 'bg-slate-950/20 text-slate-950' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                                ]"
                            >
                                {{ item.badge }}
                            </span>
                        </Link>

                        <!-- Dropdown 1: Kitchen & Stock (কিচেন ও মালামাল) -->
                        <div class="relative">
                            <button
                                type="button"
                                @click="toggleDropdown('kitchen')"
                                :class="[
                                    'px-3 py-1.5 rounded-xl text-xs font-semibold transition flex items-center space-x-1.5',
                                    isGroupActive(kitchenItems) || activeDropdown === 'kitchen'
                                        ? 'bg-slate-800/90 text-amber-300 border border-amber-500/30'
                                        : 'text-slate-300 hover:text-white hover:bg-slate-800/60'
                                ]"
                            >
                                <ChefHat class="w-3.5 h-3.5 text-amber-400" />
                                <span>{{ t('kitchen_stock', 'কিচেন ও মালামাল') }}</span>
                                <ChevronDown class="w-3 h-3 text-slate-400 transition transform" :class="activeDropdown === 'kitchen' ? 'rotate-180 text-amber-400' : ''" />
                            </button>

                            <!-- Dropdown Panel -->
                            <div 
                                v-if="activeDropdown === 'kitchen'"
                                class="absolute top-full left-0 mt-2 w-72 bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-2 z-50 animate-fade-in divide-y divide-slate-800/60"
                            >
                                <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-amber-400">
                                    ইনভেন্টরি ও কাঁচামাল নিয়ন্ত্রণ
                                </div>
                                <div class="py-1 space-y-1">
                                    <Link
                                        v-for="sub in kitchenItems"
                                        :key="sub.href"
                                        :href="sub.href"
                                        @click="activeDropdown = null"
                                        :class="[
                                            'flex items-start gap-2.5 px-3 py-2 rounded-xl transition text-xs group',
                                            isActive(sub.href) ? 'bg-amber-500/20 text-amber-200' : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                                        ]"
                                    >
                                        <component :is="sub.icon" class="w-4 h-4 text-amber-400 shrink-0 mt-0.5 group-hover:scale-110 transition" />
                                        <div>
                                            <span class="font-semibold block leading-tight">{{ sub.label }}</span>
                                            <span class="text-[10px] text-slate-400 block">{{ sub.desc }}</span>
                                        </div>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Dropdown 2: Team & Vendors (কর্মী ও মহাজন) -->
                        <div class="relative">
                            <button
                                type="button"
                                @click="toggleDropdown('team')"
                                :class="[
                                    'px-3 py-1.5 rounded-xl text-xs font-semibold transition flex items-center space-x-1.5 relative',
                                    isGroupActive(teamItems) || activeDropdown === 'team'
                                        ? 'bg-slate-800/90 text-amber-300 border border-amber-500/30'
                                        : 'text-slate-300 hover:text-white hover:bg-slate-800/60'
                                ]"
                            >
                                <Users class="w-3.5 h-3.5 text-amber-400" />
                                <span>{{ t('team_vendors', 'কর্মী ও মহাজন') }}</span>
                                <span v-if="unmarkedStaffCount > 0" class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                                <ChevronDown class="w-3 h-3 text-slate-400 transition transform" :class="activeDropdown === 'team' ? 'rotate-180 text-amber-400' : ''" />
                            </button>

                            <!-- Dropdown Panel -->
                            <div 
                                v-if="activeDropdown === 'team'"
                                class="absolute top-full left-0 mt-2 w-72 bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-2 z-50 animate-fade-in divide-y divide-slate-800/60"
                            >
                                <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-amber-400">
                                    টিম ও সাপ্লায়ার হিসাব
                                </div>
                                <div class="py-1 space-y-1">
                                    <Link
                                        v-for="sub in teamItems"
                                        :key="sub.href"
                                        :href="sub.href"
                                        @click="activeDropdown = null"
                                        :class="[
                                            'flex items-start gap-2.5 px-3 py-2 rounded-xl transition text-xs group',
                                            isActive(sub.href) ? 'bg-amber-500/20 text-amber-200' : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                                        ]"
                                    >
                                        <component :is="sub.icon" class="w-4 h-4 text-amber-400 shrink-0 mt-0.5 group-hover:scale-110 transition" />
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between">
                                                <span class="font-semibold block leading-tight">{{ sub.label }}</span>
                                                <span v-if="sub.badge > 0" class="px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-rose-500 text-white">
                                                    {{ sub.badge }}
                                                </span>
                                            </div>
                                            <span class="text-[10px] text-slate-400 block">{{ sub.desc }}</span>
                                        </div>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Dropdown 3: Finance & Reports (হিসাব ও রিপোর্ট) -->
                        <div class="relative">
                            <button
                                type="button"
                                @click="toggleDropdown('finance')"
                                :class="[
                                    'px-3 py-1.5 rounded-xl text-xs font-semibold transition flex items-center space-x-1.5',
                                    isGroupActive(financeItems) || activeDropdown === 'finance'
                                        ? 'bg-slate-800/90 text-amber-300 border border-amber-500/30'
                                        : 'text-slate-300 hover:text-white hover:bg-slate-800/60'
                                ]"
                            >
                                <TrendingUp class="w-3.5 h-3.5 text-amber-400" />
                                <span>{{ t('finance_reports', 'হিসাব ও রিপোর্ট') }}</span>
                                <ChevronDown class="w-3 h-3 text-slate-400 transition transform" :class="activeDropdown === 'finance' ? 'rotate-180 text-amber-400' : ''" />
                            </button>

                            <!-- Dropdown Panel -->
                            <div 
                                v-if="activeDropdown === 'finance'"
                                class="absolute top-full right-0 sm:left-0 mt-2 w-72 bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-2 z-50 animate-fade-in divide-y divide-slate-800/60"
                            >
                                <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-amber-400">
                                    আর্থিক অডিট ও লাভ-ক্ষতি
                                </div>
                                <div class="py-1 space-y-1">
                                    <Link
                                        v-for="sub in financeItems"
                                        :key="sub.href"
                                        :href="sub.href"
                                        @click="activeDropdown = null"
                                        :class="[
                                            'flex items-start gap-2.5 px-3 py-2 rounded-xl transition text-xs group',
                                            isActive(sub.href) ? 'bg-amber-500/20 text-amber-200' : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                                        ]"
                                    >
                                        <component :is="sub.icon" class="w-4 h-4 text-amber-400 shrink-0 mt-0.5 group-hover:scale-110 transition" />
                                        <div>
                                            <span class="font-semibold block leading-tight">{{ sub.label }}</span>
                                            <span class="text-[10px] text-slate-400 block">{{ sub.desc }}</span>
                                        </div>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Quick Status Tag -->
                    <div class="hidden lg:flex items-center space-x-2 text-[11px] text-slate-400 font-medium">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>রিয়েল-টাইম FIFO ও ক্যাশ সমন্বয় সক্রিয়</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Attendance Missing Alert Banner (Global) -->
        <div 
            v-if="unmarkedStaffCount > 0 && page.url !== '/employees/attendance/bulk'" 
            class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-3 w-full animate-fade-in"
        >
            <div class="bg-gradient-to-r from-amber-950/80 via-slate-900 to-amber-950/80 border border-amber-500/40 text-amber-200 px-4 py-3 rounded-2xl flex items-center justify-between text-xs shadow-lg shadow-black/30">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold">
                        <Clock class="w-4 h-4 animate-spin" />
                    </div>
                    <div>
                        <strong class="text-white text-sm block leading-tight">{{ unmarkedStaffCount }} জন কর্মীর আজকের হাজিরা এখনো দেওয়া হয়নি!</strong>
                        <span class="text-[11px] text-amber-400/80">সঠিক দৈনিক বেতন ও কস্ট হিসাব নিশ্চিত করতে দ্রুত হাজিরা দিন।</span>
                    </div>
                </div>
                <Link
                    href="/employees/attendance/bulk"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold rounded-xl transition shrink-0 active:scale-95 shadow-md shadow-amber-500/20"
                >
                    <span>হাজিরা দিন</span>
                    <ArrowUpRight class="w-4 h-4" />
                </Link>
            </div>
        </div>

        <!-- Flash messages -->
        <div v-if="flash.success" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-3 w-full animate-fade-in">
            <div class="bg-emerald-950/80 border border-emerald-500/40 text-emerald-300 px-4 py-3 rounded-2xl flex items-center space-x-3 text-xs sm:text-sm shadow-md">
                <CheckCircle2 class="w-5 h-5 text-emerald-400 shrink-0" />
                <span class="font-medium">{{ flash.success }}</span>
            </div>
        </div>

        <div v-if="flash.error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-3 w-full animate-fade-in">
            <div class="bg-rose-950/80 border border-rose-500/40 text-rose-300 px-4 py-3 rounded-2xl flex items-center space-x-3 text-xs sm:text-sm shadow-md">
                <AlertCircle class="w-5 h-5 text-rose-400 shrink-0" />
                <span class="font-medium">{{ flash.error }}</span>
            </div>
        </div>

        <!-- Main Body Content with Safe Area Bottom Padding -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 pb-24 sm:pb-8">
            <slot />
        </main>

        <!-- Mobile Bottom Navigation Bar (Fixed at Screen Bottom with Charming Floating Center) -->
        <nav class="sm:hidden fixed bottom-0 inset-x-0 z-50 bg-slate-950/95 backdrop-blur-xl border-t border-slate-800 pb-[env(safe-area-inset-bottom)] shadow-2xl">
            <div class="grid grid-cols-5 h-16 items-center px-1">
                <!-- 1. Home / Overview -->
                <Link
                    href="/"
                    :class="[
                        'flex flex-col items-center justify-center py-1 transition relative',
                        isActive('/') ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200'
                    ]"
                >
                    <LayoutDashboard class="w-5 h-5 mb-0.5" />
                    <span class="text-[10px] leading-tight">{{ t('dashboard', 'হোম') }}</span>
                    <span v-if="isActive('/')" class="w-1.5 h-1.5 rounded-full bg-amber-400 mt-0.5"></span>
                </Link>

                <!-- 2. Daily Costs -->
                <Link
                    href="/daily-costs"
                    :class="[
                        'flex flex-col items-center justify-center py-1 transition relative',
                        isActive('/daily-costs') ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200'
                    ]"
                >
                    <Sparkles class="w-5 h-5 mb-0.5" />
                    <span class="text-[10px] leading-tight">{{ t('daily_costs', 'খরচ') }}</span>
                    <span v-if="isActive('/daily-costs')" class="w-1.5 h-1.5 rounded-full bg-amber-400 mt-0.5"></span>
                </Link>

                <!-- 3. Daily P&L Calculator (Prominent Central Floating Action) -->
                <Link
                    href="/calculator"
                    class="flex flex-col items-center justify-center -mt-5 transition group"
                >
                    <div 
                        :class="[
                            'w-13 h-13 rounded-2xl flex items-center justify-center shadow-xl transition active:scale-95',
                            isActive('/calculator') 
                                ? 'bg-amber-500 text-slate-950 shadow-amber-500/40 ring-4 ring-amber-400/30' 
                                : 'bg-gradient-to-br from-amber-500 via-amber-600 to-amber-700 text-slate-950 shadow-amber-500/25 ring-2 ring-amber-400/20'
                        ]"
                    >
                        <Calculator class="w-6 h-6 font-black" />
                    </div>
                    <span class="text-[10px] font-extrabold mt-1 text-amber-300">
                        {{ t('pnl_calculator', 'হিসাব') }}
                    </span>
                </Link>

                <!-- 4. Staff Management -->
                <Link
                    href="/employees"
                    :class="[
                        'flex flex-col items-center justify-center py-1 transition relative',
                        isActive('/employees') ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200'
                    ]"
                >
                    <Users class="w-5 h-5 mb-0.5" />
                    <span class="text-[10px] leading-tight">{{ t('employees', 'কর্মী') }}</span>
                    <span 
                        v-if="unmarkedStaffCount > 0" 
                        class="w-2.5 h-2.5 rounded-full bg-rose-500 ring-2 ring-slate-950 absolute top-1 right-4 animate-pulse"
                    ></span>
                    <span v-else-if="isActive('/employees')" class="w-1.5 h-1.5 rounded-full bg-amber-400 mt-0.5"></span>
                </Link>

                <!-- 5. More Menu Drawer Trigger -->
                <button
                    type="button"
                    @click="showMobileMenu = true"
                    :class="[
                        'flex flex-col items-center justify-center py-1 transition cursor-pointer',
                        showMobileMenu ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200'
                    ]"
                >
                    <Menu class="w-5 h-5 mb-0.5" />
                    <span class="text-[10px] leading-tight">{{ t('more_menu', 'মেনু') }}</span>
                </button>
            </div>
        </nav>

        <!-- Slide-Up Mobile Drawer Menu Modal -->
        <div 
            v-if="showMobileMenu" 
            class="fixed inset-0 z-50 flex flex-col justify-end sm:hidden bg-slate-950/80 backdrop-blur-md transition-opacity"
            @click.self="showMobileMenu = false"
        >
            <div class="bg-slate-900 border-t border-slate-800 rounded-t-3xl max-h-[85vh] overflow-y-auto p-5 shadow-2xl flex flex-col space-y-5 animate-slide-up">
                <!-- Drawer Header -->
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold">
                            <UtensilsCrossed class="w-5 h-5" />
                        </div>
                        <div>
                            <span class="text-sm font-bold text-white block">Royal Nest Menu</span>
                            <span class="text-[10px] text-slate-400">সকল মডিউল ও অপশনস</span>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        @click="showMobileMenu = false"
                        class="p-2 rounded-xl text-slate-400 hover:text-white bg-slate-800 transition"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Section 1: Kitchen & Stock -->
                <div>
                    <h3 class="text-[11px] font-bold text-amber-400 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                        <ChefHat class="w-3.5 h-3.5" />
                        <span>কিচেন ও কাঁচামাল</span>
                    </h3>
                    <div class="grid grid-cols-2 gap-2">
                        <Link
                            v-for="item in kitchenItems"
                            :key="item.href"
                            :href="item.href"
                            @click="showMobileMenu = false"
                            :class="[
                                'p-2.5 rounded-xl border flex items-center space-x-2 transition text-xs',
                                isActive(item.href) 
                                    ? 'bg-amber-500/20 border-amber-500/40 text-amber-200 font-bold' 
                                    : 'bg-slate-950 border-slate-800 text-slate-300 hover:text-white'
                            ]"
                        >
                            <component :is="item.icon" class="w-4 h-4 text-amber-400 shrink-0" />
                            <span class="truncate">{{ item.label }}</span>
                        </Link>
                    </div>
                </div>

                <!-- Section 2: Team & Suppliers -->
                <div>
                    <h3 class="text-[11px] font-bold text-amber-400 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                        <Users class="w-3.5 h-3.5" />
                        <span>টিম ও সাপ্লায়ার</span>
                    </h3>
                    <div class="grid grid-cols-2 gap-2">
                        <Link
                            v-for="item in teamItems"
                            :key="item.href"
                            :href="item.href"
                            @click="showMobileMenu = false"
                            :class="[
                                'p-2.5 rounded-xl border flex items-center space-x-2 transition text-xs',
                                isActive(item.href) 
                                    ? 'bg-amber-500/20 border-amber-500/40 text-amber-200 font-bold' 
                                    : 'bg-slate-950 border-slate-800 text-slate-300 hover:text-white'
                            ]"
                        >
                            <component :is="item.icon" class="w-4 h-4 text-amber-400 shrink-0" />
                            <span class="truncate">{{ item.label }}</span>
                        </Link>
                    </div>
                </div>

                <!-- Section 3: Finance & Reports -->
                <div>
                    <h3 class="text-[11px] font-bold text-amber-400 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                        <TrendingUp class="w-3.5 h-3.5" />
                        <span>হিসাব ও রিপোর্ট</span>
                    </h3>
                    <div class="grid grid-cols-2 gap-2">
                        <Link
                            v-for="item in financeItems"
                            :key="item.href"
                            :href="item.href"
                            @click="showMobileMenu = false"
                            :class="[
                                'p-2.5 rounded-xl border flex items-center space-x-2 transition text-xs',
                                isActive(item.href) 
                                    ? 'bg-amber-500/20 border-amber-500/40 text-amber-200 font-bold' 
                                    : 'bg-slate-950 border-slate-800 text-slate-300 hover:text-white'
                            ]"
                        >
                            <component :is="item.icon" class="w-4 h-4 text-amber-400 shrink-0" />
                            <span class="truncate">{{ item.label }}</span>
                        </Link>
                    </div>
                </div>

                <!-- User & Settings Footer -->
                <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs">
                    <div v-if="authUser" class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-xs">
                            {{ authUser.name.charAt(0) }}
                        </div>
                        <div>
                            <span class="text-xs font-bold text-white block">{{ authUser.name }}</span>
                            <span class="text-[10px] text-amber-400 uppercase font-mono">{{ authUser.role }}</span>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="handleLogout"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-950/60 border border-rose-900/50 text-rose-300 hover:bg-rose-900 text-xs font-bold transition"
                    >
                        <LogOut class="w-3.5 h-3.5" />
                        <span>{{ t('logout', 'লগআউট') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
