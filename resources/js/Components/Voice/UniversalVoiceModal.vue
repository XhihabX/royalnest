<script setup>
import { ref, computed, watch } from 'vue';
import { 
    Mic, 
    MicOff, 
    X, 
    Check, 
    RefreshCw, 
    Globe, 
    AlertCircle, 
    Sparkles, 
    Edit3,
    ArrowRight,
    Plus,
    Trash2
} from 'lucide-vue-next';
import { useVoiceCostParser } from '../../Composables/useVoiceCostParser';

const props = defineProps({
    modelValue: Boolean,
    contextType: {
        type: String,
        default: 'costs', // costs, purchases, expenses, sales, attendance, employees, reconciliation, wastage, calculator, consumption
    },
    title: {
        type: String,
        default: 'ভয়েস টাইপিং ও এন্ট্রি',
    },
    hint: {
        type: String,
        default: 'উদাহরণ: "আলু ৫ কেজি ৫০০ টাকা ক্যাশ" বা "বিক্রি ২৫০০০ ক্যাশ ২০০০০ বিকাশ ৫০০০"',
    },
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const {
    isListening,
    transcript,
    interimTranscript,
    voiceLanguage,
    errorMessage,
    isSupported,
    startListening,
    stopListening,
    resetTranscript,
    setLanguage,
    parseSpokenCost,
} = useVoiceCostParser();

// State for review & confirmation modal
const isManualMode = ref(!isSupported.value);
const manualTextInput = ref('');
const isSearchingDb = ref(false);
const dbSearchResults = ref([]);

// Extracted & editable fields
const reviewFields = ref({
    item_name: '',
    product_id: null,
    supplier_id: null,
    employee_id: null,
    payment_account_id: null,
    quantity: null,
    unit: 'kg',
    unit_price: null,
    amount: null,
    total_sales: null,
    cash_sales: null,
    bkash_sales: null,
    nagad_sales: null,
    card_sales: null,
    other_sales: null,
    shift: 'morning',
    attendance_status: 'present',
    reason: 'spoiled',
    category: 'electricity',
    payment_method: 'cash',
    counted_closing_cash: null,
    items: [],
    note: '',
});

// Default to Bengali
voiceLanguage.value = 'bn-BD';

// Watch for transcript changes and parse
watch(transcript, async (newVal) => {
    if (newVal && newVal.trim().length > 1) {
        await processSpokenText(newVal.trim());
    }
});

const toggleListening = () => {
    if (isListening.value) {
        stopListening();
    } else {
        startListening();
    }
};

const toggleLanguage = () => {
    const nextLang = voiceLanguage.value === 'bn-BD' ? 'en-US' : 'bn-BD';
    setLanguage(nextLang);
};

// Process spoken text and match against database
const processSpokenText = async (rawText) => {
    const parsed = parseSpokenCost(rawText, props.contextType);
    if (!parsed) return;
    
    reviewFields.value.amount = parsed.amount || null;
    reviewFields.value.quantity = parsed.quantity || null;
    reviewFields.value.unit = parsed.unit || 'kg';
    reviewFields.value.unit_price = parsed.unit_price || null;
    reviewFields.value.item_name = parsed.item_name || parsed.name || '';
    reviewFields.value.note = rawText;
    reviewFields.value.payment_method = parsed.payment_method || 'cash';
    reviewFields.value.shift = parsed.shift || 'morning';
    reviewFields.value.attendance_status = parsed.attendance_status || 'present';
    reviewFields.value.reason = parsed.reason || 'spoiled';
    reviewFields.value.category = parsed.expense_category || 'electricity';
    reviewFields.value.counted_closing_cash = parsed.counted_closing_cash || parsed.amount || null;

    // Sales fields
    reviewFields.value.total_sales = parsed.total_sales || null;
    reviewFields.value.cash_sales = parsed.cash_sales || null;
    reviewFields.value.bkash_sales = parsed.bkash_sales || null;
    reviewFields.value.nagad_sales = parsed.nagad_sales || null;
    reviewFields.value.card_sales = parsed.card_sales || null;
    reviewFields.value.other_sales = parsed.other_sales || null;

    // Multi-items for consumption / calculator
    if (parsed.items && parsed.items.length > 0) {
        reviewFields.value.items = parsed.items.map(it => ({ ...it }));
    } else {
        reviewFields.value.items = [];
    }

    // Query database for fresh matching entities
    const term = parsed.item_name || parsed.name || rawText.split(' ')[0] || '';
    if (term.length >= 2) {
        isSearchingDb.value = true;
        try {
            const lookupType = props.contextType === 'purchases' ? 'purchase_entities' : 
                               (props.contextType === 'attendance' || props.contextType === 'employees') ? 'employees' : 'cost_entities';
            const params = new URLSearchParams({ type: lookupType, q: term });
            const res = await fetch(`/api/voice/lookup?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            const respData = await res.json();

            if (respData?.data) {
                const data = respData.data;
                dbSearchResults.value = data;

                // Auto-match first plausible product
                if (data.products && data.products.length > 0) {
                    reviewFields.value.item_name = data.products[0].name;
                    reviewFields.value.product_id = data.products[0].id;
                    if (!reviewFields.value.unit) {
                        reviewFields.value.unit = data.products[0].purchase_unit;
                    }
                }

                // Auto-match employee
                if (data.employees && data.employees.length > 0) {
                    reviewFields.value.employee_id = data.employees[0].id;
                    if (!reviewFields.value.item_name) {
                        reviewFields.value.item_name = data.employees[0].name;
                    }
                }

                // Auto-match supplier
                if (data.suppliers && data.suppliers.length > 0) {
                    reviewFields.value.supplier_id = data.suppliers[0].id;
                }
            }
        } catch (e) {
            console.error('Voice DB lookup failed:', e);
        } finally {
            isSearchingDb.value = false;
        }
    }
};

const handleManualSubmit = async () => {
    if (manualTextInput.value.trim()) {
        transcript.value = manualTextInput.value.trim();
        await processSpokenText(manualTextInput.value.trim());
    }
};

const addItemToReviewList = () => {
    reviewFields.value.items.push({
        item_name: '',
        quantity: 1,
        unit: 'kg',
    });
};

const removeItemFromReviewList = (idx) => {
    reviewFields.value.items.splice(idx, 1);
};

const handleConfirm = () => {
    // Explicit confirmation: Emit populated data to host form
    emit('confirm', {
        ...reviewFields.value,
        raw_transcript: transcript.value,
    });
    closeModal();
};

const closeModal = () => {
    stopListening();
    resetTranscript();
    manualTextInput.value = '';
    emit('update:modelValue', false);
};
</script>

<template>
    <div 
        v-if="modelValue" 
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-slate-950/80 backdrop-blur-sm animate-fade-in"
    >
        <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
            <!-- Header -->
            <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/60">
                <div class="flex items-center space-x-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
                        <Sparkles class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white">{{ title }}</h3>
                        <p class="text-[11px] text-slate-400">সহজ পর্যালোচনা ও নিশ্চিতকরণ</p>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <!-- Language Toggle -->
                    <button
                        type="button"
                        @click="toggleLanguage"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold border transition flex items-center space-x-1"
                        :class="voiceLanguage === 'bn-BD' 
                            ? 'bg-emerald-950/60 border-emerald-800 text-emerald-300' 
                            : 'bg-indigo-950/60 border-indigo-800 text-indigo-300'"
                    >
                        <Globe class="w-3.5 h-3.5" />
                        <span>{{ voiceLanguage === 'bn-BD' ? 'বাংলা' : 'EN' }}</span>
                    </button>

                    <button 
                        type="button" 
                        @click="closeModal"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white bg-slate-800/60 transition"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Content Body -->
            <div class="p-5 space-y-4 overflow-y-auto">
                <!-- Listening Pulse & Mic Center -->
                <div class="flex flex-col items-center justify-center py-4 bg-slate-950/60 rounded-2xl border border-slate-800/80">
                    <button
                        type="button"
                        @click="toggleListening"
                        class="relative w-16 h-16 rounded-full flex items-center justify-center transition shadow-lg focus:outline-none"
                        :class="isListening 
                            ? 'bg-rose-500 text-white animate-pulse shadow-rose-500/30' 
                            : 'bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-amber-500/20'"
                    >
                        <Mic v-if="!isListening" class="w-8 h-8" />
                        <MicOff v-else class="w-8 h-8" />
                        <span 
                            v-if="isListening" 
                            class="absolute inset-0 rounded-full border-4 border-rose-400/60 animate-ping"
                        ></span>
                    </button>

                    <p class="text-xs font-semibold mt-3" :class="isListening ? 'text-rose-400' : 'text-slate-300'">
                        {{ isListening ? 'শুনছি... কথা বলুন' : 'কথা বলতে মাইকে চাপ দিন' }}
                    </p>
                    <p class="text-[11px] text-slate-400 mt-1 text-center px-4 max-w-sm">
                        {{ hint }}
                    </p>
                </div>

                <!-- Live Transcript Preview -->
                <div class="p-3 bg-slate-950 rounded-xl border border-slate-800/60">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block mb-1">
                        যা শোনা গেছে (Recognized Speech):
                    </span>
                    <p class="text-xs text-amber-200 min-h-[1.5rem] font-medium break-words">
                        {{ transcript || interimTranscript || 'কোন কথা সনাক্ত হয়নি...' }}
                    </p>
                </div>

                <!-- Fallback Manual Text Input -->
                <div v-if="!isSupported || isManualMode" class="space-y-1.5">
                    <label class="text-[11px] font-semibold text-slate-300 flex items-center justify-between">
                        <span>কীবোর্ডে লিখে টেস্ট করুন (Manual Input):</span>
                    </label>
                    <div class="flex gap-2">
                        <input
                            v-model="manualTextInput"
                            @keyup.enter="handleManualSubmit"
                            type="text"
                            placeholder="যেমন: আলু ১০ কেজি ৮০০ টাকা ক্যাশ"
                            class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"
                        />
                        <button
                            type="button"
                            @click="handleManualSubmit"
                            class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition"
                        >
                            পার্স করুন
                        </button>
                    </div>
                </div>

                <!-- Interactive Review & Correction Card (Tailored to context) -->
                <div class="p-4 bg-slate-950/90 rounded-2xl border border-amber-500/30 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-amber-400 flex items-center gap-1.5">
                            <Edit3 class="w-3.5 h-3.5" />
                            পর্যালোচনা ও সংশোধন (Review Extracted Data)
                        </span>
                        <span v-if="isSearchingDb" class="text-[10px] text-amber-400 animate-pulse">
                            ডাটাবেজ সার্চ হচ্ছে...
                        </span>
                    </div>

                    <!-- 1. SALES CONTEXT REVIEW -->
                    <div v-if="contextType === 'sales' || contextType === 'daily_sales'" class="grid grid-cols-2 gap-3 text-xs">
                        <div class="col-span-2">
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">মোট বিক্রি (Gross Sales ৳):</label>
                            <input
                                v-model="reviewFields.total_sales"
                                type="number"
                                step="any"
                                placeholder="৳ 0.00"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-amber-300 font-extrabold text-base focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">ক্যাশ (Cash ৳):</label>
                            <input
                                v-model="reviewFields.cash_sales"
                                type="number"
                                step="any"
                                placeholder="৳ 0.00"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-bold focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">বিকাশ (bKash ৳):</label>
                            <input
                                v-model="reviewFields.bkash_sales"
                                type="number"
                                step="any"
                                placeholder="৳ 0.00"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-bold focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">নগদ (Nagad ৳):</label>
                            <input
                                v-model="reviewFields.nagad_sales"
                                type="number"
                                step="any"
                                placeholder="৳ 0.00"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-bold focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">কার্ড/ব্যাংক (Card ৳):</label>
                            <input
                                v-model="reviewFields.card_sales"
                                type="number"
                                step="any"
                                placeholder="৳ 0.00"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-bold focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <!-- 2. ATTENDANCE CONTEXT REVIEW -->
                    <div v-else-if="contextType === 'attendance' || contextType === 'employees'" class="grid grid-cols-2 gap-3 text-xs">
                        <div class="col-span-2">
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">কর্মীর নাম (Staff Name):</label>
                            <input
                                v-model="reviewFields.item_name"
                                type="text"
                                placeholder="যেমন: রহিম, করিম"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-medium focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">শিফট (Shift):</label>
                            <select
                                v-model="reviewFields.shift"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-medium focus:border-amber-500"
                            >
                                <option value="morning">সকাল (Morning)</option>
                                <option value="evening">বিকাল/রাত (Evening)</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">উপস্থিতি স্ট্যাটাস (Status):</label>
                            <select
                                v-model="reviewFields.attendance_status"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-medium focus:border-amber-500"
                            >
                                <option value="present">উপস্থিত (Present)</option>
                                <option value="half_day">হাফ ডে (Half Day)</option>
                                <option value="absent">অনুপস্থিত (Absent)</option>
                                <option value="leave">ছুটি (Leave)</option>
                            </select>
                        </div>
                    </div>

                    <!-- 3. RECONCILIATION CONTEXT REVIEW -->
                    <div v-else-if="contextType === 'reconciliation' || contextType === 'cash'" class="space-y-3 text-xs">
                        <div>
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">গুনে পাওয়া নগদ টাকা (Physical Counted Cash ৳):</label>
                            <input
                                v-model="reviewFields.counted_closing_cash"
                                type="number"
                                step="any"
                                placeholder="৳ 0.00"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-amber-300 font-extrabold text-lg focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <!-- 4. WASTAGE CONTEXT REVIEW -->
                    <div v-else-if="contextType === 'wastage'" class="grid grid-cols-2 gap-3 text-xs">
                        <div class="col-span-2">
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">নষ্ট হওয়া উপাদান (Ingredient Name):</label>
                            <input
                                v-model="reviewFields.item_name"
                                type="text"
                                placeholder="যেমন: টমেটো, মুরগি, দুধ"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-medium focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">পরিমাণ (Quantity):</label>
                            <input
                                v-model="reviewFields.quantity"
                                type="number"
                                step="any"
                                placeholder="0"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-bold focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">একক (Unit):</label>
                            <input
                                v-model="reviewFields.unit"
                                type="text"
                                placeholder="kg, pcs, L"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-medium focus:border-amber-500"
                            />
                        </div>
                        <div class="col-span-2">
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">কারণ (Reason):</label>
                            <select
                                v-model="reviewFields.reason"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-medium focus:border-amber-500"
                            >
                                <option value="spoiled">পচে গেছে (Spoiled)</option>
                                <option value="expired">মেয়াদ শেষ (Expired)</option>
                                <option value="burnt">পুড়ে গেছে (Burnt/Overcooked)</option>
                                <option value="damaged">ভেঙে/নষ্ট হয়েছে (Damaged)</option>
                                <option value="other">অন্যান্য (Other)</option>
                            </select>
                        </div>
                    </div>

                    <!-- 5. EXPENSES CONTEXT REVIEW -->
                    <div v-else-if="contextType === 'expenses'" class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">খরচের খাত (Category):</label>
                            <select
                                v-model="reviewFields.category"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-medium focus:border-amber-500"
                            >
                                <option value="electricity">বিদ্যুৎ বিল (Electricity)</option>
                                <option value="gas">গ্যাস বিল (Gas)</option>
                                <option value="rent">দোকান ভাড়া (Rent)</option>
                                <option value="cleaning">পরিচ্ছন্নতা (Cleaning)</option>
                                <option value="transport">যাতায়াত (Transport)</option>
                                <option value="repairs">মেরামত (Repairs)</option>
                                <option value="internet">ইন্টারনেট (Internet)</option>
                                <option value="misc">অন্যান্য (Misc)</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">টাকার পরিমাণ (Amount ৳):</label>
                            <input
                                v-model="reviewFields.amount"
                                type="number"
                                step="any"
                                placeholder="৳ 0.00"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-amber-300 font-extrabold text-base focus:border-amber-500"
                            />
                        </div>
                        <div class="col-span-2">
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">প্রাপক / বিবরণ (Payee / Description):</label>
                            <input
                                v-model="reviewFields.item_name"
                                type="text"
                                placeholder="যেমন: পল্লি বিদ্যুৎ, বাড়িওয়ালা"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-medium focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <!-- 6. CALCULATOR / CONSUMPTION (Items List) REVIEW -->
                    <div v-else-if="(contextType === 'calculator' || contextType === 'consumption') && reviewFields.items && reviewFields.items.length > 0" class="space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <label class="text-[10px] font-semibold text-slate-400">সনাক্তকৃত উপাদান তালিকা (Detected Ingredients):</label>
                            <button
                                type="button"
                                @click="addItemToReviewList"
                                class="text-[10px] text-amber-400 hover:text-amber-300 flex items-center gap-1 font-bold"
                            >
                                <Plus class="w-3 h-3" />
                                <span>আইটেম যোগ করুন</span>
                            </button>
                        </div>

                        <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                            <div 
                                v-for="(it, idx) in reviewFields.items" 
                                :key="idx" 
                                class="flex items-center gap-2 bg-slate-900 p-2 rounded-xl border border-slate-800"
                            >
                                <input
                                    v-model="it.item_name"
                                    type="text"
                                    placeholder="উপাদান"
                                    class="flex-1 bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-white font-medium text-xs focus:border-amber-500"
                                />
                                <input
                                    v-model="it.quantity"
                                    type="number"
                                    step="any"
                                    placeholder="পরিমাণ"
                                    class="w-20 bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-white font-bold text-xs text-right focus:border-amber-500"
                                />
                                <input
                                    v-model="it.unit"
                                    type="text"
                                    placeholder="একক"
                                    class="w-14 bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-slate-300 text-xs focus:border-amber-500"
                                />
                                <button
                                    type="button"
                                    @click="removeItemFromReviewList(idx)"
                                    class="text-rose-400 hover:text-rose-300 p-1"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        <div v-if="reviewFields.total_sales" class="mt-2 pt-2 border-t border-slate-800 flex items-center justify-between">
                            <span class="text-[11px] text-slate-400">শনাক্তকৃত মোট বিক্রি:</span>
                            <span class="text-xs font-bold text-amber-300">৳ {{ reviewFields.total_sales }}</span>
                        </div>
                    </div>

                    <!-- 7. DEFAULT / PURCHASES / GENERAL COSTS REVIEW -->
                    <div v-else class="grid grid-cols-2 gap-3 text-xs">
                        <div class="col-span-2">
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">
                                আইটেম / ব্যক্তি / খাত (Item / Payee):
                            </label>
                            <input
                                v-model="reviewFields.item_name"
                                type="text"
                                placeholder="যেমন: আলু, পেঁয়াজ বা কর্মীর নাম"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-medium focus:border-amber-500"
                            />
                        </div>

                        <div>
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">
                                পরিমাণ (Quantity):
                            </label>
                            <input
                                v-model="reviewFields.quantity"
                                type="number"
                                step="any"
                                placeholder="0"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-bold focus:border-amber-500"
                            />
                        </div>

                        <div>
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">
                                একক (Unit):
                            </label>
                            <input
                                v-model="reviewFields.unit"
                                type="text"
                                placeholder="kg, L, pcs"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-medium focus:border-amber-500"
                            />
                        </div>

                        <div class="col-span-2">
                            <label class="text-[10px] font-semibold text-slate-400 block mb-1">
                                টাকার পরিমাণ (Amount ৳):
                            </label>
                            <input
                                v-model="reviewFields.amount"
                                type="number"
                                step="any"
                                placeholder="৳ 0.00"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-amber-300 font-extrabold text-base focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 text-[11px] text-slate-400 bg-slate-900/60 p-2 rounded-lg">
                        <AlertCircle class="w-3.5 h-3.5 text-amber-400 shrink-0" />
                        <span>নিশ্চিত করার পর তথ্যটি মূল ফর্মে বসবে। স্বয়ংক্রিয়ভাবে জমা হবে না।</span>
                    </div>
                </div>
            </div>

            <!-- Modal Action Buttons (Minimum 44px touch targets) -->
            <div class="p-4 border-t border-slate-800 bg-slate-900/80 flex items-center justify-end gap-2.5">
                <button
                    type="button"
                    @click="closeModal"
                    class="px-4 py-2.5 rounded-xl border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition min-h-[44px]"
                >
                    বাতিল (Cancel)
                </button>

                <button
                    type="button"
                    @click="handleConfirm"
                    class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold transition flex items-center gap-1.5 min-h-[44px] shadow-lg shadow-amber-500/20"
                >
                    <Check class="w-4 h-4" />
                    <span>ফর্মে প্রয়োগ করুন (Apply)</span>
                </button>
            </div>
        </div>
    </div>
</template>
