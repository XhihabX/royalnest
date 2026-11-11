<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Receipt, Plus } from 'lucide-vue-next';

const props = defineProps({
    expenses: Array,
    accounts: Array,
});

const showModal = ref(false);

const form = useForm({
    expense_date: new Date().toISOString().split('T')[0],
    category: 'electricity',
    amount: '',
    payment_account_id: '',
    payee: '',
    notes: '',
});

const openModal = () => {
    form.reset();
    form.category = 'electricity';
    showModal.value = true;
};

const submit = () => {
    form.post('/expenses', {
        onSuccess: () => {
            showModal.value = false;
        }
    });
};
</script>

<template>
    <AppLayout title="Operating Expenses">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Daily Operating Expenses</h1>
                <p class="text-xs text-slate-400 mt-1">
                    Track utility bills, rent, gas, maintenance and operating costs strictly separated from inventory purchases.
                </p>
            </div>

            <button 
                @click="openModal"
                class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2 rounded-xl text-xs flex items-center space-x-1.5 shadow-lg shadow-amber-500/10 transition cursor-pointer"
            >
                <Plus class="w-4 h-4" />
                <span>Add Expense</span>
            </button>
        </div>

        <!-- Expenses Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 bg-slate-950/60 border-b border-slate-800 font-semibold">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Category</th>
                            <th class="py-3 px-4">Payee / Vendor</th>
                            <th class="py-3 px-4 text-right">Amount (৳)</th>
                            <th class="py-3 px-4">Paid From</th>
                            <th class="py-3 px-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="e in expenses" :key="e.id" class="hover:bg-slate-800/30">
                            <td class="py-3 px-4 font-mono font-medium text-slate-300">{{ e.expense_date }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-800 text-slate-300">
                                    {{ e.category }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-medium text-white">{{ e.payee || '-' }}</td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-rose-400">
                                ৳{{ Number(e.amount).toFixed(2) }}
                            </td>
                            <td class="py-3 px-4 text-slate-400">{{ e.payment_account?.name || 'Unlinked' }}</td>
                            <td class="py-3 px-4 text-slate-500 max-w-xs truncate">{{ e.notes || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Expense Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                <h2 class="text-base font-bold text-white mb-4">Record Operating Expense</h2>

                <form @submit.prevent="submit" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Date *</label>
                        <input 
                            type="date" 
                            v-model="form.expense_date" 
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Expense Category *</label>
                        <select 
                            v-model="form.category" 
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        >
                            <option value="electricity">Electricity / DESCO</option>
                            <option value="gas">Gas / Titas</option>
                            <option value="water">Water / WASA</option>
                            <option value="rent">Restaurant Rent</option>
                            <option value="transport">Transport & Conveyance</option>
                            <option value="repairs">Repairs & Maintenance</option>
                            <option value="cleaning">Cleaning & Hygiene</option>
                            <option value="marketing">Marketing & Promotion</option>
                            <option value="other">Other Operating Expense</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Amount (৳) *</label>
                        <input 
                            type="number" 
                            step="0.01" 
                            v-model="form.amount" 
                            required
                            placeholder="0.00"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono font-bold focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Payee / Vendor</label>
                        <input 
                            type="text" 
                            v-model="form.payee" 
                            placeholder="e.g. Building Landlord, Electrician"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Paid From Account</label>
                        <select 
                            v-model="form.payment_account_id" 
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        >
                            <option value="">None / External</option>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">
                                {{ a.name }} (Available: ৳{{ a.current_balance }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Notes</label>
                        <input 
                            type="text" 
                            v-model="form.notes" 
                            placeholder="e.g. October monthly electricity bill"
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
                            Record Expense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

