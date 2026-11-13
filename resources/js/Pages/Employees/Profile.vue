<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    Users, 
    ArrowLeft, 
    Calendar, 
    DollarSign, 
    Send, 
    CheckCircle2, 
    AlertCircle, 
    Clock, 
    FileText, 
    IdCard, 
    Phone, 
    MapPin, 
    X,
    Wallet
} from 'lucide-vue-next';
import { useI18n } from '../../Composables/useI18n';

const props = defineProps({
    profile: Object,
    accounts: Array,
    month: String,
});

const { t } = useI18n();

const activeTab = ref('salary'); // 'salary', 'attendance', 'advances'
const selectedMonth = ref(props.month);
const showPaySalaryModal = ref(false);
const showAdvanceModal = ref(false);
const showImageModal = ref(false);
const viewImageUrl = ref('');
const viewImageTitle = ref('');

const onMonthChange = () => {
    router.get(`/employees/${props.profile.employee.id}/profile`, {
        month: selectedMonth.value,
    }, { preserveState: true });
};

const viewFullImage = (url, title) => {
    viewImageUrl.value = url;
    viewImageTitle.value = title;
    showImageModal.value = true;
};

// Form: Disburse Salary
const paySalaryForm = useForm({
    employee_id: props.profile.employee.id,
    amount: props.profile.remaining_payable > 0 ? props.profile.remaining_payable : props.profile.agreed_salary,
    advance_deduction: Math.min(props.profile.outstanding_advances, props.profile.remaining_payable),
    payment_date: new Date().toISOString().split('T')[0],
    payment_account_id: props.accounts.length > 0 ? props.accounts[0].id : '',
    payment_type: 'salary_payment',
    month_year: props.month,
    notes: '',
});

const submitPaySalary = () => {
    paySalaryForm.post('/employees/pay-salary', {
        onSuccess: () => {
            showPaySalaryModal.value = false;
            paySalaryForm.reset();
        },
    });
};

// Form: Issue Advance
const advanceForm = useForm({
    employee_id: props.profile.employee.id,
    amount: '',
    advance_date: new Date().toISOString().split('T')[0],
    payment_account_id: props.accounts.length > 0 ? props.accounts[0].id : '',
    payment_method: 'cash',
    reason: '',
});

const submitAdvance = () => {
    advanceForm.post('/employees/advance', {
        onSuccess: () => {
            showAdvanceModal.value = false;
            advanceForm.reset();
        },
    });
};
</script>

<template>
    <AppLayout title="Staff Profile">
        <Head :title="profile.employee.name + ' — Staff Profile — Royal Nest'" />

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

                    <!-- Staff Photo Thumbnail -->
                    <div
                        @click="profile.employee.photo_url ? viewFullImage(profile.employee.photo_url, profile.employee.name) : null"
                        :class="[
                            'w-14 h-14 rounded-full border border-slate-700 overflow-hidden flex items-center justify-center bg-slate-800 shrink-0 shadow-md',
                            profile.employee.photo_url ? 'cursor-pointer hover:border-amber-400' : ''
                        ]"
                    >
                        <img v-if="profile.employee.photo_url" :src="profile.employee.photo_url" :alt="profile.employee.name" class="w-full h-full object-cover" />
                        <span v-else class="text-base font-bold text-amber-400">
                            {{ profile.employee.name.split(' ').map(n => n[0]).slice(0, 2).join('') }}
                        </span>
                    </div>

                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-bold text-white tracking-tight">{{ profile.employee.name }}</h1>
                            <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-800 text-amber-400 border border-slate-700">
                                {{ profile.employee.employee_id || ('EMP-' + profile.employee.id) }}
                            </span>
                            <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-950/60 text-emerald-400 border border-emerald-800/40 font-semibold capitalize">
                                {{ profile.employee.status }}
                            </span>
                            <span v-if="profile.employee.department" class="text-xs px-2.5 py-0.5 rounded-full bg-slate-800 text-amber-300 border border-slate-700 font-medium capitalize">
                                {{ profile.employee.department }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5 flex flex-wrap items-center gap-2">
                            <span class="text-slate-200 font-semibold">{{ profile.employee.role }}</span>
                            <span>•</span>
                            <span class="capitalize">{{ profile.employee.salary_type }} Wage</span>
                            <span v-if="profile.held_salary_days > 0 || profile.held_salary_deposit > 0">
                                •
                                <span class="text-amber-400 font-medium">নিরাপত্তা জামানত: {{ profile.held_salary_days }} দিন (৳{{ profile.held_salary_deposit }})</span>
                            </span>
                            <span>•</span>
                            <span>Phone: {{ profile.employee.phone }}</span>
                            <span v-if="profile.employee.nid_photo_url">
                                • 
                                <button
                                    type="button"
                                    @click="viewFullImage(profile.employee.nid_photo_url, profile.employee.name + ' — NID Card')"
                                    class="text-amber-400 hover:underline inline-flex items-center gap-1"
                                >
                                    <IdCard class="w-3.5 h-3.5" />
                                    <span>View NID Card</span>
                                </button>
                            </span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="showPaySalaryModal = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-lg shadow-emerald-950 transition active:scale-95"
                    >
                        <DollarSign class="w-4 h-4" />
                        <span>Disburse Salary</span>
                    </button>

                    <button
                        type="button"
                        @click="showAdvanceModal = true"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-medium transition"
                    >
                        <Send class="w-4 h-4 text-amber-400" />
                        <span>Issue Advance</span>
                    </button>
                </div>
            </div>

            <!-- Month Selection & Financial KPI Cards -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-2 bg-slate-900 border border-slate-800 px-3 py-1.5 rounded-xl self-start">
                    <span class="text-xs font-medium text-slate-400">Payroll Month:</span>
                    <input
                        v-model="selectedMonth"
                        @change="onMonthChange"
                        type="month"
                        class="bg-transparent text-amber-400 font-mono text-xs focus:outline-none cursor-pointer"
                    />
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 sm:gap-4">
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3 sm:p-4">
                    <span class="text-xs font-medium text-slate-400 block">Agreed Monthly Rate</span>
                    <span class="text-lg sm:text-xl font-bold font-mono text-slate-200 mt-0.5 block">৳ {{ profile.agreed_salary.toLocaleString() }}</span>
                    <span class="text-[10px] text-slate-500 capitalize">{{ profile.employee.salary_type }} wage basis</span>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3 sm:p-4">
                    <span class="text-xs font-medium text-amber-400 block">Accrued ({{ profile.current_month }})</span>
                    <span class="text-lg sm:text-xl font-bold font-mono text-amber-400 mt-0.5 block">৳ {{ profile.accrued_salary.toLocaleString() }}</span>
                    <span class="text-[10px] text-slate-500">Based on {{ profile.attendance_summary.present }} days present</span>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3 sm:p-4">
                    <span class="text-xs font-medium text-emerald-400 block">Paid in {{ profile.current_month }}</span>
                    <span class="text-lg sm:text-xl font-bold font-mono text-emerald-400 mt-0.5 block">৳ {{ profile.paid_this_month.toLocaleString() }}</span>
                    <span class="text-[10px] text-slate-500">Net salary disbursed</span>
                </div>

                <div class="bg-slate-900/80 border border-amber-500/30 rounded-2xl p-3 sm:p-4 bg-amber-500/5">
                    <span class="text-xs font-medium text-amber-300 block">নিরাপত্তা জামানত (Held)</span>
                    <span class="text-lg sm:text-xl font-bold font-mono text-amber-300 mt-0.5 block">৳ {{ (profile.held_salary_deposit || 0).toLocaleString() }}</span>
                    <span class="text-[10px] text-slate-400">{{ profile.held_salary_days || 0 }} দিনের বেতন সংরক্ষিত</span>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3 sm:p-4 col-span-2 sm:col-span-1">
                    <span class="text-xs font-medium text-rose-400 block">Net Payable Salary</span>
                    <span class="text-lg sm:text-xl font-bold font-mono text-rose-400 mt-0.5 block">৳ {{ profile.remaining_payable.toLocaleString() }}</span>
                    <span class="text-[10px] text-slate-500">জামানত বাদে প্রদেয়</span>
                </div>
            </div>

            <!-- Profile Navigation Tabs -->
            <div class="flex items-center border-b border-slate-800 space-x-6">
                <button
                    type="button"
                    @click="activeTab = 'salary'"
                    :class="['pb-3 text-sm font-semibold transition border-b-2 flex items-center gap-2', activeTab === 'salary' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200']"
                >
                    <DollarSign class="w-4 h-4" />
                    <span>Salary & Disbursals</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'attendance'"
                    :class="['pb-3 text-sm font-semibold transition border-b-2 flex items-center gap-2', activeTab === 'attendance' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200']"
                >
                    <Calendar class="w-4 h-4" />
                    <span>Attendance Calendar ({{ profile.attendance_summary.total_recorded }})</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'advances'"
                    :class="['pb-3 text-sm font-semibold transition border-b-2 flex items-center gap-2', activeTab === 'advances' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200']"
                >
                    <Send class="w-4 h-4" />
                    <span>Advances Ledger (৳ {{ profile.outstanding_advances.toLocaleString() }})</span>
                </button>
            </div>

            <!-- Tab 1: Salary & Disbursals -->
            <div v-show="activeTab === 'salary'" class="space-y-4">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-200">Salary Disbursal Vouchers</span>
                    </div>
                    <table class="w-full text-left text-xs sm:text-sm text-slate-300">
                        <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Payment Date</th>
                                <th class="px-4 py-3">Month</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Financial Account</th>
                                <th class="px-4 py-3">Notes</th>
                                <th class="px-4 py-3 text-right">Amount Disbursed (৳)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="pay in profile.salary_payments" :key="pay.id" class="hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3 font-mono text-slate-400">{{ pay.payment_date }}</td>
                                <td class="px-4 py-3 font-mono text-amber-400 font-semibold">{{ pay.month_year }}</td>
                                <td class="px-4 py-3 capitalize text-slate-300">{{ pay.payment_type.replace('_', ' ') }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ pay.account ? pay.account.name : 'Cash' }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ pay.notes || '—' }}</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-emerald-400">
                                    ৳ {{ Number(pay.amount).toLocaleString() }}
                                </td>
                            </tr>
                            <tr v-if="!profile.salary_payments || profile.salary_payments.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500 text-xs">
                                    No salary payments disbursed yet for this employee.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Attendance Calendar -->
            <div v-show="activeTab === 'attendance'" class="space-y-4">
                <!-- Attendance Stats Banner -->
                <div class="grid grid-cols-4 gap-3 bg-slate-900 border border-slate-800 rounded-2xl p-4">
                    <div class="text-center">
                        <span class="text-xs text-slate-400 block">Present (P)</span>
                        <span class="text-lg font-bold text-emerald-400 font-mono">{{ profile.attendance_summary.present }}</span>
                    </div>
                    <div class="text-center">
                        <span class="text-xs text-slate-400 block">Half Day (H)</span>
                        <span class="text-lg font-bold text-amber-400 font-mono">{{ profile.attendance_summary.half_day }}</span>
                    </div>
                    <div class="text-center">
                        <span class="text-xs text-slate-400 block">Leave (L)</span>
                        <span class="text-lg font-bold text-blue-400 font-mono">{{ profile.attendance_summary.leave }}</span>
                    </div>
                    <div class="text-center">
                        <span class="text-xs text-slate-400 block">Absent (A)</span>
                        <span class="text-lg font-bold text-rose-400 font-mono">{{ profile.attendance_summary.absent }}</span>
                    </div>
                </div>

                <!-- Daily Attendance Table -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-left text-xs sm:text-sm text-slate-300">
                        <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Attendance Date</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="att in profile.attendances" :key="att.id" class="hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3 font-mono text-slate-400">{{ att.attendance_date }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        :class="[
                                            'px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize',
                                            att.status === 'present' ? 'bg-emerald-950/60 text-emerald-400 border border-emerald-800/40' :
                                            (att.status === 'half_day' ? 'bg-amber-950/60 text-amber-400 border border-amber-800/40' :
                                            (att.status === 'leave' ? 'bg-blue-950/60 text-blue-400 border border-blue-800/40' : 'bg-rose-950/60 text-rose-400 border border-rose-800/40'))
                                        ]"
                                    >
                                        {{ att.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-400">{{ att.notes || '—' }}</td>
                            </tr>
                            <tr v-if="!profile.attendances || profile.attendances.length === 0">
                                <td colspan="3" class="px-4 py-8 text-center text-slate-500 text-xs">
                                    No attendance records logged yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 3: Staff Advances Ledger -->
            <div v-show="activeTab === 'advances'" class="space-y-4">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-200">Staff Advances & Deductions</span>
                        <span class="text-xs font-mono text-rose-400 font-bold">Outstanding: ৳ {{ profile.outstanding_advances.toLocaleString() }}</span>
                    </div>
                    <table class="w-full text-left text-xs sm:text-sm text-slate-300">
                        <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Advance Date</th>
                                <th class="px-4 py-3">Voucher #</th>
                                <th class="px-4 py-3">Reason</th>
                                <th class="px-4 py-3">Financial Account</th>
                                <th class="px-4 py-3 text-right">Original Amount (৳)</th>
                                <th class="px-4 py-3 text-right">Remaining Balance (৳)</th>
                                <th class="px-4 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="adv in profile.advances" :key="adv.id" class="hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3 font-mono text-slate-400">{{ adv.advance_date }}</td>
                                <td class="px-4 py-3 font-mono font-bold text-amber-400">{{ adv.voucher_no }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ adv.reason || 'Staff Advance' }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ adv.account ? adv.account.name : 'Cash' }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-slate-200">৳ {{ Number(adv.amount).toLocaleString() }}</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-rose-400">৳ {{ Number(adv.remaining_balance).toLocaleString() }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['px-2 py-0.5 rounded text-[11px] font-semibold capitalize', adv.status === 'settled' ? 'bg-emerald-950/60 text-emerald-400' : 'bg-amber-950/60 text-amber-400']">
                                        {{ adv.status }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!profile.advances || profile.advances.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500 text-xs">
                                    No advances recorded for this staff member.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: Disburse Salary -->
        <div v-if="showPaySalaryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <h3 class="font-bold text-white text-lg">Disburse Salary: {{ profile.employee.name }}</h3>
                    <button type="button" @click="showPaySalaryModal = false" class="text-slate-400 hover:text-slate-200"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submitPaySalary" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Gross Payout (৳) *</label>
                            <input v-model.number="paySalaryForm.amount" type="number" step="0.01" min="0.01" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-emerald-400 font-mono font-bold focus:outline-none focus:border-emerald-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Payment Date *</label>
                            <input v-model="paySalaryForm.payment_date" type="date" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Advance Deduction (৳)</label>
                            <input v-model.number="paySalaryForm.advance_deduction" type="number" step="0.01" min="0" :max="paySalaryForm.amount" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-amber-400 font-mono focus:outline-none focus:border-amber-500" />
                            <span class="text-[10px] text-slate-500">Max advance due: ৳{{ profile.outstanding_advances }}</span>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Month (YYYY-MM)</label>
                            <input v-model="paySalaryForm.month_year" type="text" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-200 font-mono focus:outline-none focus:border-emerald-500" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Payment Account *</label>
                        <select v-model="paySalaryForm.payment_account_id" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-emerald-500">
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.name }} (৳ {{ Number(acc.current_balance).toLocaleString() }})</option>
                        </select>
                    </div>

                    <div class="p-3 bg-slate-950 border border-slate-800 rounded-xl flex items-center justify-between text-xs">
                        <span class="text-slate-400">Net Cash Disbursed Outflow:</span>
                        <span class="font-mono font-bold text-emerald-400 text-sm">৳ {{ Math.max(0, paySalaryForm.amount - (paySalaryForm.advance_deduction || 0)).toLocaleString() }}</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Notes / Memo</label>
                        <input v-model="paySalaryForm.notes" type="text" placeholder="e.g. Paid monthly salary via Cash Drawer" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-emerald-500" />
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                        <button type="button" @click="showPaySalaryModal = false" class="px-4 py-2 text-xs text-slate-400">Cancel</button>
                        <button type="submit" :disabled="paySalaryForm.processing" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow">Confirm Payout</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Issue Advance -->
        <div v-if="showAdvanceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <h3 class="font-bold text-white text-lg">Issue Advance to {{ profile.employee.name }}</h3>
                    <button type="button" @click="showAdvanceModal = false" class="text-slate-400 hover:text-slate-200"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submitAdvance" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Advance Amount (৳) *</label>
                            <input v-model.number="advanceForm.amount" type="number" step="0.01" min="0.01" required placeholder="0.00" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-amber-400 font-mono font-bold focus:outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Advance Date *</label>
                            <input v-model="advanceForm.advance_date" type="date" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Payment Account *</label>
                        <select v-model="advanceForm.payment_account_id" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500">
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.name }} (৳ {{ Number(acc.current_balance).toLocaleString() }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Reason</label>
                        <input v-model="advanceForm.reason" type="text" placeholder="e.g. Medical emergency advance" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                    </div>
                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
                        <button type="button" @click="showAdvanceModal = false" class="px-4 py-2 text-xs text-slate-400">Cancel</button>
                        <button type="submit" :disabled="advanceForm.processing" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs shadow">Confirm Advance</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Image Viewer Modal -->
        <div v-if="showImageModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md animate-fade-in">
            <div class="relative max-w-2xl max-h-[90vh] bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden p-2 shadow-2xl flex flex-col">
                <div class="px-4 py-2 flex items-center justify-between border-b border-slate-800 mb-2">
                    <span class="text-sm font-semibold text-slate-200">{{ viewImageTitle }}</span>
                    <button type="button" @click="showImageModal = false" class="text-slate-400 hover:text-slate-200"><X class="w-5 h-5" /></button>
                </div>
                <div class="flex-1 flex items-center justify-center overflow-hidden">
                    <img :src="viewImageUrl" :alt="viewImageTitle" class="max-w-full max-h-[75vh] object-contain rounded-lg" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

