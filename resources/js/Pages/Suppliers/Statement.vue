<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    Store, 
    ArrowLeft, 
    Printer, 
    Phone, 
    Calendar, 
    Wallet, 
    CheckCircle2, 
    FileText, 
    Filter,
    X
} from 'lucide-vue-next';
import { useI18n } from '../../Composables/useI18n';

const props = defineProps({
    statement: Object,
    accounts: Array,
    filters: Object,
});

const { t } = useI18n();

const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const showPayModal = ref(false);

const applyFilter = () => {
    router.get(
        `/suppliers/${props.statement.supplier.id}/statement`,
        {
            start_date: startDate.value || undefined,
            end_date: endDate.value || undefined,
        },
        { preserveState: true }
    );
};

const clearFilter = () => {
    startDate.value = '';
    endDate.value = '';
    applyFilter();
};

const printStatement = () => {
    window.print();
};

// Pay Due Form
const payForm = useForm({
    amount: props.statement.current_balance > 0 ? props.statement.current_balance : '',
    payment_date: new Date().toISOString().split('T')[0],
    payment_account_id: props.accounts.length > 0 ? props.accounts[0].id : '',
    payment_method: 'cash',
    notes: '',
});

const submitPayDue = () => {
    payForm.post(`/suppliers/${props.statement.supplier.id}/pay-due`, {
        onSuccess: () => {
            showPayModal.value = false;
            payForm.reset();
        },
    });
};
</script>

<template>
    <AppLayout title="Supplier Ledger Statement">
        <Head :title="statement.supplier.name + ' — ' + t('statement', 'Statement') + ' — Royal Nest'" />

        <div class="space-y-6">
            <!-- Navigation & Actions (Hidden on Print) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 print:hidden">
                <div class="flex items-center gap-3">
                    <Link
                        href="/suppliers"
                        class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition"
                    >
                        <ArrowLeft class="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                            <span>{{ statement.supplier.name }}</span>
                            <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                                {{ t('statement', 'Ledger Statement') }}
                            </span>
                        </h1>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ statement.supplier.contact_person ? statement.supplier.contact_person + ' • ' : '' }}
                            {{ statement.supplier.phone || 'No phone' }}
                            {{ statement.supplier.address ? ' • ' + statement.supplier.address : '' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="showPayModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold text-xs shadow-lg shadow-rose-950 transition active:scale-95"
                    >
                        <Wallet class="w-4 h-4" />
                        <span>{{ t('pay_due', 'Record Due Payment') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="printStatement"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-xs font-semibold transition"
                    >
                        <Printer class="w-4 h-4 text-amber-400" />
                        <span>{{ t('print', 'Print Statement') }}</span>
                    </button>
                </div>
            </div>

            <!-- Printable Header (Visible Only on Print) -->
            <div class="hidden print:block border-b-2 border-stone-800 pb-4 mb-4 text-black">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-2xl font-bold">Royal Nest Café & Restaurant</h2>
                        <p class="text-xs text-gray-600">House 12, Road 5, Dhanmondi, Dhaka • Tel: +8801700000000</p>
                        <h3 class="text-lg font-semibold mt-2">Supplier Ledger Statement</h3>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold">Supplier: {{ statement.supplier.name }}</p>
                        <p class="text-xs text-gray-600">Contact: {{ statement.supplier.contact_person }} ({{ statement.supplier.phone }})</p>
                        <p class="text-xs text-gray-600">Generated: {{ new Date().toLocaleString() }}</p>
                    </div>
                </div>
            </div>

            <!-- Financial Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 print:grid-cols-4">
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 print:border-gray-300 print:bg-white print:text-black">
                    <span class="text-xs font-medium text-slate-400 print:text-gray-600 block">{{ t('opening_balance', 'Opening Due') }}</span>
                    <span class="text-xl font-bold font-mono text-slate-200 print:text-black mt-0.5 block">৳ {{ Number(statement.opening_balance).toLocaleString() }}</span>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 print:border-gray-300 print:bg-white print:text-black">
                    <span class="text-xs font-medium text-slate-400 print:text-gray-600 block">{{ t('total_purchased', 'Total Bills') }}</span>
                    <span class="text-xl font-bold font-mono text-amber-400 print:text-black mt-0.5 block">৳ {{ Number(statement.total_purchased).toLocaleString() }}</span>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 print:border-gray-300 print:bg-white print:text-black">
                    <span class="text-xs font-medium text-slate-400 print:text-gray-600 block">{{ t('paid_amount', 'Total Paid') }}</span>
                    <span class="text-xl font-bold font-mono text-emerald-400 print:text-black mt-0.5 block">৳ {{ Number(statement.total_paid).toLocaleString() }}</span>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 print:border-gray-300 print:bg-white print:text-black">
                    <span class="text-xs font-medium text-rose-400 print:text-gray-600 block">{{ t('current_due', 'Net Outstanding Due') }}</span>
                    <span class="text-xl font-bold font-mono text-rose-400 print:text-red-700 mt-0.5 block">৳ {{ Number(statement.current_balance).toLocaleString() }}</span>
                </div>
            </div>

            <!-- Filter Bar (Hidden on Print) -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4 print:hidden">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-400">From:</span>
                        <input
                            v-model="startDate"
                            type="date"
                            class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-400">To:</span>
                        <input
                            v-model="endDate"
                            type="date"
                            class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500"
                        />
                    </div>
                    <button
                        type="button"
                        @click="applyFilter"
                        class="px-3.5 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-medium text-xs transition"
                    >
                        {{ t('filter', 'Filter') }}
                    </button>
                    <button
                        v-if="startDate || endDate"
                        type="button"
                        @click="clearFilter"
                        class="px-3 py-1.5 rounded-xl border border-slate-800 text-slate-400 hover:text-slate-200 text-xs transition"
                    >
                        Clear
                    </button>
                </div>
                <div class="text-xs text-slate-500">
                    Total {{ statement.statement_rows.length }} transaction records
                </div>
            </div>

            <!-- Detailed Statement Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm print:bg-white print:border-gray-300">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm text-slate-300 print:text-black">
                        <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider print:bg-gray-100 print:text-black print:border-gray-300">
                            <tr>
                                <th class="px-4 py-3.5">{{ t('date', 'Date') }}</th>
                                <th class="px-4 py-3.5">{{ t('voucher_no', 'Ref / Voucher') }}</th>
                                <th class="px-4 py-3.5">{{ t('description', 'Transaction Description') }}</th>
                                <th class="px-4 py-3.5 text-right">{{ t('bill_amount', 'Bill (৳)') }}</th>
                                <th class="px-4 py-3.5 text-right">{{ t('paid_amount', 'Paid (৳)') }}</th>
                                <th class="px-4 py-3.5 text-right font-bold">{{ t('running_due', 'Running Due (৳)') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 print:divide-gray-200">
                            <!-- Opening Balance Row -->
                            <tr class="bg-slate-950/40 font-semibold text-slate-400 print:bg-gray-50 print:text-gray-700">
                                <td class="px-4 py-3">—</td>
                                <td class="px-4 py-3 font-mono text-xs">OPENING</td>
                                <td class="px-4 py-3 italic">Initial Opening Balance</td>
                                <td class="px-4 py-3 text-right font-mono">—</td>
                                <td class="px-4 py-3 text-right font-mono">—</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-200 print:text-black">
                                    ৳ {{ Number(statement.opening_balance).toLocaleString() }}
                                </td>
                            </tr>

                            <!-- Transaction Rows -->
                            <tr
                                v-for="(row, idx) in statement.statement_rows"
                                :key="idx"
                                :class="[
                                    'hover:bg-slate-800/30 transition',
                                    row.type === 'due_payment' ? 'bg-emerald-950/10' : ''
                                ]"
                            >
                                <td class="px-4 py-3.5 whitespace-nowrap font-mono text-xs text-slate-400 print:text-gray-700">
                                    {{ row.date }}
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded text-[11px] font-mono font-bold',
                                            row.type === 'due_payment' 
                                                ? 'bg-emerald-950/60 text-emerald-400 border border-emerald-800/40' 
                                                : 'bg-amber-950/60 text-amber-400 border border-amber-800/40'
                                        ]"
                                    >
                                        {{ row.reference }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="text-slate-200 print:text-black">{{ row.description }}</div>
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-slate-200 print:text-black">
                                    {{ row.bill_amount > 0 ? '৳ ' + Number(row.bill_amount).toLocaleString() : '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-emerald-400 print:text-emerald-700 font-semibold">
                                    {{ (row.paid_on_bill > 0 || row.payment_voucher > 0) ? '৳ ' + Number(row.paid_on_bill || row.payment_voucher).toLocaleString() : '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono font-bold text-rose-400 print:text-red-700">
                                    ৳ {{ Number(row.running_due).toLocaleString() }}
                                </td>
                            </tr>

                            <tr v-if="statement.statement_rows.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500 text-sm">
                                    No purchase or payment activity recorded in this period.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-slate-950/90 border-t-2 border-slate-800 font-bold text-slate-200 print:bg-gray-100 print:text-black print:border-gray-400">
                            <tr>
                                <td colspan="3" class="px-4 py-3.5 uppercase tracking-wider text-xs">Total Summary</td>
                                <td class="px-4 py-3.5 text-right font-mono text-amber-400 print:text-black">
                                    ৳ {{ Number(statement.total_purchased).toLocaleString() }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-emerald-400 print:text-emerald-700">
                                    ৳ {{ Number(statement.total_paid).toLocaleString() }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-rose-400 print:text-red-700">
                                    ৳ {{ Number(statement.current_balance).toLocaleString() }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: Pay Supplier Due -->
        <div v-if="showPayModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <div>
                        <h3 class="font-bold text-white text-lg flex items-center gap-2">
                            <Wallet class="w-5 h-5 text-rose-500" />
                            <span>{{ t('pay_due', 'Record Due Payment') }}</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">Supplier: <strong class="text-slate-200">{{ statement.supplier.name }}</strong></p>
                    </div>
                    <button type="button" @click="showPayModal = false" class="text-slate-400 hover:text-slate-200">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Current Due Banner -->
                <div class="bg-rose-950/40 border-b border-rose-900/40 px-6 py-3 flex items-center justify-between">
                    <span class="text-xs font-medium text-rose-300">{{ t('current_due', 'Current Outstanding Due') }}:</span>
                    <span class="text-lg font-bold text-rose-400 font-mono">৳ {{ Number(statement.current_balance).toLocaleString() }}</span>
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
                                placeholder="0.00"
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
                                <option value="cash">Cash</option>
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
                            placeholder="e.g. Paid weekly dues via Cash Drawer"
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
    </AppLayout>
</template>

