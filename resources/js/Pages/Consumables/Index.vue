<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PackageCheck, Plus, Calendar, Clock } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    allocations: Array,
    consumables: Array,
});

const showModal = ref(false);

const form = useForm({
    product_id: '',
    start_date: new Date().toISOString().split('T')[0],
    duration_days: 4,
    total_cost: '',
    notes: '',
});

const onProductSelect = () => {
    const prod = props.consumables.find(p => p.id === form.product_id);
    if (prod) {
        if (prod.default_allocation_days) {
            form.duration_days = prod.default_allocation_days;
        }
        form.total_cost = Number(prod.cost_price) || '';
    }
};

const openModal = () => {
    form.reset();
    form.duration_days = 4;
    showModal.value = true;
};

const submit = () => {
    form.post('/consumables', {
        onSuccess: () => {
            showModal.value = false;
        }
    });
};
</script>

<template>
    <AppLayout title="Consumables Cost Allocation">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">{{ t('consumables', 'Consumables Cost Amortization') }}</h1>
                <p class="text-xs text-slate-400 mt-1">
                    {{ t('consumables_desc', 'Multi-day allocation engine (e.g., ৳120 tissue box amortized over 4 days = ৳30/day) avoiding duplicate expense entries.') }}
                </p>
            </div>

            <button 
                @click="openModal"
                class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2 rounded-xl text-xs flex items-center space-x-1.5 shadow-lg shadow-amber-500/10 transition cursor-pointer"
            >
                <Plus class="w-4 h-4" />
                <span>{{ t('allocate_consumable', 'New Allocation Schedule') }}</span>
            </button>
        </div>

        <!-- Table of Allocations -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 bg-slate-950/60 border-b border-slate-800 font-semibold">
                            <th class="py-3 px-4">Consumable Item</th>
                            <th class="py-3 px-4">Start Date</th>
                            <th class="py-3 px-4">End Date</th>
                            <th class="py-3 px-4 text-center">Duration</th>
                            <th class="py-3 px-4 text-right">Total Cost</th>
                            <th class="py-3 px-4 text-right">Daily Cost Charged</th>
                            <th class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="a in allocations" :key="a.id" class="hover:bg-slate-800/30">
                            <td class="py-3 px-4 font-bold text-white">{{ a.product?.name }}</td>
                            <td class="py-3 px-4 font-mono text-slate-300">{{ a.start_date }}</td>
                            <td class="py-3 px-4 font-mono text-slate-300">{{ a.end_date }}</td>
                            <td class="py-3 px-4 text-center font-mono">{{ a.duration_days }} days</td>
                            <td class="py-3 px-4 text-right font-mono text-slate-200">৳{{ a.total_cost }}</td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-amber-300">
                                ৳{{ a.daily_allocated_cost }} / day
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    {{ a.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                <h2 class="text-base font-bold text-white mb-4">Create Multi-Day Cost Allocation</h2>

                <form @submit.prevent="submit" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Consumable Product *</label>
                        <select 
                            v-model="form.product_id" 
                            @change="onProductSelect"
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        >
                            <option value="" disabled>Select consumable item</option>
                            <option v-for="c in consumables" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Start Date *</label>
                            <input 
                                type="date" 
                                v-model="form.start_date" 
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Duration (Days) *</label>
                            <input 
                                type="number" 
                                min="1" 
                                v-model="form.duration_days" 
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Total Cost (৳) *</label>
                        <input 
                            type="number" 
                            step="0.01" 
                            v-model="form.total_cost" 
                            required
                            placeholder="e.g. 120.00"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div v-if="form.total_cost && form.duration_days" class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-[11px] font-mono text-amber-300">
                        Computed Daily Expense: ৳{{ (parseFloat(form.total_cost) / parseInt(form.duration_days)).toFixed(2) }} per day charged to P&L.
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Notes</label>
                        <input 
                            type="text" 
                            v-model="form.notes" 
                            placeholder="e.g. Dining hall tissue dispensers"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        />
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
                            Confirm Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

