<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    Store, 
    BookOpen, 
    Search, 
    Wallet, 
    ArrowUpRight, 
    CreditCard, 
    AlertCircle, 
    FileText, 
    Phone, 
    Calendar, 
    Plus, 
    X,
    Send
} from 'lucide-vue-next';
import { useI18n } from '../../Composables/useI18n';

const props = defineProps({
    suppliers: Array,
    stats: Object,
    accounts: Array,
    filters: Object,
});

const { t } = useI18n();

const search = ref(props.filters?.search || '');
const showPayModal = ref(false);
const showAdvanceModal = ref(false);
const activeSupplier = ref(null);

const applyFilters = () => {
    router.get('/suppliers/active', {
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
};

// Form: Pay Due
const payForm = useForm({
    amount: '',
    payment_date: new Date().toISOString().split('T')[0],
    payment_account_id: props.accounts.length > 0 ? props.accounts[0].id : '',
    payment_method: 'cash',
    notes: '',
});

const openPayModal = (supplier) => {
    activeSupplier.value = supplier;
    payForm.amount = supplier.current_balance > 0 ? supplier.current_balance : '';
    payForm.payment_date = new Date().toISOString().split('T')[0];
    payForm.payment_account_id = props.accounts.length > 0 ? props.accounts[0].id : '';
    payForm.payment_method = 'cash';
    payForm.notes = '';
    showPayModal.value = true;
};

const submitPayDue = () => {
    if (!activeSupplier.value) return;
    payForm.post(`/suppliers/${activeSupplier.value.id}/pay-due`, {
        onSuccess: () => {
            showPayModal.value = false;
            activeSupplier.value = null;
            payForm.reset();
        },
    });
};

// Form: Issue Advance
const advanceForm = useForm({
    amount: '',
    advance_date: new Date().toISOString().split('T')[0],
    payment_account_id: props.accounts.length > 0 ? props.accounts[0].id : '',
    payment_method: 'cash',
    reason: '',
});

const openAdvanceModal = (supplier) => {
    activeSupplier.value = supplier;
    advanceForm.amount = '';
    advanceForm.advance_date = new Date().toISOString().split('T')[0];
    advanceForm.payment_account_id = props.accounts.length > 0 ? props.accounts[0].id : '';
    advanceForm.payment_method = 'cash';
    advanceForm.reason = '';
    showAdvanceModal.value = true;
};

const submitAdvance = () => {
    if (!activeSupplier.value) return;
    advanceForm.post(`/suppliers/${activeSupplier.value.id}/advance`, {
        onSuccess: () => {
            showAdvanceModal.value = false;
            activeSupplier.value = null;
            advanceForm.reset();
        },
    });
};
</script>

<template>
    <AppLayout title="Active Supplier Management">
        <Head :title="t('active_suppliers', 'Active Suppliers & Dues') + ' — Royal Nest'" />

        <div class="space-y-6">
            <!-- Distinct Top Navigation Bar (Directory vs Active) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-4">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                        <Store class="w-6 h-6 text-amber-500" />
                        <span>{{ t('active_suppliers', 'Active Supplier Management') }}</span>
                    </h1>
                    <p class="text-xs text-slate-400 mt-1">
                        Live accounts, confirmed purchase bills, payment vouchers, and outstanding due settlements.
                    </p>
                </div>

                <!-- Two Distinct Section Switch Tabs -->
                <div class="flex items-center gap-1 bg-slate-900 border border-slate-800 p-1 rounded-xl self-start sm:self-auto">
                    <Link
                        href="/suppliers/directory"
                        class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition flex items-center gap-1.5"
                    >
                        <BookOpen class="w-3.5 h-3.5" />
                        <span>{{ t('supplier_directory', 'Supplier Directory') }}</span>
                    </Link>
                    <Link
                        href="/suppliers/active"
                        class="px-4 py-2 rounded-lg text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center gap-1.5 shadow-sm"
                    >
                        <Store class="w-3.5 h-3.5" />
                        <span>{{ t('active_suppliers', 'Active Suppliers & Dues') }}</span>
                    </Link>
                </div>
            </div>

            <!-- Financial Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Active Vendors</span>
                        <span class="text-2xl font-bold text-slate-100 mt-0.5 block">{{ stats.active_count }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <Store class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-medium text-rose-400 block">{{ t('total_payable_due', 'Total Outstanding Dues') }}</span>
                        <span class="text-2xl font-bold font-mono text-rose-400 mt-0.5 block">৳ {{ stats.total_payable.toLocaleString() }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                        <AlertCircle class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-medium text-slate-400 block">{{ t('total_purchased', 'Total Bills Received') }}</span>
                        <span class="text-2xl font-bold font-mono text-amber-400 mt-0.5 block">৳ {{ stats.total_purchased.toLocaleString() }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <CreditCard class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-medium text-slate-400 block">{{ t('paid_amount', 'Total Paid / Vouchers') }}</span>
                        <span class="text-2xl font-bold font-mono text-emerald-400 mt-0.5 block">৳ {{ stats.total_paid.toLocaleString() }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <Wallet class="w-5 h-5" />
                    </div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="flex items-center gap-3">
                <div class="relative flex-1 max-w-md">
                    <Search class="w-4 h-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="search"
                        @input="applyFilters"
                        type="text"
                        :placeholder="t('search', 'Search by vendor name, company, or phone...')"
                        class="w-full bg-slate-900 border border-slate-800 rounded-xl pl-10 pr-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500"
                    />
                </div>
            </div>

            <!-- Active Suppliers Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-950/70 border-b border-slate-800 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3.5">{{ t('supplier_name', 'Supplier Details') }}</th>
                                <th class="px-4 py-3.5">{{ t('phone', 'Phone & Contact') }}</th>
                                <th class="px-4 py-3.5 text-right">{{ t('total_purchased', 'Total Bills') }}</th>
                                <th class="px-4 py-3.5 text-right">{{ t('paid_amount', 'Total Paid') }}</th>
                                <th class="px-4 py-3.5 text-right font-bold">{{ t('current_due', 'Outstanding Due') }}</th>
                                <th class="px-4 py-3.5 text-center">Last Activity</th>
                                <th class="px-5 py-3.5 text-right">{{ t('actions', 'Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="supplier in suppliers" :key="supplier.id" class="hover:bg-slate-800/40 transition">
                                <td class="px-5 py-4">
                                    <div class="font-bold text-slate-100 text-base flex items-center gap-2">
                                        <Link :href="`/suppliers/${supplier.id}/profile`" class="hover:text-amber-400 hover:underline">
                                            {{ supplier.name }}
                                        </Link>
                                    </div>
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        {{ supplier.company_name ? supplier.company_name + ' • ' : '' }}
                                        {{ supplier.business_location || supplier.address || 'Dhaka' }}
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div v-if="supplier.contact_person" class="text-xs font-semibold text-slate-300">{{ supplier.contact_person }}</div>
                                    <div v-if="supplier.phone" class="text-xs text-amber-400 font-mono flex items-center gap-1 mt-0.5">
                                        <Phone class="w-3 h-3 text-slate-500" />
                                        <a :href="'tel:' + supplier.phone" class="hover:underline">{{ supplier.phone }}</a>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="font-mono text-slate-200">৳ {{ Number(supplier.total_purchased).toLocaleString() }}</div>
                                    <div class="text-[11px] text-slate-500">{{ supplier.purchases_count }} bills</div>
                                </td>
                                <td class="px-4 py-4 text-right font-mono text-emerald-400">
                                    ৳ {{ Number(supplier.total_paid).toLocaleString() }}
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div
                                        :class="[
                                            'inline-block px-3 py-1 rounded-lg font-mono font-bold text-sm',
                                            supplier.current_balance > 0 
                                                ? 'bg-rose-950/60 text-rose-400 border border-rose-800/40' 
                                                : 'bg-emerald-950/60 text-emerald-400 border border-emerald-800/40'
                                        ]"
                                    >
                                        ৳ {{ Number(supplier.current_balance).toLocaleString() }}
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center text-xs text-slate-400">
                                    <div v-if="supplier.last_purchase">
                                        <span class="text-slate-500 text-[10px] block">Bill:</span>
                                        <span class="font-mono text-slate-300">{{ supplier.last_purchase }}</span>
                                    </div>
                                    <div v-else class="text-slate-600">—</div>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Pay Due Button -->
                                        <button
                                            type="button"
                                            @click="openPayModal(supplier)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-500/30 text-xs font-semibold transition"
                                        >
                                            <Wallet class="w-3.5 h-3.5" />
                                            <span>{{ t('pay_due', 'Pay Due') }}</span>
                                        </button>

                                        <!-- Advance Button -->
                                        <button
                                            type="button"
                                            @click="openAdvanceModal(supplier)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-xs font-medium transition"
                                            title="Issue Advance Payment"
                                        >
                                            <Send class="w-3.5 h-3.5 text-amber-400" />
                                            <span>Advance</span>
                                        </button>

                                        <!-- Statement / Profile Link -->
                                        <Link
                                            :href="`/suppliers/${supplier.id}/profile`"
                                            class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition"
                                            title="View Detailed Profile & Statement"
                                        >
                                            <FileText class="w-4 h-4 text-amber-400" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="suppliers.length === 0">
                                <td colspan="7" class="px-5 py-8 text-center text-slate-500 text-xs">
                                    No active suppliers found. To activate a supplier, visit the Supplier Directory and click "Activate".
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: Pay Supplier Due -->
        <div v-if="showPayModal && activeSupplier" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <div>
                        <h3 class="font-bold text-white text-lg flex items-center gap-2">
                            <Wallet class="w-5 h-5 text-rose-500" />
                            <span>{{ t('pay_due', 'Record Due Payment Voucher') }}</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">Supplier: <strong class="text-slate-200">{{ activeSupplier.name }}</strong></p>
                    </div>
                    <button type="button" @click="showPayModal = false" class="text-slate-400 hover:text-slate-200">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="bg-rose-950/40 border-b border-rose-900/40 px-6 py-3 flex items-center justify-between">
                    <span class="text-xs font-medium text-rose-300">{{ t('current_due', 'Current Outstanding Due') }}:</span>
                    <span class="text-lg font-bold text-rose-400 font-mono">৳ {{ Number(activeSupplier.current_balance).toLocaleString() }}</span>
                </div>

                <form @submit.prevent="submitPayDue" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('amount', 'Payment Amount (৳)') }} *</label>
                            <input
                                v-model.number="payForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 font-mono font-bold text-amber-400 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('payment_date', 'Payment Date') }} *</label>
                            <input
                                v-model="payForm.payment_date"
                                type="date"
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('payment_account', 'Deduct from Account') }}</label>
                            <select
                                v-model="payForm.payment_account_id"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            >
                                <option value="">-- No Account Deduction --</option>
                                <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                    {{ acc.name }} (৳ {{ Number(acc.current_balance).toLocaleString() }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('payment_method', 'Payment Method') }}</label>
                            <select
                                v-model="payForm.payment_method"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            >
                                <option value="cash">Cash Drawer</option>
                                <option value="bank">Bank Transfer / Cheque</option>
                                <option value="mfs">bKash / Nagad</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('notes', 'Payment Notes / Cheque No') }}</label>
                        <input
                            v-model="payForm.notes"
                            type="text"
                            placeholder="e.g. Paid meat dues via Cash Drawer"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                        <button
                            type="button"
                            @click="showPayModal = false"
                            class="px-4 py-2 rounded-xl text-slate-400 hover:text-slate-200 text-xs font-medium transition"
                        >
                            {{ t('cancel', 'Cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="payForm.processing"
                            class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold text-xs shadow-lg shadow-rose-950 transition active:scale-95"
                        >
                            {{ t('confirm', 'Confirm Payment Voucher') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Issue Advance -->
        <div v-if="showAdvanceModal && activeSupplier" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <div>
                        <h3 class="font-bold text-white text-lg flex items-center gap-2">
                            <Send class="w-5 h-5 text-amber-500" />
                            <span>Issue Supplier Advance</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">Supplier: <strong class="text-slate-200">{{ activeSupplier.name }}</strong></p>
                    </div>
                    <button type="button" @click="showAdvanceModal = false" class="text-slate-400 hover:text-slate-200">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitAdvance" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Advance Amount (৳) *</label>
                            <input
                                v-model.number="advanceForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                placeholder="0.00"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 font-mono font-bold text-amber-400 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Date *</label>
                            <input
                                v-model="advanceForm.advance_date"
                                type="date"
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Financial Account *</label>
                            <select
                                v-model="advanceForm.payment_account_id"
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            >
                                <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                    {{ acc.name }} (৳ {{ Number(acc.current_balance).toLocaleString() }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Payment Method</label>
                            <select
                                v-model="advanceForm.payment_method"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            >
                                <option value="cash">Cash</option>
                                <option value="bank">Bank</option>
                                <option value="mfs">bKash / Nagad</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Reason / Purpose</label>
                        <input
                            v-model="advanceForm.reason"
                            type="text"
                            placeholder="e.g. Advance deposit for upcoming meat supply order"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                        <button
                            type="button"
                            @click="showAdvanceModal = false"
                            class="px-4 py-2 rounded-xl text-slate-400 hover:text-slate-200 text-xs font-medium transition"
                        >
                            {{ t('cancel', 'Cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="advanceForm.processing"
                            class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs shadow-lg shadow-amber-950 transition active:scale-95"
                        >
                            Confirm Advance Voucher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

