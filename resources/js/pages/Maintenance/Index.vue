<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onUnmounted } from 'vue';
import {
    Search,
    Activity,
    AlertCircle,
    Clock,
    CheckCircle,
    RefreshCw,
    Eye,
    Trash2,
    X,
    Loader2,
    Camera,
    ScanLine,
    Factory,
} from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import jsQR from 'jsqr';

interface Line {
    id: number;
    line_code: string;
    line_name: string;
    plant: string;
    status: string;
    qr_code: string;
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
    plants: string[];
    shifts: Array<{ value: number; label: string }>;
    filters: {
        shift: any;
        search?: string;
        status?: string;
        plant?: string;
        line_id?: number;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const plantFilter = ref(props.filters.plant || '');
const lineFilter = ref(props.filters.line_id?.toString() || '');
const autoRefresh = ref(false);
let refreshInterval: number | null = null;

// Modals
const showLineStopModal = ref(false);
const showDetailModal = ref(false);
const selectedReport = ref<Report | null>(null);

// QR Scanner
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

// Forms
const startOperationForm = useForm({
    line_id: null as number | null,
    started_by: '',
    notes: '',
});

const lineStopForm = useForm({
    line_id: null as number | null,
    machine_id: null as number | null,
    problem: '',
    reported_by: '',
});

const filteredLines = computed(() => {
    let filtered = props.lines;
    if (plantFilter.value) {
        filtered = filtered.filter(l => l.plant === plantFilter.value);
    }
    return filtered;
});

const shiftFilter = ref(props.filters.shift?.toString() || '');

const search = () => {
    router.get('/maintenance', {
        search: searchQuery.value,
        status: statusFilter.value,
        plant: plantFilter.value,
        line_id: lineFilter.value,
        shift: shiftFilter.value
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

// ========== QR SCANNER ==========
const startCamera = async () => {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        });
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            await videoRef.value.play();
            isCameraActive.value = true;
            startQrScan();
        }
    } catch (error) {
        scanError.value = 'Tidak dapat mengakses kamera. Pastikan izin kamera sudah diberikan.';
        console.error('Camera error:', error);
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

                if (code) {
                    processQrCode(code.data);
                }
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

            if (scanMode.value === 'start_operation') {
                if (data.data.line.has_running_operation) {
                    alert('Line ini sudah dalam status operasi!');
                    return;
                }
                startOperationForm.line_id = data.data.line.id;
            } else {
                if (!data.data.line.has_running_operation) {
                    alert('Line ini belum memulai operasi. Mulai operasi terlebih dahulu!');
                    return;
                }
                lineStopForm.line_id = data.data.line.id;
                showLineStopModal.value = true;
            }
        } else {
            scanError.value = data.message || 'Line tidak ditemukan';
        }
    } catch (error) {
        scanError.value = 'Gagal memproses QR Code. Coba lagi.';
        console.error('Scan error:', error);
    } finally {
        setTimeout(() => {
            isScanning.value = false;
        }, 1000);
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
                if (selectedReport.value?.id === reportId) {
                    closeDetailModal();
                }
            }
        });
    }
};

const deleteReport = (reportId: number) => {
    if (confirm('Hapus laporan ini?')) {
        router.delete(`/maintenance/${reportId}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedReport.value?.id === reportId) {
                    closeDetailModal();
                }
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
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

onUnmounted(() => {
    stopCamera();
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>

<template>
    <Head title="Monitoring Line Stop Mesin" />
    <AppLayout :breadcrumbs="[
        { title: 'Monitoring Line Stop Mesin', href: '/maintenance' }
    ]">
        <div class="p-4 space-y-4">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Activity class="w-6 h-6 text-blue-600" />
                    Monitoring Line Stop Mesin
                </h1>
                <div class="flex items-center gap-2">
                    <button
                        @click="toggleAutoRefresh"
                        :class="[
                            'flex items-center gap-2 px-3 py-2 rounded-md transition-colors text-sm',
                            autoRefresh
                                ? 'bg-green-600 text-white hover:bg-green-700'
                                : 'bg-gray-600 text-white hover:bg-gray-700'
                        ]"
                    >
                        <RefreshCw :class="['w-4 h-4', autoRefresh ? 'animate-spin' : '']" />
                        {{ autoRefresh ? 'Auto ON' : 'Auto OFF' }}
                    </button>
                    <button
                        @click="openQrModalForLineStop"
                        class="flex items-center gap-2 px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm"
                    >
                        <ScanLine class="w-4 h-4" />
                        Line Stop
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">Total Laporan</div>
                    <div class="text-2xl font-bold">{{ stats.total }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">Dilaporkan</div>
                    <div class="text-2xl font-bold text-yellow-600">{{ stats.dilaporkan }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">Sedang Diperbaiki</div>
                    <div class="text-2xl font-bold text-blue-600">{{ stats.sedang_diperbaiki }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">Selesai</div>
                    <div class="text-2xl font-bold text-green-600">{{ stats.selesai_hari_ini }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">MTTR</div>
                    <div class="text-lg font-bold text-purple-600">
                        {{ stats.mttr || 'N/A' }}
                    </div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">MTBF</div>
                    <div class="text-lg font-bold text-indigo-600">
                        {{ stats.mtbf ? stats.mtbf.toFixed(2) + 'h' : 'N/A' }}
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 items-center">
                <div class="flex-1 min-w-[200px] max-w-md flex gap-2">
                    <input
                        v-model="searchQuery"
                        @keyup.enter="search"
                        type="text"
                        placeholder="Cari line, mesin, nomor laporan..."
                        class="flex-1 rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar text-sm"
                    />
                    <button @click="search" class="p-2 bg-sidebar hover:bg-sidebar-accent rounded-md">
                        <Search class="w-5 h-5" />
                    </button>
                </div>
                <select
                    v-model="statusFilter"
                    @change="search"
                    class="rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar text-sm"
                >
                    <option value="">Semua Status</option>
                    <option value="Dilaporkan">Dilaporkan</option>
                    <option value="Sedang Diperbaiki">Sedang Diperbaiki</option>
                    <option value="Selesai">Selesai</option>
                </select>
                <select
                    v-model="plantFilter"
                    @change="search"
                    class="rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar text-sm"
                >
                    <option value="">Semua Plant</option>
                    <option v-for="plant in plants" :key="plant" :value="plant">{{ plant }}</option>
                </select>
                <select
                    v-model="lineFilter"
                    @change="search"
                    class="rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar text-sm"
                >
                    <option value="">Semua Line</option>
                    <option v-for="line in filteredLines" :key="line.id" :value="line.id">
                        {{ line.line_name }} ({{ line.plant }})
                    </option>
                </select>
                <select
                    v-model="shiftFilter"
                    @change="search"
                    class="rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar text-sm"
                >
                    <option value="">Semua Shift</option>
                    <option v-for="shift in shifts" :key="shift.value" :value="shift.value">
                        {{ shift.label }}
                    </option>
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">No Laporan</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Line</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Mesin Bermasalah</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Shift</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Plant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Masalah</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Waktu Line Stop</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Durasi Repair</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="report in reports.data" :key="report.id" class="hover:bg-gray-50 dark:hover:bg-sidebar-accent">
                                <td class="px-4 py-3">
                                    <span class="font-mono text-sm font-medium">{{ report.report_number }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    {{ report.line.line_name }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    {{ report.machine.machine_name }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['px-2 py-1 rounded-full text-xs font-medium', getShiftBadgeColor(report.shift)]">
                                        {{ report.shift_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ report.line.plant }}</td>
                                <td class="px-4 py-3">
                                    <div class="max-w-xs truncate text-sm">{{ report.problem }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium', getStatusColor(report.status)]">
                                        <component :is="getStatusIcon(report.status)" class="w-3 h-3" />
                                        {{ report.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div v-if="report.line_stopped_at">{{ formatDateTime(report.line_stopped_at) }}</div>
                                    <div class="text-xs text-gray-500">{{ report.reported_by }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="report.repair_duration_formatted" class="font-mono text-sm font-medium text-green-600">
                                        {{ report.repair_duration_formatted }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">-</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <button
                                            v-if="report.status === 'Sedang Diperbaiki'"
                                            @click="completeRepair(report.id)"
                                            class="p-1.5 bg-green-600 text-white rounded hover:bg-green-700"
                                            title="Tandai Selesai"
                                        >
                                            <CheckCircle class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            @click="viewDetail(report)"
                                            class="p-1.5 bg-gray-600 text-white rounded hover:bg-gray-700"
                                            title="Detail"
                                        >
                                            <Eye class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
    <tr v-if="reports.data.length === 0">
        <td colspan="10" class="px-4 py-8 text-center text-gray-500">
            <Activity class="w-12 h-12 mx-auto mb-2 opacity-50" />
            <p>Tidak ada laporan maintenance</p>
        </td>
    </tr>
</tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="reports.last_page > 1" class="flex justify-center gap-1">
                <button
                    v-for="page in reports.last_page"
                    :key="page"
                    @click="router.get('/maintenance', { page, search: searchQuery, status: statusFilter, plant: plantFilter, line_id: lineFilter })"
                    :class="[
                        'px-3 py-1 rounded-md text-sm',
                        page === reports.current_page
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-200 dark:bg-sidebar hover:bg-gray-300'
                    ]"
                >
                    {{ page }}
                </button>
            </div>

            <!-- Modal QR Scanner -->
            <Dialog :open="showQrModal" @update:open="val => !val && closeQrModal()">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {{ scanMode === 'start_operation' ? 'Scan QR - Start Operation' : 'Scan QR - Line Stop' }}
                        </DialogTitle>
                    </DialogHeader>
                    <div class="space-y-4 mt-4">
                        <div class="relative bg-black rounded-lg overflow-hidden" style="aspect-ratio: 4/3;">
                            <video ref="videoRef" class="w-full h-full object-cover" playsinline></video>
                            <canvas ref="canvasRef" class="hidden"></canvas>
                            <div v-if="!isCameraActive" class="absolute inset-0 flex items-center justify-center bg-gray-900">
                                <Loader2 class="w-12 h-12 text-white animate-spin" />
                            </div>
                            <div v-if="isScanning" class="absolute inset-0 flex items-center justify-center bg-green-500/20">
                                <div class="bg-green-600 text-white px-4 py-2 rounded-lg font-semibold">
                                    QR Code Terdeteksi!
                                </div>
                            </div>
                            <div class="absolute inset-0 border-2 border-purple-500 m-12 rounded-lg pointer-events-none"></div>
                        </div>
                        <p v-if="scanError" class="text-red-500 text-sm text-center">{{ scanError }}</p>
                        <div :class="[
                            'border rounded-lg p-3',
                            scanMode === 'start_operation'
                                ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'
                                : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'
                        ]">
                            <div class="flex items-start gap-2">
                                <Camera :class="[
                                    'w-5 h-5 flex-shrink-0 mt-0.5',
                                    scanMode === 'start_operation' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                                ]" />
                                <p :class="[
                                    'text-sm',
                                    scanMode === 'start_operation' ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300'
                                ]">
                                    {{ scanMode === 'start_operation'
                                        ? 'Scan QR Code LINE untuk memulai operasi. Semua mesin di line akan mulai beroperasi.'
                                        : 'Scan QR Code LINE ketika terjadi masalah. Perbaikan akan otomatis dimulai dan MTTR langsung dihitung.'
                                    }}
                                </p>
                            </div>
                        </div>
                        <button @click="closeQrModal" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent">
                            Tutup
                        </button>
                    </div>
                </DialogContent>
            </Dialog>
            <!-- Modal Line Stop -->
            <Dialog :open="showLineStopModal" @update:open="showLineStopModal = $event">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Line Stop - Pilih Mesin Bermasalah</DialogTitle>
                    </DialogHeader>

                    <form @submit.prevent="submitLineStop" class="space-y-4 mt-4">
                        <div v-if="scannedLine" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <AlertCircle class="w-5 h-5 text-red-600 dark:text-red-400" />
                                <span class="font-semibold text-red-700 dark:text-red-300">LINE STOP - Perbaikan Otomatis Dimulai</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-sm mb-3">
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Line:</span>
                                    <span class="font-medium ml-2">{{ scannedLine.line_name }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Plant:</span>
                                    <span class="font-medium ml-2">{{ scannedLine.plant }}</span>
                                </div>
                            </div>
                            <div class="bg-white dark:bg-red-950 rounded p-3">
                                <p class="text-sm text-red-700 dark:text-red-300 font-medium mb-2">
                                    ⚠️ Semua {{ scannedMachines.length }} mesin di line ini akan berhenti!
                                </p>
                                <p class="text-xs text-red-600 dark:text-red-400">
                                    🔧 Timer MTTR akan otomatis dimulai setelah konfirmasi
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">
                                Mesin yang Bermasalah <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="lineStopForm.machine_id"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                                :class="{ 'border-red-500': lineStopForm.errors.machine_id }"
                            >
                                <option :value="null">-- Pilih Mesin yang Bermasalah --</option>
                                <option
                                    v-for="machine in scannedMachines"
                                    :key="machine.id"
                                    :value="machine.id"
                                >
                                    {{ machine.machine_name }} - {{ machine.machine_type }}
                                </option>
                            </select>
                            <p v-if="lineStopForm.errors.machine_id" class="mt-1 text-sm text-red-500">
                                {{ lineStopForm.errors.machine_id }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">
                                Deskripsi Masalah <span class="text-gray-500">(opsional)</span>
                            </label>
                            <textarea
                                v-model="lineStopForm.problem"
                                rows="4"
                                placeholder="Jelaskan masalah yang terjadi..."
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            ></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">
                                Dilaporkan Oleh <span class="text-gray-500">(opsional)</span>
                            </label>
                            <input
                                v-model="lineStopForm.reported_by"
                                type="text"
                                placeholder="Nama pelapor"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            />
                        </div>

                        <div class="flex gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                @click="showLineStopModal = false"
                                :disabled="lineStopForm.processing"
                                class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent disabled:opacity-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="lineStopForm.processing"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50"
                            >
                                <AlertCircle class="w-4 h-4" />
                                {{ lineStopForm.processing ? 'Memproses...' : 'Konfirmasi Line Stop' }}
                            </button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Modal Detail Report -->
            <Dialog :open="showDetailModal" @update:open="showDetailModal = $event">
                <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Detail Laporan Maintenance</DialogTitle>
                    </DialogHeader>

                    <div v-if="selectedReport" class="space-y-4 mt-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Nomor Laporan</div>
                                <div class="text-xl font-bold font-mono">{{ selectedReport.report_number }}</div>
                            </div>
                            <span :class="['inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold', getStatusColor(selectedReport.status)]">
                                <component :is="getStatusIcon(selectedReport.status)" class="w-5 h-5" />
                                {{ selectedReport.status }}
                            </span>
                        </div>

                        <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4">
                            <h3 class="font-semibold mb-3 flex items-center gap-2">
                                <Factory class="w-5 h-5" />
                                Informasi Line & Mesin
                            </h3>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Line</div>
                                    <div class="font-semibold">{{ selectedReport.line.line_name }}</div>
                                    <div class="text-xs text-gray-500">{{ selectedReport.line.line_code }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Plant</div>
                                    <div class="font-semibold">{{ selectedReport.line.plant }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Shift</div>
                                    <span :class="['inline-flex px-2 py-1 rounded-full text-xs font-medium', getShiftBadgeColor(selectedReport.shift)]">
                                        {{ selectedReport.shift_label }}
                                    </span>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Mesin Bermasalah</div>
                                    <div class="font-semibold">{{ selectedReport.machine.machine_name }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Tipe Mesin</div>
                                    <div class="font-semibold">{{ selectedReport.machine.machine_type }}</div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="font-semibold mb-2">Deskripsi Masalah</h3>
                            <div class="p-4 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-md">
                                <p class="text-gray-800 dark:text-gray-200">{{ selectedReport.problem }}</p>
                            </div>
                        </div>

                        <div>
                            <h3 class="font-semibold mb-3">Timeline</h3>
                            <div class="space-y-3">
                                <div class="flex gap-3">
                                    <div class="flex flex-col items-center">
                                        <div class="w-10 h-10 bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-300 rounded-full flex items-center justify-center">
                                            <AlertCircle class="w-5 h-5" />
                                        </div>
                                        <div v-if="selectedReport.started_at || selectedReport.completed_at" class="flex-1 w-0.5 bg-gray-300 dark:bg-gray-700 my-2"></div>
                                    </div>
                                    <div class="flex-1 pb-4">
                                        <div class="font-semibold">Line Stop & Perbaikan Dimulai</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ selectedReport.line_stopped_at ? formatDateTime(selectedReport.line_stopped_at) : formatDateTime(selectedReport.reported_at) }}
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Oleh: {{ selectedReport.reported_by }}</div>
                                        <div class="mt-2 text-xs bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 px-2 py-1 rounded inline-block">
                                            🔧 MTTR Timer Started
                                        </div>
                                    </div>
                                </div>

                                <div v-if="selectedReport.completed_at" class="flex gap-3">
                                    <div class="flex flex-col items-center">
                                        <div class="w-10 h-10 bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-300 rounded-full flex items-center justify-center">
                                            <CheckCircle class="w-5 h-5" />
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-semibold">Perbaikan Selesai & Line Beroperasi</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ formatDateTime(selectedReport.completed_at) }}</div>
                                        <div v-if="selectedReport.repair_duration_formatted" class="mt-2 inline-block px-3 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300 rounded-full text-sm font-medium">
                                            MTTR: {{ selectedReport.repair_duration_formatted }}
                                        </div>
                                        <div v-if="selectedReport.line_stop_duration_formatted" class="mt-2 inline-block px-3 py-1 bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 rounded-full text-sm font-medium ml-2">
                                            Total Line Stop: {{ selectedReport.line_stop_duration_formatted }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <!-- Hanya tombol Selesai jika sedang diperbaiki -->
                            <button
                                v-if="selectedReport.status === 'Sedang Diperbaiki'"
                                @click="completeRepair(selectedReport.id)"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                            >
                                <CheckCircle class="w-4 h-4" />
                                Tandai Selesai
                            </button>
                            <button
                                @click="deleteReport(selectedReport.id)"
                                class="flex items-center justify-center gap-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                            >
                                <Trash2 class="w-4 h-4" />
                                Hapus
                            </button>
                            <button
                                @click="closeDetailModal"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent"
                            >
                                <X class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
