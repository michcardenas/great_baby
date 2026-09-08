<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { BarChart3, FileText } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
defineProps({ reportes: { type: Array, required: true } });
</script>

<template>
    <Head title="Reportes contables"/>
    <AppLayout>
        <div class="space-y-4 max-w-5xl">
            <h1 class="text-2xl font-bold flex items-center gap-2"><BarChart3 class="h-6 w-6 text-brand-600"/>Reportes contables · 9 reportes</h1>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <Link v-for="(r, i) in reportes" :key="i" :href="r.href"
                    class="card p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <FileText class="h-8 w-8 text-brand-600 mb-2"/>
                    <div class="font-bold">{{ r.nombre }}</div>
                    <p class="text-xs text-surface-500 mt-1">{{ r.desc }}</p>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
