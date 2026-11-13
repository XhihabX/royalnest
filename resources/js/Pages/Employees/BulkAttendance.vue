<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    CalendarCheck, 
    ArrowLeft, 
    Check, 
    Save, 
    Users, 
    Calendar,
    Clock
} from 'lucide-vue-next';
import { useI18n } from '../../Composables/useI18n';

const props = defineProps({
    employees: Array,
    missingAlerts: { type: Array, default: () => [] },
    date: String,
});

const { t } = useI18n();

const attendanceDate = ref(props.date);

const onDateChange = () => {
    router.get('/employees/attendance/bulk', {
        date: attendanceDate.value,
    }, { preserveState: true });
};

// Initialize form records
const activeShiftFilter = ref('all'); // 'all', 'morning', 'evening'

const form = useForm({
    date: props.date,
    records: props.employees.map(emp => {
        const defaultShift = emp.assigned_shift === 'evening' ? 'evening' : 'morning';
        const existingAtt = emp.attendances?.find(a => a.shift === defaultShift) || emp.attendances?.[0];
        return {
            employee_id: emp.id,
            name: emp.name,
            role: emp.role,
            assigned_shift: emp.assigned_shift || 'morning',
            shift: defaultShift,
            photo_url: emp.photo_url,
            employee_id_code: emp.employee_id || ('EMP-' + emp.id),
            status: existingAtt ? existingAtt.status : 'present',
            notes: existingAtt ? (existingAtt.notes || '') : '',
        };
    }),
});

const markAll = (st) => {
    form.records.forEach(rec => {
        rec.status = st;
    });
};

const submitBulk = () => {
    form.date = attendanceDate.value;
    form.post('/employees/attendance/bulk', {
        onSuccess: () => {
            // Handled
        },
    });
};
</script>

<template>
    <AppLayout title="Bulk Attendance Entry">
        <Head :title="t('bulk_attendance', 'Bulk Attendance Entry') + ' — Royal Nest'" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-4">
                <div class="flex items-center gap-3">
                    <Link
                        href="/employees"
                        class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition"
                    >
                        <ArrowLeft class="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                            <CalendarCheck class="w-6 h-6 text-emerald-500" />
                            <span>{{ t('bulk_attendance', 'Bulk Team Attendance Entry') }}</span>
                        </h1>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Quickly mark attendance for all active restaurant staff on a single screen.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-xs">
                        <span class="text-slate-400 mr-2 font-medium">Date:</span>
                        <input
                            v-model="attendanceDate"
                            @change="onDateChange"
                            type="date"
                            class="bg-transparent text-amber-400 font-mono font-bold focus:outline-none cursor-pointer"
                        />
                    </div>

                    <button
                        type="button"
                        @click="submitBulk"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-lg shadow-emerald-950 transition active:scale-95"
                    >
                        <Save class="w-4 h-4" />
                        <span>Save All Attendance</span>
                    </button>
                </div>
            </div>

            <!-- Fast Batch Action Buttons -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-3">
                <span class="text-xs font-semibold text-slate-300">Quick Batch Select:</span>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="markAll('present')"
                        class="px-3 py-1.5 rounded-lg bg-emerald-950/60 text-emerald-400 hover:bg-emerald-900/60 border border-emerald-800/40 text-xs font-semibold transition"
                    >
                        All Present (P)
                    </button>
                    <button
                        type="button"
                        @click="markAll('half_day')"
                        class="px-3 py-1.5 rounded-lg bg-amber-950/60 text-amber-400 hover:bg-amber-900/60 border border-amber-800/40 text-xs font-semibold transition"
                    >
                        All Half Day (H)
                    </button>
                    <button
                        type="button"
                        @click="markAll('leave')"
                        class="px-3 py-1.5 rounded-lg bg-blue-950/60 text-blue-400 hover:bg-blue-900/60 border border-blue-800/40 text-xs font-semibold transition"
                    >
                        All On Leave (L)
                    </button>
                </div>
            </div>

            <!-- Attendance Entry Grid -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <table class="w-full text-left text-xs sm:text-sm text-slate-300">
                    <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3.5">Staff Member</th>
                            <th class="px-4 py-3.5">Designation</th>
                            <th class="px-4 py-3.5 text-center">Shift</th>
                            <th class="px-4 py-3.5 text-center">Attendance Status</th>
                            <th class="px-5 py-3.5">Daily Notes / Memo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="(rec, idx) in form.records" :key="rec.employee_id" class="hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center overflow-hidden shrink-0">
                                        <img v-if="rec.photo_url" :src="rec.photo_url" class="w-full h-full object-cover" />
                                        <span v-else class="text-xs font-bold text-amber-400">{{ rec.name.split(' ').map(n=>n[0]).slice(0,2).join('') }}</span>
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-100 block">{{ rec.name }}</span>
                                        <span class="text-[10px] text-amber-400 font-mono">{{ rec.employee_id_code }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-slate-400 text-xs">{{ rec.role }}</td>
                            <td class="px-4 py-3.5 text-center">
                                <select
                                    v-model="rec.shift"
                                    class="bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 text-xs text-white focus:outline-none focus:border-amber-400"
                                >
                                    <option value="morning">☀️ Morning</option>
                                    <option value="evening">🌙 Evening</option>
                                </select>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <div class="inline-flex rounded-lg border border-slate-800 p-0.5 bg-slate-950">
                                    <button
                                        type="button"
                                        @click="rec.status = 'present'"
                                        :class="['px-3 py-1 rounded text-xs font-bold transition', rec.status === 'present' ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-slate-200']"
                                    >
                                        P
                                    </button>
                                    <button
                                        type="button"
                                        @click="rec.status = 'half_day'"
                                        :class="['px-3 py-1 rounded text-xs font-bold transition', rec.status === 'half_day' ? 'bg-amber-600 text-white shadow' : 'text-slate-400 hover:text-slate-200']"
                                    >
                                        H
                                    </button>
                                    <button
                                        type="button"
                                        @click="rec.status = 'leave'"
                                        :class="['px-3 py-1 rounded text-xs font-bold transition', rec.status === 'leave' ? 'bg-blue-600 text-white shadow' : 'text-slate-400 hover:text-slate-200']"
                                    >
                                        L
                                    </button>
                                    <button
                                        type="button"
                                        @click="rec.status = 'absent'"
                                        :class="['px-3 py-1 rounded text-xs font-bold transition', rec.status === 'absent' ? 'bg-rose-600 text-white shadow' : 'text-slate-400 hover:text-slate-200']"
                                    >
                                        A
                                    </button>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <input
                                    v-model="rec.notes"
                                    type="text"
                                    placeholder="Optional note..."
                                    class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1 text-xs text-slate-200 focus:outline-none focus:border-amber-500"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bottom Save Bar -->
            <div class="flex justify-end pt-4">
                <button
                    type="button"
                    @click="submitBulk"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-xl shadow-emerald-950 transition active:scale-95"
                >
                    <Save class="w-4 h-4" />
                    <span>Save All Attendance Records</span>
                </button>
            </div>
        </div>
    </AppLayout>
</template>

