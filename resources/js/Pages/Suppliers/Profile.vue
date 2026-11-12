<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    Store, 
    ArrowLeft, 
    Printer, 
    Phone, 
    MapPin, 
    Calendar, 
    Wallet, 
    CheckCircle2, 
    FileText, 
    Package, 
    Plus, 
    Trash2, 
    Settings, 
    Send, 
    X,
    CreditCard
} from 'lucide-vue-next';
import { useI18n } from '../../Composables/useI18n';

const props = defineProps({
    statement: Object,
    accounts: Array,
    products: Array,
    filters: Object,
});

const { t } = useI18n();

const activeTab = ref('statement'); // 'statement', 'products', 'settings'
const showPayModal = ref(false);
const showAdvanceModal = ref(false);
const showAddProductModal = ref(false);

const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');

const applyFilter = () => {
    router.get(
        `/suppliers/${props.statement.supplier.id}/profile`,
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

// Forms
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

const advanceForm = useForm({
    amount: '',
    advance_date: new Date().toISOString().split('T')[0],
    payment_account_id: props.accounts.length > 0 ? props.accounts[0].id : '',
    payment_method: 'cash',
    reason: '',
});

const submitAdvance = () => {
    advanceForm.post(`/suppliers/${props.statement.supplier.id}/advance`, {
        onSuccess: () => {
            showAdvanceModal.value = false;
            advanceForm.reset();
        },
    });
};

const productForm = useForm({
    product_name: '',
    product_category: '',
    purchase_unit: 'kg',
    unit_price: '',
    min_order_quantity: '',
    notes: '',
});

const submitAddProduct = () => {
    productForm.post(`/suppliers/${props.statement.supplier.id}/products`, {
        onSuccess: () => {
            showAddProductModal.value = false;
            productForm.reset();
        },
    });
};

const deleteProduct = (prodId) => {
    if (confirm('Delete this product rate?')) {
        router.delete(`/supplier-products/${prodId}`, { preserveScroll: true });
    }
};

const editForm = useForm({
    name: props.statement.supplier.name,
    company_name: props.statement.supplier.company_name || '',
    contact_person: props.statement.supplier.contact_person || '',
    phone: props.statement.supplier.phone || '',
    alternative_phone: props.statement.supplier.alternative_phone || '',
    email: props.statement.supplier.email || '',
    address: props.statement.supplier.address || '',
    business_location: props.statement.supplier.business_location || '',
    category: props.statement.supplier.category || 'Raw Materials',
    status: props.statement.supplier.status,
    preferred_payment_terms: props.statement.supplier.preferred_payment_terms || '',
});

const submitUpdateSupplier = () => {
    editForm.put(`/suppliers/${props.statement.supplier.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout title="Supplier Profile">
        <Head :title="statement.supplier.name + ' — Supplier Profile — Royal Nest'" />

        <div class="space-y-6">
            <!-- Header (Hidden on Print) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 print:hidden">
                <div class="flex items-center gap-3">
                    <Link
                        href="/suppliers/active"
                        class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition"
                    >
                        <ArrowLeft class="w-5 h-5" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-bold text-white tracking-tight">{{ statement.supplier.name }}</h1>
                            <span :class="['px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize border', statement.supplier.status === 'active' ? 'bg-emerald-950/60 text-emerald-400 border-emerald-800/40' : 'bg-amber-950/60 text-amber-400 border-amber-800/40']">
                                {{ statement.supplier.status }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ statement.supplier.company_name ? statement.supplier.company_name + ' • ' : '' }}
                            {{ statement.supplier.contact_person ? statement.supplier.contact_person + ' • ' : '' }}
                            {{ statement.supplier.phone }}
                            {{ statement.supplier.business_location ? ' • ' + statement.supplier.business_location : '' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="showPayModal = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold text-xs shadow-lg shadow-rose-950 transition active:scale-95"
                    >
                        <Wallet class="w-4 h-4" />
                        <span>{{ t('pay_due', 'Record Due Payment') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="showAdvanceModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-medium transition"
                    >
                        <Send class="w-4 h-4 text-amber-400" />
                        <span>Advance</span>
                    </button>

                    <button
                        type="button"
                        @click="printStatement"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-medium transition"
                    >
                        <Printer class="w-4 h-4 text-amber-400" />
                        <span>{{ t('print', 'Print') }}</span>
                    </button>
                </div>
            </div>

            <!-- Printable Header (Visible Only on Print) -->
            <div class="hidden print:block border-b-2 border-stone-800 pb-4 mb-4 text-black">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-2xl font-bold">Royal Nest Café & Restaurant</h2>
                        <p class="text-xs text-gray-600">House 12, Road 5, Dhanmondi, Dhaka • Tel: +8801700000000</p>
                        <h3 class="text-lg font-semibold mt-2">Supplier Account & Ledger Statement</h3>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold">Supplier: {{ statement.supplier.name }}</p>
                        <p class="text-xs text-gray-600">Company: {{ statement.supplier.company_name || 'N/A' }}</p>
                        <p class="text-xs text-gray-600">Phone: {{ statement.supplier.phone }}</p>
                        <p class="text-xs text-gray-600">Printed: {{ new Date().toLocaleString() }}</p>
                    </div>
                </div>
            </div>

            <!-- Financial Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 print:grid-cols-4">
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 print:bg-white print:border-gray-300">
                    <span class="text-xs font-medium text-slate-400 print:text-gray-600 block">Initial Opening Due</span>
                    <span class="text-xl font-bold font-mono text-slate-200 print:text-black mt-0.5 block">৳ {{ Number(statement.opening_balance).toLocaleString() }}</span>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 print:bg-white print:border-gray-300">
                    <span class="text-xs font-medium text-slate-400 print:text-gray-600 block">Total Material Bills</span>
                    <span class="text-xl font-bold font-mono text-amber-400 print:text-black mt-0.5 block">৳ {{ Number(statement.total_purchased).toLocaleString() }}</span>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 print:bg-white print:border-gray-300">
                    <span class="text-xs font-medium text-slate-400 print:text-gray-600 block">Total Paid to Date</span>
                    <span class="text-xl font-bold font-mono text-emerald-400 print:text-black mt-0.5 block">৳ {{ Number(statement.total_paid).toLocaleString() }}</span>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 print:bg-white print:border-gray-300">
                    <span class="text-xs font-medium text-rose-400 print:text-gray-600 block">Current Outstanding Due</span>
                    <span class="text-xl font-bold font-mono text-rose-400 print:text-red-700 mt-0.5 block">৳ {{ Number(statement.current_balance).toLocaleString() }}</span>
                </div>
            </div>

            <!-- Profile Navigation Tabs (Hidden on Print) -->
            <div class="flex items-center border-b border-slate-800 print:hidden space-x-6">
                <button
                    type="button"
                    @click="activeTab = 'statement'"
                    :class="['pb-3 text-sm font-semibold transition border-b-2 flex items-center gap-2', activeTab === 'statement' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200']"
                >
                    <FileText class="w-4 h-4" />
                    <span>Transaction History & Ledger</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'products'"
                    :class="['pb-3 text-sm font-semibold transition border-b-2 flex items-center gap-2', activeTab === 'products' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200']"
                >
                    <Package class="w-4 h-4" />
                    <span>Supplied Products & Rates ({{ statement.products?.length || 0 }})</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'settings'"
                    :class="['pb-3 text-sm font-semibold transition border-b-2 flex items-center gap-2', activeTab === 'settings' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200']"
                >
                    <Settings class="w-4 h-4" />
                    <span>Vendor Details & Status</span>
                </button>
            </div>

            <!-- Tab 1: Transaction History & Statement -->
            <div v-show="activeTab === 'statement'" class="space-y-4">
                <!-- Date Filter Bar (Hidden on Print) -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-400">From:</span>
                            <input
                                v-model="startDate"
                                type="date"
                                class="bg-slate-950 border border-slate-800 rounded-xl px-2.5 py-1 text-xs text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-400">To:</span>
                            <input
                                v-model="endDate"
                                type="date"
                                class="bg-slate-950 border border-slate-800 rounded-xl px-2.5 py-1 text-xs text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                        <button
                            type="button"
                            @click="applyFilter"
                            class="px-3 py-1 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-medium text-xs transition"
                        >
                            Filter
                        </button>
                        <button
                            v-if="startDate || endDate"
                            type="button"
                            @click="clearFilter"
                            class="px-3 py-1 rounded-xl border border-slate-800 text-slate-400 hover:text-slate-200 text-xs transition"
                        >
                            Clear
                        </button>
                    </div>
                    <span class="text-xs text-slate-500">{{ statement.statement_rows.length }} transaction records</span>
                </div>

                <!-- Ledger Table -->
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
                                <!-- Opening Balance -->
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

                                <tr
                                    v-for="(row, idx) in statement.statement_rows"
                                    :key="idx"
                                    :class="[
                                        'hover:bg-slate-800/30 transition',
                                        row.type === 'payment_voucher' ? 'bg-emerald-950/10' : (row.type === 'advance_voucher' ? 'bg-amber-950/10' : '')
                                    ]"
                                >
                                    <td class="px-4 py-3.5 font-mono text-xs text-slate-400 print:text-gray-700 whitespace-nowrap">{{ row.date }}</td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span
                                                :class="[
                                                    'px-2 py-0.5 rounded text-[11px] font-mono font-bold',
                                                    row.type === 'payment_voucher' 
                                                        ? 'bg-emerald-950/60 text-emerald-400 border border-emerald-800/40' 
                                                        : (row.type === 'advance_voucher' ? 'bg-blue-950/60 text-blue-400 border border-blue-800/40' : 'bg-amber-950/60 text-amber-400 border border-amber-800/40')
                                                ]"
                                            >
                                                {{ row.reference }}
                                            </span>
                                            <span 
                                                v-if="row.type === 'purchase_bill'" 
                                                :class="[
                                                    'px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider',
                                                    row.bill_status === 'paid' ? 'bg-emerald-900/40 text-emerald-300 border border-emerald-700/50' : (row.bill_status === 'partially_paid' ? 'bg-amber-900/40 text-amber-300 border border-amber-700/50' : 'bg-rose-900/40 text-rose-300 border border-rose-700/50')
                                                ]"
                                            >
                                                {{ row.bill_status === 'paid' ? 'Paid' : (row.bill_status === 'partially_paid' ? 'Due: ৳' + Number(row.remaining_bill_due).toLocaleString() : 'Unpaid') }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="text-slate-200 print:text-black">{{ row.description }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono text-slate-200 print:text-black">
                                        {{ row.bill_amount > 0 ? '৳ ' + Number(row.bill_amount).toLocaleString() : '—' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono text-emerald-400 print:text-emerald-700 font-semibold">
                                        {{ row.paid_amount > 0 ? '৳ ' + Number(row.paid_amount).toLocaleString() : '—' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono font-bold text-rose-400 print:text-red-700">
                                        ৳ {{ Number(row.running_due).toLocaleString() }}
                                    </td>
                                </tr>

                                <tr v-if="statement.statement_rows.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-500 text-xs">
                                        No purchase or payment records found in this period.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-slate-950/90 border-t-2 border-slate-800 font-bold text-slate-200 print:bg-gray-100 print:text-black print:border-gray-400">
                                <tr>
                                    <td colspan="3" class="px-4 py-3.5 uppercase tracking-wider text-xs">Summary Balance</td>
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

            <!-- Tab 2: Supplied Products & Agreed Rates -->
            <div v-show="activeTab === 'products'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-white text-base">Supplied Products & Quoted Rates</h3>
                        <p class="text-xs text-slate-400">Raw materials and items this supplier provides with current agreed rates.</p>
                    </div>
                    <button
                        type="button"
                        @click="showAddProductModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs shadow transition"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Add Product Rate</span>
                    </button>
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-left text-xs sm:text-sm text-slate-300">
                        <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3.5">Product Name</th>
                                <th class="px-4 py-3.5">Category</th>
                                <th class="px-4 py-3.5">Purchase Unit</th>
                                <th class="px-4 py-3.5 text-right">Agreed Rate (৳)</th>
                                <th class="px-4 py-3.5 text-right">Min Order Qty</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="prod in statement.products" :key="prod.id" class="hover:bg-slate-800/40 transition">
                                <td class="px-5 py-3.5 font-bold text-slate-100">{{ prod.product_name }}</td>
                                <td class="px-4 py-3.5 text-slate-400">{{ prod.product_category || 'Raw Material' }}</td>
                                <td class="px-4 py-3.5 font-mono text-amber-400">{{ prod.purchase_unit }}</td>
                                <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-100">
                                    ৳ {{ Number(prod.unit_price).toLocaleString() }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-slate-400">
                                    {{ prod.min_order_quantity ? prod.min_order_quantity + ' ' + prod.purchase_unit : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <button
                                        type="button"
                                        @click="deleteProduct(prod.id)"
                                        class="p-1.5 rounded-lg text-slate-500 hover:text-rose-400 hover:bg-slate-800 transition"
                                        title="Delete product rate"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!statement.products || statement.products.length === 0">
                                <td colspan="6" class="px-5 py-8 text-center text-slate-500 text-xs">
                                    No products configured yet. Click "+ Add Product Rate" to specify items supplied by this vendor.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 3: Vendor Details & Settings -->
            <div v-show="activeTab === 'settings'" class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
                <h3 class="font-bold text-white text-base mb-4">Edit Vendor Information</h3>
                <form @submit.prevent="submitUpdateSupplier" class="space-y-4 max-w-2xl">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Trade Name *</label>
                            <input v-model="editForm.name" type="text" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Company Name</label>
                            <input v-model="editForm.company_name" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Contact Person</label>
                            <input v-model="editForm.contact_person" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Phone Number</label>
                            <input v-model="editForm.phone" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Alternative Phone</label>
                            <input v-model="editForm.alternative_phone" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Category</label>
                            <input v-model="editForm.category" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Status</label>
                            <select v-model="editForm.status" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500">
                                <option value="potential">Potential</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Payment Terms</label>
                            <input v-model="editForm.preferred_payment_terms" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Business Location</label>
                        <input v-model="editForm.business_location" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Full Address</label>
                        <textarea v-model="editForm.address" rows="2" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500"></textarea>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs shadow-lg transition"
                        >
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Pay Due -->
        <div v-if="showPayModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <h3 class="font-bold text-white text-lg">Record Due Payment</h3>
                    <button type="button" @click="showPayModal = false" class="text-slate-400 hover:text-slate-200"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submitPayDue" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Amount (৳) *</label>
                            <input v-model.number="payForm.amount" type="number" step="0.01" min="0.01" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-amber-400 font-mono font-bold focus:outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Date *</label>
                            <input v-model="payForm.payment_date" type="date" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Financial Account</label>
                            <select v-model="payForm.payment_account_id" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500">
                                <option value="">-- No Account Deduction --</option>
                                <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.name }} (৳ {{ Number(acc.current_balance).toLocaleString() }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Method</label>
                            <select v-model="payForm.payment_method" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500">
                                <option value="cash">Cash Drawer</option>
                                <option value="bank">Bank</option>
                                <option value="mfs">bKash / Nagad</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Notes</label>
                        <input v-model="payForm.notes" type="text" placeholder="e.g. Paid weekly vegetable dues" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                    </div>
                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                        <button type="button" @click="showPayModal = false" class="px-4 py-2 text-xs text-slate-400">Cancel</button>
                        <button type="submit" :disabled="payForm.processing" class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold text-xs shadow">Confirm Payment</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Issue Advance -->
        <div v-if="showAdvanceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <h3 class="font-bold text-white text-lg">Issue Advance to {{ statement.supplier.name }}</h3>
                    <button type="button" @click="showAdvanceModal = false" class="text-slate-400 hover:text-slate-200"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submitAdvance" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Amount (৳) *</label>
                            <input v-model.number="advanceForm.amount" type="number" step="0.01" min="0.01" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-amber-400 font-mono font-bold focus:outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Date *</label>
                            <input v-model="advanceForm.advance_date" type="date" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Financial Account *</label>
                        <select v-model="advanceForm.payment_account_id" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500">
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.name }} (৳ {{ Number(acc.current_balance).toLocaleString() }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Reason</label>
                        <input v-model="advanceForm.reason" type="text" placeholder="Advance deposit for supplies" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                    </div>
                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                        <button type="button" @click="showAdvanceModal = false" class="px-4 py-2 text-xs text-slate-400">Cancel</button>
                        <button type="submit" :disabled="advanceForm.processing" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs shadow">Confirm Advance</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Add Product Rate -->
        <div v-if="showAddProductModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <h3 class="font-bold text-white text-lg">Add Product Rate</h3>
                    <button type="button" @click="showAddProductModal = false" class="text-slate-400 hover:text-slate-200"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submitAddProduct" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Product Name *</label>
                        <input v-model="productForm.product_name" type="text" required placeholder="e.g. Beef Tenderloin" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Unit (e.g. kg, pcs) *</label>
                            <input v-model="productForm.purchase_unit" type="text" required placeholder="kg" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Agreed Rate (৳) *</label>
                            <input v-model.number="productForm.unit_price" type="number" step="0.01" required placeholder="720.00" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 font-mono text-amber-400 focus:outline-none focus:border-amber-500" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Min Order Qty</label>
                        <input v-model.number="productForm.min_order_quantity" type="number" step="0.01" placeholder="Optional" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                    </div>
                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                        <button type="button" @click="showAddProductModal = false" class="px-4 py-2 text-xs text-slate-400">Cancel</button>
                        <button type="submit" :disabled="productForm.processing" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs shadow">Save Rate</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

