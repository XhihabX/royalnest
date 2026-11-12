<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Truck, Plus, CheckCircle, Trash2, Zap, AlertCircle } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    purchases: Array,
    products: Array,
    suppliers: Array,
    accounts: Array,
});

const showModal = ref(false);

const form = useForm({
    supplier_id: '',
    invoice_no: '',
    purchase_date: new Date().toISOString().split('T')[0],
    paid_amount: 0,
    payment_account_id: '',
    is_purchase_and_consume: false,
    notes: '',
    items: [
        { product_id: '', purchase_quantity: 1, purchase_unit: '', unit_price: 0 }
    ],
});

const onProductSelect = (index) => {
    const pId = form.items[index].product_id;
    const prod = props.products.find(p => p.id === pId);
    if (prod) {
        form.items[index].purchase_unit = prod.purchase_unit;
        const conv = prod.unit_conversions?.find(c => c.purchase_unit === prod.purchase_unit) 
            || prod.unitConversions?.find(c => c.purchase_unit === prod.purchase_unit);
        const multiplier = conv ? Number(conv.multiplier_to_base) : 1;
        const baseCost = Number(prod.cost_price) || 0;
        form.items[index].unit_price = Number((baseCost * multiplier).toFixed(2)) || 0;
    }
};

const addItem = () => {
    form.items.push({ product_id: '', purchase_quantity: 1, purchase_unit: '', unit_price: 0 });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const computedTotal = computed(() => {
    return form.items.reduce((sum, item) => {
        return sum + ((parseFloat(item.purchase_quantity) || 0) * (parseFloat(item.unit_price) || 0));
    }, 0);
});

const openModal = () => {
    form.reset();
    form.items = [{ product_id: '', purchase_quantity: 1, purchase_unit: '', unit_price: 0 }];
    showModal.value = true;
};

const submit = () => {
    form.post('/purchases', {
        onSuccess: () => {
            showModal.value = false;
        }
    });
};
</script>

<template>
    <AppLayout title="Stock Receiving & Purchasing">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">{{ t('purchases', 'Stock Receiving & Purchases') }}</h1>
                <p class="text-xs text-slate-400 mt-1">
                    {{ t('purchases_desc', 'Confirmed receipts create FIFO lots and increase perpetual inventory stock.') }}
                </p>
            </div>

            <button 
                @click="openModal"
                class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2 rounded-xl text-xs flex items-center space-x-1.5 shadow-lg shadow-amber-500/10 transition cursor-pointer"
            >
                <Plus class="w-4 h-4" />
                <span>{{ t('record_purchase', 'Record Stock Receipt') }}</span>
            </button>
        </div>

        <!-- Purchases Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 bg-slate-950/60 border-b border-slate-800 font-semibold">
                            <th class="py-3 px-4">Invoice / Ref</th>
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Supplier</th>
                            <th class="py-3 px-4">Items Received</th>
                            <th class="py-3 px-4 text-right">Total (৳)</th>
                            <th class="py-3 px-4 text-right">Paid (৳)</th>
                            <th class="py-3 px-4 text-center">Workflow</th>
                            <th class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="p in purchases" :key="p.id" class="hover:bg-slate-800/30">
                            <td class="py-3 px-4 font-mono font-medium text-slate-200">{{ p.invoice_no || `PUR-${p.id}` }}</td>
                            <td class="py-3 px-4 font-mono text-slate-400">{{ p.purchase_date }}</td>
                            <td class="py-3 px-4 font-medium text-white">{{ p.supplier?.name || 'Local Market / Petty' }}</td>
                            <td class="py-3 px-4 text-slate-300">
                                <span v-for="(it, i) in p.items" :key="it.id">
                                    {{ it.product?.name }} ({{ it.purchase_quantity }} {{ it.purchase_unit }}){{ i < p.items.length - 1 ? ', ' : '' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-white">৳{{ Number(p.total_amount).toFixed(2) }}</td>
                            <td class="py-3 px-4 text-right font-mono text-emerald-400">৳{{ Number(p.paid_amount).toFixed(2) }}</td>
                            <td class="py-3 px-4 text-center">
                                <span 
                                    v-if="p.is_purchase_and_consume"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-300 border border-amber-500/30 inline-flex items-center space-x-1"
                                >
                                    <Zap class="w-3 h-3 text-amber-400" />
                                    <span>Purchase & Consume</span>
                                </span>
                                <span v-else class="text-slate-400 text-[11px]">Standard Receipt</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    {{ p.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Record Purchase Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-white">Record Purchase / Stock Receipt</h2>
                    <span class="text-xs font-mono text-amber-400 font-bold">Total: ৳{{ computedTotal.toFixed(2) }}</span>
                </div>

                <form @submit.prevent="submit" class="space-y-4 text-xs">
                    <!-- Workflow toggle: Purchase & Consume -->
                    <div class="p-3.5 rounded-xl border border-amber-500/40 bg-amber-500/10 flex items-start space-x-3">
                        <input 
                            type="checkbox" 
                            id="purchase_consume" 
                            v-model="form.is_purchase_and_consume" 
                            class="mt-0.5 rounded bg-slate-950 border-amber-500 text-amber-500 focus:ring-0 cursor-pointer"
                        />
                        <div>
                            <label for="purchase_consume" class="font-bold text-amber-300 cursor-pointer block">
                                Direct "Purchase & Consume" Workflow (PRD Section 6)
                            </label>
                            <p class="text-[11px] text-amber-200/80 mt-0.5">
                                Check this if ingredients were bought and fully used on the same day. Automatically records stock receipt (+Q) and immediate consumption (-Q). Ending stock will be 0, recognized once as consumption cost without duplicate expense.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Supplier</label>
                            <select 
                                v-model="form.supplier_id" 
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option value="">Local Market / Cash</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Invoice / Memo No</label>
                            <input 
                                type="text" 
                                v-model="form.invoice_no" 
                                placeholder="e.g. INV-1049"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Date</label>
                            <input 
                                type="date" 
                                v-model="form.purchase_date" 
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Line Items -->
                    <div class="pt-2">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Purchased Items</span>
                            <button 
                                type="button" 
                                @click="addItem" 
                                class="text-xs text-amber-400 hover:text-amber-300 flex items-center space-x-1 cursor-pointer font-medium"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Add Another Item</span>
                            </button>
                        </div>

                        <div class="space-y-2">
                            <div 
                                v-for="(item, idx) in form.items" 
                                :key="idx"
                                class="grid grid-cols-12 gap-2 items-center bg-slate-950/60 p-2.5 rounded-xl border border-slate-800/80"
                            >
                                <div class="col-span-5">
                                    <select 
                                        v-model="item.product_id" 
                                        @change="onProductSelect(idx)"
                                        required
                                        class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white focus:border-amber-500 focus:outline-none"
                                    >
                                        <option value="" disabled>Select product</option>
                                        <option v-for="prod in products" :key="prod.id" :value="prod.id">
                                            {{ prod.name }} (Base: {{ prod.base_unit }})
                                        </option>
                                    </select>
                                </div>

                                <div class="col-span-2">
                                    <input 
                                        type="number" 
                                        step="0.0001" 
                                        v-model="item.purchase_quantity" 
                                        required 
                                        placeholder="Qty"
                                        class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono focus:border-amber-500 focus:outline-none text-right"
                                    />
                                </div>

                                <div class="col-span-2">
                                    <input 
                                        type="text" 
                                        v-model="item.purchase_unit" 
                                        required 
                                        placeholder="Unit"
                                        class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-slate-300 font-mono focus:border-amber-500 focus:outline-none"
                                    />
                                </div>

                                <div class="col-span-2">
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        v-model="item.unit_price" 
                                        required 
                                        placeholder="Price (৳)"
                                        class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono focus:border-amber-500 focus:outline-none text-right"
                                    />
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
                        </div>
                    </div>

                    <!-- Payment Section -->
                    <div class="pt-3 border-t border-slate-800 grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Paid Amount (৳)</label>
                            <input 
                                type="number" 
                                step="0.01" 
                                v-model="form.paid_amount" 
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Paid From Account</label>
                            <select 
                                v-model="form.payment_account_id" 
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option value="">None / Unpaid Bill</option>
                                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }} (৳{{ a.current_balance }})</option>
                            </select>
                        </div>
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
                            Confirm Receipt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

