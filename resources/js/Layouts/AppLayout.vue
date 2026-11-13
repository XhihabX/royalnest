<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
    ChevronRight,
    Clock,
    ArrowUpRight
} from 'lucide-vue-next';
import { useI18n } from '../Composables/useI18n';

defineProps({
    title: String,
});

const page = usePage();
const flash = computed(() => page.props.flash || {});
const authUser = computed(() => page.props.auth?.user || null);
const unmarkedStaffCount = computed(() => page.props.unmarked_staff_count || 0);
const { t, currentLocale, toggleLocale } = useI18n();

const showMobileMenu = ref(false);

const navItems = computed(() => [
    { label: t('dashboard', 'Overview'), href: '/', icon: LayoutDashboard },
    { label: t('daily_costs', 'Daily Costs'), href: '/daily-costs', icon: Sparkles },
    { label: t('pnl_calculator', 'P&L Calculator'), href: '/calculator', icon: Calculator },
    { label: t('employees', 'Staff & Payroll'), href: '/employees', icon: Users, badge: unmarkedStaffCount.value },
    { label: t('daily_sales', 'Daily Sales'), href: '/sales', icon: ShoppingBag },
    { label: t('cash_reconciliation', 'Cash Reconciliation'), href: '/cash-reconciliation', icon: Calculator },
    { label: t('suppliers', 'Suppliers / Dealers'), href: '/suppliers/active', icon: Store },
    { label: t('purchases', 'Stock Receiving'), href: '/purchases', icon: Truck },
    { label: t('consumption', 'Kitchen Usage'), href: '/consumption', icon: Flame },
    { label: t('consumables', 'Consumables'), href: '/consumables', icon: PackageCheck },
    { label: t('ledger', 'Stock Ledger'), href: '/ledger', icon: Layers },
    { label: t('wastage', 'Wastage'), href: '/wastage', icon: Trash2 },
    { label: t('stock_counts', 'Stock Count'), href: '/stock-counts', icon: ClipboardCheck },
    { label: t('products', 'Products'), href: '/products', icon: Boxes },
    { label: t('expenses', 'Operating Costs'), href: '/expenses', icon: Receipt },
    { label: t('accounts', 'Cash & Accounts'), href: '/accounts', icon: Landmark },
    { label: t('transactions_report', 'Daily Purchases & Expenses'), href: '/reports/transactions', icon: Receipt },
    { label: t('reports', 'Profit & Loss'), href: '/reports/profit-loss', icon: TrendingUp },
]);

// Grouped items for mobile drawer
const mobileInventoryItems = computed(() => [
    { label: t('daily_sales', 'Daily Sales'), href: '/sales', icon: ShoppingBag },
    { label: t('cash_reconciliation', 'Cash Reconciliation'), href: '/cash-reconciliation', icon: Calculator },
    { label: t('purchases', 'Stock Receiving'), href: '/purchases', icon: Truck },
    { label: t('consumption', 'Kitchen Usage (FIFO)'), href: '/consumption', icon: Flame },
    { label: t('consumables', 'Consumables'), href: '/consumables', icon: PackageCheck },
    { label: t('ledger', 'Stock Ledger'), href: '/ledger', icon: Layers },
    { label: t('wastage', 'Wastage & Spoilage'), href: '/wastage', icon: Trash2 },
    { label: t('stock_counts', 'Stock Count'), href: '/stock-counts', icon: ClipboardCheck },
    { label: t('products', 'Raw Materials Catalog'), href: '/products', icon: Boxes },
]);

const mobileFinanceItems = computed(() => [
    { label: t('suppliers', 'Suppliers & Due Ledger'), href: '/suppliers/active', icon: Store },
    { label: t('supplier_directory', 'Supplier Directory'), href: '/suppliers/directory', icon: Store },
    { label: t('expenses', 'Operating Costs'), href: '/expenses', icon: Receipt },
    { label: t('transactions_report', 'Daily Purchases & Expenses'), href: '/reports/transactions', icon: Receipt },
    { label: t('accounts', 'Cash Drawer & Bank'), href: '/accounts', icon: Landmark },
    { label: t('reports', 'Profit & Loss Statement'), href: '/reports/profit-loss', icon: TrendingUp },
]);

const isActive = (href) => {
    if (href === '/') return page.url === '/';
    if (href.startsWith('/suppliers')) return page.url.startsWith('/suppliers');
    return page.url.startsWith(href);
};

const handleLogout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans select-none sm:select-auto">
        <!-- Top Sticky Header -->
        <header class="border-b border-slate-800 bg-slate-900/95 backdrop-blur sticky top-0 z-40 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 sm:h-16 flex items-center justify-between">
                <div class="flex items-center space-x-2.5">
                    <Link href="/" class="flex items-center space-x-2.5 group">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl flex items-center justify-center shadow-lg shadow-amber-500/20 group-hover:from-amber-400 group-hover:to-amber-500 transition">
                            <UtensilsCrossed class="w-4 h-4 sm:w-5 sm:h-5 text-slate-950 font-bold" />
                        </div>
                        <div>
                            <span class="text-sm sm:text-base font-bold text-white tracking-tight">Royal Nest</span>
                            <span class="block text-[10px] sm:text-xs text-amber-400 -mt-0.5 font-medium">Café & Restaurant</span>
                        </div>
                    </Link>
                </div>

                <div class="flex items-center space-x-2 sm:space-x-3">
                    <!-- Attendance Alert Pill (If pending) -->
                    <Link
                        v-if="unmarkedStaffCount > 0"
                        href="/employees/attendance/bulk"
                        class="hidden xs:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-300 hover:bg-amber-500/25 text-[11px] font-bold animate-pulse transition"
                        title="Today's attendance pending"
                    >
                        <Clock class="w-3.5 h-3.5 text-amber-400 shrink-0" />
                        <span>{{ unmarkedStaffCount }} {{ t('attendance_pending', 'হাজিরা বাকি') }}</span>
                    </Link>

                    <!-- Language Switcher Toggle -->
                    <button
                        type="button"
                        @click="toggleLocale"
                        class="inline-flex items-center gap-1 px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-lg border border-slate-700 bg-slate-800/80 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition shadow-sm active:scale-95"
                        :title="currentLocale === 'bn' ? 'Switch to English' : 'বাংলা ভাষায় পরিবর্তন করুন'"
                    >
                        <Globe class="w-3.5 h-3.5 text-amber-400" />
                        <span class="text-[11px] sm:text-xs">{{ currentLocale === 'bn' ? 'বাংলা' : 'EN' }}</span>
                    </button>

                    <!-- User Profile & Logout (Desktop) -->
                    <div v-if="authUser" class="hidden sm:flex items-center gap-2 pl-2 border-l border-slate-800">
                        <div class="text-right">
                            <span class="text-xs font-semibold text-slate-200 block leading-tight">{{ authUser.name }}</span>
                            <span class="text-[10px] text-amber-400 font-mono uppercase tracking-wider">{{ authUser.role }}</span>
                        </div>
                        <button
                            type="button"
                            @click="handleLogout"
                            class="p-2 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-950/30 border border-transparent hover:border-rose-900/40 transition cursor-pointer"
                            :title="t('logout', 'Logout')"
                        >
                            <LogOut class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Desktop Horizontal Navigation Bar -->
            <div class="hidden sm:block border-t border-slate-800/80 bg-slate-900/60 overflow-x-auto scrollbar-none">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center space-x-1 py-1.5 min-w-max">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-medium transition flex items-center space-x-1.5 whitespace-nowrap relative',
                            isActive(item.href)
                                ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30 shadow-sm font-semibold'
                                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/70'
                        ]"
                    >
                        <component :is="item.icon" class="w-3.5 h-3.5" />
                        <span>{{ item.label }}</span>
                        <span 
                            v-if="item.badge > 0" 
                            class="w-2 h-2 rounded-full bg-rose-500 animate-ping absolute top-1 right-1"
                        ></span>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Attendance Missing Alert Banner (Global) -->
        <div 
            v-if="unmarkedStaffCount > 0 && page.url !== '/employees/attendance/bulk'" 
            class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-3 w-full"
        >
            <div class="bg-gradient-to-r from-amber-950/80 via-slate-900 to-amber-950/80 border border-amber-500/40 text-amber-200 px-4 py-2.5 rounded-xl flex items-center justify-between text-xs shadow-md">
                <div class="flex items-center space-x-2">
                    <Clock class="w-4 h-4 text-amber-400 shrink-0 animate-spin" />
                    <span>
                        <strong class="text-white">{{ unmarkedStaffCount }} {{ t('staff_unmarked_today', 'জন কর্মীর আজকের হাজিরা এখনো দেওয়া হয়নি!') }}</strong>
                    </span>
                </div>
                <Link
                    href="/employees/attendance/bulk"
                    class="inline-flex items-center gap-1 px-3 py-1 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-lg transition shrink-0 active:scale-95 shadow"
                >
                    <span>{{ t('mark_attendance_now', 'হাজিরা দিন') }}</span>
                    <ArrowUpRight class="w-3.5 h-3.5" />
                </Link>
            </div>
        </div>

        <!-- Flash messages -->
        <div v-if="flash.success" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-3 w-full">
            <div class="bg-emerald-950/80 border border-emerald-500/40 text-emerald-300 px-4 py-2.5 rounded-xl flex items-center space-x-2.5 text-xs sm:text-sm shadow-md">
                <CheckCircle2 class="w-4 h-4 sm:w-5 sm:h-5 text-emerald-400 shrink-0" />
                <span>{{ flash.success }}</span>
            </div>
        </div>

        <div v-if="flash.error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-3 w-full">
            <div class="bg-rose-950/80 border border-rose-500/40 text-rose-300 px-4 py-2.5 rounded-xl flex items-center space-x-2.5 text-xs sm:text-sm shadow-md">
                <AlertCircle class="w-4 h-4 sm:w-5 sm:h-5 text-rose-400 shrink-0" />
                <span>{{ flash.error }}</span>
            </div>
        </div>

        <!-- Main Body Content with Safe Area Bottom Padding -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 pb-24 sm:pb-8">
            <slot />
        </main>

        <!-- Mobile Bottom Navigation Bar (Fixed at Screen Bottom) -->
        <nav class="sm:hidden fixed bottom-0 inset-x-0 z-50 bg-slate-900/95 backdrop-blur-lg border-t border-slate-800 pb-[env(safe-area-inset-bottom)] shadow-2xl">
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
                    <span v-if="isActive('/')" class="w-1 h-1 rounded-full bg-amber-400 mt-0.5"></span>
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
                    <span v-if="isActive('/daily-costs')" class="w-1 h-1 rounded-full bg-amber-400 mt-0.5"></span>
                </Link>

                <!-- 3. Daily P&L Calculator (Prominent Central Action) -->
                <Link
                    href="/calculator"
                    :class="[
                        'flex flex-col items-center justify-center -mt-4 transition group',
                    ]"
                >
                    <div 
                        :class="[
                            'w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg transition active:scale-95',
                            isActive('/calculator') 
                                ? 'bg-amber-500 text-slate-950 shadow-amber-500/30 ring-2 ring-amber-400' 
                                : 'bg-gradient-to-br from-slate-800 to-slate-900 text-amber-400 border border-amber-500/30'
                        ]"
                    >
                        <Calculator class="w-6 h-6" />
                    </div>
                    <span class="text-[10px] font-bold mt-1" :class="isActive('/calculator') ? 'text-amber-400' : 'text-slate-300'">
                        {{ t('pnl_calculator', 'ক্যালকুলেটর') }}
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
                        class="w-2.5 h-2.5 rounded-full bg-rose-500 ring-2 ring-slate-900 absolute top-1 right-4 animate-pulse"
                    ></span>
                    <span v-else-if="isActive('/employees')" class="w-1 h-1 rounded-full bg-amber-400 mt-0.5"></span>
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
            class="fixed inset-0 z-50 flex flex-col justify-end sm:hidden bg-slate-950/80 backdrop-blur-sm transition-opacity"
            @click.self="showMobileMenu = false"
        >
            <div class="bg-slate-900 border-t border-slate-800 rounded-t-3xl max-h-[85vh] overflow-y-auto p-5 shadow-2xl flex flex-col space-y-5 animate-slide-up">
                <!-- Drawer Header -->
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold">
                            <UtensilsCrossed class="w-4 h-4" />
                        </div>
                        <div>
                            <span class="text-sm font-bold text-white block">Royal Nest ERP</span>
                            <span class="text-[10px] text-slate-400">All Modules & Shortcuts</span>
                        </div>
                    </div>
                    <button 
                        type="button"
                        @click="showMobileMenu = false"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white bg-slate-800/60 transition"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Section 1: Operations & Inventory -->
                <div>
                    <h3 class="text-[11px] font-bold text-amber-400 uppercase tracking-wider mb-2">
                        {{ t('operations_inventory', 'ইনভেন্টরি ও পরিচালনা') }}
                    </h3>
                    <div class="grid grid-cols-2 gap-2">
                        <Link
                            v-for="item in mobileInventoryItems"
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

                <!-- Section 2: Finance & Suppliers -->
                <div>
                    <h3 class="text-[11px] font-bold text-amber-400 uppercase tracking-wider mb-2">
                        {{ t('finance_suppliers', 'অর্থ ও সরবরাহকারী') }}
                    </h3>
                    <div class="grid grid-cols-2 gap-2">
                        <Link
                            v-for="item in mobileFinanceItems"
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
                <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between text-xs">
                    <div v-if="authUser" class="flex items-center space-x-2">
                        <div class="w-7 h-7 rounded-full bg-slate-800 flex items-center justify-center font-bold text-amber-400 text-xs">
                            {{ authUser.name.charAt(0) }}
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-white block">{{ authUser.name }}</span>
                            <span class="text-[10px] text-amber-400 uppercase font-mono">{{ authUser.role }}</span>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="handleLogout"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-950/60 border border-rose-900/50 text-rose-300 hover:bg-rose-900 text-xs font-bold transition"
                    >
                        <LogOut class="w-3.5 h-3.5" />
                        <span>{{ t('logout', 'লগআউট') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
