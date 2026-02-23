<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onUnmounted } from 'vue';
import {
    Search, Activity, AlertCircle, Clock, CheckCircle, RefreshCw,
    Eye, Trash2, X, Loader2, Camera, ScanLine, Factory,
} from 'lucide-vue-next';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import jsQR from 'jsqr';

interface Area {
    id: number;
    name: string;
}

interface Line {
    id: number;
    line_code: string;
    line_name: string;
    plant: string;
    status: string;
    qr_code: string;
    area?: Area;
}

interface Machine {
    id: number;
    machine_name: string;
    barcode: string;
    plant: string;
    line: string;
    machine_type: string;
}

interface LineOperation {
    id: number;
    operation_number: string;
    started_at: string;
    status: string;
}

interface Report {
    id: number;
    line_id: number;
    machine_id: number;
    report_number: string;
    problem: string;
    status: 'Dilaporkan' | 'Sedang Diperbaiki' | 'Selesai';
    reported_by: string;
    reported_at: string;
    line_stopped_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    repair_duration_formatted: string | null;
    line_stop_duration_formatted: string | null;
    shift: number;
    shift_label: string;
    line: Line;
    machine: Machine;
    line_operation: LineOperation | null;
}

interface Props {
    reports: {
        data: Report[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: {
        total: number;
        dilaporkan: number;
        sedang_diperbaiki: number;
        selesai_hari_ini: number;
        mttr: string | null;
        mtbf: number | null;
    };
    lines: Line[];
    areas: Area[];
    shifts: Array<{ value: number; label: string }>;
    filters: {
        shift: any;
        search?: string;
        status?: string;
        area_id?: number;
        line_id?: number;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const areaFilter = ref(props.filters.area_id?.toString() || '');
const lineFilter = ref(props.filters.line_id?.toString() || '');
const shiftFilter = ref(props.filters.shift?.toString() || '');
const autoRefresh = ref(false);
let refreshInterval: number | null = null;

const showLineStopModal = ref(false);
const showDetailModal = ref(false);
const selectedReport = ref<Report | null>(null);

const showQrModal = ref(false);
const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const isScanning = ref(false);
const scanError = ref('');
const scannedLine = ref<Line | null>(null);
const scannedMachines = ref<Machine[]>([]);
const isCameraActive = ref(false);
const scanMode = ref<'start_operation' | 'line_stop'>('line_stop');
let stream: MediaStream | null = null;
let scanInterval: number | null = null;

const lineStopForm = useForm({
    line_id: null as number | null,
    machine_id: null as number | null,
    problem: '',
    reported_by: '',
});

const filteredLines = computed(() => {
    if (areaFilter.value) {
        return props.lines.filter(l => l.area?.id?.toString() === areaFilter.value);
    }
    return props.lines;
});

const search = () => {
    router.get('/maintenance', {
        search: searchQuery.value,
        status: statusFilter.value,
        area_id: areaFilter.value,
        line_id: lineFilter.value,
        shift: shiftFilter.value,
    }, { preserveState: true, preserveScroll: true });
};

const getShiftBadgeColor = (shift: number) => {
    switch (shift) {
        case 1: return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 2: return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
        case 3: return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
    }
};

const refreshData = () => {
    router.reload({ only: ['reports', 'stats'] });
};

const toggleAutoRefresh = () => {
    autoRefresh.value = !autoRefresh.value;
    if (autoRefresh.value) {
        refreshInterval = window.setInterval(refreshData, 30000);
    } else if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
};

const openQrModalForLineStop = async () => {
    scanMode.value = 'line_stop';
    scanError.value = '';
    scannedLine.value = null;
    scannedMachines.value = [];
    showQrModal.value = true;
    await startCamera();
};

const submitLineStop = () => {
    lineStopForm.post('/maintenance', {
        preserveScroll: true,
        onSuccess: () => {
            showLineStopModal.value = false;
            lineStopForm.reset();
            scannedLine.value = null;
            scannedMachines.value = [];
        }
    });
};

const startCamera = async () => {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            await videoRef.value.play();
            isCameraActive.value = true;
            startQrScan();
        }
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    } catch (error) {
        scanError.value = 'Tidak dapat mengakses kamera. Pastikan izin kamera sudah diberikan.';
    }
};

const stopCamera = () => {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    if (scanInterval) {
        clearInterval(scanInterval);
        scanInterval = null;
    }
    isCameraActive.value = false;
};

const startQrScan = () => {
    scanInterval = window.setInterval(() => {
        if (videoRef.value && canvasRef.value && isCameraActive.value) {
            const canvas = canvasRef.value;
            const video = videoRef.value;
            const ctx = canvas.getContext('2d');
            if (ctx && video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height);
                if (code) processQrCode(code.data);
            }
        }
    }, 300);
};

const processQrCode = async (qrData: string) => {
    if (isScanning.value) return;
    isScanning.value = true;
    scanError.value = '';
    try {
        const response = await fetch('/maintenance/scan-barcode', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ qr_code: qrData }),
        });
        const data = await response.json();
        if (data.success) {
            scannedLine.value = data.data.line;
            scannedMachines.value = data.data.machines;
            stopCamera();
            showQrModal.value = false;
            if (!data.data.line.has_running_operation) {
                alert('Line ini belum memulai operasi. Mulai operasi terlebih dahulu!');
                return;
            }
            lineStopForm.line_id = data.data.line.id;
            showLineStopModal.value = true;
        } else {
            scanError.value = data.message || 'Line tidak ditemukan';
        }
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    } catch (error) {
        scanError.value = 'Gagal memproses QR Code. Coba lagi.';
    } finally {
        setTimeout(() => { isScanning.value = false; }, 1000);
    }
};

const closeQrModal = () => {
    stopCamera();
    showQrModal.value = false;
};

const viewDetail = (report: Report) => {
    selectedReport.value = report;
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    selectedReport.value = null;
};

const completeRepair = (reportId: number) => {
    if (confirm('Tandai perbaikan sebagai selesai? Line akan otomatis beroperasi kembali.')) {
        router.post(`/maintenance/${reportId}/complete`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedReport.value?.id === reportId) closeDetailModal();
            }
        });
    }
};

const deleteReport = (reportId: number) => {
    if (confirm('Hapus laporan ini?')) {
        router.delete(`/maintenance/${reportId}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedReport.value?.id === reportId) closeDetailModal();
            }
        });
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'Dilaporkan': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'Sedang Diperbaiki': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'Selesai': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'Dilaporkan': return AlertCircle;
        case 'Sedang Diperbaiki': return Clock;
        case 'Selesai': return CheckCircle;
        default: return AlertCircle;
    }
};

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};

const getPaginationRange = () => {
    const currentPage = props.reports.current_page;
    const lastPage = props.reports.last_page;
    const delta = 2;
    const range: (number | string)[] = [];
    for (let i = Math.max(2, currentPage - delta); i <= Math.min(lastPage - 1, currentPage + delta); i++) range.push(i);
    if (currentPage - delta > 2) range.unshift('...');
    if (currentPage + delta < lastPage - 1) range.push('...');
    range.unshift(1);
    if (lastPage > 1) range.push(lastPage);
    return range;
};

const goToPage = (page: number) => {
    router.get('/maintenance', {
        page,
        search: searchQuery.value,
        status: statusFilter.value,
        area_id: areaFilter.value,
        line_id: lineFilter.value,
        shift: shiftFilter.value,
    }, { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
    searchQuery.value = '';
    statusFilter.value = '';
    areaFilter.value = '';
    lineFilter.value = '';
    shiftFilter.value = '';
    router.get('/maintenance', {}, { preserveState: false });
};

onUnmounted(() => {
    stopCamera();
    if (refreshInterval) clearInterval(refreshInterval);
});
</script>
<template>
    <Head title="Monitoring Line Stop Mesin" />
    <AppLayout :breadcrumbs="[{ title: 'Monitoring Line Stop Mesin', href: '/maintenance' }]">
        <div class="p-4 space-y-3 bg-white dark:bg-gray-900 min-h-screen">

            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent flex items-center gap-2">
                        <Activity class="w-6 h-6 text-blue-600" />
                        Monitoring Line Stop Mesin
                    </h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Monitor dan kelola laporan kerusakan mesin</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="toggleAutoRefresh"
                        :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all text-xs font-medium',
                            autoRefresh ? 'bg-green-500 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200']">
                        <RefreshCw :class="['w-3.5 h-3.5', autoRefresh ? 'animate-spin' : '']" />
                        {{ autoRefresh ? 'Auto ON' : 'Auto OFF' }}
                    </button>
                    <button @click="openQrModalForLineStop"
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-red-500 to-rose-500 text-white rounded-lg hover:shadow-md transition-all text-xs font-medium">
                        <ScanLine class="w-3.5 h-3.5" />
                        Line Stop
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-100 dark:border-gray-700 shadow-sm">
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase leading-none">Total</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.total }}</p>
                </div>
                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-3 border border-yellow-200 dark:border-yellow-800 shadow-sm">
                    <p class="text-xs text-yellow-600 dark:text-yellow-300 font-semibold uppercase leading-none">Dilaporkan</p>
                    <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-200 mt-1">{{ stats.dilaporkan }}</p>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3 border border-blue-200 dark:border-blue-800 shadow-sm">
                    <p class="text-xs text-blue-600 dark:text-blue-300 font-semibold uppercase leading-none">Diperbaiki</p>
                    <p class="text-2xl font-bold text-blue-700 dark:text-blue-200 mt-1">{{ stats.sedang_diperbaiki }}</p>
                </div>
                <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-3 border border-green-200 dark:border-green-800 shadow-sm">
                    <p class="text-xs text-green-600 dark:text-green-300 font-semibold uppercase leading-none">Selesai</p>
                    <p class="text-2xl font-bold text-green-700 dark:text-green-200 mt-1">{{ stats.selesai_hari_ini }}</p>
                </div>
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-3 border border-purple-200 dark:border-purple-800 shadow-sm">
                    <p class="text-xs text-purple-600 dark:text-purple-300 font-semibold uppercase leading-none">MTTR</p>
                    <p class="text-base font-bold text-purple-700 dark:text-purple-200 mt-1">{{ stats.mttr || 'N/A' }}</p>
                </div>
                <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-xl p-3 border border-indigo-200 dark:border-indigo-800 shadow-sm">
                    <p class="text-xs text-indigo-600 dark:text-indigo-300 font-semibold uppercase leading-none">MTBF</p>
                    <p class="text-base font-bold text-indigo-700 dark:text-indigo-200 mt-1">{{ stats.mtbf ? stats.mtbf.toFixed(2) + 'h' : 'N/A' }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl px-3 py-2.5 shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="flex flex-wrap gap-2 items-center">
                    <div class="relative flex-1 min-w-[160px]">
                        <input v-model="searchQuery" @keyup.enter="search" type="text"
                            placeholder="Cari line, mesin, nomor laporan..."
                            class="w-full rounded-lg border border-gray-200 dark:border-gray-700 pl-8 pr-3 py-1.5 dark:bg-gray-700 focus:border-blue-500 transition-all text-sm" />
                        <Search class="absolute left-2.5 top-2 w-3.5 h-3.5 text-gray-400" />
                    </div>
                    <select v-model="statusFilter" @change="search"
                        class="px-2 py-1.5 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-sm focus:border-blue-500 transition-all">
                        <option value="">Semua Status</option>
                        <option value="Dilaporkan">Dilaporkan</option>
                        <option value="Sedang Diperbaiki">Sedang Diperbaiki</option>
                        <option value="Selesai">Selesai</option>
                    </select>
                    <select v-model="areaFilter" @change="search"
                        class="px-2 py-1.5 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-sm focus:border-blue-500 transition-all">
                        <option value="">Semua Area</option>
                        <option v-for="area in areas" :key="area.id" :value="area.id">{{ area.name }}</option>
                    </select>
                    <select v-model="lineFilter" @change="search"
                        class="px-2 py-1.5 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-sm focus:border-blue-500 transition-all">
                        <option value="">Semua Line</option>
                        <option v-for="line in filteredLines" :key="line.id" :value="line.id">
                            {{ line.line_name }} ({{ line.line_code }})
                        </option>
                    </select>
                    <select v-model="shiftFilter" @change="search"
                        class="px-2 py-1.5 border border-gray-200 dark:border-gray-700 rounded-lg dark:bg-gray-700 text-sm focus:border-blue-500 transition-all">
                        <option value="">Semua Shift</option>
                        <option v-for="shift in shifts" :key="shift.value" :value="shift.value">{{ shift.label }}</option>
                    </select>
                    <button v-if="searchQuery || statusFilter || areaFilter || lineFilter || shiftFilter"
                        @click="resetFilters"
                        class="px-2.5 py-1.5 text-xs bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all font-medium flex items-center gap-1">
                        <X class="w-3 h-3" /> Reset
                    </button>
                    <button @click="search"
                        class="px-3 py-1.5 text-xs bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-lg hover:shadow-md transition-all font-medium flex items-center gap-1">
                        <Search class="w-3 h-3" /> Cari
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-700 dark:to-gray-700">
                            <tr>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">No Laporan</th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Line</th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Mesin</th>
                                <th class="px-3 py-2.5 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Shift</th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Masalah</th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Waktu Stop</th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Durasi</th>
                                <th class="px-3 py-2.5 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="report in reports.data" :key="report.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-3 py-2">
                                    <span class="font-mono text-xs font-bold text-gray-900 dark:text-white">{{ report.report_number }}</span>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="text-xs font-semibold text-gray-900 dark:text-white">{{ report.line.line_name }}</div>
                                    <div class="text-xs text-gray-400">{{ report.line.line_code }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="text-xs font-semibold text-gray-900 dark:text-white">{{ report.machine.machine_name }}</div>
                                    <div class="text-xs text-gray-400">{{ report.machine.machine_type }}</div>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span :class="['px-1.5 py-0.5 rounded text-xs font-bold', getShiftBadgeColor(report.shift)]">
                                        {{ report.shift_label }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="max-w-[160px] truncate text-xs text-gray-700 dark:text-gray-300">{{ report.problem }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <span :class="['inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-bold', getStatusColor(report.status)]">
                                        <component :is="getStatusIcon(report.status)" class="w-3 h-3" />
                                        {{ report.status }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <div v-if="report.line_stopped_at" class="text-xs text-gray-700 dark:text-gray-300">{{ formatDateTime(report.line_stopped_at) }}</div>
                                    <div class="text-xs text-gray-400">{{ report.reported_by }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <span v-if="report.repair_duration_formatted" class="font-mono text-xs font-bold text-green-600">
                                        {{ report.repair_duration_formatted }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">-</span>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center justify-center gap-1">
                                        <button v-if="report.status === 'Sedang Diperbaiki'"
                                            @click="completeRepair(report.id)"
                                            class="p-1 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition-colors" title="Tandai Selesai">
                                            <CheckCircle class="w-3.5 h-3.5" />
                                        </button>
                                        <button @click="viewDetail(report)"
                                            class="p-1 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors" title="Detail">
                                            <Eye class="w-3.5 h-3.5" />
                                        </button>
                                        <button @click="deleteReport(report.id)"
                                            class="p-1 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Hapus">
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="reports.data.length === 0">
                                <td colspan="9" class="px-4 py-12 text-center">
                                    <Activity class="w-12 h-12 mx-auto mb-3 text-gray-300 opacity-50" />
                                    <h3 class="text-base font-bold text-gray-600 dark:text-gray-300">Tidak ada laporan maintenance</h3>
                                    <p class="text-xs text-gray-400 mt-1">Belum ada laporan yang sesuai dengan filter</p>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="reports.data.length > 0" class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <td colspan="9" class="px-3 py-2 text-xs font-bold text-gray-600 dark:text-gray-300">
                                    Total: {{ reports.total }} laporan
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div v-if="reports.last_page > 1" class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 px-4 py-2.5">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Halaman {{ reports.current_page }} dari {{ reports.last_page }}
                </div>
                <div class="flex items-center gap-1">
                    <button @click="goToPage(reports.current_page - 1)" :disabled="reports.current_page === 1"
                        class="p-1.5 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <template v-for="(page, index) in getPaginationRange()" :key="index">
                        <button v-if="typeof page === 'number'" @click="goToPage(page)"
                            :class="['min-w-[30px] h-7 px-2 rounded-lg text-xs font-bold transition-all',
                                page === reports.current_page
                                    ? 'bg-gradient-to-r from-blue-500 to-indigo-500 text-white shadow-sm'
                                    : 'border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700']">
                            {{ page }}
                        </button>
                        <span v-else class="px-1 text-gray-400 text-xs">...</span>
                    </template>
                    <button @click="goToPage(reports.current_page + 1)" :disabled="reports.current_page === reports.last_page"
                        class="p-1.5 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Total {{ reports.total }} data</div>
            </div>

            <Dialog :open="showQrModal" @update:open="val => !val && closeQrModal()">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Scan QR Code - Line Stop</DialogTitle>
                    </DialogHeader>
                    <div class="space-y-4 mt-4">
                        <div class="relative bg-black rounded-xl overflow-hidden" style="aspect-ratio: 4/3;">
                            <video ref="videoRef" class="w-full h-full object-cover" playsinline></video>
                            <canvas ref="canvasRef" class="hidden"></canvas>
                            <div v-if="!isCameraActive" class="absolute inset-0 flex items-center justify-center bg-gray-900">
                                <Loader2 class="w-12 h-12 text-white animate-spin" />
                            </div>
                            <div v-if="isScanning" class="absolute inset-0 flex items-center justify-center bg-green-500/20">
                                <div class="bg-green-600 text-white px-4 py-2 rounded-xl font-semibold">QR Code Terdeteksi!</div>
                            </div>
                            <div class="absolute inset-0 border-2 border-red-500 m-12 rounded-xl pointer-events-none"></div>
                        </div>
                        <p v-if="scanError" class="text-red-500 text-sm text-center">{{ scanError }}</p>
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-3">
                            <div class="flex items-start gap-2">
                                <Camera class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-600" />
                                <p class="text-xs text-red-800 dark:text-red-300">Scan QR Code LINE ketika terjadi masalah. Perbaikan otomatis dimulai dan MTTR langsung dihitung.</p>
                            </div>
                        </div>
                        <button @click="closeQrModal" class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium transition-all">
                            Tutup
                        </button>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog :open="showLineStopModal" @update:open="showLineStopModal = $event">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Line Stop - Pilih Mesin Bermasalah</DialogTitle>
                    </DialogHeader>
                    <form @submit.prevent="submitLineStop" class="space-y-4 mt-4">
                        <div v-if="scannedLine" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-3">
                            <div class="flex items-center gap-2 mb-2">
                                <AlertCircle class="w-4 h-4 text-red-600" />
                                <span class="font-bold text-sm text-red-700 dark:text-red-300">LINE STOP - Perbaikan Otomatis Dimulai</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs mb-2">
                                <div><span class="text-gray-500">Line:</span><span class="font-semibold ml-1">{{ scannedLine.line_name }}</span></div>
                                <div><span class="text-gray-500">Plant:</span><span class="font-semibold ml-1">{{ scannedLine.plant }}</span></div>
                            </div>
                            <div class="bg-white dark:bg-red-950 rounded-lg p-2 text-xs">
                                <p class="text-red-700 dark:text-red-300 font-medium">⚠️ Semua {{ scannedMachines.length }} mesin di line ini akan berhenti!</p>
                                <p class="text-red-600 dark:text-red-400 mt-0.5">🔧 Timer MTTR otomatis dimulai setelah konfirmasi</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Mesin yang Bermasalah <span class="text-red-500">*</span></label>
                            <select v-model="lineStopForm.machine_id" required
                                class="w-full rounded-xl border-2 border-gray-200 dark:border-gray-700 px-3 py-2 dark:bg-gray-700 focus:border-red-500 transition-all text-sm"
                                :class="{ 'border-red-500': lineStopForm.errors.machine_id }">
                                <option :value="null">-- Pilih Mesin yang Bermasalah --</option>
                                <option v-for="machine in scannedMachines" :key="machine.id" :value="machine.id">
                                    {{ machine.machine_name }} - {{ machine.machine_type }}
                                </option>
                            </select>
                            <p v-if="lineStopForm.errors.machine_id" class="mt-1 text-xs text-red-500">{{ lineStopForm.errors.machine_id }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Deskripsi Masalah <span class="text-gray-400 font-normal">(opsional)</span></label>
                            <textarea v-model="lineStopForm.problem" rows="3" placeholder="Jelaskan masalah yang terjadi..."
                                class="w-full rounded-xl border-2 border-gray-200 dark:border-gray-700 px-3 py-2 dark:bg-gray-700 focus:border-red-500 transition-all text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Dilaporkan Oleh <span class="text-gray-400 font-normal">(opsional)</span></label>
                            <input v-model="lineStopForm.reported_by" type="text" placeholder="Nama pelapor"
                                class="w-full rounded-xl border-2 border-gray-200 dark:border-gray-700 px-3 py-2 dark:bg-gray-700 focus:border-red-500 transition-all text-sm" />
                        </div>
                        <div class="flex gap-2 pt-3 border-t border-gray-200 dark:border-gray-700">
                            <button type="button" @click="showLineStopModal = false" :disabled="lineStopForm.processing"
                                class="flex-1 px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 text-sm font-medium transition-all">
                                Batal
                            </button>
                            <button type="submit" :disabled="lineStopForm.processing"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-r from-red-500 to-rose-500 text-white rounded-xl hover:shadow-md disabled:opacity-50 text-sm font-medium transition-all">
                                <AlertCircle class="w-3.5 h-3.5" />
                                {{ lineStopForm.processing ? 'Memproses...' : 'Konfirmasi Line Stop' }}
                            </button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog :open="showDetailModal" @update:open="showDetailModal = $event">
                <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Detail Laporan Maintenance</DialogTitle>
                    </DialogHeader>
                    <div v-if="selectedReport" class="space-y-4 mt-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-xs text-gray-500 font-semibold uppercase">Nomor Laporan</div>
                                <div class="text-xl font-bold font-mono text-gray-900 dark:text-white">{{ selectedReport.report_number }}</div>
                            </div>
                            <span :class="['inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold', getStatusColor(selectedReport.status)]">
                                <component :is="getStatusIcon(selectedReport.status)" class="w-3.5 h-3.5" />
                                {{ selectedReport.status }}
                            </span>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3">
                            <h3 class="font-bold text-xs mb-2.5 flex items-center gap-1.5 text-gray-700 dark:text-gray-300 uppercase">
                                <Factory class="w-3.5 h-3.5" /> Informasi Line & Mesin
                            </h3>
                            <div class="grid grid-cols-2 gap-2.5">
                                <div><div class="text-xs text-gray-400">Line</div><div class="font-semibold text-sm text-gray-900 dark:text-white">{{ selectedReport.line.line_name }}</div><div class="text-xs text-gray-400">{{ selectedReport.line.line_code }}</div></div>
                                <div><div class="text-xs text-gray-400">Plant</div><div class="font-semibold text-sm text-gray-900 dark:text-white">{{ selectedReport.line.plant }}</div></div>
                                <div>
                                    <div class="text-xs text-gray-400">Shift</div>
                                    <span :class="['inline-flex px-1.5 py-0.5 rounded text-xs font-bold', getShiftBadgeColor(selectedReport.shift)]">{{ selectedReport.shift_label }}</span>
                                </div>
                                <div><div class="text-xs text-gray-400">Mesin</div><div class="font-semibold text-sm text-gray-900 dark:text-white">{{ selectedReport.machine.machine_name }}</div></div>
                                <div><div class="text-xs text-gray-400">Tipe Mesin</div><div class="font-semibold text-sm text-gray-900 dark:text-white">{{ selectedReport.machine.machine_type }}</div></div>
                            </div>
                        </div>
                        <div>
                            <h3 class="font-bold text-xs mb-2 text-gray-700 dark:text-gray-300 uppercase">Deskripsi Masalah</h3>
                            <div class="p-3 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-xl">
                                <p class="text-sm text-gray-800 dark:text-gray-200">{{ selectedReport.problem }}</p>
                            </div>
                        </div>
                        <div>
                            <h3 class="font-bold text-xs mb-2.5 text-gray-700 dark:text-gray-300 uppercase">Timeline</h3>
                            <div class="space-y-3">
                                <div class="flex gap-3">
                                    <div class="flex flex-col items-center">
                                        <div class="w-8 h-8 bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-300 rounded-full flex items-center justify-center flex-shrink-0">
                                            <AlertCircle class="w-4 h-4" />
                                        </div>
                                        <div v-if="selectedReport.completed_at" class="flex-1 w-0.5 bg-gray-200 dark:bg-gray-700 my-1.5"></div>
                                    </div>
                                    <div class="flex-1 pb-3">
                                        <div class="font-semibold text-sm text-gray-900 dark:text-white">Line Stop & Perbaikan Dimulai</div>
                                        <div class="text-xs text-gray-500">{{ selectedReport.line_stopped_at ? formatDateTime(selectedReport.line_stopped_at) : formatDateTime(selectedReport.reported_at) }}</div>
                                        <div class="text-xs text-gray-500">Oleh: {{ selectedReport.reported_by }}</div>
                                        <div class="mt-1.5 text-xs bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 px-2 py-1 rounded-lg inline-block">🔧 MTTR Timer Started</div>
                                    </div>
                                </div>
                                <div v-if="selectedReport.completed_at" class="flex gap-3">
                                    <div class="w-8 h-8 bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-300 rounded-full flex items-center justify-center flex-shrink-0">
                                        <CheckCircle class="w-4 h-4" />
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-semibold text-sm text-gray-900 dark:text-white">Perbaikan Selesai & Line Beroperasi</div>
                                        <div class="text-xs text-gray-500">{{ formatDateTime(selectedReport.completed_at) }}</div>
                                        <div class="flex flex-wrap gap-1.5 mt-1.5">
                                            <span v-if="selectedReport.repair_duration_formatted" class="px-2 py-0.5 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300 rounded-full text-xs font-bold">
                                                MTTR: {{ selectedReport.repair_duration_formatted }}
                                            </span>
                                            <span v-if="selectedReport.line_stop_duration_formatted" class="px-2 py-0.5 bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 rounded-full text-xs font-bold">
                                                Total Stop: {{ selectedReport.line_stop_duration_formatted }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-3 border-t border-gray-200 dark:border-gray-700">
                            <button v-if="selectedReport.status === 'Sedang Diperbaiki'"
                                @click="completeRepair(selectedReport.id)"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-500 text-white rounded-xl hover:shadow-md text-sm font-medium transition-all">
                                <CheckCircle class="w-3.5 h-3.5" /> Tandai Selesai
                            </button>
                            <button @click="deleteReport(selectedReport.id)"
                                class="flex items-center justify-center gap-1.5 px-4 py-2 bg-gradient-to-r from-red-500 to-rose-500 text-white rounded-xl hover:shadow-md text-sm font-medium transition-all">
                                <Trash2 class="w-3.5 h-3.5" /> Hapus
                            </button>
                            <button @click="closeDetailModal"
                                class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium transition-all">
                                <X class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

        </div>
    </AppLayout>
</template>
