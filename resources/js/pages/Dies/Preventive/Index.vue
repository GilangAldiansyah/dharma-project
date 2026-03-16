<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    CheckCircle2, Search, Filter, Plus, Eye, Pencil, Trash2,
    AlertTriangle, X, Download, User, Calendar, Activity,
    Upload, Camera, Package, AlertCircle, Loader2, CalendarCheck
} from 'lucide-vue-next';
import * as XLSX from 'xlsx';

interface DiesMini { id_sap: string; no_part: string; nama_dies: string; line: string; current_stroke: number; std_stroke: number }
interface Sparepart { id: number; sparepart_code: string; sparepart_name: string; unit: string; stok: number }
interface HistorySp { id: number; quantity: number; notes: string | null; sparepart: Sparepart | null; created_at: string }
interface Preventive {
    id: number; report_no: string; dies_id: string; pic_name: string;
    report_date: string; stroke_at_maintenance: number;
    repair_process: string | null; repair_action: string | null;
    photos: string[] | null; status: string; completed_at: string | null;
    scheduled_date: string | null;
    dies: DiesMini | null;
    spareparts: HistorySp[];
    created_by: { id: number; name: string } | null;
}
interface Props {
    preventives: { data: Preventive[]; links: any[]; meta: any };
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
const isEdit          = ref(false);
const selectedPm      = ref<Preventive | null>(null);
const lightboxSrc     = ref<string | null>(null);

const statusCfg: Record<string, { label: string; badge: string; dot: string }> = {
    scheduled:   { label: 'Scheduled',   badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',           dot: 'bg-blue-500' },
    pending:     { label: 'Pending',     badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',               dot: 'bg-gray-400' },
    in_progress: { label: 'In Progress', badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',       dot: 'bg-amber-400' },
    completed:   { label: 'Completed',   badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400', dot: 'bg-emerald-500' },
};

const statusList = [
    { v: 'scheduled',   l: 'Scheduled',   hint: 'Sudah dijadwalkan dari dashboard' },
    { v: 'pending',     l: 'Pending',      hint: 'Belum mulai dikerjakan' },
    { v: 'in_progress', l: 'In Progress',  hint: 'Sedang dalam proses' },
    { v: 'completed',   l: 'Completed',    hint: 'PM selesai dilakukan' },
];

const processSuggestions = ['DRAW 1', 'DRAW 2', 'TRIM', 'RESTRIKE', 'FLANGE', 'PIERCE', 'BEND', 'FORM', 'BLANK', 'EMBOSS'];

const activeFilterCount = computed(() => [filterStatus.value, filterDies.value].filter(Boolean).length);

let debounce: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => navigate(), 400); });
watch([filterStatus, filterDies], () => navigate());

const navigate = () => router.get('/dies/preventive',
    { search: search.value, status: filterStatus.value, dies_id: filterDies.value },
    { preserveState: true, preserveScroll: true, replace: true });

const fmtDate = (d: string | null) =>
    !d ? '-' : new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

const pct = (p: Preventive) => {
    const std = p.dies?.std_stroke ?? 0;
    if (!std) return 0;
    return Math.min(Math.round(p.stroke_at_maintenance / std * 100), 100);
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
    repair_process:        '',
    repair_action:         '',
    status:                'pending' as string,
    scheduled_date:        '',
    photos:                [] as File[],
    spareparts:            [] as { sparepart_id: number | null; quantity: string; notes: string }[],
});

// Form dies searchable
const formDiesSearch      = ref('');
const formDiesOpen        = ref(false);
const selectedFormDiesObj = computed(() => props.diesList.find(d => d.id_sap === form.dies_id) ?? null);
const formPct             = computed(() => {
    if (!selectedFormDiesObj.value?.std_stroke) return 0;
    return Math.min(Math.round(form.stroke_at_maintenance / selectedFormDiesObj.value.std_stroke * 100), 100);
});
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
    selectedPm.value = null;
    form.dies_id = '';
    form.report_date = new Date().toISOString().slice(0, 10);
    form.stroke_at_maintenance = 0;
    form.repair_process = '';
    form.repair_action = '';
    form.status = 'pending';
    form.scheduled_date = '';
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

const openEdit = (p: Preventive) => {
    isEdit.value = true;
    selectedPm.value = p;
    form.dies_id = p.dies_id;
    form.report_date = p.report_date?.slice(0, 10) ?? new Date().toISOString().slice(0, 10);
    form.stroke_at_maintenance = p.stroke_at_maintenance;
    form.repair_process = p.repair_process ?? '';
    form.repair_action = p.repair_action ?? '';
    form.status = p.status;
    form.scheduled_date = p.scheduled_date?.slice(0, 10) ?? '';
    form.photos = [];
    form.spareparts = [];
    form.clearErrors();
    formDiesSearch.value = p.dies?.no_part ?? p.dies_id;
    formDiesOpen.value = false;
    spSearch.value = [];
    spOpen.value = [];
    newPhotoPreview.value = [];
    existingPhotos.value = p.photos ?? [];
    photosToDelete.value = [];
    showFormModal.value = true;
};

const openDetail = (p: Preventive) => { selectedPm.value = p; showDetailModal.value = true; };

const closeFormModal = () => {
    showFormModal.value = false;
    newPhotoPreview.value.forEach(p => URL.revokeObjectURL(p.url));
    newPhotoPreview.value = [];
};

const submitForm = () => {
    if (isEdit.value && selectedPm.value) {
        if (photosToDelete.value.length) {
            photosToDelete.value.forEach(photo => {
                router.post(`/dies/preventive/${selectedPm.value!.id}/delete-photo`,
                    { photo }, { preserveState: true });
            });
        }
        router.post(`/dies/preventive/${selectedPm.value.id}`, {
            _method:               'PUT',
            dies_id:               form.dies_id,
            report_date:           form.report_date,
            stroke_at_maintenance: form.stroke_at_maintenance,
            repair_process:        form.repair_process,
            repair_action:         form.repair_action,
            status:                form.status,
            scheduled_date:        form.status === 'scheduled' ? form.scheduled_date : null,
            photos:                form.photos,
            spareparts:            form.spareparts,
        }, {
            forceFormData: true,
            onSuccess: () => { closeFormModal(); },
        });
    } else {
        router.post('/dies/preventive', {
            dies_id:               form.dies_id,
            report_date:           form.report_date,
            stroke_at_maintenance: form.stroke_at_maintenance,
            repair_process:        form.repair_process,
            repair_action:         form.repair_action,
            status:                form.status,
            scheduled_date:        form.status === 'scheduled' ? form.scheduled_date : null,
            photos:                form.photos,
            spareparts:            form.spareparts,
        }, {
            forceFormData: true,
            onSuccess: () => { closeFormModal(); },
        });
    }
};

// ── Delete ──
const openDelete = (p: Preventive) => { selectedPm.value = p; showDelModal.value = true; };
const submitDelete = () => {
    if (!selectedPm.value) return;
    router.delete(`/dies/preventive/${selectedPm.value.id}`, {
        preserveScroll: true,
        only: ['preventives'],
        onSuccess: () => { showDelModal.value = false; selectedPm.value = null; },
    });
};

// ── Export ──
const exportExcel = () => {
    const rows = [
        ['No Part', 'Nama Dies', 'Line', 'PIC', 'Tanggal', 'Scheduled Date', 'Stroke MTC', '% STD', 'Proses', 'Tindakan', 'Status', 'Completed At'],
        ...props.preventives.data.map(p => [
            p.dies?.no_part ?? p.dies_id,
            p.dies?.nama_dies ?? '',
            p.dies?.line ?? '',
            p.pic_name,
            p.report_date,
            p.scheduled_date ?? '',
            p.stroke_at_maintenance,
            `${pct(p)}%`,
            p.repair_process ?? '',
            p.repair_action ?? '',
            statusCfg[p.status]?.label ?? p.status,
            p.completed_at ? fmtDate(p.completed_at) : '',
        ]),
    ];
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [{ wch: 16 }, { wch: 30 }, { wch: 10 }, { wch: 16 }, { wch: 12 }, { wch: 12 },
        { wch: 14 }, { wch: 8 }, { wch: 20 }, { wch: 30 }, { wch: 14 }, { wch: 14 }];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Preventive');
    XLSX.writeFile(wb, `Dies_PM_${new Date().toISOString().slice(0, 10)}.xlsx`);
};
</script>
<template>
    <Head title="Preventive Maintenance Dies" />
    <AppLayout :breadcrumbs="[
        { title: 'Dies', href: '/dies' },
        { title: 'Preventive', href: '/dies/preventive' },
    ]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <!-- Header -->
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-blue-600 rounded-xl flex-shrink-0">
                            <CheckCircle2 class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
                        </span>
                        Preventive Maintenance
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">{{ preventives.meta?.total ?? 0 }} laporan</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="exportExcel"
                        class="flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold active:scale-95 transition-all">
                        <Download class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Export</span>
                    </button>
                    <button @click="openAdd"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-semibold active:scale-95 transition-all shadow-sm">
                        <Plus class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Tambah PM</span>
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
                            <button v-for="s in [{ v: '', l: 'Semua' }, ...Object.entries(statusCfg).map(([v, c]) => ({ v, l: c.label }))]" :key="s.v"
                                @click="filterStatus = s.v"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                                    filterStatus === s.v ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
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
                                    selectedDiesObj ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-blue-400']" />
                            <button v-if="selectedDiesObj" type="button" @click="clearDiesFilter"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                                <X class="w-4 h-4" />
                            </button>
                            <div v-if="diesOpen"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-56 overflow-y-auto">
                                <div v-if="filteredDies.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                <button v-for="d in filteredDies" :key="d.id_sap" type="button"
                                    @mousedown.prevent="selectDiesFilter(d)"
                                    :class="['w-full text-left px-3 py-2.5 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors',
                                        selectedDiesObj?.id_sap === d.id_sap ? 'bg-blue-50 dark:bg-blue-900/20' : '']">
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
                            class="text-xs text-blue-500 font-semibold hover:underline">Reset filter</button>
                    </div>
                </div>
            </div>

            <!-- Summary Chips -->
            <div class="flex gap-2 overflow-x-auto scrollbar-none pb-0.5">
                <button v-for="[v, c] in Object.entries(statusCfg)" :key="v"
                    @click="filterStatus = filterStatus === v ? '' : v"
                    :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all flex-shrink-0',
                        filterStatus === v ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:border-blue-300']">
                    <span :class="['w-1.5 h-1.5 rounded-full', c.dot]"></span>
                    {{ c.label }}
                    <span class="font-bold">{{ preventives.data.filter(x => x.status === v).length }}</span>
                </button>
            </div>

            <!-- Desktop Table -->
            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Dies</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Tindakan</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">PIC</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Tanggal</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Stroke / %</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">SP</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="preventives.data.length === 0">
                                <td colspan="8" class="py-16 text-center text-gray-400 text-sm">
                                    <CheckCircle2 class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                    Tidak ada data preventive
                                </td>
                            </tr>
                            <tr v-for="p in preventives.data" :key="p.id"
                                :class="['hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors',
                                    p.status === 'scheduled' ? 'bg-blue-50/30 dark:bg-blue-900/10' : '']">
                                <td class="px-4 py-3">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ p.dies?.no_part ?? p.dies_id }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ p.dies?.nama_dies }}</p>
                                    <span v-if="p.dies?.line" class="text-xs px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded font-semibold">{{ p.dies.line }}</span>
                                </td>
                                <td class="px-4 py-3 max-w-xs">
                                    <div v-if="p.status === 'scheduled'" class="flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 font-semibold mb-0.5">
                                        <CalendarCheck class="w-3 h-3" /> Terjadwal: {{ fmtDate(p.scheduled_date) }}
                                    </div>
                                    <p v-if="p.repair_process" class="text-xs font-bold text-blue-600 dark:text-blue-400">{{ p.repair_process }}</p>
                                    <p v-if="p.repair_action" class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ p.repair_action }}</p>
                                    <span v-if="!p.repair_process && !p.repair_action && p.status !== 'scheduled'" class="text-xs text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">{{ p.pic_name }}</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ fmtDate(p.report_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <p class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ p.stroke_at_maintenance.toLocaleString() }}</p>
                                    <p class="text-xs" :class="pct(p) >= 100 ? 'text-red-500' : pct(p) >= 85 ? 'text-amber-500' : 'text-emerald-500'">{{ pct(p) }}%</p>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="p.spareparts?.length"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400 rounded-full text-xs font-bold">
                                        <Package class="w-3 h-3" /> {{ p.spareparts.length }}
                                    </span>
                                    <span v-else class="text-xs text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-block px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[p.status]?.badge ?? '']">
                                        {{ statusCfg[p.status]?.label ?? p.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button @click="openDetail(p)"
                                            class="p-1.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-200 transition-colors">
                                            <Eye class="w-3.5 h-3.5" />
                                        </button>
                                        <button @click="openEdit(p)"
                                            class="p-1.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-lg hover:bg-amber-200 transition-colors">
                                            <Pencil class="w-3.5 h-3.5" />
                                        </button>
                                        <button @click="openDelete(p)"
                                            class="p-1.5 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-200 transition-colors">
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="preventives.meta?.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-500">{{ preventives.meta.from }}–{{ preventives.meta.to }} dari {{ preventives.meta.total }}</p>
                    <div class="flex gap-1">
                        <button v-for="link in preventives.links" :key="link.label"
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
                <div v-if="preventives.data.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <p class="text-gray-400 text-sm">Tidak ada data preventive</p>
                </div>
                <div v-for="p in preventives.data" :key="p.id"
                    :class="['rounded-2xl border shadow-sm',
                        p.status === 'scheduled'
                            ? 'bg-blue-50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-800'
                            : 'bg-white dark:bg-gray-800 border-gray-100 dark:border-gray-700']">
                    <div class="p-3.5">
                        <div class="flex items-start justify-between gap-2 mb-2.5">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ p.dies?.no_part ?? p.dies_id }}</p>
                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ p.dies?.nama_dies }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[p.status]?.badge ?? '']">
                                    {{ statusCfg[p.status]?.label ?? p.status }}
                                </span>
                                <span v-if="p.dies?.line" class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded-full text-xs font-semibold">{{ p.dies.line }}</span>
                            </div>
                        </div>

                        <!-- Scheduled info -->
                        <div v-if="p.status === 'scheduled'"
                            class="flex items-center gap-1.5 text-xs text-blue-600 dark:text-blue-400 font-semibold bg-blue-100 dark:bg-blue-900/30 rounded-lg px-2.5 py-1.5 mb-2">
                            <CalendarCheck class="w-3.5 h-3.5" />
                            Dijadwalkan: {{ fmtDate(p.scheduled_date) }}
                        </div>

                        <div class="space-y-1.5 mb-3">
                            <div v-if="p.repair_process" class="text-xs bg-blue-50 dark:bg-blue-900/20 rounded-lg px-2.5 py-1.5">
                                <span class="font-semibold text-blue-600 dark:text-blue-400">Proses:</span> {{ p.repair_process }}
                            </div>
                            <p v-if="p.repair_action" class="text-xs text-gray-500 line-clamp-2 px-1">{{ p.repair_action }}</p>
                            <div class="flex items-center gap-3 text-xs text-gray-500 px-1">
                                <span class="flex items-center gap-1"><User class="w-3 h-3" /> {{ p.pic_name }}</span>
                                <span class="flex items-center gap-1"><Calendar class="w-3 h-3" /> {{ fmtDate(p.report_date) }}</span>
                                <span v-if="p.spareparts?.length"
                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400 rounded-full font-bold">
                                    <Package class="w-3 h-3" /> {{ p.spareparts.length }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 px-1">
                                <span class="text-xs text-gray-500">{{ p.stroke_at_maintenance.toLocaleString() }}</span>
                                <div class="flex-1 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div :class="['h-full rounded-full', pct(p) >= 100 ? 'bg-red-500' : pct(p) >= 85 ? 'bg-amber-400' : 'bg-emerald-400']"
                                        :style="{ width: `${pct(p)}%` }"></div>
                                </div>
                                <span :class="['text-xs font-bold', pct(p) >= 100 ? 'text-red-500' : pct(p) >= 85 ? 'text-amber-500' : 'text-emerald-500']">{{ pct(p) }}%</span>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-2.5 border-t border-gray-100 dark:border-gray-700">
                            <button @click="openDetail(p)"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-bold hover:bg-gray-200 active:scale-95 transition-all">
                                <Eye class="w-3.5 h-3.5" /> Detail
                            </button>
                            <button @click="openEdit(p)"
                                class="flex items-center justify-center gap-1 py-2 px-3 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-xl text-xs font-bold hover:bg-amber-200 active:scale-95 transition-all">
                                <Pencil class="w-3.5 h-3.5" />
                            </button>
                            <button @click="openDelete(p)"
                                class="flex items-center justify-center gap-1 py-2 px-3 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-xl text-xs font-bold hover:bg-red-200 active:scale-95 transition-all">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="preventives.meta?.last_page > 1" class="flex justify-center gap-1 pt-2">
                    <button v-for="link in preventives.links" :key="link.label"
                        @click="link.url && router.visit(link.url)"
                        :disabled="!link.url" v-html="link.label"
                        :class="['px-3 py-1.5 text-xs rounded-lg font-semibold',
                            link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' : 'opacity-40 cursor-default']">
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
                            <CheckCircle2 :class="['w-4 h-4', isEdit ? 'text-amber-500' : 'text-blue-600']" />
                            {{ isEdit ? 'Edit Laporan PM' : 'Tambah Preventive Maintenance' }}
                        </h2>
                        <button @click="closeFormModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <form @submit.prevent="submitForm" class="overflow-y-auto flex-1 px-4 sm:px-6 py-4 space-y-4">

                    <!-- Identifikasi -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl px-4 py-3 border border-blue-100 dark:border-blue-900/40">
                        <h3 class="text-xs font-bold text-blue-700 dark:text-blue-400 uppercase mb-3 flex items-center gap-1.5">
                            <Activity class="w-3.5 h-3.5" /> Identifikasi
                        </h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Dies <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                                    <input v-model="formDiesSearch" type="text" placeholder="Cari no part / nama dies / line..."
                                        autocomplete="off" @focus="formDiesOpen = true" @blur="closeFormDiesDropdown"
                                        :class="['w-full pl-8 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                            form.dies_id ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-blue-400']" />
                                    <button v-if="form.dies_id" type="button" @click="clearFormDies"
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                                        <X class="w-4 h-4" />
                                    </button>
                                    <div v-if="formDiesOpen"
                                        class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-56 overflow-y-auto">
                                        <div v-if="filteredFormDies.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                        <button v-for="d in filteredFormDies" :key="d.id_sap" type="button"
                                            @mousedown.prevent="selectFormDies(d)"
                                            :class="['w-full text-left px-3 py-2.5 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors',
                                                form.dies_id === d.id_sap ? 'bg-blue-50 dark:bg-blue-900/20' : '']">
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
                                <!-- Dies info card -->
                                <div v-if="selectedFormDiesObj" class="mt-2 grid grid-cols-3 gap-2 text-center">
                                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-2">
                                        <p class="text-xs text-blue-500 font-semibold">Current</p>
                                        <p class="text-sm font-black text-blue-700 dark:text-blue-300">{{ selectedFormDiesObj.current_stroke.toLocaleString() }}</p>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-2">
                                        <p class="text-xs text-gray-500 font-semibold">STD</p>
                                        <p class="text-sm font-black text-gray-700 dark:text-gray-300">{{ selectedFormDiesObj.std_stroke.toLocaleString() }}</p>
                                    </div>
                                    <div :class="['rounded-xl p-2', formPct >= 100 ? 'bg-red-50 dark:bg-red-900/20' : formPct >= 85 ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-emerald-50 dark:bg-emerald-900/20']">
                                        <p :class="['text-xs font-semibold', formPct >= 100 ? 'text-red-500' : formPct >= 85 ? 'text-amber-500' : 'text-emerald-500']">%</p>
                                        <p :class="['text-sm font-black', formPct >= 100 ? 'text-red-600' : formPct >= 85 ? 'text-amber-600' : 'text-emerald-600']">{{ formPct }}%</p>
                                    </div>
                                </div>
                                <p v-if="form.errors.dies_id" class="mt-1 text-xs text-red-500">{{ form.errors.dies_id }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Tanggal <span class="text-red-500">*</span></label>
                                    <input v-model="form.report_date" type="date"
                                        class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                                    <p v-if="form.errors.report_date" class="mt-1 text-xs text-red-500">{{ form.errors.report_date }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Stroke saat PM <span class="text-red-500">*</span></label>
                                    <input v-model="form.stroke_at_maintenance" type="number" min="0"
                                        class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" />
                                    <p v-if="form.errors.stroke_at_maintenance" class="mt-1 text-xs text-red-500">{{ form.errors.stroke_at_maintenance }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 px-4 py-3">
                        <h3 class="text-xs font-bold text-gray-500 uppercase mb-3">Status</h3>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <button v-for="s in statusList" :key="s.v" type="button" @click="form.status = s.v"
                                :class="['flex flex-col items-start p-2.5 rounded-xl border-2 text-left transition-all',
                                    form.status === s.v
                                        ? s.v === 'scheduled'   ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                          : s.v === 'pending'     ? 'border-gray-400 bg-gray-50 dark:bg-gray-700/50'
                                          : s.v === 'in_progress' ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/20'
                                          : 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300']">
                                <span :class="['text-xs font-bold',
                                    form.status === s.v
                                        ? s.v === 'scheduled'   ? 'text-blue-700 dark:text-blue-400'
                                          : s.v === 'pending'     ? 'text-gray-700 dark:text-gray-300'
                                          : s.v === 'in_progress' ? 'text-amber-700 dark:text-amber-400'
                                          : 'text-emerald-700 dark:text-emerald-400'
                                        : 'text-gray-500']">{{ s.l }}</span>
                                <span class="text-xs text-gray-400 mt-0.5 leading-tight">{{ s.hint }}</span>
                            </button>
                        </div>
                        <!-- Scheduled date field -->
                        <div v-if="form.status === 'scheduled'" class="mt-3">
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">
                                Tanggal Rencana PM <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.scheduled_date" type="date"
                                class="w-full px-3 py-2.5 border border-blue-300 dark:border-blue-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none bg-blue-50 dark:bg-blue-900/20" />
                        </div>
                    </div>

                    <!-- Detail Tindakan -->
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl px-4 py-3 border border-emerald-100 dark:border-emerald-900/40">
                        <h3 class="text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase mb-3 flex items-center gap-1.5">
                            <CheckCircle2 class="w-3.5 h-3.5" /> Detail Tindakan
                        </h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Proses yang di-PM</label>
                                <input v-model="form.repair_process" type="text" placeholder="Cth: DRAW 1, TRIM, RESTRIKE..."
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-emerald-500 focus:outline-none" />
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    <button v-for="s in processSuggestions.slice(0, 6)" :key="s" type="button"
                                        @click="form.repair_process = s"
                                        :class="['px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors',
                                            form.repair_process === s
                                                ? 'bg-emerald-600 text-white'
                                                : 'bg-gray-100 dark:bg-gray-700 text-gray-500 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:text-emerald-600']">
                                        {{ s }}
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Tindakan / Hasil PM</label>
                                <textarea v-model="form.repair_action" rows="3" placeholder="Deskripsikan tindakan yang dilakukan..."
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-emerald-500 focus:outline-none resize-none"></textarea>
                            </div>
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
                            isCompressing ? 'border-blue-400 bg-blue-50/50 cursor-wait' : 'border-gray-200 dark:border-gray-600 cursor-pointer hover:border-blue-400 hover:bg-blue-50/50 dark:hover:bg-blue-900/10']">
                            <Loader2 v-if="isCompressing" class="w-5 h-5 text-blue-400 animate-spin" />
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
                        <div v-if="isEdit && selectedPm?.spareparts?.length" class="mb-3 space-y-1.5">
                            <p class="text-xs text-gray-400 mb-1">Sudah tercatat:</p>
                            <div v-for="h in selectedPm.spareparts" :key="h.id"
                                class="flex justify-between items-center px-3 py-2 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg text-xs">
                                <span class="font-semibold text-indigo-700 dark:text-indigo-300">{{ h.sparepart?.sparepart_name ?? '-' }}</span>
                                <span class="text-gray-500">{{ h.quantity }} {{ h.sparepart?.unit }}</span>
                            </div>
                        </div>
                        <div v-if="form.spareparts.length === 0 && !(isEdit && selectedPm?.spareparts?.length)" class="py-4 text-center text-xs text-gray-400">
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
                                    isEdit ? 'bg-amber-500 hover:bg-amber-600' : 'bg-blue-600 hover:bg-blue-700']">
                                {{ isEdit ? 'Simpan Perubahan' : 'Buat Laporan PM' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ═══ MODAL DETAIL ═══ -->
        <div v-if="showDetailModal && selectedPm"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-lg max-h-[92vh] overflow-y-auto shadow-2xl">
                <div class="sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <CheckCircle2 class="w-4 h-4 text-blue-600" /> Detail PM
                        </h2>
                        <button @click="showDetailModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <div class="p-4 sm:p-5 space-y-3.5">
                    <!-- Hero -->
                    <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-white font-bold text-sm">{{ selectedPm.dies?.no_part ?? selectedPm.dies_id }}</p>
                                <p class="text-white/70 text-xs mt-0.5 line-clamp-1">{{ selectedPm.dies?.nama_dies }}</p>
                            </div>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-bold flex-shrink-0', statusCfg[selectedPm.status]?.badge ?? '']">
                                {{ statusCfg[selectedPm.status]?.label ?? selectedPm.status }}
                            </span>
                        </div>
                        <!-- Scheduled info -->
                        <div v-if="selectedPm.status === 'scheduled'" class="mt-2 flex items-center gap-1.5 text-white/80 text-xs">
                            <CalendarCheck class="w-3.5 h-3.5" />
                            Dijadwalkan PM: <span class="font-bold text-white">{{ fmtDate(selectedPm.scheduled_date) }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-3">
                            <div class="bg-white/10 rounded-xl p-2 text-center">
                                <User class="w-3 h-3 text-white/60 mx-auto mb-0.5" />
                                <p class="text-white text-xs font-bold truncate">{{ selectedPm.pic_name }}</p>
                                <p class="text-white/50 text-xs">PIC</p>
                            </div>
                            <div class="bg-white/10 rounded-xl p-2 text-center">
                                <Calendar class="w-3 h-3 text-white/60 mx-auto mb-0.5" />
                                <p class="text-white text-xs font-bold">{{ fmtDate(selectedPm.report_date) }}</p>
                                <p class="text-white/50 text-xs">Tanggal</p>
                            </div>
                            <div class="bg-white/10 rounded-xl p-2 text-center">
                                <Activity class="w-3 h-3 text-white/60 mx-auto mb-0.5" />
                                <p class="text-white text-xs font-bold">{{ selectedPm.stroke_at_maintenance.toLocaleString() }}</p>
                                <p class="text-white/50 text-xs">Stroke ({{ pct(selectedPm) }}%)</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="h-1.5 bg-white/20 rounded-full overflow-hidden">
                                <div :class="['h-full rounded-full', pct(selectedPm) >= 100 ? 'bg-red-400' : pct(selectedPm) >= 85 ? 'bg-amber-400' : 'bg-emerald-400']"
                                    :style="{ width: `${pct(selectedPm)}%` }"></div>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedPm.repair_process" class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3.5 border border-blue-100 dark:border-blue-800">
                        <p class="text-xs font-bold text-blue-500 uppercase mb-1.5">Proses PM</p>
                        <span class="inline-block px-3 py-1.5 bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 rounded-xl text-sm font-bold">
                            {{ selectedPm.repair_process }}
                        </span>
                    </div>
                    <div v-if="selectedPm.repair_action" class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-3.5 border border-emerald-100 dark:border-emerald-800">
                        <p class="text-xs font-bold text-emerald-600 uppercase mb-1.5">Tindakan / Hasil PM</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedPm.repair_action }}</p>
                    </div>
                    <div v-if="!selectedPm.repair_process && !selectedPm.repair_action && selectedPm.status !== 'scheduled'"
                        class="text-xs text-gray-400 italic text-center py-2">Belum ada detail tindakan</div>

                    <div v-if="selectedPm.photos?.length">
                        <p class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-1.5">
                            <Camera class="w-3.5 h-3.5" /> Foto ({{ selectedPm.photos.length }})
                        </p>
                        <div class="grid grid-cols-3 gap-2">
                            <div v-for="(ph, idx) in selectedPm.photos" :key="idx"
                                @click="lightboxSrc = '/storage/' + ph"
                                class="aspect-square rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-700 cursor-pointer group">
                                <img :src="'/storage/' + ph" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedPm.spareparts?.length">
                        <p class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-1.5">
                            <Package class="w-3.5 h-3.5 text-indigo-500" /> Sparepart Digunakan
                        </p>
                        <div class="space-y-1.5">
                            <div v-for="h in selectedPm.spareparts" :key="h.id"
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
                            <span class="font-semibold text-gray-800 dark:text-white">{{ selectedPm.created_by?.name ?? selectedPm.pic_name }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400">Tanggal dibuat</span>
                            <span class="font-semibold text-gray-800 dark:text-white">{{ fmtDate(selectedPm.report_date) }}</span>
                        </div>
                        <div v-if="selectedPm.completed_at" class="flex justify-between gap-2">
                            <span class="text-gray-400">Selesai pada</span>
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ fmtDate(selectedPm.completed_at) }}</span>
                        </div>
                    </div>
                </div>
                <div class="sticky bottom-0 bg-white dark:bg-gray-800 p-4 border-t border-gray-100 dark:border-gray-700 flex gap-3">
                    <button @click="showDetailModal = false"
                        class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-200 active:scale-95 transition-all">
                        Tutup
                    </button>
                    <button @click="showDetailModal = false; openEdit(selectedPm)"
                        class="flex-1 py-3 bg-amber-500 text-white rounded-xl text-sm font-bold hover:bg-amber-600 active:scale-95 transition-all flex items-center justify-center gap-2">
                        <Pencil class="w-3.5 h-3.5" /> Edit
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══ MODAL DELETE ═══ -->
        <div v-if="showDelModal && selectedPm"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="p-5 text-center">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-3">
                        <AlertTriangle class="w-6 h-6 text-red-600" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">Hapus Laporan PM?</h3>
                    <p class="text-sm text-gray-500 mb-1">{{ selectedPm.dies?.no_part ?? selectedPm.dies_id }}</p>
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

        <!-- ═══ LIGHTBOX ═══ -->
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
