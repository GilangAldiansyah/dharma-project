<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick } from 'vue';
import {
    CheckCircle2, Search, Filter, Plus, Eye, Download, User, Calendar, Activity,
    Upload, Camera, Package, AlertCircle, Loader2, AlertTriangle,
    Layers, ChevronDown, ShieldCheck, X, Trash2, History
} from 'lucide-vue-next';
import * as XLSX from 'xlsx';

interface DiesProcess { id: number; process_name: string; tonase: number | null; std_stroke: number; current_stroke: number; last_mtc_date: string | null }
interface DiesMini { id_sap: string; no_part: string; nama_dies: string; line: string; processes: DiesProcess[] }
interface Sparepart { id: number; sparepart_code: string; sparepart_name: string; unit: string; stok: number }
interface Io { id: number; nama: string; cc: string; io_number: string }
interface HistorySp { id: number; quantity: number; notes: string | null; sparepart: Sparepart | null }
interface Preventive {
    id: number; report_no: string; dies_id: string; process_id: number | null; pic_name: string;
    report_date: string; stroke_at_maintenance: number;
    repair_action: string | null; photos: string[] | null;
    pic_dies: string | null; condition: 'ok' | 'nok' | null;
    nok_closed_by: number | null; nok_closed_at: string | null; nok_notes: string | null;
    status: string; completed_at: string | null;
    dies: DiesMini | null; process: DiesProcess | null;
    spareparts: HistorySp[];
    created_by: { id: number; name: string } | null;
}
interface NearProcess {
    process_id:     number;
    process_name:   string;
    dies_id:        string;
    dies:           DiesMini;
    std_stroke:     number;
    current_stroke: number;
    remaining:      number;
    pct:            number;
    urgency:        'scheduled' | 'urgent';
    last_mtc_date:  string | null;
}
interface Props {
    preventives:   { data: Preventive[]; links: any[]; meta: any };
    totalCount: number;
    nearProcesses: NearProcess[];
    diesList:      DiesMini[];
    spareparts:    Sparepart[];
    ios:           Io[];
    isLeader:      boolean;
    filters:       { search?: string; status?: string; dies_id?: string; date_from?: string; date_to?: string };
}

const props = defineProps<Props>();
const page  = usePage();
const flash = computed(() => (page.props as any).flash);

const activeTab    = ref<'schedule' | 'history'>('schedule');
const search       = ref(props.filters.search    ?? '');
const filterStatus = ref(props.filters.status    ?? '');
const filterDies   = ref(props.filters.dies_id   ?? '');
const dateFrom     = ref(props.filters.date_from ?? '');
const dateTo       = ref(props.filters.date_to   ?? '');
const showFilter   = ref(false);

const showDetailModal   = ref(false);
const showCloseNokModal = ref(false);
const showFormModal     = ref(false);
const showSubmitModal   = ref(false);
const selectedPm        = ref<Preventive | null>(null);
const selectedNear      = ref<NearProcess | null>(null);
const lightboxSrc       = ref<string | null>(null);

const statusCfg: Record<string, { label: string; badge: string; dot: string }> = {
    pending:     { label: 'Pending',     badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',                 dot: 'bg-gray-400' },
    in_progress: { label: 'In Progress', badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',         dot: 'bg-amber-400' },
    completed:   { label: 'Completed',   badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400', dot: 'bg-emerald-500' },
};

const activeFilterCount = computed(() => [filterStatus.value, filterDies.value, dateFrom.value, dateTo.value].filter(Boolean).length);

const navigate = () => router.get('/dies/preventive',
    { search: search.value, status: filterStatus.value, dies_id: filterDies.value, date_from: dateFrom.value, date_to: dateTo.value },
    { preserveState: true, preserveScroll: true, replace: true });

let debounce: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => navigate(), 400); });
watch([filterStatus, filterDies, dateFrom, dateTo], () => navigate());

const fmtDate = (d: string | null) =>
    !d ? '-' : new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

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
    spareparts: [] as { sparepart_id: number | null; io_id: number | null; quantity: string; notes: string }[],
});
const formDiesSearch      = ref('');
const formDiesOpen        = ref(false);
const selectedFormDiesObj = computed(() => props.diesList.find(d => d.id_sap === form.dies_id) ?? null);
const filteredFormDies    = computed(() => {
    const q = formDiesSearch.value.toLowerCase().trim();
    if (!q) return props.diesList;
    return props.diesList.filter(d => d.no_part.toLowerCase().includes(q) || d.nama_dies.toLowerCase().includes(q) || d.line.toLowerCase().includes(q));
});
const selectFormDies        = (d: DiesMini) => { form.dies_id = d.id_sap; form.process_id = null; formDiesSearch.value = d.no_part; formDiesOpen.value = false; };
const clearFormDies         = () => { form.dies_id = ''; form.process_id = null; formDiesSearch.value = ''; formDiesOpen.value = true; };
const closeFormDiesDropdown = () => { setTimeout(() => { formDiesOpen.value = false; }, 180); };
const availableProcesses    = computed(() => selectedFormDiesObj.value?.processes ?? []);
const formProcessOpen       = ref(false);
const selectedProcessObj    = computed(() => availableProcesses.value.find(p => p.id === form.process_id) ?? null);
watch(() => form.dies_id, () => { form.process_id = null; });

const statusList = [
    { v: 'pending',     l: 'Pending',     hint: 'Belum mulai dikerjakan' },
    { v: 'in_progress', l: 'In Progress', hint: 'Sedang dalam proses' },
    { v: 'completed',   l: 'Completed',   hint: 'PM selesai dilakukan' },
];

const submitForm = useForm({
    process_id: null as number | null,
    photo: null as File | null, photo_sparepart: null as File | null,
    condition: 'ok' as 'ok' | 'nok', repair_action: '',
    spareparts: [] as { sparepart_id: number | null; io_id: number | null; quantity: string; notes: string }[],
});
const previewPhoto    = ref<string | null>(null);
const previewSpPhoto  = ref<string | null>(null);
const isCompressingA  = ref(false);
const isCompressingB  = ref(false);
const showPhotoPicker  = ref(false);
const photoPickerField = ref<'photo' | 'photo_sparepart' | null>(null);

const nokForm = useForm({ nok_notes: '' });

const spSearch        = ref<string[]>([]);
const spOpen          = ref<boolean[]>([]);
const spIoSearch      = ref<string[]>([]);
const spIoOpen        = ref<boolean[]>([]);
const filteredSp      = (i: number) => { const q = (spSearch.value[i] ?? '').toLowerCase().trim(); return !q ? props.spareparts : props.spareparts.filter(s => s.sparepart_name.toLowerCase().includes(q) || s.sparepart_code.toLowerCase().includes(q)); };
const filteredSpIo    = (i: number) => { const q = (spIoSearch.value[i] ?? '').toLowerCase().trim(); return !q ? props.ios : props.ios.filter(io => io.nama.toLowerCase().includes(q) || io.io_number.toLowerCase().includes(q) || io.cc.toLowerCase().includes(q)); };
const selectSp        = (i: number, s: Sparepart) => { form.spareparts[i].sparepart_id = s.id; spSearch.value[i] = s.sparepart_name; spOpen.value[i] = false; };
const selectSpIo      = (i: number, io: Io) => { form.spareparts[i].io_id = io.id; spIoSearch.value[i] = io.io_number + ' - ' + io.nama; spIoOpen.value[i] = false; };
const openSpDropdown  = (i: number) => { spOpen.value[i] = true; };
const openSpIoDropdown = (i: number) => { spIoOpen.value[i] = true; };
const closeSpDropdown = (i: number) => { setTimeout(() => { spOpen.value[i] = false; }, 180); };
const closeSpIoDropdown = (i: number) => { setTimeout(() => { spIoOpen.value[i] = false; }, 180); };
const clearSp         = (i: number) => { form.spareparts[i].sparepart_id = null; spSearch.value[i] = ''; spOpen.value[i] = true; };
const clearSpIo       = (i: number) => { form.spareparts[i].io_id = null; spIoSearch.value[i] = ''; spIoOpen.value[i] = true; };
const addSp           = () => { form.spareparts.push({ sparepart_id: null, io_id: null, quantity: '', notes: '' }); spSearch.value.push(''); spOpen.value.push(false); spIoSearch.value.push(''); spIoOpen.value.push(false); };
const removeSp        = (i: number) => { form.spareparts.splice(i, 1); spSearch.value.splice(i, 1); spOpen.value.splice(i, 1); spIoSearch.value.splice(i, 1); spIoOpen.value.splice(i, 1); };

const cspSearch        = ref<string[]>([]);
const cspOpen          = ref<boolean[]>([]);
const cspIoSearch      = ref<string[]>([]);
const cspIoOpen        = ref<boolean[]>([]);
const filteredCsp      = (i: number) => { const q = (cspSearch.value[i] ?? '').toLowerCase().trim(); return !q ? props.spareparts : props.spareparts.filter(s => s.sparepart_name.toLowerCase().includes(q) || s.sparepart_code.toLowerCase().includes(q)); };
const filteredCspIo    = (i: number) => { const q = (cspIoSearch.value[i] ?? '').toLowerCase().trim(); return !q ? props.ios : props.ios.filter(io => io.nama.toLowerCase().includes(q) || io.io_number.toLowerCase().includes(q) || io.cc.toLowerCase().includes(q)); };
const selectCsp        = (i: number, s: Sparepart) => { submitForm.spareparts[i].sparepart_id = s.id; cspSearch.value[i] = s.sparepart_name; cspOpen.value[i] = false; };
const selectCspIo      = (i: number, io: Io) => { submitForm.spareparts[i].io_id = io.id; cspIoSearch.value[i] = io.io_number + ' - ' + io.nama; cspIoOpen.value[i] = false; };
const openCspDropdown  = (i: number) => { cspOpen.value[i] = true; };
const openCspIoDropdown = (i: number) => { cspIoOpen.value[i] = true; };
const closeCspDropdown = (i: number) => { setTimeout(() => { cspOpen.value[i] = false; }, 180); };
const closeCspIoDropdown = (i: number) => { setTimeout(() => { cspIoOpen.value[i] = false; }, 180); };
const clearCsp         = (i: number) => { submitForm.spareparts[i].sparepart_id = null; cspSearch.value[i] = ''; cspOpen.value[i] = true; };
const clearCspIo       = (i: number) => { submitForm.spareparts[i].io_id = null; cspIoSearch.value[i] = ''; cspIoOpen.value[i] = true; };
const addCsp           = () => { submitForm.spareparts.push({ sparepart_id: null, io_id: null, quantity: '', notes: '' }); cspSearch.value.push(''); cspOpen.value.push(false); cspIoSearch.value.push(''); cspIoOpen.value.push(false); };
const removeCsp        = (i: number) => { submitForm.spareparts.splice(i, 1); cspSearch.value.splice(i, 1); cspOpen.value.splice(i, 1); cspIoSearch.value.splice(i, 1); cspIoOpen.value.splice(i, 1); };
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
const handleSubmitPhoto = async (e: Event, field: 'photo' | 'photo_sparepart') => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    if (field === 'photo') {
        isCompressingA.value = true;
        submitForm.photo = await compressImage(file);
        const r = new FileReader(); r.onload = ev => { previewPhoto.value = ev.target?.result as string; }; r.readAsDataURL(submitForm.photo);
        isCompressingA.value = false;
    } else {
        isCompressingB.value = true;
        submitForm.photo_sparepart = await compressImage(file);
        const r = new FileReader(); r.onload = ev => { previewSpPhoto.value = ev.target?.result as string; }; r.readAsDataURL(submitForm.photo_sparepart);
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
    form.dies_id = ''; form.process_id = null; form.repair_action = ''; form.status = 'completed'; form.photos = []; form.spareparts = [];
    form.clearErrors(); formDiesSearch.value = ''; formDiesOpen.value = false; formProcessOpen.value = false;
    spSearch.value = []; spOpen.value = []; spIoSearch.value = []; spIoOpen.value = [];
    newPhotoPreview.value.forEach(p => URL.revokeObjectURL(p.url)); newPhotoPreview.value = [];
    showFormModal.value = true;
};
const openSubmit = (near: NearProcess) => {
    selectedNear.value = near;
    submitForm.process_id = near.process_id;
    submitForm.photo = null; submitForm.photo_sparepart = null;
    submitForm.condition = 'ok'; submitForm.repair_action = ''; submitForm.spareparts = [];
    submitForm.clearErrors(); previewPhoto.value = null; previewSpPhoto.value = null;
    cspSearch.value = []; cspOpen.value = []; cspIoSearch.value = []; cspIoOpen.value = [];
    showSubmitModal.value = true;
};
const closeSubmitModal = () => { showSubmitModal.value = false; selectedNear.value = null; previewPhoto.value = null; previewSpPhoto.value = null; };
const closeFormModal   = () => { showFormModal.value = false; newPhotoPreview.value.forEach(p => URL.revokeObjectURL(p.url)); newPhotoPreview.value = []; };
const openDetail       = (p: Preventive) => { selectedPm.value = p; showDetailModal.value = true; };
const openCloseNok     = (p: Preventive) => { selectedPm.value = p; nokForm.reset(); showCloseNokModal.value = true; };

const doSubmitFromDies = () => { submitForm.post('/dies/preventive/submit-from-dies', { onSuccess: () => closeSubmitModal() }); };
const doSubmitForm     = () => {
    router.post('/dies/preventive', {
        dies_id: form.dies_id, process_id: form.process_id,
        repair_action: form.repair_action, status: form.status,
        photos: form.photos, spareparts: form.spareparts,
    }, { forceFormData: true, onSuccess: () => closeFormModal() });
};
const submitCloseNok = () => {
    if (!selectedPm.value) return;
    nokForm.post(`/dies/preventive/${selectedPm.value.id}/close-nok`, {
        onSuccess: () => { showCloseNokModal.value = false; selectedPm.value = null; }
    });
};

const exportExcel = () => {
    const rows = [
        ['No Part', 'Nama Dies', 'Line', 'Proses', 'PIC', 'Tanggal', 'Stroke MTC', 'Tindakan', 'Kondisi', 'Status', 'Completed At'],
        ...props.preventives.data.map(p => [
            p.dies?.no_part ?? p.dies_id, p.dies?.nama_dies ?? '', p.dies?.line ?? '',
            p.process?.process_name ?? '',
            p.pic_name, p.report_date, p.stroke_at_maintenance,
            p.repair_action ?? '', p.condition?.toUpperCase() ?? '-',
            statusCfg[p.status]?.label ?? p.status,
            p.completed_at ? fmtDate(p.completed_at) : '',
        ]),
    ];
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [16, 30, 10, 20, 16, 12, 14, 30, 10, 14, 14].map(w => ({ wch: w }));
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
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">{{ totalCount }} laporan history</p>
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

            <div class="bg-gray-100 dark:bg-gray-800 rounded-xl p-1">
                <div class="flex gap-1">
                    <button @click="activeTab = 'schedule'"
                        :class="['flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-lg text-xs font-bold transition-all',
                            activeTab === 'schedule' ? 'bg-white dark:bg-gray-700 text-blue-600 dark:text-blue-400 shadow' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']">
                        <AlertTriangle class="w-3.5 h-3.5" />
                        Perlu PM
                        <span v-if="nearProcesses.length > 0" :class="['px-1.5 py-0.5 rounded-full text-xs font-black', nearProcesses.some(n => n.urgency === 'urgent') ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400']">
                            {{ nearProcesses.length }}
                        </span>
                    </button>
                    <button @click="activeTab = 'history'"
                        :class="['flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-lg text-xs font-bold transition-all',
                            activeTab === 'history' ? 'bg-white dark:bg-gray-700 text-blue-600 dark:text-blue-400 shadow' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']">
                        <History class="w-3.5 h-3.5" />
                        History
                        <span class="px-1.5 py-0.5 rounded-full text-xs font-black bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300">
                            {{ totalCount }}
                        </span>
                    </button>
                </div>
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
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Tanggal</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="block text-xs text-gray-400 mb-1">Dari</label><input v-model="dateFrom" type="date" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" /></div>
                            <div><label class="block text-xs text-gray-400 mb-1">Sampai</label><input v-model="dateTo" type="date" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none" /></div>
                        </div>
                    </div>
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
                        <button @click="filterStatus = ''; dateFrom = ''; dateTo = ''; clearDiesFilter()" class="text-xs text-blue-500 font-semibold hover:underline">Reset semua filter</button>
                    </div>
                </div>
            </div>

            <div v-show="activeTab === 'schedule'">
                <div v-if="nearProcesses.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <CheckCircle2 class="w-12 h-12 mx-auto mb-3 text-emerald-300" />
                    <p class="text-sm font-bold text-gray-500">Semua proses dalam kondisi baik</p>
                    <p class="text-xs text-gray-400 mt-1">Tidak ada proses yang mendekati batas stroke</p>
                </div>
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div v-for="n in nearProcesses" :key="n.process_id"
                        :class="['rounded-2xl border shadow-sm overflow-hidden',
                            n.urgency === 'urgent' ? 'bg-red-50 dark:bg-red-900/10 border-red-200 dark:border-red-800' : 'bg-amber-50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-800']">
                        <div class="p-3.5">
                            <div class="flex items-start justify-between gap-2 mb-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ n.dies.no_part }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-1 mt-0.5">{{ n.dies.nama_dies }}</p>
                                    <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                        <span class="inline-block text-xs px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded font-semibold">{{ n.dies.line }}</span>
                                        <span class="inline-flex items-center gap-1 text-xs px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 rounded font-semibold">
                                            <Layers class="w-2.5 h-2.5" />{{ n.process_name }}
                                        </span>
                                    </div>
                                </div>
                                <span :class="['flex-shrink-0 px-2 py-0.5 rounded-full text-xs font-bold',
                                    n.urgency === 'urgent' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400']">
                                    {{ n.urgency === 'urgent' ? '🔴 Disegerakan' : '🟡 Dijadwalkan' }}
                                </span>
                            </div>

                            <div class="rounded-xl p-2.5 mb-3 space-y-1.5"
                                :class="n.urgency === 'urgent' ? 'bg-red-100/60 dark:bg-red-900/20' : 'bg-amber-100/60 dark:bg-amber-900/20'">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-500">Current Stroke</span>
                                    <span class="font-bold text-gray-800 dark:text-gray-200">{{ n.current_stroke.toLocaleString() }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-500">STD Stroke</span>
                                    <span class="font-bold text-gray-800 dark:text-gray-200">{{ n.std_stroke.toLocaleString() }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-500">Sisa</span>
                                    <span :class="['font-bold', n.urgency === 'urgent' ? 'text-red-600' : 'text-amber-600']">{{ n.remaining.toLocaleString() }}</span>
                                </div>
                                <div class="pt-1">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400">Progress</span>
                                        <span :class="['font-black text-sm', n.urgency === 'urgent' ? 'text-red-600' : 'text-amber-600']">{{ n.pct }}%</span>
                                    </div>
                                    <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div :class="['h-full rounded-full transition-all', n.urgency === 'urgent' ? 'bg-red-500' : 'bg-amber-400']"
                                            :style="{ width: `${Math.min(n.pct, 100)}%` }"></div>
                                    </div>
                                </div>
                            </div>

                            <button @click="openSubmit(n)"
                                :class="['w-full flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-xs font-bold active:scale-95 transition-all',
                                    n.urgency === 'urgent' ? 'bg-red-500 hover:bg-red-600 text-white' : 'bg-amber-500 hover:bg-amber-600 text-white']">
                                <Upload class="w-3.5 h-3.5" /> Submit PM
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-show="activeTab === 'history'" class="space-y-3">
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

                <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Dies / Proses</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Tindakan</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">PIC</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Tanggal</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Stroke MTC</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Kondisi</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                                <tr v-if="preventives.data.length === 0">
                                    <td colspan="8" class="py-16 text-center text-gray-400 text-sm">
                                        <History class="w-10 h-10 mx-auto mb-2 text-gray-300" /> Tidak ada history PM
                                    </td>
                                </tr>
                                <tr v-for="p in preventives.data" :key="p.id" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
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
                                        <p v-if="p.repair_action" class="text-xs text-gray-500 line-clamp-2">{{ p.repair_action }}</p>
                                        <span v-else class="text-xs text-gray-300">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">{{ p.pic_name }}</td>
                                    <td class="px-4 py-3 text-center text-xs text-gray-500">{{ fmtDate(p.report_date) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <p class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ p.stroke_at_maintenance.toLocaleString() }}</p>
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
                        <p class="text-xs text-gray-500">{{ preventives.meta?.from }}–{{ preventives.meta?.to }} dari {{ preventives.meta?.total }}</p>
                        <div class="flex gap-1">
                            <button v-for="link in preventives.links" :key="link.label" @click="link.url && router.visit(link.url)" :disabled="!link.url" v-html="link.label"
                                :class="['px-3 py-1.5 text-xs rounded-lg font-semibold transition-colors', link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200' : 'opacity-40 cursor-default bg-gray-100 dark:bg-gray-700 text-gray-400']">
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lg:hidden space-y-2.5">
                    <div v-if="preventives.data.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                        <History class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                        <p class="text-gray-400 text-sm">Tidak ada history PM</p>
                    </div>
                    <div v-for="p in preventives.data" :key="p.id" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
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
                            <div class="space-y-1.5 mb-3">
                                <p v-if="p.repair_action" class="text-xs text-gray-500 line-clamp-2 px-1">{{ p.repair_action }}</p>
                                <div class="flex items-center gap-3 text-xs text-gray-500 px-1">
                                    <span class="flex items-center gap-1"><User class="w-3 h-3" /> {{ p.pic_name }}</span>
                                    <span class="flex items-center gap-1"><Calendar class="w-3 h-3" /> {{ fmtDate(p.report_date) }}</span>
                                    <span v-if="p.spareparts?.length" class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400 rounded-full font-bold">
                                        <Package class="w-3 h-3" /> {{ p.spareparts.length }}
                                    </span>
                                </div>
                                <div class="px-1 text-xs text-gray-500">
                                    Stroke MTC: <span class="font-bold text-gray-700 dark:text-gray-300">{{ p.stroke_at_maintenance.toLocaleString() }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 pt-2.5 border-t border-gray-100 dark:border-gray-700">
                                <button v-if="p.condition === 'nok' && !p.nok_closed_at && isLeader" @click="openCloseNok(p)"
                                    class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold active:scale-95 transition-all">
                                    <ShieldCheck class="w-3.5 h-3.5" /> Close NOK
                                </button>
                                <button @click="openDetail(p)"
                                    class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-bold hover:bg-gray-200 active:scale-95 transition-all">
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
        </div>

        <!-- Submit PM Modal -->
        <div v-if="showSubmitModal && selectedNear" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-lg max-h-[95vh] overflow-y-auto shadow-2xl">
                <div class="sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <Upload class="w-4 h-4 text-orange-500" /> Submit PM
                        </h2>
                        <button @click="closeSubmitModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                    </div>
                </div>
                <form @submit.prevent="doSubmitFromDies" class="p-4 sm:p-5 space-y-4">
                    <div :class="['rounded-xl p-3.5 border', selectedNear.urgency === 'urgent' ? 'bg-red-50 dark:bg-red-900/20 border-red-100 dark:border-red-800' : 'bg-amber-50 dark:bg-amber-900/20 border-amber-100 dark:border-amber-800']">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <p class="text-sm font-black text-gray-900 dark:text-white">{{ selectedNear.dies.no_part }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ selectedNear.dies.nama_dies }}</p>
                                <span class="inline-flex items-center gap-1 mt-1 text-xs px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 rounded font-semibold">
                                    <Layers class="w-2.5 h-2.5" />{{ selectedNear.process_name }}
                                </span>
                            </div>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-bold flex-shrink-0', selectedNear.urgency === 'urgent' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700']">{{ selectedNear.pct }}%</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center text-xs mb-2">
                            <div :class="['rounded-lg p-1.5', selectedNear.urgency === 'urgent' ? 'bg-red-100/60 dark:bg-red-900/30' : 'bg-amber-100/60 dark:bg-amber-900/30']">
                                <p class="text-gray-500">Current</p>
                                <p class="font-bold text-gray-800 dark:text-gray-200">{{ selectedNear.current_stroke.toLocaleString() }}</p>
                            </div>
                            <div :class="['rounded-lg p-1.5', selectedNear.urgency === 'urgent' ? 'bg-red-100/60 dark:bg-red-900/30' : 'bg-amber-100/60 dark:bg-amber-900/30']">
                                <p class="text-gray-500">STD</p>
                                <p class="font-bold text-gray-800 dark:text-gray-200">{{ selectedNear.std_stroke.toLocaleString() }}</p>
                            </div>
                            <div :class="['rounded-lg p-1.5', selectedNear.urgency === 'urgent' ? 'bg-red-100/60 dark:bg-red-900/30' : 'bg-amber-100/60 dark:bg-amber-900/30']">
                                <p class="text-gray-500">Sisa</p>
                                <p :class="['font-bold', selectedNear.urgency === 'urgent' ? 'text-red-600' : 'text-amber-600']">{{ selectedNear.remaining.toLocaleString() }}</p>
                            </div>
                        </div>
                        <div class="h-1.5 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700">
                            <div :class="['h-full rounded-full', selectedNear.urgency === 'urgent' ? 'bg-red-500' : 'bg-amber-400']"
                                :style="{ width: `${Math.min(selectedNear.pct, 100)}%` }"></div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Kondisi Dies</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" @click="submitForm.condition = 'ok'"
                                :class="['py-3 rounded-xl font-bold text-sm transition-all active:scale-95', submitForm.condition === 'ok' ? 'bg-emerald-500 text-white shadow-md' : 'border-2 border-gray-200 text-gray-600 dark:border-gray-600 dark:text-gray-300']">✓ OK</button>
                            <button type="button" @click="submitForm.condition = 'nok'"
                                :class="['py-3 rounded-xl font-bold text-sm transition-all active:scale-95', submitForm.condition === 'nok' ? 'bg-red-500 text-white shadow-md' : 'border-2 border-gray-200 text-gray-600 dark:border-gray-600 dark:text-gray-300']">✗ NOK</button>
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
                                <div v-else class="text-center"><Upload class="w-7 h-7 text-gray-400 mx-auto mb-1.5" /><p class="text-xs text-gray-500 font-medium">Upload foto</p></div>
                            </div>
                            <input id="cam-photo" type="file" accept="image/*" capture="environment" @change="(e) => handleSubmitPhoto(e, 'photo')" class="hidden" />
                            <input id="gal-photo" type="file" accept="image/*" @change="(e) => handleSubmitPhoto(e, 'photo')" class="hidden" />
                            <p v-if="submitForm.errors.photo" class="mt-1 text-xs text-red-500">{{ submitForm.errors.photo }}</p>
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
                            <input id="cam-photo_sparepart" type="file" accept="image/*" capture="environment" @change="(e) => handleSubmitPhoto(e, 'photo_sparepart')" class="hidden" />
                            <input id="gal-photo_sparepart" type="file" accept="image/*" @change="(e) => handleSubmitPhoto(e, 'photo_sparepart')" class="hidden" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Tindakan / Hasil PM</label>
                        <textarea v-model="submitForm.repair_action" rows="3" placeholder="Deskripsikan tindakan yang dilakukan..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-orange-500 focus:outline-none resize-none"></textarea>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5"><Package class="w-3.5 h-3.5 text-indigo-500" /> Sparepart</label>
                            <button type="button" @click="addCsp" class="flex items-center gap-1.5 px-3 py-1.5 text-xs bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400 rounded-lg font-semibold hover:bg-indigo-200 active:scale-95 transition-all">
                                <Plus class="w-3 h-3" /> Tambah
                            </button>
                        </div>
                        <div v-if="submitForm.spareparts.length === 0" class="py-3 text-center text-xs text-gray-400">Belum ada sparepart</div>
                        <div v-for="(sp, i) in submitForm.spareparts" :key="i" class="mb-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl space-y-2.5">
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
                                            :class="['w-full text-left px-3 py-2.5 text-xs hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors flex items-center justify-between gap-2', sp.sparepart_id === s.id ? 'bg-indigo-50 dark:bg-indigo-900/30 font-semibold' : 'text-gray-700 dark:text-gray-300']">
                                            <span>{{ s.sparepart_name }}</span>
                                            <span class="text-gray-400 whitespace-nowrap shrink-0">{{ s.stok }} {{ s.unit }}</span>
                                        </button>
                                    </div>
                                </div>
                                <input v-model="sp.quantity" type="number" inputmode="numeric" min="1" placeholder="Qty" class="w-16 px-2 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none text-center" />
                                <button type="button" @click="removeCsp(i)" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg active:scale-95"><Trash2 class="w-4 h-4" /></button>
                            </div>
                            <div class="relative">
                                <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                                <input v-model="cspIoSearch[i]" type="text" placeholder="Cari IO number / nama IO..." autocomplete="off"
                                    @focus="openCspIoDropdown(i)" @blur="closeCspIoDropdown(i)"
                                    :class="['w-full pl-7 pr-7 py-2.5 border rounded-xl text-xs focus:outline-none transition-colors dark:bg-gray-700',
                                        sp.io_id ? 'border-teal-400 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-teal-400']" />
                                <button v-if="sp.io_id" type="button" @click="clearCspIo(i)" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 p-0.5"><X class="w-3 h-3" /></button>
                                <div v-if="cspIoOpen[i]" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                    <div v-if="filteredCspIo(i).length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                    <button v-for="io in filteredCspIo(i)" :key="io.id" type="button" @mousedown.prevent="selectCspIo(i, io)"
                                        :class="['w-full text-left px-3 py-2.5 text-xs hover:bg-teal-50 dark:hover:bg-teal-900/30 transition-colors', sp.io_id === io.id ? 'bg-teal-50 dark:bg-teal-900/30 font-semibold' : 'text-gray-700 dark:text-gray-300']">
                                        <span class="block font-semibold text-gray-800 dark:text-gray-200">{{ io.io_number }} — {{ io.nama }}</span>
                                        <span class="text-gray-400">CC: {{ io.cc }}</span>
                                    </button>
                                </div>
                            </div>
                            <input v-model="sp.notes" type="text" placeholder="Keterangan (opsional)" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none" />
                            <p v-if="sp.sparepart_id && selectedSpItem(sp.sparepart_id) && parseInt(sp.quantity) > selectedSpItem(sp.sparepart_id)!.stok" class="text-xs text-red-500 flex items-center gap-1"><AlertCircle class="w-3 h-3" /> Stok tidak cukup (tersedia: {{ selectedSpItem(sp.sparepart_id)!.stok }})</p>
                        </div>
                    </div>

                    <div class="sticky bottom-0 bg-white dark:bg-gray-800 pt-3 pb-4 border-t border-gray-100 dark:border-gray-700 -mx-4 sm:-mx-5 px-4 sm:px-5">
                        <div class="flex gap-3">
                            <button type="button" @click="closeSubmitModal" class="px-5 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">Batal</button>
                            <button type="submit" :disabled="submitForm.processing || isCompressingA || isCompressingB || !submitForm.photo"
                                class="flex-1 py-3 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold text-sm active:scale-95 transition-all disabled:opacity-50">
                                {{ submitForm.processing ? 'Menyimpan...' : 'Submit PM' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Close NOK Modal -->
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
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Catatan Penyelesaian</label>
                        <textarea v-model="nokForm.nok_notes" rows="4" placeholder="Tindakan yang dilakukan..."
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

        <!-- Tambah PM Manual Modal -->
        <div v-if="showFormModal" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-2xl max-h-[95vh] flex flex-col shadow-2xl">
                <div class="flex-shrink-0">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2"><CheckCircle2 class="w-4 h-4 text-blue-600" /> Tambah PM Manual</h2>
                        <button @click="closeFormModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                    </div>
                </div>
                <form @submit.prevent="doSubmitForm" class="overflow-y-auto flex-1 px-4 sm:px-6 py-4 space-y-4">
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
                                    <span class="block text-xs font-semibold text-gray-800 dark:text-gray-200">{{ d.no_part }}</span>
                                    <span class="text-xs text-gray-400">{{ d.nama_dies }} — {{ d.line }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Proses <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <button type="button" @click="formProcessOpen = !formProcessOpen" :disabled="!form.dies_id"
                                :class="['w-full flex items-center justify-between px-3 py-2.5 border rounded-xl text-sm transition-colors dark:bg-gray-700',
                                    !form.dies_id ? 'opacity-50 cursor-not-allowed border-gray-200 dark:border-gray-600' :
                                    form.process_id ? 'border-purple-400 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-semibold' :
                                    'border-gray-200 dark:border-gray-600 text-gray-500 hover:border-purple-400']">
                                <span class="flex items-center gap-2"><Layers class="w-3.5 h-3.5 text-purple-400 flex-shrink-0" />
                                    <span>{{ selectedProcessObj ? selectedProcessObj.process_name : (form.dies_id ? 'Pilih proses...' : 'Pilih dies dulu') }}</span>
                                </span>
                                <ChevronDown :class="['w-3.5 h-3.5 text-gray-400 transition-transform', formProcessOpen ? 'rotate-180' : '']" />
                            </button>
                            <div v-if="formProcessOpen && form.dies_id" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                <div v-if="availableProcesses.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada proses</div>
                                <button v-for="pr in availableProcesses" :key="pr.id" type="button" @mousedown.prevent="form.process_id = pr.id; formProcessOpen = false"
                                    :class="['w-full text-left px-3 py-2.5 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors', form.process_id === pr.id ? 'bg-purple-50 dark:bg-purple-900/20' : '']">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ pr.process_name }}</span>
                                        <div class="flex items-center gap-2 text-xs text-gray-400">
                                            <span v-if="pr.tonase">{{ pr.tonase }} ton</span>
                                            <span>{{ pr.current_stroke.toLocaleString() }} / {{ pr.std_stroke.toLocaleString() }}</span>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Status</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="s in statusList" :key="s.v" type="button" @click="form.status = s.v"
                                :class="['flex flex-col items-start p-2.5 rounded-xl border-2 text-left transition-all',
                                    form.status === s.v ? s.v === 'pending' ? 'border-gray-400 bg-gray-50 dark:bg-gray-700/50' : s.v === 'in_progress' ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/20' : 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300']">
                                <span :class="['text-xs font-bold', form.status === s.v ? s.v === 'pending' ? 'text-gray-700 dark:text-gray-300' : s.v === 'in_progress' ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400' : 'text-gray-500']">{{ s.l }}</span>
                                <span class="text-xs text-gray-400 mt-0.5 leading-tight">{{ s.hint }}</span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Tindakan (opsional)</label>
                        <textarea v-model="form.repair_action" rows="3" placeholder="Deskripsikan tindakan yang dilakukan..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none resize-none"></textarea>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Foto (opsional)</label>
                            <span class="text-xs text-gray-400">{{ newPhotoPreview.length }} foto</span>
                        </div>
                        <div v-if="newPhotoPreview.length" class="grid grid-cols-4 gap-2 mb-2">
                            <div v-for="(ph, idx) in newPhotoPreview" :key="idx" class="relative rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-700 aspect-square">
                                <img :src="ph.url" class="w-full h-full object-cover" />
                                <button type="button" @click="removeNewPhoto(idx)" class="absolute top-1 right-1 p-1 bg-red-500 rounded-full text-white"><X class="w-3 h-3" /></button>
                            </div>
                        </div>
                        <label class="flex flex-col items-center justify-center gap-2 p-4 border-2 border-dashed rounded-xl cursor-pointer border-gray-200 dark:border-gray-600 hover:border-blue-400">
                            <Loader2 v-if="isCompressing" class="w-5 h-5 text-blue-400 animate-spin" />
                            <Upload v-else class="w-5 h-5 text-gray-400" />
                            <span class="text-xs text-gray-500 font-medium">{{ isCompressing ? 'Mengompres...' : 'Klik untuk upload foto' }}</span>
                            <input type="file" multiple accept="image/*" class="hidden" :disabled="isCompressing" @change="handleFileInput" />
                        </label>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1"><Package class="w-3.5 h-3.5 text-indigo-500" /> Sparepart (opsional)</label>
                            <button type="button" @click="addSp" class="flex items-center gap-1 px-2.5 py-1.5 text-xs bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400 rounded-lg font-semibold"><Plus class="w-3 h-3" /> Tambah</button>
                        </div>
                        <div v-if="form.spareparts.length === 0" class="py-3 text-center text-xs text-gray-400">Belum ada sparepart</div>
                        <div v-for="(sp, i) in form.spareparts" :key="i" class="mb-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl space-y-2">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                                    <input v-model="spSearch[i]" type="text" placeholder="Cari sparepart..." autocomplete="off"
                                        @focus="openSpDropdown(i)" @blur="closeSpDropdown(i)"
                                        :class="['w-full pl-7 pr-7 py-2.5 border rounded-xl text-xs focus:outline-none dark:bg-gray-700', sp.sparepart_id ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 font-semibold' : 'border-gray-200 dark:border-gray-600']" />
                                    <button v-if="sp.sparepart_id" type="button" @click="clearSp(i)" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 p-0.5"><X class="w-3 h-3" /></button>
                                    <div v-if="spOpen[i]" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                        <div v-if="filteredSp(i).length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                        <button v-for="s in filteredSp(i)" :key="s.id" type="button" @mousedown.prevent="selectSp(i, s)"
                                            :class="['w-full text-left px-3 py-2.5 text-xs hover:bg-indigo-50 dark:hover:bg-indigo-900/30 flex items-center justify-between gap-2', sp.sparepart_id === s.id ? 'bg-indigo-50 font-semibold' : 'text-gray-700 dark:text-gray-300']">
                                            <span>{{ s.sparepart_name }}</span>
                                            <span class="text-gray-400 whitespace-nowrap shrink-0">{{ s.stok }} {{ s.unit }}</span>
                                        </button>
                                    </div>
                                </div>
                                <input v-model="sp.quantity" type="number" min="1" placeholder="Qty" class="w-16 px-2 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-xs text-center focus:outline-none" />
                                <button type="button" @click="removeSp(i)" class="p-2 text-red-500 hover:bg-red-50 rounded-lg"><Trash2 class="w-4 h-4" /></button>
                            </div>
                            <div class="relative">
                                <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                                <input v-model="spIoSearch[i]" type="text" placeholder="Cari IO number / nama IO..." autocomplete="off"
                                    @focus="openSpIoDropdown(i)" @blur="closeSpIoDropdown(i)"
                                    :class="['w-full pl-7 pr-7 py-2.5 border rounded-xl text-xs focus:outline-none dark:bg-gray-700',
                                        sp.io_id ? 'border-teal-400 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-teal-400']" />
                                <button v-if="sp.io_id" type="button" @click="clearSpIo(i)" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 p-0.5"><X class="w-3 h-3" /></button>
                                <div v-if="spIoOpen[i]" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                    <div v-if="filteredSpIo(i).length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                    <button v-for="io in filteredSpIo(i)" :key="io.id" type="button" @mousedown.prevent="selectSpIo(i, io)"
                                        :class="['w-full text-left px-3 py-2.5 text-xs hover:bg-teal-50 dark:hover:bg-teal-900/30 transition-colors', sp.io_id === io.id ? 'bg-teal-50 dark:bg-teal-900/30 font-semibold' : 'text-gray-700 dark:text-gray-300']">
                                        <span class="block font-semibold text-gray-800 dark:text-gray-200">{{ io.io_number }} — {{ io.nama }}</span>
                                        <span class="text-gray-400">CC: {{ io.cc }}</span>
                                    </button>
                                </div>
                            </div>
                            <input v-model="sp.notes" type="text" placeholder="Keterangan (opsional)" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-xs focus:outline-none" />
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

        <!-- Detail Modal -->
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
                            <div class="bg-white/10 rounded-xl p-2 text-center"><Activity class="w-3 h-3 text-white/60 mx-auto mb-0.5" /><p class="text-white text-xs font-bold">{{ selectedPm.stroke_at_maintenance.toLocaleString() }}</p><p class="text-white/50 text-xs">Stroke MTC</p></div>
                        </div>
                    </div>
                    <div v-if="selectedPm.repair_action" class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-3.5 border border-emerald-100 dark:border-emerald-800">
                        <p class="text-xs font-bold text-emerald-600 uppercase mb-1.5">Tindakan</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedPm.repair_action }}</p>
                    </div>
                    <div v-if="selectedPm.condition === 'nok'" class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3.5 border border-red-100 dark:border-red-800">
                        <p class="text-xs font-bold text-red-600 uppercase mb-1.5 flex items-center gap-1"><AlertTriangle class="w-3.5 h-3.5" /> Kondisi NOK</p>
                        <p v-if="selectedPm.nok_notes" class="text-xs text-gray-700 dark:text-gray-300">{{ selectedPm.nok_notes }}</p>
                        <div v-if="selectedPm.nok_closed_at" class="mt-2 text-xs text-emerald-600 font-semibold flex items-center gap-1"><ShieldCheck class="w-3.5 h-3.5" /> Closed: {{ fmtDate(selectedPm.nok_closed_at) }}</div>
                    </div>
                    <div v-if="selectedPm.photos?.length">
                        <p class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-1.5"><Camera class="w-3.5 h-3.5" /> Foto</p>
                        <div class="grid grid-cols-3 gap-2">
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
                        <p class="font-bold text-gray-400 uppercase mb-2">Info</p>
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
