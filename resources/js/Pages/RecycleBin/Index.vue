<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    Archive, 
    RotateCcw, 
    Trash2, 
    Boxes, 
    Store, 
    Users, 
    Layers, 
    AlertCircle, 
    Check, 
    Clock, 
    ShieldAlert 
} from 'lucide-vue-next';

const props = defineProps({
    activeTab: String,
    products: Object,
    suppliers: Object,
    employees: Object,
    categories: Object,
    counts: Object,
});

const currentTab = ref(props.activeTab || 'products');

const switchTab = (tab) => {
    currentTab.value = tab;
    router.get('/recycle-bin', { tab }, { preserveState: true, preserveScroll: true });
};

const handleRestore = (type, id, name) => {
    if (confirm(`আপনি কি নিশ্চিত যে '${name}' রিস্টোর করতে চান?`)) {
        router.post('/recycle-bin/restore', {
            type,
            id,
        });
    }
};

const handlePurge = (type, id, name) => {
    if (confirm(`সতর্কতা: '${name}' স্থায়ীভাবে ডিলিট করলে এটি আর কখনও ফিরিয়ে আনা যাবে না। আপনি কি নিশ্চিত?`)) {
        router.delete('/recycle-bin/purge', {
            data: { type, id },
        });
    }
};
</script>

<template>
    <AppLayout title="রিসাইকেল বিন ও রিকভারি (Recycle Bin)">
        <div class="max-w-6xl mx-auto space-y-5">
            <!-- Header -->
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold">
                    <Archive class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-lg sm:text-xl font-bold text-white">রিসাইকেল বিন ও রেকর্ড পুনরুদ্ধার</h1>
                    <p class="text-xs text-slate-400">ভুলবশত ডিলেট হওয়া আইটেম নিরাপদে ফিরিয়ে আনুন</p>
                </div>
            </div>

            <!-- Tab Pills -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
                <button
                    type="button"
                    @click="switchTab('products')"
                    :class="[
                        'px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-2 shrink-0 border min-h-[44px]',
                        currentTab === 'products'
                            ? 'bg-amber-500 border-amber-500 text-slate-950 font-extrabold shadow-md shadow-amber-500/20'
                            : 'bg-slate-900 border-slate-800 text-slate-300 hover:text-white'
                    ]"
                >
                    <Boxes class="w-4 h-4" />
                    <span>পণ্যসমূহ (Products)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-950/60 text-amber-300 font-mono">
                        {{ counts.products }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="switchTab('suppliers')"
                    :class="[
                        'px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-2 shrink-0 border min-h-[44px]',
                        currentTab === 'suppliers'
                            ? 'bg-amber-500 border-amber-500 text-slate-950 font-extrabold shadow-md shadow-amber-500/20'
                            : 'bg-slate-900 border-slate-800 text-slate-300 hover:text-white'
                    ]"
                >
                    <Store class="w-4 h-4" />
                    <span>সরবরাহকারী (Suppliers)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-950/60 text-amber-300 font-mono">
                        {{ counts.suppliers }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="switchTab('employees')"
                    :class="[
                        'px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-2 shrink-0 border min-h-[44px]',
                        currentTab === 'employees'
                            ? 'bg-amber-500 border-amber-500 text-slate-950 font-extrabold shadow-md shadow-amber-500/20'
                            : 'bg-slate-900 border-slate-800 text-slate-300 hover:text-white'
                    ]"
                >
                    <Users class="w-4 h-4" />
                    <span>কর্মী (Staff Members)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-950/60 text-amber-300 font-mono">
                        {{ counts.employees }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="switchTab('categories')"
                    :class="[
                        'px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-2 shrink-0 border min-h-[44px]',
                        currentTab === 'categories'
                            ? 'bg-amber-500 border-amber-500 text-slate-950 font-extrabold shadow-md shadow-amber-500/20'
                            : 'bg-slate-900 border-slate-800 text-slate-300 hover:text-white'
                    ]"
                >
                    <Layers class="w-4 h-4" />
                    <span>ক্যাটাগরি (Categories)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-950/60 text-amber-300 font-mono">
                        {{ counts.categories }}
                    </span>
                </button>
            </div>

            <!-- Tab Content -->
            <div class="space-y-3">
                <!-- Products Tab -->
                <div v-if="currentTab === 'products'" class="space-y-2.5">
                    <div v-if="products.data.length === 0" class="p-8 text-center bg-slate-900 border border-slate-800 rounded-2xl">
                        <Check class="w-8 h-8 text-emerald-400 mx-auto mb-2" />
                        <p class="text-sm font-semibold text-slate-300">কোন ডিলেট হওয়া পণ্য নেই</p>
                    </div>

                    <div
                        v-for="p in products.data"
                        :key="p.id"
                        class="p-4 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                    >
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm font-bold text-white">{{ p.name }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] bg-slate-800 text-slate-300 font-mono">
                                    {{ p.purchase_unit }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">
                                ক্যাটাগরি: {{ p.category?.name || 'N/A' }} • ডিলেট: {{ new Date(p.deleted_at).toLocaleString() }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="handleRestore('product', p.id, p.name)"
                                class="px-3 py-2 bg-emerald-950/60 hover:bg-emerald-900 border border-emerald-800/80 text-emerald-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5 min-h-[40px]"
                            >
                                <RotateCcw class="w-4 h-4" />
                                <span>পুনরুদ্ধার (Restore)</span>
                            </button>

                            <button
                                type="button"
                                @click="handlePurge('product', p.id, p.name)"
                                class="px-3 py-2 bg-rose-950/60 hover:bg-rose-900 border border-rose-800/80 text-rose-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5 min-h-[40px]"
                            >
                                <Trash2 class="w-4 h-4" />
                                <span>মুছুন (Purge)</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Suppliers Tab -->
                <div v-if="currentTab === 'suppliers'" class="space-y-2.5">
                    <div v-if="suppliers.data.length === 0" class="p-8 text-center bg-slate-900 border border-slate-800 rounded-2xl">
                        <Check class="w-8 h-8 text-emerald-400 mx-auto mb-2" />
                        <p class="text-sm font-semibold text-slate-300">কোন ডিলেট হওয়া সরবরাহকারী নেই</p>
                    </div>

                    <div
                        v-for="s in suppliers.data"
                        :key="s.id"
                        class="p-4 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                    >
                        <div>
                            <span class="text-sm font-bold text-white">{{ s.company_name }}</span>
                            <p class="text-xs text-slate-400 mt-1">
                                ফোন: {{ s.phone }} • বাকি: ৳{{ s.current_balance }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="handleRestore('supplier', s.id, s.company_name)"
                                class="px-3 py-2 bg-emerald-950/60 hover:bg-emerald-900 border border-emerald-800/80 text-emerald-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5 min-h-[40px]"
                            >
                                <RotateCcw class="w-4 h-4" />
                                <span>পুনরুদ্ধার (Restore)</span>
                            </button>

                            <button
                                type="button"
                                @click="handlePurge('supplier', s.id, s.company_name)"
                                class="px-3 py-2 bg-rose-950/60 hover:bg-rose-900 border border-rose-800/80 text-rose-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5 min-h-[40px]"
                            >
                                <Trash2 class="w-4 h-4" />
                                <span>মুছুন (Purge)</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Employees Tab -->
                <div v-if="currentTab === 'employees'" class="space-y-2.5">
                    <div v-if="employees.data.length === 0" class="p-8 text-center bg-slate-900 border border-slate-800 rounded-2xl">
                        <Check class="w-8 h-8 text-emerald-400 mx-auto mb-2" />
                        <p class="text-sm font-semibold text-slate-300">কোন ডিলেট হওয়া কর্মী নেই</p>
                    </div>

                    <div
                        v-for="e in employees.data"
                        :key="e.id"
                        class="p-4 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                    >
                        <div>
                            <span class="text-sm font-bold text-white">{{ e.name }}</span>
                            <p class="text-xs text-slate-400 mt-1">
                                পদবী: {{ e.role }} • আইডি: {{ e.employee_id }} • ফোন: {{ e.phone }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="handleRestore('employee', e.id, e.name)"
                                class="px-3 py-2 bg-emerald-950/60 hover:bg-emerald-900 border border-emerald-800/80 text-emerald-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5 min-h-[40px]"
                            >
                                <RotateCcw class="w-4 h-4" />
                                <span>পুনরুদ্ধার (Restore)</span>
                            </button>

                            <button
                                type="button"
                                @click="handlePurge('employee', e.id, e.name)"
                                class="px-3 py-2 bg-rose-950/60 hover:bg-rose-900 border border-rose-800/80 text-rose-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5 min-h-[40px]"
                            >
                                <Trash2 class="w-4 h-4" />
                                <span>মুছুন (Purge)</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Categories Tab -->
                <div v-if="currentTab === 'categories'" class="space-y-2.5">
                    <div v-if="categories.data.length === 0" class="p-8 text-center bg-slate-900 border border-slate-800 rounded-2xl">
                        <Check class="w-8 h-8 text-emerald-400 mx-auto mb-2" />
                        <p class="text-sm font-semibold text-slate-300">কোন ডিলেট হওয়া ক্যাটাগরি নেই</p>
                    </div>

                    <div
                        v-for="c in categories.data"
                        :key="c.id"
                        class="p-4 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                    >
                        <div>
                            <span class="text-sm font-bold text-white">{{ c.name }}</span>
                            <p class="text-xs text-slate-400 mt-1">
                                টাইপ: {{ c.type }} • ডিলেট: {{ new Date(c.deleted_at).toLocaleString() }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="handleRestore('category', c.id, c.name)"
                                class="px-3 py-2 bg-emerald-950/60 hover:bg-emerald-900 border border-emerald-800/80 text-emerald-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5 min-h-[40px]"
                            >
                                <RotateCcw class="w-4 h-4" />
                                <span>পুনরুদ্ধার (Restore)</span>
                            </button>

                            <button
                                type="button"
                                @click="handlePurge('category', c.id, c.name)"
                                class="px-3 py-2 bg-rose-950/60 hover:bg-rose-900 border border-rose-800/80 text-rose-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5 min-h-[40px]"
                            >
                                <Trash2 class="w-4 h-4" />
                                <span>মুছুন (Purge)</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

