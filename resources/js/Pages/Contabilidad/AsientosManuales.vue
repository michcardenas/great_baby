<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { BookOpen, Plus, Search, Cloud, CloudOff, RefreshCw, X, Check, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useMoney } from '@/composables/useMoney';

const props = defineProps({
    filtros: { type: Object, required: true },
    asientos: { type: Object, required: true },
    kpis: { type: Object, required: true },
    cuentas: { type: Array, required: true },
});

const { money } = useMoney();
const q = ref(props.filtros.q);
const estado = ref(props.filtros.estado);
const sinSiigo = ref(!!props.filtros.sin_siigo);

let deb;
const filtrar = () => {
    clearTimeout(deb);
    deb = setTimeout(() => {
        router.get('/app/contabilidad/asientos-manuales', {
            q: q.value || null, estado: estado.value || null,
            sin_siigo: sinSiigo.value ? 1 : null,
        }, { preserveScroll: true, preserveState: true, replace: true });
    }, 300);
};

const modal = ref(false);
const procesando = ref(false);
const nuevo = reactive({
    fecha: new Date().toISOString().slice(0, 10),
    glosa: '',
    lineas: [],
});

const nuevaLinea = () => ({ cuenta_puc: '', tercero_documento: '', debe: 0, haber: 0, descripcion: '' });

const abrir = () => {
    modal.value = true;
    Object.assign(nuevo, {
        fecha: new Date().toISOString().slice(0, 10),
        glosa: '',
        lineas: [nuevaLinea(), nuevaLinea()],
    });
};
const agregarLinea = () => nuevo.lineas.push(nuevaLinea());
const quitarLinea = (i) => { if (nuevo.lineas.length > 2) nuevo.lineas.splice(i, 1); };

const totalDebe = computed(() => nuevo.lineas.reduce((s, l) => s + (parseFloat(l.debe) || 0), 0));
const totalHaber = computed(() => nuevo.lineas.reduce((s, l) => s + (parseFloat(l.haber) || 0), 0));
const cuadra = computed(() => totalDebe.value > 0 && Math.abs(totalDebe.value - totalHaber.value) < 0.01);

const guardar = () => {
    if (procesando.value || !cuadra.value) return;
    procesando.value = true;
    router.post('/app/contabilidad/asientos-manuales', nuevo, {
        preserveScroll: true,
        onSuccess: () => { modal.value = false; },
        onFinish: () => { procesando.value = false; },
    });
};

const aprobar = (a) => {
    if (! confirm(`¿Aprobar asiento #${a.id} por ${money(a.valor_total)} y enviarlo a SIIGO?`)) return;
    router.post(`/app/contabilidad/asientos-manuales/${a.id}/aprobar`, {}, { preserveScroll: true });
};

const reintentando = ref(null);
const reenviar = (a) => {
    if (! confirm(`¿Reenviar asiento #${a.id} a SIIGO?`)) return;
    reintentando.value = a.id;
    router.post(`/app/contabilidad/asientos-manuales/${a.id}/reenviar-siigo`, {}, {
        preserveScroll: true,
        onFinish: () => { reintentando.value = null; },
    });
};

const badgeEstado = (e) => ({
    borrador: 'bg-surface-500/20 text-surface-700',
    cuadrado: 'bg-blue-500/20 text-blue-700',
    aprobado: 'bg-amber-500/20 text-amber-700',
    sincronizado: 'bg-emerald-500/20 text-emerald-700',
}[e] || 'bg-surface-500/20');
</script>

<template>
    <Head title="Asientos manuales · Contabilidad + SIIGO"/>
    <AppLayout>
        <div class="space-y-4 max-w-7xl">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <BookOpen class="h-6 w-6 text-brand-600"/>
                        Asientos manuales
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">
                        Traslados entre cuentas, ajustes de fin de mes, provisiones. Partida doble validada. Se envían a SIIGO como <code>POST /v1/journals</code>.
                    </p>
                </div>
                <button @click="abrir" class="btn-primary text-sm inline-flex items-center gap-1">
                    <Plus class="h-4 w-4"/> Nuevo asiento
                </button>
            </div>

            <div v-if="$page.props.flash?.message" class="p-3 rounded-lg text-sm"
                 :class="$page.props.flash.type === 'error' ? 'bg-red-500/15 border-l-4 border-red-500 text-red-700' : 'bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700'">
                {{ $page.props.flash.message }}
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="card p-4"><div class="text-xs uppercase text-surface-500">Total</div><div class="text-2xl font-bold mt-1">{{ kpis.total }}</div></div>
                <div class="card p-4"><div class="text-xs uppercase text-surface-500">Borradores</div><div class="text-2xl font-bold mt-1 text-surface-500">{{ kpis.borradores }}</div></div>
                <div class="card p-4" :class="kpis.sin_siigo > 0 ? 'ring-2 ring-amber-500' : ''"><div class="text-xs uppercase text-surface-500">Aprobados sin SIIGO</div><div class="text-2xl font-bold mt-1" :class="kpis.sin_siigo > 0 ? 'text-amber-600' : 'text-surface-400'">{{ kpis.sin_siigo }}</div></div>
                <div class="card p-4"><div class="text-xs uppercase text-surface-500">Monto mes</div><div class="text-2xl font-bold mt-1 text-brand-600">{{ money(kpis.monto_mes) }}</div></div>
            </div>

            <div class="card p-3">
                <div class="flex gap-2 items-center flex-wrap">
                    <div class="relative flex-1 min-w-64">
                        <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-surface-400"/>
                        <input v-model="q" @input="filtrar" placeholder="Buscar por glosa…" class="input pl-9 w-full text-sm"/>
                    </div>
                    <select v-model="estado" @change="filtrar" class="input text-sm">
                        <option :value="null">Todos los estados</option>
                        <option value="borrador">Borrador</option>
                        <option value="cuadrado">Cuadrado</option>
                        <option value="aprobado">Aprobado</option>
                        <option value="sincronizado">Sincronizado</option>
                    </select>
                    <label class="flex items-center gap-1 text-xs whitespace-nowrap">
                        <input type="checkbox" v-model="sinSiigo" @change="filtrar" class="rounded"/> Solo sin SIIGO
                    </label>
                </div>
            </div>

            <div class="card overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="text-[10px] uppercase text-surface-500 border-b bg-surface-50 dark:bg-surface-900">
                        <tr>
                            <th class="text-left p-2">#</th>
                            <th class="text-left p-2">Fecha</th>
                            <th class="text-left p-2">Glosa</th>
                            <th class="text-right p-2">Valor</th>
                            <th class="text-center p-2">Líneas</th>
                            <th class="text-left p-2">Estado</th>
                            <th class="text-center p-2">SIIGO</th>
                            <th class="text-left p-2">Autor</th>
                            <th class="text-right p-2 w-24">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="a in asientos.data" :key="a.id" class="hover:bg-surface-50">
                            <td class="p-2 font-mono">#{{ a.id }}</td>
                            <td class="p-2 text-xs">{{ a.fecha }}</td>
                            <td class="p-2 text-xs max-w-xs truncate" :title="a.glosa">{{ a.glosa }}</td>
                            <td class="p-2 text-right font-mono font-bold">{{ money(a.valor_total) }}</td>
                            <td class="p-2 text-center">{{ a.lineas_count }}</td>
                            <td class="p-2"><span class="text-[10px] px-2 py-0.5 rounded uppercase font-semibold" :class="badgeEstado(a.estado)">{{ a.estado }}</span></td>
                            <td class="p-2 text-center">
                                <Cloud v-if="a.siigo_journal_id" class="h-4 w-4 inline text-emerald-600" :title="`Journal ${a.siigo_journal_id}`"/>
                                <CloudOff v-else class="h-4 w-4 inline text-surface-400"/>
                            </td>
                            <td class="p-2 text-xs">{{ a.autor }}</td>
                            <td class="p-2 text-right whitespace-nowrap">
                                <button v-if="a.estado === 'cuadrado'" @click="aprobar(a)" class="text-emerald-600 hover:text-emerald-700 p-1" title="Aprobar y enviar a SIIGO">
                                    <Check class="h-4 w-4"/>
                                </button>
                                <button v-if="a.estado === 'aprobado' && !a.siigo_journal_id" @click="reenviar(a)" :disabled="reintentando === a.id"
                                        class="text-brand-600 hover:text-brand-700 p-1"
                                        :class="reintentando === a.id ? 'opacity-50 cursor-wait' : ''"
                                        title="Reenviar a SIIGO">
                                    <RefreshCw :class="['h-4 w-4', reintentando === a.id ? 'animate-spin' : '']"/>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!asientos.data.length"><td colspan="9" class="p-6 text-center text-surface-500 text-sm">Sin asientos manuales.</td></tr>
                    </tbody>
                </table>
            </div>

            <div v-if="modal" @click.self="modal = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="card p-5 w-full max-w-4xl space-y-3 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold">Nuevo asiento manual</h3>
                        <button @click="modal = false" class="text-surface-500 hover:text-surface-700"><X class="h-5 w-5"/></button>
                    </div>
                    <form @submit.prevent="guardar" class="space-y-3">
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="text-xs font-semibold">Fecha *</label>
                                <input v-model="nuevo.fecha" type="date" required class="input w-full text-sm"/>
                            </div>
                            <div class="col-span-2">
                                <label class="text-xs font-semibold">Glosa *</label>
                                <input v-model="nuevo.glosa" required minlength="10" maxlength="500" placeholder="Ej: Traslado a caja general por cierre de semana" class="input w-full text-sm"/>
                            </div>
                        </div>

                        <div class="border rounded overflow-hidden">
                            <table class="w-full text-xs">
                                <thead class="bg-surface-50 dark:bg-surface-900 uppercase text-[10px]">
                                    <tr>
                                        <th class="text-left p-1.5 w-64">Cuenta PUC *</th>
                                        <th class="text-left p-1.5 w-32">Tercero (NIT)</th>
                                        <th class="text-right p-1.5 w-32">Débito</th>
                                        <th class="text-right p-1.5 w-32">Crédito</th>
                                        <th class="text-left p-1.5">Descripción *</th>
                                        <th class="w-8"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="(l, i) in nuevo.lineas" :key="i">
                                        <td class="p-1">
                                            <input v-model="l.cuenta_puc" list="cuentas-puc" required placeholder="1105" class="input w-full text-xs font-mono"/>
                                        </td>
                                        <td class="p-1">
                                            <input v-model="l.tercero_documento" placeholder="opcional" class="input w-full text-xs font-mono"/>
                                        </td>
                                        <td class="p-1">
                                            <input v-model="l.debe" type="number" step="0.01" min="0" class="input w-full text-xs text-right font-mono"/>
                                        </td>
                                        <td class="p-1">
                                            <input v-model="l.haber" type="number" step="0.01" min="0" class="input w-full text-xs text-right font-mono"/>
                                        </td>
                                        <td class="p-1">
                                            <input v-model="l.descripcion" required maxlength="300" class="input w-full text-xs"/>
                                        </td>
                                        <td class="p-1">
                                            <button type="button" @click="quitarLinea(i)" :disabled="nuevo.lineas.length <= 2"
                                                    class="text-red-500 hover:text-red-700 disabled:opacity-30 p-1">
                                                <Trash2 class="h-3.5 w-3.5"/>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="border-t bg-surface-50 dark:bg-surface-900 font-mono font-bold">
                                    <tr>
                                        <td colspan="2" class="p-1.5 text-right text-xs uppercase">Totales:</td>
                                        <td class="p-1.5 text-right">{{ money(totalDebe) }}</td>
                                        <td class="p-1.5 text-right">{{ money(totalHaber) }}</td>
                                        <td class="p-1.5">
                                            <span v-if="cuadra" class="text-emerald-600 text-xs">✓ CUADRA</span>
                                            <span v-else class="text-red-600 text-xs">✗ NO CUADRA (dif: {{ money(Math.abs(totalDebe - totalHaber)) }})</span>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <datalist id="cuentas-puc">
                            <option v-for="c in cuentas" :key="c.codigo" :value="c.codigo">{{ c.nombre }}</option>
                        </datalist>

                        <button type="button" @click="agregarLinea" class="btn-ghost text-xs inline-flex items-center gap-1">
                            <Plus class="h-3 w-3"/> Agregar línea
                        </button>

                        <div class="text-xs text-surface-500 p-2 bg-surface-50 dark:bg-surface-900 rounded">
                            💡 Guardar deja el asiento en estado <b>cuadrado</b>. Requiere aprobación separada (botón <Check class="h-3 w-3 inline"/>) para envío a SIIGO.
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t">
                            <button type="button" @click="modal = false" class="btn-ghost text-sm">Cancelar</button>
                            <button type="submit" :disabled="procesando || !cuadra" class="btn-primary text-sm">
                                {{ procesando ? 'Guardando…' : `Guardar ${cuadra ? '('+money(totalDebe)+')' : ''}` }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
