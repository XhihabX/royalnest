<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    BookOpen, 
    Store, 
    Plus, 
    Search, 
    Phone, 
    MapPin, 
    Tag, 
    CheckCircle2, 
    AlertCircle, 
    Edit, 
    Trash2, 
    X, 
    Eye, 
    Package,
    ArrowRight
} from 'lucide-vue-next';
import { useI18n } from '../../Composables/useI18n';

const props = defineProps({
    suppliers: Array,
    stats: Object,
    products: Array,
    filters: Object,
});

const { t } = useI18n();

const search = ref(props.filters?.search || '');
const category = ref(props.filters?.category || 'all');
const status = ref(props.filters?.status || 'all');

const showAddModal = ref(false);
const showProductModal = ref(false);
const activeSupplier = ref(null);

const applyFilters = () => {
    router.get('/suppliers/directory', {
        search: search.value || undefined,
        category: category.value !== 'all' ? category.value : undefined,
        status: status.value !== 'all' ? status.value : undefined,
    }, { preserveState: true, replace: true });
};

// Form: Add Supplier to Directory
const addForm = useForm({
    name: '',
    company_name: '',
    contact_person: '',
    phone: '',
    alternative_phone: '',
    email: '',
    address: '',
    business_location: '',
    category: 'Raw Materials',
    status: 'potential',
    preferred_payment_terms: 'Cash on Delivery',
    notes: '',
    opening_balance: 0,
    products: [],
});

const addProductLine = () => {
    addForm.products.push({
        product_name: '',
        product_category: '',
        purchase_unit: 'kg',
        unit_price: '',
        min_order_quantity: '',
        notes: '',
    });
};

const removeProductLine = (index) => {
    addForm.products.splice(index, 1);
};

const submitAddSupplier = () => {
    addForm.post('/suppliers', {
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

// Form: Manage Supplied Products
const productForm = useForm({
    product_name: '',
    product_category: '',
    purchase_unit: 'kg',
    unit_price: '',
    min_order_quantity: '',
    notes: '',
});

const openProductModal = (supplier) => {
    activeSupplier.value = supplier;
    productForm.reset();
    showProductModal.value = true;
};

const submitAddProduct = () => {
    if (!activeSupplier.value) return;
    productForm.post(`/suppliers/${activeSupplier.value.id}/products`, {
        onSuccess: () => {
            productForm.reset();
            // Refresh active supplier reference
            const updated = props.suppliers.find(s => s.id === activeSupplier.value.id);
            if (updated) activeSupplier.value = updated;
        },
    });
};

const deleteProduct = (productId) => {
    if (confirm('Remove this product rate from supplier catalog?')) {
        router.delete(`/supplier-products/${productId}`, {
            preserveScroll: true,
            onSuccess: () => {
                const updated = props.suppliers.find(s => s.id === activeSupplier.value.id);
                if (updated) activeSupplier.value = updated;
            }
        });
    }
};

const updateStatus = (supplier, newStatus) => {
    router.post(`/suppliers/${supplier.id}/status`, {
        status: newStatus,
    }, { preserveScroll: true });
};

const statusColors = {
    potential: 'bg-amber-950/60 text-amber-400 border-amber-800/40',
    active: 'bg-emerald-950/60 text-emerald-400 border-emerald-800/40',
    inactive: 'bg-slate-800 text-slate-400 border-slate-700',
    archived: 'bg-rose-950/60 text-rose-400 border-rose-800/40',
};
</script>

<template>
    <AppLayout title="Supplier Directory">
        <Head :title="t('supplier_directory', 'Supplier Directory') + ' — Royal Nest'" />

        <div class="space-y-6">
            <!-- Distinct Top Navigation Bar (Directory vs Active) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-4">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                        <BookOpen class="w-6 h-6 text-amber-500" />
                        <span>{{ t('supplier_directory', 'Supplier Master Directory') }}</span>
                    </h1>
                    <p class="text-xs text-slate-400 mt-1">
                        Comprehensive registry of potential and existing vendors, shop locations, agreed rates, and supply catalogs.
                    </p>
                </div>

                <!-- Two Distinct Section Switch Tabs -->
                <div class="flex items-center gap-1 bg-slate-900 border border-slate-800 p-1 rounded-xl self-start sm:self-auto">
                    <Link
                        href="/suppliers/directory"
                        class="px-4 py-2 rounded-lg text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center gap-1.5 shadow-sm"
                    >
                        <BookOpen class="w-3.5 h-3.5" />
                        <span>{{ t('supplier_directory', 'Supplier Directory') }}</span>
                    </Link>
                    <Link
                        href="/suppliers/active"
                        class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition flex items-center gap-1.5"
                    >
                        <Store class="w-3.5 h-3.5" />
                        <span>{{ t('active_suppliers', 'Active Suppliers & Dues') }}</span>
                    </Link>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3.5">
                    <span class="text-xs font-medium text-slate-400 block">Total Directory</span>
                    <span class="text-xl font-bold text-slate-100 mt-0.5 block">{{ stats.total_directory }}</span>
                </div>
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3.5">
                    <span class="text-xs font-medium text-amber-400 block">{{ t('potential', 'Potential') }}</span>
                    <span class="text-xl font-bold text-amber-400 mt-0.5 block">{{ stats.potential }}</span>
                </div>
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3.5">
                    <span class="text-xs font-medium text-emerald-400 block">{{ t('active', 'Active Vendors') }}</span>
                    <span class="text-xl font-bold text-emerald-400 mt-0.5 block">{{ stats.active }}</span>
                </div>
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3.5">
                    <span class="text-xs font-medium text-slate-400 block">{{ t('inactive', 'Inactive') }}</span>
                    <span class="text-xl font-bold text-slate-300 mt-0.5 block">{{ stats.inactive }}</span>
                </div>
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3.5">
                    <span class="text-xs font-medium text-rose-400 block">{{ t('archived', 'Archived') }}</span>
                    <span class="text-xl font-bold text-rose-400 mt-0.5 block">{{ stats.archived }}</span>
                </div>
            </div>

            <!-- Filter & Action Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 flex-1">
                    <div class="relative flex-1 max-w-xs">
                        <Search class="w-4 h-4 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            v-model="search"
                            @input="applyFilters"
                            type="text"
                            :placeholder="t('search', 'Search name, company, phone...')"
                            class="w-full bg-slate-900 border border-slate-800 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <select
                        v-model="category"
                        @change="applyFilters"
                        class="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-amber-500"
                    >
                        <option value="all">All Categories</option>
                        <option value="Meat & Poultry">Meat & Poultry</option>
                        <option value="Vegetables & Produce">Vegetables & Produce</option>
                        <option value="Spices & Dry Goods">Spices & Dry Goods</option>
                        <option value="Dairy & Bakery">Dairy & Bakery</option>
                        <option value="Beverages & Syrups">Beverages & Syrups</option>
                        <option value="Packaging & Disposables">Packaging & Disposables</option>
                        <option value="Other">Other</option>
                    </select>

                    <select
                        v-model="status"
                        @change="applyFilters"
                        class="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-amber-500"
                    >
                        <option value="all">All Statuses</option>
                        <option value="potential">Potential</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <button
                    type="button"
                    @click="showAddModal = true"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs shadow-lg shadow-amber-950 transition active:scale-95 shrink-0"
                >
                    <Plus class="w-4 h-4" />
                    <span>{{ t('add_supplier', 'Add to Directory') }}</span>
                </button>
            </div>

            <!-- Supplier Directory Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm text-slate-300">
                        <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3.5">{{ t('supplier_name', 'Supplier / Business') }}</th>
                                <th class="px-4 py-3.5">{{ t('phone', 'Contact & Location') }}</th>
                                <th class="px-4 py-3.5">{{ t('category', 'Category & Terms') }}</th>
                                <th class="px-4 py-3.5 text-center">Supplied Products</th>
                                <th class="px-4 py-3.5 text-center">{{ t('status', 'Status') }}</th>
                                <th class="px-5 py-3.5 text-right">{{ t('actions', 'Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="supplier in suppliers" :key="supplier.id" class="hover:bg-slate-800/40 transition">
                                <td class="px-5 py-4">
                                    <div class="font-bold text-slate-100 text-base flex items-center gap-2">
                                        <span>{{ supplier.name }}</span>
                                        <span v-if="supplier.company_name" class="text-xs px-2 py-0.5 rounded bg-slate-800 text-slate-300 font-normal">
                                            {{ supplier.company_name }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-400 mt-1 flex items-center gap-1">
                                        <MapPin class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                                        <span>{{ supplier.business_location || supplier.address || 'Location not specified' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div v-if="supplier.contact_person" class="font-semibold text-slate-200 text-xs">{{ supplier.contact_person }}</div>
                                    <div v-if="supplier.phone" class="text-xs text-amber-400 font-mono flex items-center gap-1 mt-0.5">
                                        <Phone class="w-3 h-3 text-slate-500" />
                                        <a :href="'tel:' + supplier.phone" class="hover:underline">{{ supplier.phone }}</a>
                                    </div>
                                    <div v-if="supplier.alternative_phone" class="text-[11px] text-slate-500 font-mono">
                                        Alt: {{ supplier.alternative_phone }}
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-800 text-slate-300 border border-slate-700">
                                        {{ supplier.category || 'General' }}
                                    </span>
                                    <div class="text-[11px] text-slate-500 mt-1">
                                        Terms: {{ supplier.preferred_payment_terms || 'Cash on Delivery' }}
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button
                                        type="button"
                                        @click="openProductModal(supplier)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-400 text-xs font-mono font-medium transition"
                                    >
                                        <Package class="w-3.5 h-3.5" />
                                        <span>{{ supplier.products_count || supplier.products?.length || 0 }} items</span>
                                    </button>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span :class="['px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize border', statusColors[supplier.status] || 'bg-slate-800 text-slate-300']">
                                        {{ supplier.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Activate / Deactivate Toggle -->
                                        <button
                                            v-if="supplier.status !== 'active'"
                                            type="button"
                                            @click="updateStatus(supplier, 'active')"
                                            class="px-2.5 py-1 rounded-lg bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/30 text-xs font-semibold transition"
                                            title="Activate for Purchases & Dues"
                                        >
                                            Activate
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            @click="updateStatus(supplier, 'inactive')"
                                            class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs font-medium transition"
                                            title="Deactivate"
                                        >
                                            Deactivate
                                        </button>

                                        <!-- Profile Link -->
                                        <Link
                                            :href="`/suppliers/${supplier.id}/profile`"
                                            class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
                                            title="View Full Profile & Statement"
                                        >
                                            <Eye class="w-4 h-4 text-amber-400" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="suppliers.length === 0">
                                <td colspan="6" class="px-5 py-8 text-center text-slate-500 text-xs">
                                    No suppliers found in directory matching your criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: Add Supplier to Directory -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in overflow-y-auto">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl my-8">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <h3 class="font-bold text-white text-lg flex items-center gap-2">
                        <BookOpen class="w-5 h-5 text-amber-500" />
                        <span>Register Supplier in Master Directory</span>
                    </h3>
                    <button type="button" @click="showAddModal = false" class="text-slate-400 hover:text-slate-200">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitAddSupplier" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('supplier_name', 'Supplier / Trade Name') }} *</label>
                            <input
                                v-model="addForm.name"
                                type="text"
                                required
                                placeholder="e.g. Haji Beef House"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('company_name', 'Company / Registered Name') }}</label>
                            <input
                                v-model="addForm.company_name"
                                type="text"
                                placeholder="e.g. Haji Meat Trading Ltd."
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('contact_person', 'Contact Person') }}</label>
                            <input
                                v-model="addForm.contact_person"
                                type="text"
                                placeholder="e.g. Md. Mostafa"
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
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('alt_phone', 'Alt Phone') }}</label>
                            <input
                                v-model="addForm.alternative_phone"
                                type="text"
                                placeholder="018XXXXXXXX"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('category', 'Category') }}</label>
                            <select
                                v-model="addForm.category"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            >
                                <option value="Meat & Poultry">Meat & Poultry</option>
                                <option value="Vegetables & Produce">Vegetables & Produce</option>
                                <option value="Spices & Dry Goods">Spices & Dry Goods</option>
                                <option value="Dairy & Bakery">Dairy & Bakery</option>
                                <option value="Beverages & Syrups">Beverages & Syrups</option>
                                <option value="Packaging & Disposables">Packaging & Disposables</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('status', 'Initial Status') }}</label>
                            <select
                                v-model="addForm.status"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            >
                                <option value="potential">Potential (Directory only)</option>
                                <option value="active">Active (Ready for Purchases)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('preferred_payment_terms', 'Payment Terms') }}</label>
                            <input
                                v-model="addForm.preferred_payment_terms"
                                type="text"
                                placeholder="e.g. Net 7 Days, COD"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('business_location', 'Market Location') }}</label>
                            <input
                                v-model="addForm.business_location"
                                type="text"
                                placeholder="e.g. Kawran Bazar Market, Shed 4"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('opening_balance', 'Opening Due (৳)') }}</label>
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
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('address', 'Full Address') }}</label>
                        <textarea
                            v-model="addForm.address"
                            rows="2"
                            placeholder="Full street address..."
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"
                        ></textarea>
                    </div>

                    <!-- Products Supplied Section -->
                    <div class="bg-slate-950/70 border border-slate-800 rounded-xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-slate-300">Supplied Products & Quoted Rates</span>
                            <button
                                type="button"
                                @click="addProductLine"
                                class="text-xs text-amber-400 hover:text-amber-300 font-semibold flex items-center gap-1"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Add Product</span>
                            </button>
                        </div>

                        <div v-for="(p, idx) in addForm.products" :key="idx" class="grid grid-cols-12 gap-2 mb-2 items-center">
                            <div class="col-span-5">
                                <input
                                    v-model="p.product_name"
                                    type="text"
                                    placeholder="Product Name (e.g. Beef)"
                                    class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500"
                                />
                            </div>
                            <div class="col-span-2">
                                <input
                                    v-model="p.purchase_unit"
                                    type="text"
                                    placeholder="Unit (kg)"
                                    class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500"
                                />
                            </div>
                            <div class="col-span-4">
                                <input
                                    v-model.number="p.unit_price"
                                    type="number"
                                    step="0.01"
                                    placeholder="Rate ৳"
                                    class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 font-mono focus:outline-none focus:border-amber-500"
                                />
                            </div>
                            <div class="col-span-1 text-right">
                                <button type="button" @click="removeProductLine(idx)" class="text-rose-400 hover:text-rose-300 p-1">
                                    <X class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        <div v-if="addForm.products.length === 0" class="text-center text-slate-500 text-xs py-2 italic">
                            No products added yet. Click "+ Add Product" to record agreed rates.
                        </div>
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
                            {{ t('save', 'Save to Directory') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Manage Supplied Products Catalog -->
        <div v-if="showProductModal && activeSupplier" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <div>
                        <h3 class="font-bold text-white text-lg flex items-center gap-2">
                            <Package class="w-5 h-5 text-amber-500" />
                            <span>Supplied Products: {{ activeSupplier.name }}</span>
                        </h3>
                    </div>
                    <button type="button" @click="showProductModal = false" class="text-slate-400 hover:text-slate-200">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <!-- Existing Products List -->
                    <div class="max-h-48 overflow-y-auto space-y-2">
                        <div
                            v-for="prod in activeSupplier.products"
                            :key="prod.id"
                            class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-xs"
                        >
                            <div>
                                <span class="font-semibold text-slate-200">{{ prod.product_name }}</span>
                                <span class="text-slate-500 ml-2">({{ prod.purchase_unit }})</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-mono font-bold text-amber-400">৳ {{ Number(prod.unit_price).toLocaleString() }}</span>
                                <button type="button" @click="deleteProduct(prod.id)" class="text-slate-500 hover:text-rose-400">
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        <div v-if="!activeSupplier.products || activeSupplier.products.length === 0" class="text-center text-slate-500 text-xs py-4">
                            No products configured yet for this supplier.
                        </div>
                    </div>

                    <!-- Add New Product to Supplier -->
                    <form @submit.prevent="submitAddProduct" class="border-t border-slate-800 pt-4 space-y-3">
                        <span class="text-xs font-semibold text-slate-300 block">Add Product & Agreed Rate</span>
                        <div class="grid grid-cols-2 gap-3">
                            <input
                                v-model="productForm.product_name"
                                type="text"
                                required
                                placeholder="Product Name (e.g. Basmati Rice)"
                                class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                            <input
                                v-model="productForm.purchase_unit"
                                type="text"
                                required
                                placeholder="Unit (e.g. kg, pcs, sack)"
                                class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <input
                                v-model.number="productForm.unit_price"
                                type="number"
                                step="0.01"
                                required
                                placeholder="Agreed Rate (৳)"
                                class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 font-mono text-amber-400 focus:outline-none focus:border-amber-500"
                            />
                            <input
                                v-model.number="productForm.min_order_quantity"
                                type="number"
                                step="0.01"
                                placeholder="Min Order Qty (Optional)"
                                class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="productForm.processing"
                                class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs shadow transition active:scale-95"
                            >
                                Add Rate
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
