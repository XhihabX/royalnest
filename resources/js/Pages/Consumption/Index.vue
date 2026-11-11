<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Flame, Plus, Trash2, AlertTriangle, CheckCircle2 } from 'lucide-vue-next';

const props = defineProps({
    consumptions: Array,
    products: Array,
});

const showModal = ref(false);

const form = useForm({
    consumption_date: new Date().toISOString().split('T')[0],
    notes: '',
    items: [
        { product_id: '', quantity_used: '', notes: '' }
    ],
});

const getProduct = (productId) => {
    return props.products.find(p => p.id === productId);
};

const addItem = () => {
    form.items.push({ product_id: '', quantity_used: '', notes: '' });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const hasNegativeStockWarning = computed(() => {
    return form.items.some(item => {
        if (!item.product_id || !item.quantity_used) return false;
        const prod = getProduct(item.product_id);
        if (!prod) return false;
        return parseFloat(item.quantity_used) > parseFloat(prod.current_stock);
    });
});

const computedTotalCost = computed(() => {
    return form.items.reduce((sum, item) => {
        if (!item.product_id || !item.quantity_used) return sum;
        const prod = getProduct(item.product_id);
        if (!prod) return sum;
        return sum + (parseFloat(item.quantity_used) * parseFloat(prod.cost_price));
    }, 0);
});

const openModal = () => {
    form.reset();
    form.items = [{ product_id: '', quantity_used: '', notes: '' }];
    showModal.value = true;
};

const submit = () => {
    if (hasNegativeStockWarning.value) {
        alert('Cannot confirm consumption: requested quantity exceeds available on-hand stock for one or more items (Negative Stock Prevention).');
        return;
    }

    form.post('/consumption', {
        onSuccess: () => {
            showModal.value = false;
        }
    });
};
</script>

<template>
    <AppLayout title="Daily Ingredient Consumption">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Daily Ingredient Consumption Entry</h1>
                <p class="text-xs text-slate-400 mt-1">
                    Manual end-of-day ingredient usage recording with strict Negative Stock Prevention and moving-average cost deduction.
                </p>
            </div>

            <button 
                @click="openModal"
                class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2 rounded-xl text-xs flex items-center space-x-1.5 shadow-lg shadow-amber-500/10 transition cursor-pointer"
            >
                <Plus class="w-4 h-4" />
                <span>Record Daily Consumption</span>
            </button>
        </div>

        <!-- History of Consumptions -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 bg-slate-950/60 border-b border-slate-800 font-semibold">
                            <th class="py-3 px-4">Consumption Date</th>
                            <th class="py-3 px-4">Ingredients Used</th>
                            <th class="py-3 px-4 text-right">Total Cost Recognized</th>
                            <th class="py-3 px-4">Logged By</th>
                            <th class="py-3 px-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="c in consumptions" :key="c.id" class="hover:bg-slate-800/30">
                            <td class="py-3 px-4 font-mono font-medium text-slate-200">{{ c.consumption_date }}</td>
                            <td class="py-3 px-4 text-slate-300">
                                <span v-for="(it, i) in c.items" :key="it.id">
                                    {{ it.product?.name }}: <span class="font-mono text-amber-300">{{ it.quantity_used }} {{ it.product?.base_unit }}</span> (৳{{ it.total_cost }}){{ i < c.items.length - 1 ? ', ' : '' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-amber-300">
                                ৳{{ Number(c.total_cost).toFixed(2) }}
                            </td>
                            <td class="py-3 px-4 text-slate-400">{{ c.creator?.name }}</td>
                            <td class="py-3 px-4 text-slate-500">{{ c.notes || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal for Recording Consumption -->
        <div v-if="showModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-white flex items-center space-x-2">
                        <Flame class="w-4 h-4 text-amber-400" />
                        <span>Record End-of-Day Usage</span>
                    </h2>
                    <span class="text-xs font-mono text-amber-400 font-bold">Total Cost: ৳{{ computedTotalCost.toFixed(2) }}</span>
                </div>

                <form @submit.prevent="submit" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Consumption Date</label>
                        <input 
                            type="date" 
                            v-model="form.consumption_date" 
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <!-- Items Section -->
                    <div class="pt-2">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Used Ingredients (Base Units)</span>
                            <button 
                                type="button" 
                                @click="addItem" 
                                class="text-xs text-amber-400 hover:text-amber-300 flex items-center space-x-1 cursor-pointer font-medium"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Add Ingredient</span>
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div 
                                v-for="(item, idx) in form.items" 
                                :key="idx"
                                class="p-3 rounded-xl bg-slate-950/70 border border-slate-800 space-y-2"
                            >
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-7">
                                        <select 
                                            v-model="item.product_id" 
                                            required
                                            class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white focus:border-amber-500 focus:outline-none"
                                        >
                                            <option value="" disabled>Select product to consume</option>
                                            <option v-for="p in products" :key="p.id" :value="p.id">
                                                {{ p.name }} (On hand: {{ p.current_stock }} {{ p.base_unit }})
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-span-4">
                                        <div class="relative">
                                            <input 
                                                type="number" 
                                                step="0.0001" 
                                                v-model="item.quantity_used" 
                                                required 
                                                placeholder="Quantity used"
                                                class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono focus:border-amber-500 focus:outline-none text-right"
                                            />
                                            <span v-if="getProduct(item.product_id)" class="absolute right-2 top-1.5 text-[11px] text-slate-400 pointer-events-none">
                                                {{ getProduct(item.product_id).base_unit }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col-span-1 text-center">
                                        <button 
                                            type="button" 
                                            @click="removeItem(idx)" 
                                            class="text-slate-500 hover:text-rose-400 p-1 cursor-pointer"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Live Remaining Stock & Negative Stock Prevention Guard -->
                                <div v-if="getProduct(item.product_id)" class="text-[11px] flex items-center justify-between px-1">
                                    <span class="text-slate-400">
                                        Available: <strong class="text-white">{{ getProduct(item.product_id).current_stock }} {{ getProduct(item.product_id).base_unit }}</strong>
                                        • Cost: ৳{{ getProduct(item.product_id).cost_price }}/{{ getProduct(item.product_id).base_unit }}
                                    </span>

                                    <span 
                                        v-if="item.quantity_used && parseFloat(item.quantity_used) > parseFloat(getProduct(item.product_id).current_stock)"
                                        class="text-rose-400 font-bold flex items-center space-x-1"
                                    >
                                        <AlertTriangle class="w-3 h-3" />
                                        <span>Exceeds Stock! (Negative Stock Blocked)</span>
                                    </span>
                                    <span 
                                        v-else-if="item.quantity_used"
                                        class="text-emerald-400 font-medium"
                                    >
                                        Remaining: {{ (parseFloat(getProduct(item.product_id).current_stock) - parseFloat(item.quantity_used)).toFixed(2) }} {{ getProduct(item.product_id).base_unit }}
                                        • Item Cost: ৳{{ (parseFloat(item.quantity_used) * parseFloat(getProduct(item.product_id).cost_price)).toFixed(2) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Notes</label>
                        <textarea 
                            v-model="form.notes" 
                            rows="2"
                            placeholder="Optional kitchen shift notes..."
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
                            :disabled="form.processing || hasNegativeStockWarning"
                            class="bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-slate-950 font-bold px-4 py-2 rounded-xl transition cursor-pointer shadow-lg shadow-amber-500/10"
                        >
                            Confirm Daily Usage
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

