<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
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
    UserCircle,
    Sparkles,
    Calculator
} from 'lucide-vue-next';
import { useI18n } from '../Composables/useI18n';

defineProps({
    title: String,
});

const page = usePage();
const flash = computed(() => page.props.flash || {});
const authUser = computed(() => page.props.auth?.user || null);
const { t, currentLocale, toggleLocale } = useI18n();

const navItems = computed(() => [
    { label: t('dashboard', 'Overview'), href: '/', icon: LayoutDashboard },
    { label: t('daily_sales', 'Daily Sales'), href: '/sales', icon: ShoppingBag },
    { label: t('daily_costs', 'Daily Costs'), href: '/daily-costs', icon: Sparkles },
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
    { label: t('employees', 'Staff & Payroll'), href: '/employees', icon: Users },
    { label: t('reports', 'Profit & Loss'), href: '/reports/profit-loss', icon: TrendingUp },
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
    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans">
        <!-- Top Navbar -->
        <header class="border-b border-slate-800 bg-slate-900/90 backdrop-blur sticky top-0 z-40 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <Link href="/" class="flex items-center space-x-3 group">
                        <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl flex items-center justify-center shadow-lg shadow-amber-500/20 group-hover:from-amber-400 group-hover:to-amber-500 transition">
                            <UtensilsCrossed class="w-5 h-5 text-slate-950 font-bold" />
                        </div>
                        <div>
                            <span class="text-base font-bold text-white tracking-tight">Royal Nest</span>
                            <span class="block text-xs text-amber-400 -mt-0.5 font-medium">Café & Restaurant ERP</span>
                        </div>
                    </Link>
                </div>

                <div class="flex items-center space-x-3">
                    <!-- Language Switcher Toggle -->
                    <button
                        type="button"
                        @click="toggleLocale"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800/80 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition shadow-sm active:scale-95"
                        :title="currentLocale === 'bn' ? 'Switch to English' : 'বাংলা ভাষায় পরিবর্তন করুন'"
                    >
                        <Globe class="w-3.5 h-3.5 text-amber-400" />
                        <span>{{ currentLocale === 'bn' ? 'বাংলা (BN)' : 'English (EN)' }}</span>
                    </button>

                    <!-- User Profile & Logout -->
                    <div v-if="authUser" class="flex items-center gap-2 pl-2 border-l border-slate-800">
                        <div class="hidden sm:block text-right">
                            <span class="text-xs font-semibold text-slate-200 block leading-tight">{{ authUser.name }}</span>
                            <span class="text-[10px] text-amber-400 font-mono uppercase tracking-wider">{{ authUser.role }}</span>
                        </div>
                        <button
                            type="button"
                            @click="handleLogout"
                            class="p-2 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-950/30 border border-transparent hover:border-rose-900/40 transition"
                            :title="t('logout', 'Logout')"
                        >
                            <LogOut class="w-4 h-4" />
                        </button>
                    </div>

                    <div v-else class="text-right hidden sm:block">
                        <span class="text-xs font-semibold text-slate-200">Main Restaurant</span>
                        <span class="block text-[11px] text-emerald-400 font-mono">Perpetual Stock • ৳ BDT</span>
                    </div>
                </div>
            </div>

            <!-- Horizontal Navigation Bar -->
            <div class="border-t border-slate-800/80 bg-slate-900/60 overflow-x-auto scrollbar-none">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center space-x-1 py-1.5 min-w-max">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-medium transition flex items-center space-x-1.5 whitespace-nowrap',
                            isActive(item.href)
                                ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30 shadow-sm'
                                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/70'
                        ]"
                    >
                        <component :is="item.icon" class="w-3.5 h-3.5" />
                        <span>{{ item.label }}</span>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Flash messages -->
        <div v-if="flash.success" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 w-full">
            <div class="bg-emerald-950/80 border border-emerald-500/40 text-emerald-300 px-4 py-3 rounded-xl flex items-center space-x-3 text-sm shadow-md">
                <CheckCircle2 class="w-5 h-5 text-emerald-400 shrink-0" />
                <span>{{ flash.success }}</span>
            </div>
        </div>

        <div v-if="flash.error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 w-full">
            <div class="bg-rose-950/80 border border-rose-500/40 text-rose-300 px-4 py-3 rounded-xl flex items-center space-x-3 text-sm shadow-md">
                <AlertCircle class="w-5 h-5 text-rose-400 shrink-0" />
                <span>{{ flash.error }}</span>
            </div>
        </div>

        <!-- Main Body Content -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <slot />
        </main>
    </div>
</template>
