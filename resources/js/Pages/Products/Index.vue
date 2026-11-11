<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Boxes, Plus, AlertCircle, Check } from 'lucide-vue-next';

const props = defineProps({
    products: Array,
    categories: Array,
    suppliers: Array,
});

const showModal = ref(false);

const form = useForm({
    category_id: '',
    supplier_id: '',
    name: '',
    code: '',
    product_type: 'raw_material',
    base_unit: 'g',
    purchase_unit: 'kg',
    conversion_multiplier: 1000,
    min_reorder_level: 0,
    is_consumable: false,
    default_allocation_days: '',
    opening_stock: 0,
    opening_cost_price: 0,
});

const openModal = () => {
    form.reset();
    form.base_unit = 'g';
    form.purchase_unit = 'kg';
    form.conversion_multiplier = 1000;
    showModal.value = true;
};

const submit = () => {
    form.post('/products', {
        onSuccess: () => {
            showModal.value = false;
        }
    });
};
</script>

<template>
    <AppLayout title="Products & Inventory Master">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Products & Ingredients Master</h1>
                <p class="text-xs text-slate-400 mt-1">
                    Manage base units, configurable purchase conversions, moving weighted-average costs, and reorder alerts.
                </p>
            </div>

            <button 
                @click="openModal"
                class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2 rounded-xl text-xs flex items-center space-x-1.5 shadow-lg shadow-amber-500/10 transition cursor-pointer"
            >
                <Plus class="w-4 h-4" />
                <span>Add Product</span>
            </button>
        </div>

        <!-- Products Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 bg-slate-950/60 border-b border-slate-800 font-semibold">
                            <th class="py-3 px-4">Item Name</th>
                            <th class="py-3 px-4">Category</th>
                            <th class="py-3 px-4">Base Unit</th>
                            <th class="py-3 px-4">Purchase Unit</th>
                            <th class="py-3 px-4">Unit Conversion</th>
                            <th class="py-3 px-4 text-right">On-Hand Stock</th>
                            <th class="py-3 px-4 text-right">Avg Cost / Base</th>
                            <th class="py-3 px-4 text-right">Total Valuation</th>
                            <th class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="p in products" :key="p.id" class="hover:bg-slate-800/30">
                            <td class="py-3 px-4">
                                <span class="font-bold text-white block">{{ p.name }}</span>
                                <span v-if="p.supplier" class="text-[11px] text-slate-400">Supplier: {{ p.supplier.name }}</span>
                            </td>
                            <td class="py-3 px-4 text-slate-300">
                                <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-800 text-slate-300">
                                    {{ p.category?.name }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-amber-300 font-bold">{{ p.base_unit }}</td>
                            <td class="py-3 px-4 font-mono text-slate-300">{{ p.purchase_unit }}</td>
                            <td class="py-3 px-4 text-slate-400 font-mono text-[11px]">
                                <span v-for="conv in p.unit_conversions" :key="conv.id">
                                    1 {{ conv.purchase_unit }} = {{ Number(conv.multiplier_to_base) }} {{ p.base_unit }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold" :class="Number(p.current_stock) <= Number(p.min_reorder_level) ? 'text-rose-400' : 'text-emerald-400'">
                                {{ p.current_stock }} {{ p.base_unit }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-slate-300">
                                ৳{{ Number(p.cost_price).toFixed(2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-200">
                                ৳{{ (Number(p.current_stock) * Number(p.cost_price)).toFixed(2) }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span v-if="p.is_active" class="text-emerald-400 font-medium text-[11px]">Active</span>
                                <span v-else class="text-slate-500 font-medium text-[11px]">Inactive</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Product Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl">
                <h2 class="text-base font-bold text-white mb-4">Add New Inventory Item</h2>

                <form @submit.prevent="submit" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="block text-slate-300 font-medium mb-1">Item Name *</label>
                            <input 
                                type="text" 
                                v-model="form.name" 
                                required 
                                placeholder="e.g. Cardamom, Basmati Rice"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Category *</label>
                            <select 
                                v-model="form.category_id" 
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option value="" disabled>Select category</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Supplier</label>
                            <select 
                                v-model="form.supplier_id"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option value="">None / Multiple</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Base Unit (Storage) *</label>
                            <input 
                                type="text" 
                                v-model="form.base_unit" 
                                required 
                                placeholder="g, kg, ml, L, pcs"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Default Purchase Unit *</label>
                            <input 
                                type="text" 
                                v-model="form.purchase_unit" 
                                required 
                                placeholder="kg, sack, carton, box"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div class="col-span-2 bg-slate-950 p-3 rounded-xl border border-slate-800/80">
                            <label class="block text-amber-400 font-semibold mb-1">
                                Unit Conversion: 1 {{ form.purchase_unit || 'Purchase Unit' }} = ? {{ form.base_unit || 'Base Units' }}
                            </label>
                            <input 
                                type="number" 
                                step="0.0001" 
                                v-model="form.conversion_multiplier" 
                                required 
                                placeholder="e.g. 1000 for kg to g, 25 for sack to kg"
                                class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                            <p class="text-[11px] text-slate-400 mt-1">
                                Ensures purchases in cartons/sacks automatically convert into exact base units.
                            </p>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Min Reorder Level</label>
                            <input 
                                type="number" 
                                step="0.01" 
                                v-model="form.min_reorder_level" 
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Opening Stock (in {{ form.base_unit }})</label>
                            <input 
                                type="number" 
                                step="0.01" 
                                v-model="form.opening_stock" 
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Opening Unit Cost (৳ / {{ form.base_unit }})</label>
                            <input 
                                type="number" 
                                step="0.0001" 
                                v-model="form.opening_cost_price" 
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div class="flex items-center space-x-2 pt-5">
                            <input 
                                type="checkbox" 
                                id="is_consumable" 
                                v-model="form.is_consumable" 
                                class="rounded bg-slate-950 border-slate-800 text-amber-500 focus:ring-0"
                            />
                            <label for="is_consumable" class="text-slate-300">Is Multi-day Consumable?</label>
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
                            Save Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

