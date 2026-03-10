<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import {
    ClipboardList, CheckCircle2, Clock, AlertTriangle, X,
    Plus, Trash2, Package, FileText, Upload, Loader2, ShieldCheck, Search,
    Filter, Download
} from 'lucide-vue-next';
import * as XLSX from 'xlsx';

interface Sparepart { id: number; name: string; satuan: string; stok: number; }
interface ReportSp  { sparepart: Sparepart; qty: number; notes: string | null; }
interface PmReport  {
    id: number;
    planned_week_start: string;
    planned_week_end:   string;
    actual_date:        string | null;
    status:             'pending' | 'done' | 'late';
    condition:          'ok' | 'nok' | null;
    notes:              string | null;
    photo:              string | null;
    photo_sparepart:    string | null;
    nok_closed_by:      number | null;
    nok_closed_at:      string | null;
    nok_closed_by_user: { name: string } | null;
    pm_schedule:        { jig: { name: string; type: string; line: string } };
    pic:                { name: string };
    spareparts:         ReportSp[];
}

interface Props {
    reports:    PmReport[];
    spareparts: Sparepart[];
    summary:    { total: number; done: number; late: number; pending: number };
    isLeader:   boolean;
    filters:    { bulan?: any; tahun?: any; status?: string; minggu?: any };
}

const props = defineProps<Props>();
const page  = usePage();
const flash = computed(() => (page.props as any).flash);

const filterBulan  = ref(props.filters.bulan  ?? new Date().getMonth() + 1);
const filterTahun  = ref(props.filters.tahun  ?? new Date().getFullYear());
const filterStatus = ref(props.filters.status ?? '');
const filterMinggu = ref(props.filters.minggu ?? '');
const showFilterPanel = ref(false);

watch([filterBulan, filterTahun, filterStatus, filterMinggu], () => {
    router.get('/jig/pm/report', {
        bulan:  filterBulan.value,
        tahun:  filterTahun.value,
        status: filterStatus.value,
        minggu: filterMinggu.value,
    }, { preserveState: true, preserveScroll: true });
});

const BULAN_LABEL: Record<number | string, string> = {
    1:'Januari',2:'Februari',3:'Maret',4:'April',5:'Mei',6:'Juni',
    7:'Juli',8:'Agustus',9:'September',10:'Oktober',11:'November',12:'Desember',
    all:'Semua Bulan',
};

const MINGGU_LIST = [1,2,3,4,5].map(w => ({ val: w, label: `Week ${w}` }));

const periodeLabel = computed(() => {
    const bulanStr = filterBulan.value === 'all'
        ? `Tahun ${filterTahun.value}`
        : `${BULAN_LABEL[filterBulan.value]} ${filterTahun.value}`;
    return filterMinggu.value ? `${bulanStr} — Week ${filterMinggu.value}` : bulanStr;
});

const exportPm = () => {
    const wb = XLSX.utils.book_new();

    const detailHeader = [
        'No', 'JIG', 'Tipe', 'Line', 'PIC',
        'Planned Week Start', 'Planned Week End', 'Actual Date',
        'Status', 'Kondisi',
        'NOK Closed By', 'NOK Closed At',
        'Catatan', 'Sparepart',
        'Foto Checksheet', 'Foto Sparepart',
    ];

    const detailRows = filteredReports.value.map((r, i) => [
        i + 1,
        r.pm_schedule?.jig?.name        ?? '-',
        r.pm_schedule?.jig?.type        ?? '-',
        r.pm_schedule?.jig?.line        ?? '-',
        r.pic?.name                     ?? '-',
        r.planned_week_start            ?? '-',
        r.planned_week_end              ?? '-',
        r.actual_date                   ?? '-',
        r.status === 'done' ? 'Selesai' : r.status === 'late' ? 'Terlambat' : 'Pending',
        r.condition ? r.condition.toUpperCase() : '-',
        r.nok_closed_by_user?.name      ?? '-',
        r.nok_closed_at                 ?? '-',
        r.notes                         ?? '-',
        r.spareparts?.length
            ? r.spareparts.map(s => `${s.sparepart?.name} (${s.qty} ${s.sparepart?.satuan})${s.notes ? ' — ' + s.notes : ''}`).join('; ')
            : '-',
        r.photo           ? `${window.location.origin}/storage/${r.photo}`           : '-',
        r.photo_sparepart ? `${window.location.origin}/storage/${r.photo_sparepart}` : '-',
    ]);

    const wsDetail = XLSX.utils.aoa_to_sheet([detailHeader, ...detailRows]);
    wsDetail['!cols'] = [
        { wch: 4  }, { wch: 26 }, { wch: 12 }, { wch: 12 }, { wch: 18 },
        { wch: 20 }, { wch: 20 }, { wch: 14 },
        { wch: 12 }, { wch: 10 },
        { wch: 18 }, { wch: 16 },
        { wch: 32 }, { wch: 48 },
        { wch: 50 }, { wch: 50 },
    ];
    XLSX.utils.book_append_sheet(wb, wsDetail, 'Detail');

    const summaryRows = [
        ['Laporan Preventive Maintenance'],
        ['Periode', periodeLabel.value],
        [],
        ['RINGKASAN'],
        ['Total', 'Selesai', 'Pending', 'Terlambat', 'Completion Rate'],
        [
            props.summary.total,
            props.summary.done,
            props.summary.pending,
            props.summary.late,
            props.summary.total > 0 ? `${Math.round((props.summary.done / props.summary.total) * 100)}%` : '0%',
        ],
    ];

    const ws = XLSX.utils.aoa_to_sheet(summaryRows);
    ws['!cols'] = [{ wch: 20 }, { wch: 14 }, { wch: 14 }, { wch: 14 }, { wch: 16 }];
    XLSX.utils.book_append_sheet(wb, ws, 'Summary');

    XLSX.writeFile(wb, `PM_Report_${periodeLabel.value.replace(/\s+/g, '_')}.xlsx`);
};

const showSubmitModal   = ref(false);
const showDetailModal   = ref(false);
const showCloseNokModal = ref(false);
const showImageModal    = ref(false);
const imageSrc          = ref('');
const selectedReport    = ref<PmReport | null>(null);

const isCompressingA = ref(false);
const isCompressingB = ref(false);
const previewPhoto   = ref<string | null>(null);
const previewSpPhoto = ref<string | null>(null);

const form = useForm({
    condition:       'ok' as 'ok' | 'nok',
    notes:           '',
    photo:           null as File | null,
    photo_sparepart: null as File | null,
    spareparts:      [] as { sparepart_id: number | null; qty: string; notes: string }[],
});

const nokForm = useForm({ nok_notes: '' });

const spSearch = ref<string[]>([]);
const spOpen   = ref<boolean[]>([]);

const filteredSp = (i: number) => {
    const q = (spSearch.value[i] ?? '').toLowerCase().trim();
    if (!q) return props.spareparts;
    return props.spareparts.filter(s => s.name.toLowerCase().includes(q));
};

const selectSp = (i: number, s: Sparepart) => {
    form.spareparts[i].sparepart_id = s.id;
    spSearch.value[i] = s.name;
    spOpen.value[i]   = false;
};

const openSpDropdown = (i: number) => {
    const existing = form.spareparts[i]?.sparepart_id;
    if (existing) {
        const sp = props.spareparts.find(s => s.id === existing);
        if (sp && !spSearch.value[i]) spSearch.value[i] = sp.name;
    }
    spOpen.value[i] = true;
};

const closeSpDropdown = (i: number) => {
    setTimeout(() => { spOpen.value[i] = false; }, 180);
};

const clearSpSelection = (i: number) => {
    form.spareparts[i].sparepart_id = null;
    spSearch.value[i] = '';
    spOpen.value[i]   = true;
};

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

const selectedSpItem = (id: number | null) => props.spareparts.find(s => s.id === id);

interface JigOption { name: string; type: string; line: string; }

const allJigs = computed<JigOption[]>(() => {
    const seen = new Set<string>();
    const result: JigOption[] = [];
    for (const r of props.reports) {
        const jig = r.pm_schedule?.jig;
        if (jig && !seen.has(jig.name)) {
            seen.add(jig.name);
            result.push(jig);
        }
    }
    return result.sort((a, b) => a.name.localeCompare(b.name));
});

const jigSearch   = ref('');
const jigOpen     = ref(false);
const selectedJig = ref<JigOption | null>(null);

const filteredJigs = computed(() => {
    const q = jigSearch.value.toLowerCase().trim();
    if (!q) return allJigs.value;
    return allJigs.value.filter(j =>
        j.name.toLowerCase().includes(q) ||
        j.type.toLowerCase().includes(q) ||
        j.line.toLowerCase().includes(q)
    );
});

const selectJig = (j: JigOption) => {
    selectedJig.value = j;
    jigSearch.value   = j.name;
    jigOpen.value     = false;
};

const clearJig = () => {
    selectedJig.value = null;
    jigSearch.value   = '';
    jigOpen.value     = true;
};

const closeJigDropdown = () => { setTimeout(() => { jigOpen.value = false; }, 180); };

const filteredReports = computed(() => {
    if (!selectedJig.value) return props.reports;
    return props.reports.filter(r => r.pm_schedule?.jig?.name === selectedJig.value!.name);
});

const openSubmit = (r: PmReport) => {
    selectedReport.value  = r;
    form.reset();
    form.spareparts       = [];
    spSearch.value        = [];
    spOpen.value          = [];
    previewPhoto.value    = null;
    previewSpPhoto.value  = null;
    showSubmitModal.value = true;
};
const closeSubmit = () => {
    showSubmitModal.value = false;
    selectedReport.value  = null;
    previewPhoto.value    = null;
    previewSpPhoto.value  = null;
    spSearch.value        = [];
    spOpen.value          = [];
    form.reset();
};
const openDetail   = (r: PmReport) => { selectedReport.value = r; showDetailModal.value = true; };
const openCloseNok = (r: PmReport) => { selectedReport.value = r; nokForm.reset(); showCloseNokModal.value = true; };
const openImage    = (src: string) => { imageSrc.value = `/storage/${src}`; showImageModal.value = true; };

const addSp = () => {
    form.spareparts.push({ sparepart_id: null, qty: '', notes: '' });
    spSearch.value.push('');
    spOpen.value.push(false);
};
const removeSp = (i: number) => {
    form.spareparts.splice(i, 1);
    spSearch.value.splice(i, 1);
    spOpen.value.splice(i, 1);
};

const compressImage = (file: File): Promise<File> =>
    new Promise(resolve => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let w = img.width, h = img.height; const max = 1920;
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

const handlePhoto = async (e: Event, field: 'photo' | 'photo_sparepart') => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    if (field === 'photo') {
        isCompressingA.value = true;
        form.photo = await compressImage(file);
        const r = new FileReader(); r.onload = ev => { previewPhoto.value = ev.target?.result as string; };
        r.readAsDataURL(form.photo);
        isCompressingA.value = false;
    } else {
        isCompressingB.value = true;
        form.photo_sparepart = await compressImage(file);
        const r = new FileReader(); r.onload = ev => { previewSpPhoto.value = ev.target?.result as string; };
        r.readAsDataURL(form.photo_sparepart);
        isCompressingB.value = false;
    }
};

const submit = () => {
    if (!selectedReport.value) return;
    form.post(`/jig/pm/report/${selectedReport.value.id}/submit`, { onSuccess: closeSubmit });
};

const submitCloseNok = () => {
    if (!selectedReport.value) return;
    nokForm.post(`/jig/pm/report/${selectedReport.value.id}/close-nok`, {
        onSuccess: () => { showCloseNokModal.value = false; selectedReport.value = null; }
    });
};

const formatDate = (d: string | null) => {
    if (!d) return '-';
    const dt = new Date(d);
    return isNaN(dt.getTime()) ? '-'
        : dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};
const formatWeek = (s: string, e: string) => {
    const sd = new Date(s), ed = new Date(e);
    if (isNaN(sd.getTime())) return '-';
    return `${sd.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })} – `
         + ed.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const BULAN_LIST = [
    { val: 'all', label: 'Semua Bulan' },
    ...['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']
        .map((l, i) => ({ val: i + 1, label: l })),
];

const statusCfg: Record<string, { label: string; badge: string; cardBorder: string; icon: any }> = {
    pending: { label: 'Pending',   badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',        cardBorder: 'border-l-amber-400',   icon: Clock },
    done:    { label: 'Selesai',   badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400', cardBorder: 'border-l-emerald-400', icon: CheckCircle2 },
    late:    { label: 'Terlambat', badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',                cardBorder: 'border-l-red-400',     icon: AlertTriangle },
};

const doneRate    = computed(() => props.summary.total > 0 ? (props.summary.done    / props.summary.total) * 100 : 0);
const lateRate    = computed(() => props.summary.total > 0 ? (props.summary.late    / props.summary.total) * 100 : 0);
const pendingRate = computed(() => props.summary.total > 0 ? (props.summary.pending / props.summary.total) * 100 : 0);

const activeFilterCount = computed(() => {
    let c = 0;
    if (filterStatus.value) c++;
    if (selectedJig.value)  c++;
    if (filterMinggu.value) c++;
    return c;
});
</script>
<template>
    <Head title="PM Report" />
    <AppLayout :breadcrumbs="[{title:'JIG',href:'/jig/dashboard'},{title:'PM Report',href:'/jig/pm/report'}]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-indigo-600 rounded-xl">
                            <ClipboardList class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
                        </span>
                        PM Report
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">Preventive Maintenance JIG</p>
                </div>
                <button @click="exportPm"
                    class="flex items-center gap-1.5 px-3 sm:px-4 py-2 sm:py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white rounded-xl font-semibold text-xs sm:text-sm transition-all shadow-sm flex-shrink-0">
                    <Download class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                    <span class="hidden sm:inline">Export Excel</span>
                    <span class="sm:hidden">Export</span>
                </button>
            </div>

            <div v-if="flash?.success"
                class="flex items-center gap-3 p-3 sm:p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm">
                <CheckCircle2 class="w-4 h-4 sm:w-5 sm:h-5 text-emerald-600 flex-shrink-0" />
                <p class="text-emerald-800 dark:text-emerald-200 font-medium text-xs sm:text-sm">{{ flash.success }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-3 sm:p-4 space-y-3">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
                    <div class="text-center p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                        <p class="text-xs text-gray-400 font-medium">Total</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-white">{{ summary.total }}</p>
                    </div>
                    <div class="text-center p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20">
                        <p class="text-xs text-emerald-600 font-semibold flex items-center justify-center gap-1"><CheckCircle2 class="w-3 h-3"/>Selesai</p>
                        <p class="text-2xl font-black text-emerald-600">{{ summary.done }}</p>
                    </div>
                    <div class="text-center p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20">
                        <p class="text-xs text-amber-600 font-semibold flex items-center justify-center gap-1"><Clock class="w-3 h-3"/>Pending</p>
                        <p class="text-2xl font-black text-amber-600">{{ summary.pending }}</p>
                    </div>
                    <div class="text-center p-3 rounded-xl bg-red-50 dark:bg-red-900/20">
                        <p class="text-xs text-red-600 font-semibold flex items-center justify-center gap-1"><AlertTriangle class="w-3 h-3"/>Terlambat</p>
                        <p class="text-2xl font-black text-red-600">{{ summary.late }}</p>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-xs text-gray-500 mb-1.5">
                        <span class="font-medium">Progress Completion</span>
                        <span class="font-bold text-indigo-600">{{ Math.round(doneRate) }}%</span>
                    </div>
                    <div class="w-full h-2.5 sm:h-3 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden flex">
                        <div class="h-full bg-emerald-500 transition-all duration-700" :style="{width: doneRate+'%'}"></div>
                        <div class="h-full bg-red-400 transition-all duration-700"     :style="{width: lateRate+'%'}"></div>
                        <div class="h-full bg-amber-400 transition-all duration-700"   :style="{width: pendingRate+'%'}"></div>
                    </div>
                    <div class="flex items-center gap-3 sm:gap-4 mt-2">
                        <div class="flex items-center gap-1 text-xs text-gray-500"><div class="w-2 h-2 bg-emerald-500 rounded-sm"></div>{{ Math.round(doneRate) }}%</div>
                        <div class="flex items-center gap-1 text-xs text-gray-500"><div class="w-2 h-2 bg-red-400 rounded-sm"></div>{{ Math.round(lateRate) }}%</div>
                        <div class="flex items-center gap-1 text-xs text-gray-500"><div class="w-2 h-2 bg-amber-400 rounded-sm"></div>{{ Math.round(pendingRate) }}%</div>
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <select v-model="filterBulan"
                        class="px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none w-full sm:w-auto">
                        <option v-for="b in BULAN_LIST" :key="b.val" :value="b.val">{{ b.label }}</option>
                    </select>
                    <input v-model="filterTahun" type="number" min="2020"
                        class="w-full sm:w-24 px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none" />

                    <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-700 rounded-xl p-1 w-full sm:w-auto">
                        <button @click="filterMinggu = ''"
                            :class="['px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all whitespace-nowrap',
                                filterMinggu === ''
                                    ? 'bg-white dark:bg-gray-600 text-indigo-600 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']">
                            Semua
                        </button>
                        <button v-for="w in MINGGU_LIST" :key="w.val"
                            @click="filterMinggu = w.val"
                            :class="['px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all whitespace-nowrap',
                                filterMinggu == w.val
                                    ? 'bg-white dark:bg-gray-600 text-indigo-600 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']">
                            {{ w.label }}
                        </button>
                    </div>

                    <button @click="showFilterPanel = !showFilterPanel"
                        :class="['relative flex items-center gap-1.5 px-3 py-2.5 border rounded-xl text-sm font-medium transition-colors whitespace-nowrap w-full sm:w-auto justify-center sm:justify-start',
                            showFilterPanel || activeFilterCount > 0
                                ? 'bg-indigo-600 border-indigo-600 text-white'
                                : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-indigo-400']">
                        <Filter class="w-4 h-4" />
                        <span>Filter</span>
                        <span v-if="activeFilterCount > 0"
                            class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold">
                            {{ activeFilterCount }}
                        </span>
                    </button>
                </div>

                <div v-if="showFilterPanel" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-3 space-y-3 shadow-sm">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Status</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="opt in [{v:'',l:'Semua'},{v:'pending',l:'Pending'},{v:'done',l:'Selesai'},{v:'late',l:'Terlambat'}]"
                                :key="opt.v"
                                @click="filterStatus = opt.v"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                                    filterStatus === opt.v
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
                                {{ opt.l }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Filter JIG</label>
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                            <input
                                v-model="jigSearch"
                                type="text"
                                placeholder="Cari nama JIG..."
                                autocomplete="off"
                                @focus="jigOpen = true"
                                @blur="closeJigDropdown"
                                :class="['w-full pl-8 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                    selectedJig
                                        ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold'
                                        : 'border-gray-200 dark:border-gray-600 focus:border-indigo-400']"
                            />
                            <button v-if="selectedJig" type="button" @click="clearJig"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                                <X class="w-4 h-4" />
                            </button>
                            <div v-if="jigOpen"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                <div v-if="filteredJigs.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">
                                    Tidak ada JIG "{{ jigSearch }}"
                                </div>
                                <button v-for="j in filteredJigs" :key="j.name" type="button"
                                    @mousedown.prevent="selectJig(j)"
                                    :class="['w-full text-left px-3 py-2.5 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors',
                                        selectedJig?.name === j.name ? 'bg-indigo-50 dark:bg-indigo-900/30' : '']">
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
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-gray-400">
                    <span>{{ filteredReports.length }} laporan ditemukan</span>
                    <button v-if="activeFilterCount > 0" @click="filterStatus = ''; filterMinggu = ''; clearJig()" class="text-indigo-600 font-semibold">Reset filter</button>
                </div>
            </div>

            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">JIG</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">PIC</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Planned Week</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Actual</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Kondisi</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="filteredReports.length === 0">
                                <td colspan="7" class="py-16 text-center text-gray-400 text-sm">
                                    <ClipboardList class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                    Tidak ada laporan PM
                                </td>
                            </tr>
                            <tr v-for="r in filteredReports" :key="r.id"
                                class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ r.pm_schedule?.jig?.name }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ r.pm_schedule?.jig?.type }} — {{ r.pm_schedule?.jig?.line }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ r.pic?.name }}</td>
                                <td class="px-4 py-3 text-xs font-semibold text-gray-700 dark:text-gray-300">{{ formatWeek(r.planned_week_start, r.planned_week_end) }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(r.actual_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold', statusCfg[r.status].badge]">
                                        <component :is="statusCfg[r.status].icon" class="w-3 h-3" />
                                        {{ statusCfg[r.status].label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <span v-if="r.condition"
                                            :class="['px-2.5 py-0.5 rounded-full text-xs font-bold',
                                                r.condition === 'ok' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400']">
                                            {{ r.condition.toUpperCase() }}
                                        </span>
                                        <span v-else class="text-gray-300">—</span>
                                        <span v-if="r.condition === 'nok' && r.nok_closed_at" class="text-xs text-gray-400 italic">closed</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                        <button v-if="r.status === 'pending'" @click="openSubmit(r)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition-colors">
                                            <Upload class="w-3 h-3" /> Submit
                                        </button>
                                        <button v-if="r.condition === 'nok' && !r.nok_closed_at" @click="openCloseNok(r)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-red-600 text-white rounded-lg text-xs font-semibold hover:bg-red-700 transition-colors">
                                            <ShieldCheck class="w-3 h-3" /> Close NOK
                                        </button>
                                        <button @click="openDetail(r)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-200 transition-colors">
                                            <FileText class="w-3 h-3" /> Detail
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="lg:hidden space-y-2.5">
                <div v-if="filteredReports.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <ClipboardList class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                    <p class="text-gray-400 text-sm">Tidak ada laporan PM</p>
                </div>
                <div v-for="r in filteredReports" :key="r.id"
                    :class="['bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden border-l-4', statusCfg[r.status].cardBorder]">
                    <div class="p-3.5">
                        <div class="flex items-start justify-between gap-2 mb-2.5">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-900 dark:text-white leading-tight">{{ r.pm_schedule?.jig?.name }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ r.pm_schedule?.jig?.type }} — {{ r.pm_schedule?.jig?.line }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0 ml-2">
                                <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[r.status].badge]">
                                    <component :is="statusCfg[r.status].icon" class="w-3 h-3" />
                                    {{ statusCfg[r.status].label }}
                                </span>
                                <span v-if="r.condition"
                                    :class="['px-2 py-0.5 rounded-full text-xs font-bold',
                                        r.condition === 'ok' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400']">
                                    {{ r.condition.toUpperCase() }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs mb-3">
                            <div>
                                <span class="text-gray-400">PIC</span>
                                <p class="font-semibold text-gray-700 dark:text-gray-300 truncate">{{ r.pic?.name }}</p>
                            </div>
                            <div>
                                <span class="text-gray-400">Actual</span>
                                <p class="font-semibold text-gray-700 dark:text-gray-300">{{ formatDate(r.actual_date) }}</p>
                            </div>
                            <div class="col-span-2">
                                <span class="text-gray-400">Planned Week</span>
                                <p class="font-semibold text-indigo-600 dark:text-indigo-400">{{ formatWeek(r.planned_week_start, r.planned_week_end) }}</p>
                            </div>
                            <div v-if="r.condition === 'nok' && r.nok_closed_at" class="col-span-2">
                                <span class="text-xs text-gray-400 italic">NOK sudah di-close {{ formatDate(r.nok_closed_at) }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-2.5 border-t border-gray-100 dark:border-gray-700">
                            <button v-if="r.status === 'pending'" @click="openSubmit(r)"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 active:scale-95 transition-all">
                                <Upload class="w-3.5 h-3.5" /> Submit
                            </button>
                            <button v-if="r.condition === 'nok' && !r.nok_closed_at" @click="openCloseNok(r)"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-red-600 text-white rounded-xl text-xs font-bold hover:bg-red-700 active:scale-95 transition-all">
                                <ShieldCheck class="w-3.5 h-3.5" /> Close NOK
                            </button>
                            <button @click="openDetail(r)"
                                :class="['flex items-center justify-center gap-1.5 py-2 px-4 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-bold hover:bg-gray-200 active:scale-95 transition-all',
                                    r.status !== 'pending' && !(r.condition === 'nok' && !r.nok_closed_at) ? 'flex-1' : '']">
                                <FileText class="w-3.5 h-3.5" /> Detail
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDetailModal && selectedReport"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-lg max-h-[92vh] overflow-y-auto shadow-2xl">
                <div class="sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Detail PM Report</h2>
                        <button @click="showDetailModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <div class="p-4 sm:p-5 space-y-4">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 space-y-2.5 text-xs">
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400 shrink-0">JIG</span>
                            <span class="font-bold text-gray-900 dark:text-white text-right">{{ selectedReport.pm_schedule?.jig?.name }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400 shrink-0">PIC</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ selectedReport.pic?.name }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400 shrink-0">Planned Week</span>
                            <span class="font-bold text-indigo-600">{{ formatWeek(selectedReport.planned_week_start, selectedReport.planned_week_end) }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400 shrink-0">Actual</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ formatDate(selectedReport.actual_date) }}</span>
                        </div>
                        <div class="flex justify-between items-center gap-2">
                            <span class="text-gray-400 shrink-0">Status</span>
                            <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-bold', statusCfg[selectedReport.status].badge]">
                                <component :is="statusCfg[selectedReport.status].icon" class="w-3 h-3" />
                                {{ statusCfg[selectedReport.status].label }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center gap-2">
                            <span class="text-gray-400 shrink-0">Kondisi</span>
                            <span v-if="selectedReport.condition"
                                :class="['px-2 py-0.5 rounded-full font-bold',
                                    selectedReport.condition === 'ok' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400']">
                                {{ selectedReport.condition.toUpperCase() }}
                            </span>
                            <span v-else class="text-gray-400">—</span>
                        </div>
                        <div v-if="selectedReport.nok_closed_at" class="flex justify-between gap-2">
                            <span class="text-gray-400 shrink-0">NOK Closed</span>
                            <span class="font-bold text-emerald-600">{{ formatDate(selectedReport.nok_closed_at) }}</span>
                        </div>
                    </div>

                    <div v-if="selectedReport.notes" class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3.5">
                        <p class="text-xs text-gray-400 font-semibold uppercase mb-1.5">Catatan</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ selectedReport.notes }}</p>
                    </div>

                    <div v-if="selectedReport.spareparts?.length">
                        <p class="text-xs font-semibold text-gray-400 uppercase mb-2 flex items-center gap-1.5">
                            <Package class="w-3.5 h-3.5"/>Sparepart Diganti
                        </p>
                        <div class="space-y-2">
                            <div v-for="sp in selectedReport.spareparts" :key="sp.sparepart?.id"
                                class="px-3 py-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                                <div class="flex justify-between text-xs">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ sp.sparepart?.name }}</span>
                                    <span class="text-gray-500 font-medium">{{ sp.qty }} {{ sp.sparepart?.satuan }}</span>
                                </div>
                                <p v-if="sp.notes" class="text-xs text-gray-400 mt-0.5">{{ sp.notes }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedReport.photo || selectedReport.photo_sparepart" class="grid grid-cols-2 gap-3">
                        <div v-if="selectedReport.photo">
                            <p class="text-xs font-semibold text-gray-400 uppercase mb-2">Foto Checksheet</p>
                            <img :src="`/storage/${selectedReport.photo}`"
                                @click="openImage(selectedReport.photo!)"
                                class="w-full rounded-xl cursor-pointer hover:opacity-90 active:opacity-80 h-32 sm:h-40 object-cover shadow-sm transition-opacity" />
                        </div>
                        <div v-if="selectedReport.photo_sparepart">
                            <p class="text-xs font-semibold text-gray-400 uppercase mb-2">Foto Sparepart</p>
                            <img :src="`/storage/${selectedReport.photo_sparepart}`"
                                @click="openImage(selectedReport.photo_sparepart!)"
                                class="w-full rounded-xl cursor-pointer hover:opacity-90 active:opacity-80 h-32 sm:h-40 object-cover shadow-sm transition-opacity" />
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

        <div v-if="showSubmitModal && selectedReport"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-lg max-h-[95vh] overflow-y-auto shadow-2xl">
                <div class="sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Submit Laporan PM</h2>
                        <button @click="closeSubmit" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <form @submit.prevent="submit" class="p-4 sm:p-5 space-y-4">
                    <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-xl p-3.5 border border-indigo-100 dark:border-indigo-800">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedReport.pm_schedule?.jig?.name }}</p>
                        <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-0.5">{{ formatWeek(selectedReport.planned_week_start, selectedReport.planned_week_end) }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Kondisi JIG</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" @click="form.condition = 'ok'"
                                :class="['py-3 rounded-xl font-bold text-sm transition-all active:scale-95',
                                    form.condition === 'ok' ? 'bg-emerald-500 text-white shadow-md shadow-emerald-200 dark:shadow-emerald-900/30' : 'border-2 border-gray-200 text-gray-600 dark:border-gray-600 dark:text-gray-300']">
                                ✓ OK
                            </button>
                            <button type="button" @click="form.condition = 'nok'"
                                :class="['py-3 rounded-xl font-bold text-sm transition-all active:scale-95',
                                    form.condition === 'nok' ? 'bg-red-500 text-white shadow-md shadow-red-200 dark:shadow-red-900/30' : 'border-2 border-gray-200 text-gray-600 dark:border-gray-600 dark:text-gray-300']">
                                ✗ NOK
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-2 text-gray-700 dark:text-gray-300">
                                Foto Checksheet <span class="text-red-500">*</span>
                            </label>
                            <label :class="['cursor-pointer flex flex-col items-center justify-center border-2 border-dashed rounded-xl transition-all min-h-[8rem]',
                                previewPhoto ? 'border-indigo-300 bg-indigo-50 p-0 overflow-hidden' : 'border-gray-200 hover:border-indigo-300 bg-gray-50 dark:bg-gray-700/30 p-3']">
                                <div v-if="!previewPhoto && !isCompressingA" class="text-center">
                                    <Upload class="w-7 h-7 text-gray-400 mx-auto mb-1.5" />
                                    <p class="text-xs text-gray-500 font-medium">Upload foto</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Tap untuk pilih</p>
                                </div>
                                <Loader2 v-else-if="isCompressingA" class="w-7 h-7 text-indigo-400 animate-spin" />
                                <img v-else-if="previewPhoto" :src="previewPhoto" class="w-full h-full object-cover" style="min-height:8rem" />
                                <input type="file" accept="image/*" capture="environment" @change="e => handlePhoto(e, 'photo')" class="hidden" />
                            </label>
                            <p v-if="form.errors.photo" class="mt-1 text-xs text-red-500">{{ form.errors.photo }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-2 text-gray-700 dark:text-gray-300">Foto Sparepart</label>
                            <label :class="['cursor-pointer flex flex-col items-center justify-center border-2 border-dashed rounded-xl transition-all min-h-[8rem]',
                                previewSpPhoto ? 'border-purple-300 bg-purple-50 p-0 overflow-hidden' : 'border-gray-200 hover:border-purple-300 bg-gray-50 dark:bg-gray-700/30 p-3']">
                                <div v-if="!previewSpPhoto && !isCompressingB" class="text-center">
                                    <Upload class="w-7 h-7 text-gray-400 mx-auto mb-1.5" />
                                    <p class="text-xs text-gray-500 font-medium">Jika ada</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Ganti sparepart</p>
                                </div>
                                <Loader2 v-else-if="isCompressingB" class="w-7 h-7 text-purple-400 animate-spin" />
                                <img v-else-if="previewSpPhoto" :src="previewSpPhoto" class="w-full h-full object-cover" style="min-height:8rem" />
                                <input type="file" accept="image/*" capture="environment" @change="e => handlePhoto(e, 'photo_sparepart')" class="hidden" />
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Catatan</label>
                        <textarea v-model="form.notes" rows="3" placeholder="Catatan hasil PM..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-indigo-500 focus:outline-none text-sm resize-none transition-colors"></textarea>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Sparepart Diganti</label>
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
                                        <div v-if="filteredSp(i).length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada hasil untuk "{{ spSearch[i] }}"</div>
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
                            <input v-model="sp.notes" type="text" placeholder="Keterangan penggantian (opsional)"
                                class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none transition-colors" />
                            <p v-if="sp.sparepart_id && selectedSpItem(sp.sparepart_id) && parseInt(sp.qty) > selectedSpItem(sp.sparepart_id)!.stok"
                                class="text-xs text-red-500 flex items-center gap-1">
                                <AlertTriangle class="w-3 h-3" />
                                Stok tidak cukup (tersedia: {{ Math.floor(selectedSpItem(sp.sparepart_id)!.stok) }})
                            </p>
                        </div>
                    </div>

                    <div class="sticky bottom-0 bg-white dark:bg-gray-800 pt-3 pb-safe border-t border-gray-100 dark:border-gray-700 -mx-4 sm:-mx-5 px-4 sm:px-5">
                        <div class="flex gap-3">
                            <button type="button" @click="closeSubmit"
                                class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 font-semibold text-sm active:scale-95 transition-all">
                                Batal
                            </button>
                            <button type="submit" :disabled="form.processing || isCompressingA || isCompressingB || !form.photo"
                                class="flex-1 py-3 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all font-bold text-sm active:scale-95">
                                {{ form.processing ? 'Menyimpan...' : 'Submit Laporan' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showCloseNokModal && selectedReport"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <ShieldCheck class="w-5 h-5 text-emerald-600" /> Close NOK
                    </h2>
                    <button @click="showCloseNokModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <form @submit.prevent="submitCloseNok" class="p-4 sm:p-5 space-y-4">
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3.5 border border-red-100 dark:border-red-800">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedReport.pm_schedule?.jig?.name }}</p>
                        <p class="text-xs text-red-600 dark:text-red-400 mt-0.5 font-semibold">Kondisi saat ini: NOK</p>
                    </div>
                    <div class="flex items-start gap-2 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-800">
                        <AlertTriangle class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" />
                        <p class="text-xs text-amber-700 dark:text-amber-300">Setelah di-close, kondisi JIG akan berubah menjadi <strong class="text-emerald-600">OK</strong>.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Catatan Penyelesaian</label>
                        <textarea v-model="nokForm.nok_notes" rows="4" placeholder="Tindakan yang dilakukan untuk menyelesaikan NOK..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-emerald-500 focus:outline-none resize-none transition-colors"></textarea>
                    </div>
                    <div class="flex gap-3 pb-safe">
                        <button type="button" @click="showCloseNokModal = false"
                            class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button type="submit" :disabled="nokForm.processing"
                            class="flex-1 py-3 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 disabled:opacity-50 font-bold text-sm active:scale-95 transition-all">
                            {{ nokForm.processing ? 'Menyimpan...' : 'Konfirmasi OK' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showImageModal" class="fixed inset-0 bg-black/90 flex items-center justify-center z-50 p-4"
            @click="showImageModal = false">
            <button class="absolute top-4 right-4 p-2 bg-white/10 rounded-xl text-white hover:bg-white/20 transition-colors">
                <X class="w-5 h-5" />
            </button>
            <img :src="imageSrc" class="max-w-full max-h-full rounded-xl object-contain" />
        </div>

    </AppLayout>
</template>
