<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import {
    ClipboardList, CheckCircle2, Clock, AlertTriangle, X,
    Plus, Trash2, Package, FileText, Upload, Loader2, ShieldCheck, Search
} from 'lucide-vue-next';

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
    filters:    { bulan?: any; tahun?: any; status?: string };
}

const props = defineProps<Props>();
const page  = usePage();
const flash = computed(() => (page.props as any).flash);

const filterBulan  = ref(props.filters.bulan  ?? new Date().getMonth() + 1);
const filterTahun  = ref(props.filters.tahun  ?? new Date().getFullYear());
const filterStatus = ref(props.filters.status ?? '');

watch([filterBulan, filterTahun, filterStatus], () => {
    router.get('/jig/pm/report', {
        bulan:  filterBulan.value,
        tahun:  filterTahun.value,
        status: filterStatus.value,
    }, { preserveState: true, preserveScroll: true });
});

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

// ── Searchable JIG filter (client-side) ───────────────────────────────────
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
// ─────────────────────────────────────────────────────────────────────────

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

const statusCfg: Record<string, { label: string; badge: string; row: string; icon: any }> = {
    pending: { label: 'Pending',   badge: 'bg-yellow-100 text-yellow-700', row: '',               icon: Clock },
    done:    { label: 'Selesai',   badge: 'bg-green-100 text-green-700',   row: 'bg-green-50/30', icon: CheckCircle2 },
    late:    { label: 'Terlambat', badge: 'bg-red-100 text-red-700',       row: 'bg-red-50/30',   icon: AlertTriangle },
};

const doneRate    = computed(() => props.summary.total > 0 ? (props.summary.done    / props.summary.total) * 100 : 0);
const lateRate    = computed(() => props.summary.total > 0 ? (props.summary.late    / props.summary.total) * 100 : 0);
const pendingRate = computed(() => props.summary.total > 0 ? (props.summary.pending / props.summary.total) * 100 : 0);
</script>

<template>
    <Head title="PM Report" />
    <AppLayout :breadcrumbs="[{title:'JIG',href:'/jig/dashboard'},{title:'PM Report',href:'/jig/pm/report'}]">
        <div class="p-4 sm:p-6 space-y-5">

            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <ClipboardList class="w-6 h-6 text-indigo-600" /> PM Report
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">Laporan preventive maintenance JIG</p>
            </div>

            <div v-if="flash?.success"
                class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-sm">
                <CheckCircle2 class="w-5 h-5 text-green-600 flex-shrink-0" />
                <p class="text-green-800 dark:text-green-200 font-medium">{{ flash.success }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4 space-y-3">
                <div class="grid grid-cols-4 gap-3">
                    <div class="text-center p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                        <p class="text-xs text-gray-400 font-medium">Total</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-white">{{ summary.total }}</p>
                    </div>
                    <div class="text-center p-3 rounded-xl bg-green-50 dark:bg-green-900/20">
                        <p class="text-xs text-green-600 font-semibold flex items-center justify-center gap-1"><CheckCircle2 class="w-3 h-3"/>Selesai</p>
                        <p class="text-2xl font-black text-green-600">{{ summary.done }}</p>
                    </div>
                    <div class="text-center p-3 rounded-xl bg-yellow-50 dark:bg-yellow-900/20">
                        <p class="text-xs text-yellow-600 font-semibold flex items-center justify-center gap-1"><Clock class="w-3 h-3"/>Pending</p>
                        <p class="text-2xl font-black text-yellow-600">{{ summary.pending }}</p>
                    </div>
                    <div class="text-center p-3 rounded-xl bg-red-50 dark:bg-red-900/20">
                        <p class="text-xs text-red-600 font-semibold flex items-center justify-center gap-1"><AlertTriangle class="w-3 h-3"/>Terlambat</p>
                        <p class="text-2xl font-black text-red-600">{{ summary.late }}</p>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>Progress Completion</span>
                        <span class="font-bold text-indigo-600">{{ Math.round(doneRate) }}%</span>
                    </div>
                    <div class="w-full h-3 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden flex">
                        <div class="h-3 bg-green-500 transition-all duration-700"  :style="{width: doneRate+'%'}"></div>
                        <div class="h-3 bg-red-400 transition-all duration-700"    :style="{width: lateRate+'%'}"></div>
                        <div class="h-3 bg-yellow-400 transition-all duration-700" :style="{width: pendingRate+'%'}"></div>
                    </div>
                    <div class="flex items-center gap-4 mt-1.5">
                        <div class="flex items-center gap-1 text-xs text-gray-500"><div class="w-2 h-2 bg-green-500 rounded-sm"></div>Selesai {{ Math.round(doneRate) }}%</div>
                        <div class="flex items-center gap-1 text-xs text-gray-500"><div class="w-2 h-2 bg-red-400 rounded-sm"></div>Terlambat {{ Math.round(lateRate) }}%</div>
                        <div class="flex items-center gap-1 text-xs text-gray-500"><div class="w-2 h-2 bg-yellow-400 rounded-sm"></div>Pending {{ Math.round(pendingRate) }}%</div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <select v-model="filterBulan" class="px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none">
                    <option v-for="b in BULAN_LIST" :key="b.val" :value="b.val">{{ b.label }}</option>
                </select>
                <input v-model="filterTahun" type="number" min="2020" class="w-24 px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none" />
                <select v-model="filterStatus" class="px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="">Semua Status</option>
                    <option value="pending">Pending</option>
                    <option value="done">Selesai</option>
                    <option value="late">Terlambat</option>
                </select>

                <div class="relative">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                        <input
                            v-model="jigSearch"
                            type="text"
                            placeholder="Filter JIG..."
                            autocomplete="off"
                            @focus="jigOpen = true"
                            @blur="closeJigDropdown"
                            :class="['pl-7 pr-7 py-2 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-800 w-44',
                                selectedJig
                                    ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold'
                                    : 'border-gray-200 dark:border-gray-700 focus:border-indigo-400']"
                        />
                        <button v-if="selectedJig" type="button" @click="clearJig"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                            <X class="w-3.5 h-3.5" />
                        </button>
                    </div>
                    <div v-if="jigOpen"
                        class="absolute z-50 mt-1 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                        <div v-if="filteredJigs.length === 0"
                            class="px-3 py-3 text-xs text-gray-400 text-center">
                            Tidak ada JIG "{{ jigSearch }}"
                        </div>
                        <button
                            v-for="j in filteredJigs" :key="j.name"
                            type="button"
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

                <span class="text-xs text-gray-400">{{ filteredReports.length }} laporan</span>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">JIG</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">PIC</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Planned Week</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Actual</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Kondisi</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="filteredReports.length === 0">
                                <td colspan="7" class="py-14 text-center text-gray-400 text-sm">Tidak ada laporan PM</td>
                            </tr>
                            <tr v-for="r in filteredReports" :key="r.id"
                                :class="['hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors', statusCfg[r.status].row]">
                                <td class="px-4 py-3">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ r.pm_schedule?.jig?.name }}</p>
                                    <p class="text-xs text-gray-400">{{ r.pm_schedule?.jig?.type }} — {{ r.pm_schedule?.jig?.line }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ r.pic?.name }}</td>
                                <td class="px-4 py-3 text-xs font-semibold text-gray-700 dark:text-gray-300">{{ formatWeek(r.planned_week_start, r.planned_week_end) }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(r.actual_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[r.status].badge]">
                                        <component :is="statusCfg[r.status].icon" class="w-3 h-3" />
                                        {{ statusCfg[r.status].label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <span v-if="r.condition"
                                            :class="['px-2 py-0.5 rounded-full text-xs font-bold',
                                                r.condition === 'ok' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                                            {{ r.condition.toUpperCase() }}
                                        </span>
                                        <span v-else class="text-xs text-gray-300">—</span>
                                        <span v-if="r.condition === 'nok' && r.nok_closed_at"
                                            class="text-xs text-gray-400 italic">sudah di-close</span>
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
        </div>

        <div v-if="showDetailModal && selectedReport"
            class="fixed inset-0 backdrop-blur-sm bg-black/30 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Detail PM Report</h2>
                    <button @click="showDetailModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                </div>
                <div class="p-5 space-y-4">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 space-y-2 text-xs">
                        <div class="flex justify-between"><span class="text-gray-500">JIG</span><span class="font-bold text-gray-900 dark:text-white max-w-[55%] text-right">{{ selectedReport.pm_schedule?.jig?.name }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">PIC</span><span class="font-bold text-gray-900 dark:text-white">{{ selectedReport.pic?.name }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Planned Week</span><span class="font-bold text-indigo-600">{{ formatWeek(selectedReport.planned_week_start, selectedReport.planned_week_end) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Actual</span><span class="font-bold text-gray-900 dark:text-white">{{ formatDate(selectedReport.actual_date) }}</span></div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Status</span>
                            <span :class="['px-2 py-0.5 rounded-full font-bold', statusCfg[selectedReport.status].badge]">{{ statusCfg[selectedReport.status].label }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Kondisi</span>
                            <span v-if="selectedReport.condition"
                                :class="['px-2 py-0.5 rounded-full font-bold',
                                    selectedReport.condition === 'ok' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                                {{ selectedReport.condition.toUpperCase() }}
                            </span>
                            <span v-else class="text-gray-400">—</span>
                        </div>
                        <div v-if="selectedReport.nok_closed_at" class="flex justify-between">
                            <span class="text-gray-500">NOK Closed</span>
                            <span class="font-bold text-green-600">{{ formatDate(selectedReport.nok_closed_at) }}</span>
                        </div>
                    </div>

                    <div v-if="selectedReport.notes" class="text-xs bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3">
                        <p class="text-gray-500 font-semibold mb-1">Catatan</p>
                        <p class="text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ selectedReport.notes }}</p>
                    </div>

                    <div v-if="selectedReport.spareparts?.length">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2 flex items-center gap-1"><Package class="w-3.5 h-3.5"/>Sparepart</p>
                        <div class="space-y-1.5">
                            <div v-for="sp in selectedReport.spareparts" :key="sp.sparepart?.id"
                                class="px-3 py-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                <div class="flex justify-between text-xs">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ sp.sparepart?.name }}</span>
                                    <span class="text-gray-500">{{ sp.qty }} {{ sp.sparepart?.satuan }}</span>
                                </div>
                                <p v-if="sp.notes" class="text-xs text-gray-400 mt-0.5">{{ sp.notes }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedReport.photo || selectedReport.photo_sparepart" class="grid grid-cols-2 gap-3">
                        <div v-if="selectedReport.photo">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1.5">Foto Checksheet</p>
                            <img :src="`/storage/${selectedReport.photo}`"
                                @click="openImage(selectedReport.photo!)"
                                class="w-full rounded-xl cursor-pointer hover:opacity-90 max-h-40 object-cover shadow-sm" />
                        </div>
                        <div v-if="selectedReport.photo_sparepart">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1.5">Foto Sparepart</p>
                            <img :src="`/storage/${selectedReport.photo_sparepart}`"
                                @click="openImage(selectedReport.photo_sparepart!)"
                                class="w-full rounded-xl cursor-pointer hover:opacity-90 max-h-40 object-cover shadow-sm" />
                        </div>
                    </div>
                </div>
                <div class="p-5 border-t border-gray-100 dark:border-gray-700">
                    <button @click="showDetailModal = false" class="w-full py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-200">Tutup</button>
                </div>
            </div>
        </div>

        <div v-if="showSubmitModal && selectedReport"
            class="fixed inset-0 backdrop-blur-sm bg-black/30 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Submit Laporan PM</h2>
                    <button @click="closeSubmit" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                </div>
                <form @submit.prevent="submit" class="p-5 space-y-4">
                    <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-xl p-3 border border-indigo-100">
                        <p class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedReport.pm_schedule?.jig?.name }}</p>
                        <p class="text-xs text-indigo-600 mt-0.5">{{ formatWeek(selectedReport.planned_week_start, selectedReport.planned_week_end) }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Kondisi JIG</label>
                        <div class="flex gap-3">
                            <button type="button" @click="form.condition = 'ok'"
                                :class="['flex-1 py-2.5 rounded-xl font-bold text-sm transition-all',
                                    form.condition === 'ok' ? 'bg-green-500 text-white shadow-md' : 'border-2 border-gray-200 text-gray-600 dark:border-gray-600 dark:text-gray-300']">
                                ✓ OK
                            </button>
                            <button type="button" @click="form.condition = 'nok'"
                                :class="['flex-1 py-2.5 rounded-xl font-bold text-sm transition-all',
                                    form.condition === 'nok' ? 'bg-red-500 text-white shadow-md' : 'border-2 border-gray-200 text-gray-600 dark:border-gray-600 dark:text-gray-300']">
                                ✗ NOK
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Foto Checksheet <span class="text-red-500">*</span></label>
                            <label :class="['cursor-pointer flex flex-col items-center justify-center border-2 border-dashed rounded-xl p-4 transition-all',
                                previewPhoto ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300 bg-gray-50 dark:bg-gray-700/30']">
                                <div v-if="!previewPhoto && !isCompressingA" class="text-center">
                                    <Upload class="w-6 h-6 text-gray-400 mx-auto mb-1" />
                                    <p class="text-xs text-gray-500">Upload foto</p>
                                </div>
                                <Loader2 v-else-if="isCompressingA" class="w-6 h-6 text-indigo-400 animate-spin" />
                                <img v-else-if="previewPhoto" :src="previewPhoto" class="w-full max-h-28 object-cover rounded-lg" />
                                <input type="file" accept="image/*" @change="e => handlePhoto(e, 'photo')" class="hidden" />
                            </label>
                            <p v-if="form.errors.photo" class="mt-1 text-xs text-red-500">{{ form.errors.photo }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Foto Sparepart</label>
                            <label :class="['cursor-pointer flex flex-col items-center justify-center border-2 border-dashed rounded-xl p-4 transition-all',
                                previewSpPhoto ? 'border-purple-300 bg-purple-50' : 'border-gray-200 hover:border-purple-300 bg-gray-50 dark:bg-gray-700/30']">
                                <div v-if="!previewSpPhoto && !isCompressingB" class="text-center">
                                    <Upload class="w-6 h-6 text-gray-400 mx-auto mb-1" />
                                    <p class="text-xs text-gray-500">Jika ada ganti SP</p>
                                </div>
                                <Loader2 v-else-if="isCompressingB" class="w-6 h-6 text-purple-400 animate-spin" />
                                <img v-else-if="previewSpPhoto" :src="previewSpPhoto" class="w-full max-h-28 object-cover rounded-lg" />
                                <input type="file" accept="image/*" @change="e => handlePhoto(e, 'photo_sparepart')" class="hidden" />
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Catatan</label>
                        <textarea v-model="form.notes" rows="2" placeholder="Catatan hasil PM..."
                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-indigo-500 focus:outline-none text-sm resize-none"></textarea>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Sparepart Diganti</label>
                            <button type="button" @click="addSp"
                                class="flex items-center gap-1 px-2.5 py-1 text-xs bg-indigo-100 text-indigo-700 rounded-lg font-semibold hover:bg-indigo-200">
                                <Plus class="w-3 h-3" /> Tambah
                            </button>
                        </div>

                        <div v-for="(sp, i) in form.spareparts" :key="i" class="mb-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl space-y-2">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <div class="relative">
                                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                                        <input
                                            v-model="spSearch[i]"
                                            type="text"
                                            placeholder="Cari sparepart..."
                                            autocomplete="off"
                                            @focus="openSpDropdown(i)"
                                            @blur="closeSpDropdown(i)"
                                            :class="['w-full pl-7 pr-7 py-2 border rounded-xl text-xs focus:outline-none transition-colors dark:bg-gray-700',
                                                sp.sparepart_id
                                                    ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold'
                                                    : 'border-gray-200 dark:border-gray-600 focus:border-indigo-400']"
                                        />
                                        <button v-if="sp.sparepart_id" type="button"
                                            @click="clearSpSelection(i)"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                                            <X class="w-3 h-3" />
                                        </button>
                                    </div>
                                    <div v-if="spOpen[i]"
                                        class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                        <div v-if="filteredSp(i).length === 0"
                                            class="px-3 py-3 text-xs text-gray-400 text-center">
                                            Tidak ada hasil untuk "{{ spSearch[i] }}"
                                        </div>
                                        <button
                                            v-for="s in filteredSp(i)" :key="s.id"
                                            type="button"
                                            @mousedown.prevent="selectSp(i, s)"
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
                                <input v-model="sp.qty" type="number" step="1" min="1" placeholder="Qty"
                                    class="w-20 px-2 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none" />
                                <button type="button" @click="removeSp(i)" class="p-2 text-red-500 hover:bg-red-50 rounded-lg"><Trash2 class="w-3.5 h-3.5" /></button>
                            </div>

                            <input v-model="sp.notes" type="text" placeholder="Keterangan penggantian (opsional)"
                                class="w-full px-3 py-1.5 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none" />

                            <p v-if="sp.sparepart_id && selectedSpItem(sp.sparepart_id) && parseInt(sp.qty) > selectedSpItem(sp.sparepart_id)!.stok"
                                class="text-xs text-red-500">⚠ Stok tidak cukup (tersedia: {{ Math.floor(selectedSpItem(sp.sparepart_id)!.stok) }})</p>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <button type="submit" :disabled="form.processing || isCompressingA || isCompressingB || !form.photo"
                            class="flex-1 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-all font-semibold text-sm">
                            {{ form.processing ? 'Menyimpan...' : 'Submit Laporan' }}
                        </button>
                        <button type="button" @click="closeSubmit"
                            class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 font-medium text-sm">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showCloseNokModal && selectedReport"
            class="fixed inset-0 backdrop-blur-sm bg-black/30 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full shadow-2xl">
                <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <ShieldCheck class="w-5 h-5 text-green-600" /> Close NOK
                    </h2>
                    <button @click="showCloseNokModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                </div>
                <form @submit.prevent="submitCloseNok" class="p-5 space-y-4">
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3 border border-red-100 text-xs">
                        <p class="font-bold text-gray-900 dark:text-white">{{ selectedReport.pm_schedule?.jig?.name }}</p>
                        <p class="text-red-600 mt-0.5 font-semibold">Kondisi saat ini: NOK</p>
                    </div>
                    <p class="text-xs text-gray-500">Setelah di-close, kondisi JIG akan berubah menjadi <strong class="text-green-600">OK</strong>.</p>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Catatan Penyelesaian</label>
                        <textarea v-model="nokForm.nok_notes" rows="3" placeholder="Tindakan yang dilakukan untuk menyelesaikan NOK..."
                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-green-500 focus:outline-none resize-none"></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" :disabled="nokForm.processing"
                            class="flex-1 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 disabled:opacity-50 font-semibold text-sm">
                            {{ nokForm.processing ? 'Menyimpan...' : 'Konfirmasi OK' }}
                        </button>
                        <button type="button" @click="showCloseNokModal = false"
                            class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 font-medium text-sm">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showImageModal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4"
            @click="showImageModal = false">
            <img :src="imageSrc" class="max-w-full max-h-full rounded-2xl object-contain" />
        </div>

    </AppLayout>
</template>
