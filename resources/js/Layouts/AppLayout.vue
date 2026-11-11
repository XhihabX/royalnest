<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { 
    UtensilsCrossed, 
    ChefHat, 
    Boxes, 
    BookOpen, 
    Layers, 
    LayoutDashboard,
    ArrowLeftRight,
    Scale
} from 'lucide-vue-next';

defineProps({
    title: String,
});

const page = usePage();
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
        <!-- Top Navbar -->
        <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <Link href="/" class="flex items-center space-x-3 group">
                        <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-emerald-500/20 group-hover:bg-emerald-500 transition">
                            <UtensilsCrossed class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <span class="text-base font-bold text-white tracking-tight">Royal Nest</span>
                            <span class="block text-xs text-slate-400 -mt-1 font-medium">Café & Restaurant ERP</span>
                        </div>
                    </Link>
                </div>

                <!-- Navigation Tabs -->
                <nav class="hidden md:flex items-center space-x-1">
                    <Link
                        href="/"
                        :class="[
                            'px-3 py-2 rounded-lg text-sm font-medium transition flex items-center space-x-2',
                            page.url === '/' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800'
                        ]"
                    >
                        <LayoutDashboard class="w-4 h-4" />
                        <span>Overview</span>
                    </Link>

                    <Link
                        href="/menu"
                        :class="[
                            'px-3 py-2 rounded-lg text-sm font-medium transition flex items-center space-x-2',
                            page.url.startsWith('/menu') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800'
                        ]"
                    >
                        <BookOpen class="w-4 h-4" />
                        <span>Menu & Dishes</span>
                    </Link>

                    <Link
                        href="/recipes"
                        :class="[
                            'px-3 py-2 rounded-lg text-sm font-medium transition flex items-center space-x-2',
                            page.url.startsWith('/recipes') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800'
                        ]"
                    >
                        <ChefHat class="w-4 h-4" />
                        <span>Recipe BOM & Costing</span>
                    </Link>

                    <Link
                        href="/inventory"
                        :class="[
                            'px-3 py-2 rounded-lg text-sm font-medium transition flex items-center space-x-2',
                            page.url.startsWith('/inventory') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800'
                        ]"
                    >
                        <Boxes class="w-4 h-4" />
                        <span>Stock Ledger</span>
                    </Link>
                </nav>

                <div class="flex items-center space-x-3">
                    <div class="hidden sm:flex flex-col text-right">
                        <span class="text-xs font-semibold text-slate-200">Main Branch</span>
                        <span class="text-[11px] text-emerald-400">BDT (৳) • Asia/Dhaka</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Body -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <slot />
        </main>
    </div>
</template>
