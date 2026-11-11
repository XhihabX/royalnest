<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ClipboardCheck, Plus, AlertTriangle, CheckCircle2, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    counts: Array,
    products: Array,
});

const showModal = ref(false);

const form = useForm({
    count_date: new Date().toISOString().split('T')[0],
    notes: '',
    items: [
        { product_id: '', counted_quantity: '', adjustment_reason: '' }
    ],
});

const getProduct = (productId) => {
    return props.products.find(p => p.id === productId);
};

const addItem = () => {
    form.items.push({ product_id: '', counted_quantity: '', adjustment_reason: '' });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const openModal = () => {
    form.reset();
    form.items = [{ product_id: '', counted_quantity: '', adjustment_reason: '' }];
    showModal.value = true;
};

const submit = () => {
    form.post('/stock-counts', {
        onSuccess: () => {
            showModal.value = false;
        }
    });
};
</script>

<template>
    <AppLayout title="Physical Stock Count & Audit">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Physical Stock Count & Reconciliation</h1>
                <p class="text-xs text-slate-400 mt-1">
                    Periodic physical counting vs perpetual system stock balances with traceable adjustment audit trails.
                </p>
            </div>

            <button 
                @click="openModal"
                class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2 rounded-xl text-xs flex items-center space-x-1.5 shadow-lg shadow-amber-500/10 transition cursor-pointer"
            >
                <Plus class="w-4 h-4" />
                <span>New Count Session</span>
            </button>
        </div>

        <!-- Stock Counts Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 bg-slate-950/60 border-b border-slate-800 font-semibold">
                            <th class="py-3 px-4">Count Date</th>
                            <th class="py-3 px-4">Items Counted & Discrepancies</th>
                            <th class="py-3 px-4">Audited By</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="c in counts" :key="c.id" class="hover:bg-slate-800/30">
                            <td class="py-3 px-4 font-mono font-medium text-slate-300">{{ c.count_date }}</td>
                            <td class="py-3 px-4 text-slate-300">
                                <div v-for="it in c.items" :key="it.id" class="py-1">
                                    <span class="font-bold text-white">{{ it.product?.name }}:</span>
                                    System: <span class="font-mono text-slate-400">{{ it.system_quantity }}</span>,
                                    Counted: <span class="font-mono text-white">{{ it.counted_quantity }}</span>
                                    (Diff: 
                                    <span 
                                        :class="Number(it.difference_quantity) >= 0 ? 'text-emerald-400 font-bold font-mono' : 'text-rose-400 font-bold font-mono'"
                                    >
                                        {{ Number(it.difference_quantity) > 0 ? `+${it.difference_quantity}` : it.difference_quantity }}
                                    </span>
                                    • Value: ৳{{ it.difference_value }})
                                </div>
                            </td>
                            <td class="py-3 px-4 text-slate-400">{{ c.creator?.name || 'Manager' }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    {{ c.status }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-500">{{ c.notes || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Stock Count Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-white flex items-center space-x-2">
                        <ClipboardCheck class="w-4 h-4 text-amber-400" />
                        <span>Physical Stock Count Audit</span>
                    </h2>
                </div>

                <form @submit.prevent="submit" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Count Date *</label>
                        <input 
                            type="date" 
                            v-model="form.count_date" 
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div class="pt-2">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Counted Items</span>
                            <button 
                                type="button" 
                                @click="addItem" 
                                class="text-xs text-amber-400 hover:text-amber-300 flex items-center space-x-1 cursor-pointer font-medium"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Add Item</span>
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
                                            <option value="" disabled>Select product counted</option>
                                            <option v-for="p in products" :key="p.id" :value="p.id">
                                                {{ p.name }} (System: {{ p.current_stock }} {{ p.base_unit }})
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-span-4">
                                        <div class="relative">
                                            <input 
                                                type="number" 
                                                step="0.0001" 
                                                v-model="item.counted_quantity" 
                                                required 
                                                placeholder="Counted qty"
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

                                <div v-if="getProduct(item.product_id) && item.counted_quantity !== ''" class="text-[11px] flex items-center justify-between px-1">
                                    <span class="text-slate-400">
                                        System: <strong class="text-white">{{ getProduct(item.product_id).current_stock }} {{ getProduct(item.product_id).base_unit }}</strong>
                                    </span>

                                    <span 
                                        :class="[
                                            'font-mono font-bold',
                                            (parseFloat(item.counted_quantity) - parseFloat(getProduct(item.product_id).current_stock)) >= 0 ? 'text-emerald-400' : 'text-rose-400'
                                        ]"
                                    >
                                        Difference: {{ (parseFloat(item.counted_quantity) - parseFloat(getProduct(item.product_id).current_stock)).toFixed(2) }} {{ getProduct(item.product_id).base_unit }}
                                        (৳{{ ((parseFloat(item.counted_quantity) - parseFloat(getProduct(item.product_id).current_stock)) * parseFloat(getProduct(item.product_id).cost_price)).toFixed(2) }})
                                    </span>
                                </div>

                                <div>
                                    <input 
                                        type="text" 
                                        v-model="item.adjustment_reason" 
                                        placeholder="Reason for discrepancy (e.g. Weighing variation, minor spillage)"
                                        class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1 text-slate-300 text-[11px] focus:border-amber-500 focus:outline-none"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Audit Notes</label>
                        <textarea 
                            v-model="form.notes" 
                            rows="2"
                            placeholder="Optional weekly audit remarks..."
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
                            class="bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-slate-950 font-bold px-4 py-2 rounded-xl transition cursor-pointer shadow-lg shadow-amber-500/10"
                        >
                            Confirm Count & Adjust Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

