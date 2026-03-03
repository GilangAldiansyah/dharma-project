<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import {
    ClipboardList, CheckCircle2, Clock, AlertTriangle, X,
    Plus, Trash2, Package, FileText, Upload, Loader2
} from 'lucide-vue-next';

interface Sparepart { id: number; name: string; satuan: string; stok: number; }
interface ReportSp  { sparepart: Sparepart; qty: number; }
interface PmReport  {
    id: number;
    planned_week_start: string;
    planned_week_end:   string;
    actual_date:        string | null;
    status:             'pending' | 'done' | 'late';
    condition:          'ok' | 'nok' | null;
    notes:              string | null;
    photo:              string | null;
    pm_schedule:        { jig: { name: string; type: string; line: string } };
    pic:                { name: string };
    spareparts:         ReportSp[];
}

interface Props {
    reports:    PmReport[];
    spareparts: Sparepart[];
    summary:    { total: number; done: number; late: number; pending: number };
    filters:    { bulan?: any; tahun?: any; status?: string };
}

const props = defineProps<Props>();
const page  = usePage();
const flash = computed(() => (page.props as any).flash);

// ── Filters ───────────────────────────────────────────────────────────────────
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

// ── Modals ────────────────────────────────────────────────────────────────────
const showSubmitModal = ref(false);
const showDetailModal = ref(false);
const showImageModal  = ref(false);
const selectedReport  = ref<PmReport | null>(null);
const isCompressing   = ref(false);
const previewImage    = ref<string | null>(null);

// ── Submit form ───────────────────────────────────────────────────────────────
const form = useForm({
    condition:  'ok' as 'ok' | 'nok',
    notes:      '',
    photo:      null as File | null,
    spareparts: [] as { sparepart_id: number | null; qty: string }[],
});

const openSubmit = (r: PmReport) => {
    selectedReport.value  = r;
    form.reset();
    previewImage.value    = null;
    showSubmitModal.value = true;
};
const closeSubmit = () => {
    showSubmitModal.value = false;
    selectedReport.value  = null;
    previewImage.value    = null;
    form.reset();
};
const openDetail = (r: PmReport) => {
    selectedReport.value  = r;
    showDetailModal.value = true;
};

const handlePhoto = async (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    isCompressing.value = true;
    form.photo = await compressImage(file);
    const reader = new FileReader();
    reader.onload = ev => { previewImage.value = ev.target?.result as string; };
    reader.readAsDataURL(form.photo);
    isCompressing.value = false;
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

const addSp    = () => form.spareparts.push({ sparepart_id: null, qty: '' });
const removeSp = (i: number) => form.spareparts.splice(i, 1);
const submit   = () => {
    if (!selectedReport.value) return;
    form.post(`/jig/pm/report/${selectedReport.value.id}/submit`, { onSuccess: closeSubmit });
};

// ── Helpers ───────────────────────────────────────────────────────────────────
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

const selectedSp = (id: number | null) => props.spareparts.find(s => s.id === id);
</script>

<template>
    <Head title="PM Report" />
    <AppLayout :breadcrumbs="[{title:'JIG',href:'/jig/dashboard'},{title:'PM Report',href:'/jig/pm/report'}]">
        <div class="p-4 sm:p-6 space-y-5">

            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <ClipboardList class="w-6 h-6 text-indigo-600" /> PM Report
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">Laporan preventive maintenance JIG</p>
            </div>

            <!-- Flash -->
            <div v-if="flash?.success"
                class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-sm">
                <CheckCircle2 class="w-5 h-5 text-green-600 flex-shrink-0" />
                <p class="text-green-800 dark:text-green-200 font-medium">{{ flash.success }}</p>
            </div>

            <!-- Summary + Progress -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4 space-y-3">
                <div class="grid grid-cols-4 gap-3">
                    <div class="text-center p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                        <p class="text-xs text-gray-400 font-medium">Total</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-white">{{ summary.total }}</p>
                    </div>
                    <div class="text-center p-3 rounded-xl bg-green-50 dark:bg-green-900/20">
                        <p class="text-xs text-green-600 font-semibold flex items-center justify-center gap-1">
                            <CheckCircle2 class="w-3 h-3" /> Selesai
                        </p>
                        <p class="text-2xl font-black text-green-600">{{ summary.done }}</p>
                    </div>
                    <div class="text-center p-3 rounded-xl bg-yellow-50 dark:bg-yellow-900/20">
                        <p class="text-xs text-yellow-600 font-semibold flex items-center justify-center gap-1">
                            <Clock class="w-3 h-3" /> Pending
                        </p>
                        <p class="text-2xl font-black text-yellow-600">{{ summary.pending }}</p>
                    </div>
                    <div class="text-center p-3 rounded-xl bg-red-50 dark:bg-red-900/20">
                        <p class="text-xs text-red-600 font-semibold flex items-center justify-center gap-1">
                            <AlertTriangle class="w-3 h-3" /> Terlambat
                        </p>
                        <p class="text-2xl font-black text-red-600">{{ summary.late }}</p>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>Progress Completion</span>
                        <span class="font-bold text-indigo-600">{{ Math.round(doneRate) }}%</span>
                    </div>
                    <div class="w-full h-3 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden flex">
                        <div class="h-3 bg-green-500 transition-all duration-700"  :style="{width: doneRate + '%'}"></div>
                        <div class="h-3 bg-red-400 transition-all duration-700"    :style="{width: lateRate + '%'}"></div>
                        <div class="h-3 bg-yellow-400 transition-all duration-700" :style="{width: pendingRate + '%'}"></div>
                    </div>
                    <div class="flex items-center gap-4 mt-1.5">
                        <div class="flex items-center gap-1 text-xs text-gray-500"><div class="w-2 h-2 bg-green-500 rounded-sm"></div>Selesai {{ Math.round(doneRate) }}%</div>
                        <div class="flex items-center gap-1 text-xs text-gray-500"><div class="w-2 h-2 bg-red-400 rounded-sm"></div>Terlambat {{ Math.round(lateRate) }}%</div>
                        <div class="flex items-center gap-1 text-xs text-gray-500"><div class="w-2 h-2 bg-yellow-400 rounded-sm"></div>Pending {{ Math.round(pendingRate) }}%</div>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="flex flex-wrap items-center gap-2">
                <select v-model="filterBulan"
                    class="px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none">
                    <option v-for="b in BULAN_LIST" :key="b.val" :value="b.val">{{ b.label }}</option>
                </select>
                <input v-model="filterTahun" type="number" min="2020"
                    class="w-24 px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none" />
                <select v-model="filterStatus"
                    class="px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="">Semua Status</option>
                    <option value="pending">Pending</option>
                    <option value="done">Selesai</option>
                    <option value="late">Terlambat</option>
                </select>
                <span class="text-xs text-gray-400">{{ reports.length }} laporan</span>
            </div>

            <!-- Table -->
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
                            <tr v-if="reports.length === 0">
                                <td colspan="7" class="py-14 text-center text-gray-400 text-sm">
                                    Tidak ada laporan PM
                                </td>
                            </tr>
                            <tr v-for="r in reports" :key="r.id"
                                :class="['hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors', statusCfg[r.status].row]">
                                <td class="px-4 py-3">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ r.pm_schedule?.jig?.name }}</p>
                                    <p class="text-xs text-gray-400">{{ r.pm_schedule?.jig?.type }} — {{ r.pm_schedule?.jig?.line }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ r.pic?.name }}</td>
                                <td class="px-4 py-3 text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    {{ formatWeek(r.planned_week_start, r.planned_week_end) }}
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(r.actual_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold', statusCfg[r.status].badge]">
                                        <component :is="statusCfg[r.status].icon" class="w-3 h-3" />
                                        {{ statusCfg[r.status].label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="r.condition"
                                        :class="['px-2 py-0.5 rounded-full text-xs font-bold',
                                            r.condition === 'ok' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                                        {{ r.condition.toUpperCase() }}
                                    </span>
                                    <span v-else class="text-xs text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button v-if="r.status === 'pending'" @click="openSubmit(r)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition-colors">
                                            <Upload class="w-3 h-3" /> Submit
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

        <!-- ══════ DETAIL MODAL ══════ -->
        <div v-if="showDetailModal && selectedReport"
            class="fixed inset-0 backdrop-blur-sm bg-black/30 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Detail PM Report</h2>
                    <button @click="showDetailModal = false"
                        class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-gray-500">JIG</span>
                            <span class="font-bold text-gray-900 dark:text-white max-w-[55%] text-right">{{ selectedReport.pm_schedule?.jig?.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">PIC</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ selectedReport.pic?.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Planned Week</span>
                            <span class="font-bold text-indigo-600">{{ formatWeek(selectedReport.planned_week_start, selectedReport.planned_week_end) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Actual</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ formatDate(selectedReport.actual_date) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Status</span>
                            <span :class="['px-2 py-0.5 rounded-full font-bold', statusCfg[selectedReport.status].badge]">
                                {{ statusCfg[selectedReport.status].label }}
                            </span>
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
                    </div>
                    <div v-if="selectedReport.notes" class="text-xs bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3">
                        <p class="text-gray-500 font-semibold mb-1">Catatan</p>
                        <p class="text-gray-700 dark:text-gray-300">{{ selectedReport.notes }}</p>
                    </div>
                    <div v-if="selectedReport.spareparts?.length">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2 flex items-center gap-1">
                            <Package class="w-3.5 h-3.5" /> Sparepart
                        </p>
                        <div class="space-y-1">
                            <div v-for="sp in selectedReport.spareparts" :key="sp.sparepart?.id"
                                class="flex justify-between text-xs px-3 py-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                <span class="font-semibold text-gray-900 dark:text-white">{{ sp.sparepart?.name }}</span>
                                <span class="text-gray-500">{{ sp.qty }} {{ sp.sparepart?.satuan }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="selectedReport.photo">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Foto</p>
                        <img :src="`/storage/${selectedReport.photo}`"
                            @click="showImageModal = true"
                            class="w-full rounded-xl cursor-pointer hover:opacity-90 max-h-56 object-cover shadow-md" />
                        <p class="text-xs text-center text-gray-400 mt-1">Klik untuk perbesar</p>
                    </div>
                </div>
                <div class="p-5 border-t border-gray-100 dark:border-gray-700">
                    <button @click="showDetailModal = false"
                        class="w-full py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-200">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ SUBMIT MODAL ══════ -->
        <div v-if="showSubmitModal && selectedReport"
            class="fixed inset-0 backdrop-blur-sm bg-black/30 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Submit Laporan PM</h2>
                    <button @click="closeSubmit"
                        class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <form @submit.prevent="submit" class="p-5 space-y-4">
                    <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-xl p-3 border border-indigo-100">
                        <p class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedReport.pm_schedule?.jig?.name }}</p>
                        <p class="text-xs text-indigo-600 mt-0.5">{{ formatWeek(selectedReport.planned_week_start, selectedReport.planned_week_end) }}</p>
                    </div>

                    <!-- Kondisi -->
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

                    <!-- Foto -->
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Foto Checksheet <span class="text-red-500">*</span>
                        </label>
                        <label :class="['cursor-pointer flex flex-col items-center justify-center border-2 border-dashed rounded-2xl p-5 transition-all',
                            previewImage ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300 bg-gray-50']">
                            <div v-if="!previewImage && !isCompressing" class="text-center">
                                <Upload class="w-7 h-7 text-gray-400 mx-auto mb-1.5" />
                                <p class="text-xs font-semibold text-gray-600">Klik untuk upload foto</p>
                                <p class="text-xs text-gray-400 mt-0.5">JPG, PNG — maks 5MB</p>
                            </div>
                            <Loader2 v-else-if="isCompressing" class="w-7 h-7 text-indigo-400 animate-spin" />
                            <img v-else-if="previewImage" :src="previewImage" class="w-full max-h-40 object-cover rounded-xl" />
                            <input type="file" accept="image/*" @change="handlePhoto" class="hidden" :disabled="isCompressing" />
                        </label>
                        <p v-if="form.errors.photo" class="mt-1 text-xs text-red-600">{{ form.errors.photo }}</p>
                    </div>

                    <!-- Catatan -->
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Catatan</label>
                        <textarea v-model="form.notes" rows="2" placeholder="Catatan hasil PM..."
                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-indigo-500 focus:outline-none text-sm resize-none"></textarea>
                    </div>

                    <!-- Sparepart -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Sparepart Diganti</label>
                            <button type="button" @click="addSp"
                                class="flex items-center gap-1 px-2.5 py-1 text-xs bg-indigo-100 text-indigo-700 rounded-lg font-semibold hover:bg-indigo-200">
                                <Plus class="w-3 h-3" /> Tambah
                            </button>
                        </div>
                        <div v-for="(sp, i) in form.spareparts" :key="i" class="flex gap-2 mb-2">
                            <select v-model="sp.sparepart_id"
                                class="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none">
                                <option :value="null" disabled>Pilih Sparepart</option>
                                <option v-for="s in spareparts" :key="s.id" :value="s.id">
                                    {{ s.name }} (stok: {{ s.stok }} {{ s.satuan }})
                                </option>
                            </select>
                            <input v-model="sp.qty" type="number" step="0.01" min="0.01" placeholder="Qty"
                                class="w-20 px-2 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-xs focus:border-indigo-500 focus:outline-none" />
                            <button type="button" @click="removeSp(i)"
                                class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                        <div v-for="(sp, i) in form.spareparts" :key="'w' + i">
                            <p v-if="sp.sparepart_id && selectedSp(sp.sparepart_id) && parseFloat(sp.qty) > selectedSp(sp.sparepart_id)!.stok"
                                class="text-xs text-red-500 mb-1">
                                ⚠ Stok tidak cukup (tersedia: {{ selectedSp(sp.sparepart_id)?.stok }})
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <button type="submit" :disabled="form.processing || isCompressing || !form.photo"
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

        <!-- Fullscreen image -->
        <div v-if="showImageModal && selectedReport?.photo"
            class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4"
            @click="showImageModal = false">
            <img :src="`/storage/${selectedReport.photo}`"
                class="max-w-full max-h-full rounded-2xl object-contain" />
        </div>
    </AppLayout>
</template>
