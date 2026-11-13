<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    ShieldCheck, 
    Search, 
    Filter, 
    Download, 
    Calendar, 
    User as UserIcon, 
    Eye, 
    X, 
    AlertCircle, 
    CheckCircle2, 
    Clock, 
    ArrowRight 
} from 'lucide-vue-next';

const props = defineProps({
    logs: Object,
    filters: Object,
    users: Array,
    modules: Object,
});

const search = ref(props.filters.search || '');
const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);
const selectedModule = ref(props.filters.module || 'all');
const selectedAction = ref(props.filters.action || 'all');
const selectedUser = ref(props.filters.user_id || 'all');

const activeDiffLog = ref(null);

const applyFilters = () => {
    router.get('/audit-logs', {
        start_date: startDate.value,
        end_date: endDate.value,
        module: selectedModule.value,
        action: selectedAction.value,
        user_id: selectedUser.value,
        search: search.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    startDate.value = new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0];
    endDate.value = new Date().toISOString().split('T')[0];
    selectedModule.value = 'all';
    selectedAction.value = 'all';
    selectedUser.value = 'all';
    search.value = '';
    applyFilters();
};

const exportCsv = () => {
    window.location.href = `/audit-logs/export?start_date=${startDate.value}&end_date=${endDate.value}&module=${selectedModule.value}`;
};

const viewDiff = (log) => {
    activeDiffLog.value = log;
};
</script>

<template>
    <AppLayout title="এক্টিভিটি অডিট লগ (Activity Audit Logs)">
        <div class="max-w-7xl mx-auto space-y-5">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold">
                        <ShieldCheck class="w-5 h-5" />
                    </div>
                    <div>
                        <h1 class="text-lg sm:text-xl font-bold text-white">এক্টিভিটি অডিট লগ (Audit Logs)</h1>
                        <p class="text-xs text-slate-400">সিস্টেমের সমস্ত পরিবর্তন ও লেনদেনের স্থায়ী রেকর্ড</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="exportCsv"
                        class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold transition flex items-center gap-1.5"
                    >
                        <Download class="w-4 h-4" />
                        <span>CSV এক্সপোর্ট</span>
                    </button>
                </div>
            </div>

            <!-- Filter Toolbar -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-2.5">
                    <!-- Start Date -->
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 block mb-1">শুরুর তারিখ</label>
                        <input
                            v-model="startDate"
                            @change="applyFilters"
                            type="date"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-amber-500"
                        />
                    </div>

                    <!-- End Date -->
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 block mb-1">শেষ তারিখ</label>
                        <input
                            v-model="endDate"
                            @change="applyFilters"
                            type="date"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-amber-500"
                        />
                    </div>

                    <!-- Module Filter -->
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 block mb-1">মডিউল (Module)</label>
                        <select
                            v-model="selectedModule"
                            @change="applyFilters"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-amber-500"
                        >
                            <option v-for="(name, key) in modules" :key="key" :value="key">
                                {{ name }}
                            </option>
                        </select>
                    </div>

                    <!-- User Filter -->
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 block mb-1">ইউজার (Actor)</label>
                        <select
                            v-model="selectedUser"
                            @change="applyFilters"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-amber-500"
                        >
                            <option value="all">সকল ইউজার</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>

                    <!-- Search Box -->
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 block mb-1">সার্চ (বিবরণ / রেফারেন্স)</label>
                        <div class="relative">
                            <input
                                v-model="search"
                                @keyup.enter="applyFilters"
                                type="text"
                                placeholder="রেফারেন্স বা বিবরণ লিখুন..."
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-8 pr-3 py-2 text-xs text-white placeholder-slate-500 focus:border-amber-500"
                            />
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-1 border-t border-slate-800/60">
                    <button
                        type="button"
                        @click="resetFilters"
                        class="text-xs text-slate-400 hover:text-white font-medium px-2 py-1"
                    >
                        রিসেট ফিল্টার
                    </button>
                    <button
                        type="button"
                        @click="applyFilters"
                        class="px-4 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-bold transition shadow-sm"
                    >
                        ফিল্টার প্রয়োগ করুন
                    </button>
                </div>
            </div>

            <!-- Audit Logs Timeline / List -->
            <div class="space-y-2.5">
                <div v-if="logs.data.length === 0" class="p-8 text-center bg-slate-900 border border-slate-800 rounded-2xl">
                    <AlertCircle class="w-8 h-8 text-slate-500 mx-auto mb-2" />
                    <p class="text-sm font-semibold text-slate-300">কোন অডিট রেকর্ড পাওয়া যায়নি</p>
                    <p class="text-xs text-slate-500 mt-1">অন্য তারিখ বা ফিল্টার নির্বাচন করুন</p>
                </div>

                <div
                    v-for="log in logs.data"
                    :key="log.id"
                    class="p-4 bg-slate-900 border border-slate-800 rounded-2xl hover:border-slate-700 transition space-y-2"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 text-xs">
                        <div class="flex items-center space-x-2">
                            <span class="font-bold text-white">{{ log.user_name }}</span>
                            <span class="text-slate-500">•</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase"
                                :class="log.status === 'success' ? 'bg-emerald-950/60 text-emerald-300 border border-emerald-800/60' : 'bg-rose-950/60 text-rose-300 border border-rose-800/60'"
                            >
                                {{ log.action }}
                            </span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-mono bg-slate-800 text-slate-300">
                                {{ log.module }}
                            </span>
                        </div>

                        <div class="flex items-center space-x-2 text-[11px] text-slate-400 font-mono">
                            <Clock class="w-3.5 h-3.5 text-slate-500" />
                            <span>{{ new Date(log.created_at).toLocaleString() }}</span>
                        </div>
                    </div>

                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs text-slate-200">
                            {{ log.description }}
                        </p>

                        <button
                            v-if="log.before_payload || log.after_payload"
                            type="button"
                            @click="viewDiff(log)"
                            class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-300 text-[11px] font-bold border border-slate-700 transition flex items-center gap-1 shrink-0"
                        >
                            <Eye class="w-3 h-3" />
                            <span>পরিবর্তন দেখুন (Diff)</span>
                        </button>
                    </div>

                    <div v-if="log.transaction_reference" class="text-[10px] font-mono text-slate-400">
                        রেফারেন্স: <span class="text-amber-400">{{ log.transaction_reference }}</span>
                    </div>

                    <div v-if="log.reason" class="text-[11px] text-slate-400 italic bg-slate-950/60 p-2 rounded-lg">
                        কারণ: {{ log.reason }}
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="logs.links && logs.links.length > 3" class="flex justify-center gap-1 pt-4">
                <Link
                    v-for="(link, i) in logs.links"
                    :key="i"
                    :href="link.url || '#'"
                    :class="[
                        'px-3 py-1.5 rounded-xl text-xs font-bold transition',
                        link.active ? 'bg-amber-500 text-slate-950 font-extrabold' : 'bg-slate-900 border border-slate-800 text-slate-300 hover:text-white',
                        !link.url ? 'opacity-40 cursor-not-allowed' : ''
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>

        <!-- Before/After Payload Diff Modal -->
        <div 
            v-if="activeDiffLog" 
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-slate-950/80 backdrop-blur-sm"
        >
            <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">
                <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-white">পরিবর্তনের বিস্তারিত বিবরণ (Audit Diff)</h3>
                        <p class="text-[11px] text-slate-400">অ্যাকশন: {{ activeDiffLog.action }} ({{ activeDiffLog.user_name }})</p>
                    </div>
                    <button 
                        type="button" 
                        @click="activeDiffLog = null"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white bg-slate-800/60"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-5 space-y-4 overflow-y-auto text-xs font-mono">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800">
                            <span class="text-[10px] font-bold text-rose-400 uppercase tracking-wider block mb-2">
                                পরিবর্তনের আগের মান (Before):
                            </span>
                            <pre class="text-slate-300 whitespace-pre-wrap break-words text-[11px]">{{ activeDiffLog.before_payload ? JSON.stringify(activeDiffLog.before_payload, null, 2) : 'কোন পূর্ববর্তী মান ছিল না' }}</pre>
                        </div>

                        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800">
                            <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider block mb-2">
                                পরিবর্তনের পরের মান (After):
                            </span>
                            <pre class="text-slate-300 whitespace-pre-wrap break-words text-[11px]">{{ activeDiffLog.after_payload ? JSON.stringify(activeDiffLog.after_payload, null, 2) : 'কোন নতুন মান ছিল না' }}</pre>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t border-slate-800 bg-slate-900/60 flex justify-end">
                    <button
                        type="button"
                        @click="activeDiffLog = null"
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition"
                    >
                        বন্ধ করুন (Close)
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
