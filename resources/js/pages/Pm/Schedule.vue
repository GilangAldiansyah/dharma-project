<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { Calendar, Plus, Trash2, X, User, Zap, AlertTriangle, CheckCircle2, Info, Pencil } from 'lucide-vue-next';

interface Pic { id: number; name: string; }
interface Jig { id: number; name: string; type: string; line: string; kategori: string; pic_id: number; pic: Pic; }
interface PmSchedule { id: number; interval: '1_bulan'|'3_bulan'; tahun: number; is_active: boolean; jig: Jig; }

interface Props {
    schedules: PmSchedule[]; jigs: Jig[]; pics: Pic[];
    jigsBelumAda: Jig[]; previewTahun: number;
    filters: { pic_id?: string; tahun?: string; interval?: string };
}

const props  = defineProps<Props>();
const page   = usePage();
const flash  = computed(() => (page.props as any).flash);

const showAddModal  = ref(false);
const showEditModal = ref(false);
const showBulkModal = ref(false);
const editTarget    = ref<PmSchedule|null>(null);

const filterPic      = ref(props.filters.pic_id   ?? '');
const filterTahun    = ref(props.filters.tahun    ?? '');
const filterInterval = ref(props.filters.interval ?? '');
const previewTahun   = ref(props.previewTahun);

watch([filterPic, filterTahun, filterInterval], () => {
    router.get('/jig/pm/schedule', {
        pic_id: filterPic.value, tahun: filterTahun.value, interval: filterInterval.value,
    }, { preserveState: true, preserveScroll: true });
});

watch(previewTahun, (val) => {
    router.get('/jig/pm/schedule', {
        pic_id: filterPic.value, tahun: filterTahun.value, interval: filterInterval.value,
        preview_tahun: val,
    }, { preserveState: true, preserveScroll: true });
});

// Form tambah
const form = useForm({ jig_id: null as number|null, interval: '1_bulan' as '1_bulan'|'3_bulan', tahun: new Date().getFullYear() });
watch(() => form.jig_id, (id) => {
    const jig = props.jigs.find(j => j.id === id);
    if (jig?.kategori === 'regular')     form.interval = '1_bulan';
    if (jig?.kategori === 'slow_moving') form.interval = '3_bulan';
});

// Form edit
const editForm = useForm({ interval: '1_bulan' as '1_bulan'|'3_bulan', tahun: new Date().getFullYear() });

const openEdit = (s: PmSchedule) => {
    editTarget.value = s;
    editForm.interval = s.interval;
    editForm.tahun    = s.tahun;
    showEditModal.value = true;
};
const closeEdit = () => { showEditModal.value = false; editTarget.value = null; editForm.reset(); };

// Form bulk
const bulkForm = useForm({ tahun: new Date().getFullYear(), skip_exists: true });

const submitAdd = () => {
    form.post('/jig/pm/schedule', { onSuccess: () => { showAddModal.value = false; form.reset(); } });
};
const submitEdit = () => {
    if (!editTarget.value) return;
    editForm.put(`/jig/pm/schedule/${editTarget.value.id}`, { onSuccess: closeEdit });
};
const submitBulk = () => {
    bulkForm.post('/jig/pm/schedule/generate-bulk', {
        onSuccess: () => { showBulkModal.value = false; bulkForm.reset('tahun'); }
    });
};

const destroy = (s: PmSchedule) => {
    if (confirm(`Hapus schedule "${s.jig?.name}" tahun ${s.tahun}?`)) {
        router.delete(`/jig/pm/schedule/${s.id}`, { preserveScroll: true });
    }
};

const KATEGORI_INTERVAL: Record<string, string> = { regular: '1 Bulan', slow_moving: '3 Bulan' };
const intervalColor = (i: string) => i === '1_bulan' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700';
const getTargetMonths = (i: string) => i === '1_bulan' ? 'Setiap bulan (12x/tahun)' : 'Jan · Apr · Jul · Okt (4x/tahun)';

const bulkRegular    = computed(() => props.jigsBelumAda.filter(j => j.kategori === 'regular').length);
const bulkSlowMoving = computed(() => props.jigsBelumAda.filter(j => j.kategori === 'slow_moving').length);
const bulkTotal      = computed(() => bulkRegular.value + bulkSlowMoving.value);

// Edit: show warning if interval or tahun will change
const editWillRegenerate = computed(() => {
    if (!editTarget.value) return false;
    return editForm.interval !== editTarget.value.interval || editForm.tahun !== editTarget.value.tahun;
});
</script>

<template>
    <Head title="PM Schedule" />
    <AppLayout :breadcrumbs="[{title:'JIG',href:'/jig/dashboard'},{title:'PM Schedule',href:'/jig/pm/schedule'}]">
        <div class="p-4 sm:p-6 space-y-5">

            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Calendar class="w-6 h-6 text-indigo-600" /> PM Schedule
                    </h1>
                    <p class="text-sm text-gray-500 mt-0.5">Jadwal preventive maintenance JIG</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="showBulkModal = true"
                        class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-500 text-white rounded-xl hover:shadow-lg transition-all font-semibold text-sm">
                        <Zap class="w-4 h-4" /> Generate Semua
                    </button>
                    <button @click="showAddModal = true"
                        class="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-colors font-medium text-sm">
                        <Plus class="w-4 h-4" /> Tambah Manual
                    </button>
                </div>
            </div>

            <!-- Flash -->
            <div v-if="flash?.success" class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-xl text-sm">
                <CheckCircle2 class="w-5 h-5 text-green-600 flex-shrink-0" />
                <p class="text-green-800 dark:text-green-200 font-medium">{{ flash.success }}</p>
            </div>

            <!-- Filter -->
            <div class="flex flex-wrap items-center gap-2">
                <select v-model="filterPic" class="px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="">Semua PIC</option>
                    <option v-for="p in pics" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <input v-model="filterTahun" type="number" min="2020" placeholder="Tahun"
                    class="w-24 px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none" />
                <select v-model="filterInterval" class="px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="">Semua Interval</option>
                    <option value="1_bulan">1 Bulan</option>
                    <option value="3_bulan">3 Bulan</option>
                </select>
                <span class="text-xs text-gray-400">{{ schedules.length }} schedule</span>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">JIG</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">PIC</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Interval</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Target</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Tahun</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="schedules.length === 0">
                                <td colspan="6" class="py-14 text-center text-gray-400 text-sm">
                                    <Calendar class="w-10 h-10 mx-auto mb-2 opacity-30" />
                                    Belum ada schedule PM
                                </td>
                            </tr>
                            <tr v-for="s in schedules" :key="s.id" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ s.jig?.name }}</p>
                                    <p class="text-xs text-gray-400">{{ s.jig?.type }} — {{ s.jig?.line }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                            {{ s.jig?.pic?.name?.charAt(0)?.toUpperCase() }}
                                        </div>
                                        <span class="text-xs text-gray-700 dark:text-gray-300">{{ s.jig?.pic?.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-bold', intervalColor(s.interval)]">
                                        {{ s.interval === '1_bulan' ? '1 Bulan' : '3 Bulan' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ getTargetMonths(s.interval) }}</td>
                                <td class="px-4 py-3 text-center text-sm font-black text-gray-900 dark:text-white">{{ s.tahun }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button @click="openEdit(s)" class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors" title="Edit">
                                            <Pencil class="w-4 h-4" />
                                        </button>
                                        <button @click="destroy(s)" class="p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Hapus">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══ EDIT MODAL ══ -->
        <div v-if="showEditModal && editTarget" class="fixed inset-0 backdrop-blur-sm bg-black/30 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full shadow-2xl">
                <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Edit Schedule</h2>
                        <p class="text-xs text-gray-400 mt-0.5">{{ editTarget.jig?.name }}</p>
                    </div>
                    <button @click="closeEdit" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                </div>
                <form @submit.prevent="submitEdit" class="p-5 space-y-4">
                    <!-- JIG info (readonly) -->
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-500 font-semibold mb-1">JIG</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ editTarget.jig?.name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ editTarget.jig?.line }} · PIC: {{ editTarget.jig?.pic?.name }}</p>
                    </div>

                    <!-- Interval -->
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Interval</label>
                        <div class="flex gap-3">
                            <button type="button" @click="editForm.interval = '1_bulan'"
                                :class="['flex-1 py-2.5 rounded-xl font-semibold text-sm transition-all border-2',
                                    editForm.interval === '1_bulan' ? 'bg-blue-500 text-white border-blue-500 shadow-md' : 'border-gray-200 text-gray-600 dark:border-gray-600 dark:text-gray-300 hover:border-blue-300']">
                                1 Bulan
                                <span class="block text-xs font-normal opacity-70 mt-0.5">12x / tahun</span>
                            </button>
                            <button type="button" @click="editForm.interval = '3_bulan'"
                                :class="['flex-1 py-2.5 rounded-xl font-semibold text-sm transition-all border-2',
                                    editForm.interval === '3_bulan' ? 'bg-yellow-500 text-white border-yellow-500 shadow-md' : 'border-gray-200 text-gray-600 dark:border-gray-600 dark:text-gray-300 hover:border-yellow-300']">
                                3 Bulan
                                <span class="block text-xs font-normal opacity-70 mt-0.5">4x / tahun</span>
                            </button>
                        </div>
                        <p v-if="editForm.errors.interval" class="mt-1 text-xs text-red-500">{{ editForm.errors.interval }}</p>
                    </div>

                    <!-- Tahun -->
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Tahun</label>
                        <input v-model="editForm.tahun" type="number" min="2020" max="2099" required
                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none" />
                        <p v-if="editForm.errors.tahun" class="mt-1 text-xs text-red-500">{{ editForm.errors.tahun }}</p>
                    </div>

                    <!-- Warning jika akan re-generate -->
                    <div v-if="editWillRegenerate" class="flex items-start gap-2 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                        <AlertTriangle class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
                        <p class="text-xs text-amber-700 dark:text-amber-300">
                            Laporan PM <strong>pending</strong> untuk schedule ini akan dihapus dan di-generate ulang sesuai interval/tahun baru. Laporan yang sudah done/late tetap aman.
                        </p>
                    </div>

                    <div class="flex gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <button type="submit" :disabled="editForm.processing"
                            class="flex-1 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 disabled:opacity-50 font-semibold text-sm">
                            {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </button>
                        <button type="button" @click="closeEdit"
                            class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 font-medium text-sm">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══ BULK GENERATE MODAL ══ -->
        <div v-if="showBulkModal" class="fixed inset-0 backdrop-blur-sm bg-black/30 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-xl w-full shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <Zap class="w-5 h-5 text-emerald-500" /> Generate Semua PM Schedule
                        </h2>
                        <p class="text-xs text-gray-400 mt-0.5">Otomatis dari master JIG berdasarkan kategori</p>
                    </div>
                    <button @click="showBulkModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                </div>
                <div class="p-5 space-y-5">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 border border-blue-100 dark:border-blue-800">
                        <p class="text-xs font-bold text-blue-800 dark:text-blue-200 mb-2 flex items-center gap-1.5"><Info class="w-3.5 h-3.5" /> Aturan Interval Otomatis</p>
                        <div class="space-y-1.5 text-xs text-blue-700 dark:text-blue-300">
                            <div class="flex items-center gap-2"><span class="px-2 py-0.5 bg-blue-200 dark:bg-blue-800 rounded font-bold">Regular</span><span>→ <strong>1 Bulan</strong> (12 laporan/tahun)</span></div>
                            <div class="flex items-center gap-2"><span class="px-2 py-0.5 bg-yellow-200 dark:bg-yellow-800 rounded font-bold text-yellow-800">Slow Moving</span><span>→ <strong>3 Bulan</strong> (4 laporan/tahun)</span></div>
                            <div class="flex items-center gap-2"><span class="px-2 py-0.5 bg-red-200 dark:bg-red-800 rounded font-bold text-red-800">Discontinue</span><span>→ <strong>Dilewati</strong></span></div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Tahun</label>
                        <input v-model="bulkForm.tahun" @change="previewTahun = bulkForm.tahun" type="number" min="2020" max="2099"
                            class="w-32 px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none" />
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Lewati yang sudah ada</p>
                            <p class="text-xs text-gray-400 mt-0.5">Jika dimatikan, schedule lama akan dihapus dan dibuat ulang</p>
                        </div>
                        <button @click="bulkForm.skip_exists = !bulkForm.skip_exists" type="button"
                            :class="['relative w-11 h-6 rounded-full transition-all flex-shrink-0', bulkForm.skip_exists ? 'bg-indigo-600' : 'bg-gray-300']">
                            <span :class="['absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all', bulkForm.skip_exists ? 'left-5' : 'left-0.5']"></span>
                        </button>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white mb-3">Preview — JIG belum punya schedule tahun {{ previewTahun }}</p>
                        <div v-if="bulkTotal === 0" class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-xl text-center">
                            <CheckCircle2 class="w-8 h-8 mx-auto mb-1.5 text-green-500" />
                            <p class="text-sm font-semibold text-green-800 dark:text-green-200">Semua JIG sudah punya schedule</p>
                        </div>
                        <div v-else class="space-y-2">
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3 text-center border border-blue-100">
                                    <p class="text-xs text-blue-600 font-semibold">Regular (1 Bulan)</p>
                                    <p class="text-2xl font-black text-blue-700 dark:text-blue-300">{{ bulkRegular }}</p>
                                </div>
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-3 text-center border border-yellow-100">
                                    <p class="text-xs text-yellow-600 font-semibold">Slow Moving (3 Bulan)</p>
                                    <p class="text-2xl font-black text-yellow-700 dark:text-yellow-300">{{ bulkSlowMoving }}</p>
                                </div>
                            </div>
                            <div class="max-h-48 overflow-y-auto rounded-xl border border-gray-100 dark:border-gray-700 divide-y divide-gray-50 dark:divide-gray-700/50">
                                <div v-for="jig in jigsBelumAda" :key="jig.id" class="flex items-center justify-between px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ jig.name }}</p>
                                        <p class="text-xs text-gray-400">{{ jig.line }} · <User class="w-3 h-3 inline" /> {{ jig.pic?.name }}</p>
                                    </div>
                                    <span :class="['flex-shrink-0 ml-2 px-2 py-0.5 rounded-full text-xs font-bold',
                                        jig.kategori === 'regular' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700']">
                                        {{ KATEGORI_INTERVAL[jig.kategori] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!bulkForm.skip_exists" class="flex items-start gap-2 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 rounded-xl">
                        <AlertTriangle class="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" />
                        <p class="text-xs text-red-700 dark:text-red-300"><strong>Perhatian:</strong> Laporan PM pending dari schedule lama akan terhapus.</p>
                    </div>
                </div>
                <div class="p-5 border-t border-gray-100 dark:border-gray-700 flex gap-3">
                    <button @click="submitBulk" :disabled="bulkForm.processing || bulkTotal === 0"
                        class="flex-1 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-500 text-white rounded-xl hover:shadow-lg disabled:opacity-50 transition-all font-semibold text-sm flex items-center justify-center gap-2">
                        <Zap class="w-4 h-4" />{{ bulkForm.processing ? 'Generating...' : `Generate ${bulkTotal} Schedule` }}
                    </button>
                    <button @click="showBulkModal = false" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 font-medium text-sm">Batal</button>
                </div>
            </div>
        </div>

        <!-- ══ ADD MANUAL MODAL ══ -->
        <div v-if="showAddModal" class="fixed inset-0 backdrop-blur-sm bg-black/30 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full shadow-2xl">
                <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tambah Schedule Manual</h2>
                    <button @click="showAddModal = false; form.reset()" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                </div>
                <form @submit.prevent="submitAdd" class="p-5 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">JIG <span class="text-red-500">*</span></label>
                        <select v-model="form.jig_id" required class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none">
                            <option :value="null" disabled>Pilih JIG</option>
                            <option v-for="j in jigs" :key="j.id" :value="j.id">{{ j.name }} — {{ j.kategori === 'regular' ? 'Regular' : 'Slow Moving' }}</option>
                        </select>
                        <p v-if="form.errors.jig_id" class="mt-1 text-xs text-red-500">{{ form.errors.jig_id }}</p>
                    </div>
                    <div v-if="form.jig_id" class="bg-indigo-50 dark:bg-indigo-900/20 rounded-xl p-3 border border-indigo-100 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5"><User class="w-3.5 h-3.5 text-indigo-500" /><span class="text-indigo-700 font-medium">PIC: {{ jigs.find(j => j.id === form.jig_id)?.pic?.name ?? '-' }}</span></div>
                        <span :class="['px-2 py-0.5 rounded-full font-bold', form.interval === '1_bulan' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700']">
                            {{ form.interval === '1_bulan' ? '1 Bulan' : '3 Bulan' }}
                        </span>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Tahun <span class="text-red-500">*</span></label>
                        <input v-model="form.tahun" type="number" min="2020" max="2099" required class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none" />
                    </div>
                    <div class="flex gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <button type="submit" :disabled="form.processing" class="flex-1 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 disabled:opacity-50 font-semibold text-sm">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan & Generate' }}
                        </button>
                        <button type="button" @click="showAddModal = false; form.reset()" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 font-medium text-sm">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
