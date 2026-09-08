<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import { LogIn } from 'lucide-vue-next';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => form.post('/app/login', {
    onFinish: () => form.reset('password'),
});
</script>

<template>
    <Head title="Iniciar sesión"/>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-surface-100 via-white to-brand-50 dark:from-surface-950 dark:to-surface-900 p-4">
        <div class="w-full max-w-md">
            <!-- Brand -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-3">
                    <div class="h-14 w-14 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-black text-lg shadow-lg">
                        GB
                    </div>
                    <div class="text-left">
                        <div class="text-2xl font-black text-surface-900 dark:text-surface-100">GREAT BABY</div>
                        <div class="text-xs uppercase tracking-widest text-brand-600 font-semibold">Sistema ERP</div>
                    </div>
                </div>
            </div>

            <div class="card p-8">
                <h1 class="text-xl font-bold text-surface-900 dark:text-surface-100 mb-1">Bienvenido de vuelta</h1>
                <p class="text-sm text-surface-500 mb-6">Ingresá con tu correo y contraseña</p>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="label">Correo</label>
                        <input v-model="form.email" type="email" required autofocus autocomplete="username" class="input" placeholder="aracely@greatbaby.com"/>
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="label">Contraseña</label>
                        <input v-model="form.password" type="password" required autocomplete="current-password" class="input"/>
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-surface-700 dark:text-surface-300">
                        <input v-model="form.remember" type="checkbox" class="rounded border-surface-300 text-brand-600 focus:ring-brand-500"/>
                        Recordarme
                    </label>

                    <button type="submit" :disabled="form.processing" class="btn-primary w-full py-2.5">
                        <LogIn class="h-4 w-4"/>
                        <span v-if="form.processing">Ingresando…</span>
                        <span v-else>Ingresar</span>
                    </button>
                </form>
            </div>

            <p class="text-center text-xs text-surface-500 mt-6">
                © 2026 MyTech Solutions · Desarrollado para GREAT BABY S.A.S.
            </p>
        </div>
    </div>
</template>
