<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    UserCog, 
    UserPlus, 
    ShieldCheck, 
    ShieldAlert, 
    Eye, 
    X, 
    Check, 
    Power, 
    Trash2, 
    Key, 
    Lock,
    User as UserIcon,
    AlertCircle
} from 'lucide-vue-next';

const props = defineProps({
    users: Object,
    roles: Array,
});

const showCreateModal = ref(false);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    primary_role: 'cashier',
    password: '',
    password_confirmation: '',
});

const submitCreate = () => {
    form.post('/users', {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const handleToggleStatus = (user) => {
    if (user.is_super_admin) {
        alert('সুপার এডমিন একাউন্ট নিষ্ক্রিয় করা যাবে না।');
        return;
    }
    const action = user.is_active ? 'নিষ্ক্রিয়' : 'সক্রিয়';
    if (confirm(`আপনি কি নিশ্চিত যে '${user.name}' একাউন্টটি ${action} করতে চান?`)) {
        router.post(`/users/${user.id}/toggle-status`);
    }
};

const handleDeleteUser = (user) => {
    if (user.is_super_admin) {
        alert('সুপার এডমিন একাউন্ট ডিলিট করা সম্ভব নয়।');
        return;
    }
    if (confirm(`আপনি কি নিশ্চিত যে '${user.name}' ইউজারটি ডিলিট করতে চান?`)) {
        router.delete(`/users/${user.id}`);
    }
};
</script>

<template>
    <AppLayout title="ইউজার ম্যানেজমেন্ট ও রোল (User Management)">
        <div class="max-w-6xl mx-auto space-y-5">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold">
                        <UserCog class="w-5 h-5" />
                    </div>
                    <div>
                        <h1 class="text-lg sm:text-xl font-bold text-white">ইউজার ও একাউন্ট পারমিশন</h1>
                        <p class="text-xs text-slate-400">ম্যানেজার, ক্যাশিয়ার ও ভিজিটর একাউন্ট তৈরি ও নিয়ন্ত্রণ করুন</p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="showCreateModal = true"
                    class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition flex items-center justify-center gap-2 min-h-[44px] shadow-lg shadow-amber-500/20"
                >
                    <UserPlus class="w-4 h-4" />
                    <span>নতুন ইউজার তৈরি করুন</span>
                </button>
            </div>

            <!-- Users List Cards -->
            <div class="space-y-3">
                <div
                    v-for="user in users.data"
                    :key="user.id"
                    class="p-4 bg-slate-900 border rounded-2xl transition flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                    :class="user.is_super_admin ? 'border-amber-500/40 bg-amber-950/10' : 'border-slate-800'"
                >
                    <div class="flex items-start sm:items-center space-x-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shrink-0"
                            :class="user.is_super_admin ? 'bg-amber-500 text-slate-950 font-extrabold shadow-md shadow-amber-500/20' : 'bg-slate-800 text-slate-300'"
                        >
                            {{ user.name.charAt(0) }}
                        </div>

                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-bold text-white">{{ user.name }}</span>
                                
                                <span v-if="user.is_super_admin" class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500/20 border border-amber-500/40 text-amber-300 flex items-center gap-1">
                                    <ShieldCheck class="w-3 h-3 text-amber-400" />
                                    <span>Super Admin (রক্ষিত)</span>
                                </span>

                                <span v-else class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono"
                                    :class="user.primary_role === 'visitor' ? 'bg-purple-950/60 text-purple-300 border border-purple-800/60' : 'bg-slate-800 text-slate-300'"
                                >
                                    {{ user.primary_role }}
                                </span>

                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                    :class="user.is_active ? 'bg-emerald-950/60 text-emerald-300' : 'bg-rose-950/60 text-rose-300'"
                                >
                                    {{ user.is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়' }}
                                </span>
                            </div>

                            <p class="text-xs text-slate-400 mt-1">
                                {{ user.email }} • ফোন: {{ user.phone || 'N/A' }} • তৈরি: {{ user.created_at }}
                            </p>
                        </div>
                    </div>

                    <!-- User Action Controls -->
                    <div class="flex items-center gap-2 self-end sm:self-auto">
                        <template v-if="!user.is_super_admin">
                            <button
                                type="button"
                                @click="handleToggleStatus(user)"
                                class="px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 min-h-[40px] border"
                                :class="user.is_active 
                                    ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700' 
                                    : 'bg-emerald-950/60 hover:bg-emerald-900 text-emerald-300 border-emerald-800'"
                            >
                                <Power class="w-3.5 h-3.5" />
                                <span>{{ user.is_active ? 'নিষ্ক্রিয় করুন' : 'সক্রিয় করুন' }}</span>
                            </button>

                            <button
                                type="button"
                                @click="handleDeleteUser(user)"
                                class="px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 min-h-[40px] bg-rose-950/60 hover:bg-rose-900 text-rose-300 border border-rose-800"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                                <span>ডিলিট</span>
                            </button>
                        </template>

                        <div v-else class="text-[11px] text-amber-400/80 font-bold px-3 py-2 rounded-xl bg-amber-500/10 border border-amber-500/20">
                            স্থায়ী মালিক একাউন্ট
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create User Modal -->
        <div 
            v-if="showCreateModal" 
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-slate-950/80 backdrop-blur-sm"
        >
            <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <UserPlus class="w-4 h-4 text-amber-400" />
                        <span>নতুন ইউজার তৈরি করুন</span>
                    </h3>
                    <button 
                        type="button" 
                        @click="showCreateModal = false"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white bg-slate-800/60"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitCreate" class="p-5 space-y-3.5">
                    <div>
                        <label class="text-[11px] font-bold text-slate-300 block mb-1">পূর্ণ নাম (Full Name)*</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="যেমন: মো: করিম হোসেন"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-amber-500"
                        />
                        <p v-if="form.errors.name" class="text-rose-400 text-[10px] mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-[11px] font-bold text-slate-300 block mb-1">ইমেইল (Login Email)*</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            placeholder="manager@royalnest.com"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-amber-500"
                        />
                        <p v-if="form.errors.email" class="text-rose-400 text-[10px] mt-1">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="text-[11px] font-bold text-slate-300 block mb-1">মোবাইল নম্বর (Phone)</label>
                        <input
                            v-model="form.phone"
                            type="text"
                            placeholder="017XXXXXXXX"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-amber-500"
                        />
                    </div>

                    <div>
                        <label class="text-[11px] font-bold text-slate-300 block mb-1">ভূমিকা ও রোল (Role)*</label>
                        <select
                            v-model="form.primary_role"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-amber-500"
                        >
                            <option value="cashier">ক্যাশিয়ার (Cashier) — বিক্রয় ও দৈনিক হিসাব</option>
                            <option value="manager">ম্যানেজার (Manager) — স্টক, স্টাফ ও ক্রয় পরিচালনা</option>
                            <option value="visitor">ভিজিটর (Visitor) — শুধুমাত্র রিপোর্ট ও ড্যাশবোর্ড দেখার জন্য (Read-Only)</option>
                        </select>
                        <p v-if="form.primary_role === 'visitor'" class="text-[10px] text-purple-300 mt-1 italic">
                            * ভিজিটর একাউন্ট কোন তথ্য পরিবর্তন, সেভ বা ডিলিট করতে পারবে না।
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-bold text-slate-300 block mb-1">পাসওয়ার্ড (Password)*</label>
                            <input
                                v-model="form.password"
                                type="password"
                                required
                                minlength="8"
                                placeholder="কমপক্ষে ৮ অক্ষর"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-amber-500"
                            />
                            <p v-if="form.errors.password" class="text-rose-400 text-[10px] mt-1">{{ form.errors.password }}</p>
                        </div>

                        <div>
                            <label class="text-[11px] font-bold text-slate-300 block mb-1">পাসওয়ার্ড নিশ্চিত করুন*</label>
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                required
                                minlength="8"
                                placeholder="একই পাসওয়ার্ড দিন"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <div class="p-3 border-t border-slate-800 flex justify-end gap-2 pt-4">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition"
                        >
                            বাতিল
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-extrabold transition shadow-lg shadow-amber-500/20"
                        >
                            ইউজার তৈরি করুন
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
