<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    Wrench, Search, Filter, Plus, Eye, Pencil, Trash2,
    AlertTriangle, CheckCircle2, X, Download, Camera,
    Upload, Package, AlertCircle, User, Calendar, Loader2
} from 'lucide-vue-next';
import * as XLSX from 'xlsx';

interface DiesMini { id_sap: string; no_part: string; nama_dies: string; line: string; current_stroke: number }
interface Sparepart { id: number; sparepart_code: string; sparepart_name: string; unit: string; stok: number }
interface HistorySp { id: number; quantity: number; notes: string | null; sparepart: Sparepart | null; created_at: string }
interface Corrective {
    id: number; report_no: string; dies_id: string; pic_name: string;
    report_date: string; stroke_at_maintenance: number;
    problem_description: string; cause: string | null;
    repair_action: string; action: string | null;
    photos: string[] | null; status: string;
    closed_at: string | null;
    dies: DiesMini | null;
    spareparts: HistorySp[];
    created_by: { id: number; name: string } | null;
    closed_by: { id: number; name: string } | null;
}
interface Props {
    correctives: { data: Corrective[]; links: any[]; meta: any };
    diesList: DiesMini[];
    spareparts: Sparepart[];
    filters: { search?: string; status?: string; dies_id?: string };
}

const props = defineProps<Props>();
const page  = usePage();
const flash = computed(() => (page.props as any).flash);

const search       = ref(props.filters.search  ?? '');
const filterStatus = ref(props.filters.status  ?? '');
const filterDies   = ref(props.filters.dies_id ?? '');
const showFilter   = ref(false);

const showDelModal    = ref(false);
const showFormModal   = ref(false);
const showDetailModal = ref(false);
const showCloseModal  = ref(false);
const isEdit          = ref(false);
const selectedCm      = ref<Corrective | null>(null);
const lightboxSrc     = ref<string | null>(null);

const statusCfg: Record<string, { label: string; badge: string; dot: string }> = {
    open:        { label: 'Open',        badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',         dot: 'bg-red-400' },
    in_progress: { label: 'In Progress', badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400', dot: 'bg-amber-400' },
    closed:      { label: 'Closed',      badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400', dot: 'bg-emerald-500' },
};

const statusList = [
    { v: 'open',        l: 'Open',        hint: 'Laporan baru, belum ditangani' },
    { v: 'in_progress', l: 'In Progress', hint: 'Sedang dalam proses perbaikan' },
    { v: 'closed',      l: 'Closed',      hint: 'Perbaikan selesai dilakukan' },
];

const activeFilterCount = computed(() => [filterStatus.value, filterDies.value].filter(Boolean).length);

let debounce: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => navigate(), 400); });
watch([filterStatus, filterDies], () => navigate());

const navigate = () => router.get('/dies/corrective',
    { search: search.value, status: filterStatus.value, dies_id: filterDies.value },
    { preserveState: true, preserveScroll: true, replace: true });

const fmtDate = (d: string | null) =>
    !d ? '-' : new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

const fmtDatetime = (d: string | null) => {
    if (!d) return '-';
    return new Date(d).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

// ── Dies filter searchable ──
const diesSearch      = ref('');
const diesOpen        = ref(false);
const selectedDiesObj = computed(() => props.diesList.find(d => d.id_sap === filterDies.value) ?? null);
const filteredDies    = computed(() => {
    const q = diesSearch.value.toLowerCase().trim();
    if (!q) return props.diesList;
    return props.diesList.filter(d =>
        d.no_part.toLowerCase().includes(q) ||
        d.nama_dies.toLowerCase().includes(q) ||
        d.line.toLowerCase().includes(q)
    );
});
const selectDiesFilter  = (d: DiesMini) => { filterDies.value = d.id_sap; diesSearch.value = d.no_part; diesOpen.value = false; };
const clearDiesFilter   = () => { filterDies.value = ''; diesSearch.value = ''; diesOpen.value = true; };
const closeDiesDropdown = () => { setTimeout(() => { diesOpen.value = false; }, 180); };

watch(filterDies, (val) => {
    if (!val) { diesSearch.value = ''; return; }
    const found = props.diesList.find(d => d.id_sap === val);
    if (found) diesSearch.value = found.no_part;
}, { immediate: true });

const highlightMatch = (name: string, query: string) => {
    if (!query.trim()) return [{ text: name, match: false }];
    const idx = name.toLowerCase().indexOf(query.toLowerCase().trim());
    if (idx === -1) return [{ text: name, match: false }];
    return [
        { text: name.slice(0, idx), match: false },
        { text: name.slice(idx, idx + query.trim().length), match: true },
        { text: name.slice(idx + query.trim().length), match: false },
    ].filter(p => p.text !== '');
};

// ── Form ──
const form = useForm({
    dies_id:               '',
    report_date:           new Date().toISOString().slice(0, 10),
    stroke_at_maintenance: 0,
    problem_description:   '',
    cause:                 '',
    repair_action:         '',
    status:                'open',
    photos:                [] as File[],
    spareparts:            [] as { sparepart_id: number | null; quantity: string; notes: string }[],
});

// Form dies searchable
const formDiesSearch      = ref('');
const formDiesOpen        = ref(false);
const selectedFormDiesObj = computed(() => props.diesList.find(d => d.id_sap === form.dies_id) ?? null);
const filteredFormDies    = computed(() => {
    const q = formDiesSearch.value.toLowerCase().trim();
    if (!q) return props.diesList;
    return props.diesList.filter(d =>
        d.no_part.toLowerCase().includes(q) ||
        d.nama_dies.toLowerCase().includes(q) ||
        d.line.toLowerCase().includes(q)
    );
});
const selectFormDies        = (d: DiesMini) => { form.dies_id = d.id_sap; formDiesSearch.value = d.no_part; formDiesOpen.value = false; };
const clearFormDies         = () => { form.dies_id = ''; formDiesSearch.value = ''; formDiesOpen.value = true; };
const closeFormDiesDropdown = () => { setTimeout(() => { formDiesOpen.value = false; }, 180); };

watch(() => form.dies_id, () => {
    if (!isEdit.value && selectedFormDiesObj.value) {
        form.stroke_at_maintenance = selectedFormDiesObj.value.current_stroke;
    }
});

// ── Sparepart searchable ──
const spSearch = ref<string[]>([]);
const spOpen   = ref<boolean[]>([]);

const filteredSp = (i: number) => {
    const q = (spSearch.value[i] ?? '').toLowerCase().trim();
    if (!q) return props.spareparts;
    return props.spareparts.filter(s =>
        s.sparepart_name.toLowerCase().includes(q) ||
        s.sparepart_code.toLowerCase().includes(q)
    );
};
const selectSp        = (i: number, s: Sparepart) => { form.spareparts[i].sparepart_id = s.id; spSearch.value[i] = s.sparepart_name; spOpen.value[i] = false; };
const openSpDropdown  = (i: number) => {
    const existing = form.spareparts[i]?.sparepart_id;
    if (existing) {
        const sp = props.spareparts.find(s => s.id === existing);
        if (sp && !spSearch.value[i]) spSearch.value[i] = sp.sparepart_name;
    }
    spOpen.value[i] = true;
};
const closeSpDropdown  = (i: number) => { setTimeout(() => { spOpen.value[i] = false; }, 180); };
const clearSpSelection = (i: number) => { form.spareparts[i].sparepart_id = null; spSearch.value[i] = ''; spOpen.value[i] = true; };
const selectedSpItem   = (id: number | null) => props.spareparts.find(s => s.id === id);
const addSp    = () => { form.spareparts.push({ sparepart_id: null, quantity: '', notes: '' }); spSearch.value.push(''); spOpen.value.push(false); };
const removeSp = (i: number) => { form.spareparts.splice(i, 1); spSearch.value.splice(i, 1); spOpen.value.splice(i, 1); };

// ── Photo handling ──
const newPhotoPreview = ref<{ file: File; url: string }[]>([]);
const existingPhotos  = ref<string[]>([]);
const photosToDelete  = ref<string[]>([]);
const isCompressing   = ref(false);

const compressImage = (file: File): Promise<File> =>
    new Promise(resolve => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let w = img.width, h = img.height;
                const max = 1920;
                if (w > max || h > max) {
                    if (w > h) { h = (h / w) * max; w = max; }
                    else       { w = (w / h) * max; h = max; }
                }
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d')!.drawImage(img, 0, 0, w, h);
                canvas.toBlob(
                    blob => resolve(new File([blob!], 'photo.jpg', { type: 'image/jpeg' })),
                    'image/jpeg', 0.8
                );
            };
            img.src = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    });

const handleFileInput = async (e: Event) => {
    const files = Array.from((e.target as HTMLInputElement).files ?? []);
    if (!files.length) return;
    isCompressing.value = true;
    try {
        for (const f of files) {
            const compressed = await compressImage(f);
            newPhotoPreview.value.push({ file: compressed, url: URL.createObjectURL(compressed) });
        }
        form.photos = newPhotoPreview.value.map(p => p.file);
    } finally {
        isCompressing.value = false;
    }
    (e.target as HTMLInputElement).value = '';
};

const removeNewPhoto = (idx: number) => {
    URL.revokeObjectURL(newPhotoPreview.value[idx].url);
    newPhotoPreview.value.splice(idx, 1);
    form.photos = newPhotoPreview.value.map(p => p.file);
};
const markDeletePhoto = (path: string) => {
    if (photosToDelete.value.includes(path)) {
        photosToDelete.value = photosToDelete.value.filter(p => p !== path);
    } else {
        photosToDelete.value.push(path);
    }
};

// ── Open/close modals ──
const openAdd = () => {
    isEdit.value = false;
    selectedCm.value = null;
    form.dies_id = '';
    form.report_date = new Date().toISOString().slice(0, 10);
    form.stroke_at_maintenance = 0;
    form.problem_description = '';
    form.cause = '';
    form.repair_action = '';
    form.status = 'open';
    form.photos = [];
    form.spareparts = [];
    form.clearErrors();
    formDiesSearch.value = '';
    formDiesOpen.value = false;
    spSearch.value = [];
    spOpen.value = [];
    newPhotoPreview.value = [];
    existingPhotos.value = [];
    photosToDelete.value = [];
    showFormModal.value = true;
};

const openEdit = (c: Corrective) => {
    isEdit.value = true;
    selectedCm.value = c;
    form.dies_id = c.dies_id;
    form.report_date = c.report_date?.slice(0, 10) ?? new Date().toISOString().slice(0, 10);
    form.stroke_at_maintenance = c.stroke_at_maintenance;
    form.problem_description = c.problem_description;
    form.cause = c.cause ?? '';
    form.repair_action = c.repair_action;
    form.status = c.status;
    form.photos = [];
    form.spareparts = [];
    form.clearErrors();
    formDiesSearch.value = c.dies?.no_part ?? c.dies_id;
    formDiesOpen.value = false;
    spSearch.value = [];
    spOpen.value = [];
    newPhotoPreview.value = [];
    existingPhotos.value = c.photos ?? [];
    photosToDelete.value = [];
    showFormModal.value = true;
};

const openDetail = (c: Corrective) => { selectedCm.value = c; showDetailModal.value = true; };

const closeFormModal = () => {
    showFormModal.value = false;
    newPhotoPreview.value.forEach(p => URL.revokeObjectURL(p.url));
    newPhotoPreview.value = [];
};

const submitForm = () => {
    if (isEdit.value && selectedCm.value) {
        if (photosToDelete.value.length) {
            photosToDelete.value.forEach(photo => {
                router.post(`/dies/corrective/${selectedCm.value!.id}/delete-photo`,
                    { photo }, { preserveState: true });
            });
        }
        router.post(`/dies/corrective/${selectedCm.value.id}`, {
            _method:               'PUT',
            dies_id:               form.dies_id,
            report_date:           form.report_date,
            stroke_at_maintenance: form.stroke_at_maintenance,
            problem_description:   form.problem_description,
            cause:                 form.cause,
            repair_action:         form.repair_action,
            status:                form.status,
            photos:                form.photos,
            spareparts:            form.spareparts,
        }, {
            forceFormData: true,
            onSuccess: () => { closeFormModal(); },
        });
    } else {
        router.post('/dies/corrective', {
            dies_id:               form.dies_id,
            report_date:           form.report_date,
            stroke_at_maintenance: form.stroke_at_maintenance,
            problem_description:   form.problem_description,
            cause:                 form.cause,
            repair_action:         form.repair_action,
            status:                form.status,
            photos:                form.photos,
            spareparts:            form.spareparts,
        }, {
            forceFormData: true,
            onSuccess: () => { closeFormModal(); },
        });
    }
};

// ── Close ──
const closeForm = useForm({ action: '' });

const openClose = (c: Corrective) => {
    selectedCm.value = c;
    closeForm.reset();
    showCloseModal.value = true;
};

const submitClose = () => {
    if (!selectedCm.value) return;
    closeForm.post(`/dies/corrective/${selectedCm.value.id}/close`, {
        onSuccess: () => { showCloseModal.value = false; selectedCm.value = null; },
    });
};

// ── Delete ──
const openDelete = (c: Corrective) => { selectedCm.value = c; showDelModal.value = true; };

const submitDelete = () => {
    if (!selectedCm.value) return;
    router.delete(`/dies/corrective/${selectedCm.value.id}`, {
        preserveScroll: true,
        only: ['correctives'],
        onSuccess: () => { showDelModal.value = false; selectedCm.value = null; },
    });
};

// ── Export ──
const exportExcel = () => {
    const rows = [
        ['No Part', 'Nama Dies', 'Line', 'PIC', 'Tanggal', 'Stroke MTC', 'Problem', 'Cause', 'Tindakan', 'Action', 'Status', 'Ditutup Oleh', 'Tanggal Tutup'],
        ...props.correctives.data.map(c => [
            c.dies?.no_part ?? c.dies_id,
            c.dies?.nama_dies ?? '',
            c.dies?.line ?? '',
            c.pic_name,
            c.report_date,
            c.stroke_at_maintenance,
            c.problem_description,
            c.cause ?? '',
            c.repair_action,
            c.action ?? '',
            statusCfg[c.status]?.label ?? c.status,
            c.closed_by?.name ?? '',
            c.closed_at ? fmtDate(c.closed_at) : '',
        ]),
    ];
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [{ wch: 16 }, { wch: 30 }, { wch: 10 }, { wch: 16 }, { wch: 12 },
        { wch: 14 }, { wch: 30 }, { wch: 30 }, { wch: 30 }, { wch: 30 }, { wch: 14 }, { wch: 16 }, { wch: 14 }];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Corrective');
    XLSX.writeFile(wb, `Dies_CM_${new Date().toISOString().slice(0, 10)}.xlsx`);
};
</script>
<template>
    <Head title="Corrective Maintenance Dies" />
    <AppLayout :breadcrumbs="[
        { title: 'Dies', href: '/dies' },
        { title: 'Corrective', href: '/dies/corrective' },
    ]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <!-- Header -->
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-orange-500 rounded-xl flex-shrink-0">
                            <Wrench class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
                        </span>
                        Corrective Maintenance
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">{{ correctives.meta?.total ?? 0 }} laporan</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="exportExcel"
                        class="flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold active:scale-95 transition-all">
                        <Download class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Export</span>
                    </button>
                    <button @click="openAdd"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-xs sm:text-sm font-semibold active:scale-95 transition-all shadow-sm">
                        <Plus class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Tambah CM</span>
                        <span class="sm:hidden">Tambah</span>
                    </button>
                </div>
            </div>

            <!-- Flash -->
            <div v-if="flash?.success"
                class="flex items-center gap-3 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                <p class="text-emerald-800 dark:text-emerald-200 font-medium text-xs sm:text-sm">{{ flash.success }}</p>
            </div>

            <!-- Search + Filter -->
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                        <input v-model="search" type="text" placeholder="Cari no part, PIC..."
                            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-orange-400 focus:outline-none transition-colors" />
                    </div>
                    <button @click="showFilter = !showFilter"
                        :class="['relative flex items-center gap-1.5 px-3 py-2.5 border rounded-xl text-sm font-medium transition-colors',
                            showFilter || activeFilterCount > 0
                                ? 'bg-orange-500 border-orange-500 text-white'
                                : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-orange-400']">
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
                            <button v-for="s in [{ v: '', l: 'Semua' }, ...Object.entries(statusCfg).map(([v, c]) => ({ v, l: c.label }))]" :key="s.v"
                                @click="filterStatus = s.v"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                                    filterStatus === s.v ? 'bg-orange-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
                                {{ s.l }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Dies</label>
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                            <input v-model="diesSearch" type="text" placeholder="Cari no part / nama dies / line..."
                                autocomplete="off" @focus="diesOpen = true" @blur="closeDiesDropdown"
                                :class="['w-full pl-8 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                    selectedDiesObj ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-orange-400']" />
                            <button v-if="selectedDiesObj" type="button" @click="clearDiesFilter"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                                <X class="w-4 h-4" />
                            </button>
                            <div v-if="diesOpen"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-56 overflow-y-auto">
                                <div v-if="filteredDies.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                <button v-for="d in filteredDies" :key="d.id_sap" type="button"
                                    @mousedown.prevent="selectDiesFilter(d)"
                                    :class="['w-full text-left px-3 py-2.5 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors',
                                        selectedDiesObj?.id_sap === d.id_sap ? 'bg-orange-50 dark:bg-orange-900/20' : '']">
                                    <span class="block text-xs font-semibold text-gray-800 dark:text-gray-200">
                                        <template v-for="(part, pi) in highlightMatch(d.no_part, diesSearch)" :key="pi">
                                            <mark v-if="part.match" class="bg-yellow-200 dark:bg-yellow-700 text-gray-900 dark:text-white rounded px-0.5 not-italic">{{ part.text }}</mark>
                                            <span v-else>{{ part.text }}</span>
                                        </template>
                                    </span>
                                    <span class="text-xs text-gray-400">{{ d.nama_dies }} — {{ d.line }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button @click="filterStatus = ''; clearDiesFilter()"
                            class="text-xs text-orange-500 font-semibold hover:underline">Reset filter</button>
                    </div>
                </div>
            </div>

            <!-- Summary Chips -->
            <div class="flex gap-2 overflow-x-auto scrollbar-none pb-0.5">
                <button v-for="[v, c] in Object.entries(statusCfg)" :key="v"
                    @click="filterStatus = filterStatus === v ? '' : v"
                    :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all flex-shrink-0',
                        filterStatus === v ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:border-orange-300']">
                    <span :class="['w-1.5 h-1.5 rounded-full', c.dot]"></span>
                    {{ c.label }}
                    <span class="font-bold">{{ correctives.data.filter(x => x.status === v).length }}</span>
                </button>
            </div>

            <!-- Desktop Table -->
            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Dies</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Problem</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">PIC</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Tanggal</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">SP</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="correctives.data.length === 0">
                                <td colspan="7" class="py-16 text-center text-gray-400 text-sm">
                                    <Wrench class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                    Tidak ada data corrective
                                </td>
                            </tr>
                            <tr v-for="c in correctives.data" :key="c.id"
                                class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ c.dies?.no_part ?? c.dies_id }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ c.dies?.nama_dies }}</p>
                                    <span class="text-xs px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded font-semibold">{{ c.dies?.line }}</span>
                                </td>
                                <td class="px-4 py-3 max-w-xs">
                                    <p class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2">{{ c.problem_description }}</p>
                                    <p v-if="c.cause" class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ c.cause }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">{{ c.pic_name }}</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ fmtDate(c.report_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="c.spareparts?.length"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400 rounded-full text-xs font-bold">
                                        <Package class="w-3 h-3" /> {{ c.spareparts.length }}
                                    </span>
                                    <span v-else class="text-xs text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-block px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[c.status]?.badge ?? '']">
                                        {{ statusCfg[c.status]?.label ?? c.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button @click="openDetail(c)"
                                            class="p-1.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-200 transition-colors">
                                            <Eye class="w-3.5 h-3.5" />
                                        </button>
                                        <button v-if="c.status !== 'closed'" @click="openEdit(c)"
                                            class="p-1.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-lg hover:bg-amber-200 transition-colors">
                                            <Pencil class="w-3.5 h-3.5" />
                                        </button>
                                        <button v-if="c.status !== 'closed'" @click="openClose(c)"
                                            class="p-1.5 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 rounded-lg hover:bg-emerald-200 transition-colors">
                                            <CheckCircle2 class="w-3.5 h-3.5" />
                                        </button>
                                        <button @click="openDelete(c)"
                                            class="p-1.5 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-200 transition-colors">
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="correctives.meta?.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-500">{{ correctives.meta.from }}–{{ correctives.meta.to }} dari {{ correctives.meta.total }}</p>
                    <div class="flex gap-1">
                        <button v-for="link in correctives.links" :key="link.label"
                            @click="link.url && router.visit(link.url)"
                            :disabled="!link.url" v-html="link.label"
                            :class="['px-3 py-1.5 text-xs rounded-lg font-semibold transition-colors',
                                link.active ? 'bg-orange-500 text-white' : link.url ? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200' : 'opacity-40 cursor-default bg-gray-100 dark:bg-gray-700 text-gray-400']">
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Cards -->
            <div class="lg:hidden space-y-2.5">
                <div v-if="correctives.data.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <p class="text-gray-400 text-sm">Tidak ada data corrective</p>
                </div>
                <div v-for="c in correctives.data" :key="c.id"
                    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="p-3.5">
                        <div class="flex items-start justify-between gap-2 mb-2.5">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ c.dies?.no_part ?? c.dies_id }}</p>
                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ c.dies?.nama_dies }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[c.status]?.badge ?? '']">
                                    {{ statusCfg[c.status]?.label ?? c.status }}
                                </span>
                                <span v-if="c.dies?.line" class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded-full text-xs font-semibold">
                                    {{ c.dies.line }}
                                </span>
                            </div>
                        </div>
                        <div class="space-y-1.5 mb-3">
                            <p class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-2.5 py-1.5">
                                <span class="font-semibold text-red-600 dark:text-red-400">Problem:</span> {{ c.problem_description }}
                            </p>
                            <div class="flex items-center gap-3 text-xs text-gray-500 px-1">
                                <span class="flex items-center gap-1"><User class="w-3 h-3" /> {{ c.pic_name }}</span>
                                <span class="flex items-center gap-1"><Calendar class="w-3 h-3" /> {{ fmtDate(c.report_date) }}</span>
                                <span v-if="c.spareparts?.length"
                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400 rounded-full font-bold">
                                    <Package class="w-3 h-3" /> {{ c.spareparts.length }}
                                </span>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-2.5 border-t border-gray-100 dark:border-gray-700">
                            <button @click="openDetail(c)"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-bold hover:bg-gray-200 active:scale-95 transition-all">
                                <Eye class="w-3.5 h-3.5" /> Detail
                            </button>
                            <button v-if="c.status !== 'closed'" @click="openClose(c)"
                                class="flex items-center justify-center gap-1 py-2 px-3 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 rounded-xl text-xs font-bold hover:bg-emerald-200 active:scale-95 transition-all">
                                <CheckCircle2 class="w-3.5 h-3.5" />
                            </button>
                            <button v-if="c.status !== 'closed'" @click="openEdit(c)"
                                class="flex items-center justify-center gap-1 py-2 px-3 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-xl text-xs font-bold hover:bg-amber-200 active:scale-95 transition-all">
                                <Pencil class="w-3.5 h-3.5" />
                            </button>
                            <button @click="openDelete(c)"
                                class="flex items-center justify-center gap-1 py-2 px-3 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-xl text-xs font-bold hover:bg-red-200 active:scale-95 transition-all">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="correctives.meta?.last_page > 1" class="flex justify-center gap-1 pt-2">
                    <button v-for="link in correctives.links" :key="link.label"
                        @click="link.url && router.visit(link.url)"
                        :disabled="!link.url" v-html="link.label"
                        :class="['px-3 py-1.5 text-xs rounded-lg font-semibold',
                            link.active ? 'bg-orange-500 text-white' : link.url ? 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' : 'opacity-40 cursor-default']">
                    </button>
                </div>
            </div>
        </div>
        <!-- ═══ MODAL FORM (Add / Edit) ═══ -->
        <div v-if="showFormModal"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-2xl max-h-[95vh] flex flex-col shadow-2xl">
                <div class="flex-shrink-0">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <Wrench :class="['w-4 h-4', isEdit ? 'text-amber-500' : 'text-orange-500']" />
                            {{ isEdit ? 'Edit Laporan CM' : 'Tambah Corrective Maintenance' }}
                        </h2>
                        <button @click="closeFormModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <form @submit.prevent="submitForm" class="overflow-y-auto flex-1 px-4 sm:px-6 py-4 space-y-4">

                    <!-- Identifikasi -->
                    <div class="bg-orange-50 dark:bg-orange-900/20 rounded-xl px-4 py-3 border border-orange-100 dark:border-orange-900/40">
                        <h3 class="text-xs font-bold text-orange-700 dark:text-orange-400 uppercase mb-3 flex items-center gap-1.5">
                            <AlertCircle class="w-3.5 h-3.5" /> Identifikasi
                        </h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Dies <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                                    <input v-model="formDiesSearch" type="text" placeholder="Cari no part / nama dies / line..."
                                        autocomplete="off" @focus="formDiesOpen = true" @blur="closeFormDiesDropdown"
                                        :class="['w-full pl-8 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                            form.dies_id ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-orange-400']" />
                                    <button v-if="form.dies_id" type="button" @click="clearFormDies"
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                                        <X class="w-4 h-4" />
                                    </button>
                                    <div v-if="formDiesOpen"
                                        class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-56 overflow-y-auto">
                                        <div v-if="filteredFormDies.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                        <button v-for="d in filteredFormDies" :key="d.id_sap" type="button"
                                            @mousedown.prevent="selectFormDies(d)"
                                            :class="['w-full text-left px-3 py-2.5 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors',
                                                form.dies_id === d.id_sap ? 'bg-orange-50 dark:bg-orange-900/20' : '']">
                                            <span class="block text-xs font-semibold text-gray-800 dark:text-gray-200">
                                                <template v-for="(part, pi) in highlightMatch(d.no_part, formDiesSearch)" :key="pi">
                                                    <mark v-if="part.match" class="bg-yellow-200 dark:bg-yellow-700 text-gray-900 dark:text-white rounded px-0.5 not-italic">{{ part.text }}</mark>
                                                    <span v-else>{{ part.text }}</span>
                                                </template>
                                            </span>
                                            <span class="text-xs text-gray-400">{{ d.nama_dies }} — {{ d.line }}</span>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="selectedFormDiesObj" class="mt-1.5 flex items-center gap-2 text-xs text-gray-500">
                                    <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 rounded font-semibold">{{ selectedFormDiesObj.line }}</span>
                                    <span>Current stroke: <span class="font-bold text-gray-700 dark:text-gray-300">{{ selectedFormDiesObj.current_stroke.toLocaleString() }}</span></span>
                                </div>
                                <p v-if="form.errors.dies_id" class="mt-1 text-xs text-red-500">{{ form.errors.dies_id }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Tanggal <span class="text-red-500">*</span></label>
                                    <input v-model="form.report_date" type="date"
                                        class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-orange-400 focus:outline-none" />
                                    <p v-if="form.errors.report_date" class="mt-1 text-xs text-red-500">{{ form.errors.report_date }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Stroke saat CM <span class="text-red-500">*</span></label>
                                    <input v-model="form.stroke_at_maintenance" type="number" min="0"
                                        class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-orange-400 focus:outline-none" />
                                    <p v-if="form.errors.stroke_at_maintenance" class="mt-1 text-xs text-red-500">{{ form.errors.stroke_at_maintenance }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Kerusakan -->
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-xl px-4 py-3 border border-red-100 dark:border-red-900/40">
                        <h3 class="text-xs font-bold text-red-700 dark:text-red-400 uppercase mb-3 flex items-center gap-1.5">
                            <AlertCircle class="w-3.5 h-3.5" /> Detail Kerusakan & Tindakan
                        </h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Deskripsi Problem <span class="text-red-500">*</span></label>
                                <textarea v-model="form.problem_description" rows="3" placeholder="Jelaskan masalah yang terjadi..."
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-red-400 focus:outline-none resize-none"></textarea>
                                <p v-if="form.errors.problem_description" class="mt-1 text-xs text-red-500">{{ form.errors.problem_description }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Cause / Penyebab</label>
                                <textarea v-model="form.cause" rows="2" placeholder="Analisa penyebab (opsional)"
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-red-400 focus:outline-none resize-none"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Tindakan Perbaikan <span class="text-red-500">*</span></label>
                                <textarea v-model="form.repair_action" rows="3" placeholder="Tindakan yang dilakukan..."
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-red-400 focus:outline-none resize-none"></textarea>
                                <p v-if="form.errors.repair_action" class="mt-1 text-xs text-red-500">{{ form.errors.repair_action }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 px-4 py-3">
                        <h3 class="text-xs font-bold text-gray-500 uppercase mb-3">Status</h3>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="s in statusList" :key="s.v" type="button" @click="form.status = s.v"
                                :class="['flex flex-col items-start p-2.5 rounded-xl border-2 text-left transition-all',
                                    form.status === s.v
                                        ? s.v === 'open' ? 'border-red-400 bg-red-50 dark:bg-red-900/20'
                                          : s.v === 'in_progress' ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/20'
                                          : 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300']">
                                <span :class="['text-xs font-bold',
                                    form.status === s.v
                                        ? s.v === 'open' ? 'text-red-700 dark:text-red-400'
                                          : s.v === 'in_progress' ? 'text-amber-700 dark:text-amber-400'
                                          : 'text-emerald-700 dark:text-emerald-400'
                                        : 'text-gray-500']">{{ s.l }}</span>
                                <span class="text-xs text-gray-400 mt-0.5 leading-tight">{{ s.hint }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Foto -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 px-4 py-3">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-bold text-gray-500 uppercase flex items-center gap-1.5">
                                <Camera class="w-3.5 h-3.5" /> Dokumentasi Foto
                            </h3>
                            <span class="text-xs text-gray-400">{{ (existingPhotos.length - photosToDelete.length) + newPhotoPreview.length }} foto</span>
                        </div>
                        <div v-if="existingPhotos.length" class="grid grid-cols-4 gap-2 mb-2">
                            <div v-for="ph in existingPhotos" :key="ph"
                                :class="['relative rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-700 aspect-square',
                                    photosToDelete.includes(ph) ? 'opacity-40 ring-2 ring-red-400' : '']">
                                <img :src="'/storage/' + ph" class="w-full h-full object-cover cursor-pointer" @click="lightboxSrc = '/storage/' + ph" />
                                <button type="button" @click="markDeletePhoto(ph)"
                                    :class="['absolute top-1 right-1 p-1 rounded-full text-white transition-colors',
                                        photosToDelete.includes(ph) ? 'bg-gray-500' : 'bg-red-500 hover:bg-red-600']">
                                    <X class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                        <div v-if="newPhotoPreview.length" class="grid grid-cols-4 gap-2 mb-2">
                            <div v-for="(p, idx) in newPhotoPreview" :key="idx"
                                class="relative rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-700 aspect-square ring-2 ring-blue-400">
                                <img :src="p.url" class="w-full h-full object-cover" />
                                <button type="button" @click="removeNewPhoto(idx)"
                                    class="absolute top-1 right-1 p-1 bg-red-500 hover:bg-red-600 rounded-full text-white">
                                    <X class="w-3 h-3" />
                                </button>
                                <span class="absolute bottom-1 left-1 bg-blue-500 text-white text-xs px-1 rounded font-bold">Baru</span>
                            </div>
                        </div>
                        <label :class="['flex flex-col items-center justify-center gap-2 p-4 border-2 border-dashed rounded-xl transition-colors',
                            isCompressing ? 'border-orange-400 bg-orange-50/50 cursor-wait' : 'border-gray-200 dark:border-gray-600 cursor-pointer hover:border-orange-400 hover:bg-orange-50/50 dark:hover:bg-orange-900/10']">
                            <Loader2 v-if="isCompressing" class="w-5 h-5 text-orange-400 animate-spin" />
                            <Upload v-else class="w-5 h-5 text-gray-400" />
                            <span class="text-xs text-gray-500 font-medium">{{ isCompressing ? 'Mengompres foto...' : 'Klik untuk upload foto' }}</span>
                            <input type="file" multiple accept="image/*" class="hidden" :disabled="isCompressing" @change="handleFileInput" />
                        </label>
                        <p v-if="photosToDelete.length" class="mt-2 text-xs text-red-500 flex items-center gap-1">
                            <AlertCircle class="w-3.5 h-3.5" /> {{ photosToDelete.length }} foto akan dihapus saat disimpan
                        </p>
                    </div>

                    <!-- Sparepart -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 px-4 py-3">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-bold text-gray-500 uppercase flex items-center gap-1.5">
                                <Package class="w-3.5 h-3.5 text-indigo-500" /> Sparepart Digunakan
                            </h3>
                            <button type="button" @click="addSp"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-xs bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400 rounded-lg font-semibold hover:bg-indigo-200 active:scale-95 transition-all">
                                <Plus class="w-3 h-3" /> Tambah
                            </button>
                        </div>
                        <div v-if="isEdit && selectedCm?.spareparts?.length" class="mb-3 space-y-1.5">
                            <p class="text-xs text-gray-400 mb-1">Sudah tercatat:</p>
                            <div v-for="h in selectedCm.spareparts" :key="h.id"
                                class="flex justify-between items-center px-3 py-2 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg text-xs">
                                <span class="font-semibold text-indigo-700 dark:text-indigo-300">{{ h.sparepart?.sparepart_name ?? '-' }}</span>
                                <span class="text-gray-500">{{ h.quantity }} {{ h.sparepart?.unit }}</span>
                            </div>
                        </div>
                        <div v-if="form.spareparts.length === 0 && !(isEdit && selectedCm?.spareparts?.length)" class="py-4 text-center text-xs text-gray-400">
                            Belum ada sparepart ditambahkan
                        </div>
                        <div v-for="(sp, i) in form.spareparts" :key="i" class="mb-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl space-y-2.5">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                                    <input v-model="spSearch[i]" type="text" placeholder="Cari sparepart..." autocomplete="off"
                                        @focus="openSpDropdown(i)" @blur="closeSpDropdown(i)"
                                        :class="['w-full pl-7 pr-7 py-2.5 border rounded-xl text-xs focus:outline-none transition-colors dark:bg-gray-700',
                                            sp.sparepart_id ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-indigo-400']" />
                                    <button v-if="sp.sparepart_id" type="button" @click="clearSpSelection(i)"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors p-0.5">
                                        <X class="w-3 h-3" />
                                    </button>
                                    <div v-if="spOpen[i]"
                                        class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                        <div v-if="filteredSp(i).length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                        <button v-for="s in filteredSp(i)" :key="s.id" type="button"
                                            @mousedown.prevent="selectSp(i, s)"
                                            :class="['w-full text-left px-3 py-2.5 text-xs hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors flex items-center justify-between gap-2',
                                                sp.sparepart_id === s.id ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-gray-700 dark:text-gray-300']">
                                            <span>
                                                <template v-for="(part, pi) in highlightMatch(s.sparepart_name, spSearch[i] ?? '')" :key="pi">
                                                    <mark v-if="part.match" class="bg-yellow-200 dark:bg-yellow-700 text-gray-900 dark:text-white rounded px-0.5 not-italic">{{ part.text }}</mark>
                                                    <span v-else>{{ part.text }}</span>
                                                </template>
                                            </span>
                                            <span class="text-gray-400 whitespace-nowrap shrink-0">{{ s.stok }} {{ s.unit }}</span>
                                        </button>
                                    </div>
                                </div>
                                <input v-model="sp.quantity" type="number" inputmode="numeric" min="1" placeholder="Qty"
                                    class="w-16 px-2 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none text-center" />
                                <button type="button" @click="removeSp(i)"
                                    class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors active:scale-95">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                            <input v-model="sp.notes" type="text" placeholder="Keterangan (opsional)"
                                class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none transition-colors" />
                            <p v-if="sp.sparepart_id && selectedSpItem(sp.sparepart_id) && parseInt(sp.quantity) > selectedSpItem(sp.sparepart_id)!.stok"
                                class="text-xs text-red-500 flex items-center gap-1">
                                <AlertCircle class="w-3 h-3" />
                                Stok tidak cukup (tersedia: {{ selectedSpItem(sp.sparepart_id)!.stok }})
                            </p>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="sticky bottom-0 bg-white dark:bg-gray-800 pt-3 pb-4 border-t border-gray-100 dark:border-gray-700 -mx-4 sm:-mx-6 px-4 sm:px-6">
                        <div class="flex gap-3">
                            <button type="button" @click="closeFormModal"
                                class="px-5 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                                Batal
                            </button>
                            <button type="submit"
                                :class="['flex-1 py-3 rounded-xl font-bold text-sm text-white active:scale-95 transition-all disabled:opacity-50',
                                    isEdit ? 'bg-amber-500 hover:bg-amber-600' : 'bg-orange-500 hover:bg-orange-600']">
                                {{ isEdit ? 'Simpan Perubahan' : 'Buat Laporan CM' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ═══ MODAL DETAIL ═══ -->
        <div v-if="showDetailModal && selectedCm"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-lg max-h-[92vh] overflow-y-auto shadow-2xl">
                <div class="sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <Wrench class="w-4 h-4 text-orange-500" /> Detail CM
                        </h2>
                        <button @click="showDetailModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <div class="p-4 sm:p-5 space-y-3.5">
                    <!-- Hero -->
                    <div class="bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-white font-bold text-sm mt-0.5">{{ selectedCm.dies?.no_part ?? selectedCm.dies_id }}</p>
                                <p class="text-white/70 text-xs mt-0.5 line-clamp-1">{{ selectedCm.dies?.nama_dies }}</p>
                            </div>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-bold flex-shrink-0', statusCfg[selectedCm.status]?.badge ?? '']">
                                {{ statusCfg[selectedCm.status]?.label ?? selectedCm.status }}
                            </span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-3">
                            <div class="bg-white/10 rounded-xl p-2 text-center">
                                <User class="w-3 h-3 text-white/60 mx-auto mb-0.5" />
                                <p class="text-white text-xs font-bold truncate">{{ selectedCm.pic_name }}</p>
                                <p class="text-white/50 text-xs">PIC</p>
                            </div>
                            <div class="bg-white/10 rounded-xl p-2 text-center">
                                <Calendar class="w-3 h-3 text-white/60 mx-auto mb-0.5" />
                                <p class="text-white text-xs font-bold">{{ fmtDate(selectedCm.report_date) }}</p>
                                <p class="text-white/50 text-xs">Tanggal</p>
                            </div>
                            <div class="bg-white/10 rounded-xl p-2 text-center">
                                <Wrench class="w-3 h-3 text-white/60 mx-auto mb-0.5" />
                                <p class="text-white text-xs font-bold">{{ selectedCm.stroke_at_maintenance.toLocaleString() }}</p>
                                <p class="text-white/50 text-xs">Stroke</p>
                            </div>
                        </div>
                    </div>
                    <!-- Problem -->
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3.5 border border-red-100 dark:border-red-800">
                        <p class="text-xs font-bold text-red-500 uppercase mb-1.5">Problem</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedCm.problem_description }}</p>
                    </div>
                    <div v-if="selectedCm.cause" class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-3.5 border border-amber-100 dark:border-amber-800">
                        <p class="text-xs font-bold text-amber-600 uppercase mb-1.5">Cause</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedCm.cause }}</p>
                    </div>
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-3.5 border border-emerald-100 dark:border-emerald-800">
                        <p class="text-xs font-bold text-emerald-600 uppercase mb-1.5">Tindakan Perbaikan</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedCm.repair_action }}</p>
                    </div>
                    <!-- Foto -->
                    <div v-if="selectedCm.photos?.length">
                        <p class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-1.5">
                            <Camera class="w-3.5 h-3.5" /> Foto ({{ selectedCm.photos.length }})
                        </p>
                        <div class="grid grid-cols-3 gap-2">
                            <div v-for="(ph, idx) in selectedCm.photos" :key="idx"
                                @click="lightboxSrc = '/storage/' + ph"
                                class="aspect-square rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-700 cursor-pointer group">
                                <img :src="'/storage/' + ph" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                            </div>
                        </div>
                    </div>
                    <!-- Sparepart -->
                    <div v-if="selectedCm.spareparts?.length">
                        <p class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-1.5">
                            <Package class="w-3.5 h-3.5 text-indigo-500" /> Sparepart Digunakan
                        </p>
                        <div class="space-y-1.5">
                            <div v-for="h in selectedCm.spareparts" :key="h.id"
                                class="flex justify-between items-center px-3 py-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-xl text-xs">
                                <span class="font-semibold text-gray-800 dark:text-white">{{ h.sparepart?.sparepart_name ?? '-' }}</span>
                                <span class="text-gray-500">{{ h.quantity }} {{ h.sparepart?.unit }}</span>
                            </div>
                        </div>
                    </div>
                    <!-- History -->
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3.5 space-y-2 text-xs">
                        <p class="font-bold text-gray-400 uppercase mb-2">History</p>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400">Dibuat oleh</span>
                            <span class="font-semibold text-gray-800 dark:text-white">{{ selectedCm.created_by?.name ?? selectedCm.pic_name }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400">Tanggal dibuat</span>
                            <span class="font-semibold text-gray-800 dark:text-white">{{ fmtDate(selectedCm.report_date) }}</span>
                        </div>
                        <template v-if="selectedCm.status === 'closed'">
                            <div class="border-t border-gray-200 dark:border-gray-600 pt-2 mt-1"></div>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-400">Ditutup oleh</span>
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ selectedCm.closed_by?.name ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-400">Tanggal ditutup</span>
                                <span class="font-semibold text-gray-800 dark:text-white">{{ fmtDatetime(selectedCm.closed_at) }}</span>
                            </div>
                            <div v-if="selectedCm.action" class="pt-1">
                                <span class="text-gray-400 block mb-1">Kesimpulan</span>
                                <p class="text-gray-700 dark:text-gray-300 leading-relaxed bg-emerald-50 dark:bg-emerald-900/20 rounded-lg px-2.5 py-2">{{ selectedCm.action }}</p>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="sticky bottom-0 bg-white dark:bg-gray-800 p-4 border-t border-gray-100 dark:border-gray-700 flex gap-3">
                    <button @click="showDetailModal = false"
                        class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-200 active:scale-95 transition-all">
                        Tutup
                    </button>
                    <button v-if="selectedCm.status !== 'closed'" @click="showDetailModal = false; openEdit(selectedCm)"
                        class="flex-1 py-3 bg-amber-500 text-white rounded-xl text-sm font-bold hover:bg-amber-600 active:scale-95 transition-all flex items-center justify-center gap-2">
                        <Pencil class="w-3.5 h-3.5" /> Edit
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══ MODAL CLOSE ═══ -->
        <div v-if="showCloseModal && selectedCm"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <CheckCircle2 class="w-5 h-5 text-emerald-600" /> Tutup Laporan CM
                    </h2>
                    <button @click="showCloseModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <form @submit.prevent="submitClose" class="p-4 sm:p-5 space-y-4">
                    <div class="bg-orange-50 dark:bg-orange-900/20 rounded-xl p-3.5 border border-orange-100 dark:border-orange-800">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedCm.dies?.no_part ?? selectedCm.dies_id }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ selectedCm.dies?.nama_dies }}</p>
                        <p class="text-xs text-gray-400 mt-1">Dilaporkan: {{ fmtDate(selectedCm.report_date) }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">
                            Kesimpulan / Action <span class="text-gray-400 font-normal text-xs ml-1">(opsional)</span>
                        </label>
                        <textarea v-model="closeForm.action" rows="4" placeholder="Tuliskan kesimpulan atau action penutupan..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-emerald-500 focus:outline-none resize-none transition-colors"></textarea>
                    </div>
                    <div class="flex gap-3 pb-safe">
                        <button type="button" @click="showCloseModal = false"
                            class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button type="submit" :disabled="closeForm.processing"
                            class="flex-1 py-3 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 disabled:opacity-50 font-bold text-sm active:scale-95 transition-all">
                            {{ closeForm.processing ? 'Menyimpan...' : 'Tutup Laporan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ═══ MODAL DELETE ═══ -->
        <div v-if="showDelModal && selectedCm"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="p-5 text-center">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-3">
                        <AlertTriangle class="w-6 h-6 text-red-600" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">Hapus Laporan CM?</h3>
                    <p class="text-sm text-gray-500 mb-1">{{ selectedCm.dies?.no_part ?? selectedCm.dies_id }}</p>
                    <p class="text-xs text-gray-400 mb-5">Foto dan sparepart terkait akan ikut dihapus.</p>
                    <div class="flex gap-3">
                        <button @click="showDelModal = false"
                            class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button @click="submitDelete"
                            class="flex-1 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 font-bold text-sm active:scale-95 transition-all">
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="lightboxSrc" class="fixed inset-0 z-[60] bg-black/90 flex items-center justify-center p-4"
                @click="lightboxSrc = null">
                <button class="absolute top-4 right-4 p-2 bg-white/10 hover:bg-white/20 rounded-xl text-white">
                    <X class="w-5 h-5" />
                </button>
                <img :src="lightboxSrc" class="max-w-full max-h-full rounded-2xl object-contain" @click.stop />
            </div>
        </Teleport>
    </AppLayout>
</template>
