<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { UtensilsCrossed, ChefHat, ReceiptText, ShieldCheck } from 'lucide-vue-next';

defineProps({
    restaurantName: {
        type: String,
        default: 'Royal Nest Café & Restaurant'
    },
    version: {
        type: String,
        default: '2.0.0'
    }
});
</script>

<template>
    <Head title="Welcome to Royal Nest ERP" />
    <div class="min-h-screen bg-slate-900 text-white flex flex-col justify-between p-6 md:p-12">
        <header class="flex items-center justify-between border-b border-slate-800 pb-6">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 bg-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-emerald-500/20">
                    <UtensilsCrossed class="w-6 h-6 text-white" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-white">{{ restaurantName }}</h1>
                    <p class="text-xs text-slate-400">Restaurant ERP Master • PRD v{{ version }}</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <ShieldCheck class="w-3.5 h-3.5 mr-1" /> Phase 0 System Initialized
                </span>
            </div>
        </header>

        <main class="my-auto py-12 max-w-4xl mx-auto w-full">
            <div class="text-center space-y-4 mb-12">
                <h2 class="text-3xl md:text-5xl font-extrabold tracking-tight text-white">
                    Next-Generation <span class="text-emerald-400">Restaurant ERP</span>
                </h2>
                <p class="text-slate-400 text-base md:text-lg max-w-2xl mx-auto">
                    A unified restaurant management and back-office enterprise system. Connecting front-of-house table service with kitchen stations, recipe inventory explosions, and double-entry general ledger accounting.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-slate-800/60 border border-slate-700/60 p-6 rounded-2xl hover:border-emerald-500/50 transition">
                    <div class="w-10 h-10 bg-emerald-500/10 text-emerald-400 rounded-lg flex items-center justify-center mb-4">
                        <UtensilsCrossed class="w-5 h-5" />
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">POS & Tables</h3>
                    <p class="text-sm text-slate-400">
                        Dine-in, takeaway, delivery, split billing, table status management, and instant kitchen ticket dispatching.
                    </p>
                </div>

                <div class="bg-slate-800/60 border border-slate-700/60 p-6 rounded-2xl hover:border-emerald-500/50 transition">
                    <div class="w-10 h-10 bg-emerald-500/10 text-emerald-400 rounded-lg flex items-center justify-center mb-4">
                        <ChefHat class="w-5 h-5" />
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Recipe BOM & Stock</h3>
                    <p class="text-sm text-slate-400">
                        Fractional ingredient deductions, perpetual stock ledger, multi-unit conversions, and theoretical food costing.
                    </p>
                </div>

                <div class="bg-slate-800/60 border border-slate-700/60 p-6 rounded-2xl hover:border-emerald-500/50 transition">
                    <div class="w-10 h-10 bg-emerald-500/10 text-emerald-400 rounded-lg flex items-center justify-center mb-4">
                        <ReceiptText class="w-5 h-5" />
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Double-Entry Ledger</h3>
                    <p class="text-sm text-slate-400">
                        Chart of accounts, balanced journal postings, AP/AR ageing, bKash/Nagad verification, and Mushak-6.3 tax reports.
                    </p>
                </div>
            </div>
        </main>

        <footer class="border-t border-slate-800 pt-6 flex flex-col md:flex-row items-center justify-between text-xs text-slate-500">
            <p>© 2026 Royal Nest Café & Restaurant. All rights reserved.</p>
            <p>Engineered on Laravel 12 + Inertia.js + Vue 3 + MySQL 8.4</p>
        </footer>
    </div>
</template>

