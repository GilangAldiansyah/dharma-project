<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed, nextTick, reactive } from 'vue';
import {
    TrendingUp, CheckCircle2, Clock, AlertCircle, X, Plus, Trash2,
    Package, FileText, Upload, Loader2, Image as ImageIcon, Search, Filter, Download, Timer, ChevronUp, ChevronDown
} from 'lucide-vue-next';
import * as XLSX from 'xlsx';

interface Jig       { id: number; name: string; type: string; line: string; }
interface Sparepart { id: number; name: string; satuan: string; stok: number; }
interface ReportSp  { sparepart: Sparepart; qty: number; notes: string | null; }
interface ImprovementReport {
    id:               number;
    pic_id:           number;
    report_date:      string;
    description:      string | null;
    penyebab:         string | null;
    perbaikan:        string | null;
    photo:            string | null;
    photo_perbaikan:  string | null;
    status:           'open' | 'in_progress' | 'closed';
    action:           string | null;
    closed_at:        string | null;
    repair_duration:  string | null;
    jig:              Jig;
    pic:              { name: string };
    closed_by:        { name: string } | null;
    spareparts:       ReportSp[];
}

interface Props {
    reports:    ImprovementReport[];
    jigs:       Jig[];
    spareparts: Sparepart[];
    summary:    { open: number; in_progress: number; closed: number };
    isLeader:   boolean;
    authId:     number;
    filters:    { status?: string; jig_id?: any; month?: string; year?: string };
}

const props     = defineProps<Props>();
const localJigs = reactive([...props.jigs]);
const page      = usePage();
const flash     = computed(() => (page.props as any).flash);

const currentYear = new Date().getFullYear();

const filterStatus = ref(props.filters.status ?? '');
const filterJig    = ref(props.filters.jig_id  ?? '');
const filterMonth  = ref(props.filters.month   ?? '');
const filterYear   = ref(props.filters.year    ? Number(props.filters.year) : currentYear);

const showFilterPanel = ref(false);

const months = [
    { v: '',   l: 'Semua' },
    { v: '01', l: 'Jan' }, { v: '02', l: 'Feb' }, { v: '03', l: 'Mar' },
    { v: '04', l: 'Apr' }, { v: '05', l: 'Mei' }, { v: '06', l: 'Jun' },
    { v: '07', l: 'Jul' }, { v: '08', l: 'Agu' }, { v: '09', l: 'Sep' },
    { v: '10', l: 'Okt' }, { v: '11', l: 'Nov' }, { v: '12', l: 'Des' },
];

const incrementYear = () => { if (filterYear.value < currentYear) filterYear.value++; };
const decrementYear = () => { if (filterYear.value > currentYear - 4) filterYear.value--; };

const jigSearch      = ref('');
const jigOpen        = ref(false);
const selectedJigObj = computed(() => localJigs.find(j => j.id == filterJig.value) ?? null);

const filteredJigs = computed(() => {
    const q = jigSearch.value.toLowerCase().trim();
    if (!q) return localJigs;
    return localJigs.filter(j =>
        j.name.toLowerCase().includes(q) ||
        j.type.toLowerCase().includes(q) ||
        j.line.toLowerCase().includes(q)
    );
});

const selectJig      = (j: Jig) => { filterJig.value = j.id; jigSearch.value = j.name; jigOpen.value = false; };
const clearJig       = () => { filterJig.value = ''; jigSearch.value = ''; jigOpen.value = true; };
const closeJigDropdown = () => { setTimeout(() => { jigOpen.value = false; }, 180); };

watch([filterStatus, filterJig, filterMonth, filterYear], () => {
    router.get('/jig/improvement', {
        status: filterStatus.value,
        jig_id: filterJig.value,
        month:  filterMonth.value,
        year:   String(filterYear.value),
    }, { preserveState: true, preserveScroll: true });
});

watch(filterJig, (val) => {
    if (!val) { jigSearch.value = ''; return; }
    const found = localJigs.find(j => j.id == val);
    if (found) jigSearch.value = found.name;
}, { immediate: true });

const exportData = () => {
    const wb    = XLSX.utils.book_new();
    const total = props.summary.open + props.summary.in_progress + props.summary.closed;

    const detailHeader = [
        'No', 'JIG', 'Tipe', 'Line', 'PIC',
        'Waktu Laporan', 'Status', 'Deskripsi',
        'Penyebab', 'Perbaikan', 'Action',
        'Ditutup Oleh', 'Tanggal Tutup', 'Durasi', 'Sparepart',
        'Foto Before', 'Foto After',
    ];

    const detailRows = props.reports.map((r, i) => [
        i + 1,
        r.jig?.name        ?? '-',
        r.jig?.type        ?? '-',
        r.jig?.line        ?? '-',
        r.pic?.name        ?? '-',
        r.report_date      ?? '-',
        r.status === 'open' ? 'Open' : r.status === 'in_progress' ? 'In Progress' : 'Closed',
        r.description      ?? '-',
        r.penyebab         ?? '-',
        r.perbaikan        ?? '-',
        r.action           ?? '-',
        r.closed_by?.name  ?? '-',
        r.closed_at        ?? '-',
        r.repair_duration  ?? '-',
        r.spareparts?.length
            ? r.spareparts.map(s => `${s.sparepart?.name} (${s.qty} ${s.sparepart?.satuan})`).join(', ')
            : '-',
        r.photo           ? `${window.location.origin}/storage/${r.photo}`           : '-',
        r.photo_perbaikan ? `${window.location.origin}/storage/${r.photo_perbaikan}` : '-',
    ]);

    const wsDetail = XLSX.utils.aoa_to_sheet([detailHeader, ...detailRows]);
    wsDetail['!cols'] = [
        { wch: 4  }, { wch: 24 }, { wch: 12 }, { wch: 12 }, { wch: 16 },
        { wch: 20 }, { wch: 12 }, { wch: 30 },
        { wch: 24 }, { wch: 24 }, { wch: 24 },
        { wch: 16 }, { wch: 16 }, { wch: 14 }, { wch: 40 },
        { wch: 50 }, { wch: 50 },
    ];
    XLSX.utils.book_append_sheet(wb, wsDetail, 'Detail');

    const summaryRows = [
        ['Laporan Improvement'],
        [],
        ['RINGKASAN'],
        ['Open', 'In Progress', 'Closed', 'Total'],
        [props.summary.open, props.summary.in_progress, props.summary.closed, total],
    ];
    const ws = XLSX.utils.aoa_to_sheet(summaryRows);
    ws['!cols'] = [{ wch: 12 }, { wch: 14 }, { wch: 12 }, { wch: 12 }];
    XLSX.utils.book_append_sheet(wb, ws, 'Summary');

    const monthLabel = filterMonth.value
        ? months.find(m => m.v === filterMonth.value)?.l ?? filterMonth.value
        : 'Semua';
    XLSX.writeFile(wb, `Improvement_Report_${monthLabel}_${filterYear.value}.xlsx`);
};

const showAddModal    = ref(false);
const showEditModal   = ref(false);
const showDetailModal = ref(false);
const showCloseModal  = ref(false);
const showImageModal  = ref(false);
const imageSrc        = ref('');
const selectedReport  = ref<ImprovementReport | null>(null);

const isCompressingA = ref(false);
const isCompressingB = ref(false);
const isCompressingC = ref(false);
const isCompressingD = ref(false);
const previewA       = ref<string | null>(null);
const previewB       = ref<string | null>(null);
const previewC       = ref<string | null>(null);
const previewD       = ref<string | null>(null);

const showPhotoPicker  = ref(false);
const photoPickerField = ref<'photo' | 'photo_perbaikan' | null>(null);
const photoPickerForm  = ref<'add' | 'edit' | null>(null);

const openPhotoPicker = (f: 'add' | 'edit', field: 'photo' | 'photo_perbaikan') => {
    photoPickerForm.value  = f;
    photoPickerField.value = field;
    showPhotoPicker.value  = true;
};

const triggerInput = (type: 'cam' | 'gal') => {
    showPhotoPicker.value = false;
    nextTick(() => {
        const id = `${type}-${photoPickerForm.value}-${photoPickerField.value}`;
        (document.getElementById(id) as HTMLInputElement)?.click();
    });
};

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

const form = useForm({
    jig_id:          null as number | null,
    description:     '',
    penyebab:        '',
    perbaikan:       '',
    photo:           null as File | null,
    photo_perbaikan: null as File | null,
    spareparts:      [] as { sparepart_id: number | null; qty: string; notes: string }[],
});

const formJigSearch = ref('');
const formJigOpen   = ref(false);
const isCreatingJig = ref(false);

const filteredFormJigs = computed(() => {
    const q = formJigSearch.value.toLowerCase().trim();
    if (!q) return localJigs;
    return localJigs.filter(j =>
        j.name.toLowerCase().includes(q) ||
        j.type.toLowerCase().includes(q) ||
        j.line.toLowerCase().includes(q)
    );
});

const selectFormJig      = (j: Jig) => { form.jig_id = j.id; formJigSearch.value = j.name; formJigOpen.value = false; };
const clearFormJig       = () => { form.jig_id = null; formJigSearch.value = ''; formJigOpen.value = true; };
const closeFormJigDropdown = () => { setTimeout(() => { formJigOpen.value = false; }, 180); };

const quickCreateJig = async (name: string, formTarget: 'add' | 'filter') => {
    isCreatingJig.value = true;
    try {
        const res = await fetch('/jig/improvement/quick-jig', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({ name }),
        });
        const newJig: Jig = await res.json();
        localJigs.push(newJig);
        if (formTarget === 'add') {
            form.jig_id         = newJig.id;
            formJigSearch.value = newJig.name;
            formJigOpen.value   = false;
        } else {
            filterJig.value = newJig.id;
            jigSearch.value = newJig.name;
            jigOpen.value   = false;
        }
    } finally {
        isCreatingJig.value = false;
    }
};

const spSearch = ref<string[]>([]);
const spOpen   = ref<boolean[]>([]);

const filteredSp   = (i: number) => { const q = (spSearch.value[i] ?? '').toLowerCase().trim(); if (!q) return props.spareparts; return props.spareparts.filter(s => s.name.toLowerCase().includes(q)); };
const selectSp     = (i: number, s: Sparepart) => { form.spareparts[i].sparepart_id = s.id; spSearch.value[i] = s.name; spOpen.value[i] = false; };
const openSpDropdown = (i: number) => { const existing = form.spareparts[i]?.sparepart_id; if (existing) { const sp = props.spareparts.find(s => s.id === existing); if (sp && !spSearch.value[i]) spSearch.value[i] = sp.name; } spOpen.value[i] = true; };
const closeSpDropdown  = (i: number) => { setTimeout(() => { spOpen.value[i] = false; }, 180); };
const clearSpSelection = (i: number) => { form.spareparts[i].sparepart_id = null; spSearch.value[i] = ''; spOpen.value[i] = true; };
const selectedSpItem   = (id: number | null) => props.spareparts.find(s => s.id === id);

const addSp    = () => { form.spareparts.push({ sparepart_id: null, qty: '', notes: '' }); spSearch.value.push(''); spOpen.value.push(false); };
const removeSp = (i: number) => { form.spareparts.splice(i, 1); spSearch.value.splice(i, 1); spOpen.value.splice(i, 1); };

const openAdd = () => {
    form.reset();
    form.spareparts     = [];
    spSearch.value      = [];
    spOpen.value        = [];
    formJigSearch.value = '';
    formJigOpen.value   = false;
    previewA.value      = null;
    previewB.value      = null;
    showAddModal.value  = true;
};

const closeAdd = () => {
    showAddModal.value  = false;
    previewA.value      = null;
    previewB.value      = null;
    spSearch.value      = [];
    spOpen.value        = [];
    formJigSearch.value = '';
    formJigOpen.value   = false;
    form.reset();
};

const handlePhoto = async (e: Event, field: 'photo' | 'photo_perbaikan') => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    if (field === 'photo') {
        isCompressingA.value = true;
        form.photo = await compressImage(file);
        const r = new FileReader(); r.onload = ev => { previewA.value = ev.target?.result as string; }; r.readAsDataURL(form.photo);
        isCompressingA.value = false;
    } else {
        isCompressingB.value = true;
        form.photo_perbaikan = await compressImage(file);
        const r = new FileReader(); r.onload = ev => { previewB.value = ev.target?.result as string; }; r.readAsDataURL(form.photo_perbaikan);
        isCompressingB.value = false;
    }
    (e.target as HTMLInputElement).value = '';
};

const submitAdd = () => form.post('/jig/improvement', { onSuccess: closeAdd });

const editForm = useForm({
    _method:         'PUT',
    description:     '',
    penyebab:        '',
    perbaikan:       '',
    photo:           null as File | null,
    photo_perbaikan: null as File | null,
    spareparts:      [] as { sparepart_id: number | null; qty: string; notes: string }[],
});

const spSearchE = ref<string[]>([]);
const spOpenE   = ref<boolean[]>([]);

const filteredSpE      = (i: number) => { const q = (spSearchE.value[i] ?? '').toLowerCase().trim(); if (!q) return props.spareparts; return props.spareparts.filter(s => s.name.toLowerCase().includes(q)); };
const selectSpE        = (i: number, s: Sparepart) => { editForm.spareparts[i].sparepart_id = s.id; spSearchE.value[i] = s.name; spOpenE.value[i] = false; };
const openSpDropdownE  = (i: number) => { const existing = editForm.spareparts[i]?.sparepart_id; if (existing) { const sp = props.spareparts.find(s => s.id === existing); if (sp && !spSearchE.value[i]) spSearchE.value[i] = sp.name; } spOpenE.value[i] = true; };
const closeSpDropdownE  = (i: number) => { setTimeout(() => { spOpenE.value[i] = false; }, 180); };
const clearSpSelectionE = (i: number) => { editForm.spareparts[i].sparepart_id = null; spSearchE.value[i] = ''; spOpenE.value[i] = true; };
const selectedSpItemE   = (id: number | null) => props.spareparts.find(s => s.id === id);

const addSpE    = () => { editForm.spareparts.push({ sparepart_id: null, qty: '', notes: '' }); spSearchE.value.push(''); spOpenE.value.push(false); };
const removeSpE = (i: number) => { editForm.spareparts.splice(i, 1); spSearchE.value.splice(i, 1); spOpenE.value.splice(i, 1); };

const openEdit = (r: ImprovementReport) => {
    selectedReport.value = r;
    previewC.value       = null;
    previewD.value       = null;
    spSearchE.value      = [];
    spOpenE.value        = [];
    editForm.reset();
    editForm.description = r.description ?? '';
    editForm.penyebab    = r.penyebab    ?? '';
    editForm.perbaikan   = r.perbaikan   ?? '';
    editForm.spareparts  = [];
    showEditModal.value  = true;
};

const closeEdit = () => {
    showEditModal.value = false;
    previewC.value      = null;
    previewD.value      = null;
    spSearchE.value     = [];
    spOpenE.value       = [];
    editForm.reset();
};

const handlePhotoEdit = async (e: Event, field: 'photo' | 'photo_perbaikan') => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    if (field === 'photo') {
        isCompressingC.value = true;
        editForm.photo = await compressImage(file);
        const r = new FileReader(); r.onload = ev => { previewC.value = ev.target?.result as string; }; r.readAsDataURL(editForm.photo);
        isCompressingC.value = false;
    } else {
        isCompressingD.value = true;
        editForm.photo_perbaikan = await compressImage(file);
        const r = new FileReader(); r.onload = ev => { previewD.value = ev.target?.result as string; }; r.readAsDataURL(editForm.photo_perbaikan);
        isCompressingD.value = false;
    }
    (e.target as HTMLInputElement).value = '';
};

const submitEdit = () => {
    if (!selectedReport.value) return;
    editForm.post(`/jig/improvement/${selectedReport.value.id}`, { onSuccess: closeEdit });
};

const closeForm = useForm({ action: '' });
const openClose = (r: ImprovementReport) => { selectedReport.value = r; closeForm.reset(); showCloseModal.value = true; };
const submitClose = () => {
    if (!selectedReport.value) return;
    closeForm.post(`/jig/improvement/${selectedReport.value.id}/close`, {
        onSuccess: () => { showCloseModal.value = false; selectedReport.value = null; },
    });
};

const openDetail = (r: ImprovementReport) => { selectedReport.value = r; showDetailModal.value = true; };
const openImage  = (path: string) => { imageSrc.value = `/storage/${path}`; showImageModal.value = true; };

const highlightMatch = (name: string, query: string): { text: string; match: boolean }[] => {
    if (!query.trim()) return [{ text: name, match: false }];
    const idx = name.toLowerCase().indexOf(query.toLowerCase().trim());
    if (idx === -1) return [{ text: name, match: false }];
    return [
        { text: name.slice(0, idx),                         match: false },
        { text: name.slice(idx, idx + query.trim().length), match: true  },
        { text: name.slice(idx + query.trim().length),      match: false },
    ].filter(p => p.text !== '');
};

const fmtDatetime = (d: string | null) => {
    if (!d) return '-';
    return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const statusCfg: Record<string, { label: string; badge: string; cardBorder: string; icon: any }> = {
    open:        { label: 'Open',        badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',                 cardBorder: 'border-l-red-400',     icon: AlertCircle  },
    in_progress: { label: 'In Progress', badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',         cardBorder: 'border-l-amber-400',   icon: Clock        },
    closed:      { label: 'Closed',      badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400', cardBorder: 'border-l-emerald-400', icon: CheckCircle2 },
};

const activeFilterCount = computed(() => {
    let c = 0;
    if (filterStatus.value) c++;
    if (filterJig.value)    c++;
    if (filterMonth.value)  c++;
    return c;
});
</script>
<template>
    <Head title="Improvement Report" />
    <AppLayout :breadcrumbs="[{title:'JIG',href:'/jig/dashboard'},{title:'Improvement Report',href:'/jig/improvement'}]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-blue-500 rounded-xl flex-shrink-0">
                            <TrendingUp class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
                        </span>
                        Improvement Report
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">Improvement & Kaizen JIG</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="exportData"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-2 sm:py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white rounded-xl font-semibold text-xs sm:text-sm transition-all shadow-sm">
                        <Download class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                        <span class="hidden sm:inline">Export Excel</span>
                        <span class="sm:hidden">Export</span>
                    </button>
                    <button @click="openAdd"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-2 sm:py-2.5 bg-blue-500 hover:bg-blue-600 active:scale-95 text-white rounded-xl font-semibold text-xs sm:text-sm transition-all shadow-sm">
                        <Plus class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                        <span class="hidden sm:inline">Buat Improvement</span>
                        <span class="sm:hidden">Buat</span>
                    </button>
                </div>
            </div>

            <div v-if="flash?.success"
                class="flex items-center gap-3 p-3 sm:p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl">
                <CheckCircle2 class="w-4 h-4 sm:w-5 sm:h-5 text-emerald-600 flex-shrink-0" />
                <p class="text-emerald-800 dark:text-emerald-200 font-medium text-xs sm:text-sm">{{ flash.success }}</p>
            </div>

            <div class="grid grid-cols-3 gap-2 sm:gap-3">
                <div class="text-center p-3 sm:p-4 rounded-2xl bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800">
                    <p class="text-xs text-red-500 font-semibold flex items-center justify-center gap-1">
                        <AlertCircle class="w-3 h-3" /><span class="hidden sm:inline">Open</span>
                    </p>
                    <p class="text-2xl sm:text-3xl font-black text-red-600 mt-0.5">{{ summary.open }}</p>
                    <p class="text-xs text-red-500 sm:hidden font-semibold">Open</p>
                </div>
                <div class="text-center p-3 sm:p-4 rounded-2xl bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800">
                    <p class="text-xs text-amber-600 font-semibold flex items-center justify-center gap-1">
                        <Clock class="w-3 h-3" /><span class="hidden sm:inline">In Progress</span>
                    </p>
                    <p class="text-2xl sm:text-3xl font-black text-amber-600 mt-0.5">{{ summary.in_progress }}</p>
                    <p class="text-xs text-amber-600 sm:hidden font-semibold">Progress</p>
                </div>
                <div class="text-center p-3 sm:p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800">
                    <p class="text-xs text-emerald-600 font-semibold flex items-center justify-center gap-1">
                        <CheckCircle2 class="w-3 h-3" /><span class="hidden sm:inline">Closed</span>
                    </p>
                    <p class="text-2xl sm:text-3xl font-black text-emerald-600 mt-0.5">{{ summary.closed }}</p>
                    <p class="text-xs text-emerald-600 sm:hidden font-semibold">Closed</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl p-3 shadow-sm space-y-2.5">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-hide flex-1 min-w-0">
                        <button v-for="m in months" :key="m.v" @click="filterMonth = m.v"
                            :class="['flex-shrink-0 px-3 py-1.5 rounded-xl text-xs font-bold transition-all active:scale-95',
                                filterMonth === m.v ? 'bg-blue-500 text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600']">
                            {{ m.l }}
                        </button>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-xl px-3 py-1.5">
                        <span class="text-sm font-black text-gray-800 dark:text-gray-200 tabular-nums w-10 text-center">{{ filterYear }}</span>
                        <div class="flex flex-col gap-0.5 ml-1">
                            <button @click="incrementYear" :disabled="filterYear >= currentYear"
                                class="p-0.5 text-gray-500 hover:text-blue-500 disabled:opacity-30 transition-colors">
                                <ChevronUp class="w-3 h-3" />
                            </button>
                            <button @click="decrementYear" :disabled="filterYear <= currentYear - 4"
                                class="p-0.5 text-gray-500 hover:text-blue-500 disabled:opacity-30 transition-colors">
                                <ChevronDown class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <button @click="showFilterPanel = !showFilterPanel"
                        :class="['relative flex items-center gap-1.5 px-3 py-2.5 border rounded-xl text-sm font-medium transition-colors',
                            showFilterPanel || activeFilterCount > 0
                                ? 'bg-blue-500 border-blue-500 text-white'
                                : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-blue-400']">
                        <Filter class="w-4 h-4" />
                        <span>Filter</span>
                        <span v-if="activeFilterCount > 0"
                            class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold">
                            {{ activeFilterCount }}
                        </span>
                    </button>
                    <span class="text-xs text-gray-400">{{ reports.length }} laporan</span>
                    <button v-if="activeFilterCount > 0" @click="filterStatus = ''; clearJig()"
                        class="text-xs text-blue-500 font-semibold ml-auto">Reset filter</button>
                </div>

                <div v-if="showFilterPanel" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-3 space-y-3 shadow-sm">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Status</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="opt in [{v:'',l:'Semua'},{v:'open',l:'Open'},{v:'in_progress',l:'In Progress'},{v:'closed',l:'Closed'}]"
                                :key="opt.v" @click="filterStatus = opt.v"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors active:scale-95',
                                    filterStatus === opt.v ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
                                {{ opt.l }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">JIG</label>
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                            <input v-model="jigSearch" type="text" placeholder="Cari nama / tipe / line JIG..." autocomplete="off"
                                @focus="jigOpen = true" @blur="closeJigDropdown"
                                :class="['w-full pl-8 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                    selectedJigObj ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-blue-400']" />
                            <button v-if="selectedJigObj" type="button" @click="clearJig"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                                <X class="w-4 h-4" />
                            </button>
                            <div v-if="jigOpen"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                                <div v-if="filteredJigs.length === 0 && jigSearch.trim()" class="p-2">
                                    <button type="button" @mousedown.prevent="quickCreateJig(jigSearch.trim(), 'filter')" :disabled="isCreatingJig"
                                        class="w-full flex items-center gap-2 px-3 py-2.5 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 rounded-lg text-xs font-semibold text-blue-600 dark:text-blue-400 transition-colors disabled:opacity-50">
                                        <Loader2 v-if="isCreatingJig" class="w-3.5 h-3.5 animate-spin flex-shrink-0" />
                                        <Plus v-else class="w-3.5 h-3.5 flex-shrink-0" />
                                        Tambah JIG baru "{{ jigSearch.trim() }}"
                                    </button>
                                </div>
                                <div v-else-if="filteredJigs.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada JIG</div>
                                <button v-for="j in filteredJigs" :key="j.id" type="button" @mousedown.prevent="selectJig(j)"
                                    :class="['w-full text-left px-3 py-2.5 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors', selectedJigObj?.id === j.id ? 'bg-blue-50 dark:bg-blue-900/20' : '']">
                                    <span class="block text-xs font-semibold text-gray-800 dark:text-gray-200">
                                        <template v-for="(part, pi) in highlightMatch(j.name, jigSearch)" :key="pi">
                                            <mark v-if="part.match" class="bg-yellow-200 dark:bg-yellow-700 text-gray-900 dark:text-white rounded px-0.5 not-italic">{{ part.text }}</mark>
                                            <span v-else>{{ part.text }}</span>
                                        </template>
                                    </span>
                                    <span class="text-xs text-gray-400">{{ j.type }} — {{ j.line }}</span>
                                </button>
                            </div>
                        </div>
                        <div v-if="selectedJigObj" class="mt-1.5 flex items-center gap-1.5 text-xs text-blue-600 dark:text-blue-400">
                            <span class="font-semibold">{{ selectedJigObj.name }}</span>
                            <span class="text-gray-400">{{ selectedJigObj.type }} — {{ selectedJigObj.line }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop Table -->
            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">JIG</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">PIC</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Waktu Laporan</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Deskripsi</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Penyebab</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">SP</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Durasi</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="reports.length === 0">
                                <td colspan="9" class="py-16 text-center text-gray-400 text-sm">
                                    <TrendingUp class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                    Tidak ada laporan Improvement
                                </td>
                            </tr>
                            <tr v-for="r in reports" :key="r.id" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ r.jig?.name }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ r.jig?.type }} — {{ r.jig?.line }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ r.pic?.name }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ fmtDatetime(r.report_date) }}</td>
                                <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-300 max-w-[180px]">
                                    <p v-if="r.description" class="line-clamp-2">{{ r.description }}</p>
                                    <span v-else class="text-gray-300 italic">Belum diisi</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 max-w-[140px]">
                                    <p v-if="r.penyebab" class="line-clamp-2">{{ r.penyebab }}</p>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="r.spareparts?.length"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400 rounded-full text-xs font-bold">
                                        <Package class="w-3 h-3" /> {{ r.spareparts.length }}
                                    </span>
                                    <span v-else class="text-xs text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="r.repair_duration"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-400 rounded-full text-xs font-bold">
                                        <Timer class="w-3 h-3" /> {{ r.repair_duration }}
                                    </span>
                                    <span v-else class="text-xs text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[r.status].badge]">
                                        <component :is="statusCfg[r.status].icon" class="w-3 h-3" />
                                        {{ statusCfg[r.status].label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button @click="openDetail(r)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-200 transition-colors">
                                            <FileText class="w-3 h-3" /> Detail
                                        </button>
                                        <button v-if="r.status !== 'closed'" @click="openEdit(r)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-lg text-xs font-semibold hover:bg-amber-200 transition-colors">
                                            <TrendingUp class="w-3 h-3" /> Edit
                                        </button>
                                        <button v-if="r.status !== 'closed'" @click="openClose(r)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 transition-colors">
                                            <CheckCircle2 class="w-3 h-3" /> Close
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Cards -->
            <div class="lg:hidden space-y-2.5">
                <div v-if="reports.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <TrendingUp class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                    <p class="text-gray-400 text-sm">Tidak ada laporan Improvement</p>
                </div>
                <div v-for="r in reports" :key="r.id"
                    :class="['bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden border-l-4', statusCfg[r.status].cardBorder]">
                    <div class="p-3.5">
                        <div class="flex items-start justify-between gap-2 mb-2.5">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-900 dark:text-white leading-tight">{{ r.jig?.name }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ r.jig?.type }} — {{ r.jig?.line }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0 ml-2">
                                <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[r.status].badge]">
                                    <component :is="statusCfg[r.status].icon" class="w-3 h-3" />
                                    {{ statusCfg[r.status].label }}
                                </span>
                                <span v-if="r.repair_duration"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-400 rounded-full text-xs font-bold">
                                    <Timer class="w-3 h-3" /> {{ r.repair_duration }}
                                </span>
                                <span v-if="r.spareparts?.length"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400 rounded-full text-xs font-bold">
                                    <Package class="w-3 h-3" /> {{ r.spareparts.length }} SP
                                </span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs mb-2.5">
                            <div>
                                <span class="text-gray-400">PIC</span>
                                <p class="font-semibold text-gray-700 dark:text-gray-300">{{ r.pic?.name }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400">Waktu Laporan</span>
                                <p class="font-semibold text-gray-700 dark:text-gray-300">{{ fmtDatetime(r.report_date) }}</p>
                            </div>
                            <div class="col-span-2">
                                <span class="text-gray-400">Deskripsi</span>
                                <p v-if="r.description" class="font-semibold text-gray-700 dark:text-gray-300 line-clamp-2">{{ r.description }}</p>
                                <p v-else class="italic text-gray-300 text-xs">Belum diisi</p>
                            </div>
                            <div v-if="r.penyebab" class="col-span-2">
                                <span class="text-gray-400">Penyebab</span>
                                <p class="font-semibold text-gray-700 dark:text-gray-300 line-clamp-1">{{ r.penyebab }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-2.5 border-t border-gray-100 dark:border-gray-700">
                            <button v-if="r.status !== 'closed'" @click="openClose(r)"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 active:scale-95 transition-all">
                                <CheckCircle2 class="w-3.5 h-3.5" /> Close
                            </button>
                            <button v-if="r.status !== 'closed'" @click="openEdit(r)"
                                class="flex items-center justify-center gap-1.5 py-2 px-4 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-xl text-xs font-bold hover:bg-amber-200 active:scale-95 transition-all">
                                <TrendingUp class="w-3.5 h-3.5" /> Edit
                            </button>
                            <button @click="openDetail(r)"
                                :class="['flex items-center justify-center gap-1.5 py-2 px-4 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-bold hover:bg-gray-200 active:scale-95 transition-all',
                                    r.status !== 'closed' ? '' : 'flex-1']">
                                <FileText class="w-3.5 h-3.5" /> Detail
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Add -->
        <div v-if="showAddModal" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-2xl max-h-[95vh] flex flex-col shadow-2xl">
                <div class="flex-shrink-0">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <TrendingUp class="w-4 h-4 text-blue-500" /> Buat Improvement
                        </h2>
                        <button @click="closeAdd" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <form @submit.prevent="submitAdd" class="overflow-y-auto flex-1 px-4 sm:px-6 py-4 sm:py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">JIG <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                            <input v-model="formJigSearch" type="text" placeholder="Cari nama / tipe / line JIG..." autocomplete="off"
                                @focus="formJigOpen = true" @blur="closeFormJigDropdown"
                                :class="['w-full pl-8 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                    form.jig_id ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-blue-400']" />
                            <button v-if="form.jig_id" type="button" @click="clearFormJig"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                                <X class="w-4 h-4" />
                            </button>
                            <div v-if="formJigOpen"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                                <div v-if="filteredFormJigs.length === 0 && formJigSearch.trim()" class="p-2">
                                    <button type="button" @mousedown.prevent="quickCreateJig(formJigSearch.trim(), 'add')" :disabled="isCreatingJig"
                                        class="w-full flex items-center gap-2 px-3 py-2.5 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 rounded-lg text-xs font-semibold text-blue-600 dark:text-blue-400 transition-colors disabled:opacity-50">
                                        <Loader2 v-if="isCreatingJig" class="w-3.5 h-3.5 animate-spin flex-shrink-0" />
                                        <Plus v-else class="w-3.5 h-3.5 flex-shrink-0" />
                                        Tambah baru "{{ formJigSearch.trim() }}"
                                    </button>
                                </div>
                                <div v-else-if="filteredFormJigs.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Ketik nama JIG untuk mencari</div>
                                <button v-for="j in filteredFormJigs" :key="j.id" type="button" @mousedown.prevent="selectFormJig(j)"
                                    :class="['w-full text-left px-3 py-2.5 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors', form.jig_id === j.id ? 'bg-blue-50 dark:bg-blue-900/20' : '']">
                                    <span class="block text-xs font-semibold text-gray-800 dark:text-gray-200">
                                        <template v-for="(part, pi) in highlightMatch(j.name, formJigSearch)" :key="pi">
                                            <mark v-if="part.match" class="bg-yellow-200 dark:bg-yellow-700 text-gray-900 dark:text-white rounded px-0.5 not-italic">{{ part.text }}</mark>
                                            <span v-else>{{ part.text }}</span>
                                        </template>
                                    </span>
                                    <span class="text-xs text-gray-400">{{ j.type }} — {{ j.line }}</span>
                                </button>
                            </div>
                        </div>
                        <p v-if="form.errors.jig_id" class="mt-1 text-xs text-red-500">{{ form.errors.jig_id }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Deskripsi <span class="text-gray-400 font-normal text-xs ml-1">(opsional)</span></label>
                        <textarea v-model="form.description" rows="3" placeholder="Jelaskan improvement yang akan dilakukan..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none resize-none transition-colors"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Kondisi Saat Ini <span class="text-gray-400 font-normal text-xs ml-1">(opsional)</span></label>
                        <textarea v-model="form.penyebab" rows="2" placeholder="Kondisi/masalah yang ada saat ini..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none resize-none transition-colors"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Rencana Perbaikan <span class="text-gray-400 font-normal text-xs ml-1">(opsional)</span></label>
                        <textarea v-model="form.perbaikan" rows="2" placeholder="Rencana tindakan improvement..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-blue-500 focus:outline-none resize-none transition-colors"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-2 text-gray-700 dark:text-gray-300">Foto Before</label>
                            <div @click="openPhotoPicker('add', 'photo')"
                                :class="['cursor-pointer flex flex-col items-center justify-center border-2 border-dashed rounded-xl transition-all min-h-[8rem]',
                                    previewA ? 'border-blue-300 bg-blue-50 dark:bg-blue-900/20 p-0 overflow-hidden' : 'border-gray-200 hover:border-blue-300 bg-gray-50 dark:bg-gray-700/30 p-3']">
                                <Loader2 v-if="isCompressingA" class="w-7 h-7 text-blue-400 animate-spin" />
                                <img v-else-if="previewA" :src="previewA" class="w-full h-full object-cover" style="min-height:8rem" />
                                <div v-else class="text-center">
                                    <Upload class="w-7 h-7 text-gray-400 mx-auto mb-1.5" />
                                    <p class="text-xs text-gray-500 font-medium">Upload foto</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Kamera / Galeri</p>
                                </div>
                            </div>
                            <input id="cam-add-photo" type="file" accept="image/*" capture="environment" @change="e => handlePhoto(e, 'photo')" class="hidden" />
                            <input id="gal-add-photo" type="file" accept="image/*" @change="e => handlePhoto(e, 'photo')" class="hidden" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-2 text-gray-700 dark:text-gray-300">Foto After</label>
                            <div @click="openPhotoPicker('add', 'photo_perbaikan')"
                                :class="['cursor-pointer flex flex-col items-center justify-center border-2 border-dashed rounded-xl transition-all min-h-[8rem]',
                                    previewB ? 'border-emerald-300 bg-emerald-50 dark:bg-emerald-900/20 p-0 overflow-hidden' : 'border-gray-200 hover:border-emerald-300 bg-gray-50 dark:bg-gray-700/30 p-3']">
                                <Loader2 v-if="isCompressingB" class="w-7 h-7 text-emerald-400 animate-spin" />
                                <img v-else-if="previewB" :src="previewB" class="w-full h-full object-cover" style="min-height:8rem" />
                                <div v-else class="text-center">
                                    <Upload class="w-7 h-7 text-gray-400 mx-auto mb-1.5" />
                                    <p class="text-xs text-gray-500 font-medium">Upload foto</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Kamera / Galeri</p>
                                </div>
                            </div>
                            <input id="cam-add-photo_perbaikan" type="file" accept="image/*" capture="environment" @change="e => handlePhoto(e, 'photo_perbaikan')" class="hidden" />
                            <input id="gal-add-photo_perbaikan" type="file" accept="image/*" @change="e => handlePhoto(e, 'photo_perbaikan')" class="hidden" />
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Sparepart Digunakan</label>
                            <button type="button" @click="addSp"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-xs bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400 rounded-lg font-semibold hover:bg-indigo-200 active:scale-95 transition-all">
                                <Plus class="w-3 h-3" /> Tambah
                            </button>
                        </div>
                        <div v-for="(sp, i) in form.spareparts" :key="i" class="mb-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl space-y-2.5">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <div class="relative">
                                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                                        <input v-model="spSearch[i]" type="text" placeholder="Cari sparepart..." autocomplete="off"
                                            @focus="openSpDropdown(i)" @blur="closeSpDropdown(i)"
                                            :class="['w-full pl-7 pr-7 py-2.5 border rounded-xl text-xs focus:outline-none transition-colors dark:bg-gray-700',
                                                sp.sparepart_id ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-indigo-400']" />
                                        <button v-if="sp.sparepart_id" type="button" @click="clearSpSelection(i)"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors p-0.5">
                                            <X class="w-3 h-3" />
                                        </button>
                                    </div>
                                    <div v-if="spOpen[i]" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                        <div v-if="filteredSp(i).length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                        <button v-for="s in filteredSp(i)" :key="s.id" type="button" @mousedown.prevent="selectSp(i, s)"
                                            :class="['w-full text-left px-3 py-2.5 text-xs hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors flex items-center justify-between gap-2',
                                                sp.sparepart_id === s.id ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-gray-700 dark:text-gray-300']">
                                            <span>
                                                <template v-for="(part, pi) in highlightMatch(s.name, spSearch[i])" :key="pi">
                                                    <mark v-if="part.match" class="bg-yellow-200 dark:bg-yellow-700 text-gray-900 dark:text-white rounded px-0.5 not-italic">{{ part.text }}</mark>
                                                    <span v-else>{{ part.text }}</span>
                                                </template>
                                            </span>
                                            <span class="text-gray-400 whitespace-nowrap shrink-0">{{ Math.floor(s.stok) }} {{ s.satuan }}</span>
                                        </button>
                                    </div>
                                </div>
                                <input v-model="sp.qty" type="number" inputmode="numeric" step="1" min="1" placeholder="Qty"
                                    class="w-16 sm:w-20 px-2 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none text-center" />
                                <button type="button" @click="removeSp(i)" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors active:scale-95">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                            <input v-model="sp.notes" type="text" placeholder="Keterangan (opsional)"
                                class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none transition-colors" />
                            <p v-if="sp.sparepart_id && selectedSpItem(sp.sparepart_id) && parseInt(sp.qty) > selectedSpItem(sp.sparepart_id)!.stok"
                                class="text-xs text-red-500 flex items-center gap-1">
                                <AlertCircle class="w-3 h-3" />
                                Stok tidak cukup (tersedia: {{ Math.floor(selectedSpItem(sp.sparepart_id)!.stok) }})
                            </p>
                        </div>
                    </div>
                    <div class="sticky bottom-0 bg-white dark:bg-gray-800 pt-3 pb-safe border-t border-gray-100 dark:border-gray-700 -mx-4 sm:-mx-6 px-4 sm:px-6">
                        <div class="flex gap-3">
                            <button type="button" @click="closeAdd"
                                class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 font-semibold text-sm active:scale-95 transition-all">
                                Batal
                            </button>
                            <button type="submit" :disabled="form.processing"
                                class="flex-1 py-3 bg-blue-500 text-white rounded-xl hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed font-bold text-sm active:scale-95 transition-all">
                                {{ form.processing ? 'Menyimpan...' : 'Buat Improvement' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Edit -->
        <div v-if="showEditModal && selectedReport" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-2xl max-h-[95vh] flex flex-col shadow-2xl">
                <div class="flex-shrink-0">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <TrendingUp class="w-4 h-4 text-amber-500" /> Edit Improvement
                        </h2>
                        <button @click="closeEdit" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <form @submit.prevent="submitEdit" class="overflow-y-auto flex-1 px-4 sm:px-6 py-4 sm:py-5 space-y-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3 border border-blue-100 dark:border-blue-800">
                        <p class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedReport.jig?.name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">Mulai: {{ fmtDatetime(selectedReport.report_date) }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Deskripsi</label>
                        <textarea v-model="editForm.description" rows="3" placeholder="Jelaskan improvement..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-amber-500 focus:outline-none resize-none transition-colors"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Kondisi Saat Ini</label>
                        <textarea v-model="editForm.penyebab" rows="2" placeholder="Kondisi/masalah yang ada..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-amber-500 focus:outline-none resize-none transition-colors"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Rencana Perbaikan</label>
                        <textarea v-model="editForm.perbaikan" rows="2" placeholder="Tindakan yang dilakukan..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-amber-500 focus:outline-none resize-none transition-colors"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-2 text-gray-700 dark:text-gray-300">
                                Foto Before
                                <span v-if="selectedReport.photo" class="text-emerald-500 font-normal ml-1">✓ Ada</span>
                            </label>
                            <div @click="openPhotoPicker('edit', 'photo')"
                                :class="['cursor-pointer flex flex-col items-center justify-center border-2 border-dashed rounded-xl transition-all min-h-[8rem]',
                                    previewC ? 'border-amber-300 bg-amber-50 dark:bg-amber-900/20 p-0 overflow-hidden' : 'border-gray-200 hover:border-amber-300 bg-gray-50 dark:bg-gray-700/30 p-0 overflow-hidden']">
                                <Loader2 v-if="isCompressingC" class="w-7 h-7 text-amber-400 animate-spin" />
                                <img v-else-if="previewC" :src="previewC" class="w-full h-full object-cover" style="min-height:8rem" />
                                <img v-else-if="selectedReport.photo" :src="`/storage/${selectedReport.photo}`" class="w-full h-full object-cover" style="min-height:8rem" />
                                <div v-else class="text-center p-3">
                                    <Upload class="w-7 h-7 text-gray-400 mx-auto mb-1.5" />
                                    <p class="text-xs text-gray-500 font-medium">Upload foto</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Kamera / Galeri</p>
                                </div>
                            </div>
                            <input id="cam-edit-photo" type="file" accept="image/*" capture="environment" @change="e => handlePhotoEdit(e, 'photo')" class="hidden" />
                            <input id="gal-edit-photo" type="file" accept="image/*" @change="e => handlePhotoEdit(e, 'photo')" class="hidden" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-2 text-gray-700 dark:text-gray-300">
                                Foto After
                                <span v-if="selectedReport.photo_perbaikan" class="text-emerald-500 font-normal ml-1">✓ Ada</span>
                            </label>
                            <div @click="openPhotoPicker('edit', 'photo_perbaikan')"
                                :class="['cursor-pointer flex flex-col items-center justify-center border-2 border-dashed rounded-xl transition-all min-h-[8rem]',
                                    previewD ? 'border-emerald-300 bg-emerald-50 dark:bg-emerald-900/20 p-0 overflow-hidden' : 'border-gray-200 hover:border-emerald-300 bg-gray-50 dark:bg-gray-700/30 p-0 overflow-hidden']">
                                <Loader2 v-if="isCompressingD" class="w-7 h-7 text-emerald-400 animate-spin" />
                                <img v-else-if="previewD" :src="previewD" class="w-full h-full object-cover" style="min-height:8rem" />
                                <img v-else-if="selectedReport.photo_perbaikan" :src="`/storage/${selectedReport.photo_perbaikan}`" class="w-full h-full object-cover" style="min-height:8rem" />
                                <div v-else class="text-center p-3">
                                    <Upload class="w-7 h-7 text-gray-400 mx-auto mb-1.5" />
                                    <p class="text-xs text-gray-500 font-medium">Upload foto</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Kamera / Galeri</p>
                                </div>
                            </div>
                            <input id="cam-edit-photo_perbaikan" type="file" accept="image/*" capture="environment" @change="e => handlePhotoEdit(e, 'photo_perbaikan')" class="hidden" />
                            <input id="gal-edit-photo_perbaikan" type="file" accept="image/*" @change="e => handlePhotoEdit(e, 'photo_perbaikan')" class="hidden" />
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Sparepart Digunakan
                                <span v-if="selectedReport.spareparts?.length" class="text-xs text-gray-400 font-normal ml-1">({{ selectedReport.spareparts.length }} sudah tercatat)</span>
                            </label>
                            <button type="button" @click="addSpE"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-xs bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400 rounded-lg font-semibold hover:bg-indigo-200 active:scale-95 transition-all">
                                <Plus class="w-3 h-3" /> Tambah
                            </button>
                        </div>
                        <div v-if="selectedReport.spareparts?.length" class="mb-3 space-y-1.5">
                            <div v-for="sp in selectedReport.spareparts" :key="sp.sparepart?.id"
                                class="flex justify-between items-center px-3 py-2 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg text-xs">
                                <span class="font-semibold text-indigo-700 dark:text-indigo-300">{{ sp.sparepart?.name }}</span>
                                <span class="text-gray-500">{{ sp.qty }} {{ sp.sparepart?.satuan }}</span>
                            </div>
                        </div>
                        <div v-for="(sp, i) in editForm.spareparts" :key="i" class="mb-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl space-y-2.5">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <div class="relative">
                                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                                        <input v-model="spSearchE[i]" type="text" placeholder="Cari sparepart..." autocomplete="off"
                                            @focus="openSpDropdownE(i)" @blur="closeSpDropdownE(i)"
                                            :class="['w-full pl-7 pr-7 py-2.5 border rounded-xl text-xs focus:outline-none transition-colors dark:bg-gray-700',
                                                sp.sparepart_id ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-indigo-400']" />
                                        <button v-if="sp.sparepart_id" type="button" @click="clearSpSelectionE(i)"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors p-0.5">
                                            <X class="w-3 h-3" />
                                        </button>
                                    </div>
                                    <div v-if="spOpenE[i]" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                        <div v-if="filteredSpE(i).length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil</div>
                                        <button v-for="s in filteredSpE(i)" :key="s.id" type="button" @mousedown.prevent="selectSpE(i, s)"
                                            :class="['w-full text-left px-3 py-2.5 text-xs hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors flex items-center justify-between gap-2',
                                                sp.sparepart_id === s.id ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-gray-700 dark:text-gray-300']">
                                            <span>
                                                <template v-for="(part, pi) in highlightMatch(s.name, spSearchE[i])" :key="pi">
                                                    <mark v-if="part.match" class="bg-yellow-200 dark:bg-yellow-700 text-gray-900 dark:text-white rounded px-0.5 not-italic">{{ part.text }}</mark>
                                                    <span v-else>{{ part.text }}</span>
                                                </template>
                                            </span>
                                            <span class="text-gray-400 whitespace-nowrap shrink-0">{{ Math.floor(s.stok) }} {{ s.satuan }}</span>
                                        </button>
                                    </div>
                                </div>
                                <input v-model="sp.qty" type="number" inputmode="numeric" step="1" min="1" placeholder="Qty"
                                    class="w-16 sm:w-20 px-2 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none text-center" />
                                <button type="button" @click="removeSpE(i)" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors active:scale-95">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                            <input v-model="sp.notes" type="text" placeholder="Keterangan (opsional)"
                                class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none transition-colors" />
                            <p v-if="sp.sparepart_id && selectedSpItemE(sp.sparepart_id) && parseInt(sp.qty) > selectedSpItemE(sp.sparepart_id)!.stok"
                                class="text-xs text-red-500 flex items-center gap-1">
                                <AlertCircle class="w-3 h-3" />
                                Stok tidak cukup (tersedia: {{ Math.floor(selectedSpItemE(sp.sparepart_id)!.stok) }})
                            </p>
                        </div>
                    </div>
                    <div class="sticky bottom-0 bg-white dark:bg-gray-800 pt-3 pb-safe border-t border-gray-100 dark:border-gray-700 -mx-4 sm:-mx-6 px-4 sm:px-6">
                        <div class="flex gap-3">
                            <button type="button" @click="closeEdit"
                                class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 font-semibold text-sm active:scale-95 transition-all">
                                Batal
                            </button>
                            <button type="submit" :disabled="editForm.processing"
                                class="flex-1 py-3 bg-amber-500 text-white rounded-xl hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed font-bold text-sm active:scale-95 transition-all">
                                {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Detail -->
        <div v-if="showDetailModal && selectedReport" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-lg max-h-[92vh] overflow-y-auto shadow-2xl">
                <div class="sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Detail Improvement</h2>
                        <button @click="showDetailModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <div class="p-4 sm:p-5 space-y-3.5">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 space-y-2.5 text-xs">
                        <div class="flex justify-between gap-2"><span class="text-gray-400 shrink-0">JIG</span><span class="font-bold text-gray-900 dark:text-white text-right">{{ selectedReport.jig?.name }}</span></div>
                        <div class="flex justify-between gap-2"><span class="text-gray-400 shrink-0">PIC</span><span class="font-bold text-gray-900 dark:text-white">{{ selectedReport.pic?.name }}</span></div>
                        <div class="flex justify-between gap-2"><span class="text-gray-400 shrink-0">Waktu Laporan</span><span class="font-bold text-gray-900 dark:text-white">{{ fmtDatetime(selectedReport.report_date) }}</span></div>
                        <div class="flex justify-between items-center gap-2">
                            <span class="text-gray-400 shrink-0">Status</span>
                            <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-bold', statusCfg[selectedReport.status].badge]">
                                <component :is="statusCfg[selectedReport.status].icon" class="w-3 h-3" />
                                {{ statusCfg[selectedReport.status].label }}
                            </span>
                        </div>
                        <div v-if="selectedReport.closed_by" class="flex justify-between gap-2"><span class="text-gray-400 shrink-0">Ditutup oleh</span><span class="font-bold text-gray-900 dark:text-white text-right">{{ selectedReport.closed_by.name }} · {{ fmtDatetime(selectedReport.closed_at) }}</span></div>
                        <div v-if="selectedReport.repair_duration" class="flex justify-between gap-2 pt-1 border-t border-gray-200 dark:border-gray-600">
                            <span class="text-gray-400 shrink-0 flex items-center gap-1"><Timer class="w-3 h-3" /> Durasi</span>
                            <span class="font-black text-violet-600 dark:text-violet-400">{{ selectedReport.repair_duration }}</span>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3.5">
                        <p class="text-xs text-gray-400 font-semibold uppercase mb-1.5">Deskripsi</p>
                        <p v-if="selectedReport.description" class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedReport.description }}</p>
                        <p v-else class="text-xs text-gray-400 italic">Belum diisi</p>
                    </div>
                    <div v-if="selectedReport.penyebab" class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-3.5 border border-amber-100 dark:border-amber-800">
                        <p class="text-xs text-amber-600 font-semibold uppercase mb-1.5">Kondisi Saat Ini</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedReport.penyebab }}</p>
                    </div>
                    <div v-if="selectedReport.perbaikan" class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3.5 border border-blue-100 dark:border-blue-800">
                        <p class="text-xs text-blue-600 font-semibold uppercase mb-1.5">Rencana Perbaikan</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedReport.perbaikan }}</p>
                    </div>
                    <div v-if="selectedReport.action" class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-3.5 border border-emerald-100 dark:border-emerald-800">
                        <p class="text-xs text-emerald-600 font-semibold uppercase mb-1.5">Hasil / Kesimpulan</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ selectedReport.action }}</p>
                    </div>
                    <div v-if="selectedReport.photo || selectedReport.photo_perbaikan" class="grid grid-cols-2 gap-3">
                        <div v-if="selectedReport.photo">
                            <p class="text-xs font-semibold text-gray-400 uppercase mb-2 flex items-center gap-1"><ImageIcon class="w-3 h-3" /> Foto Before</p>
                            <img :src="`/storage/${selectedReport.photo}`" @click="openImage(selectedReport.photo!)"
                                class="w-full rounded-xl cursor-pointer hover:opacity-90 active:opacity-80 h-32 sm:h-40 object-cover shadow-sm transition-opacity" />
                        </div>
                        <div v-if="selectedReport.photo_perbaikan">
                            <p class="text-xs font-semibold text-gray-400 uppercase mb-2 flex items-center gap-1"><ImageIcon class="w-3 h-3" /> Foto After</p>
                            <img :src="`/storage/${selectedReport.photo_perbaikan}`" @click="openImage(selectedReport.photo_perbaikan!)"
                                class="w-full rounded-xl cursor-pointer hover:opacity-90 active:opacity-80 h-32 sm:h-40 object-cover shadow-sm transition-opacity" />
                        </div>
                    </div>
                    <div v-if="selectedReport.spareparts?.length">
                        <p class="text-xs font-semibold text-gray-400 uppercase mb-2 flex items-center gap-1.5"><Package class="w-3.5 h-3.5" /> Sparepart Digunakan</p>
                        <div class="space-y-2">
                            <div v-for="sp in selectedReport.spareparts" :key="sp.sparepart?.id" class="px-3 py-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                                <div class="flex justify-between text-xs">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ sp.sparepart?.name }}</span>
                                    <span class="text-gray-500 font-medium">{{ sp.qty }} {{ sp.sparepart?.satuan }}</span>
                                </div>
                                <p v-if="sp.notes" class="text-xs text-gray-400 mt-0.5">{{ sp.notes }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sticky bottom-0 bg-white dark:bg-gray-800 p-4 border-t border-gray-100 dark:border-gray-700">
                    <button @click="showDetailModal = false"
                        class="w-full py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-200 active:scale-95 transition-all">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Close -->
        <div v-if="showCloseModal && selectedReport" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <CheckCircle2 class="w-5 h-5 text-emerald-600" /> Tutup Improvement
                    </h2>
                    <button @click="showCloseModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <form @submit.prevent="submitClose" class="p-4 sm:p-5 space-y-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3.5 border border-blue-100 dark:border-blue-800">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedReport.jig?.name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Mulai: {{ fmtDatetime(selectedReport.report_date) }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Hasil / Kesimpulan <span class="text-gray-400 font-normal text-xs ml-1">(opsional)</span></label>
                        <textarea v-model="closeForm.action" rows="4" placeholder="Tuliskan hasil improvement..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-emerald-500 focus:outline-none resize-none transition-colors"></textarea>
                        <p v-if="closeForm.errors.action" class="mt-1 text-xs text-red-500">{{ closeForm.errors.action }}</p>
                    </div>
                    <div class="flex gap-3 pb-safe">
                        <button type="button" @click="showCloseModal = false"
                            class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button type="submit" :disabled="closeForm.processing"
                            class="flex-1 py-3 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 disabled:opacity-50 font-bold text-sm active:scale-95 transition-all">
                            {{ closeForm.processing ? 'Menyimpan...' : 'Tutup Improvement' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Image -->
        <div v-if="showImageModal" class="fixed inset-0 bg-black/90 flex items-center justify-center z-50 p-4" @click="showImageModal = false">
            <button class="absolute top-4 right-4 p-2 bg-white/10 rounded-xl text-white hover:bg-white/20 transition-colors">
                <X class="w-5 h-5" />
            </button>
            <img :src="imageSrc" class="max-w-full max-h-full rounded-xl object-contain" />
        </div>

        <!-- Photo Picker Sheet -->
        <div v-if="showPhotoPicker" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] flex items-end justify-center p-4" @click.self="showPhotoPicker = false">
            <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-4"></div>
                <p class="text-center text-sm font-bold text-gray-700 dark:text-gray-200 mb-3 px-5">Pilih Sumber Foto</p>
                <div class="grid grid-cols-2 gap-3 px-4 pb-5">
                    <button @click="triggerInput('cam')"
                        class="flex flex-col items-center gap-2.5 py-5 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold text-sm hover:bg-indigo-100 active:scale-95 transition-all">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Kamera
                    </button>
                    <button @click="triggerInput('gal')"
                        class="flex flex-col items-center gap-2.5 py-5 rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 font-semibold text-sm hover:bg-emerald-100 active:scale-95 transition-all">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Galeri
                    </button>
                </div>
            </div>
        </div>

    </AppLayout>
</template>
