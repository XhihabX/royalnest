<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Trash2, Plus, AlertCircle } from 'lucide-vue-next';

const props = defineProps({
    wastages: Array,
    products: Array,
});

const showModal = ref(false);

const form = useForm({
    product_id: '',
    wastage_date: new Date().toISOString().split('T')[0],
    quantity: '',
    reason: 'spoiled',
    notes: '',
});

const selectedProduct = ref(null);

const onProductSelect = () => {
    selectedProduct.value = props.products.find(p => p.id === form.product_id);
};

const openModal = () => {
    form.reset();
    form.reason = 'spoiled';
    selectedProduct.value = null;
    showModal.value = true;
};

const submit = () => {
    form.post('/wastage', {
        onSuccess: () => {
            showModal.value = false;
        }
    });
};
</script>

<template>
    <AppLayout title="Wastage & Spoilage">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Ingredient Wastage & Spoilage</h1>
                <p class="text-xs text-slate-400 mt-1">
                    Trace burnt, expired, or spoiled materials. Automatically deducts from stock and charges wastage cost to operating P&L.
                </p>
            </div>

            <button 
                @click="openModal"
                class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2 rounded-xl text-xs flex items-center space-x-1.5 shadow-lg shadow-amber-500/10 transition cursor-pointer"
            >
                <Plus class="w-4 h-4" />
                <span>Record Wastage</span>
            </button>
        </div>

        <!-- Wastage Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 bg-slate-950/60 border-b border-slate-800 font-semibold">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Item</th>
                            <th class="py-3 px-4">Reason</th>
                            <th class="py-3 px-4 text-right">Qty Wasted</th>
                            <th class="py-3 px-4 text-right">Unit Cost</th>
                            <th class="py-3 px-4 text-right">Total Wastage Cost</th>
                            <th class="py-3 px-4">Reported By</th>
                            <th class="py-3 px-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="w in wastages" :key="w.id" class="hover:bg-slate-800/30">
                            <td class="py-3 px-4 font-mono font-medium text-slate-300">{{ w.wastage_date }}</td>
                            <td class="py-3 px-4 font-bold text-white">{{ w.product?.name }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                    {{ w.reason.replace('_', ' ') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-rose-400">
                                {{ w.quantity }} {{ w.product?.base_unit }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-slate-400">৳{{ w.unit_cost }}</td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-rose-400">৳{{ w.total_cost }}</td>
                            <td class="py-3 px-4 text-slate-400">{{ w.creator?.name || 'Staff' }}</td>
                            <td class="py-3 px-4 text-slate-500 max-w-xs truncate">{{ w.notes || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Wastage Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                <h2 class="text-base font-bold text-white mb-4">Record Ingredient Wastage</h2>

                <form @submit.prevent="submit" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Item *</label>
                        <select 
                            v-model="form.product_id" 
                            @change="onProductSelect"
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        >
                            <option value="" disabled>Select wasted ingredient</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">
                                {{ p.name }} (On hand: {{ p.current_stock }} {{ p.base_unit }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Wastage Date *</label>
                            <input 
                                type="date" 
                                v-model="form.wastage_date" 
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Reason *</label>
                            <select 
                                v-model="form.reason" 
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option value="spoiled">Spoiled / Rotten</option>
                                <option value="expired">Expired Date</option>
                                <option value="burnt">Burnt in Cooking</option>
                                <option value="accidental_loss">Accidental Drop/Spill</option>
                                <option value="other">Other Loss</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">
                            Quantity Wasted (in {{ selectedProduct?.base_unit || 'Base Units' }}) *
                        </label>
                        <input 
                            type="number" 
                            step="0.0001" 
                            v-model="form.quantity" 
                            required
                            placeholder="0.00"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div v-if="selectedProduct && form.quantity" class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-[11px] font-mono text-rose-300">
                        Wastage Value: ৳{{ (parseFloat(form.quantity) * parseFloat(selectedProduct.cost_price)).toFixed(2) }} charged to operating costs.
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Incident Notes</label>
                        <textarea 
                            v-model="form.notes" 
                            rows="2"
                            placeholder="e.g. Fridge temperature failure overnight"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:border-amber-500 focus:outline-none"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-800">
                        <button 
                            type="button" 
                            @click="showModal = false"
                            class="px-4 py-2 rounded-xl text-slate-400 hover:text-white transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-slate-950 font-bold px-4 py-2 rounded-xl transition cursor-pointer"
                        >
                            Record Wastage
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

