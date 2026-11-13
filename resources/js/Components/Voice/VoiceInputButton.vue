<script setup>
import { ref } from 'vue';
import { Mic } from 'lucide-vue-next';
import UniversalVoiceModal from './UniversalVoiceModal.vue';

const props = defineProps({
    contextType: {
        type: String,
        default: 'costs',
    },
    title: {
        type: String,
        default: 'ভয়েস টাইপিং',
    },
    hint: {
        type: String,
        default: 'যেমন: "আলু ৫ কেজি ৫০০ টাকা ক্যাশ"',
    },
    buttonClass: {
        type: String,
        default: 'px-3 py-2 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-xl text-xs font-bold transition flex items-center gap-1.5 min-h-[40px]',
    },
});

const emit = defineEmits(['apply']);

const showModal = ref(false);

const handleConfirmed = (parsedData) => {
    emit('apply', parsedData);
};
</script>

<template>
    <div>
        <button
            type="button"
            @click="showModal = true"
            :class="buttonClass"
            title="ভয়েস ইনপুট শুরু করুন"
        >
            <Mic class="w-4 h-4 text-amber-400 animate-pulse" />
            <slot>
                <span>ভয়েস ইনপুট</span>
            </slot>
        </button>

        <UniversalVoiceModal
            v-model="showModal"
            :context-type="contextType"
            :title="title"
            :hint="hint"
            @confirm="handleConfirmed"
        />
    </div>
</template>
