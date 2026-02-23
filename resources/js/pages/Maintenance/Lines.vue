<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, nextTick, onMounted, onUnmounted } from 'vue';
import QRCode from 'qrcode';
import jsQR from 'jsqr';
import {
    Search, Factory, Plus, Edit, Printer, Activity, Clock, AlertCircle, Wrench,
    PlayCircle, Loader2, PauseCircle, StopCircle, RotateCcw,
    CheckCircle, Archive, Maximize2, Minimize2, Sun, Moon, Eye, Trash2, Calendar
} from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';

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
    paused_at: string | null;
    status: string;
    total_pause_minutes: number;
    shift: number;
    shift_label: string;
    is_auto_paused?: boolean;
}

interface Line {
    id: number;
    line_code: string;
    line_name: string;
    plant: string;
    qr_code: string;
    status: 'operating' | 'stopped' | 'maintenance' | 'paused';
    pending_line_stop: boolean;
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
    uptime_hours: number;
    total_failures: number;
    schedule_start_time?: string;
    schedule_end_time?: string;
    schedule_breaks?: Array<{ start: string; end: string }>;
    machines: Machine[];
    current_operation?: LineOperation;
    area?: {
        id: number;
        name: string;
    };
}

interface Area {
    id: number;
    name: string;
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
    areas: Area[];
    filters: {
        search?: string;
        plant?: string;
        status?: string;
        area?: number;
    };
}
const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const plantFilter = ref(props.filters.plant || '');
const statusFilter = ref(props.filters.status || '');
const isFullscreen = ref(false);
const fullscreenDarkMode = ref(true);

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showQrModal = ref(false);
const showQrScanModal = ref(false);
const showConfirmActionModal = ref(false);
const showActiveReportsModal = ref(false);
const showResetConfirmModal = ref(false);
const showMachineModal = ref(false);
const showScheduleModal = ref(false);

const selectedLine = ref<Line | null>(null);
const selectedMachines = ref<Machine[]>([]);
const activeReports = ref<MaintenanceReport[]>([]);

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

const actionForm = ref({
    line_id: null as number | null,
    operation_id: null as number | null,
    machine_id: null as number | null,
    report_id: null as number | null,
    user_name: '',
    notes: '',
    problem: '',
});

const scheduleForm = ref({
    schedule_start_time: '07:00',
    schedule_end_time: '16:00',
    schedule_breaks: [] as Array<{ start: string; end: string }>,
});

const resetForm = useForm({ reason: '' });
const createForm = useForm({ line_name: '', plant: '', description: '' });
const editForm = useForm({ line_name: '', plant: '', description: '' });

const areaFilter = ref(props.filters.area || null);
const editAreaId = ref<number | null>(null);
const editNewAreaName = ref('');
const showNewAreaInput = ref(false);

const refreshKey = ref(0);
let refreshInterval: number | null = null;

const calculateCurrentUptime = (line: Line): number => {
    const baseUptime = line.uptime_hours ?? 0;

    if (!line.current_operation) {
        return baseUptime;
    }

    if (line.status === 'paused' && line.current_operation.paused_at) {
        const startedAt = new Date(line.current_operation.started_at);
        const pausedAt = new Date(line.current_operation.paused_at);

        const elapsedMs = pausedAt.getTime() - startedAt.getTime();
        const elapsedHours = elapsedMs / (1000 * 60 * 60);
        const pauseHours = (line.current_operation.total_pause_minutes ?? 0) / 60;
        const frozenUptime = Math.max(0, elapsedHours - pauseHours);

        return baseUptime + frozenUptime;
    }

    if (line.status === 'operating') {
        const startedAt = new Date(line.current_operation.started_at);
        const now = new Date();

        const elapsedMs = now.getTime() - startedAt.getTime();
        const elapsedHours = elapsedMs / (1000 * 60 * 60);
        const pauseHours = (line.current_operation.total_pause_minutes ?? 0) / 60;
        const currentOperationUptime = Math.max(0, elapsedHours - pauseHours);

        return baseUptime + currentOperationUptime;
    }

    return baseUptime;
};

const calculateCurrentOperationTime = (line: Line): number => {
    if (!line.current_operation) {
        return line.total_operation_hours ?? 0;
    }

    if (line.status === 'paused' && line.current_operation.paused_at) {
        const startedAt = new Date(line.current_operation.started_at);
        const pausedAt = new Date(line.current_operation.paused_at);

        const elapsedMs = pausedAt.getTime() - startedAt.getTime();
        const elapsedHours = elapsedMs / (1000 * 60 * 60);
        const pauseHours = (line.current_operation.total_pause_minutes ?? 0) / 60;  // tambah ini
        const netHours = Math.max(0, elapsedHours - pauseHours);  // kurangi pause

        return (line.total_operation_hours ?? 0) + netHours;
    }

    if (line.status === 'operating' || line.status === 'maintenance') {
        const startedAt = new Date(line.current_operation.started_at);
        const now = new Date();
        const elapsedMs = now.getTime() - startedAt.getTime();
        const elapsedHours = elapsedMs / (1000 * 60 * 60);
        const pauseHours = (line.current_operation.total_pause_minutes ?? 0) / 60;  // sudah ada ini
        return (line.total_operation_hours ?? 0) + Math.max(0, elapsedHours - pauseHours);
    }

    return line.total_operation_hours ?? 0;
};

const search = () => {
    router.get('/maintenance/lines', {
        search: searchQuery.value,
        plant: plantFilter.value,
        status: statusFilter.value,
        area: areaFilter.value
    }, { preserveState: true, preserveScroll: true });
};

const toggleNewAreaInput = () => {
    showNewAreaInput.value = !showNewAreaInput.value;
    if (showNewAreaInput.value) {
        editAreaId.value = null;
    } else {
        editNewAreaName.value = '';
    }
};

const openMachineModal = (line: Line) => {
    selectedLine.value = line;
    selectedMachines.value = line.machines;
    showMachineModal.value = true;
};

const openScheduleModal = (line: Line) => {
    selectedLine.value = line;
    scheduleForm.value.schedule_start_time = line.schedule_start_time?.substring(0, 5) || '07:00';
    scheduleForm.value.schedule_end_time = line.schedule_end_time?.substring(0, 5) || '16:00';
    scheduleForm.value.schedule_breaks = line.schedule_breaks?.map(b => ({
        start: b.start.substring(0, 5),
        end: b.end.substring(0, 5),
    })) || [];
    showScheduleModal.value = true;
};

const addBreak = () => {
    scheduleForm.value.schedule_breaks.push({
        start: '12:00',
        end: '13:00',
    });
};

const removeBreak = (index: number) => {
    scheduleForm.value.schedule_breaks.splice(index, 1);
};

const submitSchedule = async () => {
    if (!selectedLine.value) return;
    try {
        const response = await fetch(`/maintenance/lines/${selectedLine.value.id}/schedule`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(scheduleForm.value),
        });

        const data = await response.json();

        if (data.success) {
            showScheduleModal.value = false;
            router.reload({ only: ['lines'] });
            alert('Schedule berhasil diupdate!');
        } else {
            alert(data.message || 'Gagal update schedule');
        }
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    } catch (error) {
        alert('Terjadi kesalahan saat update schedule');
    }
};

const formatDuration = (hours: number): string => {
    const h = Math.floor(hours);
    const m = Math.floor((hours - h) * 60);
    const s = Math.floor(((hours - h) * 60 - m) * 60);
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'operating': return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        case 'stopped': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'maintenance': return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
        case 'paused': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        default: return 'bg-gray-100 text-gray-700';
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
        'start': { title: 'Start Operation', color: 'green', icon: PlayCircle, description: 'Scan QR Code LINE untuk memulai operasi.' },
        'line-stop': { title: 'Line Stop', color: 'red', icon: AlertCircle, description: 'Scan QR Code LINE untuk line stop.' },
        'pause': { title: 'Pause Operation', color: 'blue', icon: PauseCircle, description: 'Scan QR Code LINE untuk pause operasi.' },
        'resume': { title: 'Resume Operation', color: 'green', icon: PlayCircle, description: 'Scan QR Code LINE untuk melanjutkan operasi.' },
        'stop': { title: 'Stop Operation', color: 'orange', icon: StopCircle, description: 'Scan QR Code LINE untuk menghentikan operasi.' },
        'complete': { title: 'Complete Maintenance', color: 'emerald', icon: CheckCircle, description: 'Scan QR Code LINE untuk menyelesaikan perbaikan.' },
    };
    return configs[scanMode.value];
};

const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value;
    if (isFullscreen.value) {
        document.documentElement.requestFullscreen?.();
    } else {
        document.exitFullscreen?.();
    }
};

const handleFullscreenChange = () => {
    if (!document.fullscreenElement) isFullscreen.value = false;
};

const viewLineDetail = (line: Line) => {
    router.visit(`/maintenance/lines/${line.id}`);
};

const openCreateModal = () => {
    createForm.reset();
    editAreaId.value = null;
    editNewAreaName.value = '';
    showNewAreaInput.value = false;
    showCreateModal.value = true;
};

const openEditModal = (line: Line) => {
    selectedLine.value = line;
    editForm.line_name = line.line_name;
    editForm.plant = line.plant;
    editForm.description = line.description || '';
    editAreaId.value = line.area?.id || null;
    editNewAreaName.value = '';
    showNewAreaInput.value = false;
    showEditModal.value = true;
};

const submitCreate = () => {
    const data: any = {
        line_name: createForm.line_name,
        plant: createForm.plant,
        description: createForm.description,
    };

    if (showNewAreaInput.value && editNewAreaName.value.trim()) {
        data.new_area_name = editNewAreaName.value.trim();
    } else if (editAreaId.value) {
        data.area_id = editAreaId.value;
    }

    router.post('/maintenance/lines', data, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const submitEdit = () => {
    if (!selectedLine.value) return;

    const data: any = {
        line_name: editForm.line_name,
        plant: editForm.plant,
        description: editForm.description,
    };

    if (showNewAreaInput.value && editNewAreaName.value.trim()) {
        data.new_area_name = editNewAreaName.value.trim();
    } else if (editAreaId.value) {
        data.area_id = editAreaId.value;
    }

    router.put(`/maintenance/lines/${selectedLine.value.id}`, data, {
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
        router.delete(`/maintenance/lines/${lineId}`, { preserveScroll: true });
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
    if (!confirm(`Reset metrics line "${selectedLine.value.line_name}"?`)) return;
    try {
        const response = await fetch(`/maintenance/lines/${selectedLine.value.id}/reset`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ reason: resetForm.reason }),
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
                color: { dark: '#000000', light: '#FFFFFF' }
            });
            container.appendChild(canvas);
        } catch { }
    }
};

const printQrCode = () => window.print();

const openActionModal = async (mode: typeof scanMode.value, line?: Line) => {
    scanMode.value = mode;
    if (line) {
        scannedLine.value = line;
        scannedMachines.value = line.machines;
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
                    alert('Tidak ada laporan maintenance aktif!');
                    return;
                }
            } catch {
                alert('Gagal mengambil data laporan!');
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
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            await videoRef.value.play();
            isCameraActive.value = true;
            startQrScan();
        }
    } catch {
        scanError.value = 'Tidak dapat mengakses kamera.';
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
            if (scanMode.value === 'start' && data.data.line.has_running_operation) {
                scanError.value = 'Line sudah dalam operasi!';
                setTimeout(() => { isScanning.value = false; }, 2000);
                return;
            } else if ((scanMode.value === 'pause' || scanMode.value === 'resume' || scanMode.value === 'stop') && !data.data.line.has_running_operation) {
                scanError.value = 'Line tidak memiliki operasi!';
                setTimeout(() => { isScanning.value = false; }, 2000);
                return;
            } else if (scanMode.value === 'complete' && data.data.line.status !== 'maintenance') {
                scanError.value = 'Line tidak dalam maintenance!';
                setTimeout(() => { isScanning.value = false; }, 2000);
                return;
            }
            if (scanMode.value === 'pause' || scanMode.value === 'resume' || scanMode.value === 'stop') {
                actionForm.value.operation_id = data.data.line.current_operation_id;
            }
            if (scanMode.value === 'complete') {
                try {
                    const reportsResponse = await fetch(`/maintenance/lines/${data.data.line.id}/active-reports`);
                    const reportsData = await reportsResponse.json();
                    if (reportsData.success && reportsData.data.length > 0) {
                        activeReports.value = reportsData.data;
                    } else {
                        scanError.value = 'Tidak ada laporan maintenance aktif!';
                        setTimeout(() => { isScanning.value = false; }, 2000);
                        return;
                    }
                } catch {
                    scanError.value = 'Gagal mengambil data laporan!';
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
        scanError.value = 'Gagal memproses QR Code.';
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
            payload = { line_id: actionForm.value.line_id, started_by: actionForm.value.user_name || 'System', notes: actionForm.value.notes };
        } else if (scanMode.value === 'line-stop') {
            if (!actionForm.value.machine_id) {
                alert('Pilih mesin yang bermasalah!');
                return;
            }
            endpoint = '/maintenance/lines/line-stop';
            payload = { line_id: actionForm.value.line_id, machine_id: actionForm.value.machine_id, problem: actionForm.value.problem, reported_by: actionForm.value.user_name || 'System' };
        } else if (scanMode.value === 'pause') {
            endpoint = `/maintenance/operations/${actionForm.value.operation_id}/pause`;
            payload = { paused_by: actionForm.value.user_name || 'System' };
        } else if (scanMode.value === 'resume') {
            endpoint = `/maintenance/operations/${actionForm.value.operation_id}/resume`;
            payload = { resumed_by: actionForm.value.user_name || 'System' };
        } else if (scanMode.value === 'stop') {
            endpoint = `/maintenance/operations/${actionForm.value.operation_id}/stop`;
            payload = { stopped_by: actionForm.value.user_name || 'System', notes: actionForm.value.notes };
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
            alert(data.message || 'Aksi berhasil!');
            router.reload({ only: ['lines', 'stats'] });
        } else {
            alert(data.message || 'Gagal melakukan aksi');
        }
    } catch {
        alert('Gagal melakukan aksi.');
    }
};

const quickCompleteReport = async (reportId: number) => {
    if (!confirm('Tandai perbaikan selesai?')) return;
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
            alert(data.message || 'Perbaikan selesai!');
            router.reload({ only: ['lines', 'stats'] });
        } else {
            alert(data.message || 'Gagal menyelesaikan perbaikan');
        }
    } catch {
        alert('Gagal menyelesaikan perbaikan.');
    }
};

onMounted(() => {
    refreshInterval = window.setInterval(() => {
        refreshKey.value++;
    }, 1000);

    document.addEventListener('fullscreenchange', handleFullscreenChange);
});

onUnmounted(() => {
    stopCamera();

    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }

    document.removeEventListener('fullscreenchange', handleFullscreenChange);
});
</script>
<template>
    <Head title="Line - Maintenance" />
    <AppLayout v-if="!isFullscreen" :breadcrumbs="[
        { title: 'Monitoring Maintenance', href: '/maintenance' },
        { title: 'Line', href: '/maintenance/lines' }
    ]">
        <div class="p-6 space-y-6 bg-gray-50 dark:!bg-gray-900 min-h-screen">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent flex items-center gap-3">
                        <Factory class="w-8 h-8 text-blue-600" />
                        Line Produksi
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Real-time production line monitoring</p>
                </div>
                <div class="flex gap-3">
                    <button @click="toggleFullscreen" class="px-4 py-2.5 bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium flex items-center gap-2">
                        <Maximize2 class="w-4 h-4" />
                        Fullscreen
                    </button>
                    <button @click="openCreateModal" class="px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium flex items-center gap-2">
                        <Plus class="w-4 h-4" />
                        Tambah Line
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-blue-500 hover:shadow-xl transition-all duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Total Lines</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.total_lines }}</p>
                        </div>
                        <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-xl">
                            <Factory class="w-6 h-6 text-blue-600" />
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-green-500 hover:shadow-xl transition-all duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Beroperasi</p>
                            <p class="text-3xl font-bold text-green-600 mt-1">{{ stats.operating }}</p>
                        </div>
                        <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-xl">
                            <Activity class="w-5 h-5 text-green-600" />
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-red-500 hover:shadow-xl transition-all duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Berhenti</p>
                            <p class="text-3xl font-bold text-red-600 mt-1">{{ stats.stopped }}</p>
                        </div>
                        <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-xl">
                            <AlertCircle class="w-6 h-6 text-red-600" />
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-yellow-500 hover:shadow-xl transition-all duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Maintenance</p>
                            <p class="text-3xl font-bold text-yellow-600 mt-1">{{ stats.maintenance }}</p>
                        </div>
                        <div class="p-3 bg-yellow-100 dark:bg-yellow-900/30 rounded-xl">
                            <Wrench class="w-6 h-6 text-yellow-600" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl p-5 shadow-lg">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                    <button @click="openActionModal('start')" class="flex flex-col items-center gap-2 p-4 bg-gradient-to-br from-green-500 to-emerald-600 text-white rounded-xl hover:shadow-xl active:scale-95 transition-all duration-300">
                        <PlayCircle class="w-8 h-8" />
                        <span class="text-sm font-bold">Start</span>
                    </button>
                    <button @click="openActionModal('pause')" class="flex flex-col items-center gap-2 p-4 bg-gradient-to-br from-blue-500 to-cyan-600 text-white rounded-xl hover:shadow-xl active:scale-95 transition-all duration-300">
                        <PauseCircle class="w-8 h-8" />
                        <span class="text-sm font-bold">Pause</span>
                    </button>
                    <button @click="openActionModal('resume')" class="flex flex-col items-center gap-2 p-4 bg-gradient-to-br from-teal-500 to-green-600 text-white rounded-xl hover:shadow-xl active:scale-95 transition-all duration-300">
                        <PlayCircle class="w-8 h-8" />
                        <span class="text-sm font-bold">Resume</span>
                    </button>
                    <button @click="openActionModal('stop')" class="flex flex-col items-center gap-2 p-4 bg-gradient-to-br from-orange-500 to-red-600 text-white rounded-xl hover:shadow-xl active:scale-95 transition-all duration-300">
                        <StopCircle class="w-8 h-8" />
                        <span class="text-sm font-bold">Stop</span>
                    </button>
                    <button @click="openActionModal('line-stop')" class="flex flex-col items-center gap-2 p-4 bg-gradient-to-br from-red-500 to-rose-600 text-white rounded-xl hover:shadow-xl active:scale-95 transition-all duration-300">
                        <AlertCircle class="w-8 h-8" />
                        <span class="text-sm font-bold">Line Stop</span>
                    </button>
                    <button @click="openActionModal('complete')" class="flex flex-col items-center gap-2 p-4 bg-gradient-to-br from-emerald-500 to-teal-600 text-white rounded-xl hover:shadow-xl active:scale-95 transition-all duration-300">
                        <CheckCircle class="w-8 h-8" />
                        <span class="text-sm font-bold">Complete</span>
                    </button>
                </div>
            </div>

            <div class="flex gap-3 flex-wrap">
                <div class="relative flex-1 min-w-[200px] max-w-md">
                    <input v-model="searchQuery" @keyup.enter="search" type="text" placeholder="Cari line, kode..." class="w-full pl-11 pr-4 py-3 rounded-xl border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                    <Search class="w-5 h-5 text-gray-400 absolute left-3.5 top-3.5" />
                </div>
                <select v-model="plantFilter" @change="search" class="rounded-xl border-2 border-gray-200 dark:border-gray-700 px-4 py-3 dark:bg-gray-800">
                    <option value="">Semua Plant</option>
                    <option v-for="plant in plants" :key="plant" :value="plant">{{ plant }}</option>
                </select>
                <select v-model="areaFilter" @change="search" class="rounded-xl border-2 border-gray-200 dark:border-gray-700 px-4 py-3 dark:bg-gray-800">
                    <option :value="null">Semua Area</option>
                    <option v-for="area in props.areas" :key="area.id" :value="area.id">{{ area.name }}</option>
                </select>
                <select v-model="statusFilter" @change="search" class="rounded-xl border-2 border-gray-200 dark:border-gray-700 px-4 py-3 dark:bg-gray-800">
                    <option value="">Semua Status</option>
                    <option value="operating">Beroperasi</option>
                    <option value="stopped">Berhenti</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="paused">Pause</option>
                </select>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                <div v-for="line in lines.data" :key="`${line.id}-${refreshKey}`" class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg transition-all duration-300 border border-gray-100 dark:border-gray-700">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ line.line_name }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ line.plant }}</p>
                            <p v-if="line.area" class="text-xs text-blue-600 dark:text-blue-400 font-semibold mt-0.5">{{ line.area.name }}</p>
                        </div>
                        <div class="flex flex-col gap-2 items-end">
                            <span :class="['inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold', getStatusColor(line.status)]">
                                <component :is="getStatusIcon(line.status)" class="w-3.5 h-3.5" />
                                {{ getStatusText(line.status) }}
                            </span>
                            <span v-if="line.current_operation" class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                {{ line.current_operation.shift_label }}
                            </span>
                            <span v-if="line.current_operation?.is_auto_paused" class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                Auto-Pause
                            </span>
                            <span v-if="line.pending_line_stop"
                                @click="openActionModal('line-stop', line)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer bg-red-500 text-white animate-pulse hover:bg-red-600 transition-all">
                                <AlertCircle class="w-3.5 h-3.5" />
                                Confirm Line Stop
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <button @click="openMachineModal(line)" class="group/machine text-center p-3 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl transition-all duration-300 border-2 border-blue-200 dark:border-blue-800 hover:border-blue-400 dark:hover:border-blue-600 cursor-pointer relative overflow-hidden">
                            <div class="absolute inset-0 bg-gradient-to-r from-blue-400/0 via-blue-400/10 to-blue-400/0 translate-x-[-100%] group-hover/machine:translate-x-[100%] transition-transform duration-700"></div>
                            <div class="relative">
                                <div class="flex items-center justify-center gap-1.5 text-xs text-gray-600 dark:text-gray-400 font-medium mb-1">
                                    <Wrench class="w-3.5 h-3.5" />
                                    <span>Mesin</span>
                                    <Eye class="w-3 h-3 opacity-0 group-hover/machine:opacity-100 transition-opacity" />
                                </div>
                                <div class="text-2xl font-bold text-blue-600 group-hover/machine:text-blue-700 transition-colors">{{ line.machines_count }}</div>
                                <div class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold mt-1 opacity-0 group-hover/machine:opacity-100 transition-opacity">Klik untuk detail</div>
                            </div>
                        </button>

                        <div class="text-center p-3 bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl">
                            <div class="text-xs text-gray-600 dark:text-gray-400 font-medium">Uptime</div>
                            <div class="text-xl font-bold text-green-600 mt-3">
                                {{ formatDuration(calculateCurrentUptime(line)) }}
                            </div>
                        </div>

                        <div class="text-center p-3 bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 rounded-xl">
                            <div class="text-xs text-gray-600 dark:text-gray-400 font-medium">Failures</div>
                            <div class="text-2xl font-bold text-red-600 mt-1">{{ line.total_failures }}</div>
                        </div>
                        <div class="text-center p-3 bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-xl">
                            <div class="text-xs text-gray-600 dark:text-gray-400 font-medium">MTBF</div>
                            <div class="text-xl font-bold text-indigo-600 mt-1">{{ line.average_mtbf ? formatDuration(line.average_mtbf) : '-' }}</div>
                        </div>
                    </div>

                    <div class="space-y-2 mb-4 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Operation:</span>
                            <span class="font-mono font-semibold text-blue-600">
                                {{ formatDuration(calculateCurrentOperationTime(line)) }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Repair:</span>
                            <span class="font-mono font-semibold text-orange-600">{{ formatDuration(line.total_repair_hours) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">MTTR:</span>
                            <span class="font-mono font-semibold text-purple-600">{{ line.average_mttr || '-' }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 mb-4">
                        <button v-if="line.active_reports_count > 0" @click="viewActiveReports(line)" class="w-full px-3 py-2 text-sm font-semibold text-white bg-gradient-to-r from-red-500 to-rose-600 hover:shadow-lg rounded-xl transition-all duration-300 flex items-center justify-center gap-2">
                            <AlertCircle class="w-4 h-4" />
                            Problem ({{ line.active_reports_count }})
                        </button>
                        <div class="grid grid-cols-3 gap-2">
                            <template v-if="line.current_operation">
                                <button v-if="line.status === 'operating'" @click="openActionModal('pause', line)" class="px-3 py-2 bg-gradient-to-r from-blue-500 to-cyan-600 text-white rounded-xl hover:shadow-lg text-sm font-semibold flex items-center justify-center gap-1.5 transition-all duration-300">
                                    <PauseCircle class="w-4 h-4" />
                                    Pause
                                </button>
                                <button v-else-if="line.status === 'paused'" @click="openActionModal('resume', line)" class="px-3 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl hover:shadow-lg text-sm font-semibold flex items-center justify-center gap-1.5 transition-all duration-300">
                                    <PlayCircle class="w-4 h-4" />
                                    Resume
                                </button>
                                <button v-if="line.status !== 'maintenance'" @click="openActionModal('stop', line)" class="px-3 py-2 bg-gradient-to-r from-orange-500 to-red-600 text-white rounded-xl hover:shadow-lg text-sm font-semibold flex items-center justify-center gap-1.5 transition-all duration-300">
                                    <StopCircle class="w-4 h-4" />
                                    Stop
                                </button>
                                <button v-if="line.status !== 'maintenance'" @click="openActionModal('line-stop', line)" class="px-3 py-2 bg-gradient-to-r from-red-500 to-rose-600 text-white rounded-xl hover:shadow-lg text-sm font-semibold flex items-center justify-center gap-1.5 transition-all duration-300">
                                    <AlertCircle class="w-4 h-4" />
                                    Line Stop
                                </button>
                            </template>
                            <button v-else-if="line.status !== 'maintenance'" @click="openActionModal('start', line)" class="col-span-3 px-3 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl hover:shadow-lg text-sm font-semibold flex items-center justify-center gap-1.5 transition-all duration-300">
                                <PlayCircle class="w-4 h-4" />
                                Start Operation
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-6 gap-2">
                        <button @click="openScheduleModal(line)" class="p-2.5 bg-indigo-100 dark:bg-indigo-900/30 rounded-xl hover:shadow-lg transition-all duration-300" title="Schedule">
                            <Calendar class="w-4 h-4 text-indigo-600 mx-auto" />
                        </button>
                        <button @click="viewQrCode(line)" class="p-2.5 bg-blue-100 dark:bg-blue-900/30 rounded-xl hover:shadow-lg transition-all duration-300" title="QR Code">
                            <Printer class="w-4 h-4 text-blue-600 mx-auto" />
                        </button>
                        <button @click="viewLineDetail(line)" class="p-2.5 bg-purple-100 dark:bg-purple-900/30 rounded-xl hover:shadow-lg transition-all duration-300" title="Detail">
                            <Archive class="w-4 h-4 text-purple-600 mx-auto" />
                        </button>
                        <button @click="openEditModal(line)" class="p-2.5 bg-yellow-100 dark:bg-yellow-900/30 rounded-xl hover:shadow-lg transition-all duration-300" title="Edit">
                            <Edit class="w-4 h-4 text-yellow-600 mx-auto" />
                        </button>
                        <button @click="openResetConfirmModal(line)" class="p-2.5 bg-orange-100 dark:bg-orange-900/30 rounded-xl hover:shadow-lg transition-all duration-300" title="Reset Metrics">
                            <RotateCcw class="w-4 h-4 text-orange-600 mx-auto" />
                        </button>
                        <button @click="deleteLine(line.id, line.line_name)" class="p-2.5 bg-red-100 dark:bg-red-900/30 rounded-xl hover:shadow-lg transition-all duration-300" title="Delete">
                            <Trash2 class="w-4 h-4 text-red-600 mx-auto" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
    <div v-else :class="['fixed inset-0 overflow-auto', fullscreenDarkMode ? 'bg-gray-900' : 'bg-gray-50']">
        <div class="min-h-screen p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 :class="['text-4xl font-bold flex items-center gap-3', fullscreenDarkMode ? 'text-white' : 'text-gray-900']">
                    <Factory class="w-10 h-10 text-blue-500" />
                    Line Produksi Display
                </h1>
                <div class="flex gap-3">
                    <button @click="fullscreenDarkMode = !fullscreenDarkMode" :class="['px-5 py-3 rounded-xl font-semibold flex items-center gap-2', fullscreenDarkMode ? 'bg-yellow-500 text-white' : 'bg-gray-800 text-white']">
                        <component :is="fullscreenDarkMode ? Sun : Moon" class="w-5 h-5" />
                        {{ fullscreenDarkMode ? 'Light' : 'Dark' }}
                    </button>
                    <button @click="toggleFullscreen" class="px-5 py-3 bg-red-600 text-white rounded-xl font-semibold flex items-center gap-2 hover:bg-red-700">
                        <Minimize2 class="w-5 h-5" />
                        Exit
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <div v-for="line in lines.data" :key="`fs-${line.id}-${refreshKey}`" :class="['rounded-2xl p-6 shadow-2xl', fullscreenDarkMode ? 'bg-gray-800' : 'bg-white']">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 :class="['text-2xl font-bold', fullscreenDarkMode ? 'text-white' : 'text-gray-900']">{{ line.line_name }}</h3>
                            <p :class="['text-sm mt-1 font-mono', fullscreenDarkMode ? 'text-gray-400' : 'text-gray-600']">{{ line.line_code }}</p>
                        </div>
                        <div :class="['px-3 py-1.5 rounded-lg text-sm font-bold', line.status === 'operating' ? 'bg-green-500 text-white' : line.status === 'maintenance' ? 'bg-yellow-500 text-white' : line.status === 'paused' ? 'bg-blue-500 text-white' : 'bg-red-500 text-white']">
                            {{ getStatusText(line.status) }}
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div :class="['text-center p-2 rounded-xl', fullscreenDarkMode ? 'bg-gray-700' : 'bg-gray-100']">
                            <div :class="['text-xs', fullscreenDarkMode ? 'text-gray-400' : 'text-gray-600']">Mesin</div>
                            <div class="text-lg font-bold text-blue-500 mt-1">{{ line.machines_count }}</div>
                        </div>
                        <div :class="['text-center p-2 rounded-xl', fullscreenDarkMode ? 'bg-gray-700' : 'bg-gray-100']">
                            <div :class="['text-xs', fullscreenDarkMode ? 'text-gray-400' : 'text-gray-600']">Uptime</div>
                            <div class="text-xs font-bold text-green-500 mt-1">
                                {{ formatDuration(calculateCurrentUptime(line)) }}
                            </div>
                        </div>
                        <div :class="['text-center p-2 rounded-xl', fullscreenDarkMode ? 'bg-gray-700' : 'bg-gray-100']">
                            <div :class="['text-xs', fullscreenDarkMode ? 'text-gray-400' : 'text-gray-600']">Fail</div>
                            <div class="text-lg font-bold text-red-500 mt-1">{{ line.total_failures }}</div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div :class="['flex justify-between text-sm', fullscreenDarkMode ? 'text-gray-300' : 'text-gray-700']">
                            <span>Operation:</span>
                            <span class="font-mono font-bold text-blue-500">
                                {{ formatDuration(calculateCurrentOperationTime(line)) }}
                            </span>
                        </div>
                        <div :class="['flex justify-between text-sm', fullscreenDarkMode ? 'text-gray-300' : 'text-gray-700']">
                            <span>Repair:</span>
                            <span class="font-mono font-bold text-orange-500">{{ formatDuration(line.total_repair_hours) }}</span>
                        </div>
                        <div :class="['flex justify-between text-sm', fullscreenDarkMode ? 'text-gray-300' : 'text-gray-700']">
                            <span>MTBF:</span>
                            <span class="font-mono font-bold text-indigo-500">{{ line.average_mtbf ? formatDuration(line.average_mtbf) : '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <Dialog :open="showScheduleModal" @update:open="showScheduleModal = $event">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Set Schedule - {{ selectedLine?.line_name }}</DialogTitle>
                <DialogDescription>
                    Atur jadwal operasi dan break time untuk auto-pause
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitSchedule" class="space-y-6 mt-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Jam Mulai</label>
                        <input
                            v-model="scheduleForm.schedule_start_time"
                            type="time"
                            required
                            class="w-full rounded-xl border-2 px-3 py-2 dark:bg-gray-800"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Jam Selesai</label>
                        <input
                            v-model="scheduleForm.schedule_end_time"
                            type="time"
                            required
                            class="w-full rounded-xl border-2 px-3 py-2 dark:bg-gray-800"
                        />
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-3">
                        <label class="block text-sm font-semibold">Break Time (Auto-Pause)</label>
                        <button
                            type="button"
                            @click="addBreak"
                            class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-all"
                        >
                            + Tambah Break
                        </button>
                    </div>

                    <div v-if="scheduleForm.schedule_breaks.length > 0" class="space-y-3">
                        <div
                            v-for="(breakItem, index) in scheduleForm.schedule_breaks"
                            :key="index"
                            class="flex gap-3 items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
                        >
                            <div class="flex-1 grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Mulai</label>
                                    <input
                                        v-model="breakItem.start"
                                        type="time"
                                        required
                                        class="w-full rounded-lg border px-2 py-1.5 text-sm dark:bg-gray-700"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Selesai</label>
                                    <input
                                        v-model="breakItem.end"
                                        type="time"
                                        required
                                        class="w-full rounded-lg border px-2 py-1.5 text-sm dark:bg-gray-700"
                                    />
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="removeBreak(index)"
                                class="p-2 bg-red-100 dark:bg-red-900/30 text-red-600 rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 transition-all"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div v-else class="text-center py-8 text-gray-500 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <p class="text-sm">Tidak ada break time. Klik "Tambah Break" untuk menambahkan.</p>
                    </div>
                </div>

                <div class="bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-200 dark:border-blue-800 rounded-xl p-4">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="text-sm text-blue-800 dark:text-blue-300">
                            <p class="font-semibold mb-1">Auto-Pause System</p>
                            <p>System akan otomatis pause operasi saat break time dan resume setelah break selesai. Total waktu pause akan tercatat dan tidak mempengaruhi perhitungan performa.</p>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button
                        type="button"
                        @click="showScheduleModal = false"
                        class="flex-1 px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-xl font-semibold hover:bg-gray-50 dark:hover:bg-gray-800 transition-all"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="flex-1 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-semibold hover:shadow-lg transition-all"
                    >
                        Simpan Schedule
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="showMachineModal" @update:open="showMachineModal = $event">
        <DialogContent class="max-w-4xl max-h-[85vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Wrench class="w-5 h-5 text-blue-600" />
                    Daftar Mesin
                </DialogTitle>
                <DialogDescription v-if="selectedLine">
                    {{ selectedLine.line_name }} - Total {{ selectedMachines.length }} Mesin
                </DialogDescription>
            </DialogHeader>
            <div class="mt-4">
                <div v-if="selectedMachines.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="machine in selectedMachines" :key="machine.id" class="p-4 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <div class="font-bold text-lg text-gray-900 dark:text-white">{{ machine.machine_name }}</div>
                                <div class="text-sm text-gray-500 mt-1">{{ machine.machine_type }}</div>
                                <div class="text-xs text-blue-600 dark:text-blue-400 font-mono mt-1">{{ machine.barcode }}</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="text-center p-3 bg-white dark:bg-gray-800 rounded-lg">
                                <div class="text-xs text-gray-500">Operation</div>
                                <div class="text-sm font-bold text-blue-600">{{ formatDuration(machine.total_operation_hours) }}</div>
                            </div>
                            <div class="text-center p-3 bg-white dark:bg-gray-800 rounded-lg">
                                <div class="text-xs text-gray-500">Repair</div>
                                <div class="text-sm font-bold text-orange-600">{{ formatDuration(machine.total_repair_hours) }}</div>
                            </div>
                            <div class="text-center p-3 bg-white dark:bg-gray-800 rounded-lg">
                                <div class="text-xs text-gray-500">MTBF</div>
                                <div class="text-sm font-bold text-indigo-600">{{ machine.mtbf_hours ? formatDuration(machine.mtbf_hours) : '-' }}</div>
                            </div>
                            <div class="text-center p-3 bg-white dark:bg-gray-800 rounded-lg">
                                <div class="text-xs text-gray-500">MTTR</div>
                                <div class="text-sm font-bold text-purple-600">{{ machine.mttr_hours ? formatDuration(machine.mttr_hours) : '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12 text-gray-500">
                    <Wrench class="w-16 h-16 mx-auto mb-3 opacity-30" />
                    <p>Belum ada mesin</p>
                </div>
                <button @click="showMachineModal = false" class="w-full mt-6 px-4 py-2.5 border-2 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 font-semibold transition-all">Tutup</button>
            </div>
        </DialogContent>
    </Dialog>

    <Dialog :open="showQrScanModal" @update:open="val => !val && closeQrScanModal()">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <component :is="getScanModeConfig().icon" class="w-5 h-5" />
                    Scan QR - {{ getScanModeConfig().title }}
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
                        <div class="bg-green-600 text-white px-4 py-2 rounded-lg font-semibold">✓ QR Code Terdeteksi!</div>
                    </div>
                </div>
                <p v-if="scanError" class="text-red-500 text-sm text-center font-medium bg-red-50 dark:bg-red-900/20 p-2 rounded">⚠️ {{ scanError }}</p>
                <button @click="closeQrScanModal" class="w-full px-4 py-3 border-2 border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 font-medium transition-all">Tutup</button>
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
                <div v-if="scannedLine" class="border-2 rounded-xl p-4 bg-blue-50 dark:bg-blue-900/20">
                    <div class="font-semibold mb-2">{{ scannedLine.line_name }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ scannedLine.line_code }} - {{ scannedLine.plant }}</div>
                </div>
                <div v-if="scanMode === 'complete' && activeReports.length > 0">
                    <label class="block text-sm font-semibold mb-2">Pilih Laporan <span class="text-red-600">*</span></label>
                    <div class="space-y-2 max-h-60 overflow-y-auto border-2 rounded-xl p-2">
                        <label v-for="report in activeReports" :key="report.id" :class="['flex gap-3 p-3 rounded-lg border-2 cursor-pointer', actionForm.report_id === report.id ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20' : 'border-gray-200 dark:border-gray-700']">
                            <input type="radio" :value="report.id" v-model="actionForm.report_id" />
                            <div class="flex-1">
                                <div class="font-semibold text-sm">{{ report.report_number }}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">{{ report.machine.machine_name }}</div>
                                <div class="text-sm mt-1">{{ report.problem }}</div>
                            </div>
                        </label>
                    </div>
                </div>
                <div v-if="scanMode === 'line-stop'">
                    <label class="block text-sm font-semibold mb-2">Mesin Bermasalah <span class="text-red-600">*</span></label>
                    <select v-model="actionForm.machine_id" required class="w-full rounded-xl border-2 px-3 py-2 dark:bg-gray-800">
                        <option value="">Pilih Mesin</option>
                        <option v-for="machine in scannedMachines" :key="machine.id" :value="machine.id">{{ machine.machine_name }}</option>
                    </select>
                </div>
                <div v-if="scanMode === 'line-stop'">
                    <label class="block text-sm font-semibold mb-2">Deskripsi Masalah</label>
                    <textarea v-model="actionForm.problem" rows="3" class="w-full rounded-xl border-2 px-3 py-2 dark:bg-gray-800"></textarea>
                </div>
                <div v-if="scanMode !== 'complete'">
                    <label class="block text-sm font-semibold mb-2">{{ scanMode === 'line-stop' ? 'Dilaporkan Oleh' : 'Nama Operator' }}</label>
                    <input v-model="actionForm.user_name" type="text" class="w-full rounded-xl border-2 px-3 py-2 dark:bg-gray-800" />
                </div>
                <div v-if="scanMode === 'start' || scanMode === 'stop'">
                    <label class="block text-sm font-semibold mb-2">Catatan</label>
                    <textarea v-model="actionForm.notes" rows="2" class="w-full rounded-xl border-2 px-3 py-2 dark:bg-gray-800"></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="showConfirmActionModal = false" class="flex-1 px-4 py-2.5 border-2 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 font-semibold transition-all">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-semibold hover:shadow-lg transition-all">{{ getScanModeConfig().title }}</button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="showActiveReportsModal" @update:open="showActiveReportsModal = $event">
        <DialogContent class="max-w-3xl">
            <DialogHeader>
                <DialogTitle>Laporan Maintenance Aktif</DialogTitle>
            </DialogHeader>
            <div class="mt-4 space-y-3">
                <div v-for="report in activeReports" :key="report.id" class="border-2 rounded-xl p-4 bg-white dark:bg-gray-800">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-semibold">{{ report.report_number }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">{{ report.machine.machine_name }}</div>
                            <div class="text-sm mt-1">{{ report.problem }}</div>
                        </div>
                        <button @click="quickCompleteReport(report.id)" class="px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl font-semibold hover:shadow-lg transition-all flex items-center gap-2">
                            <CheckCircle class="w-4 h-4" />
                            Selesai
                        </button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <Dialog :open="showQrModal" @update:open="showQrModal = $event">
        <DialogContent class="max-w-md print:max-w-full print:shadow-none print:border-0">
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
                    <button @click="showQrModal = false" class="flex-1 px-4 py-2.5 border-2 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 font-semibold transition-all">
                        Tutup
                    </button>
                    <button @click="printQrCode" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-semibold hover:shadow-lg flex items-center justify-center gap-2">
                        <Printer class="w-4 h-4" />
                        Cetak
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <Dialog :open="showCreateModal" @update:open="showCreateModal = $event">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Tambah Line Baru</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitCreate" class="space-y-4 mt-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Area</label>
                    <div class="flex gap-2">
                        <select v-if="!showNewAreaInput" v-model="editAreaId" class="flex-1 rounded-xl border-2 px-3 py-2 dark:bg-gray-800">
                            <option :value="null">Tidak Ada Area</option>
                            <option v-for="area in props.areas" :key="area.id" :value="area.id">{{ area.name }}</option>
                        </select>
                        <input v-else v-model="editNewAreaName" type="text" placeholder="Nama area baru..." class="flex-1 rounded-xl border-2 px-3 py-2 dark:bg-gray-800" />
                        <button type="button" @click="toggleNewAreaInput" :class="['px-4 py-2 rounded-xl font-semibold transition-all', showNewAreaInput ? 'bg-gray-600 text-white' : 'bg-green-600 text-white']">
                            {{ showNewAreaInput ? 'Pilih' : 'Baru' }}
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Nama Line <span class="text-red-600">*</span></label>
                    <input v-model="createForm.line_name" type="text" required class="w-full rounded-xl border-2 px-3 py-2 dark:bg-gray-800" />
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Plant <span class="text-red-600">*</span></label>
                    <input v-model="createForm.plant" list="plant-list" type="text" required class="w-full rounded-xl border-2 px-3 py-2 dark:bg-gray-800" />
                    <datalist id="plant-list">
                        <option v-for="plant in props.plants" :key="plant" :value="plant">{{ plant }}</option>
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Deskripsi</label>
                    <textarea v-model="createForm.description" rows="2" class="w-full rounded-xl border-2 px-3 py-2 dark:bg-gray-800"></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="showCreateModal = false" class="flex-1 px-4 py-2.5 border-2 rounded-xl font-semibold">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-semibold hover:shadow-lg">Simpan</button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="showEditModal" @update:open="showEditModal = $event">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Edit Line</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitEdit" class="space-y-4 mt-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Area</label>
                    <div class="flex gap-2">
                        <select v-if="!showNewAreaInput" v-model="editAreaId" class="flex-1 rounded-xl border-2 px-3 py-2 dark:bg-gray-800">
                            <option :value="null">Tidak Ada Area</option>
                            <option v-for="area in props.areas" :key="area.id" :value="area.id">{{ area.name }}</option>
                        </select>
                        <input v-else v-model="editNewAreaName" type="text" placeholder="Nama area baru..." class="flex-1 rounded-xl border-2 px-3 py-2 dark:bg-gray-800" />
                        <button type="button" @click="toggleNewAreaInput" :class="['px-4 py-2 rounded-xl font-semibold transition-all', showNewAreaInput ? 'bg-gray-600 text-white' : 'bg-green-600 text-white']">
                            {{ showNewAreaInput ? 'Pilih' : 'Baru' }}
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Nama Line <span class="text-red-600">*</span></label>
                    <input v-model="editForm.line_name" type="text" required class="w-full rounded-xl border-2 px-3 py-2 dark:bg-gray-800" />
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Plant <span class="text-red-600">*</span></label>
                    <select v-model="editForm.plant" required class="w-full rounded-xl border-2 px-3 py-2 dark:bg-gray-800">
                        <option value="">Pilih Plant</option>
                        <option v-for="plant in props.plants" :key="plant" :value="plant">{{ plant }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Deskripsi</label>
                    <textarea v-model="editForm.description" rows="2" class="w-full rounded-xl border-2 px-3 py-2 dark:bg-gray-800"></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="showEditModal = false" class="flex-1 px-4 py-2.5 border-2 rounded-xl font-semibold">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-semibold hover:shadow-lg">Simpan</button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="showResetConfirmModal" @update:open="showResetConfirmModal = $event">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <RotateCcw class="w-5 h-5 text-orange-600" />
                    Reset Metrics
                </DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitResetMetrics" class="space-y-4 mt-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Alasan Reset <span class="text-red-600">*</span></label>
                    <textarea v-model="resetForm.reason" rows="4" required class="w-full rounded-xl border-2 px-3 py-2 dark:bg-gray-800"></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="showResetConfirmModal = false" class="flex-1 px-4 py-2.5 border-2 rounded-xl font-semibold">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-orange-500 to-red-600 text-white rounded-xl font-semibold hover:shadow-lg">Reset</button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@media print {
    body * {
        visibility: hidden;
    }
    #qrcode-display,
    #qrcode-display * {
        visibility: visible;
    }
    .print\:block {
        display: block !important;
        visibility: visible !important;
    }
    .print\:hidden {
        display: none !important;
    }
    [role="dialog"] {
        position: absolute !important;
        left: 50% !important;
        top: 50% !important;
        transform: translate(-50%, -50%) !important;
        box-shadow: none !important;
        border: none !important;
        width: auto !important;
        max-width: 100% !important;
    }
    [role="dialog"] * {
        visibility: visible !important;
    }
}
</style>
