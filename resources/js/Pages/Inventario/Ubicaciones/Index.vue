<script setup>
import { ref, computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Warehouse, Plus, Pencil, Trash2, Power, PowerOff, Cloud, CloudOff, Upload, FileText, Receipt, RefreshCw, Star, UserPlus, ShieldCheck } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppConfirmModal from '@/Components/AppConfirmModal.vue';
import axios from 'axios';

const props = defineProps({
    ubicaciones: { type: Array, required: true },
    categorias: { type: Array, required: true },
    resoluciones: { type: Array, default: () => [] },
    warehouses_siigo: { type: Array, default: () => [] },
    resolucion_default_id: { type: Number, default: null },
    candidatos_admin: { type: Array, default: () => [] },
});

const modal = ref(null);
const modalConfirm = ref(null);
const guardando = ref(false);
const flash = ref(null);
// UBIC-4 · listado reactivo de resoluciones (se refresca sin reload tras sync).
const resolucionesList = ref([...props.resoluciones]);
const sincronizandoResol = ref(false);
// UBIC-6 · default actual (reactivo, se actualiza sin reload).
const defaultResolId = ref(props.resolucion_default_id);
// UBIC-8 · lista reactiva de candidatos admin (crecerá al crear uno nuevo in-situ).
const candidatosAdmin = ref([...props.candidatos_admin]);
const nuevoAdmin = reactive({ abierto: false, name: '', email: '', password: '', creando: false, passGenerado: null });

const vacio = () => ({
    id: null, codigo: '', nombre: '', categoria: 'venta', activa: true,
    direccion: '', ciudad: '', responsable_user_id: null, notas: '',
    siigo_id: null,
    // UBIC-6 · pre-selección de resolución default al crear una ubicación nueva.
    siigo_resolution_id: defaultResolId.value || null,
    siigo_resolution_name: '',
    siigo_resolution_prefix: '', cta_inventario: '', cta_costo: '',
});
const form = reactive(vacio());

const abrirNueva = () => { Object.assign(form, vacio()); modal.value = 'nueva'; };
const abrirEditar = (u) => {
    Object.assign(form, vacio(), {
        id: u.id, codigo: u.codigo, nombre: u.nombre, categoria: u.categoria,
        activa: u.activa, direccion: u.direccion, ciudad: u.ciudad,
        responsable_user_id: u.responsable_user_id, notas: u.notas,
        siigo_id: u.siigo_id, siigo_resolution_id: u.siigo_resolution_id,
        siigo_resolution_name: u.siigo_resolution_name,
        siigo_resolution_prefix: u.siigo_resolution_prefix,
        cta_inventario: u.cta_inventario ?? '',
        cta_costo: u.cta_costo ?? '',
    });
    modal.value = 'editar';
};

const resolucionElegida = computed(() => resolucionesList.value.find(r => r.id === form.siigo_resolution_id));

// UBIC-6 · marca/desmarca una resolución como default para auto-asignación.
const marcarDefault = async (resol) => {
    try {
        const nuevaId = defaultResolId.value === resol.id ? null : resol.id;
        const { data } = await axios.post('/app/inventario/ubicaciones/resolucion-default', {
            siigo_resolution_id: nuevaId,
        });
        if (data.ok) {
            defaultResolId.value = nuevaId;
            mostrarFlash('success', data.mensaje);
        }
    } catch (e) {
        mostrarFlash('error', 'Error: ' + (e.response?.data?.mensaje || e.message));
    }
};

// UBIC-4 · trae las resoluciones DIAN de SIIGO sin recargar la página.
const sincronizarResoluciones = async () => {
    sincronizandoResol.value = true;
    try {
        const { data } = await axios.post('/app/inventario/ubicaciones/sync-resoluciones');
        if (data.ok) {
            resolucionesList.value = data.resoluciones;
            mostrarFlash('success', data.mensaje);
        } else {
            mostrarFlash('error', data.mensaje || 'No se pudieron traer las resoluciones.');
        }
    } catch (e) {
        mostrarFlash('error', 'Error: ' + (e.response?.data?.mensaje || e.message));
    } finally { sincronizandoResol.value = false; }
};

const guardar = async () => {
    guardando.value = true;
    try {
        // Si la resolución fue elegida de la lista, cacheamos nombre+prefix.
        if (resolucionElegida.value) {
            form.siigo_resolution_name = resolucionElegida.value.nombre;
            form.siigo_resolution_prefix = resolucionElegida.value.prefix;
        }
        const { data } = await axios.post('/app/inventario/ubicaciones', form);
        if (data.ok) {
            modal.value = null;
            mostrarFlash('success', data.mensaje);
            router.reload({ only: ['ubicaciones', 'candidatos_admin'] });
        }
    } catch (e) {
        mostrarFlash('error', e.response?.data?.mensaje || e.message);
    } finally { guardando.value = false; }
};

const toggleActiva = async (u) => {
    const { data } = await axios.post(`/app/inventario/ubicaciones/${u.id}/toggle`);
    if (data.ok) { router.reload({ only: ['ubicaciones'] }); }
};

const confirmarEliminar = (u) => {
    modalConfirm.value = {
        titulo: `¿Eliminar ubicación ${u.codigo}?`,
        mensaje: u.movimientos_count > 0
            ? `Tiene ${u.movimientos_count} movimientos históricos · se marcará como INACTIVA en lugar de borrarse (preserva el kardex).`
            : 'La ubicación se eliminará definitivamente.',
        color: 'red', textoConfirmar: u.movimientos_count > 0 ? 'Desactivar' : 'Eliminar',
        onConfirmar: async () => {
            modalConfirm.value = null;
            const { data } = await axios.delete(`/app/inventario/ubicaciones/${u.id}`);
            mostrarFlash('success', data.mensaje);
            router.reload({ only: ['ubicaciones'] });
        },
    };
};

const mostrarFlash = (type, message) => {
    flash.value = { type, message };
    setTimeout(() => { flash.value = null; }, 5000);
};

// UBIC-9 · empuja/reenvía la ubicación a SIIGO como warehouse.
const enviandoSiigo = ref({});
const enviarASiigo = async (u) => {
    // FIX-S0 · early-return si ya estamos enviando (doble-click lo disparaba
    // antes aunque el :disabled estaba puesto, por el micro-delay de reactividad).
    if (enviandoSiigo.value[u.id]) return;
    enviandoSiigo.value[u.id] = true;
    try {
        const { data } = await axios.post(`/app/inventario/ubicaciones/${u.id}/enviar-siigo`);
        if (data.ok) {
            mostrarFlash('success', data.mensaje);
            // El job corre en background: polleamos cada 2s hasta 10 intentos
            // esperando el siigo_id. Antes era un setTimeout fijo que fallaba
            // si el worker tardaba más de 2.5s.
            pollearSiigoId(u.id, 10);
        }
    } catch (e) {
        mostrarFlash('error', 'Error: ' + (e.response?.data?.mensaje || e.message));
        enviandoSiigo.value[u.id] = false;
    }
};

const pollearSiigoId = async (ubicacionId, intentosRestantes) => {
    if (intentosRestantes <= 0) {
        enviandoSiigo.value[ubicacionId] = false;
        mostrarFlash('error', 'El push a SIIGO sigue en cola · recargá la página en un momento.');
        return;
    }
    setTimeout(async () => {
        router.reload({
            only: ['ubicaciones'],
            onSuccess: (page) => {
                const u = page.props.ubicaciones?.find((x) => x.id === ubicacionId);
                if (u?.siigo_id) {
                    enviandoSiigo.value[ubicacionId] = false;
                    mostrarFlash('success', `SIIGO recibió la bodega · id ${u.siigo_id}.`);
                } else if (u?.siigo_last_error) {
                    enviandoSiigo.value[ubicacionId] = false;
                    mostrarFlash('error', 'SIIGO rechazó: '+u.siigo_last_error);
                } else {
                    pollearSiigoId(ubicacionId, intentosRestantes - 1);
                }
            },
        });
    }, 2000);
};

// FIX-S0 · Modal persistente con password generado (antes se perdía con el flash).
const credencialGenerada = ref(null);
const copiarCredencial = async () => {
    if (!credencialGenerada.value) return;
    try {
        await navigator.clipboard.writeText(
            `${credencialGenerada.value.email}  ·  ${credencialGenerada.value.password}`
        );
        mostrarFlash('success', 'Credenciales copiadas al portapapeles.');
    } catch { mostrarFlash('error', 'No pude copiar · copialas a mano.'); }
};

// UBIC-8 · crea un nuevo Admin Bodega sin salir del modal, lo deja seleccionado.
const crearAdminBodega = async () => {
    if (!nuevoAdmin.name || !nuevoAdmin.email) {
        mostrarFlash('error', 'Nombre y email son obligatorios.');
        return;
    }
    nuevoAdmin.creando = true;
    try {
        const { data } = await axios.post('/app/inventario/ubicaciones/admin-bodega', {
            name: nuevoAdmin.name,
            email: nuevoAdmin.email,
            password: nuevoAdmin.password || null,
        });
        if (data.ok) {
            candidatosAdmin.value.push(data.usuario);
            form.responsable_user_id = data.usuario.id;
            // FIX-S0 · si se generó password, mostramos un modal persistente
            // con "copiar" en vez de flash de 5s (antes se perdía).
            if (data.password_generado) {
                credencialGenerada.value = {
                    nombre: data.usuario.name,
                    email: data.usuario.email,
                    password: data.password_generado,
                };
            } else {
                mostrarFlash('success', data.mensaje);
            }
            nuevoAdmin.name = ''; nuevoAdmin.email = ''; nuevoAdmin.password = '';
            nuevoAdmin.abierto = false;
        }
    } catch (e) {
        const err = e.response?.data;
        const msg = err?.errors ? Object.values(err.errors).flat().join(' · ') : (err?.mensaje || e.message);
        mostrarFlash('error', 'Error: ' + msg);
    } finally { nuevoAdmin.creando = false; }
};
</script>

<template>
    <Head title="Ubicaciones · Inventario"/>
    <AppLayout>
        <div class="space-y-4 max-w-7xl">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <Warehouse class="h-6 w-6 text-brand-600"/>Ubicaciones
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">
                        Bodegas, puntos de venta y centros de distribución · con mapeo de resolución DIAN y cuentas PUC por sitio.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="sincronizarResoluciones" :disabled="sincronizandoResol"
                            class="text-sm inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-sky-500 text-sky-700 hover:bg-sky-50 disabled:opacity-50"
                            title="Trae de SIIGO las resoluciones DIAN de factura venta activas">
                        <RefreshCw :class="['h-4 w-4', sincronizandoResol && 'animate-spin']"/>
                        {{ sincronizandoResol ? 'Trayendo…' : 'Traer resoluciones de SIIGO' }}
                    </button>
                    <button @click="abrirNueva" class="btn-primary text-sm inline-flex items-center gap-1.5">
                        <Plus class="h-4 w-4"/> Nueva ubicación
                    </button>
                </div>
            </div>

            <div v-if="flash" :class="['card p-3 border-l-4', flash.type==='success' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-rose-500 bg-rose-50 text-rose-700']">
                {{ flash.message }}
            </div>

            <!-- UBIC-6 · Resoluciones sincronizadas + default marcada con estrella.
                 Click en la estrella alterna default. -->
            <div v-if="resolucionesList.length" class="card p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs uppercase font-bold text-brand-600 flex items-center gap-1.5">
                        <Receipt class="h-3.5 w-3.5"/>
                        Resoluciones DIAN sincronizadas · {{ resolucionesList.length }}
                    </div>
                    <div class="text-[11px] text-surface-500">
                        La resolución marcada con <Star class="h-3 w-3 inline text-amber-500 fill-amber-500 mx-0.5"/> se auto-asigna a toda ubicación nueva de categoría venta.
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-for="r in resolucionesList" :key="r.id" @click="marcarDefault(r)"
                            :class="[
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs',
                                r.id === defaultResolId ? 'border-amber-400 bg-amber-50 text-amber-800' : 'border-surface-200 hover:border-amber-300 hover:bg-amber-50/50'
                            ]"
                            :title="r.id === defaultResolId ? 'Click para quitar como default' : 'Click para marcar como default'">
                        <Star :class="['h-3.5 w-3.5', r.id === defaultResolId ? 'text-amber-500 fill-amber-500' : 'text-surface-300']"/>
                        <span v-if="r.prefix" class="font-mono bg-sky-100 text-sky-700 px-1 py-0.5 rounded text-[10px]">{{ r.prefix }}</span>
                        {{ r.nombre }}
                    </button>
                </div>
            </div>

            <!-- Tabla principal · FIX-S0 overflow-x-auto para móvil (10 cols) -->
            <div class="card overflow-x-auto">
                <table class="w-full text-sm min-w-[900px]">
                    <thead class="text-xs text-surface-500 uppercase border-b bg-surface-50">
                        <tr>
                            <th class="text-left p-2 w-24">Código</th>
                            <th class="text-left p-2">Nombre</th>
                            <th class="text-left p-2 w-32">Categoría</th>
                            <th class="text-left p-2">Ciudad</th>
                            <th class="text-left p-2 w-28" title="Resolución DIAN mapeada en SIIGO">
                                <Receipt class="h-3 w-3 inline"/> Resol.
                            </th>
                            <th class="text-left p-2 w-24">PUC inv.</th>
                            <th class="text-left p-2 w-40" title="Admin bodega (scope restringido)">
                                <ShieldCheck class="h-3 w-3 inline"/> Admin bodega
                            </th>
                            <th class="text-center p-2 w-20">Movs.</th>
                            <th class="text-center p-2 w-20">Activa</th>
                            <th class="text-right p-2 w-28">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="u in ubicaciones" :key="u.id"
                            :class="[!u.activa && 'opacity-50 bg-surface-50/50', 'hover:bg-surface-50']">
                            <td class="p-2 font-mono font-bold text-brand-600">{{ u.codigo }}</td>
                            <td class="p-2">
                                {{ u.nombre }}
                                <span v-if="u.siigo_id" class="inline-flex items-center gap-0.5 ml-1 text-[10px] bg-emerald-50 text-emerald-700 px-1 py-0.5 rounded border border-emerald-200"
                                      :title="`Sincronizado con SIIGO · warehouse #${u.siigo_id}`">
                                    <Cloud class="h-2.5 w-2.5"/> SIIGO · {{ u.siigo_id }}
                                </span>
                                <button v-else @click="enviarASiigo(u)" :disabled="enviandoSiigo[u.id] || !u.activa"
                                        class="inline-flex items-center gap-0.5 ml-1 text-[10px] bg-amber-50 text-amber-800 hover:bg-amber-100 px-1 py-0.5 rounded border border-amber-200 disabled:opacity-50"
                                        :title="u.activa ? 'Crear como warehouse en SIIGO' : 'Reactivá la ubicación primero'">
                                    <CloudOff v-if="!enviandoSiigo[u.id]" class="h-2.5 w-2.5"/>
                                    <RefreshCw v-else class="h-2.5 w-2.5 animate-spin"/>
                                    {{ enviandoSiigo[u.id] ? 'Enviando…' : 'Crear en SIIGO' }}
                                </button>
                            </td>
                            <td class="p-2 text-xs text-surface-600">{{ u.categoria_label }}</td>
                            <td class="p-2 text-xs text-surface-600">{{ u.ciudad || '—' }}</td>
                            <td class="p-2 text-xs">
                                <span v-if="u.siigo_resolution_prefix" class="font-mono bg-sky-50 text-sky-700 px-1.5 py-0.5 rounded">
                                    {{ u.siigo_resolution_prefix }}
                                </span>
                                <span v-else class="text-surface-300">—</span>
                            </td>
                            <td class="p-2 text-xs font-mono text-surface-600">{{ u.cta_inventario || '—' }}</td>
                            <td class="p-2 text-xs">
                                <span v-if="u.responsable_nombre" class="inline-flex items-center gap-1 bg-violet-50 text-violet-700 px-1.5 py-0.5 rounded">
                                    <ShieldCheck class="h-3 w-3"/> {{ u.responsable_nombre }}
                                </span>
                                <span v-else class="text-surface-300">— sin admin —</span>
                            </td>
                            <td class="p-2 text-center text-xs text-surface-500">{{ u.movimientos_count || 0 }}</td>
                            <td class="p-2 text-center">
                                <button @click="toggleActiva(u)">
                                    <Power v-if="u.activa" class="h-4 w-4 text-emerald-600 inline"/>
                                    <PowerOff v-else class="h-4 w-4 text-rose-400 inline"/>
                                </button>
                            </td>
                            <td class="p-2 text-right whitespace-nowrap">
                                <button @click="abrirEditar(u)" class="text-brand-600 hover:text-brand-700 p-1" title="Editar">
                                    <Pencil class="h-4 w-4"/>
                                </button>
                                <button @click="confirmarEliminar(u)" class="text-red-500 hover:text-red-700 p-1" title="Eliminar">
                                    <Trash2 class="h-4 w-4"/>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!ubicaciones.length">
                            <td colspan="10" class="p-8 text-center text-sm text-surface-500">
                                Aún no hay ubicaciones · creá la primera con el botón de arriba.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Modal crear/editar -->
            <div v-if="modal" class="fixed inset-0 bg-black/50 flex items-start justify-center p-4 z-50 overflow-y-auto"
                 @click.self="modal = null">
                <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full my-8">
                    <div class="p-5 border-b flex items-center gap-3">
                        <Warehouse class="h-6 w-6 text-brand-600"/>
                        <h2 class="text-lg font-bold text-surface-800">
                            {{ modal === 'nueva' ? 'Nueva ubicación' : `Editar ${form.codigo}` }}
                        </h2>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-surface-600">Código *</label>
                                <input v-model="form.codigo" class="input w-full font-mono" placeholder="PRIN"/>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs font-semibold text-surface-600">Nombre *</label>
                                <input v-model="form.nombre" class="input w-full"/>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-surface-600">Categoría *</label>
                                <select v-model="form.categoria" class="input w-full">
                                    <option v-for="c in categorias" :key="c.value" :value="c.value">{{ c.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-surface-600">Ciudad</label>
                                <input v-model="form.ciudad" class="input w-full"/>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs font-semibold text-surface-600">Dirección</label>
                                <input v-model="form.direccion" class="input w-full"/>
                            </div>
                        </div>

                        <!-- Bloque SIIGO -->
                        <div class="border-t pt-4">
                            <div class="text-xs uppercase font-bold text-brand-600 mb-2 flex items-center gap-1.5">
                                <Cloud class="h-3.5 w-3.5"/> Enlaces SIIGO
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-semibold text-surface-600">Bodega SIIGO (warehouse)</label>
                                    <select v-model="form.siigo_id" class="input w-full">
                                        <option :value="null">— sin mapear —</option>
                                        <option v-for="w in warehouses_siigo" :key="w.id" :value="w.id">
                                            [{{ w.id }}] {{ w.nombre }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-surface-600 flex items-center gap-1">
                                        Resolución DIAN para facturar
                                        <Star v-if="form.siigo_resolution_id === defaultResolId && defaultResolId"
                                              class="h-3 w-3 text-amber-500 fill-amber-500"
                                              title="Es la resolución default · se auto-asigna a nuevas ubicaciones"/>
                                    </label>
                                    <select v-model="form.siigo_resolution_id" class="input w-full">
                                        <option :value="null">— sin resolución (no factura) —</option>
                                        <option v-for="r in resolucionesList" :key="r.id" :value="r.id">
                                            <template v-if="r.id === defaultResolId">★ </template><template v-if="r.prefix">[{{ r.prefix }}] </template>{{ r.nombre }}
                                        </option>
                                    </select>
                                    <p v-if="!resolucionesList.length" class="text-[11px] text-amber-700 mt-1">
                                        No hay resoluciones sincronizadas · usá el botón "Traer resoluciones de SIIGO" arriba.
                                    </p>
                                    <p v-else-if="defaultResolId && modal !== 'editar'" class="text-[11px] text-emerald-700 mt-1">
                                        Default pre-seleccionada · se auto-asigna a toda ubicación nueva de venta sin elegir otra.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Bloque contable -->
                        <div class="border-t pt-4">
                            <div class="text-xs uppercase font-bold text-brand-600 mb-2 flex items-center gap-1.5">
                                <FileText class="h-3.5 w-3.5"/> Cuentas PUC por ubicación
                                <span class="text-[10px] font-normal text-surface-500 ml-1">(dejá vacío para heredar las globales)</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-semibold text-surface-600">Cta. inventario (default 1435)</label>
                                    <input v-model="form.cta_inventario" class="input w-full font-mono" placeholder="1435"/>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-surface-600">Cta. costo (default 6135)</label>
                                    <input v-model="form.cta_costo" class="input w-full font-mono" placeholder="6135"/>
                                </div>
                            </div>
                        </div>

                        <!-- UBIC-8 · Admin bodega · se auto-promueve a rol AdminBodega al guardar -->
                        <div class="border-t pt-4">
                            <div class="text-xs uppercase font-bold text-brand-600 mb-2 flex items-center gap-1.5">
                                <ShieldCheck class="h-3.5 w-3.5"/> Admin de esta bodega
                                <span class="text-[10px] font-normal text-surface-500 ml-1">(solo verá y operará ESTA ubicación)</span>
                            </div>
                            <div class="flex gap-2">
                                <select v-model="form.responsable_user_id" class="input flex-1">
                                    <option :value="null">— sin admin asignado —</option>
                                    <option v-for="c in candidatosAdmin" :key="c.id" :value="c.id">
                                        {{ c.name }} · {{ c.email }}<template v-if="c.roles.length"> · [{{ c.roles.join(', ') }}]</template>
                                    </option>
                                </select>
                                <button type="button" @click="nuevoAdmin.abierto = !nuevoAdmin.abierto"
                                        class="text-xs inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-violet-500 text-violet-700 hover:bg-violet-50">
                                    <UserPlus class="h-3.5 w-3.5"/> Nuevo
                                </button>
                            </div>
                            <div v-if="nuevoAdmin.abierto" class="mt-3 p-3 rounded-lg border border-violet-200 bg-violet-50/50 space-y-2">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                    <input v-model="nuevoAdmin.name" class="input" placeholder="Nombre completo"/>
                                    <input v-model="nuevoAdmin.email" type="email" class="input" placeholder="correo@dominio.com"/>
                                    <input v-model="nuevoAdmin.password" type="text" class="input" placeholder="Password (opcional, se genera)"/>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="nuevoAdmin.abierto = false" class="text-xs px-3 py-1.5 rounded-lg hover:bg-white">Cancelar</button>
                                    <button type="button" @click="crearAdminBodega" :disabled="nuevoAdmin.creando"
                                            class="text-xs px-3 py-1.5 rounded-lg bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50">
                                        {{ nuevoAdmin.creando ? 'Creando…' : 'Crear + asignar' }}
                                    </button>
                                </div>
                                <p class="text-[11px] text-violet-700">
                                    Se creará con rol <strong>AdminBodega</strong> y quedará asignado a esta ubicación al guardar.
                                </p>
                            </div>
                        </div>

                        <div class="border-t pt-4">
                            <label class="text-xs font-semibold text-surface-600">Notas</label>
                            <textarea v-model="form.notas" rows="2" class="input w-full"/>
                        </div>
                    </div>
                    <div class="p-5 border-t flex items-center justify-between gap-2">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" v-model="form.activa" class="rounded"/> Activa
                        </label>
                        <div class="flex gap-2">
                            <button @click="modal = null" class="text-sm px-4 py-2 rounded-lg hover:bg-surface-100">Cancelar</button>
                            <button @click="guardar" :disabled="guardando" class="btn-primary text-sm inline-flex items-center gap-1.5">
                                {{ guardando ? 'Guardando…' : 'Guardar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FIX-S0 · Modal persistente con credencial generada del admin bodega -->
            <div v-if="credencialGenerada" class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50">
                <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-5 border-2 border-violet-500">
                    <h3 class="text-lg font-bold text-violet-700 flex items-center gap-2 mb-3">
                        <ShieldCheck class="h-5 w-5"/> Credenciales del Admin Bodega
                    </h3>
                    <p class="text-xs text-surface-600 mb-3">
                        El usuario fue creado. Guardá o envíale estas credenciales ahora:
                        <strong>este password NO se vuelve a mostrar</strong>.
                    </p>
                    <div class="bg-surface-50 rounded-lg p-3 font-mono text-sm space-y-1 mb-4">
                        <div><span class="text-surface-400">Nombre:</span> {{ credencialGenerada.nombre }}</div>
                        <div><span class="text-surface-400">Email:</span> {{ credencialGenerada.email }}</div>
                        <div><span class="text-surface-400">Password:</span> <strong>{{ credencialGenerada.password }}</strong></div>
                    </div>
                    <div class="flex justify-between gap-2">
                        <button @click="copiarCredencial" class="text-sm px-3 py-2 rounded-lg border border-violet-500 text-violet-700 hover:bg-violet-50">
                            Copiar al portapapeles
                        </button>
                        <button @click="credencialGenerada = null" class="btn-primary text-sm">
                            Entendido · ya las guardé
                        </button>
                    </div>
                </div>
            </div>

            <AppConfirmModal :cfg="modalConfirm" @cerrar="modalConfirm = null"/>
        </div>
    </AppLayout>
</template>
