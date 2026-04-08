<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    Wrench, AlertTriangle, CheckCircle2,
    Plus, Pencil, Trash2, X, Activity, BarChart2,
    Calendar, User, Layers, GripVertical
} from 'lucide-vue-next';

interface Process {
    id: number;
    no_proses: number;
    process_name: string;
    tonase: number | null;
    std_stroke: number;
    current_stroke: number;
    last_mtc_date: string | null;
}
interface Mtc {
    id: number; report_no: string; dies_id: string; pic_name: string;
    report_date: string; stroke_at_maintenance: number;
    repair_action?: string; problem_description?: string; cause?: string;
    photos: string[]; status: string; completed_at: string | null;
    process?: Process | null;
}
interface Dies {
    id_sap: string; no_part: string; nama_dies: string; line: string;
    kategori: string | null; status: string; is_common: boolean;
    cav: number; forecast_per_day: number; total_stroke: number;
    processes: Process[];
}
interface Props {
    dies: Dies;
    preventives: { data: Mtc[]; links: any[]; meta: any };
    correctives: { data: Mtc[]; links: any[]; meta: any };
}

const props     = defineProps<Props>();
const page      = usePage();
const flash     = computed(() => (page.props as any).flash);
const activeTab = ref<'info' | 'pm' | 'cm'>('info');

const statusList = [
    { v: 'active',       l: 'Active',       badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' },
    { v: 'slow_moving',  l: 'Slow Moving',  badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' },
    { v: 'discontinue',  l: 'Discontinue',  badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' },
    { v: 'ohp',          l: 'OHP',          badge: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400' },
    { v: 'service_part', l: 'Service Part', badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400' },
];
const statusCfg = (s: string) => statusList.find(x => x.v === s) ?? { l: s, badge: 'bg-gray-100 text-gray-600' };

const mtcStatusCfg: Record<string, { label: string; badge: string }> = {
    pending:     { label: 'Pending',     badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' },
    in_progress: { label: 'In Progress', badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' },
    completed:   { label: 'Completed',   badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' },
};

const processPct  = (p: Process) => p.std_stroke > 0 ? Math.min(round(p.current_stroke / p.std_stroke * 100, 1), 100) : 0;
const round       = (n: number, dec: number) => Math.round(n * 10 ** dec) / 10 ** dec;
const fmtDate     = (d: string | null) => !d ? '-' : new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
const fmtNum      = (n: number) => n.toLocaleString('id-ID');
const procPctBar  = (p: Process) => { const v = processPct(p); return v >= 100 ? 'bg-red-500' : v >= 85 ? 'bg-amber-400' : 'bg-emerald-400'; };
const procPctText = (p: Process) => { const v = processPct(p); return v >= 100 ? 'text-red-600' : v >= 85 ? 'text-amber-600' : 'text-emerald-600'; };

const sortedProcesses = computed(() =>
    [...props.dies.processes].sort((a, b) => a.no_proses - b.no_proses)
);

const worstProcess = computed(() => {
    if (!props.dies.processes.length) return null;
    return props.dies.processes.reduce((a, b) => processPct(a) >= processPct(b) ? a : b);
});

const gaugeColor  = computed(() => {
    const p = worstProcess.value ? processPct(worstProcess.value) : 0;
    return p >= 100 ? '#ef4444' : p >= 85 ? '#f59e0b' : '#22c55e';
});
const circumference = 2 * Math.PI * 42;
const dashOffset    = computed(() => {
    const p = worstProcess.value ? processPct(worstProcess.value) : 0;
    return circumference - (Math.min(p, 100) / 100) * circumference;
});

const showProcessModal    = ref(false);
const editingProcess      = ref<Process | null>(null);
const showDelProcessModal = ref(false);
const selectedProcess     = ref<Process | null>(null);

const processForm = useForm({
    process_name:   '',
    no_proses:      0 as number,
    tonase:         '' as string | number,
    std_stroke:     20000 as number,
    current_stroke: 0 as number,
    last_mtc_date:  '' as string,
});

const openAddProcess = () => {
    editingProcess.value        = null;
    processForm.reset();
    processForm.std_stroke      = 20000;
    processForm.current_stroke  = 0;
    processForm.no_proses       = sortedProcesses.value.length;
    showProcessModal.value      = true;
};

const openEditProcess = (p: Process) => {
    editingProcess.value        = p;
    processForm.process_name    = p.process_name;
    processForm.no_proses       = p.no_proses;
    processForm.tonase          = p.tonase ?? '';
    processForm.std_stroke      = p.std_stroke;
    processForm.current_stroke  = p.current_stroke;
    processForm.last_mtc_date   = p.last_mtc_date ?? '';
    showProcessModal.value      = true;
};

const submitProcess = () => {
    if (editingProcess.value) {
        processForm.put(`/dies/${props.dies.id_sap}/processes/${editingProcess.value.id}`, {
            onSuccess: () => { showProcessModal.value = false; },
        });
    } else {
        processForm.post(`/dies/${props.dies.id_sap}/processes`, {
            onSuccess: () => { showProcessModal.value = false; },
        });
    }
};

const openDelProcess   = (p: Process) => { selectedProcess.value = p; showDelProcessModal.value = true; };
const submitDelProcess = () => {
    if (!selectedProcess.value) return;
    router.delete(`/dies/${props.dies.id_sap}/processes/${selectedProcess.value.id}`, {
        onSuccess: () => { showDelProcessModal.value = false; },
    });
};

const draggingId  = ref<number | null>(null);
const dragOverId  = ref<number | null>(null);
const localOrder  = ref<Process[]>([]);
const reordering  = ref(false);

const startReorder = () => {
    localOrder.value = [...sortedProcesses.value];
    reordering.value = true;
};

const cancelReorder = () => {
    reordering.value = false;
    localOrder.value = [];
    draggingId.value = null;
    dragOverId.value = null;
};

const onDragStart = (id: number) => { draggingId.value = id; };
const onDragOver  = (e: DragEvent, id: number) => { e.preventDefault(); dragOverId.value = id; };
const onDrop      = (targetId: number) => {
    if (draggingId.value === null || draggingId.value === targetId) return;
    const list    = [...localOrder.value];
    const fromIdx = list.findIndex(p => p.id === draggingId.value);
    const toIdx   = list.findIndex(p => p.id === targetId);
    const [moved] = list.splice(fromIdx, 1);
    list.splice(toIdx, 0, moved);
    localOrder.value = list;
    draggingId.value = null;
    dragOverId.value = null;
};

const onTouchStart = (id: number) => { draggingId.value = id; };
const onTouchEnd   = (targetId: number) => {
    if (draggingId.value !== null && draggingId.value !== targetId) {
        onDrop(targetId);
    }
};

const saveReorder = () => {
    const orders: Record<number, number> = {};
    localOrder.value.forEach((p, i) => { orders[i] = p.id; });
    router.put(`/dies/${props.dies.id_sap}/processes/reorder`, { orders }, {
        onSuccess: () => { reordering.value = false; localOrder.value = []; },
    });
};

const displayProcesses = computed(() => reordering.value ? localOrder.value : sortedProcesses.value);

const lightboxSrc = ref<string | null>(null);
const photoBase   = '/storage/';
</script>

<template>
    <Head :title="`Dies — ${dies.no_part}`" />
    <AppLayout :breadcrumbs="[
        { title: 'Dies', href: '/dies' },
        { title: 'Master Dies', href: '/dies' },
        { title: dies.no_part, href: `/dies/${dies.id_sap}` },
    ]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <div v-if="flash?.success" class="flex items-center gap-3 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                <p class="text-emerald-800 dark:text-emerald-200 font-medium text-xs sm:text-sm">{{ flash.success }}</p>
            </div>

            <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl shadow-xl overflow-hidden">
                <div class="px-4 pt-4 pb-3 flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="bg-white/15 backdrop-blur-sm p-2.5 rounded-xl flex-shrink-0">
                            <Wrench class="w-5 h-5 text-white" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h1 class="text-lg font-bold text-white leading-tight">{{ dies.no_part }}</h1>
                                <span v-if="dies.is_common" class="px-2 py-0.5 bg-white/20 text-white text-xs font-bold rounded-full">Common</span>
                            </div>
                            <p class="text-white/70 text-xs mt-0.5 line-clamp-2">{{ dies.nama_dies }}</p>
                            <p class="text-white/50 text-xs mt-0.5 font-mono">{{ dies.id_sap }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                        <span :class="['px-2 py-0.5 rounded-full text-xs font-bold', statusCfg(dies.status).badge]">{{ statusCfg(dies.status).l }}</span>
                        <span class="px-2 py-0.5 bg-white/15 text-white text-xs font-semibold rounded-full">{{ dies.line }}</span>
                    </div>
                </div>
                <div class="px-4 pb-4">
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-4">
                        <p class="text-white/50 text-xs mb-2 font-semibold uppercase">Proses terburuk</p>
                        <div v-if="worstProcess" class="flex items-center gap-4">
                            <div class="relative flex-shrink-0" style="width:96px;height:96px;">
                                <svg viewBox="0 0 100 100" width="96" height="96" class="-rotate-90">
                                    <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="10" />
                                    <circle cx="50" cy="50" r="42" fill="none" :stroke="gaugeColor" stroke-width="10"
                                        stroke-linecap="round" :stroke-dasharray="circumference" :stroke-dashoffset="dashOffset"
                                        style="transition: stroke-dashoffset 0.6s ease" />
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-xl font-black text-white leading-none">{{ processPct(worstProcess) }}%</span>
                                    <span class="text-[9px] text-white/60 mt-0.5">stroke</span>
                                </div>
                            </div>
                            <div class="flex-1 grid grid-cols-2 gap-2.5">
                                <div><p class="text-white/50 text-xs">Proses</p><p class="text-white font-bold text-sm">{{ worstProcess.process_name }}</p></div>
                                <div><p class="text-white/50 text-xs">Current</p><p class="text-white font-bold text-sm">{{ fmtNum(worstProcess.current_stroke) }}</p></div>
                                <div><p class="text-white/50 text-xs">STD</p><p class="text-white font-bold text-sm">{{ fmtNum(worstProcess.std_stroke) }}</p></div>
                                <div><p class="text-white/50 text-xs">Sisa</p><p class="font-bold text-sm" :style="{ color: gaugeColor }">{{ fmtNum(Math.max(0, worstProcess.std_stroke - worstProcess.current_stroke)) }}</p></div>
                            </div>
                        </div>
                        <div v-else class="text-center py-4 text-white/50 text-xs">Belum ada proses</div>
                    </div>
                </div>
            </div>

            <div class="bg-gray-100 dark:bg-gray-800 rounded-xl p-1">
                <div class="flex gap-1">
                    <button @click="activeTab = 'info'" :class="['flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-bold transition-all', activeTab === 'info' ? 'bg-white dark:bg-gray-700 text-blue-600 shadow' : 'text-gray-500 hover:text-gray-700']">
                        <Activity class="w-3.5 h-3.5" /> Info
                    </button>
                    <button @click="activeTab = 'pm'" :class="['flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-bold transition-all', activeTab === 'pm' ? 'bg-white dark:bg-gray-700 text-blue-600 shadow' : 'text-gray-500 hover:text-gray-700']">
                        <CheckCircle2 class="w-3.5 h-3.5" /> PM <span class="text-xs font-black">({{ preventives.meta?.total ?? 0 }})</span>
                    </button>
                    <button @click="activeTab = 'cm'" :class="['flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-bold transition-all', activeTab === 'cm' ? 'bg-white dark:bg-gray-700 text-orange-600 shadow' : 'text-gray-500 hover:text-gray-700']">
                        <Wrench class="w-3.5 h-3.5" /> CM <span class="text-xs font-black">({{ correctives.meta?.total ?? 0 }})</span>
                    </button>
                </div>
            </div>

            <div v-show="activeTab === 'info'" class="space-y-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-4 py-3.5 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-bold text-sm text-gray-800 dark:text-white flex items-center gap-2"><BarChart2 class="w-4 h-4 text-blue-500" /> Detail Informasi</h3>
                    </div>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div><p class="text-xs text-gray-400">ID SAP</p><p class="text-xs font-bold text-gray-900 dark:text-white font-mono mt-0.5">{{ dies.id_sap }}</p></div>
                        <div><p class="text-xs text-gray-400">Line</p><p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5">{{ dies.line }}</p></div>
                        <div><p class="text-xs text-gray-400">Kategori</p><p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5">{{ dies.kategori ?? '—' }}</p></div>
                        <div><p class="text-xs text-gray-400">CAV</p><p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5">{{ dies.cav }}</p></div>
                        <div><p class="text-xs text-gray-400">Forecast/Day</p><p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5">{{ fmtNum(dies.forecast_per_day) }}</p></div>
                        <div><p class="text-xs text-gray-400">Total Stroke</p><p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5">{{ fmtNum(dies.total_stroke) }}</p></div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3.5 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-bold text-sm text-gray-800 dark:text-white flex items-center gap-2"><Layers class="w-4 h-4 text-purple-500" /> Proses Dies</h3>
                        <div class="flex items-center gap-2">
                            <template v-if="reordering">
                                <button @click="saveReorder" class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 active:scale-95 transition-all">Simpan Urutan</button>
                                <button @click="cancelReorder" class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-bold active:scale-95 transition-all">Batal</button>
                            </template>
                            <template v-else>
                                <button v-if="sortedProcesses.length > 1" @click="startReorder" class="flex items-center gap-1.5 px-3 py-1.5 bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-400 rounded-lg text-xs font-bold hover:bg-purple-200 active:scale-95 transition-all">
                                    <GripVertical class="w-3.5 h-3.5" /> Urutan
                                </button>
                                <button @click="openAddProcess" class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-bold hover:bg-blue-700 active:scale-95 transition-all">
                                    <Plus class="w-3.5 h-3.5" /> Tambah
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        <div v-if="dies.processes.length === 0" class="py-8 text-center text-gray-400 text-xs">Belum ada data proses</div>

                        <div v-if="reordering" class="px-3 py-2 bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 text-xs font-medium flex items-center gap-2">
                            <GripVertical class="w-3.5 h-3.5" /> Drag baris untuk mengubah urutan, lalu klik Simpan Urutan
                        </div>

                        <div
                            v-for="p in displayProcesses" :key="p.id"
                            :draggable="reordering"
                            @dragstart="reordering && onDragStart(p.id)"
                            @dragover="reordering && onDragOver($event, p.id)"
                            @drop="reordering && onDrop(p.id)"
                            @touchstart.passive="reordering && onTouchStart(p.id)"
                            @touchend.passive="reordering && onTouchEnd(p.id)"
                            :class="[
                                'p-4 transition-all',
                                reordering ? 'cursor-grab active:cursor-grabbing' : '',
                                dragOverId === p.id && draggingId !== p.id ? 'bg-purple-50 dark:bg-purple-900/20 border-l-4 border-purple-400' : '',
                                draggingId === p.id ? 'opacity-40' : '',
                            ]"
                        >
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="flex items-start gap-2 min-w-0">
                                    <div v-if="reordering" class="mt-0.5 text-gray-400 flex-shrink-0">
                                        <GripVertical class="w-4 h-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-black text-gray-400 dark:text-gray-500 w-5 text-right flex-shrink-0">#{{ p.no_proses + 1 }}</span>
                                            <p class="text-sm font-bold text-gray-800 dark:text-white">{{ p.process_name }}</p>
                                        </div>
                                        <p v-if="p.tonase" class="text-xs text-gray-400 mt-0.5 ml-7">Tonase: {{ p.tonase }} ton</p>
                                        <p class="text-xs text-gray-400 mt-0.5 ml-7">Last MTC: {{ fmtDate(p.last_mtc_date) }}</p>
                                    </div>
                                </div>
                                <div v-if="!reordering" class="flex items-center gap-2 flex-shrink-0">
                                    <button @click="openEditProcess(p)" class="p-1.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-lg hover:bg-amber-200 transition-colors"><Pencil class="w-3.5 h-3.5" /></button>
                                    <button @click="openDelProcess(p)" class="p-1.5 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-200 transition-colors"><Trash2 class="w-3.5 h-3.5" /></button>
                                </div>
                            </div>
                            <div v-if="!reordering" class="space-y-1.5 ml-7">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-500">Current / STD</span>
                                    <span class="font-bold text-gray-700 dark:text-gray-300">{{ fmtNum(p.current_stroke) }} / {{ fmtNum(p.std_stroke) }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div :class="['h-full rounded-full transition-all', procPctBar(p)]" :style="{ width: `${processPct(p)}%` }"></div>
                                    </div>
                                    <span :class="['text-xs font-black w-10 text-right', procPctText(p)]">{{ processPct(p) }}%</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-400">Sisa</span>
                                    <span :class="['font-bold', procPctText(p)]">{{ fmtNum(Math.max(0, p.std_stroke - p.current_stroke)) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-show="activeTab === 'pm'" class="space-y-3">
                <p class="text-xs text-gray-500">{{ preventives.meta?.total ?? 0 }} record preventive</p>
                <div v-if="preventives.data.length === 0" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 py-16 text-center">
                    <CheckCircle2 class="w-10 h-10 mx-auto mb-2 text-gray-300" /><p class="text-sm text-gray-400">Belum ada data PM</p>
                </div>
                <div v-for="r in preventives.data" :key="r.id" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="flex items-start justify-between p-4 gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1.5">
                                <span class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400">{{ r.report_no }}</span>
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-bold', mtcStatusCfg[r.status]?.badge ?? '']">{{ mtcStatusCfg[r.status]?.label ?? r.status }}</span>
                                <span v-if="r.process" class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 rounded text-xs font-semibold">
                                    <Layers class="w-2.5 h-2.5" />{{ r.process.process_name }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                                <div class="flex items-center gap-1 text-gray-500"><User class="w-3 h-3" /> {{ r.pic_name }}</div>
                                <div class="flex items-center gap-1 text-gray-500"><Calendar class="w-3 h-3" /> {{ fmtDate(r.report_date) }}</div>
                                <div class="col-span-2 text-gray-500">Stroke saat MTC: <span class="font-bold text-gray-700 dark:text-gray-300">{{ fmtNum(r.stroke_at_maintenance) }}</span></div>
                                <div v-if="r.repair_action" class="col-span-2 text-gray-500 line-clamp-2">Tindakan: {{ r.repair_action }}</div>
                            </div>
                            <div v-if="r.photos?.length" class="flex gap-2 mt-2.5 flex-wrap">
                                <div v-for="(ph, i) in r.photos.slice(0, 4)" :key="i" @click="lightboxSrc = photoBase + ph"
                                    class="relative w-14 h-14 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700 cursor-pointer group flex-shrink-0">
                                    <img :src="photoBase + ph" class="w-full h-full object-cover group-hover:scale-110 transition-transform" />
                                    <div v-if="i === 3 && r.photos.length > 4" class="absolute inset-0 bg-black/50 flex items-center justify-center text-white text-xs font-bold">+{{ r.photos.length - 4 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="preventives.meta?.last_page > 1" class="flex justify-center gap-1">
                    <button v-for="link in preventives.links" :key="link.label" @click="link.url && router.visit(link.url, { data: { pm_page: link.label }, preserveState: true, preserveScroll: true })" :disabled="!link.url" v-html="link.label"
                        :class="['px-3 py-1.5 text-xs rounded-lg font-semibold', link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' : 'opacity-40 cursor-default']">
                    </button>
                </div>
            </div>

            <div v-show="activeTab === 'cm'" class="space-y-3">
                <p class="text-xs text-gray-500">{{ correctives.meta?.total ?? 0 }} record corrective</p>
                <div v-if="correctives.data.length === 0" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 py-16 text-center">
                    <CheckCircle2 class="w-10 h-10 mx-auto mb-2 text-gray-300" /><p class="text-sm text-gray-400">Belum ada data CM</p>
                </div>
                <div v-for="r in correctives.data" :key="r.id" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="flex items-start justify-between p-4 gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1.5">
                                <span class="text-xs font-mono font-bold text-orange-600 dark:text-orange-400">{{ r.report_no }}</span>
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-bold', mtcStatusCfg[r.status]?.badge ?? '']">{{ mtcStatusCfg[r.status]?.label ?? r.status }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                                <div class="flex items-center gap-1 text-gray-500"><User class="w-3 h-3" /> {{ r.pic_name }}</div>
                                <div class="flex items-center gap-1 text-gray-500"><Calendar class="w-3 h-3" /> {{ fmtDate(r.report_date) }}</div>
                                <div v-if="r.problem_description" class="col-span-2 text-gray-500 line-clamp-2">Problem: {{ r.problem_description }}</div>
                                <div v-if="r.cause" class="col-span-2 text-gray-500 line-clamp-1">Cause: {{ r.cause }}</div>
                                <div v-if="r.repair_action" class="col-span-2 text-gray-500 line-clamp-2">Tindakan: {{ r.repair_action }}</div>
                            </div>
                            <div v-if="r.photos?.length" class="flex gap-2 mt-2.5 flex-wrap">
                                <div v-for="(ph, i) in r.photos.slice(0, 4)" :key="i" @click="lightboxSrc = photoBase + ph"
                                    class="relative w-14 h-14 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700 cursor-pointer group flex-shrink-0">
                                    <img :src="photoBase + ph" class="w-full h-full object-cover group-hover:scale-110 transition-transform" />
                                    <div v-if="i === 3 && r.photos.length > 4" class="absolute inset-0 bg-black/50 flex items-center justify-center text-white text-xs font-bold">+{{ r.photos.length - 4 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="correctives.meta?.last_page > 1" class="flex justify-center gap-1">
                    <button v-for="link in correctives.links" :key="link.label" @click="link.url && router.visit(link.url, { data: { cm_page: link.label }, preserveState: true, preserveScroll: true })" :disabled="!link.url" v-html="link.label"
                        :class="['px-3 py-1.5 text-xs rounded-lg font-semibold', link.active ? 'bg-orange-500 text-white' : link.url ? 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' : 'opacity-40 cursor-default']">
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showProcessModal" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="font-bold text-sm text-gray-900 dark:text-white">{{ editingProcess ? 'Edit Proses' : 'Tambah Proses' }}</h3>
                    <button @click="showProcessModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                </div>
                <form @submit.prevent="submitProcess" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Nama Proses <span class="text-red-500">*</span></label>
                        <input v-model="processForm.process_name" type="text" placeholder="DRAW 1 / TRIM / RESTRIKE..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                        <p v-if="processForm.errors.process_name" class="mt-1 text-xs text-red-500">{{ processForm.errors.process_name }}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">No. Urut</label>
                            <input v-model="processForm.no_proses" type="number" min="0"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">STD Stroke <span class="text-red-500">*</span></label>
                            <input v-model="processForm.std_stroke" type="number" min="0"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Current Stroke</label>
                            <input v-model="processForm.current_stroke" type="number" min="0"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Tonase (ton)</label>
                            <input v-model="processForm.tonase" type="number" min="0" step="0.1" placeholder="opsional"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Last MTC Date</label>
                            <input v-model="processForm.last_mtc_date" type="date"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                    </div>
                    <div class="flex gap-3 pb-safe">
                        <button type="button" @click="showProcessModal = false" class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">Batal</button>
                        <button type="submit" :disabled="processForm.processing" class="flex-1 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 disabled:opacity-50 font-bold text-sm active:scale-95 transition-all">
                            {{ processForm.processing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showDelProcessModal && selectedProcess" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl p-5 text-center">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-0 mb-4 sm:hidden"></div>
                <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-3"><AlertTriangle class="w-6 h-6 text-red-600" /></div>
                <h3 class="font-bold text-gray-900 dark:text-white mb-1">Hapus Proses?</h3>
                <p class="text-sm text-gray-500 mb-5">{{ selectedProcess.process_name }}</p>
                <div class="flex gap-3">
                    <button @click="showDelProcessModal = false" class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">Batal</button>
                    <button @click="submitDelProcess" class="flex-1 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 font-bold text-sm active:scale-95 transition-all">Hapus</button>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="lightboxSrc" class="fixed inset-0 z-[60] bg-black/90 flex items-center justify-center p-4" @click="lightboxSrc = null">
                <button class="absolute top-4 right-4 p-2 bg-white/10 hover:bg-white/20 rounded-xl text-white transition-colors"><X class="w-5 h-5" /></button>
                <img :src="lightboxSrc" class="max-w-full max-h-full rounded-2xl shadow-2xl object-contain" @click.stop />
            </div>
        </Teleport>
    </AppLayout>
</template>
