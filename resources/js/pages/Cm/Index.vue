<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { AlertTriangle, Plus, Eye, X, Camera, Loader2, CheckCircle2, Clock, Trash2, Package, User, Calendar } from 'lucide-vue-next';

interface Jig { id: number; name: string; type: string; line: string; }
interface Sparepart { id: number; name: string; satuan: string; }
interface ReportSparepart { sparepart: Sparepart; qty: number; }
interface CmReport {
    id: number; jig_id: number; report_date: string; description: string;
    photo: string | null; status: 'open' | 'in_progress' | 'closed';
    action: string | null; closed_at: string | null;
    jig: Jig; pic: { name: string }; closed_by: { name: string } | null;
    spareparts: ReportSparepart[];
}

interface Props {
    reports:    CmReport[];
    jigs:       Jig[];
    spareparts: Sparepart[];
    filters:    { status?: string };
}

const props = defineProps<Props>();

const filterStatus    = ref(props.filters.status || '');
const showCreateModal = ref(false);
const showDetailModal = ref(false);
const showCloseModal  = ref(false);
const showImageModal  = ref(false);
const selectedReport  = ref<CmReport | null>(null);
const isCompressing   = ref(false);
const previewImage    = ref<string | null>(null);

const form = useForm({
    jig_id:      null as number | null,
    description: '',
    photo:       null as File | null,
    spareparts:  [] as { sparepart_id: number | null; qty: string }[],
});

const closeForm = useForm({ action: '' });

const applyFilter = () => {
    router.get('/jig/cm', { status: filterStatus.value }, { preserveState: true, preserveScroll: true });
};

const closeCreateModal = () => { showCreateModal.value = false; form.reset(); previewImage.value = null; };
const openDetail = (r: CmReport) => { selectedReport.value = r; showDetailModal.value = true; };
const closeDetail = () => { showDetailModal.value = false; selectedReport.value = null; };

const handlePhoto = async (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    isCompressing.value = true;
    form.photo = await compressImage(file);
    const reader = new FileReader();
    reader.onload = (ev) => { previewImage.value = ev.target?.result as string; };
    reader.readAsDataURL(form.photo);
    isCompressing.value = false;
};

const compressImage = (file: File): Promise<File> => {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let w = img.width, h = img.height, max = 1920;
                if (w > max || h > max) { if (w > h) { h = (h / w) * max; w = max; } else { w = (w / h) * max; h = max; } }
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d')!.drawImage(img, 0, 0, w, h);
                canvas.toBlob((blob) => resolve(new File([blob!], 'photo.jpg', { type: 'image/jpeg' })), 'image/jpeg', 0.8);
            };
            img.src = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    });
};

const addSparepart    = () => form.spareparts.push({ sparepart_id: null, qty: '' });
const removeSparepart = (i: number) => form.spareparts.splice(i, 1);
const submitCreate    = () => { form.post('/jig/cm', { onSuccess: closeCreateModal }); };

const updateStatus = (report: CmReport, status: 'open' | 'in_progress') => {
    router.put(`/jig/cm/${report.id}`, { status }, { preserveScroll: true });
};

const openCloseModal = (report: CmReport) => { selectedReport.value = report; closeForm.reset(); showCloseModal.value = true; };
const submitClose = () => {
    if (!selectedReport.value) return;
    closeForm.post(`/jig/cm/${selectedReport.value.id}/close`, {
        onSuccess: () => { showCloseModal.value = false; selectedReport.value = null; }
    });
};

const formatDate = (d: string | null) => {
    if (!d) return '-';
    const date = new Date(d);
    if (isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const statusConfig: Record<string, { label: string; class: string }> = {
    open:        { label: 'Open',        class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' },
    in_progress: { label: 'In Progress', class: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300' },
    closed:      { label: 'Closed',      class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' },
};

const cmCounts = {
    open:        props.reports.filter(r => r.status === 'open').length,
    in_progress: props.reports.filter(r => r.status === 'in_progress').length,
    closed:      props.reports.filter(r => r.status === 'closed').length,
};
</script>

<template>
    <Head title="CM Report" />
    <AppLayout :breadcrumbs="[{ title: 'JIG', href: '/jig/dashboard' }, { title: 'CM Report', href: '/jig/cm' }]">
        <div class="p-6 space-y-6 bg-white dark:bg-gray-900 min-h-screen">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-red-500 to-orange-500 bg-clip-text text-transparent flex items-center gap-3">
                        <AlertTriangle class="w-8 h-8 text-red-500" /> CM Report
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Laporan corrective maintenance / abnormality JIG</p>
                </div>
                <button @click="showCreateModal = true"
                    class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-red-500 to-orange-500 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium">
                    <Plus class="w-4 h-4" /> Laporkan Abnormality
                </button>
            </div>

            <!-- Summary -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 rounded-2xl p-4 border border-red-200 dark:border-red-800 shadow-lg">
                    <p class="text-xs font-semibold text-red-600 dark:text-red-300 uppercase">Open</p>
                    <p class="text-3xl font-black text-red-700 dark:text-red-200 mt-1">{{ cmCounts.open }}</p>
                </div>
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 dark:from-yellow-900/20 dark:to-yellow-800/20 rounded-2xl p-4 border border-yellow-200 dark:border-yellow-800 shadow-lg">
                    <p class="text-xs font-semibold text-yellow-600 dark:text-yellow-300 uppercase">In Progress</p>
                    <p class="text-3xl font-black text-yellow-700 dark:text-yellow-200 mt-1">{{ cmCounts.in_progress }}</p>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-2xl p-4 border border-green-200 dark:border-green-800 shadow-lg">
                    <p class="text-xs font-semibold text-green-600 dark:text-green-300 uppercase">Closed</p>
                    <p class="text-3xl font-black text-green-700 dark:text-green-200 mt-1">{{ cmCounts.closed }}</p>
                </div>
            </div>

            <!-- Filter -->
            <div class="flex items-center gap-3">
                <select v-model="filterStatus" @change="applyFilter"
                    class="px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-red-500 text-sm">
                    <option value="">Semua Status</option>
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="closed">Closed</option>
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gradient-to-r from-red-50 to-orange-50 dark:from-gray-700 dark:to-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">JIG</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Deskripsi</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">PIC</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Tanggal</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-if="reports.length === 0">
                                <td colspan="6" class="py-16 text-center text-gray-400 text-sm">Tidak ada laporan CM</td>
                            </tr>
                            <tr v-for="r in reports" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ r.jig?.name }}</p>
                                    <p class="text-xs text-gray-400">{{ r.jig?.type }} — {{ r.jig?.line }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300 max-w-xs truncate">{{ r.description }}</td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ r.pic?.name }}</td>
                                <td class="px-4 py-3 text-xs font-semibold text-gray-900 dark:text-white">{{ formatDate(r.report_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-bold', statusConfig[r.status].class]">
                                        {{ statusConfig[r.status].label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        <button @click="openDetail(r)"
                                            class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors" title="Detail">
                                            <Eye class="w-4 h-4" />
                                        </button>
                                        <button v-if="r.status === 'open'" @click="updateStatus(r, 'in_progress')"
                                            class="p-1.5 text-yellow-600 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 rounded-lg transition-colors" title="Set In Progress">
                                            <Clock class="w-4 h-4" />
                                        </button>
                                        <button v-if="r.status !== 'closed'" @click="openCloseModal(r)"
                                            class="p-1.5 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition-colors" title="Close">
                                            <CheckCircle2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <div v-if="showDetailModal && selectedReport" class="fixed inset-0 backdrop-blur-sm bg-white/30 dark:bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Detail CM Report</h2>
                    <button @click="closeDetail" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <div class="p-6 space-y-5">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700 space-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">JIG</span>
                            <span class="text-xs font-bold text-gray-900 dark:text-white text-right max-w-[60%]">{{ selectedReport.jig?.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">PIC</span>
                            <span class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedReport.pic?.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Tanggal Laporan</span>
                            <span class="text-xs font-bold text-gray-900 dark:text-white">{{ formatDate(selectedReport.report_date) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-500">Status</span>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-bold', statusConfig[selectedReport.status].class]">
                                {{ statusConfig[selectedReport.status].label }}
                            </span>
                        </div>
                        <div v-if="selectedReport.closed_by" class="flex justify-between">
                            <span class="text-xs text-gray-500">Ditutup Oleh</span>
                            <span class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedReport.closed_by?.name }}</span>
                        </div>
                        <div v-if="selectedReport.closed_at" class="flex justify-between">
                            <span class="text-xs text-gray-500">Tanggal Ditutup</span>
                            <span class="text-xs font-bold text-gray-900 dark:text-white">{{ formatDate(selectedReport.closed_at) }}</span>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Deskripsi Abnormality</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 bg-red-50 dark:bg-red-900/10 rounded-xl p-3 border border-red-100 dark:border-red-800">
                            {{ selectedReport.description }}
                        </p>
                    </div>

                    <div v-if="selectedReport.action">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Action Taken</p>
                        <p class="text-xs text-gray-700 dark:text-gray-300 bg-green-50 dark:bg-green-900/10 rounded-xl p-3 border border-green-100 dark:border-green-800">
                            {{ selectedReport.action }}
                        </p>
                    </div>

                    <div v-if="selectedReport.spareparts?.length">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2 flex items-center gap-1">
                            <Package class="w-3.5 h-3.5" /> Sparepart Diganti
                        </p>
                        <div class="space-y-1">
                            <div v-for="sp in selectedReport.spareparts" :key="sp.sparepart?.id"
                                class="flex justify-between items-center px-3 py-2 bg-gray-50 dark:bg-gray-700/50 rounded-xl text-xs">
                                <span class="font-semibold text-gray-900 dark:text-white">{{ sp.sparepart?.name }}</span>
                                <span class="text-gray-500">{{ sp.qty }} {{ sp.sparepart?.satuan }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedReport.photo">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Foto</p>
                        <img :src="`/storage/${selectedReport.photo}`" @click="showImageModal = true"
                            class="w-full rounded-xl object-cover cursor-pointer hover:opacity-90 transition-opacity shadow-md max-h-64" />
                    </div>
                </div>
                <div class="p-6 border-t border-gray-200 dark:border-gray-700">
                    <button @click="closeDetail"
                        class="w-full px-6 py-2.5 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all font-medium">Tutup</button>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 backdrop-blur-sm bg-white/30 dark:bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Laporkan Abnormality</h2>
                    <button @click="closeCreateModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">JIG <span class="text-red-500">*</span></label>
                        <select v-model="form.jig_id" required
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-red-500 text-sm">
                            <option :value="null" disabled>Pilih JIG</option>
                            <option v-for="jig in jigs" :key="jig.id" :value="jig.id">{{ jig.name }} ({{ jig.type }})</option>
                        </select>
                        <p v-if="form.errors.jig_id" class="mt-1 text-xs text-red-600">{{ form.errors.jig_id }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Deskripsi <span class="text-red-500">*</span></label>
                        <textarea v-model="form.description" required rows="4" placeholder="Jelaskan abnormality yang ditemukan..."
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-red-500 text-sm"></textarea>
                        <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Foto (Opsional)</label>
                        <label :class="['cursor-pointer flex items-center gap-2 px-4 py-2.5 text-white rounded-xl font-medium transition-all duration-300 w-fit',
                            isCompressing ? 'bg-gray-400 cursor-not-allowed' : 'bg-gradient-to-r from-red-500 to-orange-500 hover:shadow-md']">
                            <Loader2 v-if="isCompressing" class="w-4 h-4 animate-spin" />
                            <Camera v-else class="w-4 h-4" />
                            {{ isCompressing ? 'Memproses...' : 'Pilih Foto' }}
                            <input type="file" accept="image/*" @change="handlePhoto" class="hidden" :disabled="isCompressing" />
                        </label>
                        <img v-if="previewImage" :src="previewImage" class="mt-3 w-full max-h-48 object-cover rounded-xl shadow-md" />
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Sparepart Diganti (Opsional)</label>
                            <button type="button" @click="addSparepart"
                                class="flex items-center gap-1 px-3 py-1 text-xs bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300 rounded-lg font-semibold hover:bg-red-200 transition-colors">
                                <Plus class="w-3 h-3" /> Tambah
                            </button>
                        </div>
                        <div v-for="(sp, i) in form.spareparts" :key="i" class="flex gap-2 mb-2">
                            <select v-model="sp.sparepart_id"
                                class="flex-1 px-3 py-2 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-red-500 text-xs">
                                <option :value="null" disabled>Pilih Sparepart</option>
                                <option v-for="s in spareparts" :key="s.id" :value="s.id">{{ s.name }} ({{ s.satuan }})</option>
                            </select>
                            <input v-model="sp.qty" type="number" step="0.01" min="0.01" placeholder="Qty"
                                class="w-24 px-3 py-2 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-red-500 text-xs" />
                            <button type="button" @click="removeSparepart(i)"
                                class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit" :disabled="form.processing || isCompressing"
                            class="flex-1 px-6 py-2.5 bg-gradient-to-r from-red-500 to-orange-500 text-white rounded-xl hover:shadow-lg disabled:opacity-50 transition-all duration-300 font-medium">
                            {{ form.processing ? 'Menyimpan...' : 'Kirim Laporan' }}
                        </button>
                        <button type="button" @click="closeCreateModal"
                            class="px-6 py-2.5 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all duration-300 font-medium">Batal</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Close Modal -->
        <div v-if="showCloseModal && selectedReport" class="fixed inset-0 backdrop-blur-sm bg-white/30 dark:bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full shadow-2xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Close CM Report</h2>
                    <button @click="showCloseModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submitClose" class="p-6 space-y-4">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                        <p class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedReport.jig?.name }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ selectedReport.description }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Action Taken <span class="text-red-500">*</span></label>
                        <textarea v-model="closeForm.action" required rows="4" placeholder="Jelaskan tindakan yang sudah dilakukan..."
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-green-500 text-sm"></textarea>
                        <p v-if="closeForm.errors.action" class="mt-1 text-xs text-red-600">{{ closeForm.errors.action }}</p>
                    </div>
                    <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit" :disabled="closeForm.processing"
                            class="flex-1 px-6 py-2.5 bg-gradient-to-r from-green-500 to-emerald-500 text-white rounded-xl hover:shadow-lg disabled:opacity-50 transition-all duration-300 font-medium">
                            {{ closeForm.processing ? 'Menyimpan...' : 'Close Report' }}
                        </button>
                        <button type="button" @click="showCloseModal = false"
                            class="px-6 py-2.5 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all duration-300 font-medium">Batal</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Image Modal -->
        <div v-if="showImageModal && selectedReport?.photo" class="fixed inset-0 backdrop-blur-sm bg-black/80 flex items-center justify-center z-50 p-4"
            @click="showImageModal = false">
            <img :src="`/storage/${selectedReport.photo}`" class="max-w-full max-h-full object-contain rounded-2xl" />
        </div>
    </AppLayout>
</template>
