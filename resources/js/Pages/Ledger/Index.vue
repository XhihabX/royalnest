<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Layers, Filter, Search, RotateCcw } from 'lucide-vue-next';

const props = defineProps({
    ledgers: Object,
    products: Array,
    filters: Object,
});

const filterForm = ref({
    product_id: props.filters.product_id || '',
    movement_type: props.filters.movement_type || '',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
});

const applyFilters = () => {
    router.get('/ledger', filterForm.value, { preserveState: true });
};

const resetFilters = () => {
    filterForm.value = { product_id: '', movement_type: '', from_date: '', to_date: '' };
    router.get('/ledger', {}, { preserveState: true });
};
</script>

<template>
    <AppLayout title="Perpetual Stock Ledger">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Perpetual Stock Movement Ledger</h1>
                <p class="text-xs text-slate-400 mt-1">
                    Immutable audit trail of every stock increase, deduction, wastage, and count adjustment.
                </p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                <div>
                    <label class="block text-slate-400 mb-1">Product</label>
                    <select 
                        v-model="filterForm.product_id" 
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:border-amber-500 focus:outline-none"
                    >
                        <option value="">All Products</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-slate-400 mb-1">Movement Type</label>
                    <select 
                        v-model="filterForm.movement_type" 
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:border-amber-500 focus:outline-none"
                    >
                        <option value="">All Types</option>
                        <option value="opening_stock">Opening Stock</option>
                        <option value="purchase_receipt">Purchase Receipt</option>
                        <option value="consumption">Consumption</option>
                        <option value="wastage">Wastage</option>
                        <option value="adjustment_in">Adjustment In</option>
                        <option value="adjustment_out">Adjustment Out</option>
                        <option value="purchase_return">Purchase Return</option>
                    </select>
                </div>

                <div>
                    <label class="block text-slate-400 mb-1">From Date</label>
                    <input 
                        type="date" 
                        v-model="filterForm.from_date" 
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white font-mono focus:border-amber-500 focus:outline-none"
                    />
                </div>

                <div>
                    <label class="block text-slate-400 mb-1">To Date</label>
                    <input 
                        type="date" 
                        v-model="filterForm.to_date" 
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white font-mono focus:border-amber-500 focus:outline-none"
                    />
                </div>
            </div>

            <div class="flex items-center justify-end space-x-2 mt-3 pt-3 border-t border-slate-800/80">
                <button 
                    @click="resetFilters" 
                    class="px-3 py-1.5 rounded-xl text-xs text-slate-400 hover:text-white flex items-center space-x-1 cursor-pointer"
                >
                    <RotateCcw class="w-3.5 h-3.5" />
                    <span>Reset</span>
                </button>
                <button 
                    @click="applyFilters" 
                    class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-1.5 rounded-xl text-xs flex items-center space-x-1 cursor-pointer transition shadow-sm"
                >
                    <Filter class="w-3.5 h-3.5" />
                    <span>Filter Ledger</span>
                </button>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 bg-slate-950/60 border-b border-slate-800 font-semibold">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Item</th>
                            <th class="py-3 px-4">Movement Type</th>
                            <th class="py-3 px-4 text-right">Qty In</th>
                            <th class="py-3 px-4 text-right">Qty Out</th>
                            <th class="py-3 px-4 text-right">Balance After</th>
                            <th class="py-3 px-4 text-right">Unit Cost</th>
                            <th class="py-3 px-4 text-right">Value (৳)</th>
                            <th class="py-3 px-4">User</th>
                            <th class="py-3 px-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="l in ledgers.data" :key="l.id" class="hover:bg-slate-800/30">
                            <td class="py-3 px-4 font-mono font-medium text-slate-300">{{ l.transaction_date }}</td>
                            <td class="py-3 px-4 font-bold text-white">{{ l.product?.name }}</td>
                            <td class="py-3 px-4">
                                <span 
                                    :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider',
                                        l.movement_type === 'purchase_receipt' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                                        l.movement_type === 'consumption' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' :
                                        l.movement_type === 'wastage' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' :
                                        l.movement_type === 'opening_stock' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' :
                                        'bg-slate-800 text-slate-300'
                                    ]"
                                >
                                    {{ l.movement_type.replace('_', ' ') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-emerald-400">
                                {{ Number(l.quantity_in) > 0 ? `+${l.quantity_in}` : '-' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-rose-400">
                                {{ Number(l.quantity_out) > 0 ? `-${l.quantity_out}` : '-' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-200">
                                {{ l.balance_after }} {{ l.product?.base_unit }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-slate-300">৳{{ l.unit_cost }}</td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-white">৳{{ l.total_value }}</td>
                            <td class="py-3 px-4 text-slate-400">{{ l.user?.name || 'Admin' }}</td>
                            <td class="py-3 px-4 text-slate-500 max-w-xs truncate">{{ l.notes || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <div v-if="ledgers.links && ledgers.links.length > 3" class="p-4 border-t border-slate-800 flex justify-center space-x-1">
                <template v-for="(link, i) in ledgers.links" :key="i">
                    <span 
                        v-if="!link.url" 
                        class="px-3 py-1 text-slate-600 text-xs rounded-lg"
                        v-html="link.label"
                    />
                    <a 
                        v-else 
                        :href="link.url" 
                        :class="[
                            'px-3 py-1 text-xs rounded-lg transition',
                            link.active ? 'bg-amber-500 text-slate-950 font-bold' : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                        ]"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>

