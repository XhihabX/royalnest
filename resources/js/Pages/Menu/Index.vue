<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { ChefHat, Plus, CheckCircle2, AlertCircle, Sparkles } from 'lucide-vue-next';

defineProps({
    categories: Array,
    menuItems: Array,
    filters: Object,
});
</script>

<template>
    <AppLayout title="Menu Catalog">
        <Head title="Menu & Dishes — Royal Nest ERP" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>Menu & Dishes</span>
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            {{ menuItems.length }} Active Dishes
                        </span>
                    </h1>
                    <p class="text-sm text-slate-400 mt-1">
                        Configure restaurant dishes, selling prices, kitchen routing, and linked recipe BOMs.
                    </p>
                </div>
            </div>

            <!-- Categories Badges -->
            <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
                <Link
                    href="/menu"
                    :class="[
                        'px-4 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition border',
                        !filters.category ? 'bg-emerald-600 text-white border-emerald-500' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'
                    ]"
                >
                    All Categories
                </Link>
                <Link
                    v-for="cat in categories"
                    :key="cat.id"
                    :href="`/menu?category=${cat.id}`"
                    :class="[
                        'px-4 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition border',
                        filters.category == cat.id ? 'bg-emerald-600 text-white border-emerald-500' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'
                    ]"
                >
                    {{ cat.name }} ({{ cat.menu_items ? cat.menu_items.length : 0 }})
                </Link>
            </div>

            <!-- Dishes Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="item in menuItems"
                    :key="item.id"
                    class="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col justify-between hover:border-slate-700 transition"
                >
                    <div>
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <span class="text-xs font-medium px-2 py-0.5 rounded bg-slate-800 text-slate-300 uppercase tracking-wider">
                                {{ item.code }}
                            </span>
                            <span class="text-xs font-medium px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 capitalize">
                                {{ item.kitchen_station }} station
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-white mb-1.5">{{ item.name }}</h3>
                        <p class="text-xs text-slate-400 line-clamp-2 mb-4 leading-relaxed">
                            {{ item.description || 'No description provided.' }}
                        </p>
                    </div>

                    <div class="border-t border-slate-800/80 pt-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400">Selling Price:</span>
                            <span class="text-base font-extrabold text-white">
                                ৳{{ Number(item.selling_price).toFixed(2) }}
                                <span class="text-[10px] text-slate-500 font-normal">({{ item.vat_mode }} VAT)</span>
                            </span>
                        </div>

                        <!-- Recipe BOM Badge -->
                        <div v-if="item.recipe && item.recipe.active_version" class="p-2.5 rounded-xl bg-slate-800/50 border border-slate-800 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-1.5 text-emerald-400">
                                <ChefHat class="w-4 h-4" />
                                <span>Recipe Cost:</span>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-white">৳{{ Number(item.recipe.active_version.calculated_cost_price).toFixed(2) }}</span>
                                <span class="text-slate-400 text-[11px] ml-1.5">({{ item.recipe.active_version.food_cost_percentage }}% Food Cost)</span>
                            </div>
                        </div>

                        <div v-else class="p-2.5 rounded-xl bg-red-500/5 border border-red-500/20 flex items-center gap-2 text-xs text-red-400">
                            <AlertCircle class="w-4 h-4 shrink-0" />
                            <span>No Recipe BOM configured</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
