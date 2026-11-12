<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { UtensilsCrossed, Lock, Mail, Globe, ArrowRight, ShieldCheck } from 'lucide-vue-next';
import { useI18n } from '../../Composables/useI18n';

const { t, currentLocale, toggleLocale } = useI18n();

const form = useForm({
    email: 'admin@royalnest.com',
    password: 'password',
    remember: true,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="min-h-screen bg-slate-950 flex flex-col justify-center items-center p-4 selection:bg-amber-500 selection:text-black">
        <Head title="Sign In — Royal Nest Restaurant ERP" />

        <!-- Language toggle in top right -->
        <div class="absolute top-6 right-6">
            <button
                type="button"
                @click="toggleLocale"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-800 bg-slate-900/90 hover:bg-slate-800 text-xs font-semibold text-slate-300 transition shadow"
            >
                <Globe class="w-3.5 h-3.5 text-amber-400" />
                <span>{{ currentLocale === 'bn' ? 'বাংলা (BN)' : 'English (EN)' }}</span>
            </button>
        </div>

        <div class="w-full max-w-md">
            <!-- Branding Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 shadow-xl shadow-amber-500/20 mb-4">
                    <UtensilsCrossed class="w-8 h-8 text-slate-950 font-bold" />
                </div>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">Royal Nest</h1>
                <p class="text-xs text-amber-400 font-medium uppercase tracking-wider mt-0.5">Café & Restaurant ERP</p>
                <p class="text-xs text-slate-400 mt-2">{{ t('sign_in', 'Sign in to access restaurant finances & inventory') }}</p>
            </div>

            <!-- Login Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl backdrop-blur">
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Email Field -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('email', 'Email Address') }}</label>
                        <div class="relative">
                            <Mail class="w-4 h-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                placeholder="admin@royalnest.com"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-10 pr-4 py-2.5 text-sm text-slate-200 placeholder-slate-600 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                        <div v-if="form.errors.email" class="text-xs text-rose-400 mt-1.5">
                            {{ form.errors.email }}
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ t('password', 'Password') }}</label>
                        <div class="relative">
                            <Lock class="w-4 h-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="form.password"
                                type="password"
                                required
                                placeholder="••••••••"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-10 pr-4 py-2.5 text-sm text-slate-200 placeholder-slate-600 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                        <div v-if="form.errors.password" class="text-xs text-rose-400 mt-1.5">
                            {{ form.errors.password }}
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between text-xs">
                        <label class="flex items-center gap-2 text-slate-300 cursor-pointer select-none">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="w-4 h-4 rounded border-slate-800 bg-slate-950 text-amber-500 focus:ring-0 focus:ring-offset-0"
                            />
                            <span>{{ t('remember_me', 'Remember Me') }}</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 px-4 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-sm flex items-center justify-center gap-2 shadow-lg shadow-amber-950 transition active:scale-[0.98] disabled:opacity-50"
                    >
                        <span>{{ t('login', 'Sign In') }}</span>
                        <ArrowRight class="w-4 h-4" />
                    </button>
                </form>

                <!-- Initial credentials info badge -->
                <div class="mt-6 pt-5 border-t border-slate-800/80 flex items-center gap-2 text-[11px] text-slate-500">
                    <ShieldCheck class="w-4 h-4 text-emerald-500 shrink-0" />
                    <span>Default Owner: <strong class="text-slate-400">admin@royalnest.com</strong> / <strong class="text-slate-400">password</strong></span>
                </div>
            </div>

            <!-- Footer copyright -->
            <div class="text-center mt-6 text-xs text-slate-600">
                &copy; {{ new Date().getFullYear() }} Royal Nest Café & Restaurant. All rights reserved.
            </div>
        </div>
    </div>
</template>

