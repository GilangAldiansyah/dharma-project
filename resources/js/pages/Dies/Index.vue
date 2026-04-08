<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    Plus, Search, Filter, Upload, Trash2, Eye, Pencil,
    AlertTriangle, CheckCircle2, X, Loader2, Download,
    FileText, ChevronDown, ChevronUp
} from 'lucide-vue-next';
import * as XLSX from 'xlsx';

interface DiesProcess {
    id: number;
    dies_id: string;
    process_name: string;
    tonase: number | null;
    std_stroke: number;
    current_stroke: number;
    last_mtc_date: string | null;
    pct: number;
    remaining: number;
}

interface Dies {
    id_sap: string;
    no_part: string;
    nama_dies: string;
    line: string;
    kategori: string | null;
    status: string;
    is_common: boolean;
    forecast_per_day: number;
    bstb_updated_at: string | null;
    preventives_count: number;
    correctives_count: number;
    processes: DiesProcess[];
}

interface BstbProcess {
    id: number;
    process_name: string;
    old_stroke: number;
    add_qty: number;
    new_stroke: number;
    std_stroke: number;
}

interface BstbPreviewItem {
    id_sap: string;
    no_part: string;
    nama_dies: string;
    line: string;
    desc: string;
    qty: number;
    processes: BstbProcess[];
}

interface Props {
    dies: { data: Dies[]; links: any[]; meta: any; total?: number; from?: number; to?: number; last_page?: number };
    lines: string[];
    filters: { search?: string; status?: string; line?: string };
    totalProcesses: number;
}

const props  = defineProps<Props>();
const page   = usePage();
const flash  = computed(() => (page.props as any).flash);

const totalDies = computed(() => props.dies.meta?.total    ?? props.dies.total    ?? 0);
const fromDies  = computed(() => props.dies.meta?.from     ?? props.dies.from     ?? 0);
const toDies    = computed(() => props.dies.meta?.to       ?? props.dies.to       ?? 0);
const lastPage  = computed(() => props.dies.meta?.last_page ?? props.dies.last_page ?? 1);

const search       = ref(props.filters.search ?? '');
const filterStatus = ref(props.filters.status ?? '');
const filterLine   = ref(props.filters.line   ?? '');
const showFilter   = ref(false);
const showAddModal    = ref(false);
const showEditModal   = ref(false);
const showImportModal = ref(false);
const showDeleteModal = ref(false);
const showBstbModal   = ref(false);
const selectedDies    = ref<Dies | null>(null);
const importFile      = ref<File | null>(null);
const isImporting     = ref(false);
const expandedRows    = ref<Set<string>>(new Set());

const bstbFile        = ref<File | null>(null);
const bstbUploading   = ref(false);
const bstbConfirming  = ref(false);
const bstbStep        = ref<'upload' | 'preview'>('upload');
const selectedUpdates = ref<string[]>([]);

const bstbPreviewRaw = computed<BstbPreviewItem[]>(() => (page.props as any).bstb_preview ?? []);
const bstbNotFound   = computed(() => (page.props as any).bstb_not_found ?? []);
const bstbNoChange   = computed(() => (page.props as any).bstb_no_change ?? 0);
const bstbTotal      = computed(() => (page.props as any).bstb_total     ?? 0);

const editablePreview = ref<BstbPreviewItem[]>([]);

watch(bstbPreviewRaw, (val) => {
    editablePreview.value = JSON.parse(JSON.stringify(val));
}, { immediate: true, deep: true });

const recalcNewStroke = (item: BstbPreviewItem, proc: BstbProcess) => {
    proc.new_stroke = proc.old_stroke + (proc.add_qty ?? 0);
};

watch(bstbTotal, (val) => {
    if (val > 0 && bstbStep.value === 'upload') {
        bstbStep.value        = 'preview';
        selectedUpdates.value = bstbPreviewRaw.value.map((p) => p.id_sap);
    }
}, { immediate: true });

const handleBstbFile = (e: Event) => {
    bstbFile.value = (e.target as HTMLInputElement).files?.[0] ?? null;
};

const uploadBstb = () => {
    if (!bstbFile.value) return;
    bstbUploading.value = true;
    const fd = new FormData();
    fd.append('file', bstbFile.value);
    router.post('/dies/import-bstb-preview', fd, {
        forceFormData: true,
        onSuccess: () => {
            bstbUploading.value = false;
            const total = (page.props as any).bstb_total ?? 0;
            if (total > 0) {
                bstbStep.value        = 'preview';
                selectedUpdates.value = ((page.props as any).bstb_preview ?? []).map((p: any) => p.id_sap);
            }
        },
        onError: () => { bstbUploading.value = false; },
    });
};

const confirmBstb = () => {
    const payload = editablePreview.value
        .filter(item => selectedUpdates.value.includes(item.id_sap))
        .map(item => ({
            id_sap: item.id_sap,
            processes: item.processes.map(p => ({
                id:      p.id,
                add_qty: p.add_qty,
            })),
        }));

    if (payload.length === 0) return;
    bstbConfirming.value = true;
    router.post('/dies/import-bstb-confirm', { updates: payload }, {
        onSuccess: () => {
            bstbConfirming.value  = false;
            showBstbModal.value   = false;
            bstbFile.value        = null;
            bstbStep.value        = 'upload';
            selectedUpdates.value = [];
            editablePreview.value = [];
        },
        onError: () => { bstbConfirming.value = false; },
    });
};

const closeBstbModal = () => {
    showBstbModal.value   = false;
    bstbFile.value        = null;
    bstbStep.value        = 'upload';
    selectedUpdates.value = [];
    editablePreview.value = [];
};

const toggleAllBstb = () => {
    if (selectedUpdates.value.length === editablePreview.value.length) {
        selectedUpdates.value = [];
    } else {
        selectedUpdates.value = editablePreview.value.map((p) => p.id_sap);
    }
};

const toggleBstbItem = (idSap: string) => {
    const idx = selectedUpdates.value.indexOf(idSap);
    if (idx >= 0) selectedUpdates.value.splice(idx, 1);
    else selectedUpdates.value.push(idSap);
};

const statusList = [
    { v: 'active',       l: 'Active',       badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' },
    { v: 'slow_moving',  l: 'Slow Moving',  badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' },
    { v: 'discontinue',  l: 'Discontinue',  badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' },
    { v: 'ohp',          l: 'OHP',          badge: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400' },
    { v: 'service_part', l: 'Service Part', badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400' },
];

const statusCfg = (s: string) => statusList.find(x => x.v === s) ?? { l: s, badge: 'bg-gray-100 text-gray-600' };

const getDiesProcesses = (d: Dies): DiesProcess[] => d.processes ?? [];

const procPct = (p: DiesProcess): number => {
    if (!p.std_stroke) return 0;
    return Math.min(Math.round(p.current_stroke / p.std_stroke * 1000) / 10, 100);
};

const pctBadge = (pct: number) => {
    if (pct >= 100) return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400';
    if (pct >= 85)  return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400';
    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400';
};

const pctBar = (pct: number) => {
    if (pct >= 100) return 'bg-red-500';
    if (pct >= 85)  return 'bg-amber-400';
    return 'bg-emerald-400';
};

const pctTextColor = (pct: number) => {
    if (pct >= 100) return 'text-red-600 dark:text-red-400';
    if (pct >= 85)  return 'text-amber-600 dark:text-amber-400';
    return 'text-emerald-600 dark:text-emerald-400';
};

const toggleRow = (idSap: string) => {
    if (expandedRows.value.has(idSap)) expandedRows.value.delete(idSap);
    else expandedRows.value.add(idSap);
};

const isExpanded = (idSap: string) => expandedRows.value.has(idSap);

const activeFilterCount = computed(() => {
    let c = 0;
    if (filterStatus.value) c++;
    if (filterLine.value)   c++;
    return c;
});

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/dies', { search: val, status: filterStatus.value, line: filterLine.value },
            { preserveState: true, preserveScroll: true, replace: true });
    }, 400);
});

watch([filterStatus, filterLine], () => {
    router.get('/dies', { search: search.value, status: filterStatus.value, line: filterLine.value },
        { preserveState: true, preserveScroll: true });
});

const form = useForm({
    id_sap: '', no_part: '', nama_dies: '', line: '',
    kategori: '', status: 'active', is_common: false,
    forecast_per_day: 0,
    process_name: '', std_stroke: 20000, tonase: null as number | null,
    last_mtc_date: '',
});

const editForm = useForm({
    no_part: '', nama_dies: '', line: '',
    kategori: '', status: 'active', is_common: false,
    forecast_per_day: 0,
});

const openAdd  = () => { form.reset(); showAddModal.value = true; };
const closeAdd = () => { showAddModal.value = false; form.reset(); };
const submitAdd = () => { form.post('/dies', { onSuccess: closeAdd }); };

const openEdit = (d: Dies) => {
    selectedDies.value         = d;
    editForm.no_part           = d.no_part;
    editForm.nama_dies         = d.nama_dies;
    editForm.line              = d.line;
    editForm.kategori          = d.kategori ?? '';
    editForm.status            = d.status;
    editForm.is_common         = d.is_common;
    editForm.forecast_per_day  = d.forecast_per_day;
    showEditModal.value = true;
};

const closeEdit  = () => { showEditModal.value = false; editForm.reset(); selectedDies.value = null; };
const submitEdit = () => {
    if (!selectedDies.value) return;
    editForm.put(`/dies/${selectedDies.value.id_sap}`, { onSuccess: closeEdit });
};

const openDelete   = (d: Dies) => { selectedDies.value = d; showDeleteModal.value = true; };
const submitDelete = () => {
    if (!selectedDies.value) return;
    router.delete(`/dies/${selectedDies.value.id_sap}`, {
        onSuccess: () => { showDeleteModal.value = false; selectedDies.value = null; },
    });
};

const handleImportFile = (e: Event) => {
    importFile.value = (e.target as HTMLInputElement).files?.[0] ?? null;
};

const submitImport = async () => {
    if (!importFile.value) return;
    isImporting.value = true;
    const fd = new FormData();
    fd.append('file', importFile.value);
    router.post('/dies/import', fd, {
        onSuccess: () => { showImportModal.value = false; importFile.value = null; },
        onFinish:  () => { isImporting.value = false; },
    });
};

const fmtDate = (d: string | null) => {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const exportExcel = () => {
    const rows = [
        ['No Part', 'Nama Dies', 'ID SAP', 'Line', 'Kategori', 'Status', 'Common', 'Forecast/Day', 'Total PM', 'Total CM', 'Proses', 'STD Stroke', 'Current Stroke', '%'],
        ...props.dies.data.flatMap(d => {
            const procs = getDiesProcesses(d);
            if (!procs.length) return [[
                d.no_part, d.nama_dies, d.id_sap, d.line, d.kategori ?? '-',
                statusCfg(d.status).l, d.is_common ? 'Ya' : 'Tidak',
                d.forecast_per_day, d.preventives_count, d.correctives_count,
                '-', 0, 0, '0%',
            ]];
            return procs.map((p, i) => [
                i === 0 ? d.no_part : '',
                i === 0 ? d.nama_dies : '',
                i === 0 ? d.id_sap : '',
                i === 0 ? d.line : '',
                i === 0 ? (d.kategori ?? '-') : '',
                i === 0 ? statusCfg(d.status).l : '',
                i === 0 ? (d.is_common ? 'Ya' : 'Tidak') : '',
                i === 0 ? d.forecast_per_day : '',
                i === 0 ? d.preventives_count : '',
                i === 0 ? d.correctives_count : '',
                p.process_name,
                p.std_stroke,
                p.current_stroke,
                `${procPct(p)}%`,
            ]);
        }),
    ];
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [
        { wch: 16 }, { wch: 30 }, { wch: 20 }, { wch: 10 }, { wch: 16 },
        { wch: 14 }, { wch: 8 }, { wch: 12 }, { wch: 10 }, { wch: 10 },
        { wch: 20 }, { wch: 12 }, { wch: 14 }, { wch: 8 },
    ];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Master Dies');
    XLSX.writeFile(wb, 'Master_Dies.xlsx');
};
</script>

<template>
    <Head title="Master Dies" />
    <AppLayout :breadcrumbs="[{ title: 'Dies', href: '/dies' }, { title: 'Master Dies', href: '/dies' }]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-blue-600 rounded-xl flex-shrink-0">
                            <Filter class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
                        </span>
                        Master Dies
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">
                        {{ totalDies }} part
                        <span class="text-gray-300 mx-1">|</span>
                        <span class="text-gray-400">{{ totalProcesses }} dies</span>
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0 flex-wrap justify-end">
                    <button @click="exportExcel"
                        class="flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold active:scale-95 transition-all">
                        <Download class="w-3.5 h-3.5" /><span class="hidden sm:inline">Export</span>
                    </button>
                    <button @click="showBstbModal = true"
                        class="flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold active:scale-95 transition-all">
                        <Upload class="w-3.5 h-3.5" /><span class="hidden sm:inline">Import BSTB</span><span class="sm:hidden">BSTB</span>
                    </button>
                    <button @click="showImportModal = true"
                        class="flex items-center gap-1.5 px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-semibold hover:bg-gray-200 active:scale-95 transition-all">
                        <Upload class="w-3.5 h-3.5" /><span class="hidden sm:inline">Import Excel</span>
                    </button>
                    <button @click="openAdd"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-semibold active:scale-95 transition-all shadow-sm">
                        <Plus class="w-3.5 h-3.5" /><span class="hidden sm:inline">Tambah Dies</span><span class="sm:hidden">Tambah</span>
                    </button>
                </div>
            </div>

            <div v-if="flash?.success"
                class="flex items-center gap-3 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                <p class="text-emerald-800 dark:text-emerald-200 font-medium text-xs sm:text-sm">{{ flash.success }}</p>
            </div>

            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                        <input v-model="search" type="text" placeholder="Cari no part, nama dies, ID SAP..."
                            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-blue-500 focus:outline-none transition-colors" />
                    </div>
                    <button @click="showFilter = !showFilter"
                        :class="['relative flex items-center gap-1.5 px-3 py-2.5 border rounded-xl text-sm font-medium transition-colors',
                            showFilter || activeFilterCount > 0
                                ? 'bg-blue-600 border-blue-600 text-white'
                                : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-blue-400']">
                        <Filter class="w-4 h-4" />
                        <span v-if="activeFilterCount > 0"
                            class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold">
                            {{ activeFilterCount }}
                        </span>
                    </button>
                </div>

                <div v-if="showFilter" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-3 space-y-3 shadow-sm">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Status</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="s in [{ v: '', l: 'Semua' }, ...statusList]" :key="s.v"
                                @click="filterStatus = s.v"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                                    filterStatus === s.v ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
                                {{ s.l }}
                            </button>
                        </div>
                    </div>
                    <div v-if="lines.length">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Line</label>
                        <div class="flex flex-wrap gap-2">
                            <button @click="filterLine = ''"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                                    filterLine === '' ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
                                Semua
                            </button>
                            <button v-for="l in lines" :key="l" @click="filterLine = l"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                                    filterLine === l ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
                                {{ l }}
                            </button>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button @click="filterStatus = ''; filterLine = ''"
                            class="text-xs text-blue-500 font-semibold hover:underline">Reset filter</button>
                    </div>
                </div>
            </div>

            <!-- Desktop Table -->
            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide w-6"></th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">No Part / Dies</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Line</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Proses & Stroke</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">PM / CM</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">BSTB Update</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="dies.data.length === 0">
                                <td colspan="8" class="py-16 text-center text-gray-400 text-sm">
                                    <Search class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                    Tidak ada data dies
                                </td>
                            </tr>
                            <template v-for="d in dies.data" :key="d.id_sap">
                                <tr :class="['transition-colors border-b border-gray-50 dark:border-gray-700/50',
                                    isExpanded(d.id_sap) ? 'bg-blue-50/40 dark:bg-blue-900/10' : 'hover:bg-gray-50/80 dark:hover:bg-gray-700/30']">
                                    <td class="pl-3 pr-1 py-3">
                                        <button v-if="getDiesProcesses(d).length > 0"
                                            @click="toggleRow(d.id_sap)"
                                            class="p-1 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-gray-400">
                                            <ChevronDown v-if="!isExpanded(d.id_sap)" class="w-3.5 h-3.5" />
                                            <ChevronUp v-else class="w-3.5 h-3.5 text-blue-500" />
                                        </button>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div>
                                                <p class="text-xs font-bold text-gray-900 dark:text-white">{{ d.no_part }}</p>
                                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ d.nama_dies }}</p>
                                                <p class="text-xs text-gray-300 mt-0.5 font-mono">{{ d.id_sap }}</p>
                                            </div>
                                            <span v-if="d.is_common"
                                                class="px-1.5 py-0.5 bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-400 rounded text-xs font-bold flex-shrink-0">
                                                Common
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 rounded-full text-xs font-semibold">
                                            {{ d.line }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="['inline-block px-2 py-0.5 rounded-full text-xs font-bold', statusCfg(d.status).badge]">
                                            {{ statusCfg(d.status).l }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div v-if="getDiesProcesses(d).length === 0" class="text-center text-xs text-gray-300">—</div>
                                        <div v-else class="space-y-1.5">
                                            <div v-for="p in getDiesProcesses(d)" :key="p.id" class="flex items-center gap-2">
                                                <span class="text-xs text-gray-500 w-24 truncate flex-shrink-0">{{ p.process_name }}</span>
                                                <div class="flex-1 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden min-w-16">
                                                    <div :class="['h-full rounded-full transition-all', pctBar(procPct(p))]"
                                                        :style="{ width: `${procPct(p)}%` }"></div>
                                                </div>
                                                <span :class="['text-xs font-bold w-10 text-right flex-shrink-0', pctTextColor(procPct(p))]">
                                                    {{ procPct(p) }}%
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center text-xs">
                                        <span class="text-blue-600 dark:text-blue-400 font-bold">{{ d.preventives_count }}</span>
                                        <span class="text-gray-300 mx-1">/</span>
                                        <span class="text-orange-600 dark:text-orange-400 font-bold">{{ d.correctives_count }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <p v-if="d.bstb_updated_at" class="text-xs text-indigo-500 font-semibold">{{ fmtDate(d.bstb_updated_at) }}</p>
                                        <p v-else class="text-xs text-gray-300">—</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button @click="router.visit(`/dies/${d.id_sap}`)"
                                                class="p-1.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-200 transition-colors">
                                                <Eye class="w-3.5 h-3.5" />
                                            </button>
                                            <button @click="openEdit(d)"
                                                class="p-1.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-lg hover:bg-amber-200 transition-colors">
                                                <Pencil class="w-3.5 h-3.5" />
                                            </button>
                                            <button @click="openDelete(d)"
                                                class="p-1.5 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-200 transition-colors">
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="isExpanded(d.id_sap)" class="bg-blue-50/60 dark:bg-blue-900/10 border-b border-blue-100 dark:border-blue-900/30">
                                    <td colspan="8" class="px-8 py-3">
                                        <div class="grid grid-cols-2 xl:grid-cols-3 gap-2">
                                            <div v-for="p in getDiesProcesses(d)" :key="p.id"
                                                class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3 flex flex-col gap-1.5">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ p.process_name }}</span>
                                                    <span :class="['text-xs font-black px-2 py-0.5 rounded-full', pctBadge(procPct(p))]">
                                                        {{ procPct(p) }}%
                                                    </span>
                                                </div>
                                                <div class="h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                                    <div :class="['h-full rounded-full', pctBar(procPct(p))]"
                                                        :style="{ width: `${procPct(p)}%` }"></div>
                                                </div>
                                                <div class="flex items-center justify-between text-xs text-gray-500">
                                                    <span>{{ (p.current_stroke ?? 0).toLocaleString() }} / {{ (p.std_stroke ?? 0).toLocaleString() }}</span>
                                                    <span class="text-gray-400">sisa {{ (p.remaining ?? 0).toLocaleString() }}</span>
                                                </div>
                                                <div class="flex items-center justify-between text-xs">
                                                    <span class="text-gray-400">Last MTC</span>
                                                    <span class="text-gray-600 dark:text-gray-400">{{ fmtDate(p.last_mtc_date) }}</span>
                                                </div>
                                                <div v-if="p.tonase" class="flex items-center justify-between text-xs">
                                                    <span class="text-gray-400">Tonase</span>
                                                    <span class="text-gray-600 dark:text-gray-400">{{ p.tonase }} T</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div v-if="lastPage > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-500">{{ fromDies }}–{{ toDies }} dari {{ totalDies }} dies</p>
                    <div class="flex gap-1">
                        <button v-for="link in dies.links" :key="link.label"
                            @click="link.url && router.visit(link.url)"
                            :disabled="!link.url" v-html="link.label"
                            :class="['px-3 py-1.5 text-xs rounded-lg font-semibold transition-colors',
                                link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200' : 'opacity-40 cursor-default bg-gray-100 dark:bg-gray-700 text-gray-400']">
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Cards -->
            <div class="lg:hidden space-y-2.5">
                <div v-if="dies.data.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <p class="text-gray-400 text-sm">Tidak ada data dies</p>
                </div>
                <div v-for="d in dies.data" :key="d.id_sap"
                    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="p-3.5">
                        <div class="flex items-start justify-between gap-2 mb-2.5">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ d.no_part }}</p>
                                    <span v-if="d.is_common" class="px-1.5 py-0.5 bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-400 rounded text-xs font-bold">Common</span>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ d.nama_dies }}</p>
                                <p class="text-xs text-gray-300 mt-0.5 font-mono">{{ d.id_sap }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                <span :class="['inline-block px-2 py-0.5 rounded-full text-xs font-bold', statusCfg(d.status).badge]">{{ statusCfg(d.status).l }}</span>
                                <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 rounded-full text-xs font-semibold">{{ d.line }}</span>
                            </div>
                        </div>

                        <div v-if="getDiesProcesses(d).length > 0" class="space-y-2 mb-3">
                            <p class="text-xs font-bold text-gray-500 uppercase">Proses</p>
                            <div v-for="p in getDiesProcesses(d)" :key="p.id" class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-2.5 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ p.process_name }}</span>
                                    <span :class="['text-xs font-black px-2 py-0.5 rounded-full', pctBadge(procPct(p))]">{{ procPct(p) }}%</span>
                                </div>
                                <div class="h-1.5 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                                    <div :class="['h-full rounded-full', pctBar(procPct(p))]" :style="{ width: `${procPct(p)}%` }"></div>
                                </div>
                                <div class="flex items-center justify-between text-xs text-gray-400">
                                    <span>{{ (p.current_stroke ?? 0).toLocaleString() }} / {{ (p.std_stroke ?? 0).toLocaleString() }}</span>
                                    <span>Last MTC: {{ fmtDate(p.last_mtc_date) }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mb-3 text-xs text-gray-300 text-center py-2">Belum ada data proses</div>

                        <div class="space-y-1 mb-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-400">PM / CM</span>
                                <span>
                                    <span class="text-blue-600 font-bold">{{ d.preventives_count }}</span>
                                    <span class="text-gray-300 mx-1">/</span>
                                    <span class="text-orange-600 font-bold">{{ d.correctives_count }}</span>
                                </span>
                            </div>
                            <div v-if="d.bstb_updated_at" class="flex items-center justify-between text-xs">
                                <span class="text-gray-400">BSTB Update</span>
                                <span class="text-indigo-500 font-semibold">{{ fmtDate(d.bstb_updated_at) }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-2.5 border-t border-gray-100 dark:border-gray-700">
                            <button @click="router.visit(`/dies/${d.id_sap}`)"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-bold hover:bg-gray-200 active:scale-95 transition-all">
                                <Eye class="w-3.5 h-3.5" /> Detail
                            </button>
                            <button @click="openEdit(d)"
                                class="flex items-center justify-center gap-1.5 py-2 px-4 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-xl text-xs font-bold hover:bg-amber-200 active:scale-95 transition-all">
                                <Pencil class="w-3.5 h-3.5" /> Edit
                            </button>
                            <button @click="openDelete(d)"
                                class="flex items-center justify-center gap-1.5 py-2 px-4 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-xl text-xs font-bold hover:bg-red-200 active:scale-95 transition-all">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="lastPage > 1" class="flex justify-center gap-1 pt-2">
                    <button v-for="link in dies.links" :key="link.label"
                        @click="link.url && router.visit(link.url)"
                        :disabled="!link.url" v-html="link.label"
                        :class="['px-3 py-1.5 text-xs rounded-lg font-semibold transition-colors',
                            link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' : 'opacity-40 cursor-default']">
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Modal -->
        <div v-if="showAddModal" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-2xl max-h-[95vh] flex flex-col shadow-2xl">
                <div class="flex-shrink-0">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <Plus class="w-4 h-4 text-blue-600" /> Tambah Dies
                        </h2>
                        <button @click="closeAdd" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                    </div>
                </div>
                <form @submit.prevent="submitAdd" class="overflow-y-auto flex-1 px-4 sm:px-6 py-4 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">ID SAP <span class="text-red-500">*</span></label>
                            <input v-model="form.id_sap" type="text" placeholder="A4ADMD31E..."
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none font-mono" />
                            <p v-if="form.errors.id_sap" class="mt-1 text-xs text-red-500">{{ form.errors.id_sap }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">No Part <span class="text-red-500">*</span></label>
                            <input v-model="form.no_part" type="text" placeholder="17167-BZ180"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                            <p v-if="form.errors.no_part" class="mt-1 text-xs text-red-500">{{ form.errors.no_part }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Nama Dies <span class="text-red-500">*</span></label>
                        <input v-model="form.nama_dies" type="text" placeholder="INSULATOR, EXHAUST MANIFOLD..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                        <p v-if="form.errors.nama_dies" class="mt-1 text-xs text-red-500">{{ form.errors.nama_dies }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Line <span class="text-red-500">*</span></label>
                            <input v-model="form.line" type="text" placeholder="SEYI / ADM / TMMIN"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                            <p v-if="form.errors.line" class="mt-1 text-xs text-red-500">{{ form.errors.line }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Kategori</label>
                            <input v-model="form.kategori" type="text" placeholder="INSULATOR, FRAME BODY..."
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Status <span class="text-red-500">*</span></label>
                            <select v-model="form.status"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none">
                                <option v-for="s in statusList" :key="s.v" :value="s.v">{{ s.l }}</option>
                            </select>
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.is_common" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Common Dies (R/L)</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Forecast/Day <span class="text-red-500">*</span></label>
                        <input v-model="form.forecast_per_day" type="number" min="0"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                    </div>
                    <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                        <p class="text-xs font-bold text-gray-500 uppercase mb-3">Proses Pertama (opsional)</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Nama Proses</label>
                                <input v-model="form.process_name" type="text" placeholder="DRAW / TRIM / PIERCE..."
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">STD Stroke</label>
                                <input v-model="form.std_stroke" type="number" min="0"
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Tonase (T)</label>
                                <input v-model="form.tonase" type="number" min="0" step="0.1"
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Last MTC Date</label>
                                <input v-model="form.last_mtc_date" type="date"
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                            </div>
                        </div>
                    </div>
                    <div class="sticky bottom-0 bg-white dark:bg-gray-800 pt-3 pb-safe border-t border-gray-100 dark:border-gray-700 -mx-4 sm:-mx-6 px-4 sm:px-6">
                        <div class="flex gap-3">
                            <button type="button" @click="closeAdd"
                                class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">Batal</button>
                            <button type="submit" :disabled="form.processing"
                                class="flex-1 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 disabled:opacity-50 font-bold text-sm active:scale-95 transition-all">
                                {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="showEditModal && selectedDies" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-2xl max-h-[95vh] flex flex-col shadow-2xl">
                <div class="flex-shrink-0">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <Pencil class="w-4 h-4 text-amber-500" /> Edit Dies — {{ selectedDies.no_part }}
                        </h2>
                        <button @click="closeEdit" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                    </div>
                </div>
                <form @submit.prevent="submitEdit" class="overflow-y-auto flex-1 px-4 sm:px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Nama Dies <span class="text-red-500">*</span></label>
                        <input v-model="editForm.nama_dies" type="text"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-amber-500 focus:outline-none" />
                        <p v-if="editForm.errors.nama_dies" class="mt-1 text-xs text-red-500">{{ editForm.errors.nama_dies }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Line <span class="text-red-500">*</span></label>
                            <input v-model="editForm.line" type="text"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-amber-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Kategori</label>
                            <input v-model="editForm.kategori" type="text"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-amber-500 focus:outline-none" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Status <span class="text-red-500">*</span></label>
                            <select v-model="editForm.status"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-amber-500 focus:outline-none">
                                <option v-for="s in statusList" :key="s.v" :value="s.v">{{ s.l }}</option>
                            </select>
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="editForm.is_common" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Common Dies (R/L)</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Forecast/Day</label>
                        <input v-model="editForm.forecast_per_day" type="number" min="0"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-amber-500 focus:outline-none" />
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3 text-xs text-blue-700 dark:text-blue-300">
                        Data proses (stroke, STD stroke, last MTC) dikelola melalui halaman detail dies.
                    </div>
                    <div class="sticky bottom-0 bg-white dark:bg-gray-800 pt-3 pb-safe border-t border-gray-100 dark:border-gray-700 -mx-4 sm:-mx-6 px-4 sm:px-6">
                        <div class="flex gap-3">
                            <button type="button" @click="closeEdit"
                                class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">Batal</button>
                            <button type="submit" :disabled="editForm.processing"
                                class="flex-1 py-3 bg-amber-500 text-white rounded-xl hover:bg-amber-600 disabled:opacity-50 font-bold text-sm active:scale-95 transition-all">
                                {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Import Excel Modal -->
        <div v-if="showImportModal" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Upload class="w-4 h-4 text-blue-600" /> Import Stroke dari Excel
                    </h2>
                    <button @click="showImportModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                </div>
                <div class="p-5 space-y-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3.5 text-xs text-blue-700 dark:text-blue-300 space-y-1">
                        <p class="font-bold">Format Excel:</p>
                        <p>• Kolom L = ID SAP</p>
                        <p>• Kolom AA = Counting Stroke</p>
                        <p>• Kolom AC = Total Stroke</p>
                        <p>• Kolom S = Last MTC Date</p>
                        <p>• Data mulai dari baris ke-3</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-2 text-gray-700 dark:text-gray-300">Pilih File Excel</label>
                        <input type="file" accept=".xlsx,.xls" @change="handleImportFile"
                            class="w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                    </div>
                    <div class="flex gap-3 pb-safe">
                        <button @click="showImportModal = false"
                            class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">Batal</button>
                        <button @click="submitImport" :disabled="!importFile || isImporting"
                            class="flex-1 flex items-center justify-center gap-2 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 disabled:opacity-50 font-bold text-sm active:scale-95 transition-all">
                            <Loader2 v-if="isImporting" class="w-4 h-4 animate-spin" />
                            {{ isImporting ? 'Mengimport...' : 'Import' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- BSTB Modal -->
        <div v-if="showBstbModal" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-3xl max-h-[92vh] flex flex-col shadow-2xl">
                <div class="flex-shrink-0">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <div>
                            <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Upload class="w-4 h-4 text-indigo-600" /> Import BSTB
                                <span class="text-xs font-normal text-gray-400">— Update Counting Stroke dari CSV</span>
                            </h2>
                            <div class="flex items-center gap-2 mt-1.5">
                                <div :class="['flex items-center gap-1.5 text-xs font-semibold', bstbStep === 'upload' ? 'text-indigo-600' : 'text-gray-400']">
                                    <span :class="['w-5 h-5 rounded-full flex items-center justify-center text-xs font-black', bstbStep === 'upload' ? 'bg-indigo-600 text-white' : 'bg-emerald-500 text-white']">
                                        {{ bstbStep === 'upload' ? '1' : '✓' }}
                                    </span>
                                    Upload CSV
                                </div>
                                <div class="w-8 h-px bg-gray-200 dark:bg-gray-600"></div>
                                <div :class="['flex items-center gap-1.5 text-xs font-semibold', bstbStep === 'preview' ? 'text-indigo-600' : 'text-gray-400']">
                                    <span :class="['w-5 h-5 rounded-full flex items-center justify-center text-xs font-black', bstbStep === 'preview' ? 'bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-500']">2</span>
                                    Review & Confirm
                                </div>
                            </div>
                        </div>
                        <button @click="closeBstbModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div class="overflow-y-auto flex-1 px-4 sm:px-6 py-4 space-y-4">
                    <div v-if="bstbStep === 'upload'" class="space-y-4">
                        <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-xl p-4 border border-indigo-100 dark:border-indigo-800 text-xs text-indigo-800 dark:text-indigo-200 space-y-1.5">
                            <p class="font-bold text-sm">Format CSV BSTB yang didukung:</p>
                            <p>• File CSV dari sistem BSTB (Daily Production Report)</p>
                            <p>• Sistem membaca otomatis semua shift dalam satu file</p>
                            <p>• Key matching: <span class="font-bold">Material Number</span> → ID SAP di sistem</p>
                            <p>• Qty BSTB akan ditambahkan ke <span class="font-bold">semua proses</span> dies — bisa diedit per proses sebelum konfirmasi</p>
                        </div>
                        <label class="flex flex-col items-center justify-center gap-3 p-8 border-2 border-dashed border-indigo-200 dark:border-indigo-700 rounded-2xl cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/20 transition-colors">
                            <div class="w-14 h-14 bg-indigo-100 dark:bg-indigo-900/40 rounded-2xl flex items-center justify-center">
                                <FileText class="w-7 h-7 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-bold text-indigo-700 dark:text-indigo-300">
                                    {{ bstbFile ? bstbFile.name : 'Klik untuk pilih file BSTB' }}
                                </p>
                                <p class="text-xs text-gray-400 mt-1">Format: .CSV | Max 10MB</p>
                            </div>
                            <input type="file" accept=".csv,.txt" class="hidden" @change="handleBstbFile" />
                        </label>
                        <div v-if="bstbFile" class="flex items-center gap-3 p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-800">
                            <CheckCircle2 class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-emerald-700 dark:text-emerald-300 truncate">{{ bstbFile.name }}</p>
                                <p class="text-xs text-emerald-500">{{ (bstbFile.size / 1024).toFixed(1) }} KB</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="bstbStep === 'preview'" class="space-y-4">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3 border border-blue-100 dark:border-blue-800 text-center">
                                <p class="text-2xl font-black text-blue-600">{{ bstbTotal }}</p>
                                <p class="text-xs text-blue-500 font-semibold">Total di CSV</p>
                            </div>
                            <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-3 border border-emerald-100 dark:border-emerald-800 text-center">
                                <p class="text-2xl font-black text-emerald-600">{{ editablePreview.length }}</p>
                                <p class="text-xs text-emerald-500 font-semibold">Ada Perubahan</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3 border border-gray-100 dark:border-gray-700 text-center">
                                <p class="text-2xl font-black text-gray-500">{{ bstbNoChange }}</p>
                                <p class="text-xs text-gray-400 font-semibold">Qty Nol</p>
                            </div>
                            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-3 border border-amber-100 dark:border-amber-800 text-center">
                                <p class="text-2xl font-black text-amber-600">{{ bstbNotFound.length }}</p>
                                <p class="text-xs text-amber-500 font-semibold">Tidak Ditemukan</p>
                            </div>
                        </div>

                        <div v-if="bstbNotFound.length > 0" class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-3 border border-amber-200 dark:border-amber-800">
                            <p class="text-xs font-bold text-amber-700 dark:text-amber-400 mb-1.5 flex items-center gap-1">
                                <AlertTriangle class="w-3.5 h-3.5" /> {{ bstbNotFound.length }} ID SAP tidak ditemukan:
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="item in bstbNotFound.slice(0, 10)" :key="item.id_sap"
                                    class="text-xs px-2 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded font-mono">
                                    {{ item.id_sap }}
                                </span>
                                <span v-if="bstbNotFound.length > 10" class="text-xs text-amber-500 font-semibold">+{{ bstbNotFound.length - 10 }} lainnya</span>
                            </div>
                        </div>

                        <div v-if="editablePreview.length > 0" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox"
                                        :checked="selectedUpdates.length === editablePreview.length"
                                        @change="toggleAllBstb"
                                        class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300">
                                        {{ selectedUpdates.length }} / {{ editablePreview.length }} dipilih
                                    </span>
                                </div>
                                <span class="text-xs text-gray-400">Qty per proses bisa diedit</span>
                            </div>
                            <div class="overflow-x-auto max-h-96">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2 text-center w-10"></th>
                                            <th class="px-3 py-2 text-left font-bold text-gray-500 uppercase">No Part</th>
                                            <th class="px-3 py-2 text-center font-bold text-gray-500 uppercase">Qty BSTB</th>
                                            <th class="px-3 py-2 text-left font-bold text-gray-500 uppercase">Proses</th>
                                            <th class="px-3 py-2 text-center font-bold text-gray-500 uppercase">Stroke Skrg</th>
                                            <th class="px-3 py-2 text-center font-bold text-gray-500 uppercase">+Qty</th>
                                            <th class="px-3 py-2 text-center font-bold text-gray-500 uppercase">Setelah</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                                        <template v-for="item in editablePreview" :key="item.id_sap">
                                            <tr v-for="(proc, pi) in item.processes" :key="proc.id"
                                                :class="['transition-colors',
                                                    pi % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50/50 dark:bg-gray-700/20',
                                                    selectedUpdates.includes(item.id_sap) ? '' : 'opacity-40']">
                                                <td class="px-3 py-2 text-center">
                                                    <input v-if="pi === 0" type="checkbox"
                                                        :checked="selectedUpdates.includes(item.id_sap)"
                                                        @change="toggleBstbItem(item.id_sap)"
                                                        class="w-3.5 h-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                                </td>
                                                <td class="px-3 py-2">
                                                    <template v-if="pi === 0">
                                                        <p class="font-bold text-gray-900 dark:text-white">{{ item.no_part }}</p>
                                                        <p class="text-gray-400 truncate max-w-40">{{ item.nama_dies }}</p>
                                                        <p class="text-gray-300 font-mono">{{ item.id_sap }}</p>
                                                    </template>
                                                </td>
                                                <td class="px-3 py-2 text-center font-black text-emerald-600 dark:text-emerald-400">
                                                    <template v-if="pi === 0">+{{ item.qty.toLocaleString() }}</template>
                                                </td>
                                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400 font-semibold">{{ proc.process_name }}</td>
                                                <td class="px-3 py-2 text-center text-gray-400 font-semibold">{{ (proc.old_stroke ?? 0).toLocaleString() }}</td>
                                                <td class="px-3 py-2 text-center">
                                                    <input
                                                        v-model.number="proc.add_qty"
                                                        @input="recalcNewStroke(item, proc)"
                                                        type="number"
                                                        min="0"
                                                        :disabled="!selectedUpdates.includes(item.id_sap)"
                                                        class="w-20 px-2 py-1 text-xs text-center border border-indigo-200 dark:border-indigo-700 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-bold focus:outline-none focus:border-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed" />
                                                </td>
                                                <td class="px-3 py-2 text-center font-black text-indigo-600 dark:text-indigo-400">
                                                    {{ (proc.new_stroke ?? 0).toLocaleString() }}
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div v-else class="py-10 text-center">
                            <CheckCircle2 class="w-12 h-12 mx-auto mb-3 text-emerald-400" />
                            <p class="text-sm font-bold text-gray-600 dark:text-gray-300">Semua data sudah up-to-date</p>
                            <p class="text-xs text-gray-400 mt-1">Tidak ada qty yang perlu diakumulasi</p>
                        </div>

                        <button @click="bstbStep = 'upload'; bstbFile = null"
                            class="text-xs text-indigo-500 font-semibold hover:underline flex items-center gap-1">
                            ← Upload file lain
                        </button>
                    </div>
                </div>

                <div class="flex-shrink-0 px-4 sm:px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex gap-3">
                    <button @click="closeBstbModal"
                        class="px-5 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                        Batal
                    </button>
                    <button v-if="bstbStep === 'upload'"
                        @click="uploadBstb" :disabled="!bstbFile || bstbUploading"
                        class="flex-1 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-sm disabled:opacity-50 active:scale-95 transition-all flex items-center justify-center gap-2">
                        <Loader2 v-if="bstbUploading" class="w-4 h-4 animate-spin" />
                        <Upload v-else class="w-4 h-4" />
                        {{ bstbUploading ? 'Memproses...' : 'Proses File BSTB' }}
                    </button>
                    <button v-if="bstbStep === 'preview' && editablePreview.length > 0"
                        @click="confirmBstb" :disabled="selectedUpdates.length === 0 || bstbConfirming"
                        class="flex-1 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-sm disabled:opacity-50 active:scale-95 transition-all flex items-center justify-center gap-2">
                        <Loader2 v-if="bstbConfirming" class="w-4 h-4 animate-spin" />
                        <CheckCircle2 v-else class="w-4 h-4" />
                        {{ bstbConfirming ? 'Mengupdate...' : `Akumulasi ${selectedUpdates.length} Dies` }}
                    </button>
                    <button v-if="bstbStep === 'preview' && editablePreview.length === 0"
                        @click="closeBstbModal"
                        class="flex-1 py-3 bg-emerald-600 text-white rounded-xl font-bold text-sm active:scale-95 transition-all">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="showDeleteModal && selectedDies" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="p-5 text-center">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-3">
                        <AlertTriangle class="w-6 h-6 text-red-600" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">Hapus Dies?</h3>
                    <p class="text-sm text-gray-500 mb-1">{{ selectedDies.no_part }}</p>
                    <p class="text-xs text-gray-400 mb-5">Semua data PM, CM, proses, dan history sparepart terkait akan ikut terhapus.</p>
                    <div class="flex gap-3">
                        <button @click="showDeleteModal = false"
                            class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">Batal</button>
                        <button @click="submitDelete"
                            class="flex-1 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 font-bold text-sm active:scale-95 transition-all">Hapus</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
