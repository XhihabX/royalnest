<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import CameraCapture from '../../Components/CameraCapture.vue';
import { 
    Users, 
    Plus, 
    CalendarCheck, 
    DollarSign, 
    Calendar, 
    Search, 
    Phone, 
    Camera, 
    IdCard, 
    X, 
    Eye, 
    CheckCircle2, 
    AlertCircle, 
    Clock, 
    Send,
    Lock,
    ShieldAlert,
    ChefHat,
    ArrowUpRight
} from 'lucide-vue-next';
import { useI18n } from '../../Composables/useI18n';

const props = defineProps({
    employees: Array,
    accounts: Array,
    stats: Object,
    missingAlerts: { type: Array, default: () => [] },
    selectedDate: String,
    nextEmployeeId: String,
    filters: Object,
});

const { t } = useI18n();

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'active');
const department = ref(props.filters?.department || 'all');
const currentDate = ref(props.selectedDate);

const showEmployeeModal = ref(false);
const showCameraModal = ref(false);
const cameraTarget = ref('photo');
const showImageModal = ref(false);
const viewImageUrl = ref('');
const viewImageTitle = ref('');

const applyFilters = () => {
    router.get('/employees', {
        search: search.value || undefined,
        status: status.value !== 'all' ? status.value : undefined,
        department: department.value !== 'all' ? department.value : undefined,
        date: currentDate.value,
    }, { preserveState: true, replace: true });
};

const selectDepartment = (dept) => {
    department.value = dept;
    applyFilters();
};

const onDateChange = () => {
    applyFilters();
};

const empForm = useForm({
    employee_id: props.nextEmployeeId,
    name: '',
    phone: '',
    alternative_phone: '',
    nid_number: '',
    emergency_contact: '',
    address: '',
    role: '',
    department: 'kitchen',
    tags: '',
    joining_date: new Date().toISOString().split('T')[0],
    salary_type: 'monthly',
    agreed_salary: '',
    assigned_shift: 'morning',
    held_salary_days: 3,
    held_salary_amount: 0,
    initial_advance: 0,
    payment_account_id: props.accounts.length > 0 ? props.accounts[0].id : '',
    hourly_rate: '',
    status: 'active',
    notes: '',
    photo: null,
    nid_photo: null,
});

const openCamera = (target) => {
    cameraTarget.value = target;
    showCameraModal.value = true;
};

const onPhotoCaptured = (base64) => {
    if (cameraTarget.value === 'photo') {
        empForm.photo = base64;
    } else if (cameraTarget.value === 'nid') {
        empForm.nid_photo = base64;
    }
};

const viewFullImage = (url, title) => {
    viewImageUrl.value = url;
    viewImageTitle.value = title;
    showImageModal.value = true;
};

const markAttendance = (empId, st, shift = null) => {
    router.post('/employees/attendance', {
        employee_id: empId,
        attendance_date: currentDate.value,
        status: st,
        shift: shift,
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
            empForm.reset();
        }
    });
};
</script>

<template>
    <AppLayout title="Staff & Payroll">
        <Head :title="t('employees', 'Staff & Payroll') + ' — Royal Nest'" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                        <Users class="w-6 h-6 text-amber-500" />
                        <span>{{ t('staff_directory', 'Staff Directory & Payroll') }}</span>
                    </h1>
                    <p class="text-xs text-slate-400 mt-1">
                        Employee profiles, camera photo/NID capture, attendance tracking, held security salary, and advances.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        href="/employees/attendance/bulk"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold transition shadow active:scale-95"
                    >
                        <CalendarCheck class="w-4 h-4 text-emerald-400" />
                        <span>{{ t('bulk_attendance', 'Bulk Attendance Entry') }}</span>
                    </Link>

                    <button
                        type="button"
                        @click="showEmployeeModal = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs shadow-lg shadow-amber-950 transition active:scale-95 shrink-0 cursor-pointer"
                    >
                        <Plus class="w-4 h-4" />
                        <span>{{ t('add_employee', 'Add Staff Member') }}</span>
                    </button>
                </div>
            </div>

            <!-- Attendance Missing Reminder Banner -->
            <div 
                v-if="stats.unmarked_today > 0" 
                class="bg-gradient-to-r from-amber-950/90 via-slate-900 to-amber-950/90 border border-amber-500/50 rounded-2xl p-4 shadow-lg flex flex-col sm:flex-row sm:items-center justify-between gap-3 animate-fade-in"
            >
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0">
                        <Clock class="w-5 h-5 animate-pulse" />
                    </div>
                    <div>
                        <h3 class="text-xs sm:text-sm font-bold text-white">
                            {{ stats.unmarked_today }} জন কর্মীর আজকের হাজিরা এখনো এন্ট্রি করা হয়নি!
                        </h3>
                        <p class="text-[11px] text-amber-200/80 mt-0.5">
                            দৈনিক সঠিক লাভ-ক্ষতি ও সঠিক বেতন নির্ধারণের জন্য প্রতিদিন কর্মীদের হাজিরা প্রদান আবশ্যক।
                        </p>
                    </div>
                </div>

                <Link
                    href="/employees/attendance/bulk"
                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold transition shadow shrink-0 active:scale-95"
                >
                    <span>এখনই হাজিরা দিন</span>
                    <ArrowUpRight class="w-4 h-4" />
                </Link>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3 sm:p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Total Staff</span>
                        <span class="text-xl sm:text-2xl font-bold text-slate-100 mt-0.5 block">{{ stats.total_staff }}</span>
                    </div>
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <Users class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3 sm:p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-medium text-emerald-400 block">Active Employees</span>
                        <span class="text-xl sm:text-2xl font-bold font-mono text-emerald-400 mt-0.5 block">{{ stats.active_staff }}</span>
                    </div>
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <CheckCircle2 class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3 sm:p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-medium text-amber-400 block">Pending Attendance</span>
                        <span class="text-xl sm:text-2xl font-bold font-mono text-amber-400 mt-0.5 block">{{ stats.unmarked_today }}</span>
                    </div>
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <Clock class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3 sm:p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-medium text-rose-400 block">Staff Advances Due</span>
                        <span class="text-xl sm:text-2xl font-bold font-mono text-rose-400 mt-0.5 block">৳ {{ stats.total_advances.toLocaleString() }}</span>
                    </div>
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                        <Send class="w-5 h-5" />
                    </div>
                </div>
            </div>

            <!-- Department Category Filter Tabs (Pills) -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none">
                <button
                    type="button"
                    @click="selectDepartment('all')"
                    :class="[
                        'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition shrink-0 cursor-pointer',
                        department === 'all' 
                            ? 'bg-amber-500 text-slate-950 shadow-md font-bold' 
                            : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white'
                    ]"
                >
                    {{ t('dept_all', 'সব বিভাগ (All)') }}
                </button>
                <button
                    type="button"
                    @click="selectDepartment('kitchen')"
                    :class="[
                        'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition shrink-0 cursor-pointer',
                        department === 'kitchen' 
                            ? 'bg-amber-500 text-slate-950 shadow-md font-bold' 
                            : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white'
                    ]"
                >
                    🍳 {{ t('dept_kitchen', 'বাবুর্চি / রান্নাঘর (Kitchen)') }}
                </button>
                <button
                    type="button"
                    @click="selectDepartment('service')"
                    :class="[
                        'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition shrink-0 cursor-pointer',
                        department === 'service' 
                            ? 'bg-amber-500 text-slate-950 shadow-md font-bold' 
                            : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white'
                    ]"
                >
                    🛎️ {{ t('dept_service', 'পরিবেশন / ওয়েটার (Service)') }}
                </button>
                <button
                    type="button"
                    @click="selectDepartment('cleaning')"
                    :class="[
                        'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition shrink-0 cursor-pointer',
                        department === 'cleaning' 
                            ? 'bg-amber-500 text-slate-950 shadow-md font-bold' 
                            : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white'
                    ]"
                >
                    🧹 {{ t('dept_cleaning', 'পরিচ্ছন্নতা কর্মী (Cleaning)') }}
                </button>
                <button
                    type="button"
                    @click="selectDepartment('cashier')"
                    :class="[
                        'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition shrink-0 cursor-pointer',
                        department === 'cashier' 
                            ? 'bg-amber-500 text-slate-950 shadow-md font-bold' 
                            : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white'
                    ]"
                >
                    💵 {{ t('dept_cashier', 'ক্যাশিয়ার (Cashier)') }}
                </button>
                <button
                    type="button"
                    @click="selectDepartment('management')"
                    :class="[
                        'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition shrink-0 cursor-pointer',
                        department === 'management' 
                            ? 'bg-amber-500 text-slate-950 shadow-md font-bold' 
                            : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white'
                    ]"
                >
                    👔 {{ t('dept_management', 'ম্যানেজমেন্ট (Management)') }}
                </button>
            </div>

            <!-- Search & Date Filter Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 flex-1">
                    <div class="relative flex-1 max-w-xs">
                        <Search class="w-4 h-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input
                            v-model="search"
                            @input="applyFilters"
                            type="text"
                            :placeholder="t('search', 'Search staff name, ID, phone...')"
                            class="w-full bg-slate-900 border border-slate-800 rounded-xl pl-10 pr-3 py-2 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <select
                        v-model="status"
                        @change="applyFilters"
                        class="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-amber-500"
                    >
                        <option value="all">All Statuses</option>
                        <option value="active">Active Staff</option>
                        <option value="on_leave">On Leave</option>
                        <option value="inactive">Inactive</option>
                        <option value="archived">Archived</option>
                    </select>

                    <div class="flex items-center bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-xs">
                        <span class="text-slate-400 mr-2 font-medium">{{ t('attendance', 'Date') }}:</span>
                        <input
                            v-model="currentDate"
                            @change="onDateChange"
                            type="date"
                            class="bg-transparent text-amber-400 font-mono focus:outline-none cursor-pointer"
                        />
                    </div>
                </div>
            </div>

            <!-- Missing Attendance Shifts Alert Banner -->
            <div v-if="missingAlerts && missingAlerts.length > 0" class="bg-amber-500/10 border border-amber-500/40 rounded-2xl p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <AlertCircle class="w-5 h-5 text-amber-400 shrink-0" />
                        <h3 class="text-xs sm:text-sm font-bold text-amber-200">
                            {{ missingAlerts.length }} Unmarked Staff Shifts Today ({{ currentDate }})
                        </h3>
                    </div>
                    <Link href="/employees/attendance/bulk" class="text-xs font-bold text-amber-400 hover:underline">
                        Open Bulk Attendance →
                    </Link>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    <div
                        v-for="alert in missingAlerts"
                        :key="alert.employee_id + '-' + alert.missing_shift"
                        class="bg-slate-900 border border-slate-800 rounded-xl p-2.5 flex items-center justify-between gap-2 text-xs"
                    >
                        <div>
                            <span class="font-bold text-white block">{{ alert.name }}</span>
                            <span class="text-[11px] text-amber-400 font-medium">{{ alert.shift_label }}</span>
                            <span class="text-[10px] text-slate-500 block">{{ alert.role }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                @click="markAttendance(alert.employee_id, 'present', alert.missing_shift)"
                                class="px-2 py-1 rounded bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 font-bold text-[11px] transition cursor-pointer"
                                title="Mark Present"
                            >
                                Present
                            </button>
                            <button
                                type="button"
                                @click="markAttendance(alert.employee_id, 'half_day', alert.missing_shift)"
                                class="px-2 py-1 rounded bg-amber-500/20 text-amber-400 hover:bg-amber-500/30 font-bold text-[11px] transition cursor-pointer"
                                title="Mark Half-Day"
                            >
                                Half
                            </button>
                            <button
                                type="button"
                                @click="markAttendance(alert.employee_id, 'absent', alert.missing_shift)"
                                class="px-2 py-1 rounded bg-rose-500/20 text-rose-400 hover:bg-rose-500/30 font-bold text-[11px] transition cursor-pointer"
                                title="Mark Absent"
                            >
                                Absent
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Staff Directory Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm text-slate-300">
                        <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3.5">{{ t('staff_name', 'Employee Profile') }}</th>
                                <th class="px-4 py-3.5">{{ t('phone', 'Phone & NID') }}</th>
                                <th class="px-4 py-3.5">বিভাগ ও জামানত</th>
                                <th class="px-4 py-3.5 text-right">{{ t('agreed_salary', 'Agreed Salary') }}</th>
                                <th class="px-4 py-3.5 text-center">{{ t('attendance', 'Today Attendance') }}</th>
                                <th class="px-5 py-3.5 text-right">{{ t('actions', 'Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="emp in employees" :key="emp.id" class="hover:bg-slate-800/40 transition">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <!-- Photo Avatar -->
                                        <div
                                            @click="emp.photo_url ? viewFullImage(emp.photo_url, emp.name + ' — Staff Photo') : null"
                                            :class="[
                                                'w-11 h-11 rounded-full border border-slate-700 overflow-hidden flex items-center justify-center bg-slate-800 shrink-0 shadow-sm',
                                                emp.photo_url ? 'cursor-pointer hover:border-amber-400' : ''
                                            ]"
                                        >
                                            <img v-if="emp.photo_url" :src="emp.photo_url" :alt="emp.name" class="w-full h-full object-cover" />
                                            <span v-else class="text-xs font-bold text-amber-400">
                                                {{ emp.name.split(' ').map(n => n[0]).slice(0, 2).join('') }}
                                            </span>
                                        </div>

                                        <div>
                                            <div class="font-bold text-slate-100 flex items-center gap-1.5 text-sm">
                                                <Link :href="`/employees/${emp.id}/profile`" class="hover:text-amber-400 hover:underline">
                                                    {{ emp.name }}
                                                </Link>
                                                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-800 text-amber-400 border border-slate-700">
                                                    {{ emp.employee_id || ('EMP-' + emp.id) }}
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-slate-400 mt-0.5">
                                                Joined: {{ emp.joining_date }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div v-if="emp.phone" class="font-mono text-amber-400 text-xs flex items-center gap-1">
                                        <Phone class="w-3 h-3 text-slate-500" />
                                        <a :href="'tel:' + emp.phone" class="hover:underline">{{ emp.phone }}</a>
                                    </div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span v-if="emp.nid_number" class="text-[11px] text-slate-400 font-mono">NID: {{ emp.nid_number }}</span>
                                        <button
                                            v-if="emp.nid_photo_url"
                                            type="button"
                                            @click="viewFullImage(emp.nid_photo_url, emp.name + ' — NID Card Photo')"
                                            class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/30 hover:bg-amber-500/20 transition flex items-center gap-1"
                                            title="View NID Card"
                                        >
                                            <IdCard class="w-3 h-3" />
                                            <span>NID</span>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-800 text-slate-200 border border-slate-700 capitalize">
                                            {{ emp.department || emp.role }}
                                        </span>
                                        <span 
                                            v-if="emp.held_salary_days > 0 || emp.held_salary_amount > 0"
                                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono bg-amber-500/10 border border-amber-500/30 text-amber-300"
                                            title="Held security deposit"
                                        >
                                            <Lock class="w-2.5 h-2.5" />
                                            <span>জামানত: {{ emp.held_salary_days }} দিন</span>
                                        </span>
                                    </div>
                                    <span class="block text-[11px] text-slate-500 mt-1 capitalize">
                                        {{ emp.role }} • {{ emp.salary_type }}
                                    </span>
                                    <span :class="[
                                        'inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold mt-1',
                                        emp.assigned_shift === 'evening' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/30' :
                                        emp.assigned_shift === 'both' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' :
                                        'bg-amber-500/10 text-amber-400 border border-amber-500/30'
                                    ]">
                                        {{ emp.assigned_shift === 'evening' ? '🌙 Evening' : emp.assigned_shift === 'both' ? '🔄 Both Shifts' : '☀️ Morning' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="font-mono font-bold text-slate-100 text-sm">
                                        ৳ {{ Number(emp.agreed_salary).toLocaleString() }}
                                    </div>
                                    <span class="text-[10px] text-slate-500 block">
                                        {{ emp.salary_type === 'monthly' ? 'per month' : 'per day' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <div class="inline-flex rounded-lg border border-slate-800 p-0.5 bg-slate-950">
                                        <button 
                                            @click="markAttendance(emp.id, 'present')"
                                            :class="['px-2.5 py-1 rounded text-xs font-semibold transition', getAttendanceStatus(emp) === 'present' ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-slate-200']"
                                            title="Present"
                                        >
                                            P
                                        </button>
                                        <button 
                                            @click="markAttendance(emp.id, 'half_day')"
                                            :class="['px-2.5 py-1 rounded text-xs font-semibold transition', getAttendanceStatus(emp) === 'half_day' ? 'bg-amber-600 text-white shadow' : 'text-slate-400 hover:text-slate-200']"
                                            title="Half Day"
                                        >
                                            H
                                        </button>
                                        <button 
                                            @click="markAttendance(emp.id, 'leave')"
                                            :class="['px-2.5 py-1 rounded text-xs font-semibold transition', getAttendanceStatus(emp) === 'leave' ? 'bg-blue-600 text-white shadow' : 'text-slate-400 hover:text-slate-200']"
                                            title="Leave"
                                        >
                                            L
                                        </button>
                                        <button 
                                            @click="markAttendance(emp.id, 'absent')"
                                            :class="['px-2.5 py-1 rounded text-xs font-semibold transition', getAttendanceStatus(emp) === 'absent' ? 'bg-rose-600 text-white shadow' : 'text-slate-400 hover:text-slate-200']"
                                            title="Absent"
                                        >
                                            A
                                        </button>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <Link
                                            :href="`/employees/${emp.id}/profile`"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-medium transition"
                                        >
                                            <Eye class="w-3.5 h-3.5 text-amber-400" />
                                            <span>Profile</span>
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="employees.length === 0">
                                <td colspan="6" class="px-5 py-8 text-center text-slate-500 text-xs">
                                    No staff members registered. Click "+ Add Staff Member" to add one.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: Add New Staff Member with Camera Capture & Held Salary Settings -->
        <div v-if="showEmployeeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in overflow-y-auto">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-xl overflow-hidden shadow-2xl my-8">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <h3 class="font-bold text-white text-lg flex items-center gap-2">
                        <Users class="w-5 h-5 text-amber-500" />
                        <span>Register Staff Member</span>
                    </h3>
                    <button type="button" @click="showEmployeeModal = false" class="text-slate-400 hover:text-slate-200"><X class="w-5 h-5" /></button>
                </div>

                <form @submit.prevent="submitEmployee" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                    <!-- Photo & NID Camera Section -->
                    <div class="bg-slate-950/70 border border-slate-800 rounded-xl p-4">
                        <span class="text-xs font-semibold text-slate-300 block mb-3">Live Camera Photo & NID Capture</span>
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Staff Photo Box -->
                            <div class="flex flex-col items-center justify-center border border-dashed border-slate-700 rounded-xl p-3 bg-slate-900/50">
                                <div v-if="empForm.photo" class="relative w-24 h-24 rounded-lg overflow-hidden border border-amber-500/40 mb-2">
                                    <img :src="empForm.photo" alt="Photo Preview" class="w-full h-full object-cover" />
                                    <button type="button" @click="empForm.photo = null" class="absolute top-1 right-1 p-0.5 rounded-full bg-rose-600 text-white"><X class="w-3 h-3" /></button>
                                </div>
                                <div v-else class="w-16 h-16 rounded-full bg-slate-800 flex items-center justify-center text-slate-500 mb-2">
                                    <Users class="w-6 h-6" />
                                </div>
                                <button
                                    type="button"
                                    @click="openCamera('photo')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-medium text-xs shadow transition cursor-pointer"
                                >
                                    <Camera class="w-3.5 h-3.5" />
                                    <span>{{ empForm.photo ? 'Retake' : 'Camera Snap' }}</span>
                                </button>
                            </div>

                            <!-- NID Photo Box -->
                            <div class="flex flex-col items-center justify-center border border-dashed border-slate-700 rounded-xl p-3 bg-slate-900/50">
                                <div v-if="empForm.nid_photo" class="relative w-28 h-20 rounded-lg overflow-hidden border border-amber-500/40 mb-2">
                                    <img :src="empForm.nid_photo" alt="NID Preview" class="w-full h-full object-cover" />
                                    <button type="button" @click="empForm.nid_photo = null" class="absolute top-1 right-1 p-0.5 rounded-full bg-rose-600 text-white"><X class="w-3 h-3" /></button>
                                </div>
                                <div v-else class="w-16 h-16 rounded-lg bg-slate-800 flex items-center justify-center text-slate-500 mb-2">
                                    <IdCard class="w-6 h-6" />
                                </div>
                                <button
                                    type="button"
                                    @click="openCamera('nid')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-medium text-xs transition cursor-pointer"
                                >
                                    <Camera class="w-3.5 h-3.5 text-amber-400" />
                                    <span>{{ empForm.nid_photo ? 'Retake NID' : 'Capture NID' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Employee ID *</label>
                            <input v-model="empForm.employee_id" type="text" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs font-mono text-amber-400 focus:outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Full Name *</label>
                            <input v-model="empForm.name" type="text" required placeholder="e.g. কামাল হোসেন" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Phone Number *</label>
                            <input v-model="empForm.phone" type="text" required placeholder="017XXXXXXXX" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Alternative Phone</label>
                            <input v-model="empForm.alternative_phone" type="text" placeholder="018XXXXXXXX" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                    </div>

                    <!-- Department, Role & Shift -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">বিভাগ (Department) *</label>
                            <select v-model="empForm.department" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500">
                                <option value="kitchen">🍳 রান্নাঘর / বাবুর্চি (Kitchen)</option>
                                <option value="service">🛎️ পরিবেশন / ওয়েটার (Service)</option>
                                <option value="cleaning">🧹 পরিচ্ছন্নতা কর্মী (Cleaning)</option>
                                <option value="cashier">💵 ক্যাশিয়ার (Cashier)</option>
                                <option value="management">👔 ম্যানেজমেন্ট (Management)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">পদবী (Role / Title) *</label>
                            <input v-model="empForm.role" type="text" required placeholder="যেমন: হেড কুক, সহকারী, ওয়েটার" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">কাজের শিফট (Shift) *</label>
                            <select v-model="empForm.assigned_shift" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500">
                                <option value="morning">☀️ Morning Shift (সকালের শিফট)</option>
                                <option value="evening">🌙 Evening / Night Shift (রাতের শিফট)</option>
                                <option value="both">🔄 Both Shifts (উভয় শিফট)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Salary & Type -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">বেতনের ধরন (Salary Type) *</label>
                            <select v-model="empForm.salary_type" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500">
                                <option value="monthly">মাসিক বেতন (Monthly Fixed)</option>
                                <option value="daily">দৈনিক হাজিরা মজুরি (Daily Wage)</option>
                                <option value="hourly">ঘণ্টাভিত্তিক মজুরি (Hourly Rate)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">ধার্যকৃত বেতন (৳) *</label>
                            <input v-model.number="empForm.agreed_salary" type="number" step="0.01" required placeholder="e.g. 18000" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs font-mono font-bold text-amber-400 focus:outline-none focus:border-amber-500" />
                        </div>
                    </div>

                    <!-- NEW: Held Salary Security Deposit Settings (জামানত) -->
                    <div class="bg-gradient-to-r from-amber-950/40 to-slate-950 border border-amber-500/30 rounded-xl p-4 space-y-3">
                        <div class="flex items-center space-x-2">
                            <Lock class="w-4 h-4 text-amber-400" />
                            <h4 class="text-xs font-bold text-amber-300 uppercase tracking-wider">
                                বেতন অন-হোল্ড / নিরাপত্তা জামানত (Held Salary)
                            </h4>
                        </div>
                        <p class="text-[11px] text-slate-400">
                            কর্মী না বলে চাকরি ছেড়ে চলে যাওয়া রোধে মালিকের কাছে ৩ দিন বা ৫ দিনের বেতন জামানত রাখা যায় (বাধ্যতামূলক নয়)।
                        </p>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] text-slate-300 font-medium mb-1">অন-হোল্ড দিন (দিন)</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="30"
                                    v-model.number="empForm.held_salary_days"
                                    placeholder="৩ বা ৫ দিন"
                                    class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-amber-500"
                                />
                            </div>
                            <div>
                                <label class="block text-[11px] text-slate-300 font-medium mb-1">নির্দিষ্ট টাকা (ঐচ্ছিক)</label>
                                <input
                                    type="number"
                                    min="0"
                                    v-model.number="empForm.held_salary_amount"
                                    placeholder="0.00"
                                    class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-amber-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Initial Cash Advance -->
                    <div class="bg-slate-950 border border-slate-800 rounded-xl p-4 space-y-3">
                        <div class="flex items-center space-x-2">
                            <Send class="w-4 h-4 text-rose-400" />
                            <h4 class="text-xs font-bold text-slate-200">
                                কাজে যোগদানের সময় অগ্রিম টাকা (Initial Advance)
                            </h4>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] text-slate-300 font-medium mb-1">অগ্রিম টাকার পরিমাণ (৳)</label>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    v-model.number="empForm.initial_advance"
                                    placeholder="0.00"
                                    class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-amber-500"
                                />
                            </div>
                            <div>
                                <label class="block text-[11px] text-slate-300 font-medium mb-1">পেমেন্ট অ্যাকাউন্ট</label>
                                <select v-model="empForm.payment_account_id" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none">
                                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                        {{ acc.name }} (৳{{ acc.current_balance }})
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Joining Date *</label>
                            <input v-model="empForm.joining_date" type="date" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Emergency Contact</label>
                            <input v-model="empForm.emergency_contact" type="text" placeholder="ভাই/স্ত্রী: 019XXXXXXXX" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Address</label>
                        <textarea v-model="empForm.address" rows="2" placeholder="বর্তমান বসবাসের ঠিকানা..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                        <button type="button" @click="showEmployeeModal = false" class="px-4 py-2 rounded-xl text-slate-400 text-xs">Cancel</button>
                        <button type="submit" :disabled="empForm.processing" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs shadow-lg transition">Save Employee Profile</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Camera Component Modal -->
        <CameraCapture
            v-if="showCameraModal"
            :title="cameraTarget === 'photo' ? 'Capture Staff Photo' : 'Capture NID Card Photo'"
            @captured="onPhotoCaptured"
            @close="showCameraModal = false"
        />

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
