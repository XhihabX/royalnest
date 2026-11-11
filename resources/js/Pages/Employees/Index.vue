<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Users, Plus, CheckCircle2, DollarSign, Calendar, Clock } from 'lucide-vue-next';

const props = defineProps({
    employees: Array,
    accounts: Array,
    payments: Array,
    selectedDate: String,
});

const showEmployeeModal = ref(false);
const showPayModal = ref(false);
const selectedEmployee = ref(null);
const currentDate = ref(props.selectedDate);

const empForm = useForm({
    name: '',
    phone: '',
    role: '',
    joining_date: new Date().toISOString().split('T')[0],
    salary_type: 'monthly',
    agreed_salary: '',
});

const payForm = useForm({
    employee_id: '',
    payment_date: new Date().toISOString().split('T')[0],
    payment_type: 'salary_payment',
    amount: '',
    payment_account_id: '',
    month_year: props.selectedDate.substring(0, 7),
    notes: '',
});

const onDateChange = () => {
    router.get('/employees', { date: currentDate.value }, { preserveState: true });
};

const openPayModal = (emp) => {
    selectedEmployee.value = emp;
    payForm.reset();
    payForm.employee_id = emp.id;
    payForm.amount = emp.agreed_salary;
    payForm.month_year = currentDate.value.substring(0, 7);
    showPayModal.value = true;
};

const markAttendance = (empId, status) => {
    router.post('/employees/attendance', {
        employee_id: empId,
        attendance_date: currentDate.value,
        status: status,
    }, { preserveScroll: true });
};

const getAttendanceStatus = (emp) => {
    if (emp.attendances && emp.attendances.length > 0) {
        return emp.attendances[0].status;
    }
    return 'none';
};

const submitEmployee = () => {
    empForm.post('/employees', {
        onSuccess: () => {
            showEmployeeModal.value = false;
        }
    });
};

const submitPay = () => {
    payForm.post('/employees/pay-salary', {
        onSuccess: () => {
            showPayModal.value = false;
        }
    });
};
</script>

<template>
    <AppLayout title="Staff Attendance & Payroll">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Staff Attendance & Payroll</h1>
                <p class="text-xs text-slate-400 mt-1">
                    Track salary cost separately from actual salary payouts (avoids duplicate P&L expense entries).
                </p>
            </div>

            <div class="flex items-center space-x-3">
                <div class="flex items-center bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-xs">
                    <span class="text-slate-400 mr-2 font-medium">Attendance Date:</span>
                    <input 
                        type="date" 
                        v-model="currentDate" 
                        @change="onDateChange"
                        class="bg-transparent text-amber-400 font-mono focus:outline-none cursor-pointer"
                    />
                </div>

                <button 
                    @click="showEmployeeModal = true"
                    class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2 rounded-xl text-xs flex items-center space-x-1.5 shadow-lg shadow-amber-500/10 transition cursor-pointer"
                >
                    <Plus class="w-4 h-4" />
                    <span>Add Employee</span>
                </button>
            </div>
        </div>

        <!-- Employee List & Attendance Control Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm mb-6">
            <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-sm font-bold text-white">Daily Attendance for {{ currentDate }}</h2>
                <span class="text-[11px] text-slate-400">Monthly salaries accrue daily across 30 days</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 bg-slate-950/60 border-b border-slate-800 font-semibold">
                            <th class="py-3 px-4">Employee</th>
                            <th class="py-3 px-4">Role</th>
                            <th class="py-3 px-4">Salary Structure</th>
                            <th class="py-3 px-4 text-center">Attendance for {{ currentDate }}</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="emp in employees" :key="emp.id" class="hover:bg-slate-800/30">
                            <td class="py-3 px-4">
                                <span class="font-bold text-white block">{{ emp.name }}</span>
                                <span class="text-[11px] text-slate-400 font-mono">{{ emp.phone || 'No phone' }}</span>
                            </td>
                            <td class="py-3 px-4 font-medium text-slate-300">{{ emp.role }}</td>
                            <td class="py-3 px-4 font-mono">
                                <span class="text-amber-300 font-bold">৳{{ emp.agreed_salary }}</span>
                                <span class="text-slate-400 text-[11px]"> / {{ emp.salary_type }}</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="inline-flex rounded-lg bg-slate-950 border border-slate-800 p-0.5 space-x-0.5">
                                    <button 
                                        @click="markAttendance(emp.id, 'present')"
                                        :class="[
                                            'px-2.5 py-1 rounded text-[11px] font-bold transition cursor-pointer',
                                            getAttendanceStatus(emp) === 'present' ? 'bg-emerald-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-white'
                                        ]"
                                    >
                                        Present
                                    </button>
                                    <button 
                                        @click="markAttendance(emp.id, 'half_day')"
                                        :class="[
                                            'px-2.5 py-1 rounded text-[11px] font-bold transition cursor-pointer',
                                            getAttendanceStatus(emp) === 'half_day' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-white'
                                        ]"
                                    >
                                        Half-Day
                                    </button>
                                    <button 
                                        @click="markAttendance(emp.id, 'absent')"
                                        :class="[
                                            'px-2.5 py-1 rounded text-[11px] font-bold transition cursor-pointer',
                                            getAttendanceStatus(emp) === 'absent' ? 'bg-rose-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-white'
                                        ]"
                                    >
                                        Absent
                                    </button>
                                    <button 
                                        @click="markAttendance(emp.id, 'leave')"
                                        :class="[
                                            'px-2.5 py-1 rounded text-[11px] font-bold transition cursor-pointer',
                                            getAttendanceStatus(emp) === 'leave' ? 'bg-indigo-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-white'
                                        ]"
                                    >
                                        Leave
                                    </button>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <button 
                                    @click="openPayModal(emp)"
                                    class="bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-3 py-1 rounded-lg font-bold text-[11px] transition cursor-pointer"
                                >
                                    Pay Salary
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Salary Payments History -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-sm font-bold text-white">Recent Salary Cash Disbursals</h2>
                <span class="text-[11px] text-slate-400">Cash reductions that settle accrued payroll liability</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 bg-slate-950/60 border-b border-slate-800 font-semibold">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Employee</th>
                            <th class="py-3 px-4">Salary Month</th>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4 text-right">Amount Paid</th>
                            <th class="py-3 px-4">Paid From Account</th>
                            <th class="py-3 px-4">Disbursed By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="pay in payments" :key="pay.id" class="hover:bg-slate-800/30">
                            <td class="py-3 px-4 font-mono font-medium text-slate-300">{{ pay.payment_date }}</td>
                            <td class="py-3 px-4 font-bold text-white">{{ pay.employee?.name }}</td>
                            <td class="py-3 px-4 font-mono text-slate-400">{{ pay.month_year }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-800 text-slate-300">
                                    {{ pay.payment_type.replace('_', ' ') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-emerald-400">
                                ৳{{ Number(pay.amount).toFixed(2) }}
                            </td>
                            <td class="py-3 px-4 text-slate-300">{{ pay.payment_account?.name }}</td>
                            <td class="py-3 px-4 text-slate-500">{{ pay.creator?.name || 'Owner' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Employee Modal -->
        <div v-if="showEmployeeModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                <h2 class="text-base font-bold text-white mb-4">Add Staff Member</h2>

                <form @submit.prevent="submitEmployee" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Full Name *</label>
                        <input 
                            type="text" 
                            v-model="empForm.name" 
                            required
                            placeholder="e.g. Rahim Uddin"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Phone</label>
                            <input 
                                type="text" 
                                v-model="empForm.phone" 
                                placeholder="017XXXXXXXX"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Role / Designation *</label>
                            <input 
                                type="text" 
                                v-model="empForm.role" 
                                required
                                placeholder="e.g. Head Chef, Waiter"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Salary Type *</label>
                            <select 
                                v-model="empForm.salary_type" 
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option value="monthly">Monthly Fixed</option>
                                <option value="daily">Daily Wage</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Agreed Salary (৳) *</label>
                            <input 
                                type="number" 
                                step="0.01" 
                                v-model="empForm.agreed_salary" 
                                required
                                placeholder="e.g. 15000"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono font-bold focus:border-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Joining Date</label>
                        <input 
                            type="date" 
                            v-model="empForm.joining_date" 
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-800">
                        <button 
                            type="button" 
                            @click="showEmployeeModal = false"
                            class="px-4 py-2 rounded-xl text-slate-400 hover:text-white transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="empForm.processing"
                            class="bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-slate-950 font-bold px-4 py-2 rounded-xl transition cursor-pointer"
                        >
                            Save Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Pay Salary Modal -->
        <div v-if="showPayModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                <h2 class="text-base font-bold text-white mb-1">Disburse Salary Payout</h2>
                <p class="text-xs text-slate-400 mb-4">
                    For {{ selectedEmployee?.name }} (Agreed: ৳{{ selectedEmployee?.agreed_salary }}/{{ selectedEmployee?.salary_type }})
                </p>

                <form @submit.prevent="submitPay" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Payment Type</label>
                            <select 
                                v-model="payForm.payment_type" 
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                            >
                                <option value="salary_payment">Monthly Salary Payout</option>
                                <option value="salary_advance">Salary Advance</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Salary Month (YYYY-MM)</label>
                            <input 
                                type="text" 
                                v-model="payForm.month_year" 
                                required
                                placeholder="2026-11"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Amount Paid (৳) *</label>
                            <input 
                                type="number" 
                                step="0.01" 
                                v-model="payForm.amount" 
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono font-bold focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Payment Date *</label>
                            <input 
                                type="date" 
                                v-model="payForm.payment_date" 
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:border-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Paid From Account *</label>
                        <select 
                            v-model="payForm.payment_account_id" 
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        >
                            <option value="" disabled>Select cash/bank account</option>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">
                                {{ a.name }} (Available: ৳{{ a.current_balance }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Notes</label>
                        <input 
                            type="text" 
                            v-model="payForm.notes" 
                            placeholder="Optional notes..."
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-800">
                        <button 
                            type="button" 
                            @click="showPayModal = false"
                            class="px-4 py-2 rounded-xl text-slate-400 hover:text-white transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="payForm.processing"
                            class="bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-slate-950 font-bold px-4 py-2 rounded-xl transition cursor-pointer"
                        >
                            Confirm Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

