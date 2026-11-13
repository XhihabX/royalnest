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
    ArrowRight
} from 'lucide-vue-next';
import { useVoiceCostParser } from '../../Composables/useVoiceCostParser';
import axios from 'axios';

const props = defineProps({
    modelValue: Boolean,
    contextType: {
        type: String,
        default: 'costs', // costs, purchases, expenses, sales, attendance, employees, suppliers, inventory
    },
    title: {
        type: String,
        default: 'ভয়েস টাইপিং ও এন্ট্রি',
    },
    hint: {
        type: String,
        default: 'উদাহরণ: "আলু ৫ কেজি ৫০০ টাকা ক্যাশ" বা " রহিম কে ৫০০ টাকা বেতন দিয়েছি"',
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
    unit: '',
    amount: null,
    shift: 'morning',
    attendance_status: 'present',
    note: '',
});

const isConfirmed = ref(false);

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
    const parsed = parseSpokenCost(rawText);
    
    reviewFields.value.amount = parsed.amount || null;
    reviewFields.value.quantity = parsed.quantity || null;
    reviewFields.value.unit = parsed.unit || '';
    reviewFields.value.note = rawText;

    // Detect shift keywords
    if (/সকাল|মর্নিং|morning/i.test(rawText)) {
        reviewFields.value.shift = 'morning';
    } else if (/বিকাল|সন্ধ্যা|ইভনিং|রাত|evening|night/i.test(rawText)) {
        reviewFields.value.shift = 'evening';
    }

    // Detect attendance status
    if (/অনুপস্থিত|এবসেন্ট|ছুটি|leave|absent/i.test(rawText)) {
        reviewFields.value.attendance_status = 'absent';
    } else if (/হাফ|অর্ধেক|half/i.test(rawText)) {
        reviewFields.value.attendance_status = 'half_day';
    } else {
        reviewFields.value.attendance_status = 'present';
    }

    // Query database for fresh matching entities
    const term = parsed.itemName || parsed.category || rawText.split(' ')[0] || '';
    if (term.length >= 2) {
        isSearchingDb.value = true;
        try {
            const resp = await axios.get('/api/voice/lookup', {
                params: {
                    type: props.contextType === 'purchases' ? 'purchase_entities' : 
                          props.contextType === 'attendance' ? 'employees' : 'cost_entities',
                    q: term,
                }
            });

            if (resp.data?.data) {
                const data = resp.data.data;
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

                <!-- Fallback Manual Text Input (if mic unsupported or user prefers typing) -->
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

                <!-- Interactive Review & Correction Card (Mandatory Safety Step) -->
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

                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <!-- Item / Person Name -->
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

                        <!-- Quantity -->
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

                        <!-- Unit -->
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

                        <!-- Amount -->
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
