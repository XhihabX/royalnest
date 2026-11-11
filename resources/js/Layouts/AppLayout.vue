<script setup>
import { Link, usePage } from '@inertiajs/vue3';
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
    CheckCircle2
} from 'lucide-vue-next';

defineProps({
    title: String,
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

const navItems = [
    { label: 'Overview', href: '/', icon: LayoutDashboard },
    { label: 'Daily Sales', href: '/sales', icon: ShoppingBag },
    { label: 'Stock Receiving', href: '/purchases', icon: Truck },
    { label: 'Daily Usage', href: '/consumption', icon: Flame },
    { label: 'Consumables', href: '/consumables', icon: PackageCheck },
    { label: 'Stock Ledger', href: '/ledger', icon: Layers },
    { label: 'Wastage', href: '/wastage', icon: Trash2 },
    { label: 'Stock Count', href: '/stock-counts', icon: ClipboardCheck },
    { label: 'Products', href: '/products', icon: Boxes },
    { label: 'Operating Costs', href: '/expenses', icon: Receipt },
    { label: 'Cash & Accounts', href: '/accounts', icon: Landmark },
    { label: 'Staff & Payroll', href: '/employees', icon: Users },
    { label: 'Profit & Loss', href: '/reports/profit-loss', icon: TrendingUp },
];

const isActive = (href) => {
    if (href === '/') return page.url === '/';
    return page.url.startsWith(href);
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
                            <span class="block text-xs text-amber-400 -mt-0.5 font-medium">Cost & Profit Engine</span>
                        </div>
                    </Link>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="text-right hidden sm:block">
                        <span class="text-xs font-semibold text-slate-200">Main Restaurant</span>
                        <span class="block text-[11px] text-emerald-400 font-mono">Perpetual Stock Active • ৳ BDT</span>
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
