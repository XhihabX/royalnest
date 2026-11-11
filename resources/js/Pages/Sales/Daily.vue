<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ShoppingBag, AlertTriangle, CheckCircle2, Calendar } from 'lucide-vue-next';

const props = defineProps({
    sales: Array,
});

const form = useForm({
    sales_date: new Date().toISOString().split('T')[0],
    total_sales: '',
    cash_sales: '',
    bkash_sales: '',
    nagad_sales: '',
    card_bank_sales: '',
    other_sales: '',
    notes: '',
});

const paymentSum = computed(() => {
    const c = parseFloat(form.cash_sales) || 0;
    const b = parseFloat(form.bkash_sales) || 0;
    const n = parseFloat(form.nagad_sales) || 0;
    const card = parseFloat(form.card_bank_sales) || 0;
    const o = parseFloat(form.other_sales) || 0;
    return Number((c + b + n + card + o).toFixed(2));
});

const discrepancy = computed(() => {
    const total = parseFloat(form.total_sales) || 0;
    return Number((total - paymentSum.value).toFixed(2));
});

const submit = () => {
    form.post('/sales', {
        onSuccess: () => {
            form.reset('total_sales', 'cash_sales', 'bkash_sales', 'nagad_sales', 'card_bank_sales', 'other_sales', 'notes');
        }
    });
};
</script>

<template>
    <AppLayout title="Daily Sales & Income">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 1 Col: Daily Sales Input Form -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
                <div class="flex items-center space-x-2 mb-4">
                    <ShoppingBag class="w-5 h-5 text-amber-400" />
                    <h2 class="text-base font-bold text-white">Record Daily Sales</h2>
                </div>
                <p class="text-xs text-slate-400 mb-5">
                    Manual end-of-day sales entry with automated payment method reconciliation (Cash, bKash, Nagad, Card).
                </p>

                <form @submit.prevent="submit" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Sales Date</label>
                        <input 
                            type="date" 
                            v-model="form.sales_date" 
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Total Gross Sales (৳)</label>
                        <input 
                            type="number" 
                            step="0.01" 
                            v-model="form.total_sales" 
                            required 
                            placeholder="0.00"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono font-bold text-sm focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div class="pt-2 border-t border-slate-800/80">
                        <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-2">
                            Payment Breakdown
                        </span>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-400 mb-1">Cash (৳)</label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    v-model="form.cash_sales" 
                                    placeholder="0.00"
                                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white font-mono focus:border-amber-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label class="block text-slate-400 mb-1">bKash (৳)</label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    v-model="form.bkash_sales" 
                                    placeholder="0.00"
                                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white font-mono focus:border-amber-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label class="block text-slate-400 mb-1">Nagad (৳)</label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    v-model="form.nagad_sales" 
                                    placeholder="0.00"
                                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white font-mono focus:border-amber-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label class="block text-slate-400 mb-1">Card / Bank (৳)</label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    v-model="form.card_bank_sales" 
                                    placeholder="0.00"
                                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white font-mono focus:border-amber-500 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Discrepancy indicator -->
                    <div 
                        v-if="form.total_sales" 
                        :class="[
                            'p-3 rounded-xl border text-xs font-mono flex items-center justify-between',
                            discrepancy === 0 
                                ? 'bg-emerald-950/40 border-emerald-500/30 text-emerald-300' 
                                : 'bg-amber-950/40 border-amber-500/30 text-amber-300'
                        ]"
                    >
                        <span>Breakdown Sum: ৳{{ paymentSum }}</span>
                        <span v-if="discrepancy === 0" class="flex items-center text-emerald-400 font-bold">
                            <CheckCircle2 class="w-3.5 h-3.5 mr-1" /> Reconciled
                        </span>
                        <span v-else class="flex items-center text-amber-400 font-bold">
                            <AlertTriangle class="w-3.5 h-3.5 mr-1" /> Discrepancy: ৳{{ discrepancy }}
                        </span>
                    </div>

                    <div>
                        <label class="block text-slate-400 mb-1">Notes / Discrepancy Reason</label>
                        <textarea 
                            v-model="form.notes" 
                            rows="2"
                            placeholder="Optional shift notes..."
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:border-amber-500 focus:outline-none"
                        ></textarea>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="form.processing"
                        class="w-full bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-slate-950 font-bold py-2.5 rounded-xl transition shadow-lg shadow-amber-500/10 cursor-pointer"
                    >
                        Save Daily Sales
                    </button>
                </form>
            </div>

            <!-- Right 2 Cols: Sales History -->
            <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-6">
                <h2 class="text-base font-bold text-white mb-4">Historical Daily Sales</h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-800 font-semibold">
                                <th class="pb-2.5">Date</th>
                                <th class="pb-2.5 text-right">Total Sales</th>
                                <th class="pb-2.5 text-right">Cash</th>
                                <th class="pb-2.5 text-right">bKash</th>
                                <th class="pb-2.5 text-right">Nagad</th>
                                <th class="pb-2.5 text-right">Card</th>
                                <th class="pb-2.5 text-center">Reconciliation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="s in sales" :key="s.id" class="hover:bg-slate-800/30">
                                <td class="py-3 font-mono font-medium text-slate-200">{{ s.sales_date }}</td>
                                <td class="py-3 text-right font-mono font-bold text-white">৳{{ s.total_sales }}</td>
                                <td class="py-3 text-right font-mono text-slate-300">৳{{ s.cash_sales }}</td>
                                <td class="py-3 text-right font-mono text-slate-300">৳{{ s.bkash_sales }}</td>
                                <td class="py-3 text-right font-mono text-slate-300">৳{{ s.nagad_sales }}</td>
                                <td class="py-3 text-right font-mono text-slate-300">৳{{ s.card_bank_sales }}</td>
                                <td class="py-3 text-center">
                                    <span 
                                        v-if="Number(s.discrepancy_amount) === 0"
                                        class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"
                                    >
                                        Reconciled
                                    </span>
                                    <span 
                                        v-else
                                        class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20"
                                    >
                                        Discrepancy ৳{{ s.discrepancy_amount }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

