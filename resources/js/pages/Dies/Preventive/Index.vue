<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick } from 'vue';
import {
    CheckCircle2, Search, Filter, Plus, Eye, Download, User, Calendar, Activity,
    Upload, Camera, Package, AlertCircle, Loader2, CalendarCheck,
    Layers, ChevronDown, ShieldCheck, AlertTriangle, X, Trash2
} from 'lucide-vue-next';
import * as XLSX from 'xlsx';

interface DiesProcess { id: number; process_name: string; tonase: number | null }
interface DiesMini { id_sap: string; no_part: string; nama_dies: string; line: string; current_stroke: number; std_stroke: number; processes: DiesProcess[] }
interface Sparepart { id: number; sparepart_code: string; sparepart_name: string; unit: string; stok: number }
interface HistorySp { id: number; quantity: number; notes: string | null; sparepart: Sparepart | null }
interface Preventive {
    id: number; report_no: string; dies_id: string; process_id: number | null; pic_name: string;
    report_date: string; stroke_at_maintenance: number;
    repair_action: string | null; photos: string[] | null;
    pic_dies: string | null; condition: 'ok' | 'nok' | null;
    nok_closed_by: number | null; nok_closed_at: string | null; nok_notes: string | null;
    status: string; completed_at: string | null; scheduled_date: string | null;
    dies: DiesMini | null; process: DiesProcess | null;
    spareparts: HistorySp[];
    created_by: { id: number; name: string } | null;
    nok_closed_by_user?: { name: string } | null;
}
interface Props {
    preventives: { data: Preventive[]; links: any[]; meta: any };
    scheduled:   Preventive[];
    diesList:    DiesMini[];
    spareparts:  Sparepart[];
    isLeader:    boolean;
    filters:     { search?: string; status?: string; dies_id?: string };
}

const props = defineProps<Props>();
const page  = usePage();
const flash = computed(() => (page.props as any).flash);

const search       = ref(props.filters.search  ?? '');
const filterStatus = ref(props.filters.status  ?? '');
const filterDies   = ref(props.filters.dies_id ?? '');
const showFilter   = ref(false);

const showDetailModal   = ref(false);
const showCompleteModal = ref(false);
const showCloseNokModal = ref(false);
const showFormModal     = ref(false);
const isEdit            = ref(false);
const selectedPm        = ref<Preventive | null>(null);
const lightboxSrc       = ref<string | null>(null);

const statusCfg: Record<string, { label: string; badge: string; dot: string }> = {
    scheduled:   { label: 'Scheduled',   badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',             dot: 'bg-blue-500' },
    pending:     { label: 'Pending',     badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',                 dot: 'bg-gray-400' },
    in_progress: { label: 'In Progress', badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',         dot: 'bg-amber-400' },
    completed:   { label: 'Completed',   badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400', dot: 'bg-emerald-500' },
};

const allData = computed(() => {
    const scheduled = props.scheduled ?? [];
    const rest      = props.preventives.data ?? [];
    return [...scheduled, ...rest];
});

const totalLabel = computed(() => {
    const count = allData.value.length;
    const total = props.preventives.meta?.total ?? 0;
    if (count > 0 && total === 0) return `${count} laporan`;
    return `${total} laporan`;
});

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

const diesSearch      = ref('');
const diesOpen        = ref(false);
const selectedDiesObj = computed(() => props.diesList.find(d => d.id_sap === filterDies.value) ?? null);
const filteredDies    = computed(() => {
    const q = diesSearch.value.toLowerCase().trim();
    if (!q) return props.diesList;
    return props.diesList.filter(d => d.no_part.toLowerCase().includes(q) || d.nama_dies.toLowerCase().includes(q) || d.line.toLowerCase().includes(q));
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
    dies_id: '', process_id: null as number | null,
    repair_action: '', status: 'completed' as string,
    photos: [] as File[],
    spareparts: [] as { sparepart_id: number | null; quantity: string; notes: string }[],
});
const formDiesSearch      = ref('');
const formDiesOpen        = ref(false);
const selectedFormDiesObj = computed(() => props.diesList.find(d => d.id_sap === form.dies_id) ?? null);
const formPct             = computed(() => {
    if (!selectedFormDiesObj.value?.std_stroke) return 0;
    return Math.min(Math.round(selectedFormDiesObj.value.current_stroke / selectedFormDiesObj.value.std_stroke * 100), 100);
});
const filteredFormDies = computed(() => {
    const q = formDiesSearch.value.toLowerCase().trim();
    if (!q) return props.diesList;
    return props.diesList.filter(d => d.no_part.toLowerCase().includes(q) || d.nama_dies.toLowerCase().includes(q) || d.line.toLowerCase().includes(q));
});
const selectFormDies        = (d: DiesMini) => { form.dies_id = d.id_sap; form.process_id = null; formDiesSearch.value = d.no_part; formDiesOpen.value = false; };
const clearFormDies         = () => { form.dies_id = ''; form.process_id = null; formDiesSearch.value = ''; formDiesOpen.value = true; };
const closeFormDiesDropdown = () => { setTimeout(() => { formDiesOpen.value = false; }, 180); };
const availableProcesses    = computed(() => selectedFormDiesObj.value?.processes ?? []);
const processOpen           = ref(false);
const selectedProcessObj    = computed(() => availableProcesses.value.find(p => p.id === form.process_id) ?? null);
watch(() => form.dies_id, () => { form.process_id = null; });

const statusList = [
    { v: 'pending',     l: 'Pending',     hint: 'Belum mulai dikerjakan' },
    { v: 'in_progress', l: 'In Progress', hint: 'Sedang dalam proses' },
    { v: 'completed',   l: 'Completed',   hint: 'PM selesai dilakukan' },
];

const completeForm = useForm({
    photo: null as File | null, photo_sparepart: null as File | null,
    condition: 'ok' as 'ok' | 'nok', repair_action: '',
    spareparts: [] as { sparepart_id: number | null; quantity: string; notes: string }[],
});
const previewPhoto    = ref<string | null>(null);
const previewSpPhoto  = ref<string | null>(null);
const isCompressingA  = ref(false);
const isCompressingB  = ref(false);
const showPhotoPicker  = ref(false);
const photoPickerField = ref<'photo' | 'photo_sparepart' | null>(null);

const nokForm = useForm({ nok_notes: '' });

const spSearch = ref<string[]>([]);
const spOpen   = ref<boolean[]>([]);
const filteredSp      = (i: number) => { const q = (spSearch.value[i] ?? '').toLowerCase().trim(); return !q ? props.spareparts : props.spareparts.filter(s => s.sparepart_name.toLowerCase().includes(q) || s.sparepart_code.toLowerCase().includes(q)); };
const selectSp        = (i: number, s: Sparepart) => { form.spareparts[i].sparepart_id = s.id; spSearch.value[i] = s.sparepart_name; spOpen.value[i] = false; };
const openSpDropdown  = (i: number) => { const ex = form.spareparts[i]?.sparepart_id; if (ex) { const sp = props.spareparts.find(s => s.id === ex); if (sp && !spSearch.value[i]) spSearch.value[i] = sp.sparepart_name; } spOpen.value[i] = true; };
const closeSpDropdown = (i: number) => { setTimeout(() => { spOpen.value[i] = false; }, 180); };
const clearSp         = (i: number) => { form.spareparts[i].sparepart_id = null; spSearch.value[i] = ''; spOpen.value[i] = true; };
const addSp           = () => { form.spareparts.push({ sparepart_id: null, quantity: '', notes: '' }); spSearch.value.push(''); spOpen.value.push(false); };
const removeSp        = (i: number) => { form.spareparts.splice(i, 1); spSearch.value.splice(i, 1); spOpen.value.splice(i, 1); };

const cspSearch = ref<string[]>([]);
const cspOpen   = ref<boolean[]>([]);
const filteredCsp      = (i: number) => { const q = (cspSearch.value[i] ?? '').toLowerCase().trim(); return !q ? props.spareparts : props.spareparts.filter(s => s.sparepart_name.toLowerCase().includes(q) || s.sparepart_code.toLowerCase().includes(q)); };
const selectCsp        = (i: number, s: Sparepart) => { completeForm.spareparts[i].sparepart_id = s.id; cspSearch.value[i] = s.sparepart_name; cspOpen.value[i] = false; };
const openCspDropdown  = (i: number) => { const ex = completeForm.spareparts[i]?.sparepart_id; if (ex) { const sp = props.spareparts.find(s => s.id === ex); if (sp && !cspSearch.value[i]) cspSearch.value[i] = sp.sparepart_name; } cspOpen.value[i] = true; };
const closeCspDropdown = (i: number) => { setTimeout(() => { cspOpen.value[i] = false; }, 180); };
const clearCsp         = (i: number) => { completeForm.spareparts[i].sparepart_id = null; cspSearch.value[i] = ''; cspOpen.value[i] = true; };
const addCsp           = () => { completeForm.spareparts.push({ sparepart_id: null, quantity: '', notes: '' }); cspSearch.value.push(''); cspOpen.value.push(false); };
const removeCsp        = (i: number) => { completeForm.spareparts.splice(i, 1); cspSearch.value.splice(i, 1); cspOpen.value.splice(i, 1); };
const selectedSpItem   = (id: number | null) => props.spareparts.find(s => s.id === id);

const compressImage = (file: File): Promise<File> =>
    new Promise(resolve => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let w = img.width, h = img.height; const max = 1920;
                if (w > max || h > max) { if (w > h) { h = (h / w) * max; w = max; } else { w = (w / h) * max; h = max; } }
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d')!.drawImage(img, 0, 0, w, h);
                canvas.toBlob(blob => resolve(new File([blob!], 'photo.jpg', { type: 'image/jpeg' })), 'image/jpeg', 0.8);
            };
            img.src = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    });

const openPhotoPicker = (field: 'photo' | 'photo_sparepart') => { photoPickerField.value = field; showPhotoPicker.value = true; };
const triggerInput    = (type: 'cam' | 'gal') => {
    showPhotoPicker.value = false;
    nextTick(() => { (document.getElementById(`${type}-${photoPickerField.value}`) as HTMLInputElement)?.click(); });
};
const handleCompletePhoto = async (e: Event, field: 'photo' | 'photo_sparepart') => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    if (field === 'photo') {
        isCompressingA.value = true;
        completeForm.photo = await compressImage(file);
        const r = new FileReader(); r.onload = ev => { previewPhoto.value = ev.target?.result as string; }; r.readAsDataURL(completeForm.photo);
        isCompressingA.value = false;
    } else {
        isCompressingB.value = true;
        completeForm.photo_sparepart = await compressImage(file);
        const r = new FileReader(); r.onload = ev => { previewSpPhoto.value = ev.target?.result as string; }; r.readAsDataURL(completeForm.photo_sparepart);
        isCompressingB.value = false;
    }
    (e.target as HTMLInputElement).value = '';
};

const newPhotoPreview = ref<{ file: File; url: string }[]>([]);
const isCompressing   = ref(false);
const handleFileInput = async (e: Event) => {
    const files = Array.from((e.target as HTMLInputElement).files ?? []);
    if (!files.length) return;
    isCompressing.value = true;
    try { for (const f of files) { const c = await compressImage(f); newPhotoPreview.value.push({ file: c, url: URL.createObjectURL(c) }); } form.photos = newPhotoPreview.value.map(p => p.file); }
    finally { isCompressing.value = false; }
    (e.target as HTMLInputElement).value = '';
};
const removeNewPhoto = (idx: number) => { URL.revokeObjectURL(newPhotoPreview.value[idx].url); newPhotoPreview.value.splice(idx, 1); form.photos = newPhotoPreview.value.map(p => p.file); };

const openAdd = () => {
    isEdit.value = false; selectedPm.value = null;
    form.dies_id = ''; form.process_id = null; form.repair_action = ''; form.status = 'completed'; form.photos = []; form.spareparts = [];
    form.clearErrors(); formDiesSearch.value = ''; formDiesOpen.value = false; processOpen.value = false; spSearch.value = []; spOpen.value = [];
    newPhotoPreview.value.forEach(p => URL.revokeObjectURL(p.url)); newPhotoPreview.value = [];
    showFormModal.value = true;
};
const openDetail = (p: Preventive) => { selectedPm.value = p; showDetailModal.value = true; };
const openComplete = (p: Preventive) => {
    selectedPm.value = p; completeForm.reset(); completeForm.spareparts = []; cspSearch.value = []; cspOpen.value = [];
    previewPhoto.value = null; previewSpPhoto.value = null; showCompleteModal.value = true;
};
const openCloseNok = (p: Preventive) => { selectedPm.value = p; nokForm.reset(); showCloseNokModal.value = true; };
const closeFormModal     = () => { showFormModal.value = false; newPhotoPreview.value.forEach(p => URL.revokeObjectURL(p.url)); newPhotoPreview.value = []; };
const closeCompleteModal = () => { showCompleteModal.value = false; selectedPm.value = null; previewPhoto.value = null; previewSpPhoto.value = null; };

const submitForm = () => {
    router.post('/dies/preventive', {
        dies_id: form.dies_id, process_id: form.process_id,
        repair_action: form.repair_action, status: form.status,
        photos: form.photos, spareparts: form.spareparts,
    }, { forceFormData: true, onSuccess: () => closeFormModal() });
};
const submitComplete = () => {
    if (!selectedPm.value) return;
    completeForm.post(`/dies/preventive/${selectedPm.value.id}/complete`, { onSuccess: () => closeCompleteModal() });
};
const submitCloseNok = () => {
    if (!selectedPm.value) return;
    nokForm.post(`/dies/preventive/${selectedPm.value.id}/close-nok`, {
        onSuccess: () => { showCloseNokModal.value = false; selectedPm.value = null; }
    });
};

const exportExcel = () => {
    const rows = [
        ['No Part', 'Nama Dies', 'Line', 'Proses', 'PIC', 'Tanggal', 'Stroke MTC', '% STD', 'Tindakan', 'Kondisi', 'Status', 'Completed At'],
        ...props.preventives.data.map(p => [
            p.dies?.no_part ?? p.dies_id, p.dies?.nama_dies ?? '', p.dies?.line ?? '', p.process?.process_name ?? '',
            p.pic_name, p.report_date, p.stroke_at_maintenance, `${pct(p)}%`, p.repair_action ?? '',
            p.condition?.toUpperCase() ?? '-', statusCfg[p.status]?.label ?? p.status,
            p.completed_at ? fmtDate(p.completed_at) : '',
        ]),
    ];
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [16, 30, 10, 20, 16, 12, 14, 8, 30, 10, 14, 14].map(w => ({ wch: w }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Preventive');
    XLSX.writeFile(wb, `Dies_PM_${new Date().toISOString().slice(0, 10)}.xlsx`);
};
</script>

<template>
    <Head title="Preventive Maintenance Dies" />
    <AppLayout :breadcrumbs="[{ title: 'Dies', href: '/dies' }, { title: 'Preventive', href: '/dies/preventive' }]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-blue-600 rounded-xl flex-shrink-0">
                            <CheckCircle2 class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
                        </span>
                        Preventive Maintenance
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">{{ totalLabel }}</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="exportExcel" class="flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold active:scale-95 transition-all">
                        <Download class="w-3.5 h-3.5" /><span class="hidden sm:inline">Export</span>
                    </button>
                    <button @click="openAdd" class="flex items-center gap-1.5 px-3 sm:px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-semibold active:scale-95 transition-all shadow-sm">
                        <Plus class="w-3.5 h-3.5" /><span class="hidden sm:inline">Tambah PM</span><span class="sm:hidden">Tambah</span>
                    </button>
                </div>
            </div>

            <div v-if="flash?.success" class="flex items-center gap-3 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                <p class="text-emerald-800 dark:text-emerald-200 font-medium text-xs sm:text-sm">{{ flash.success }}</p>
            </div>

            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                        <input v-model="search" type="text" placeholder="Cari no part, PIC..."
                            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-blue-500 focus:outline-none transition-colors" />
                    </div>
                    <button @click="showFilter = !showFilter"
                        :class="['relative flex items-center gap-1.5 px-3 py-2.5 border rounded-xl text-sm font-medium transition-colors',
                            showFilter || activeFilterCount > 0 ? 'bg-blue-600 border-blue-600 text-white' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-blue-400']">
                        <Filter class="w-4 h-4" />
                        <span v-if="activeFilterCount > 0" class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold">{{ activeFilterCount }}</span>
                    </button>
                </div>

                <div v-if="showFilter" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-3 space-y-3 shadow-sm">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Status</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="s in [{ v: '', l: 'Semua' }, ...Object.entries(statusCfg).map(([v, c]) => ({ v, l: c.label }))]" :key="s.v"
                                @click="filterStatus = s.v"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors', filterStatus === s.v ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
                                {{ s.l }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Dies</label>
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                            <input v-model="diesSearch" type="text" placeholder="Cari no part / nama dies / line..." autocomplete="off"
                                @focus="diesOpen = true" @blur="closeDiesDropdown"
                                :class="['w-full pl-8 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                    selectedDiesObj ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-blue-400']" />
                            <button v-if="selectedDiesObj" type="button" @click="clearDiesFilter" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500"><X class="w-4 h-4" /></button>
                            <div v-if="diesOpen" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-56 overflow-y-auto">
                                <div v-if="filteredDies.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                <button v-for="d in filteredDies" :key="d.id_sap" type="button" @mousedown.prevent="selectDiesFilter(d)"
                                    :class="['w-full text-left px-3 py-2.5 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors', selectedDiesObj?.id_sap === d.id_sap ? 'bg-blue-50 dark:bg-blue-900/20' : '']">
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
                        <button @click="filterStatus = ''; clearDiesFilter()" class="text-xs text-blue-500 font-semibold hover:underline">Reset filter</button>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 overflow-x-auto scrollbar-none pb-0.5">
                <button v-for="[v, c] in Object.entries(statusCfg)" :key="v"
                    @click="filterStatus = filterStatus === v ? '' : v"
                    :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all flex-shrink-0',
                        filterStatus === v ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:border-blue-300']">
                    <span :class="['w-1.5 h-1.5 rounded-full', c.dot]"></span>
                    {{ c.label }}
                    <span class="font-bold">{{ allData.filter(x => x.status === v).length }}</span>
                </button>
            </div>

            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Dies / Proses</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Tindakan</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">PIC</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Tanggal</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Stroke / %</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Kondisi</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="allData.length === 0">
                                <td colspan="8" class="py-16 text-center text-gray-400 text-sm">
                                    <CheckCircle2 class="w-10 h-10 mx-auto mb-2 text-gray-300" /> Tidak ada data preventive
                                </td>
                            </tr>
                            <tr v-for="p in allData" :key="p.id"
                                :class="['transition-colors', p.status === 'scheduled' ? 'bg-blue-50/50 dark:bg-blue-900/10 hover:bg-blue-50 dark:hover:bg-blue-900/20' : 'hover:bg-gray-50/80 dark:hover:bg-gray-700/30']">
                                <td class="px-4 py-3">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ p.dies?.no_part ?? p.dies_id }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ p.dies?.nama_dies }}</p>
                                    <div class="flex items-center gap-1 mt-1 flex-wrap">
                                        <span v-if="p.dies?.line" class="text-xs px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded font-semibold">{{ p.dies.line }}</span>
                                        <span v-if="p.process" class="text-xs px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 rounded font-semibold flex items-center gap-1">
                                            <Layers class="w-2.5 h-2.5" />{{ p.process.process_name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 max-w-xs">
                                    <div v-if="p.status === 'scheduled'" class="text-xs text-blue-600 dark:text-blue-400 font-semibold flex items-center gap-1">
                                        <CalendarCheck class="w-3 h-3" /> {{ fmtDate(p.scheduled_date) }}
                                    </div>
                                    <p v-else-if="p.repair_action" class="text-xs text-gray-500 line-clamp-2">{{ p.repair_action }}</p>
                                    <span v-else class="text-xs text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">{{ p.pic_name }}</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ fmtDate(p.report_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <p class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ p.stroke_at_maintenance.toLocaleString() }}</p>
                                    <p class="text-xs" :class="pct(p) >= 100 ? 'text-red-500' : pct(p) >= 85 ? 'text-amber-500' : 'text-emerald-500'">{{ pct(p) }}%</p>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <span v-if="p.condition" :class="['px-2 py-0.5 rounded-full text-xs font-bold', p.condition === 'ok' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400']">{{ p.condition.toUpperCase() }}</span>
                                        <span v-else class="text-xs text-gray-300">—</span>
                                        <span v-if="p.condition === 'nok' && p.nok_closed_at" class="text-xs text-emerald-500 italic">closed</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-block px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[p.status]?.badge ?? '']">{{ statusCfg[p.status]?.label ?? p.status }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button v-if="p.status === 'scheduled'" @click="openComplete(p)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-xs font-semibold transition-colors active:scale-95">
                                            <Upload class="w-3 h-3" /> Submit
                                        </button>
                                        <button v-if="p.condition === 'nok' && !p.nok_closed_at && isLeader" @click="openCloseNok(p)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-semibold transition-colors active:scale-95">
                                            <ShieldCheck class="w-3 h-3" /> Close NOK
                                        </button>
                                        <button @click="openDetail(p)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-200 transition-colors">
                                            <Eye class="w-3 h-3" /> Detail
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="preventives.links && preventives.links.length > 3" class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-500">
                        <template v-if="preventives.meta?.from">{{ preventives.meta.from }}–{{ preventives.meta.to }} dari {{ preventives.meta.total }}</template>
                        <template v-else>{{ allData.length }} laporan</template>
                    </p>
                    <div class="flex gap-1">
                        <button v-for="link in preventives.links" :key="link.label" @click="link.url && router.visit(link.url)" :disabled="!link.url" v-html="link.label"
                            :class="['px-3 py-1.5 text-xs rounded-lg font-semibold transition-colors', link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200' : 'opacity-40 cursor-default bg-gray-100 dark:bg-gray-700 text-gray-400']">
                        </button>
                    </div>
                </div>
            </div>

            <div class="lg:hidden space-y-2.5">
                <div v-if="allData.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <CheckCircle2 class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                    <p class="text-gray-400 text-sm">Tidak ada data preventive</p>
                </div>
                <div v-for="p in allData" :key="p.id"
                    :class="['rounded-2xl border shadow-sm overflow-hidden',
                        p.status === 'scheduled'
                            ? 'bg-blue-50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-800'
                            : 'bg-white dark:bg-gray-800 border-gray-100 dark:border-gray-700']">
                    <div class="p-3.5">
                        <div class="flex items-start justify-between gap-2 mb-2.5">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ p.dies?.no_part ?? p.dies_id }}</p>
                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ p.dies?.nama_dies }}</p>
                                <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                    <span v-if="p.dies?.line" class="px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded text-xs font-semibold">{{ p.dies.line }}</span>
                                    <span v-if="p.process" class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 rounded text-xs font-semibold">
                                        <Layers class="w-2.5 h-2.5" />{{ p.process.process_name }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[p.status]?.badge ?? '']">{{ statusCfg[p.status]?.label ?? p.status }}</span>
                                <div v-if="p.condition" class="flex items-center gap-1">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-bold', p.condition === 'ok' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400']">{{ p.condition.toUpperCase() }}</span>
                                    <span v-if="p.condition === 'nok' && p.nok_closed_at" class="text-xs text-emerald-500 italic">✓</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="p.status === 'scheduled'" class="flex items-center gap-1.5 text-xs text-blue-600 dark:text-blue-400 font-semibold mb-2">
                            <CalendarCheck class="w-3.5 h-3.5" /> Dijadwalkan: {{ fmtDate(p.scheduled_date) }}
                        </div>

                        <div class="space-y-1.5 mb-3">
                            <p v-if="p.repair_action && p.status !== 'scheduled'" class="text-xs text-gray-500 line-clamp-2 px-1">{{ p.repair_action }}</p>
                            <div class="flex items-center gap-3 text-xs text-gray-500 px-1">
                                <span class="flex items-center gap-1"><User class="w-3 h-3" /> {{ p.pic_name }}</span>
                                <span class="flex items-center gap-1"><Calendar class="w-3 h-3" /> {{ fmtDate(p.report_date) }}</span>
                                <span v-if="p.spareparts?.length" class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400 rounded-full font-bold">
                                    <Package class="w-3 h-3" /> {{ p.spareparts.length }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 px-1">
                                <span class="text-xs text-gray-500">{{ p.stroke_at_maintenance.toLocaleString() }}</span>
                                <div class="flex-1 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div :class="['h-full rounded-full', pct(p) >= 100 ? 'bg-red-500' : pct(p) >= 85 ? 'bg-amber-400' : 'bg-emerald-400']" :style="{ width: `${pct(p)}%` }"></div>
                                </div>
                                <span :class="['text-xs font-bold', pct(p) >= 100 ? 'text-red-500' : pct(p) >= 85 ? 'text-amber-500' : 'text-emerald-500']">{{ pct(p) }}%</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-2.5 border-t" :class="p.status === 'scheduled' ? 'border-blue-200 dark:border-blue-800/50' : 'border-gray-100 dark:border-gray-700'">
                            <button v-if="p.status === 'scheduled'" @click="openComplete(p)"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-xs font-black active:scale-95 transition-all">
                                <Upload class="w-3.5 h-3.5" /> Submit PM
                            </button>
                            <button v-if="p.condition === 'nok' && !p.nok_closed_at && isLeader" @click="openCloseNok(p)"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold active:scale-95 transition-all">
                                <ShieldCheck class="w-3.5 h-3.5" /> Close NOK
                            </button>
                            <button @click="openDetail(p)"
                                :class="['flex items-center justify-center gap-1.5 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-bold hover:bg-gray-200 active:scale-95 transition-all',
                                    p.status !== 'scheduled' && !(p.condition === 'nok' && !p.nok_closed_at && isLeader) ? 'flex-1' : 'px-4']">
                                <Eye class="w-3.5 h-3.5" /> Detail
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="preventives.links && preventives.links.length > 3" class="flex justify-center gap-1 pt-2">
                    <button v-for="link in preventives.links" :key="link.label" @click="link.url && router.visit(link.url)" :disabled="!link.url" v-html="link.label"
                        :class="['px-3 py-1.5 text-xs rounded-lg font-semibold', link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' : 'opacity-40 cursor-default']">
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showCompleteModal && selectedPm" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-lg max-h-[95vh] overflow-y-auto shadow-2xl">
                <div class="sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <Upload class="w-4 h-4 text-orange-500" /> Submit Laporan PM
                        </h2>
                        <button @click="closeCompleteModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                    </div>
                </div>
                <form @submit.prevent="submitComplete" class="p-4 sm:p-5 space-y-4">
                    <div class="bg-orange-50 dark:bg-orange-900/20 rounded-xl p-3.5 border border-orange-100 dark:border-orange-800">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-black text-gray-900 dark:text-white">{{ selectedPm.dies?.no_part ?? selectedPm.dies_id }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ selectedPm.dies?.nama_dies }}</p>
                            </div>
                            <span v-if="selectedPm.process" class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 rounded-full text-xs font-bold flex-shrink-0">
                                <Layers class="w-3 h-3" /> {{ selectedPm.process.process_name }}
                            </span>
                        </div>
                        <div class="mt-2.5 flex items-center gap-2">
                            <div class="flex-1 h-2 bg-orange-200 dark:bg-orange-900/40 rounded-full overflow-hidden">
                                <div class="h-full bg-orange-500 rounded-full" :style="{ width: `${Math.min(pct(selectedPm), 100)}%` }"></div>
                            </div>
                            <span class="text-xs font-black text-orange-600">{{ pct(selectedPm) }}%</span>
                        </div>
                        <div class="mt-1 flex items-center gap-3 text-xs text-gray-500">
                            <span>Stroke: <span class="font-bold text-gray-700 dark:text-gray-300">{{ selectedPm.stroke_at_maintenance.toLocaleString() }}</span></span>
                            <span v-if="selectedPm.scheduled_date">Jadwal: <span class="font-bold text-orange-600">{{ fmtDate(selectedPm.scheduled_date) }}</span></span>
                        </div>
                    </div>

                    <div v-if="selectedPm.pic_dies">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1.5 flex items-center gap-1"><Camera class="w-3.5 h-3.5" /> Foto Dies (saat jadwal)</p>
                        <img :src="'/storage/' + selectedPm.pic_dies" @click="lightboxSrc = '/storage/' + selectedPm.pic_dies"
                            class="w-full h-32 object-cover rounded-xl cursor-pointer hover:opacity-90 transition-opacity" />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Kondisi Dies</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" @click="completeForm.condition = 'ok'"
                                :class="['py-3 rounded-xl font-bold text-sm transition-all active:scale-95', completeForm.condition === 'ok' ? 'bg-emerald-500 text-white shadow-md' : 'border-2 border-gray-200 text-gray-600 dark:border-gray-600 dark:text-gray-300']">✓ OK</button>
                            <button type="button" @click="completeForm.condition = 'nok'"
                                :class="['py-3 rounded-xl font-bold text-sm transition-all active:scale-95', completeForm.condition === 'nok' ? 'bg-red-500 text-white shadow-md' : 'border-2 border-gray-200 text-gray-600 dark:border-gray-600 dark:text-gray-300']">✗ NOK</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-2 text-gray-700 dark:text-gray-300">Foto Checksheet <span class="text-red-500">*</span></label>
                            <div @click="openPhotoPicker('photo')"
                                :class="['cursor-pointer flex flex-col items-center justify-center border-2 border-dashed rounded-xl transition-all min-h-[8rem]',
                                    previewPhoto ? 'border-orange-300 bg-orange-50 p-0 overflow-hidden' : 'border-gray-200 hover:border-orange-300 bg-gray-50 dark:bg-gray-700/30 p-3']">
                                <Loader2 v-if="isCompressingA" class="w-7 h-7 text-orange-400 animate-spin" />
                                <img v-else-if="previewPhoto" :src="previewPhoto" class="w-full h-full object-cover" style="min-height:8rem" />
                                <div v-else class="text-center"><Upload class="w-7 h-7 text-gray-400 mx-auto mb-1.5" /><p class="text-xs text-gray-500 font-medium">Upload foto</p><p class="text-xs text-gray-400 mt-0.5">Kamera / Galeri</p></div>
                            </div>
                            <input id="cam-photo" type="file" accept="image/*" capture="environment" @change="(e) => handleCompletePhoto(e, 'photo')" class="hidden" />
                            <input id="gal-photo" type="file" accept="image/*" @change="(e) => handleCompletePhoto(e, 'photo')" class="hidden" />
                            <p v-if="completeForm.errors.photo" class="mt-1 text-xs text-red-500">{{ completeForm.errors.photo }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-2 text-gray-700 dark:text-gray-300">Foto Sparepart</label>
                            <div @click="openPhotoPicker('photo_sparepart')"
                                :class="['cursor-pointer flex flex-col items-center justify-center border-2 border-dashed rounded-xl transition-all min-h-[8rem]',
                                    previewSpPhoto ? 'border-indigo-300 bg-indigo-50 p-0 overflow-hidden' : 'border-gray-200 hover:border-indigo-300 bg-gray-50 dark:bg-gray-700/30 p-3']">
                                <Loader2 v-if="isCompressingB" class="w-7 h-7 text-indigo-400 animate-spin" />
                                <img v-else-if="previewSpPhoto" :src="previewSpPhoto" class="w-full h-full object-cover" style="min-height:8rem" />
                                <div v-else class="text-center"><Package class="w-7 h-7 text-gray-400 mx-auto mb-1.5" /><p class="text-xs text-gray-500 font-medium">Jika ada</p></div>
                            </div>
                            <input id="cam-photo_sparepart" type="file" accept="image/*" capture="environment" @change="(e) => handleCompletePhoto(e, 'photo_sparepart')" class="hidden" />
                            <input id="gal-photo_sparepart" type="file" accept="image/*" @change="(e) => handleCompletePhoto(e, 'photo_sparepart')" class="hidden" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Tindakan / Hasil PM</label>
                        <textarea v-model="completeForm.repair_action" rows="3" placeholder="Deskripsikan tindakan yang dilakukan..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-orange-500 focus:outline-none resize-none"></textarea>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5"><Package class="w-3.5 h-3.5 text-indigo-500" /> Sparepart Digunakan</label>
                            <button type="button" @click="addCsp" class="flex items-center gap-1.5 px-3 py-1.5 text-xs bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400 rounded-lg font-semibold hover:bg-indigo-200 active:scale-95 transition-all">
                                <Plus class="w-3 h-3" /> Tambah
                            </button>
                        </div>
                        <div v-if="completeForm.spareparts.length === 0" class="py-3 text-center text-xs text-gray-400">Belum ada sparepart</div>
                        <div v-for="(sp, i) in completeForm.spareparts" :key="i" class="mb-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl space-y-2.5">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                                    <input v-model="cspSearch[i]" type="text" placeholder="Cari sparepart..." autocomplete="off"
                                        @focus="openCspDropdown(i)" @blur="closeCspDropdown(i)"
                                        :class="['w-full pl-7 pr-7 py-2.5 border rounded-xl text-xs focus:outline-none transition-colors dark:bg-gray-700',
                                            sp.sparepart_id ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-indigo-400']" />
                                    <button v-if="sp.sparepart_id" type="button" @click="clearCsp(i)" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 p-0.5"><X class="w-3 h-3" /></button>
                                    <div v-if="cspOpen[i]" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                        <div v-if="filteredCsp(i).length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                        <button v-for="s in filteredCsp(i)" :key="s.id" type="button" @mousedown.prevent="selectCsp(i, s)"
                                            :class="['w-full text-left px-3 py-2.5 text-xs hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors flex items-center justify-between gap-2',
                                                sp.sparepart_id === s.id ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-gray-700 dark:text-gray-300']">
                                            <span>
                                                <template v-for="(part, pi) in highlightMatch(s.sparepart_name, cspSearch[i] ?? '')" :key="pi">
                                                    <mark v-if="part.match" class="bg-yellow-200 dark:bg-yellow-700 text-gray-900 dark:text-white rounded px-0.5 not-italic">{{ part.text }}</mark>
                                                    <span v-else>{{ part.text }}</span>
                                                </template>
                                            </span>
                                            <span class="text-gray-400 whitespace-nowrap shrink-0">{{ s.stok }} {{ s.unit }}</span>
                                        </button>
                                    </div>
                                </div>
                                <input v-model="sp.quantity" type="number" inputmode="numeric" min="1" placeholder="Qty" class="w-16 px-2 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none text-center" />
                                <button type="button" @click="removeCsp(i)" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg active:scale-95"><Trash2 class="w-4 h-4" /></button>
                            </div>
                            <input v-model="sp.notes" type="text" placeholder="Keterangan (opsional)" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none" />
                            <p v-if="sp.sparepart_id && selectedSpItem(sp.sparepart_id) && parseInt(sp.quantity) > selectedSpItem(sp.sparepart_id)!.stok" class="text-xs text-red-500 flex items-center gap-1"><AlertCircle class="w-3 h-3" /> Stok tidak cukup (tersedia: {{ selectedSpItem(sp.sparepart_id)!.stok }})</p>
                        </div>
                    </div>

                    <div class="sticky bottom-0 bg-white dark:bg-gray-800 pt-3 pb-4 border-t border-gray-100 dark:border-gray-700 -mx-4 sm:-mx-5 px-4 sm:px-5">
                        <div class="flex gap-3">
                            <button type="button" @click="closeCompleteModal" class="px-5 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">Batal</button>
                            <button type="submit" :disabled="completeForm.processing || isCompressingA || isCompressingB || !completeForm.photo"
                                class="flex-1 py-3 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold text-sm active:scale-95 transition-all disabled:opacity-50">
                                {{ completeForm.processing ? 'Menyimpan...' : 'Submit Laporan PM' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showCloseNokModal && selectedPm" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2"><ShieldCheck class="w-5 h-5 text-emerald-600" /> Close NOK</h2>
                    <button @click="showCloseNokModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                </div>
                <form @submit.prevent="submitCloseNok" class="p-4 sm:p-5 space-y-4">
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3.5 border border-red-100 dark:border-red-800">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedPm.dies?.no_part ?? selectedPm.dies_id }}</p>
                        <p class="text-xs text-red-600 dark:text-red-400 mt-0.5 font-semibold">Kondisi: NOK</p>
                        <p v-if="selectedPm.process" class="text-xs text-purple-600 dark:text-purple-400 mt-0.5 flex items-center gap-1"><Layers class="w-3 h-3" /> {{ selectedPm.process.process_name }}</p>
                    </div>
                    <div class="flex items-start gap-2 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-800">
                        <AlertTriangle class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" />
                        <p class="text-xs text-amber-700 dark:text-amber-300">Kondisi dies akan berubah menjadi <strong class="text-emerald-600">OK</strong> setelah di-close.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Catatan Penyelesaian</label>
                        <textarea v-model="nokForm.nok_notes" rows="4" placeholder="Tindakan yang dilakukan untuk menyelesaikan NOK..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-emerald-500 focus:outline-none resize-none"></textarea>
                    </div>
                    <div class="flex gap-3 pb-safe">
                        <button type="button" @click="showCloseNokModal = false" class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">Batal</button>
                        <button type="submit" :disabled="nokForm.processing" class="flex-1 py-3 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 disabled:opacity-50 font-bold text-sm active:scale-95 transition-all">
                            {{ nokForm.processing ? 'Menyimpan...' : 'Konfirmasi OK' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showFormModal" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-2xl max-h-[95vh] flex flex-col shadow-2xl">
                <div class="flex-shrink-0">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2"><CheckCircle2 class="w-4 h-4 text-blue-600" /> Tambah Preventive Maintenance</h2>
                        <button @click="closeFormModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                    </div>
                </div>
                <form @submit.prevent="submitForm" class="overflow-y-auto flex-1 px-4 sm:px-6 py-4 space-y-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl px-4 py-3 border border-blue-100 dark:border-blue-900/40">
                        <h3 class="text-xs font-bold text-blue-700 dark:text-blue-400 uppercase mb-3 flex items-center gap-1.5"><Activity class="w-3.5 h-3.5" /> Identifikasi</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Dies <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                                    <input v-model="formDiesSearch" type="text" placeholder="Cari no part / nama dies / line..." autocomplete="off"
                                        @focus="formDiesOpen = true" @blur="closeFormDiesDropdown"
                                        :class="['w-full pl-8 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                            form.dies_id ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-blue-400']" />
                                    <button v-if="form.dies_id" type="button" @click="clearFormDies" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500"><X class="w-4 h-4" /></button>
                                    <div v-if="formDiesOpen" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-56 overflow-y-auto">
                                        <div v-if="filteredFormDies.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                        <button v-for="d in filteredFormDies" :key="d.id_sap" type="button" @mousedown.prevent="selectFormDies(d)"
                                            :class="['w-full text-left px-3 py-2.5 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors', form.dies_id === d.id_sap ? 'bg-blue-50 dark:bg-blue-900/20' : '']">
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
                                <div v-if="selectedFormDiesObj" class="mt-2 grid grid-cols-3 gap-2 text-center">
                                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-2"><p class="text-xs text-blue-500 font-semibold">Current</p><p class="text-sm font-black text-blue-700 dark:text-blue-300">{{ selectedFormDiesObj.current_stroke.toLocaleString() }}</p></div>
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-2"><p class="text-xs text-gray-500 font-semibold">STD</p><p class="text-sm font-black text-gray-700 dark:text-gray-300">{{ selectedFormDiesObj.std_stroke.toLocaleString() }}</p></div>
                                    <div :class="['rounded-xl p-2', formPct >= 100 ? 'bg-red-50 dark:bg-red-900/20' : formPct >= 85 ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-emerald-50 dark:bg-emerald-900/20']">
                                        <p :class="['text-xs font-semibold', formPct >= 100 ? 'text-red-500' : formPct >= 85 ? 'text-amber-500' : 'text-emerald-500']">%</p>
                                        <p :class="['text-sm font-black', formPct >= 100 ? 'text-red-600' : formPct >= 85 ? 'text-amber-600' : 'text-emerald-600']">{{ formPct }}%</p>
                                    </div>
                                </div>
                                <p v-if="form.errors.dies_id" class="mt-1 text-xs text-red-500">{{ form.errors.dies_id }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Proses <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <button type="button" @click="processOpen = !processOpen" :disabled="!form.dies_id"
                                        :class="['w-full flex items-center justify-between px-3 py-2.5 border rounded-xl text-sm transition-colors dark:bg-gray-700',
                                            !form.dies_id ? 'opacity-50 cursor-not-allowed border-gray-200 dark:border-gray-600' :
                                            form.process_id ? 'border-purple-400 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-semibold' :
                                            'border-gray-200 dark:border-gray-600 text-gray-500 hover:border-purple-400']">
                                        <span class="flex items-center gap-2"><Layers class="w-3.5 h-3.5 text-purple-400 flex-shrink-0" /><span>{{ selectedProcessObj ? selectedProcessObj.process_name : (form.dies_id ? 'Pilih proses...' : 'Pilih dies dulu') }}</span></span>
                                        <div class="flex items-center gap-1">
                                            <button v-if="form.process_id" type="button" @click.stop="form.process_id = null" class="p-0.5 text-gray-400 hover:text-red-500"><X class="w-3.5 h-3.5" /></button>
                                            <ChevronDown :class="['w-3.5 h-3.5 text-gray-400 transition-transform', processOpen ? 'rotate-180' : '']" />
                                        </div>
                                    </button>
                                    <div v-if="processOpen && form.dies_id" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                        <div v-if="availableProcesses.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada proses untuk dies ini</div>
                                        <button v-for="pr in availableProcesses" :key="pr.id" type="button" @mousedown.prevent="form.process_id = pr.id; processOpen = false"
                                            :class="['w-full text-left px-3 py-2.5 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors flex items-center justify-between', form.process_id === pr.id ? 'bg-purple-50 dark:bg-purple-900/20' : '']">
                                            <span class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ pr.process_name }}</span>
                                            <span v-if="pr.tonase" class="text-xs text-gray-400">{{ pr.tonase }} ton</span>
                                        </button>
                                    </div>
                                </div>
                                <p v-if="form.errors.process_id" class="mt-1 text-xs text-red-500">{{ form.errors.process_id }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 px-4 py-3">
                        <h3 class="text-xs font-bold text-gray-500 uppercase mb-3">Status</h3>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="s in statusList" :key="s.v" type="button" @click="form.status = s.v"
                                :class="['flex flex-col items-start p-2.5 rounded-xl border-2 text-left transition-all',
                                    form.status === s.v ? s.v === 'pending' ? 'border-gray-400 bg-gray-50 dark:bg-gray-700/50' : s.v === 'in_progress' ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/20' : 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300']">
                                <span :class="['text-xs font-bold', form.status === s.v ? s.v === 'pending' ? 'text-gray-700 dark:text-gray-300' : s.v === 'in_progress' ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400' : 'text-gray-500']">{{ s.l }}</span>
                                <span class="text-xs text-gray-400 mt-0.5 leading-tight">{{ s.hint }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl px-4 py-3 border border-emerald-100 dark:border-emerald-900/40">
                        <h3 class="text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase mb-3 flex items-center gap-1.5"><CheckCircle2 class="w-3.5 h-3.5" /> Tindakan <span class="text-gray-400 font-normal">(opsional)</span></h3>
                        <textarea v-model="form.repair_action" rows="3" placeholder="Deskripsikan tindakan yang dilakukan..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-emerald-500 focus:outline-none resize-none"></textarea>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 px-4 py-3">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-bold text-gray-500 uppercase flex items-center gap-1.5"><Camera class="w-3.5 h-3.5" /> Foto <span class="text-gray-400 font-normal">(opsional)</span></h3>
                            <span class="text-xs text-gray-400">{{ newPhotoPreview.length }} foto</span>
                        </div>
                        <div v-if="newPhotoPreview.length" class="grid grid-cols-4 gap-2 mb-2">
                            <div v-for="(ph, idx) in newPhotoPreview" :key="idx" class="relative rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-700 aspect-square ring-2 ring-blue-400">
                                <img :src="ph.url" class="w-full h-full object-cover" />
                                <button type="button" @click="removeNewPhoto(idx)" class="absolute top-1 right-1 p-1 bg-red-500 rounded-full text-white"><X class="w-3 h-3" /></button>
                            </div>
                        </div>
                        <label :class="['flex flex-col items-center justify-center gap-2 p-4 border-2 border-dashed rounded-xl transition-colors cursor-pointer', isCompressing ? 'border-blue-400 bg-blue-50/50' : 'border-gray-200 dark:border-gray-600 hover:border-blue-400']">
                            <Loader2 v-if="isCompressing" class="w-5 h-5 text-blue-400 animate-spin" />
                            <Upload v-else class="w-5 h-5 text-gray-400" />
                            <span class="text-xs text-gray-500 font-medium">{{ isCompressing ? 'Mengompres...' : 'Klik untuk upload foto' }}</span>
                            <input type="file" multiple accept="image/*" class="hidden" :disabled="isCompressing" @change="handleFileInput" />
                        </label>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 px-4 py-3">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-bold text-gray-500 uppercase flex items-center gap-1.5"><Package class="w-3.5 h-3.5 text-indigo-500" /> Sparepart <span class="text-gray-400 font-normal">(opsional)</span></h3>
                            <button type="button" @click="addSp" class="flex items-center gap-1.5 px-3 py-1.5 text-xs bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400 rounded-lg font-semibold hover:bg-indigo-200 active:scale-95 transition-all"><Plus class="w-3 h-3" /> Tambah</button>
                        </div>
                        <div v-if="form.spareparts.length === 0" class="py-3 text-center text-xs text-gray-400">Belum ada sparepart</div>
                        <div v-for="(sp, i) in form.spareparts" :key="i" class="mb-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl space-y-2.5">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                                    <input v-model="spSearch[i]" type="text" placeholder="Cari sparepart..." autocomplete="off"
                                        @focus="openSpDropdown(i)" @blur="closeSpDropdown(i)"
                                        :class="['w-full pl-7 pr-7 py-2.5 border rounded-xl text-xs focus:outline-none transition-colors dark:bg-gray-700',
                                            sp.sparepart_id ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-indigo-400']" />
                                    <button v-if="sp.sparepart_id" type="button" @click="clearSp(i)" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 p-0.5"><X class="w-3 h-3" /></button>
                                    <div v-if="spOpen[i]" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                        <div v-if="filteredSp(i).length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                        <button v-for="s in filteredSp(i)" :key="s.id" type="button" @mousedown.prevent="selectSp(i, s)"
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
                                <input v-model="sp.quantity" type="number" inputmode="numeric" min="1" placeholder="Qty" class="w-16 px-2 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none text-center" />
                                <button type="button" @click="removeSp(i)" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg active:scale-95"><Trash2 class="w-4 h-4" /></button>
                            </div>
                            <input v-model="sp.notes" type="text" placeholder="Keterangan (opsional)" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none" />
                            <p v-if="sp.sparepart_id && selectedSpItem(sp.sparepart_id) && parseInt(sp.quantity) > selectedSpItem(sp.sparepart_id)!.stok" class="text-xs text-red-500 flex items-center gap-1"><AlertCircle class="w-3 h-3" /> Stok tidak cukup (tersedia: {{ selectedSpItem(sp.sparepart_id)!.stok }})</p>
                        </div>
                    </div>

                    <div class="sticky bottom-0 bg-white dark:bg-gray-800 pt-3 pb-4 border-t border-gray-100 dark:border-gray-700 -mx-4 sm:-mx-6 px-4 sm:px-6">
                        <div class="flex gap-3">
                            <button type="button" @click="closeFormModal" class="px-5 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">Batal</button>
                            <button type="submit" :disabled="!form.dies_id || !form.process_id"
                                class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-sm active:scale-95 transition-all disabled:opacity-50">
                                Buat Laporan PM
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showDetailModal && selectedPm" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-lg max-h-[92vh] overflow-y-auto shadow-2xl">
                <div class="sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2"><CheckCircle2 class="w-4 h-4 text-blue-600" /> Detail PM</h2>
                        <button @click="showDetailModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                    </div>
                </div>
                <div class="p-4 sm:p-5 space-y-3.5">
                    <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-white font-bold text-sm">{{ selectedPm.dies?.no_part ?? selectedPm.dies_id }}</p>
                                <p class="text-white/70 text-xs mt-0.5 line-clamp-1">{{ selectedPm.dies?.nama_dies }}</p>
                                <span v-if="selectedPm.process" class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 bg-white/20 text-white rounded-full text-xs font-semibold"><Layers class="w-3 h-3" /> {{ selectedPm.process.process_name }}</span>
                            </div>
                            <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[selectedPm.status]?.badge ?? '']">{{ statusCfg[selectedPm.status]?.label ?? selectedPm.status }}</span>
                                <span v-if="selectedPm.condition" :class="['px-2 py-0.5 rounded-full text-xs font-bold', selectedPm.condition === 'ok' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700']">{{ selectedPm.condition.toUpperCase() }}</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-3">
                            <div class="bg-white/10 rounded-xl p-2 text-center"><User class="w-3 h-3 text-white/60 mx-auto mb-0.5" /><p class="text-white text-xs font-bold truncate">{{ selectedPm.pic_name }}</p><p class="text-white/50 text-xs">PIC</p></div>
                            <div class="bg-white/10 rounded-xl p-2 text-center"><Calendar class="w-3 h-3 text-white/60 mx-auto mb-0.5" /><p class="text-white text-xs font-bold">{{ fmtDate(selectedPm.report_date) }}</p><p class="text-white/50 text-xs">Tanggal</p></div>
                            <div class="bg-white/10 rounded-xl p-2 text-center"><Activity class="w-3 h-3 text-white/60 mx-auto mb-0.5" /><p class="text-white text-xs font-bold">{{ selectedPm.stroke_at_maintenance.toLocaleString() }}</p><p class="text-white/50 text-xs">Stroke ({{ pct(selectedPm) }}%)</p></div>
                        </div>
                        <div class="mt-3"><div class="h-1.5 bg-white/20 rounded-full overflow-hidden"><div :class="['h-full rounded-full', pct(selectedPm) >= 100 ? 'bg-red-400' : pct(selectedPm) >= 85 ? 'bg-amber-400' : 'bg-emerald-400']" :style="{ width: `${pct(selectedPm)}%` }"></div></div></div>
                        <div v-if="selectedPm.scheduled_date && selectedPm.status === 'scheduled'" class="mt-2 flex items-center gap-1.5 text-white/80 text-xs"><CalendarCheck class="w-3.5 h-3.5" /> Dijadwalkan: <span class="font-bold text-white">{{ fmtDate(selectedPm.scheduled_date) }}</span></div>
                    </div>

                    <div v-if="selectedPm.repair_action" class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-3.5 border border-emerald-100 dark:border-emerald-800">
                        <p class="text-xs font-bold text-emerald-600 uppercase mb-1.5">Tindakan / Hasil PM</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedPm.repair_action }}</p>
                    </div>

                    <div v-if="selectedPm.condition === 'nok'" class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3.5 border border-red-100 dark:border-red-800">
                        <p class="text-xs font-bold text-red-600 uppercase mb-1.5 flex items-center gap-1"><AlertTriangle class="w-3.5 h-3.5" /> Kondisi NOK</p>
                        <p v-if="selectedPm.nok_notes" class="text-xs text-gray-700 dark:text-gray-300">{{ selectedPm.nok_notes }}</p>
                        <div v-if="selectedPm.nok_closed_at" class="mt-2 text-xs text-emerald-600 font-semibold flex items-center gap-1"><ShieldCheck class="w-3.5 h-3.5" /> Closed: {{ fmtDate(selectedPm.nok_closed_at) }}</div>
                    </div>

                    <div v-if="selectedPm.pic_dies || selectedPm.photos?.length">
                        <p class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-1.5"><Camera class="w-3.5 h-3.5" /> Dokumentasi</p>
                        <div v-if="selectedPm.pic_dies" class="mb-2">
                            <p class="text-xs text-gray-400 mb-1">Foto Dies</p>
                            <img :src="'/storage/' + selectedPm.pic_dies" @click="lightboxSrc = '/storage/' + selectedPm.pic_dies" class="w-full h-32 object-cover rounded-xl cursor-pointer hover:opacity-90" />
                        </div>
                        <div v-if="selectedPm.photos?.length" class="grid grid-cols-3 gap-2">
                            <div v-for="(ph, idx) in selectedPm.photos" :key="idx" @click="lightboxSrc = '/storage/' + ph" class="aspect-square rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-700 cursor-pointer group">
                                <img :src="'/storage/' + ph" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedPm.spareparts?.length">
                        <p class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-1.5"><Package class="w-3.5 h-3.5 text-indigo-500" /> Sparepart</p>
                        <div class="space-y-1.5">
                            <div v-for="h in selectedPm.spareparts" :key="h.id" class="flex justify-between items-center px-3 py-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-xl text-xs">
                                <span class="font-semibold text-gray-800 dark:text-white">{{ h.sparepart?.sparepart_name ?? '-' }}</span>
                                <span class="text-gray-500">{{ h.quantity }} {{ h.sparepart?.unit }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3.5 space-y-2 text-xs">
                        <p class="font-bold text-gray-400 uppercase mb-2">History</p>
                        <div class="flex justify-between gap-2"><span class="text-gray-400">Dibuat oleh</span><span class="font-semibold text-gray-800 dark:text-white">{{ selectedPm.created_by?.name ?? selectedPm.pic_name }}</span></div>
                        <div class="flex justify-between gap-2"><span class="text-gray-400">Tanggal</span><span class="font-semibold text-gray-800 dark:text-white">{{ fmtDate(selectedPm.report_date) }}</span></div>
                        <div v-if="selectedPm.completed_at" class="flex justify-between gap-2"><span class="text-gray-400">Selesai</span><span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ fmtDate(selectedPm.completed_at) }}</span></div>
                    </div>
                </div>
                <div class="sticky bottom-0 bg-white dark:bg-gray-800 p-4 border-t border-gray-100 dark:border-gray-700">
                    <button @click="showDetailModal = false" class="w-full py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-200 active:scale-95 transition-all">Tutup</button>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="lightboxSrc" class="fixed inset-0 z-[60] bg-black/90 flex items-center justify-center p-4" @click="lightboxSrc = null">
                <button class="absolute top-4 right-4 p-2 bg-white/10 hover:bg-white/20 rounded-xl text-white"><X class="w-5 h-5" /></button>
                <img :src="lightboxSrc" class="max-w-full max-h-full rounded-2xl object-contain" @click.stop />
            </div>
        </Teleport>

        <div v-if="showPhotoPicker" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] flex items-end justify-center p-4" @click.self="showPhotoPicker = false">
            <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-4"></div>
                <p class="text-center text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 px-5">Pilih Sumber Foto</p>
                <div class="grid grid-cols-2 gap-3 px-4 pb-5">
                    <button @click="triggerInput('cam')" class="flex flex-col items-center gap-2.5 py-5 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold text-sm hover:bg-indigo-100 active:scale-95 transition-all">
                        <Camera class="w-8 h-8" /> Kamera
                    </button>
                    <button @click="triggerInput('gal')" class="flex flex-col items-center gap-2.5 py-5 rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 font-semibold text-sm hover:bg-emerald-100 active:scale-95 transition-all">
                        <Upload class="w-8 h-8" /> Galeri
                    </button>
                </div>
            </div>
        </div>

    </AppLayout>
</template>
