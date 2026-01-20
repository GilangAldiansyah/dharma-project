<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, nextTick, onUnmounted } from 'vue';
import QRCode from 'qrcode';
import jsQR from 'jsqr';
import {
    Search,
    Factory,
    Plus,
    Edit,
    Printer,
    Activity,
    Clock,
    AlertCircle,
    Wrench,
    Trash2,
    PlayCircle,
    Camera,
    Loader2,
    PauseCircle,
    StopCircle,
    RotateCcw,
    ChevronDown,
    ChevronUp,
    CheckCircle,
    History,
    Archive,
    TrendingUp,
    Calendar,
    BarChart3,
} from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';

interface Machine {
    id: number;
    machine_name: string;
    barcode: string;
    machine_type: string;
    total_operation_hours: number;
    total_repair_hours: number;
    total_failures: number;
    mttr_hours: number | null;
    mtbf_hours: number | null;
}

interface LineOperation {
    id: number;
    operation_number: string;
    started_at: string;
    status: string;
    total_pause_minutes: number;
}

interface Line {
    id: number;
    line_code: string;
    line_name: string;
    plant: string;
    qr_code: string;
    status: 'operating' | 'stopped' | 'maintenance' | 'paused';
    last_operation_start: string | null;
    last_line_stop: string | null;
    description: string | null;
    machines_count: number;
    maintenance_reports_count: number;
    operations_count: number;
    average_mtbf: number | null;
    average_mttr: string | null;
    total_line_stops: number;
    active_reports_count: number;
    total_operation_hours: number;
    total_repair_hours: number;
    total_failures: number;
    machines: Machine[];
    current_operation?: LineOperation;
}

interface MaintenanceReport {
    id: number;
    report_number: string;
    problem: string;
    status: string;
    reported_at: string;
    machine: {
        machine_name: string;
        machine_type: string;
    };
}

interface ArchivedPeriod {
    period: string;
    line: {
        id: number;
        line_code: string;
        line_name: string;
        plant: string;
        total_operation_hours: number;
        total_repair_hours: number;
        total_failures: number;
        total_line_stops: number;
        average_mtbf: number | null;
        average_mttr: string | null;
    };
    machines: Machine[];
    reason: string;
}

interface HistoryData {
    current: {
        line: Line;
        period: string;
    };
    history: ArchivedPeriod[];
}

interface SummaryData {
    line: {
        id: number;
        line_code: string;
        line_name: string;
    };
    current_period: {
        operation_hours: number;
        repair_hours: number;
        failures: number;
        mtbf: number | null;
        mttr: string | null;
    };
    total_all_time: {
        operation_hours: number;
        repair_hours: number;
        failures: number;
        line_stops: number;
    };
    periods_count: number;
    archived_periods: Array<{
        period: string;
        operation_hours: number;
        repair_hours: number;
        failures: number;
    }>;
}

interface Props {
    lines: {
        data: Line[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: {
        total_lines: number;
        operating: number;
        stopped: number;
        maintenance: number;
    };
    plants: string[];
    filters: {
        search?: string;
        plant?: string;
        status?: string;
    };
}
const props = defineProps<Props>();

// Filter States
const searchQuery = ref(props.filters.search || '');
const plantFilter = ref(props.filters.plant || '');
const statusFilter = ref(props.filters.status || '');

// Modal States
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showQrModal = ref(false);
const showQrScanModal = ref(false);
const showConfirmActionModal = ref(false);
const showActiveReportsModal = ref(false);
const showResetConfirmModal = ref(false);
const showHistoryModal = ref(false);
const showSummaryModal = ref(false);

// Data States
const selectedLine = ref<Line | null>(null);
const expandedLines = ref<Set<number>>(new Set());
const activeReports = ref<MaintenanceReport[]>([]);
const historyData = ref<HistoryData | null>(null);
const summaryData = ref<SummaryData | null>(null);
const isLoadingHistory = ref(false);
const isLoadingSummary = ref(false);
const expandedHistoryPeriods = ref<Set<number>>(new Set());

// QR Scanner States
const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const isScanning = ref(false);
const scanError = ref('');
const scannedLine = ref<Line | null>(null);
const scannedMachines = ref<Machine[]>([]);
const isCameraActive = ref(false);
const scanMode = ref<'start' | 'line-stop' | 'pause' | 'resume' | 'stop' | 'complete'>('start');
let stream: MediaStream | null = null;
let scanInterval: number | null = null;

// Form States
const actionForm = ref({
    line_id: null as number | null,
    operation_id: null as number | null,
    machine_id: null as number | null,
    report_id: null as number | null,
    user_name: '',
    notes: '',
    problem: '',
});

const resetForm = useForm({
    reason: '',
});

const createForm = useForm({
    line_name: '',
    plant: '',
    description: '',
});

const editForm = useForm({
    line_name: '',
    plant: '',
    description: '',
});
// Search & Filter
const search = () => {
    router.get('/maintenance/lines', {
        search: searchQuery.value,
        plant: plantFilter.value,
        status: statusFilter.value,
    }, { preserveState: true, preserveScroll: true });
};

// Toggle Expand
const toggleLineExpand = (lineId: number) => {
    if (expandedLines.value.has(lineId)) {
        expandedLines.value.delete(lineId);
    } else {
        expandedLines.value.add(lineId);
    }
};

const isLineExpanded = (lineId: number) => {
    return expandedLines.value.has(lineId);
};

const toggleHistoryPeriodExpand = (index: number) => {
    if (expandedHistoryPeriods.value.has(index)) {
        expandedHistoryPeriods.value.delete(index);
    } else {
        expandedHistoryPeriods.value.add(index);
    }
};

const isHistoryPeriodExpanded = (index: number) => {
    return expandedHistoryPeriods.value.has(index);
};

// Format Duration
const formatDuration = (hours: number): string => {
    const h = Math.floor(hours);
    const m = Math.floor((hours - h) * 60);
    const s = Math.floor(((hours - h) * 60 - m) * 60);
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
};

// Status Helpers
const getStatusColor = (status: string) => {
    switch (status) {
        case 'operating': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'stopped': return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        case 'maintenance': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'paused': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'operating': return Activity;
        case 'stopped': return AlertCircle;
        case 'maintenance': return Wrench;
        case 'paused': return PauseCircle;
        default: return Clock;
    }
};

const getStatusText = (status: string) => {
    switch (status) {
        case 'operating': return 'Beroperasi';
        case 'stopped': return 'Berhenti';
        case 'maintenance': return 'Maintenance';
        case 'paused': return 'Pause';
        default: return status;
    }
};

const getScanModeConfig = () => {
    const configs = {
        'start': {
            title: 'Start Operation',
            color: 'green',
            icon: PlayCircle,
            description: 'Scan QR Code LINE untuk memulai operasi. Semua mesin di line akan mulai beroperasi.',
        },
        'line-stop': {
            title: 'Line Stop',
            color: 'red',
            icon: AlertCircle,
            description: 'Scan QR Code LINE untuk line stop. Pilih mesin yang bermasalah setelah scan.',
        },
        'pause': {
            title: 'Pause Operation',
            color: 'blue',
            icon: PauseCircle,
            description: 'Scan QR Code LINE untuk pause operasi (break/istirahat).',
        },
        'resume': {
            title: 'Resume Operation',
            color: 'green',
            icon: PlayCircle,
            description: 'Scan QR Code LINE untuk melanjutkan operasi setelah pause.',
        },
        'stop': {
            title: 'Stop Operation',
            color: 'orange',
            icon: StopCircle,
            description: 'Scan QR Code LINE untuk menghentikan operasi sepenuhnya.',
        },
        'complete': {
            title: 'Complete Maintenance',
            color: 'emerald',
            icon: CheckCircle,
            description: 'Scan QR Code LINE untuk menyelesaikan perbaikan maintenance.',
        },
    };
    return configs[scanMode.value];
};
const openCreateModal = () => {
    createForm.reset();
    showCreateModal.value = true;
};

const openEditModal = (line: Line) => {
    selectedLine.value = line;
    editForm.line_name = line.line_name;
    editForm.plant = line.plant;
    editForm.description = line.description || '';
    showEditModal.value = true;
};

const submitEdit = () => {
    if (!selectedLine.value) return;
    editForm.put(`/maintenance/lines/${selectedLine.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            selectedLine.value = null;
            editForm.reset();
        },
    });
};

const deleteLine = (lineId: number, lineName: string) => {
    if (confirm(`Hapus line "${lineName}"? Data ini tidak dapat dikembalikan.`)) {
        router.delete(`/maintenance/lines/${lineId}`, {
            preserveScroll: true,
        });
    }
};

const openResetConfirmModal = (line: Line) => {
    selectedLine.value = line;
    resetForm.reset();
    showResetConfirmModal.value = true;
};

const submitResetMetrics = async () => {
    if (!selectedLine.value || !resetForm.reason) {
        alert('Alasan reset harus diisi!');
        return;
    }

    if (!confirm(`Reset metrics line "${selectedLine.value.line_name}"? Data lama akan disimpan dalam history.`)) {
        return;
    }

    try {
        const response = await fetch(`/maintenance/lines/${selectedLine.value.id}/reset`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                reason: resetForm.reason,
            }),
        });

        const data = await response.json();

        if (data.success) {
            showResetConfirmModal.value = false;
            resetForm.reset();
            selectedLine.value = null;
            router.reload({ only: ['lines', 'stats'] });
            alert(data.message);
        } else {
            alert(data.message || 'Gagal reset metrics');
        }
    } catch {
        alert('Gagal reset metrics. Coba lagi.');
    }
};
const openHistoryModal = async (line: Line) => {
    selectedLine.value = line;
    showHistoryModal.value = true;
    isLoadingHistory.value = true;
    expandedHistoryPeriods.value.clear();

    try {
        const response = await fetch(`/maintenance/lines/${line.id}/history`);
        const data = await response.json();

        if (data.success) {
            historyData.value = data;
        } else {
            alert(data.message || 'Gagal mengambil data history');
        }
    } catch {
        alert('Gagal mengambil data history');
    } finally {
        isLoadingHistory.value = false;
    }
};

const openSummaryModal = async (line: Line) => {
    selectedLine.value = line;
    showSummaryModal.value = true;
    isLoadingSummary.value = true;

    try {
        const response = await fetch(`/maintenance/lines/${line.id}/summary`);
        const data = await response.json();

        if (data.success) {
            summaryData.value = data;
        } else {
            alert(data.message || 'Gagal mengambil data summary');
        }
    } catch {
        alert('Gagal mengambil data summary');
    } finally {
        isLoadingSummary.value = false;
    }
};

const viewActiveReports = async (line: Line) => {
    selectedLine.value = line;

    try {
        const response = await fetch(`/maintenance/lines/${line.id}/active-reports`);
        const data = await response.json();

        if (data.success) {
            activeReports.value = data.data;
            showActiveReportsModal.value = true;
        } else {
            alert(data.message || 'Gagal mengambil data laporan');
        }
    } catch {
        alert('Gagal mengambil data laporan');
    }
};
const viewQrCode = async (line: Line) => {
    selectedLine.value = line;
    showQrModal.value = true;
    await nextTick();
    await new Promise(resolve => setTimeout(resolve, 150));
    await generateQrCodeForDisplay(line.qr_code);
};

const generateQrCodeForDisplay = async (qrCode: string) => {
    const container = document.getElementById('qrcode-display');
    if (container && qrCode) {
        container.innerHTML = '';
        try {
            const canvas = document.createElement('canvas');
            await QRCode.toCanvas(canvas, qrCode, {
                width: 256,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#FFFFFF'
                }
            });
            container.appendChild(canvas);
        } catch{
        }
    }
};

const printQrCode = () => {
    window.print();
};
const openActionModal = async (mode: typeof scanMode.value, line?: Line) => {
    scanMode.value = mode;

    if (line) {
        scannedLine.value = line;
        actionForm.value.line_id = line.id;

        if (mode === 'pause' || mode === 'resume' || mode === 'stop') {
            actionForm.value.operation_id = line.current_operation?.id ?? null;
        }

        if (mode === 'complete') {
            try {
                const response = await fetch(`/maintenance/lines/${line.id}/active-reports`);
                const data = await response.json();

                if (data.success && data.data.length > 0) {
                    activeReports.value = data.data;
                } else {
                    alert('Tidak ada laporan maintenance aktif di line ini!');
                    return;
                }
            } catch {
                alert('Gagal mengambil data laporan maintenance!');
                return;
            }
        }

        showConfirmActionModal.value = true;
    } else {
        resetScanState();
        showQrScanModal.value = true;
        await startCamera();
    }
};

const resetScanState = () => {
    scanError.value = '';
    scannedLine.value = null;
    scannedMachines.value = [];
    actionForm.value = {
        line_id: null,
        operation_id: null,
        machine_id: null,
        report_id: null,
        user_name: '',
        notes: '',
        problem: '',
    };
};

const closeQrScanModal = () => {
    stopCamera();
    showQrScanModal.value = false;
    resetScanState();
};
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
    } catch {
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
        const response = await fetch('/maintenance/lines/scan-qr', {
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

            if (scanMode.value === 'start') {
                if (data.data.line.has_running_operation) {
                    scanError.value = 'Line ini sudah dalam status operasi!';
                    setTimeout(() => { isScanning.value = false; }, 2000);
                    return;
                }
            } else if (scanMode.value === 'pause' || scanMode.value === 'resume' || scanMode.value === 'stop') {
                if (!data.data.line.has_running_operation) {
                    scanError.value = 'Line tidak memiliki operasi yang sedang berjalan!';
                    setTimeout(() => { isScanning.value = false; }, 2000);
                    return;
                }
                actionForm.value.operation_id = data.data.line.current_operation_id;
            } else if (scanMode.value === 'complete') {
                if (data.data.line.status !== 'maintenance') {
                    scanError.value = 'Line tidak dalam status maintenance!';
                    setTimeout(() => { isScanning.value = false; }, 2000);
                    return;
                }

                try {
                    const reportsResponse = await fetch(`/maintenance/lines/${data.data.line.id}/active-reports`);
                    const reportsData = await reportsResponse.json();

                    if (reportsData.success && reportsData.data.length > 0) {
                        activeReports.value = reportsData.data;
                    } else {
                        scanError.value = 'Tidak ada laporan maintenance aktif di line ini!';
                        setTimeout(() => { isScanning.value = false; }, 2000);
                        return;
                    }
                } catch {
                    scanError.value = 'Gagal mengambil data laporan maintenance!';
                    setTimeout(() => { isScanning.value = false; }, 2000);
                    return;
                }
            }

            stopCamera();
            showQrScanModal.value = false;
            actionForm.value.line_id = data.data.line.id;
            showConfirmActionModal.value = true;

        } else {
            scanError.value = data.message || 'Line tidak ditemukan';
        }
    } catch {
        scanError.value = 'Gagal memproses QR Code. Coba lagi.';
    } finally {
        setTimeout(() => { isScanning.value = false; }, 1000);
    }
};
const submitAction = async () => {
    if (!actionForm.value.line_id) return;

    try {
        let endpoint = '';
        let payload: any = {};

        if (scanMode.value === 'start') {
            endpoint = '/maintenance/operations/start';
            payload = {
                line_id: actionForm.value.line_id,
                started_by: actionForm.value.user_name || 'System',
                notes: actionForm.value.notes,
            };
        } else if (scanMode.value === 'line-stop') {
            if (!actionForm.value.machine_id) {
                alert('Pilih mesin yang bermasalah!');
                return;
            }
            endpoint = '/maintenance/lines/line-stop';
            payload = {
                line_id: actionForm.value.line_id,
                machine_id: actionForm.value.machine_id,
                problem: actionForm.value.problem,
                reported_by: actionForm.value.user_name || 'System',
            };
        } else if (scanMode.value === 'pause') {
            endpoint = `/maintenance/operations/${actionForm.value.operation_id}/pause`;
            payload = {
                paused_by: actionForm.value.user_name || 'System',
            };
        } else if (scanMode.value === 'resume') {
            endpoint = `/maintenance/operations/${actionForm.value.operation_id}/resume`;
            payload = {
                paused_by: actionForm.value.user_name || 'System',
            };
        } else if (scanMode.value === 'stop') {
            endpoint = `/maintenance/operations/${actionForm.value.operation_id}/stop`;
            payload = {
                stopped_by: actionForm.value.user_name || 'System',
                notes: actionForm.value.notes,
            };
        } else if (scanMode.value === 'complete') {
            if (!actionForm.value.report_id) {
                alert('Pilih laporan yang ingin diselesaikan!');
                return;
            }
            endpoint = `/maintenance/lines/reports/${actionForm.value.report_id}/complete`;
            payload = {};
        }

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (data.success) {
            showConfirmActionModal.value = false;
            resetScanState();
            alert(data.message || 'Aksi berhasil dilakukan!');
            router.reload({ only: ['lines', 'stats'] });
        } else {
            alert(data.message || 'Gagal melakukan aksi');
        }
    } catch {
        alert('Gagal melakukan aksi. Coba lagi.');
    }
};

const quickCompleteReport = async (reportId: number) => {
    if (!confirm('Tandai perbaikan selesai? Line akan otomatis beroperasi kembali.')) {
        return;
    }

    try {
        const response = await fetch(`/maintenance/lines/reports/${reportId}/complete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        const data = await response.json();

        if (data.success) {
            showActiveReportsModal.value = false;
            activeReports.value = [];
            alert(data.message || 'Perbaikan selesai! Line otomatis beroperasi kembali.');
            router.reload({ only: ['lines', 'stats'] });
        } else {
            alert(data.message || 'Gagal menyelesaikan perbaikan');
        }
    } catch {
        alert('Gagal menyelesaikan perbaikan. Coba lagi.');
    }
};

onUnmounted(() => {
    stopCamera();
});
</script>
<template>
    <Head title="Line - Maintenance" />
    <AppLayout :breadcrumbs="[
        { title: 'Monitoring Maintenance', href: '/maintenance' },
        { title: 'Line', href: '/maintenance/lines' }
    ]">
        <div class="p-4 space-y-4">
            <div class="flex flex-col gap-3">
                <div class="flex justify-between items-center">
                    <h1 class="text-xl md:text-2xl font-bold flex items-center gap-2">
                        <Factory class="w-5 h-5 md:w-6 md:h-6 text-blue-600" />
                        Line Produksi
                    </h1>
                    <button
                        @click="openCreateModal"
                        class="flex items-center gap-2 px-3 py-2 md:px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"
                    >
                        <Plus class="w-4 h-4" />
                        <span class="hidden sm:inline">Tambah Line</span>
                        <span class="sm:hidden">Tambah</span>
                    </button>
                </div>

                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
                        <button
                            @click="openActionModal('start')"
                            class="flex flex-col items-center gap-2 p-3 md:p-4 bg-green-600 text-white rounded-lg hover:bg-green-700 active:scale-95 transition-transform shadow-md"
                        >
                            <PlayCircle class="w-6 h-6 md:w-8 md:h-8" />
                            <span class="text-xs md:text-sm font-semibold">Start</span>
                        </button>

                        <button
                            @click="openActionModal('pause')"
                            class="flex flex-col items-center gap-2 p-3 md:p-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 active:scale-95 transition-transform shadow-md"
                        >
                            <PauseCircle class="w-6 h-6 md:w-8 md:h-8" />
                            <span class="text-xs md:text-sm font-semibold">Pause</span>
                        </button>

                        <button
                            @click="openActionModal('resume')"
                            class="flex flex-col items-center gap-2 p-3 md:p-4 bg-teal-600 text-white rounded-lg hover:bg-teal-700 active:scale-95 transition-transform shadow-md"
                        >
                            <PlayCircle class="w-6 h-6 md:w-8 md:h-8" />
                            <span class="text-xs md:text-sm font-semibold">Resume</span>
                        </button>

                        <button
                            @click="openActionModal('stop')"
                            class="flex flex-col items-center gap-2 p-3 md:p-4 bg-orange-600 text-white rounded-lg hover:bg-orange-700 active:scale-95 transition-transform shadow-md"
                        >
                            <StopCircle class="w-6 h-6 md:w-8 md:h-8" />
                            <span class="text-xs md:text-sm font-semibold">Stop</span>
                        </button>

                        <button
                            @click="openActionModal('line-stop')"
                            class="flex flex-col items-center gap-2 p-3 md:p-4 bg-red-600 text-white rounded-lg hover:bg-red-700 active:scale-95 transition-transform shadow-md"
                        >
                            <AlertCircle class="w-6 h-6 md:w-8 md:h-8" />
                            <span class="text-xs md:text-sm font-semibold">Line Stop</span>
                        </button>

                        <button
                            @click="openActionModal('complete')"
                            class="flex flex-col items-center gap-2 p-3 md:p-4 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 active:scale-95 transition-transform shadow-md"
                        >
                            <CheckCircle class="w-6 h-6 md:w-8 md:h-8" />
                            <span class="text-xs md:text-sm font-semibold">Complete</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-3 md:p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">Total Line</div>
                    <div class="text-xl md:text-2xl font-bold">{{ stats.total_lines }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-3 md:p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">Beroperasi</div>
                    <div class="text-xl md:text-2xl font-bold text-green-600">{{ stats.operating }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-3 md:p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">Berhenti</div>
                    <div class="text-xl md:text-2xl font-bold text-red-600">{{ stats.stopped }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-3 md:p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">Maintenance</div>
                    <div class="text-xl md:text-2xl font-bold text-yellow-600">{{ stats.maintenance }}</div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row flex-wrap gap-2 items-stretch sm:items-center">
                <div class="flex-1 min-w-[200px] max-w-full sm:max-w-md flex gap-2">
                    <input
                        v-model="searchQuery"
                        @keyup.enter="search"
                        type="text"
                        placeholder="Cari line, kode..."
                        class="flex-1 rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar text-sm"
                    />
                    <button @click="search" class="p-2 bg-sidebar hover:bg-sidebar-accent rounded-md">
                        <Search class="w-5 h-5" />
                    </button>
                </div>
                <select
                    v-model="plantFilter"
                    @change="search"
                    class="rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar text-sm"
                >
                    <option value="">Semua Plant</option>
                    <option v-for="plant in plants" :key="plant" :value="plant">{{ plant }}</option>
                </select>
                <select
                    v-model="statusFilter"
                    @change="search"
                    class="rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar text-sm"
                >
                    <option value="">Semua Status</option>
                    <option value="operating">Beroperasi</option>
                    <option value="stopped">Berhenti</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="paused">Pause</option>
                </select>
            </div>
            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent">
                            <tr>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Expand</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Kode Line</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Nama Line</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Plant</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Mesin</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Operation</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Repair</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">MTTR</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">MTBF</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Failures</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Control</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">QR</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <template v-for="line in lines.data" :key="line.id">
                                <tr class="hover:bg-gray-50 dark:hover:bg-sidebar-accent">
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            @click="toggleLineExpand(line.id)"
                                            class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded"
                                        >
                                            <ChevronDown v-if="!isLineExpanded(line.id)" class="w-4 h-4" />
                                            <ChevronUp v-else class="w-4 h-4" />
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-mono text-sm font-medium">{{ line.line_code }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="font-medium">{{ line.line_name }}</div>
                                        <div v-if="line.description" class="text-xs text-gray-500">{{ line.description }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center">{{ line.plant }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="['inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium', getStatusColor(line.status)]">
                                            <component :is="getStatusIcon(line.status)" class="w-3 h-3" />
                                            {{ getStatusText(line.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-medium">{{ line.machines_count }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-mono text-sm text-blue-600 dark:text-blue-400 font-semibold">
                                                {{ formatDuration(line.total_operation_hours) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-mono text-sm text-orange-600 dark:text-orange-400 font-semibold">
                                                {{ formatDuration(line.total_repair_hours) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span v-if="line.average_mttr" class="font-mono text-sm text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/20 px-2 py-1 rounded">
                                            {{ line.average_mttr }}
                                        </span>
                                        <span v-else class="text-xs text-gray-400">-</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span v-if="line.average_mtbf" class="font-mono text-sm text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 px-2 py-1 rounded">
                                            {{ formatDuration(line.average_mtbf) }}
                                        </span>
                                        <span v-else class="text-xs text-gray-400">-</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex flex-col gap-1 items-center">
                                            <span class="text-sm font-medium">{{ line.total_failures }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1 items-center">
                                            <button
                                                v-if="line.active_reports_count > 0"
                                                @click="viewActiveReports(line)"
                                                class="w-full px-2 py-1 text-xs font-medium text-white dark:text-red-400 bg-red-600 dark:bg-red-900/20 hover:bg-red-700 dark:hover:bg-red-900/30 rounded-md border border-red-200 dark:border-red-800 transition-colors cursor-pointer flex items-center justify-center gap-1"
                                                title="Klik untuk melihat dan menyelesaikan perbaikan"
                                            >
                                                <AlertCircle class="w-3 h-3" />
                                                Problem
                                            </button>
                                            <template v-if="line.current_operation">
                                                <button
                                                    v-if="line.status === 'operating'"
                                                    @click="openActionModal('pause', line)"
                                                    class="w-full px-2 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs flex items-center justify-center gap-1"
                                                    title="Pause (Break/Istirahat)"
                                                >
                                                    <PauseCircle class="w-3 h-3" />
                                                    Pause
                                                </button>
                                                <button
                                                    v-else-if="line.status === 'paused'"
                                                    @click="openActionModal('resume', line)"
                                                    class="w-full px-2 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs flex items-center justify-center gap-1"
                                                    title="Resume"
                                                >
                                                    <PlayCircle class="w-3 h-3" />
                                                    Resume
                                                </button>
                                                <button
                                                    v-if="line.status !== 'maintenance'"
                                                    @click="openActionModal('stop', line)"
                                                    class="w-full px-2 py-1 bg-orange-600 text-white rounded hover:bg-orange-700 text-xs flex items-center justify-center gap-1"
                                                    title="Stop Operasi"
                                                >
                                                    <StopCircle class="w-3 h-3" />
                                                    Stop
                                                </button>
                                            </template>
                                            <button
                                                v-else-if="line.status !== 'maintenance'"
                                                @click="openActionModal('start', line)"
                                                class="w-full px-2 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs flex items-center justify-center gap-1"
                                                title="Start Operation"
                                            >
                                                <PlayCircle class="w-3 h-3" />
                                                Start
                                            </button>
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 text-center">
                                        <button
                                            @click="viewQrCode(line)"
                                            class="p-1 text-gray-500 hover:text-blue-600"
                                            title="Lihat & Cetak QR Code"
                                        >
                                            <Printer class="w-4 h-4" />
                                        </button>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-1 justify-center">
                                            <button
                                                @click="openEditModal(line)"
                                                class="p-1.5 bg-yellow-600 text-white rounded hover:bg-yellow-700"
                                                title="Edit"
                                            >
                                                <Edit class="w-4 h-4" />
                                            </button>
                                            <button
                                                @click="openHistoryModal(line)"
                                                class="p-1.5 bg-purple-600 text-white rounded hover:bg-purple-700"
                                                title="History"
                                            >
                                                <History class="w-4 h-4" />
                                            </button>
                                            <button
                                                @click="openSummaryModal(line)"
                                                class="p-1.5 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                                                title="Summary"
                                            >
                                                <BarChart3 class="w-4 h-4" />
                                            </button>
                                            <button
                                                @click="openResetConfirmModal(line)"
                                                class="p-1.5 bg-orange-600 text-white rounded hover:bg-orange-700"
                                                title="Reset Metrics"
                                            >
                                                <RotateCcw class="w-4 h-4" />
                                            </button>
                                            <button
                                                @click="deleteLine(line.id, line.line_name)"
                                                class="p-1.5 bg-red-600 text-white rounded hover:bg-red-700"
                                                title="Hapus"
                                            >
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="isLineExpanded(line.id)" class="bg-gray-50 dark:bg-gray-800">
                                    <td colspan="14" class="py-2">
                                        <div class="px-2">
                                            <div v-if="line.machines.length > 0" class="overflow-x-auto">
                                                <table class="w-full text-sm border border-gray-300 dark:border-gray-600">
                                                    <thead class="bg-gray-100 dark:bg-gray-700">
                                                        <tr>
                                                            <th class="px-3 py-2 text-left text-xs font-medium">Nama Mesin</th>
                                                            <th class="px-3 py-2 text-left text-xs font-medium">Tipe</th>
                                                            <th class="px-3 py-2 text-center text-xs font-medium">Barcode</th>
                                                            <th class="px-3 py-2 text-center text-xs font-medium">Operation</th>
                                                            <th class="px-3 py-2 text-center text-xs font-medium">Repair</th>
                                                            <th class="px-3 py-2 text-center text-xs font-medium">MTTR</th>
                                                            <th class="px-3 py-2 text-center text-xs font-medium">MTBF</th>
                                                            <th class="px-3 py-2 text-center text-xs font-medium">Failures</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                        <tr v-for="machine in line.machines" :key="machine.id">
                                                            <td class="px-3 py-2 font-medium">{{ machine.machine_name }}</td>
                                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ machine.machine_type }}</td>
                                                            <td class="px-3 py-2 text-center">
                                                                <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                                                                    {{ machine.barcode }}
                                                                </span>
                                                            </td>
                                                            <td class="px-3 py-2 text-center">
                                                                <div class="flex flex-col gap-1">
                                                                    <span class="font-mono text-blue-600 dark:text-blue-400 font-semibold">
                                                                        {{ formatDuration(machine.total_operation_hours) }}
                                                                    </span>
                                                                </div>
                                                            </td>
                                                            <td class="px-3 py-2 text-center">
                                                                <div class="flex flex-col gap-1">
                                                                    <span class="font-mono text-orange-600 dark:text-orange-400 font-semibold">
                                                                        {{ formatDuration(machine.total_repair_hours) }}
                                                                    </span>
                                                                </div>
                                                            </td>
                                                            <td class="px-3 py-2 text-center">
                                                                <span v-if="machine.mttr_hours" class="font-mono text-purple-600 dark:text-purple-400">
                                                                    {{ formatDuration(machine.mttr_hours) }}
                                                                </span>
                                                                <span v-else class="text-xs text-gray-400">-</span>
                                                            </td>
                                                            <td class="px-3 py-2 text-center">
                                                                <span v-if="machine.mtbf_hours" class="font-mono text-indigo-600 dark:text-indigo-400">
                                                                    {{ formatDuration(machine.mtbf_hours) }}
                                                                </span>
                                                                <span v-else class="text-xs text-gray-400">-</span>
                                                            </td>
                                                            <td class="px-3 py-2 text-center">
                                                                <span class="font-medium text-red-600 dark:text-red-400">
                                                                    {{ machine.total_failures }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div v-else class="text-center py-4 text-gray-500">
                                                <Wrench class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                                <p class="text-sm">Belum ada mesin di line ini</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="lines.data.length === 0">
                                <td colspan="14" class="px-4 py-8 text-center text-gray-500">
                                    <Factory class="w-12 h-12 mx-auto mb-2 opacity-50" />
                                    <p>Tidak ada data line</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="lines.last_page > 1" class="flex justify-center gap-1">
                <button
                    v-for="page in lines.last_page"
                    :key="page"
                    @click="router.get('/maintenance/lines', { page, search: searchQuery, plant: plantFilter, status: statusFilter })"
                    :class="[
                        'px-3 py-1 rounded-md text-sm',
                        page === lines.current_page
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-200 dark:bg-sidebar hover:bg-gray-300'
                    ]"
                >
                    {{ page }}
                </button>
            </div>
            <Dialog :open="showResetConfirmModal" @update:open="showResetConfirmModal = $event">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <RotateCcw class="w-5 h-5 text-orange-600" />
                            Reset Matriks
                        </DialogTitle>
                        <DialogDescription v-if="selectedLine">
                            {{ selectedLine.line_name }} - {{ selectedLine.line_code }}
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submitResetMetrics" class="space-y-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">
                                Alasan Reset <span class="text-red-600">*</span>
                            </label>
                            <textarea
                                v-model="resetForm.reason"
                                rows="4"
                                placeholder="Jelaskan alasan reset metrics (contoh: Pergantian periode, evaluasi, Start tahun baru, dll)"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            ></textarea>
                        </div>

                        <div class="flex gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                @click="showResetConfirmModal = false"
                                class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700"
                            >
                                <RotateCcw class="w-4 h-4" />
                                Reset Metrics
                            </button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog :open="showHistoryModal" @update:open="showHistoryModal = $event">
                <DialogContent class="!max-w-6xl max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <History class="w-5 h-5 text-purple-600" />
                            History Data Line
                        </DialogTitle>
                        <DialogDescription v-if="selectedLine">
                            {{ selectedLine.line_name }} - {{ selectedLine.line_code }}
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="isLoadingHistory" class="flex items-center justify-center py-12">
                        <Loader2 class="w-8 h-8 animate-spin text-purple-600" />
                    </div>

                    <div v-else-if="historyData && selectedLine" class="mt-4 space-y-4">
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <Activity class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                <h3 class="font-semibold text-blue-800 dark:text-blue-300">Periode Saat Ini (Aktif)</h3>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                    <div class="text-xs text-gray-600 dark:text-gray-400">Operation</div>
                                    <div class="text-lg font-semibold text-blue-600">
                                        {{ formatDuration(selectedLine.total_operation_hours) }}
                                    </div>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                    <div class="text-xs text-gray-600 dark:text-gray-400">Repair</div>
                                    <div class="text-lg font-semibold text-orange-600">
                                        {{ formatDuration(selectedLine.total_repair_hours) }}
                                    </div>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                    <div class="text-xs text-gray-600 dark:text-gray-400">Failures</div>
                                    <div class="text-lg font-semibold text-red-600">
                                        {{ selectedLine.total_failures }}
                                    </div>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                    <div class="text-xs text-gray-600 dark:text-gray-400">MTBF</div>
                                    <div class="text-lg font-semibold text-indigo-600">
                                        {{ selectedLine.average_mtbf ? formatDuration(selectedLine.average_mtbf) : '-' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="historyData.history.length > 0">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                                <Archive class="w-5 h-5 text-gray-600" />
                                Periode Sebelumnya ({{ historyData.history.length }} Periode)
                            </h3>
                            <div class="space-y-3">
                                <div
                                    v-for="(period, index) in historyData.history"
                                    :key="index"
                                    class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden"
                                >
                                    <div
                                        @click="toggleHistoryPeriodExpand(index)"
                                        class="bg-gray-50 dark:bg-gray-800 p-4 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-750 transition-colors"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-3">
                                                <Calendar class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                                                <div>
                                                    <div class="font-semibold text-gray-800 dark:text-gray-200">
                                                        {{ period.period }}
                                                    </div>
                                                    <div v-if="period.reason" class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                        Alasan: {{ period.reason }}
                                                    </div>
                                                </div>
                                            </div>
                                            <ChevronDown
                                                v-if="!isHistoryPeriodExpanded(index)"
                                                class="w-5 h-5 text-gray-500"
                                            />
                                            <ChevronUp
                                                v-else
                                                class="w-5 h-5 text-gray-500"
                                            />
                                        </div>
                                        <div class="grid grid-cols-4 gap-3 mt-3">
                                            <div>
                                                <div class="text-xs text-gray-600 dark:text-gray-400">Operation</div>
                                                <div class="text-sm font-semibold text-blue-600">
                                                    {{ formatDuration(period.line.total_operation_hours) }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-600 dark:text-gray-400">Repair</div>
                                                <div class="text-sm font-semibold text-orange-600">
                                                    {{ formatDuration(period.line.total_repair_hours) }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-600 dark:text-gray-400">Failures</div>
                                                <div class="text-sm font-semibold text-red-600">
                                                    {{ period.line.total_failures }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-600 dark:text-gray-400">MTBF</div>
                                                <div class="text-sm font-semibold text-indigo-600">
                                                    {{ period.line.average_mtbf ? formatDuration(period.line.average_mtbf) : '-' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="isHistoryPeriodExpanded(index)" class="p-4 bg-white dark:bg-gray-900">
                                        <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-3">
                                            Mesin ({{ period.machines.length }})
                                        </h4>
                                        <div v-if="period.machines.length > 0" class="overflow-x-auto">
                                            <table class="w-full text-sm">
                                                <thead class="bg-gray-100 dark:bg-gray-800">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium">Nama Mesin</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium">Tipe</th>
                                                        <th class="px-3 py-2 text-center text-xs font-medium">Operation</th>
                                                        <th class="px-3 py-2 text-center text-xs font-medium">Repair</th>
                                                        <th class="px-3 py-2 text-center text-xs font-medium">MTTR</th>
                                                        <th class="px-3 py-2 text-center text-xs font-medium">MTBF</th>
                                                        <th class="px-3 py-2 text-center text-xs font-medium">Failures</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                    <tr v-for="machine in period.machines" :key="machine.id">
                                                        <td class="px-3 py-2 font-medium">{{ machine.machine_name }}</td>
                                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ machine.machine_type }}</td>
                                                        <td class="px-3 py-2 text-center text-blue-600 dark:text-blue-400">
                                                            {{ formatDuration(machine.total_operation_hours) }}
                                                        </td>
                                                        <td class="px-3 py-2 text-center text-orange-600 dark:text-orange-400">
                                                            {{ formatDuration(machine.total_repair_hours) }}
                                                        </td>
                                                        <td class="px-3 py-2 text-center text-purple-600 dark:text-purple-400">
                                                            {{ machine.mttr_hours ? formatDuration(machine.mttr_hours) : '-' }}
                                                        </td>
                                                        <td class="px-3 py-2 text-center text-indigo-600 dark:text-indigo-400">
                                                            {{ machine.mtbf_hours ? formatDuration(machine.mtbf_hours) : '-' }}
                                                        </td>
                                                        <td class="px-3 py-2 text-center text-red-600 dark:text-red-400">
                                                            {{ machine.total_failures }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div v-else class="text-center py-4 text-gray-500">
                                            <Wrench class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                            <p class="text-sm">Tidak ada data mesin</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-8 text-gray-500">
                            <Archive class="w-12 h-12 mx-auto mb-3 opacity-50" />
                            <p class="text-sm">Belum ada history periode sebelumnya</p>
                        </div>

                        <div class="border-t pt-3">
                            <button
                                @click="showHistoryModal = false"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
            <Dialog :open="showSummaryModal" @update:open="showSummaryModal = $event">
                <DialogContent class="!max-w-4xl max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <BarChart3 class="w-5 h-5 text-indigo-600" />
                            Summary Keseluruhan
                        </DialogTitle>
                        <DialogDescription v-if="selectedLine">
                            {{ selectedLine.line_name }} - {{ selectedLine.line_code }}
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="isLoadingSummary" class="flex items-center justify-center py-12">
                        <Loader2 class="w-8 h-8 animate-spin text-indigo-600" />
                    </div>

                    <div v-else-if="summaryData" class="mt-4 space-y-4">
                        <div class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-6">
                            <div class="flex items-center gap-2 mb-4">
                                <TrendingUp class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                                <h3 class="text-lg font-semibold text-indigo-800 dark:text-indigo-300">Total Keseluruhan</h3>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">Total Operation</div>
                                    <div class="text-2xl font-bold text-blue-600">
                                        {{ formatDuration(summaryData.total_all_time.operation_hours) }}
                                    </div>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">Total Repair</div>
                                    <div class="text-2xl font-bold text-orange-600">
                                        {{ formatDuration(summaryData.total_all_time.repair_hours) }}
                                    </div>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">Total Failures</div>
                                    <div class="text-2xl font-bold text-red-600">
                                        {{ summaryData.total_all_time.failures }}
                                    </div>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">Total Periode</div>
                                    <div class="text-2xl font-bold text-purple-600">
                                        {{ summaryData.periods_count }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                                    <Activity class="w-5 h-5 text-blue-600" />
                                    Periode Saat Ini
                                </h4>
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Operation:</span>
                                        <span class="font-semibold text-blue-600">
                                            {{ formatDuration(summaryData.current_period.operation_hours) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Repair:</span>
                                        <span class="font-semibold text-orange-600">
                                            {{ formatDuration(summaryData.current_period.repair_hours) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Failures:</span>
                                        <span class="font-semibold text-red-600">
                                            {{ summaryData.current_period.failures }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">MTBF:</span>
                                        <span class="font-semibold text-indigo-600">
                                            {{ summaryData.current_period.mtbf ? formatDuration(summaryData.current_period.mtbf) : '-' }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">MTTR:</span>
                                        <span class="font-semibold text-purple-600">
                                            {{ summaryData.current_period.mttr || '-' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                                    <Archive class="w-5 h-5 text-purple-600" />
                                    Ringkasan Periode Lama
                                </h4>
                                <div v-if="summaryData.archived_periods.length > 0" class="space-y-3 max-h-64 overflow-y-auto">
                                    <div
                                        v-for="(period, index) in summaryData.archived_periods"
                                        :key="index"
                                        class="p-3 bg-gray-50 dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700"
                                    >
                                        <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                            {{ period.period }}
                                        </div>
                                        <div class="grid grid-cols-3 gap-2 text-xs">
                                            <div>
                                                <div class="text-gray-500">Operation</div>
                                                <div class="font-semibold text-blue-600">
                                                    {{ formatDuration(period.operation_hours) }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-gray-500">Repair</div>
                                                <div class="font-semibold text-orange-600">
                                                    {{ formatDuration(period.repair_hours) }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-gray-500">Failures</div>
                                                <div class="font-semibold text-red-600">
                                                    {{ period.failures }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-4 text-gray-500">
                                    <p class="text-sm">Belum ada periode sebelumnya</p>
                                </div>
                            </div>
                        </div>

                        <div class="border-t pt-3">
                            <button
                                @click="showSummaryModal = false"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
            <Dialog :open="showQrScanModal" @update:open="val => !val && closeQrScanModal()">
                <DialogContent class="max-w-lg sm:max-w-xl w-[95vw] sm:w-full">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2 text-base sm:text-lg">
                            <component :is="getScanModeConfig().icon" class="w-5 h-5" />
                            Scan QR - {{ getScanModeConfig().title }}
                        </DialogTitle>
                    </DialogHeader>
                    <div class="space-y-3 sm:space-y-4 mt-4">
                        <div class="relative bg-black rounded-lg overflow-hidden" style="aspect-ratio: 4/3;">
                            <video ref="videoRef" class="w-full h-full object-cover" playsinline></video>
                            <canvas ref="canvasRef" class="hidden"></canvas>
                            <div v-if="!isCameraActive" class="absolute inset-0 flex items-center justify-center bg-gray-900">
                                <div class="text-center">
                                    <Loader2 class="w-10 h-10 sm:w-12 sm:h-12 text-white animate-spin mx-auto mb-2" />
                                    <p class="text-white text-sm">Membuka kamera...</p>
                                </div>
                            </div>
                            <div v-if="isScanning" class="absolute inset-0 flex items-center justify-center bg-green-500/20">
                                <div class="bg-green-600 text-white px-3 py-2 sm:px-4 rounded-lg font-semibold text-sm sm:text-base">
                                    ✓ QR Code Terdeteksi!
                                </div>
                            </div>
                            <div class="absolute inset-0 border-2 sm:border-4 border-green-500 m-8 sm:m-12 rounded-lg pointer-events-none">
                                <div class="absolute top-0 left-0 w-6 h-6 sm:w-8 sm:h-8 border-t-4 border-l-4 border-green-500 -mt-1 -ml-1"></div>
                                <div class="absolute top-0 right-0 w-6 h-6 sm:w-8 sm:h-8 border-t-4 border-r-4 border-green-500 -mt-1 -mr-1"></div>
                                <div class="absolute bottom-0 left-0 w-6 h-6 sm:w-8 sm:h-8 border-b-4 border-l-4 border-green-500 -mb-1 -ml-1"></div>
                                <div class="absolute bottom-0 right-0 w-6 h-6 sm:w-8 sm:h-8 border-b-4 border-r-4 border-green-500 -mb-1 -mr-1"></div>
                            </div>
                        </div>
                        <p v-if="scanError" class="text-red-500 text-sm text-center font-medium bg-red-50 dark:bg-red-900/20 p-2 rounded">
                            ⚠️ {{ scanError }}
                        </p>
                        <div :class="[
                            'border rounded-lg p-3',
                            `bg-${getScanModeConfig().color}-50 dark:bg-${getScanModeConfig().color}-900/20 border-${getScanModeConfig().color}-200 dark:border-${getScanModeConfig().color}-800`
                        ]">
                            <div class="flex items-start gap-2">
                                <Camera :class="[
                                    'w-5 h-5 flex-shrink-0 mt-0.5',
                                    `text-${getScanModeConfig().color}-600 dark:text-${getScanModeConfig().color}-400`
                                ]" />
                                <p :class="[
                                    'text-xs sm:text-sm',
                                    `text-${getScanModeConfig().color}-800 dark:text-${getScanModeConfig().color}-300`
                                ]">
                                    {{ getScanModeConfig().description }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="closeQrScanModal"
                            class="w-full px-4 py-3 sm:py-2 text-base sm:text-sm border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent active:scale-95 transition-transform font-medium"
                        >
                            Tutup
                        </button>
                    </div>
                </DialogContent>
            </Dialog>
            <Dialog :open="showConfirmActionModal" @update:open="showConfirmActionModal = $event">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <component :is="getScanModeConfig().icon" class="w-5 h-5" />
                            Konfirmasi {{ getScanModeConfig().title }}
                        </DialogTitle>
                    </DialogHeader>

                    <form @submit.prevent="submitAction" class="space-y-4 mt-4">
                        <div v-if="scannedLine" :class="[
                            'border rounded-lg p-4',
                            `bg-${getScanModeConfig().color}-50 dark:bg-${getScanModeConfig().color}-900/20 border-${getScanModeConfig().color}-200 dark:border-${getScanModeConfig().color}-800`
                        ]">
                            <div class="flex items-center gap-2 mb-2">
                                <Factory :class="[
                                    'w-5 h-5',
                                    `text-${getScanModeConfig().color}-600 dark:text-${getScanModeConfig().color}-400`
                                ]" />
                                <span :class="[
                                    'font-semibold',
                                    `text-${getScanModeConfig().color}-700 dark:text-${getScanModeConfig().color}-300`
                                ]">Line Hasil Scan</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Kode:</span>
                                    <span class="font-mono font-medium ml-2">{{ scannedLine.line_code }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Nama:</span>
                                    <span class="font-medium ml-2">{{ scannedLine.line_name }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Plant:</span>
                                    <span class="font-medium ml-2">{{ scannedLine.plant }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Status:</span>
                                    <span class="font-medium ml-2">{{ getStatusText(scannedLine.status) }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="scanMode === 'complete' && activeReports.length > 0">
                            <label class="block text-sm font-medium mb-2">
                                Pilih Laporan yang Selesai <span class="text-red-600">*</span>
                            </label>
                            <div class="space-y-2 max-h-60 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-2">
                                <label
                                    v-for="report in activeReports"
                                    :key="report.id"
                                    :class="[
                                        'flex items-start gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all',
                                        actionForm.report_id === report.id
                                            ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                            : 'border-gray-200 dark:border-gray-700 hover:border-emerald-300 dark:hover:border-emerald-700'
                                    ]"
                                >
                                    <input
                                        type="radio"
                                        :value="report.id"
                                        v-model="actionForm.report_id"
                                        class="mt-1"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-mono text-sm font-semibold text-blue-600 dark:text-blue-400">
                                                {{ report.report_number }}
                                            </span>
                                            <span :class="[
                                                'px-2 py-0.5 rounded-full text-xs font-medium',
                                                report.status === 'Sedang Diperbaiki'
                                                    ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300'
                                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
                                            ]">
                                                {{ report.status }}
                                            </span>
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                                            <Wrench class="w-3 h-3 inline mr-1" />
                                            {{ report.machine.machine_name }} - {{ report.machine.machine_type }}
                                        </div>
                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                            <strong>Masalah:</strong> {{ report.problem }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            Dilaporkan: {{ report.reported_at }}
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div v-if="scanMode === 'line-stop'">
                            <label class="block text-sm font-medium mb-2">
                                Mesin Bermasalah <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="actionForm.machine_id"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            >
                                <option value="">Pilih Mesin</option>
                                <option v-for="machine in scannedMachines" :key="machine.id" :value="machine.id">
                                    {{ machine.machine_name }} ({{ machine.machine_type }})
                                </option>
                            </select>
                        </div>

                        <div v-if="scanMode === 'line-stop'">
                            <label class="block text-sm font-medium mb-2">
                                Deskripsi Masalah
                            </label>
                            <textarea
                                v-model="actionForm.problem"
                                rows="3"
                                placeholder="Jelaskan masalah yang terjadi..."
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            ></textarea>
                        </div>

                        <div v-if="scanMode !== 'complete'">
                            <label class="block text-sm font-medium mb-2">
                                {{ scanMode === 'line-stop' ? 'Dilaporkan Oleh' : 'Nama Operator' }} <span class="text-gray-500">(opsional)</span>
                            </label>
                            <input
                                v-model="actionForm.user_name"
                                type="text"
                                :placeholder="scanMode === 'line-stop' ? 'Nama pelapor' : 'Nama operator'"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            />
                        </div>

                        <div v-if="scanMode === 'start' || scanMode === 'stop'">
                            <label class="block text-sm font-medium mb-2">
                                Catatan <span class="text-gray-500">(opsional)</span>
                            </label>
                            <textarea
                                v-model="actionForm.notes"
                                rows="2"
                                placeholder="Catatan tambahan..."
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            ></textarea>
                        </div>

                        <div :class="[
                            'border rounded-lg p-3',
                            scanMode === 'line-stop' ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800' :
                            scanMode === 'complete' ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800' :
                            'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'
                        ]">
                            <p :class="[
                                'text-sm',
                                scanMode === 'line-stop' ? 'text-yellow-800 dark:text-yellow-300' :
                                scanMode === 'complete' ? 'text-emerald-800 dark:text-emerald-300' :
                                'text-blue-800 dark:text-blue-300'
                            ]">
                                <template v-if="scanMode === 'start'">
                                    ℹ️ Setelah start operation, sistem akan mencatat waktu mulai operasi untuk perhitungan MTBF.
                                </template>
                                <template v-else-if="scanMode === 'line-stop'">
                                    ⚠️ Line stop akan menghentikan operasi dan mencatat waktu kerusakan untuk perhitungan MTTR.
                                </template>
                                <template v-else-if="scanMode === 'pause'">
                                    ℹ️ Pause akan menghentikan sementara operasi (untuk break/istirahat). Waktu pause tidak dihitung dalam operasi.
                                </template>
                                <template v-else-if="scanMode === 'resume'">
                                    ✅ Resume akan melanjutkan operasi yang di-pause. Timer operasi akan kembali berjalan.
                                </template>
                                <template v-else-if="scanMode === 'stop'">
                                    ⚠️ Stop akan menghentikan operasi sepenuhnya. Sistem akan menghitung total durasi operasi.
                                </template>
                                <template v-else-if="scanMode === 'complete'">
                                    ✅ Setelah complete, line akan otomatis kembali beroperasi jika tidak ada laporan maintenance aktif lainnya.
                                </template>
                            </p>
                        </div>

                        <div class="flex gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                @click="showConfirmActionModal = false"
                                class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :class="[
                                    'flex-1 flex items-center justify-center gap-2 px-4 py-2 text-white rounded-md',
                                    scanMode === 'start' ? 'bg-green-600 hover:bg-green-700' :
                                    scanMode === 'line-stop' ? 'bg-red-600 hover:bg-red-700' :
                                    scanMode === 'pause' ? 'bg-blue-600 hover:bg-blue-700' :
                                    scanMode === 'stop' ? 'bg-orange-600 hover:bg-orange-700' :
                                    'bg-emerald-600 hover:bg-emerald-700'
                                ]"
                            >
                                <component :is="getScanModeConfig().icon" class="w-4 h-4" />
                                {{ getScanModeConfig().title }}
                            </button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog :open="showActiveReportsModal" @update:open="showActiveReportsModal = $event">
                <DialogContent class="max-w-3xl max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Laporan Maintenance Aktif</DialogTitle>
                        <DialogDescription v-if="selectedLine">
                            {{ selectedLine.line_name }} - {{ selectedLine.line_code }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="mt-4 space-y-4">
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3">
                            <p class="text-sm text-yellow-800 dark:text-yellow-300 flex items-center gap-2">
                                <AlertCircle class="w-4 h-4" />
                                Klik tombol "Selesai" untuk menandai perbaikan selesai. Line akan otomatis beroperasi kembali.
                            </p>
                        </div>

                        <div v-if="activeReports.length > 0" class="space-y-3">
                            <div
                                v-for="report in activeReports"
                                :key="report.id"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 space-y-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-sm font-semibold text-blue-600 dark:text-blue-400">
                                                {{ report.report_number }}
                                            </span>
                                            <span :class="[
                                                'px-2 py-0.5 rounded-full text-xs font-medium',
                                                report.status === 'Sedang Diperbaiki'
                                                    ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300'
                                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
                                            ]">
                                                {{ report.status }}
                                            </span>
                                        </div>

                                        <div class="text-sm">
                                            <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                                <Wrench class="w-4 h-4" />
                                                <span class="font-medium">{{ report.machine.machine_name }}</span>
                                            </div>
                                        </div>

                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                            <strong>Masalah:</strong> {{ report.problem }}
                                        </div>

                                        <div class="text-xs text-gray-500">
                                            Dilaporkan: {{ report.reported_at }}
                                        </div>
                                    </div>

                                    <button
                                        @click="quickCompleteReport(report.id)"
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center gap-2 text-sm whitespace-nowrap transition-colors"
                                    >
                                        <CheckCircle class="w-4 h-4" />
                                        Selesai
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-8 text-gray-500">
                            <Activity class="w-12 h-12 mx-auto mb-3 opacity-50" />
                            <p class="text-sm">Tidak ada laporan maintenance aktif</p>
                        </div>

                        <div class="border-t pt-3 flex gap-2">
                            <button
                                @click="router.visit('/maintenance')"
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center justify-center gap-2"
                            >
                                <Activity class="w-4 h-4" />
                                Buka Halaman Monitoring
                            </button>
                            <button
                                @click="showActiveReportsModal = false"
                                class="px-6 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
            <Dialog :open="showEditModal" @update:open="showEditModal = $event">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit Line</DialogTitle>
                        <DialogDescription>
                            Ubah informasi line produksi
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submitEdit" class="space-y-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">
                                Nama Line <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="editForm.line_name"
                                type="text"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">
                                Plant <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="editForm.plant"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            >
                                <option value="">Pilih Plant</option>
                                <option v-for="plant in plants" :key="plant" :value="plant">{{ plant }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">
                                Deskripsi <span class="text-gray-500">(opsional)</span>
                            </label>
                            <textarea
                                v-model="editForm.description"
                                rows="2"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            ></textarea>
                        </div>

                        <div class="flex gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                @click="showEditModal = false"
                                class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="editForm.processing"
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog :open="showQrModal" @update:open="showQrModal = $event">
                <DialogContent class="max-w-md print:max-w-full print:shadow-none">
                    <DialogHeader class="print:hidden">
                        <DialogTitle>QR Code Line</DialogTitle>
                        <DialogDescription v-if="selectedLine">
                            {{ selectedLine.line_code }} - {{ selectedLine.line_name }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="mt-4 print:mt-0">
                        <div class="text-center print:py-8">
                            <div class="hidden print:block mb-4">
                                <h2 class="text-2xl font-bold">{{ selectedLine?.line_name }}</h2>
                                <p class="text-gray-600">{{ selectedLine?.line_code }} - {{ selectedLine?.plant }}</p>
                            </div>

                            <div id="qrcode-display" class="flex justify-center mb-4"></div>

                            <div v-if="selectedLine" class="text-sm text-gray-600 print:text-base">
                                <p class="font-mono">{{ selectedLine.qr_code }}</p>
                            </div>

                            <div class="hidden print:block mt-6 text-sm text-gray-500">
                                <p>Scan QR Code ini untuk Start Operation atau Line Stop</p>
                            </div>
                        </div>

                        <div class="flex gap-2 mt-6 print:hidden">
                            <button
                                @click="showQrModal = false"
                                class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent"
                            >
                                Tutup
                            </button>
                            <button
                                @click="printQrCode"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                            >
                                <Printer class="w-4 h-4" />
                                Cetak
                            </button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    body * {
        visibility: hidden;
    }
    .dialog-content, .dialog-content * {
        visibility: visible;
    }
    .dialog-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
}
</style>
