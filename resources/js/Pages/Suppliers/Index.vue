<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    Store, 
    Plus, 
    Phone, 
    CreditCard, 
    FileText, 
    AlertCircle, 
    Search, 
    ArrowUpRight, 
    CheckCircle2, 
    Edit, 
    X,
    Wallet
} from 'lucide-vue-next';
import { useI18n } from '../../Composables/useI18n';

const props = defineProps({
    suppliers: Array,
    accounts: Array,
    stats: Object,
});

const { t } = useI18n();

const searchQuery = ref('');
const showAddModal = ref(false);
const showPayModal = ref(false);
const showEditModal = ref(false);
const activeSupplier = ref(null);

const filteredSuppliers = computed(() => {
    if (!searchQuery.value.trim()) return props.suppliers;
    const q = searchQuery.value.toLowerCase();
    return props.suppliers.filter(s => 
        s.name.toLowerCase().includes(q) || 
        (s.phone && s.phone.includes(q)) ||
        (s.contact_person && s.contact_person.toLowerCase().includes(q))
    );
});

// Form: Add Supplier
const addForm = useForm({
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    opening_balance: 0,
});

const submitAddSupplier = () => {
    addForm.post('/suppliers', {
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

// Form: Edit Supplier
const editForm = useForm({
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    is_active: true,
});

const openEditModal = (supplier) => {
    activeSupplier.value = supplier;
    editForm.name = supplier.name;
    editForm.contact_person = supplier.contact_person || '';
    editForm.phone = supplier.phone || '';
    editForm.email = supplier.email || '';
    editForm.address = supplier.address || '';
    editForm.is_active = supplier.is_active;
    showEditModal.value = true;
};

const submitEditSupplier = () => {
    if (!activeSupplier.value) return;
    editForm.put(`/suppliers/${activeSupplier.value.id}`, {
        onSuccess: () => {
            showEditModal.value = false;
            activeSupplier.value = null;
        },
    });
};

// Form: Pay Supplier Due
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
</script>

<template>
    <AppLayout title="Suppliers & Dealers">
        <Head :title="t('suppliers', 'Suppliers & Dealers') + ' — Royal Nest'" />

        <div class="space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                        <Store class="w-6 h-6 text-amber-500" />
                        <span>{{ t('suppliers', 'Suppliers & Raw Material Dealers') }}</span>
                    </h1>
                    <p class="text-xs text-slate-400 mt-1">
                        {{ t('supplier_desc', 'Track raw material vendors, purchase dues, payment vouchers, and ledger statements.') }}
                    </p>
                </div>
                <button
                    type="button"
                    @click="showAddModal = true"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-sm shadow-lg shadow-amber-950 transition active:scale-95 shrink-0"
                >
                    <Plus class="w-4 h-4" />
                    <span>{{ t('add_supplier', 'Add New Supplier') }}</span>
                </button>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-medium text-slate-400 block">{{ t('total_suppliers', 'Total Suppliers') }}</span>
                        <span class="text-2xl font-bold text-slate-100 mt-0.5 block">{{ stats.total_suppliers }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <Store class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-medium text-rose-400 block">{{ t('total_payable_due', 'Total Payable Dues') }}</span>
                        <span class="text-2xl font-bold text-rose-400 font-mono mt-0.5 block">৳ {{ stats.total_payable_dues.toLocaleString() }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                        <AlertCircle class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-medium text-slate-400 block">{{ t('total_purchased', 'Total Material Purchased') }}</span>
                        <span class="text-2xl font-bold text-emerald-400 font-mono mt-0.5 block">৳ {{ stats.total_purchased_all.toLocaleString() }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <CreditCard class="w-5 h-5" />
                    </div>
                </div>
            </div>

            <!-- Search & Filter Bar -->
            <div class="flex items-center gap-3">
                <div class="relative flex-1 max-w-md">
                    <Search class="w-4 h-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('search', 'Search by supplier name or phone...')"
                        class="w-full bg-slate-900 border border-slate-800 rounded-xl pl-10 pr-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500"
                    />
                </div>
            </div>

            <!-- Suppliers Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-950/70 border-b border-slate-800 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3.5">{{ t('supplier_name', 'Supplier Details') }}</th>
                                <th class="px-4 py-3.5">{{ t('phone', 'Phone & Contact') }}</th>
                                <th class="px-4 py-3.5 text-right">{{ t('total_purchased', 'Total Bills') }}</th>
                                <th class="px-4 py-3.5 text-right">{{ t('current_due', 'Outstanding Due') }}</th>
                                <th class="px-5 py-3.5 text-right">{{ t('actions', 'Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="supplier in filteredSuppliers" :key="supplier.id" class="hover:bg-slate-800/40 transition">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-100 text-base">{{ supplier.name }}</div>
                                    <div class="text-xs text-slate-400 mt-0.5">{{ supplier.address || 'No address provided' }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <div v-if="supplier.contact_person" class="text-xs font-medium text-slate-300">{{ supplier.contact_person }}</div>
                                    <div v-if="supplier.phone" class="text-xs text-amber-400 font-mono flex items-center gap-1 mt-0.5">
                                        <Phone class="w-3 h-3 text-slate-500" />
                                        <a :href="'tel:' + supplier.phone" class="hover:underline">{{ supplier.phone }}</a>
                                    </div>
                                    <div v-else class="text-xs text-slate-500">—</div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="font-mono text-slate-200">৳ {{ Number(supplier.total_purchased).toLocaleString() }}</div>
                                    <div class="text-[11px] text-slate-500">{{ supplier.purchases_count }} bills</div>
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
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Pay Due Button -->
                                        <button
                                            type="button"
                                            @click="openPayModal(supplier)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-500/30 text-xs font-semibold transition"
                                        >
                                            <Wallet class="w-3.5 h-3.5" />
                                            <span>{{ t('pay_due', 'Pay Due') }}</span>
                                        </button>

                                        <!-- Statement Link -->
                                        <Link
                                            :href="`/suppliers/${supplier.id}/statement`"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-medium transition"
                                        >
                                            <FileText class="w-3.5 h-3.5 text-amber-400" />
                                            <span>{{ t('statement', 'Statement') }}</span>
                                        </Link>

                                        <!-- Edit Button -->
                                        <button
                                            type="button"
                                            @click="openEditModal(supplier)"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition"
                                            title="Edit Supplier"
                                        >
                                            <Edit class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredSuppliers.length === 0">
                                <td colspan="5" class="px-5 py-8 text-center text-slate-500 text-sm">
                                    {{ t('no_records', 'No suppliers found.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: Add New Supplier -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <h3 class="font-bold text-white text-lg flex items-center gap-2">
                        <Store class="w-5 h-5 text-amber-500" />
                        <span>{{ t('add_supplier', 'Add New Supplier / Dealer') }}</span>
                    </h3>
                    <button type="button" @click="showAddModal = false" class="text-slate-400 hover:text-slate-200">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitAddSupplier" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('supplier_name', 'Supplier / Dealer Name') }} *</label>
                        <input
                            v-model="addForm.name"
                            type="text"
                            required
                            placeholder="e.g. Haji Meat Mart, Dhaka Spices"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('contact_person', 'Contact Person') }}</label>
                            <input
                                v-model="addForm.contact_person"
                                type="text"
                                placeholder="e.g. Md. Rafiq"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('phone', 'Phone Number') }}</label>
                            <input
                                v-model="addForm.phone"
                                type="text"
                                placeholder="017XXXXXXXX"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('email', 'Email Address') }}</label>
                            <input
                                v-model="addForm.email"
                                type="email"
                                placeholder="dealer@example.com"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('opening_balance', 'Opening Due Balance (৳)') }}</label>
                            <input
                                v-model.number="addForm.opening_balance"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 font-mono focus:outline-none focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('address', 'Market / Shop Address') }}</label>
                        <textarea
                            v-model="addForm.address"
                            rows="2"
                            placeholder="e.g. Shop #14, Kawran Bazar, Dhaka"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                        <button
                            type="button"
                            @click="showAddModal = false"
                            class="px-4 py-2 rounded-xl text-slate-400 hover:text-slate-200 text-xs font-medium transition"
                        >
                            {{ t('cancel', 'Cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="addForm.processing"
                            class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs shadow-lg shadow-amber-950 transition active:scale-95"
                        >
                            {{ t('save', 'Save Supplier') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Edit Supplier -->
        <div v-if="showEditModal && activeSupplier" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <h3 class="font-bold text-white text-lg flex items-center gap-2">
                        <Edit class="w-5 h-5 text-amber-500" />
                        <span>Edit Supplier: {{ activeSupplier.name }}</span>
                    </h3>
                    <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-slate-200">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitEditSupplier" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('supplier_name', 'Supplier Name') }} *</label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('contact_person', 'Contact Person') }}</label>
                            <input
                                v-model="editForm.contact_person"
                                type="text"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('phone', 'Phone Number') }}</label>
                            <input
                                v-model="editForm.phone"
                                type="text"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('address', 'Address') }}</label>
                        <textarea
                            v-model="editForm.address"
                            rows="2"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                        <button
                            type="button"
                            @click="showEditModal = false"
                            class="px-4 py-2 rounded-xl text-slate-400 hover:text-slate-200 text-xs font-medium transition"
                        >
                            {{ t('cancel', 'Cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs shadow-lg shadow-amber-950 transition active:scale-95"
                        >
                            {{ t('save', 'Update Supplier') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Pay Supplier Due -->
        <div v-if="showPayModal && activeSupplier" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <div>
                        <h3 class="font-bold text-white text-lg flex items-center gap-2">
                            <Wallet class="w-5 h-5 text-rose-500" />
                            <span>{{ t('pay_due', 'Record Due Payment') }}</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">Supplier: <strong class="text-slate-200">{{ activeSupplier.name }}</strong></p>
                    </div>
                    <button type="button" @click="showPayModal = false" class="text-slate-400 hover:text-slate-200">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Current Due Banner -->
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
                            placeholder="e.g. Paid weekly vegetable dues via Cash Drawer"
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

