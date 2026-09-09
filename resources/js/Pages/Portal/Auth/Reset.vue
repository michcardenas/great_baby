<script setup>
import { reactive, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Lock, Mail, Eye, EyeOff } from 'lucide-vue-next';

const props = defineProps({ token: { type: String, required: true }, email: { type: String, required: true } });
const page = usePage();
const form = reactive({
    email: props.email, token: props.token,
    password: '', password_confirmation: '', processing: false,
});
const showPassword = ref(false);

const submit = () => {
    if (form.processing) return;
    form.processing = true;
    router.post(window.location.pathname + window.location.search, form, {
        onFinish: () => { form.processing = false; },
    });
};
</script>

<template>
    <Head title="Restablecer contraseña"/>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-brand-50 to-white dark:from-surface-950 dark:to-surface-900 px-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <div class="mx-auto h-14 w-14 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-bold text-lg mb-3">GB</div>
                <h1 class="text-2xl font-bold">Restablecer contraseña</h1>
                <p class="text-sm text-surface-500 mt-1">Define una nueva contraseña para tu cuenta.</p>
            </div>
            <div class="card p-6 space-y-4">
                <div v-for="(msg, k) in page.props.errors" :key="k" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/40 border-l-4 border-red-500 text-red-700 dark:text-red-300 text-sm">{{ msg }}</div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-surface-600 dark:text-surface-400 mb-1">Correo</label>
                        <div class="relative">
                            <Mail class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-500"/>
                            <input v-model="form.email" type="email" required readonly class="input w-full pl-10 opacity-70"/>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-surface-600 dark:text-surface-400 mb-1">Nueva contraseña</label>
                        <div class="relative">
                            <Lock class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-500"/>
                            <input v-model="form.password" :type="showPassword ? 'text' : 'password'" required autocomplete="new-password" minlength="8" class="input w-full pl-10 pr-10"/>
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-surface-500 hover:text-surface-800 dark:hover:text-surface-200">
                                <EyeOff v-if="showPassword" class="h-4 w-4"/><Eye v-else class="h-4 w-4"/>
                            </button>
                        </div>
                        <p class="text-[11px] text-surface-500 mt-1">Mínimo 8 caracteres.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-surface-600 dark:text-surface-400 mb-1">Repetir contraseña</label>
                        <div class="relative">
                            <Lock class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-500"/>
                            <input v-model="form.password_confirmation" :type="showPassword ? 'text' : 'password'" required autocomplete="new-password" minlength="8" class="input w-full pl-10"/>
                        </div>
                    </div>
                    <button type="submit" :disabled="form.processing || form.password !== form.password_confirmation" class="btn-primary w-full disabled:opacity-50">
                        {{ form.processing ? 'Guardando…' : 'Actualizar contraseña' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
