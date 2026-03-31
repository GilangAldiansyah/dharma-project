<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    Wrench, Search, Filter, Plus, Pencil, Trash2,
    AlertTriangle, CheckCircle2, X, Download, Camera,
    Upload, Package, AlertCircle, Clock, Loader2,
    Timer, ChevronUp, ChevronDown, Layers, FileText, ArrowDownToLine
} from 'lucide-vue-next';
import * as XLSX from 'xlsx';

interface DiesProcess { id: number; process_name: string; tonase: number | null }
interface DiesMini { id_sap: string; no_part: string; nama_dies: string; line: string; current_stroke: number; processes: DiesProcess[] }
interface Sparepart { id: number; sparepart_code: string; sparepart_name: string; unit: string; stok: number }
interface HistorySp { id: number; quantity: number; notes: string | null; sparepart: Sparepart | null }
interface Photo { path: string; type: 'before' | 'after' }
interface Corrective {
    id: number; report_no: string; dies_id: string; process_id: number | null; pic_name: string;
    report_date: string; stroke_at_maintenance: number; repair_duration_minutes: number | null;
    machine_duration_minutes: number | null;
    repair_duration: string | null;
    machine_duration: string | null;
    off_machine_at: string | null;
    problem_description: string | null; cause: string | null;
    repair_action: string | null; action: string | null;
    photos: Photo[] | null; status: string;
    closed_at: string | null;
    dies: DiesMini | null;
    process: DiesProcess | null;
    spareparts: HistorySp[];
    created_by: { id: number; name: string } | null;
    closed_by: { id: number; name: string } | null;
}
interface Props {
    correctives: { data: Corrective[]; links: any[]; meta: any };
    diesList: DiesMini[];
    spareparts: Sparepart[];
    summary: { open: number; in_progress: number; on_repair: number; closed: number };
    filters: { search?: string; status?: string; dies_id?: string; month?: string; year?: string };
}

const props = defineProps<Props>();
const page  = usePage();
const flash = computed(() => (page.props as any).flash);

const currentYear = new Date().getFullYear();

const search       = ref(props.filters.search   ?? '');
const filterStatus = ref(props.filters.status   ?? '');
const filterDies   = ref(props.filters.dies_id  ?? '');
const filterMonth  = ref(props.filters.month    ?? '');
const filterYear   = ref(props.filters.year ? Number(props.filters.year) : currentYear);
const showFilter   = ref(false);

const months = [
    { v: '',   l: 'Semua' },
    { v: '01', l: 'Jan' }, { v: '02', l: 'Feb' }, { v: '03', l: 'Mar' },
    { v: '04', l: 'Apr' }, { v: '05', l: 'Mei' }, { v: '06', l: 'Jun' },
    { v: '07', l: 'Jul' }, { v: '08', l: 'Agu' }, { v: '09', l: 'Sep' },
    { v: '10', l: 'Okt' }, { v: '11', l: 'Nov' }, { v: '12', l: 'Des' },
];

const incrementYear = () => { if (filterYear.value < currentYear) filterYear.value++; };
const decrementYear = () => { if (filterYear.value > currentYear - 4) filterYear.value--; };

const showDelModal        = ref(false);
const showFormModal       = ref(false);
const showDetailModal     = ref(false);
const showCloseModal      = ref(false);
const showOffMachineModal = ref(false);
const isEdit              = ref(false);
const selectedCm          = ref<Corrective | null>(null);
const lightboxSrc         = ref<string | null>(null);
const showPhotoPicker     = ref(false);
const photoPickerType     = ref<'before' | 'after'>('before');
const photoPickerMode     = ref<'add' | 'edit'>('add');

const statusCfg: Record<string, { label: string; badge: string; dot: string; cardBorder: string; icon: any }> = {
    in_progress: { label: 'In Progress', badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400', dot: 'bg-amber-400',   cardBorder: 'border-l-amber-400',   icon: Clock        },
    on_repair:   { label: 'Di Bengkel',   badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',     dot: 'bg-blue-400',    cardBorder: 'border-l-blue-400',    icon: Wrench       },
    closed:      { label: 'Closed',      badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400', dot: 'bg-emerald-500', cardBorder: 'border-l-emerald-400', icon: CheckCircle2 },
};

const canClose = (c: Corrective) => c.status === 'in_progress' || c.status === 'on_repair';

const activeFilterCount = computed(() => [filterStatus.value, filterDies.value, filterMonth.value].filter(Boolean).length);

let debounce: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => navigate(), 400); });
watch([filterStatus, filterDies, filterMonth, filterYear], () => navigate());

const navigate = () => router.get('/dies/corrective', {
    search:   search.value,
    status:   filterStatus.value,
    dies_id:  filterDies.value,
    month:    filterMonth.value,
    year:     String(filterYear.value),
}, { preserveState: true, preserveScroll: true, replace: true });

const fmtDate = (d: string | null) =>
    !d ? '-' : new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

const fmtDatetime = (d: string | null) => {
    if (!d) return '-';
    return new Date(d).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

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

const form = useForm({
    dies_id:             '',
    process_id:          null as number | null,
    problem_description: '',
    cause:               '',
    repair_action:       '',
    photos_before:       [] as File[],
    photos_after:        [] as File[],
    spareparts:          [] as { sparepart_id: number | null; quantity: string; notes: string }[],
});

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
const selectFormDies        = (d: DiesMini) => { form.dies_id = d.id_sap; form.process_id = null; formDiesSearch.value = d.no_part; formDiesOpen.value = false; };
const clearFormDies         = () => { form.dies_id = ''; form.process_id = null; formDiesSearch.value = ''; formDiesOpen.value = true; };
const closeFormDiesDropdown = () => { setTimeout(() => { formDiesOpen.value = false; }, 180); };

const availableProcesses  = computed(() => selectedFormDiesObj.value?.processes ?? []);
const processOpen         = ref(false);
const selectedProcessObj  = computed(() => availableProcesses.value.find(p => p.id === form.process_id) ?? null);

watch(() => form.dies_id, () => { form.process_id = null; });

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
    if (existing) { const sp = props.spareparts.find(s => s.id === existing); if (sp && !spSearch.value[i]) spSearch.value[i] = sp.sparepart_name; }
    spOpen.value[i] = true;
};
const closeSpDropdown  = (i: number) => { setTimeout(() => { spOpen.value[i] = false; }, 180); };
const clearSpSelection = (i: number) => { form.spareparts[i].sparepart_id = null; spSearch.value[i] = ''; spOpen.value[i] = true; };
const selectedSpItem   = (id: number | null) => props.spareparts.find(s => s.id === id);
const addSp    = () => { form.spareparts.push({ sparepart_id: null, quantity: '', notes: '' }); spSearch.value.push(''); spOpen.value.push(false); };
const removeSp = (i: number) => { form.spareparts.splice(i, 1); spSearch.value.splice(i, 1); spOpen.value.splice(i, 1); };

const newBeforePreview = ref<{ file: File; url: string }[]>([]);
const newAfterPreview  = ref<{ file: File; url: string }[]>([]);
const existingPhotos   = ref<Photo[]>([]);
const photosToDelete   = ref<string[]>([]);
const isCompressing    = ref(false);

const existingBefore = computed(() => existingPhotos.value.filter(p => p.type === 'before'));
const existingAfter  = computed(() => existingPhotos.value.filter(p => p.type === 'after'));

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
                    if (w > h) { h = (h / w) * max; w = max; } else { w = (w / h) * max; h = max; }
                }
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d')!.drawImage(img, 0, 0, w, h);
                canvas.toBlob(blob => resolve(new File([blob!], 'photo.jpg', { type: 'image/jpeg' })), 'image/jpeg', 0.8);
            };
            img.src = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    });

const openPhotoPicker = (type: 'before' | 'after', mode: 'add' | 'edit') => {
    photoPickerType.value = type;
    photoPickerMode.value = mode;
    showPhotoPicker.value = true;
};

const triggerPhotoInput = (source: 'cam' | 'gal') => {
    showPhotoPicker.value = false;
    setTimeout(() => {
        const id = `${source}-${photoPickerMode.value}-${photoPickerType.value}`;
        (document.getElementById(id) as HTMLInputElement)?.click();
    }, 100);
};

const handleFileInput = async (e: Event, type: 'before' | 'after') => {
    const files = Array.from((e.target as HTMLInputElement).files ?? []);
    if (!files.length) return;
    isCompressing.value = true;
    try {
        for (const f of files) {
            const compressed = await compressImage(f);
            if (type === 'before') {
                newBeforePreview.value.push({ file: compressed, url: URL.createObjectURL(compressed) });
            } else {
                newAfterPreview.value.push({ file: compressed, url: URL.createObjectURL(compressed) });
            }
        }
        form.photos_before = newBeforePreview.value.map(p => p.file);
        form.photos_after  = newAfterPreview.value.map(p => p.file);
    } finally {
        isCompressing.value = false;
    }
    (e.target as HTMLInputElement).value = '';
};

const removeNewPhoto = (idx: number, type: 'before' | 'after') => {
    if (type === 'before') {
        URL.revokeObjectURL(newBeforePreview.value[idx].url);
        newBeforePreview.value.splice(idx, 1);
        form.photos_before = newBeforePreview.value.map(p => p.file);
    } else {
        URL.revokeObjectURL(newAfterPreview.value[idx].url);
        newAfterPreview.value.splice(idx, 1);
        form.photos_after = newAfterPreview.value.map(p => p.file);
    }
};

const markDeletePhoto = (path: string) => {
    if (photosToDelete.value.includes(path)) {
        photosToDelete.value = photosToDelete.value.filter(p => p !== path);
    } else {
        photosToDelete.value.push(path);
    }
};

const resetFormState = () => {
    form.clearErrors();
    formDiesSearch.value = '';
    formDiesOpen.value   = false;
    processOpen.value    = false;
    spSearch.value       = [];
    spOpen.value         = [];
    newBeforePreview.value.forEach(p => URL.revokeObjectURL(p.url));
    newAfterPreview.value.forEach(p => URL.revokeObjectURL(p.url));
    newBeforePreview.value = [];
    newAfterPreview.value  = [];
    existingPhotos.value   = [];
    photosToDelete.value   = [];
};

const openAdd = () => {
    isEdit.value        = false;
    selectedCm.value    = null;
    form.dies_id             = '';
    form.process_id          = null;
    form.problem_description = '';
    form.cause               = '';
    form.repair_action       = '';
    form.photos_before       = [];
    form.photos_after        = [];
    form.spareparts          = [];
    resetFormState();
    showFormModal.value = true;
};

const openEdit = (c: Corrective) => {
    isEdit.value     = true;
    selectedCm.value = c;
    form.dies_id             = c.dies_id;
    form.process_id          = c.process_id;
    form.problem_description = c.problem_description ?? '';
    form.cause               = c.cause ?? '';
    form.repair_action       = c.repair_action ?? '';
    form.photos_before       = [];
    form.photos_after        = [];
    form.spareparts          = [];
    resetFormState();
    formDiesSearch.value  = c.dies?.no_part ?? c.dies_id;
    existingPhotos.value  = c.photos ?? [];
    showFormModal.value   = true;
};

const openDetail = (c: Corrective) => { selectedCm.value = c; showDetailModal.value = true; };

const closeFormModal = () => {
    showFormModal.value = false;
    newBeforePreview.value.forEach(p => URL.revokeObjectURL(p.url));
    newAfterPreview.value.forEach(p => URL.revokeObjectURL(p.url));
    newBeforePreview.value = [];
    newAfterPreview.value  = [];
};

const submitForm = () => {
    if (isEdit.value && selectedCm.value) {
        if (photosToDelete.value.length) {
            photosToDelete.value.forEach(photo => {
                router.post(`/dies/corrective/${selectedCm.value!.id}/delete-photo`, { photo }, { preserveState: true });
            });
        }
        router.post(`/dies/corrective/${selectedCm.value.id}`, {
            _method:             'PUT',
            problem_description: form.problem_description,
            cause:               form.cause,
            repair_action:       form.repair_action,
            photos_before:       form.photos_before,
            photos_after:        form.photos_after,
            spareparts:          form.spareparts,
        }, { forceFormData: true, onSuccess: () => closeFormModal() });
    } else {
        router.post('/dies/corrective', {
            dies_id:             form.dies_id,
            process_id:          form.process_id,
            problem_description: form.problem_description,
            cause:               form.cause,
            repair_action:       form.repair_action,
            photos_before:       form.photos_before,
            photos_after:        form.photos_after,
            spareparts:          form.spareparts,
        }, { forceFormData: true, onSuccess: () => closeFormModal() });
    }
};

const openOffMachine = (c: Corrective) => { selectedCm.value = c; showOffMachineModal.value = true; };
const submitOffMachine = () => {
    if (!selectedCm.value) return;
    router.post(`/dies/corrective/${selectedCm.value.id}/off-machine`, {}, {
        preserveScroll: true,
        onSuccess: () => { showOffMachineModal.value = false; selectedCm.value = null; },
    });
};

const closeForm = useForm({ action: '' });
const openClose  = (c: Corrective) => { selectedCm.value = c; closeForm.reset(); showCloseModal.value = true; };
const submitClose = () => {
    if (!selectedCm.value) return;
    closeForm.post(`/dies/corrective/${selectedCm.value.id}/close`, {
        onSuccess: () => { showCloseModal.value = false; selectedCm.value = null; },
    });
};

const openDelete  = (c: Corrective) => { selectedCm.value = c; showDelModal.value = true; };
const submitDelete = () => {
    if (!selectedCm.value) return;
    router.delete(`/dies/corrective/${selectedCm.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { showDelModal.value = false; selectedCm.value = null; },
    });
};

const exportExcel = () => {
    const rows = [
        ['No Part', 'Nama Dies', 'Line', 'Proses', 'PIC', 'Tanggal', 'Stroke', 'Durasi Perbaikan Di atas Mesin', 'Durasi Total', 'Problem', 'Cause', 'Tindakan', 'Status', 'Ditutup Oleh', 'Tanggal Tutup'],
        ...props.correctives.data.map(c => [
            c.dies?.no_part ?? c.dies_id,
            c.dies?.nama_dies ?? '',
            c.dies?.line ?? '',
            c.process?.process_name ?? '',
            c.pic_name,
            c.report_date,
            c.stroke_at_maintenance,
            c.machine_duration ?? '',
            c.repair_duration ?? '',
            c.problem_description ?? '',
            c.cause ?? '',
            c.repair_action ?? '',
            statusCfg[c.status]?.label ?? c.status,
            c.closed_by?.name ?? '',
            c.closed_at ? fmtDate(c.closed_at) : '',
        ]),
    ];
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [
        { wch: 16 }, { wch: 30 }, { wch: 10 }, { wch: 20 }, { wch: 16 },
        { wch: 12 }, { wch: 14 }, { wch: 14 }, { wch: 14 }, { wch: 30 },
        { wch: 30 }, { wch: 30 }, { wch: 14 }, { wch: 16 }, { wch: 14 },
    ];
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
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-9 h-9 bg-orange-500 rounded-xl flex-shrink-0">
                        <Wrench class="w-5 h-5 text-white" />
                    </span>
                    <div>
                        <h1 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white leading-tight">Corrective Maintenance</h1>
                        <p class="text-xs text-gray-400">Laporan kerusakan & perbaikan dies</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="exportExcel"
                        class="flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <Download class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Export</span>
                    </button>
                    <button @click="openAdd"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-2 bg-orange-500 hover:bg-orange-600 active:scale-95 text-white rounded-xl text-xs sm:text-sm font-semibold transition-all">
                        <Plus class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Buat Laporan</span>
                        <span class="sm:hidden">Buat</span>
                    </button>
                </div>
            </div>

            <!-- Flash -->
            <div v-if="flash?.success"
                class="flex items-center gap-2.5 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                <p class="text-emerald-800 dark:text-emerald-200 text-xs font-medium">{{ flash.success }}</p>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-3 gap-2">
                <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800/50 text-center">
                    <p class="text-xs text-amber-500 font-semibold mb-0.5 flex items-center justify-center gap-1">
                        <Clock class="w-3 h-3" /> In Progress
                    </p>
                    <p class="text-2xl font-black text-amber-600">{{ summary.in_progress }}</p>
                </div>
                <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/50 text-center">
                    <p class="text-xs text-blue-500 font-semibold mb-0.5 flex items-center justify-center gap-1">
                        <Wrench class="w-3 h-3" /> Di Bengkel
                    </p>
                    <p class="text-2xl font-black text-blue-600">{{ summary.on_repair }}</p>
                </div>
                <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800/50 text-center">
                    <p class="text-xs text-emerald-500 font-semibold mb-0.5 flex items-center justify-center gap-1">
                        <CheckCircle2 class="w-3 h-3" /> Closed
                    </p>
                    <p class="text-2xl font-black text-emerald-600">{{ summary.closed }}</p>
                </div>
            </div>

            <!-- Month + Year Filter -->
            <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-xl p-2.5">
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1 overflow-x-auto scrollbar-hide flex-1 min-w-0">
                        <button v-for="m in months" :key="m.v" @click="filterMonth = m.v"
                            :class="['flex-shrink-0 px-2.5 py-1 rounded-lg text-xs font-semibold transition-all',
                                filterMonth === m.v ? 'bg-orange-500 text-white' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700']">
                            {{ m.l }}
                        </button>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0 border border-gray-200 dark:border-gray-700 rounded-lg px-2 py-1">
                        <button @click="decrementYear" :disabled="filterYear <= currentYear - 4"
                            class="text-gray-400 hover:text-orange-500 disabled:opacity-30 transition-colors">
                            <ChevronDown class="w-3.5 h-3.5" />
                        </button>
                        <span class="text-xs font-bold text-gray-800 dark:text-gray-200 tabular-nums w-9 text-center">{{ filterYear }}</span>
                        <button @click="incrementYear" :disabled="filterYear >= currentYear"
                            class="text-gray-400 hover:text-orange-500 disabled:opacity-30 transition-colors">
                            <ChevronUp class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Search + Filter -->
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                    <input v-model="search" type="text" placeholder="Cari no part, PIC..."
                        class="w-full pl-9 pr-4 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-orange-400 focus:outline-none transition-colors" />
                </div>
                <button @click="showFilter = !showFilter"
                    :class="['relative flex items-center gap-1.5 px-3 py-2 border rounded-xl text-sm font-medium transition-colors',
                        showFilter || activeFilterCount > 0
                            ? 'bg-orange-500 border-orange-500 text-white'
                            : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-orange-400']">
                    <Filter class="w-3.5 h-3.5" />
                    <span class="text-xs">Filter</span>
                    <span v-if="activeFilterCount > 0"
                        class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold leading-none">
                        {{ activeFilterCount }}
                    </span>
                </button>
            </div>

            <!-- Filter Panel -->
            <div v-if="showFilter" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-3 space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase mb-1.5">Status</label>
                    <div class="flex flex-wrap gap-1.5">
                        <button v-for="s in [{ v: '', l: 'Semua' }, ...Object.entries(statusCfg).map(([v, c]) => ({ v, l: c.label }))]" :key="s.v"
                            @click="filterStatus = s.v"
                            :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                                filterStatus === s.v ? 'bg-orange-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
                            {{ s.l }}
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase mb-1.5">Dies</label>
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                        <input v-model="diesSearch" type="text" placeholder="Cari no part / nama dies..."
                            autocomplete="off" @focus="diesOpen = true" @blur="closeDiesDropdown"
                            :class="['w-full pl-8 pr-8 py-2 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                selectedDiesObj ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-orange-400']" />
                        <button v-if="selectedDiesObj" type="button" @click="clearDiesFilter"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                            <X class="w-3.5 h-3.5" />
                        </button>
                        <div v-if="diesOpen" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-56 overflow-y-auto">
                            <div v-if="filteredDies.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                            <button v-for="d in filteredDies" :key="d.id_sap" type="button"
                                @mousedown.prevent="selectDiesFilter(d)"
                                :class="['w-full text-left px-3 py-2.5 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors', selectedDiesObj?.id_sap === d.id_sap ? 'bg-orange-50 dark:bg-orange-900/20' : '']">
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

            <!-- Desktop Table -->
            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Dies / Proses</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">PIC</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Waktu</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Problem</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-400 uppercase">Durasi</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-400 uppercase">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-400 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        <tr v-if="correctives.data.length === 0">
                            <td colspan="7" class="py-16 text-center text-gray-400 text-sm">
                                <Wrench class="w-8 h-8 mx-auto mb-2 text-gray-300" />
                                Tidak ada data corrective
                            </td>
                        </tr>
                        <tr v-for="c in correctives.data" :key="c.id"
                            class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3">
                                <p class="text-xs font-bold text-gray-900 dark:text-white">{{ c.dies?.no_part ?? c.dies_id }}</p>
                                <p class="text-xs text-gray-400 line-clamp-1">{{ c.dies?.nama_dies }}</p>
                                <div class="flex items-center gap-1 mt-1">
                                    <span class="text-xs px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded font-semibold">{{ c.dies?.line }}</span>
                                    <span v-if="c.process" class="text-xs px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 rounded font-semibold flex items-center gap-0.5">
                                        <Layers class="w-2.5 h-2.5" />{{ c.process.process_name }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">{{ c.pic_name }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ fmtDatetime(c.report_date) }}</td>
                            <td class="px-4 py-3 max-w-xs">
                                <p v-if="c.problem_description" class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2">{{ c.problem_description }}</p>
                                <span v-else class="text-xs text-gray-300 italic">—</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex flex-col items-center gap-1">
                                    <span v-if="c.machine_duration"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400 rounded-full text-xs font-bold">
                                        <ArrowDownToLine class="w-2.5 h-2.5" /> {{ c.machine_duration }}
                                    </span>
                                    <span v-if="c.repair_duration"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-400 rounded-full text-xs font-bold">
                                        <Timer class="w-2.5 h-2.5" /> {{ c.repair_duration }}
                                    </span>
                                    <span v-if="c.spareparts?.length"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400 rounded-full text-xs font-bold">
                                        <Package class="w-2.5 h-2.5" /> {{ c.spareparts.length }} SP
                                    </span>
                                    <span v-if="!c.machine_duration && !c.repair_duration && !c.spareparts?.length" class="text-xs text-gray-300">—</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[c.status]?.badge ?? '']">
                                    <component :is="statusCfg[c.status]?.icon" class="w-3 h-3" />
                                    {{ statusCfg[c.status]?.label ?? c.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <button @click="openDetail(c)"
                                        class="p-1.5 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-lg hover:bg-gray-200 transition-colors" title="Detail">
                                        <FileText class="w-3.5 h-3.5" />
                                    </button>
                                    <button v-if="c.status !== 'closed'" @click="openEdit(c)"
                                        class="p-1.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-lg hover:bg-amber-200 transition-colors" title="Edit">
                                        <Pencil class="w-3.5 h-3.5" />
                                    </button>
                                    <button v-if="c.status === 'in_progress'" @click="openOffMachine(c)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400 rounded-lg text-xs font-semibold hover:bg-blue-200 transition-colors">
                                        <ArrowDownToLine class="w-3 h-3" /> Turun
                                    </button>
                                    <button v-if="canClose(c)" @click="openClose(c)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 transition-colors">
                                        <CheckCircle2 class="w-3 h-3" /> Close
                                    </button>
                                    <button @click="openDelete(c)"
                                        class="p-1.5 bg-red-100 dark:bg-red-900/40 text-red-500 dark:text-red-400 rounded-lg hover:bg-red-200 transition-colors" title="Hapus">
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="correctives.meta?.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-400">{{ correctives.meta.from }}–{{ correctives.meta.to }} dari {{ correctives.meta.total }}</p>
                    <div class="flex gap-1">
                        <button v-for="link in correctives.links" :key="link.label"
                            @click="link.url && router.visit(link.url)" :disabled="!link.url" v-html="link.label"
                            :class="['px-3 py-1.5 text-xs rounded-lg font-semibold transition-colors',
                                link.active ? 'bg-orange-500 text-white' : link.url ? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200' : 'opacity-40 cursor-default bg-gray-100 dark:bg-gray-700 text-gray-400']">
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Cards -->
            <div class="lg:hidden space-y-2">
                <div v-if="correctives.data.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                    <Wrench class="w-8 h-8 mx-auto mb-2 text-gray-300" />
                    <p class="text-gray-400 text-sm">Tidak ada data corrective</p>
                </div>
                <div v-for="c in correctives.data" :key="c.id"
                    :class="['bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden border-l-4', statusCfg[c.status]?.cardBorder]">
                    <div class="p-3">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-900 dark:text-white leading-tight">{{ c.dies?.no_part ?? c.dies_id }}</p>
                                <p class="text-xs text-gray-400 line-clamp-1">{{ c.dies?.nama_dies }}</p>
                                <div class="flex items-center gap-1 mt-1 flex-wrap">
                                    <span class="text-xs px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded font-semibold">{{ c.dies?.line }}</span>
                                    <span v-if="c.process" class="text-xs px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 rounded font-semibold flex items-center gap-0.5">
                                        <Layers class="w-2.5 h-2.5" />{{ c.process.process_name }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[c.status]?.badge ?? '']">
                                    <component :is="statusCfg[c.status]?.icon" class="w-3 h-3" />
                                    {{ statusCfg[c.status]?.label ?? c.status }}
                                </span>
                                <div class="flex items-center gap-1 flex-wrap justify-end">
                                    <span v-if="c.machine_duration"
                                        class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400 rounded-full text-xs font-bold">
                                        <ArrowDownToLine class="w-2.5 h-2.5" /> {{ c.machine_duration }}
                                    </span>
                                    <span v-if="c.repair_duration"
                                        class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-400 rounded-full text-xs font-bold">
                                        <Timer class="w-2.5 h-2.5" /> {{ c.repair_duration }}
                                    </span>
                                    <span v-if="c.spareparts?.length"
                                        class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400 rounded-full text-xs font-bold">
                                        <Package class="w-2.5 h-2.5" /> {{ c.spareparts.length }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-xs mb-2.5">
                            <span class="text-gray-400">{{ c.pic_name }}</span>
                            <span class="text-gray-300">·</span>
                            <span class="text-gray-400">{{ fmtDatetime(c.report_date) }}</span>
                        </div>
                        <p v-if="c.problem_description" class="text-xs text-gray-600 dark:text-gray-400 line-clamp-2 mb-2.5">{{ c.problem_description }}</p>

                        <!-- Action buttons -->
                        <div class="flex items-center gap-1.5 pt-2 border-t border-gray-100 dark:border-gray-700">
                            <button v-if="c.status === 'in_progress'" @click="openOffMachine(c)"
                                class="flex items-center gap-1 px-2.5 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-bold hover:bg-blue-700 active:scale-95 transition-all">
                                <ArrowDownToLine class="w-3.5 h-3.5" /> Turun
                            </button>
                            <button v-if="canClose(c)" @click="openClose(c)"
                                class="flex items-center gap-1 px-2.5 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 active:scale-95 transition-all">
                                <CheckCircle2 class="w-3.5 h-3.5" /> Close
                            </button>
                            <div class="flex-1"></div>
                            <button v-if="c.status !== 'closed'" @click="openEdit(c)"
                                class="p-1.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-lg hover:bg-amber-200 transition-colors">
                                <Pencil class="w-3.5 h-3.5" />
                            </button>
                            <button @click="openDetail(c)"
                                class="p-1.5 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-lg hover:bg-gray-200 transition-colors">
                                <FileText class="w-3.5 h-3.5" />
                            </button>
                            <button @click="openDelete(c)"
                                class="p-1.5 bg-red-100 dark:bg-red-900/40 text-red-500 dark:text-red-400 rounded-lg hover:bg-red-200 transition-colors">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="correctives.meta?.last_page > 1" class="flex justify-center gap-1 pt-1">
                    <button v-for="link in correctives.links" :key="link.label"
                        @click="link.url && router.visit(link.url)" :disabled="!link.url" v-html="link.label"
                        :class="['px-3 py-1.5 text-xs rounded-lg font-semibold',
                            link.active ? 'bg-orange-500 text-white' : link.url ? 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600' : 'opacity-40 cursor-default']">
                    </button>
                </div>
            </div>
        </div>

        <!-- ====== MODAL FORM ====== -->
        <div v-if="showFormModal"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-2xl max-h-[95vh] flex flex-col shadow-2xl">
                <div class="flex-shrink-0">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <Wrench :class="['w-4 h-4', isEdit ? 'text-amber-500' : 'text-orange-500']" />
                            {{ isEdit ? 'Edit Laporan CM' : 'Buat Laporan CM' }}
                        </h2>
                        <button @click="closeFormModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <form @submit.prevent="submitForm" class="overflow-y-auto flex-1 px-4 sm:px-6 py-4 space-y-4">

                    <!-- Identifikasi (Add only) -->
                    <div v-if="!isEdit" class="space-y-3">
                        <h3 class="text-xs font-bold text-gray-400 uppercase flex items-center gap-1.5">
                            <AlertCircle class="w-3.5 h-3.5" /> Identifikasi Dies
                        </h3>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Dies <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                                <input v-model="formDiesSearch" type="text" placeholder="Cari no part / nama dies..."
                                    autocomplete="off" @focus="formDiesOpen = true" @blur="closeFormDiesDropdown"
                                    :class="['w-full pl-8 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                        form.dies_id ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-orange-400']" />
                                <button v-if="form.dies_id" type="button" @click="clearFormDies"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                                    <X class="w-4 h-4" />
                                </button>
                                <div v-if="formDiesOpen" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-56 overflow-y-auto">
                                    <div v-if="filteredFormDies.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                    <button v-for="d in filteredFormDies" :key="d.id_sap" type="button"
                                        @mousedown.prevent="selectFormDies(d)"
                                        :class="['w-full text-left px-3 py-2.5 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors', form.dies_id === d.id_sap ? 'bg-orange-50 dark:bg-orange-900/20' : '']">
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
                                <span>Stroke: <span class="font-bold text-gray-700 dark:text-gray-300">{{ selectedFormDiesObj.current_stroke.toLocaleString() }}</span></span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Proses <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <button type="button" @click="processOpen = !processOpen" :disabled="!form.dies_id"
                                    :class="['w-full flex items-center justify-between px-3 py-2.5 border rounded-xl text-sm transition-colors dark:bg-gray-700',
                                        !form.dies_id ? 'opacity-50 cursor-not-allowed border-gray-200 dark:border-gray-600' :
                                        form.process_id ? 'border-purple-400 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-semibold' :
                                        'border-gray-200 dark:border-gray-600 text-gray-500 hover:border-purple-400']">
                                    <span class="flex items-center gap-2">
                                        <Layers class="w-3.5 h-3.5 text-purple-400 flex-shrink-0" />
                                        {{ selectedProcessObj ? selectedProcessObj.process_name : (form.dies_id ? 'Pilih proses...' : 'Pilih dies dulu') }}
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <button v-if="form.process_id" type="button" @click.stop="form.process_id = null"
                                            class="p-0.5 text-gray-400 hover:text-red-500 transition-colors">
                                            <X class="w-3.5 h-3.5" />
                                        </button>
                                        <ChevronDown :class="['w-3.5 h-3.5 text-gray-400 transition-transform', processOpen ? 'rotate-180' : '']" />
                                    </div>
                                </button>
                                <div v-if="processOpen && form.dies_id" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                    <div v-if="availableProcesses.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada proses</div>
                                    <button v-for="p in availableProcesses" :key="p.id" type="button"
                                        @mousedown.prevent="form.process_id = p.id; processOpen = false"
                                        :class="['w-full text-left px-3 py-2.5 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors flex items-center justify-between',
                                            form.process_id === p.id ? 'bg-purple-50 dark:bg-purple-900/20' : '']">
                                        <span class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ p.process_name }}</span>
                                        <span v-if="p.tonase" class="text-xs text-gray-400">{{ p.tonase }} ton</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dies info (Edit mode) -->
                    <div v-if="isEdit && selectedCm" class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedCm.dies?.no_part ?? selectedCm.dies_id }}</p>
                            <p class="text-xs text-gray-400 line-clamp-1">{{ selectedCm.dies?.nama_dies }}</p>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <span class="text-xs px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 rounded font-semibold">{{ selectedCm.dies?.line }}</span>
                            <span v-if="selectedCm.process" class="text-xs px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 rounded font-semibold">{{ selectedCm.process.process_name }}</span>
                        </div>
                    </div>

                    <!-- Detail Kerusakan -->
                    <div class="space-y-3">
                        <h3 class="text-xs font-bold text-gray-400 uppercase">Detail Kerusakan & Tindakan</h3>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Deskripsi Problem</label>
                            <textarea v-model="form.problem_description" rows="3" placeholder="Jelaskan masalah yang terjadi..."
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-orange-400 focus:outline-none resize-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Penyebab</label>
                            <textarea v-model="form.cause" rows="2" placeholder="Analisa penyebab..."
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-orange-400 focus:outline-none resize-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Tindakan Perbaikan</label>
                            <textarea v-model="form.repair_action" rows="3" placeholder="Tindakan yang dilakukan..."
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-orange-400 focus:outline-none resize-none"></textarea>
                        </div>
                    </div>

                    <!-- Foto -->
                    <div class="space-y-3">
                        <h3 class="text-xs font-bold text-gray-400 uppercase flex items-center gap-1.5">
                            <Camera class="w-3.5 h-3.5" /> Foto Dokumentasi
                        </h3>
                        <div class="grid grid-cols-2 gap-3">
                            <!-- Before -->
                            <div>
                                <p class="text-xs font-bold text-red-500 mb-2 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span> Before
                                </p>
                                <div v-if="existingBefore.length || newBeforePreview.length" class="grid grid-cols-2 gap-1.5 mb-2">
                                    <div v-for="ph in existingBefore" :key="ph.path"
                                        :class="['relative rounded-lg overflow-hidden aspect-square', photosToDelete.includes(ph.path) ? 'opacity-40 ring-2 ring-red-400' : '']">
                                        <img :src="'/storage/' + ph.path" class="w-full h-full object-cover cursor-pointer" @click="lightboxSrc = '/storage/' + ph.path" />
                                        <button type="button" @click="markDeletePhoto(ph.path)"
                                            :class="['absolute top-1 right-1 p-1 rounded-full text-white', photosToDelete.includes(ph.path) ? 'bg-gray-500' : 'bg-red-500']">
                                            <X class="w-2.5 h-2.5" />
                                        </button>
                                    </div>
                                    <div v-for="(p, idx) in newBeforePreview" :key="idx"
                                        class="relative rounded-lg overflow-hidden aspect-square ring-2 ring-blue-400">
                                        <img :src="p.url" class="w-full h-full object-cover" />
                                        <button type="button" @click="removeNewPhoto(idx, 'before')"
                                            class="absolute top-1 right-1 p-1 bg-red-500 rounded-full text-white">
                                            <X class="w-2.5 h-2.5" />
                                        </button>
                                    </div>
                                </div>
                                <div @click="openPhotoPicker('before', isEdit ? 'edit' : 'add')"
                                    class="cursor-pointer flex flex-col items-center justify-center gap-1.5 p-3 border-2 border-dashed border-red-200 dark:border-red-800/50 rounded-xl hover:border-red-400 hover:bg-red-50/50 transition-colors min-h-16">
                                    <Loader2 v-if="isCompressing" class="w-4 h-4 text-red-400 animate-spin" />
                                    <Upload v-else class="w-4 h-4 text-red-400" />
                                    <span class="text-xs text-gray-400 text-center">{{ isCompressing ? 'Mengompres...' : 'Tambah foto' }}</span>
                                </div>
                                <input :id="`cam-${isEdit ? 'edit' : 'add'}-before`" type="file" accept="image/*" capture="environment" class="hidden" @change="(e) => handleFileInput(e, 'before')" />
                                <input :id="`gal-${isEdit ? 'edit' : 'add'}-before`" type="file" accept="image/*" class="hidden" @change="(e) => handleFileInput(e, 'before')" />
                            </div>
                            <!-- After -->
                            <div>
                                <p class="text-xs font-bold text-emerald-600 mb-2 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span> After
                                </p>
                                <div v-if="existingAfter.length || newAfterPreview.length" class="grid grid-cols-2 gap-1.5 mb-2">
                                    <div v-for="ph in existingAfter" :key="ph.path"
                                        :class="['relative rounded-lg overflow-hidden aspect-square', photosToDelete.includes(ph.path) ? 'opacity-40 ring-2 ring-red-400' : '']">
                                        <img :src="'/storage/' + ph.path" class="w-full h-full object-cover cursor-pointer" @click="lightboxSrc = '/storage/' + ph.path" />
                                        <button type="button" @click="markDeletePhoto(ph.path)"
                                            :class="['absolute top-1 right-1 p-1 rounded-full text-white', photosToDelete.includes(ph.path) ? 'bg-gray-500' : 'bg-red-500']">
                                            <X class="w-2.5 h-2.5" />
                                        </button>
                                    </div>
                                    <div v-for="(p, idx) in newAfterPreview" :key="idx"
                                        class="relative rounded-lg overflow-hidden aspect-square ring-2 ring-blue-400">
                                        <img :src="p.url" class="w-full h-full object-cover" />
                                        <button type="button" @click="removeNewPhoto(idx, 'after')"
                                            class="absolute top-1 right-1 p-1 bg-red-500 rounded-full text-white">
                                            <X class="w-2.5 h-2.5" />
                                        </button>
                                    </div>
                                </div>
                                <div @click="openPhotoPicker('after', isEdit ? 'edit' : 'add')"
                                    class="cursor-pointer flex flex-col items-center justify-center gap-1.5 p-3 border-2 border-dashed border-emerald-200 dark:border-emerald-800/50 rounded-xl hover:border-emerald-400 hover:bg-emerald-50/50 transition-colors min-h-16">
                                    <Loader2 v-if="isCompressing" class="w-4 h-4 text-emerald-400 animate-spin" />
                                    <Upload v-else class="w-4 h-4 text-emerald-400" />
                                    <span class="text-xs text-gray-400 text-center">{{ isCompressing ? 'Mengompres...' : 'Tambah foto' }}</span>
                                </div>
                                <input :id="`cam-${isEdit ? 'edit' : 'add'}-after`" type="file" accept="image/*" capture="environment" class="hidden" @change="(e) => handleFileInput(e, 'after')" />
                                <input :id="`gal-${isEdit ? 'edit' : 'add'}-after`" type="file" accept="image/*" class="hidden" @change="(e) => handleFileInput(e, 'after')" />
                            </div>
                        </div>
                        <p v-if="photosToDelete.length" class="text-xs text-red-500 flex items-center gap-1">
                            <AlertCircle class="w-3.5 h-3.5" /> {{ photosToDelete.length }} foto akan dihapus saat disimpan
                        </p>
                    </div>

                    <!-- Sparepart -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-xs font-bold text-gray-400 uppercase flex items-center gap-1.5">
                                <Package class="w-3.5 h-3.5 text-indigo-500" /> Sparepart
                            </h3>
                            <button type="button" @click="addSp"
                                class="flex items-center gap-1 px-2.5 py-1.5 text-xs bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400 rounded-lg font-semibold hover:bg-indigo-200 transition-colors">
                                <Plus class="w-3 h-3" /> Tambah
                            </button>
                        </div>
                        <div v-if="isEdit && selectedCm?.spareparts?.length" class="mb-2 space-y-1">
                            <p class="text-xs text-gray-400">Sudah tercatat:</p>
                            <div v-for="h in selectedCm.spareparts" :key="h.id"
                                class="flex justify-between px-3 py-2 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg text-xs">
                                <span class="font-semibold text-indigo-700 dark:text-indigo-300">{{ h.sparepart?.sparepart_name ?? '-' }}</span>
                                <span class="text-gray-500">{{ h.quantity }} {{ h.sparepart?.unit }}</span>
                            </div>
                        </div>
                        <div v-if="form.spareparts.length === 0 && !(isEdit && selectedCm?.spareparts?.length)"
                            class="py-3 text-center text-xs text-gray-400">Belum ada sparepart</div>
                        <div v-for="(sp, i) in form.spareparts" :key="i" class="mb-2 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl space-y-2">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                                    <input v-model="spSearch[i]" type="text" placeholder="Cari sparepart..." autocomplete="off"
                                        @focus="openSpDropdown(i)" @blur="closeSpDropdown(i)"
                                        :class="['w-full pl-7 pr-7 py-2 border rounded-xl text-xs focus:outline-none transition-colors dark:bg-gray-700',
                                            sp.sparepart_id ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-indigo-400']" />
                                    <button v-if="sp.sparepart_id" type="button" @click="clearSpSelection(i)"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500">
                                        <X class="w-3 h-3" />
                                    </button>
                                    <div v-if="spOpen[i]" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-48 overflow-y-auto">
                                        <div v-if="filteredSp(i).length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                        <button v-for="s in filteredSp(i)" :key="s.id" type="button"
                                            @mousedown.prevent="selectSp(i, s)"
                                            :class="['w-full text-left px-3 py-2 text-xs hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors flex items-center justify-between gap-2',
                                                sp.sparepart_id === s.id ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 font-semibold' : 'text-gray-700 dark:text-gray-300']">
                                            <span>{{ s.sparepart_name }}</span>
                                            <span class="text-gray-400 shrink-0">{{ s.stok }} {{ s.unit }}</span>
                                        </button>
                                    </div>
                                </div>
                                <input v-model="sp.quantity" type="number" inputmode="numeric" min="1" placeholder="Qty"
                                    class="w-16 px-2 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none text-center" />
                                <button type="button" @click="removeSp(i)"
                                    class="p-2 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                            <input v-model="sp.notes" type="text" placeholder="Keterangan (opsional)"
                                class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none" />
                            <p v-if="sp.sparepart_id && selectedSpItem(sp.sparepart_id) && parseInt(sp.quantity) > selectedSpItem(sp.sparepart_id)!.stok"
                                class="text-xs text-red-500 flex items-center gap-1">
                                <AlertCircle class="w-3 h-3" /> Stok tidak cukup ({{ selectedSpItem(sp.sparepart_id)!.stok }} tersedia)
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
                            <button type="submit" :disabled="!isEdit && (!form.dies_id || !form.process_id)"
                                :class="['flex-1 py-3 rounded-xl font-bold text-sm text-white active:scale-95 transition-all disabled:opacity-50',
                                    isEdit ? 'bg-amber-500 hover:bg-amber-600' : 'bg-orange-500 hover:bg-orange-600']">
                                {{ isEdit ? 'Simpan Perubahan' : 'Buat Laporan CM' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ====== MODAL DETAIL ====== -->
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
                <div class="p-4 sm:p-5 space-y-3">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 space-y-2 text-xs">
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400">Dies</span>
                            <span class="font-bold text-gray-900 dark:text-white text-right">{{ selectedCm.dies?.no_part ?? selectedCm.dies_id }}</span>
                        </div>
                        <div v-if="selectedCm.dies?.nama_dies" class="flex justify-between gap-2">
                            <span class="text-gray-400">Nama</span>
                            <span class="font-semibold text-gray-700 dark:text-gray-300 text-right">{{ selectedCm.dies.nama_dies }}</span>
                        </div>
                        <div v-if="selectedCm.process" class="flex justify-between gap-2">
                            <span class="text-gray-400">Proses</span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 rounded font-semibold">
                                <Layers class="w-3 h-3" /> {{ selectedCm.process.process_name }}
                            </span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400">PIC</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ selectedCm.pic_name }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400">Laporan</span>
                            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ fmtDatetime(selectedCm.report_date) }}</span>
                        </div>
                        <div class="flex justify-between items-center gap-2">
                            <span class="text-gray-400">Status</span>
                            <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-bold', statusCfg[selectedCm.status]?.badge ?? '']">
                                <component :is="statusCfg[selectedCm.status]?.icon" class="w-3 h-3" />
                                {{ statusCfg[selectedCm.status]?.label ?? selectedCm.status }}
                            </span>
                        </div>
                        <template v-if="selectedCm.off_machine_at">
                            <div class="flex justify-between gap-2 pt-2 border-t border-gray-200 dark:border-gray-600">
                                <span class="text-gray-400 flex items-center gap-1"><ArrowDownToLine class="w-3 h-3" /> Turun dari Mesin</span>
                                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ fmtDatetime(selectedCm.off_machine_at) }}</span>
                            </div>
                            <div v-if="selectedCm.machine_duration" class="flex justify-between gap-2">
                                <span class="text-gray-400 flex items-center gap-1"><Timer class="w-3 h-3" /> Durasi Perbaikan di atas Mesin</span>
                                <span class="font-black text-orange-600 dark:text-orange-400">{{ selectedCm.machine_duration }}</span>
                            </div>
                        </template>
                        <div v-if="selectedCm.repair_duration" class="flex justify-between gap-2 pt-2 border-t border-gray-200 dark:border-gray-600">
                            <span class="text-gray-400 flex items-center gap-1"><Timer class="w-3 h-3" /> Durasi Total</span>
                            <span class="font-black text-violet-600 dark:text-violet-400">{{ selectedCm.repair_duration }}</span>
                        </div>
                        <div v-if="selectedCm.status === 'closed'" class="flex justify-between gap-2">
                            <span class="text-gray-400">Ditutup</span>
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400 text-right">{{ selectedCm.closed_by?.name ?? '-' }} · {{ fmtDatetime(selectedCm.closed_at) }}</span>
                        </div>
                    </div>

                    <div v-if="selectedCm.problem_description" class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3 border border-red-100 dark:border-red-800">
                        <p class="text-xs font-bold text-red-500 uppercase mb-1">Problem</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedCm.problem_description }}</p>
                    </div>
                    <div v-if="selectedCm.cause" class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-3 border border-amber-100 dark:border-amber-800">
                        <p class="text-xs font-bold text-amber-600 uppercase mb-1">Cause</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedCm.cause }}</p>
                    </div>
                    <div v-if="selectedCm.repair_action" class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-3 border border-emerald-100 dark:border-emerald-800">
                        <p class="text-xs font-bold text-emerald-600 uppercase mb-1">Tindakan</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedCm.repair_action }}</p>
                    </div>
                    <div v-if="selectedCm.action" class="bg-indigo-50 dark:bg-indigo-900/20 rounded-xl p-3 border border-indigo-100 dark:border-indigo-800">
                        <p class="text-xs font-bold text-indigo-600 uppercase mb-1">Kesimpulan</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedCm.action }}</p>
                    </div>

                    <div v-if="selectedCm.photos?.filter(p => p.type === 'before').length">
                        <p class="text-xs font-bold text-red-500 uppercase mb-2 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span>
                            Before
                        </p>
                        <div class="grid grid-cols-3 gap-2">
                            <div v-for="(ph, idx) in selectedCm.photos.filter(p => p.type === 'before')" :key="idx"
                                @click="lightboxSrc = '/storage/' + ph.path"
                                class="aspect-square rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-700 cursor-pointer group">
                                <img :src="'/storage/' + ph.path" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                            </div>
                        </div>
                    </div>
                    <div v-if="selectedCm.photos?.filter(p => p.type === 'after').length">
                        <p class="text-xs font-bold text-emerald-600 uppercase mb-2 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span>
                            After
                        </p>
                        <div class="grid grid-cols-3 gap-2">
                            <div v-for="(ph, idx) in selectedCm.photos.filter(p => p.type === 'after')" :key="idx"
                                @click="lightboxSrc = '/storage/' + ph.path"
                                class="aspect-square rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-700 cursor-pointer group">
                                <img :src="'/storage/' + ph.path" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedCm.spareparts?.length">
                        <p class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-1.5">
                            <Package class="w-3.5 h-3.5 text-indigo-500" /> Sparepart
                        </p>
                        <div class="space-y-1">
                            <div v-for="h in selectedCm.spareparts" :key="h.id"
                                class="flex justify-between items-center px-3 py-2 bg-gray-50 dark:bg-gray-700/50 rounded-xl text-xs">
                                <span class="font-semibold text-gray-800 dark:text-white">{{ h.sparepart?.sparepart_name ?? '-' }}</span>
                                <span class="text-gray-500">{{ h.quantity }} {{ h.sparepart?.unit }}</span>
                            </div>
                        </div>
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

        <!-- ====== MODAL TURUN MESIN ====== -->
        <div v-if="showOffMachineModal && selectedCm"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <ArrowDownToLine class="w-5 h-5 text-blue-600" /> Turun dari Mesin
                    </h2>
                    <button @click="showOffMachineModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <div class="p-4 sm:p-5 space-y-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3.5 border border-blue-100 dark:border-blue-800">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedCm.dies?.no_part ?? selectedCm.dies_id }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ selectedCm.dies?.nama_dies }}</p>
                        <p v-if="selectedCm.process" class="text-xs text-purple-600 dark:text-purple-400 mt-0.5 flex items-center gap-1">
                            <Layers class="w-3 h-3" /> {{ selectedCm.process.process_name }}
                        </p>
                        <p class="text-xs text-gray-400 mt-1">Laporan: {{ fmtDatetime(selectedCm.report_date) }}</p>
                    </div>
                    <p class="text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 rounded-xl p-3">
                        <AlertCircle class="w-3.5 h-3.5 flex-shrink-0" />
                        Durasi di mesin dihitung otomatis. Status berubah ke <strong>Repair di Bengkel</strong>.
                    </p>
                    <div class="flex gap-3">
                        <button type="button" @click="showOffMachineModal = false"
                            class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button type="button" @click="submitOffMachine"
                            class="flex-1 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-bold text-sm active:scale-95 transition-all flex items-center justify-center gap-2">
                            <ArrowDownToLine class="w-4 h-4" /> Konfirmasi Turun
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ====== MODAL CLOSE ====== -->
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
                        <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                            <span v-if="selectedCm.process" class="text-xs text-purple-600 dark:text-purple-400 flex items-center gap-1">
                                <Layers class="w-3 h-3" /> {{ selectedCm.process.process_name }}
                            </span>
                            <span v-if="selectedCm.machine_duration" class="text-xs text-orange-600 dark:text-orange-400 flex items-center gap-1 font-semibold">
                                <ArrowDownToLine class="w-3 h-3" /> {{ selectedCm.machine_duration }}
                            </span>
                        </div>
                        <p class="text-xs text-violet-500 font-semibold mt-1.5 flex items-center gap-1">
                            <Timer class="w-3 h-3" /> Durasi total dihitung otomatis saat ditutup
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">
                            Kesimpulan / Action <span class="text-gray-400 font-normal text-xs">(opsional)</span>
                        </label>
                        <textarea v-model="closeForm.action" rows="4" placeholder="Tuliskan kesimpulan atau action penutupan..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-emerald-500 focus:outline-none resize-none transition-colors"></textarea>
                    </div>
                    <div class="flex gap-3">
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

        <!-- ====== MODAL DELETE ====== -->
        <div v-if="showDelModal && selectedCm"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="p-5 text-center">
                    <div class="w-11 h-11 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-3">
                        <AlertTriangle class="w-5 h-5 text-red-600" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">Hapus Laporan?</h3>
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ selectedCm.dies?.no_part ?? selectedCm.dies_id }}</p>
                    <p class="text-xs text-gray-400 mt-1 mb-5">Foto dan sparepart terkait akan ikut dihapus.</p>
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

        <!-- Lightbox -->
        <Teleport to="body">
            <div v-if="lightboxSrc" class="fixed inset-0 z-[60] bg-black/90 flex items-center justify-center p-4"
                @click="lightboxSrc = null">
                <button class="absolute top-4 right-4 p-2 bg-white/10 hover:bg-white/20 rounded-xl text-white">
                    <X class="w-5 h-5" />
                </button>
                <img :src="lightboxSrc" class="max-w-full max-h-full rounded-2xl object-contain" @click.stop />
            </div>
        </Teleport>

        <!-- Photo Picker -->
        <Teleport to="body">
            <div v-if="showPhotoPicker"
                class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] flex items-end justify-center p-4"
                @click.self="showPhotoPicker = false">
                <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-4"></div>
                    <p class="text-center text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 px-5 flex items-center justify-center gap-2">
                        Pilih Sumber Foto
                        <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', photoPickerType === 'before' ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-600']">
                            {{ photoPickerType === 'before' ? 'Before' : 'After' }}
                        </span>
                    </p>
                    <div class="grid grid-cols-2 gap-3 px-4 pb-5">
                        <button @click="triggerPhotoInput('cam')"
                            class="flex flex-col items-center gap-2.5 py-5 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold text-sm hover:bg-indigo-100 active:scale-95 transition-all">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Kamera
                        </button>
                        <button @click="triggerPhotoInput('gal')"
                            class="flex flex-col items-center gap-2.5 py-5 rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 font-semibold text-sm hover:bg-emerald-100 active:scale-95 transition-all">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Galeri
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
