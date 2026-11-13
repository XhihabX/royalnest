<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Landmark, ArrowLeftRight, PlusCircle, ArrowUpRight, ArrowDownRight, CheckCircle2 } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    accounts: Array,
    transactions: Array,
});

const activeAccounts = computed(() => props.accounts.filter(a => a.is_active));

const showTransferModal = ref(false);
const showAdjustModal = ref(false);

const toggleStatus = (account) => {
    router.post(`/accounts/${account.id}/toggle-status`, {}, {
        preserveScroll: true,
    });
};

const transferForm = useForm({
    from_account_id: '',
    to_account_id: '',
    amount: '',
    transaction_date: new Date().toISOString().split('T')[0],
    notes: '',
});

const adjustForm = useForm({
    financial_account_id: '',
    type: 'deposit',
    amount: '',
    transaction_date: new Date().toISOString().split('T')[0],
    notes: '',
});

const openTransfer = () => {
    transferForm.reset();
    showTransferModal.value = true;
};

const openAdjust = () => {
    adjustForm.reset();
    adjustForm.type = 'deposit';
    showAdjustModal.value = true;
};

const submitTransfer = () => {
    transferForm.post('/accounts/transfer', {
        onSuccess: () => {
            showTransferModal.value = false;
        }
    });
};

const submitAdjust = () => {
    adjustForm.post('/accounts/adjust', {
        onSuccess: () => {
            showAdjustModal.value = false;
        }
    });
};
</script>

<template>
    <AppLayout title="Financial Accounts & Cash">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">{{ t('accounts', 'Financial Accounts & Cash Management') }}</h1>
                <p class="text-xs text-slate-400 mt-1">
                    {{ t('accounts_desc', 'Track Cash Drawer, bKash, Nagad, and Bank accounts with neutral internal liquidity transfers.') }}
                </p>
            </div>

            <div class="flex items-center space-x-3">
                <button 
                    @click="openAdjust"
                    class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold px-3.5 py-2 rounded-xl text-xs flex items-center space-x-1.5 transition cursor-pointer"
                >
                    <PlusCircle class="w-4 h-4 text-emerald-400" />
                    <span>{{ t('adjust_balance', 'Deposit / Withdrawal') }}</span>
                </button>

                <button 
                    @click="openTransfer"
                    class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2 rounded-xl text-xs flex items-center space-x-1.5 shadow-lg shadow-amber-500/10 transition cursor-pointer"
                >
                    <ArrowLeftRight class="w-4 h-4" />
                    <span>{{ t('transfer_funds', 'Transfer Funds') }}</span>
                </button>
            </div>
        </div>

        <!-- Account Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div 
                v-for="acc in accounts" 
                :key="acc.id"
                class="bg-slate-900 border border-slate-800 rounded-2xl p-5"
                :class="{ 'opacity-60 border-slate-800/50': !acc.is_active }"
            >
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider font-mono">{{ acc.type }}</span>
                        <span :class="['px-1.5 py-0.5 rounded text-[10px] font-bold uppercase', acc.is_active ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400']">
                            {{ acc.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <button
                        type="button"
                        @click="toggleStatus(acc)"
                        :class="[
                            'text-[10px] px-2 py-0.5 rounded-lg font-bold transition cursor-pointer',
                            acc.is_active ? 'bg-rose-500/20 text-rose-300 hover:bg-rose-500/30' : 'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30'
                        ]"
                        :title="acc.is_active ? 'Deactivate account' : 'Activate account'"
                    >
                        {{ acc.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </div>
                <h3 class="text-base font-bold text-white mb-2">{{ acc.name }}</h3>
                <div class="text-2xl font-black font-mono text-emerald-400 mb-2">
                    ৳{{ Number(acc.current_balance).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </div>
                <div class="text-[11px] text-slate-500 flex justify-between border-t border-slate-800/80 pt-2 font-mono">
                    <span>Opening: ৳{{ acc.opening_balance }}</span>
                    <span v-if="acc.account_number">#{{ acc.account_number }}</span>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-sm font-bold text-white">Financial Account Transaction History</h2>
                <span class="text-[11px] text-slate-400 font-medium">Internal transfers are profit-neutral</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 bg-slate-950/60 border-b border-slate-800 font-semibold">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Account</th>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4 text-right">Amount (৳)</th>
                            <th class="py-3 px-4">Counterparty / Related</th>
                            <th class="py-3 px-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="t in transactions" :key="t.id" class="hover:bg-slate-800/30">
                            <td class="py-3 px-4 font-mono font-medium text-slate-300">{{ t.transaction_date }}</td>
                            <td class="py-3 px-4 font-bold text-white">{{ t.financial_account?.name }}</td>
                            <td class="py-3 px-4">
                                <span 
                                    :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider',
                                        t.type === 'deposit' || t.type === 'transfer_in' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'
                                    ]"
                                >
                                    {{ t.type.replace('_', ' ') }}
                                </span>
                            </td>
                            <td 
                                class="py-3 px-4 text-right font-mono font-bold"
                                :class="t.type === 'deposit' || t.type === 'transfer_in' ? 'text-emerald-400' : 'text-rose-400'"
                            >
                                {{ t.type === 'deposit' || t.type === 'transfer_in' ? '+' : '-' }}৳{{ Number(t.amount).toFixed(2) }}
                            </td>
                            <td class="py-3 px-4 text-slate-300">{{ t.related_account?.name || '-' }}</td>
                            <td class="py-3 px-4 text-slate-500 max-w-xs truncate">{{ t.notes || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transfer Modal -->
        <div v-if="showTransferModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                <h2 class="text-base font-bold text-white mb-2 flex items-center space-x-2">
                    <ArrowLeftRight class="w-4 h-4 text-amber-400" />
                    <span>Internal Account Transfer</span>
                </h2>
                <p class="text-xs text-slate-400 mb-4">
                    Transfers between the business's own accounts move liquidity without altering operating income or expenses.
                </p>

                <form @submit.prevent="submitTransfer" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Transfer From (Origin Account) *</label>
                        <select 
                            v-model="transferForm.from_account_id" 
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        >
                            <option value="" disabled>Select source account</option>
                            <option v-for="a in activeAccounts" :key="a.id" :value="a.id">
                                {{ a.name }} (Available: ৳{{ a.current_balance }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Transfer To (Destination Account) *</label>
                        <select 
                            v-model="transferForm.to_account_id" 
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        >
                            <option value="" disabled>Select target account</option>
                            <option v-for="a in activeAccounts" :key="a.id" :value="a.id">
                                {{ a.name }} (Current: ৳{{ a.current_balance }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Amount (৳) *</label>
                            <input 
                                type="number" 
                                step="0.01" 
                                v-model="transferForm.amount" 
                                required
                                placeholder="0.00"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono font-bold focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Date *</label>
                            <input 
                                type="date" 
                                v-model="transferForm.transaction_date" 
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Transfer Notes</label>
                        <input 
                            type="text" 
                            v-model="transferForm.notes" 
                            placeholder="e.g. End of day cash deposit to bank"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-800">
                        <button 
                            type="button" 
                            @click="showTransferModal = false"
                            class="px-4 py-2 rounded-xl text-slate-400 hover:text-white transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="transferForm.processing"
                            class="bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-slate-950 font-bold px-4 py-2 rounded-xl transition cursor-pointer"
                        >
                            Confirm Transfer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Adjust Modal -->
        <div v-if="showAdjustModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                <h2 class="text-base font-bold text-white mb-4">Manual Deposit / Withdrawal Adjustment</h2>

                <form @submit.prevent="submitAdjust" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Financial Account *</label>
                        <select 
                            v-model="adjustForm.financial_account_id" 
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        >
                            <option value="" disabled>Select account</option>
                            <option v-for="a in activeAccounts" :key="a.id" :value="a.id">
                                {{ a.name }} (Current: ৳{{ a.current_balance }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Action *</label>
                            <select 
                                v-model="adjustForm.type" 
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option value="deposit">Deposit (Cash In / Capital)</option>
                                <option value="withdrawal">Withdrawal (Drawings)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Amount (৳) *</label>
                            <input 
                                type="number" 
                                step="0.01" 
                                v-model="adjustForm.amount" 
                                required
                                placeholder="0.00"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono font-bold focus:border-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Date *</label>
                        <input 
                            type="date" 
                            v-model="adjustForm.transaction_date" 
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Notes *</label>
                        <input 
                            type="text" 
                            v-model="adjustForm.notes" 
                            required
                            placeholder="e.g. Owner capital injection or float balancing"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-800">
                        <button 
                            type="button" 
                            @click="showAdjustModal = false"
                            class="px-4 py-2 rounded-xl text-slate-400 hover:text-white transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="adjustForm.processing"
                            class="bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-slate-950 font-bold px-4 py-2 rounded-xl transition cursor-pointer"
                        >
                            Confirm Adjustment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

