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
    Loader2
} from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';

interface Line {
    id: number;
    line_code: string;
    line_name: string;
    plant: string;
    qr_code: string;
    status: 'operating' | 'stopped' | 'maintenance';
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
    has_running_operation?: boolean;
}

interface Machine {
    id: number;
    machine_name: string;
    barcode: string;
    plant: string;
    line: string;
    machine_type: string;
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

const searchQuery = ref(props.filters.search || '');
const plantFilter = ref(props.filters.plant || '');
const statusFilter = ref(props.filters.status || '');

// Modals
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showQrModal = ref(false);
const showQrScanModal = ref(false);
const showStartOperationModal = ref(false);
const selectedLine = ref<Line | null>(null);

// QR Scanner untuk Start Operation
const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const isScanning = ref(false);
const scanError = ref('');
const scannedLine = ref<Line | null>(null);
const scannedMachines = ref<Machine[]>([]);
const isCameraActive = ref(false);
let stream: MediaStream | null = null;
let scanInterval: number | null = null;

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

const startOperationForm = useForm({
    line_id: null as number | null,
    started_by: '',
    notes: '',
});

const search = () => {
    router.get('/maintenance/lines', {
        search: searchQuery.value,
        plant: plantFilter.value,
        status: statusFilter.value,
    }, { preserveState: true, preserveScroll: true });
};

const openCreateModal = () => {
    createForm.reset();
    showCreateModal.value = true;
};

const submitCreate = () => {
    createForm.post('/maintenance/lines', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const openEditModal = (line: Line) => {
    selectedLine.value = line;
    editForm.line_name = line.line_name;
    editForm.plant = line.plant;
    editForm.description = line.description || '';
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    selectedLine.value = null;
    editForm.reset();
};

const submitEdit = () => {
    if (!selectedLine.value) return;

    editForm.put(`/maintenance/lines/${selectedLine.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeEditModal();
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

const viewQrCode = async (line: Line) => {
    selectedLine.value = line;
    showQrModal.value = true;

    await nextTick();
    await new Promise(resolve => setTimeout(resolve, 150));
    await generateQrCodeForDisplay(line.qr_code);
};

const closeQrModal = () => {
    showQrModal.value = false;
    selectedLine.value = null;
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
        } catch (error) {
            console.error('QR Code generation error:', error);
        }
    }
};

const printQrCode = () => {
    window.print();
};

// ========== START OPERATION FUNCTIONS ==========
const openQrModalForStart = async () => {
    scanError.value = '';
    scannedLine.value = null;
    scannedMachines.value = [];
    showQrScanModal.value = true;
    await startCamera();
};

const submitStartOperation = async () => {
    if (!startOperationForm.line_id) return;

    try {
        const response = await fetch('/maintenance/operations/start', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                line_id: startOperationForm.line_id,
                started_by: startOperationForm.started_by,
                notes: startOperationForm.notes,
            }),
        });

        const data = await response.json();

        if (data.success) {
            showStartOperationModal.value = false;
            startOperationForm.reset();
            scannedLine.value = null;
            router.reload({ only: ['lines', 'stats'] });
            alert('Operasi line berhasil dimulai! Semua mesin sudah beroperasi.');
        } else {
            alert(data.message || 'Gagal memulai operasi');
        }
    } catch (error) {
        console.error('Start operation error:', error);
        alert('Gagal memulai operasi. Coba lagi.');
    }
};

// ========== QR SCANNER FUNCTIONS ==========
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
            showQrScanModal.value = false;

            // Check if line already has running operation
            if (data.data.line.has_running_operation) {
                alert('Line ini sudah dalam status operasi!');
                return;
            }

            startOperationForm.line_id = data.data.line.id;
            showStartOperationModal.value = true;
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

const closeQrScanModal = () => {
    stopCamera();
    showQrScanModal.value = false;
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'operating': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'stopped': return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        case 'maintenance': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'operating': return Activity;
        case 'stopped': return AlertCircle;
        case 'maintenance': return Wrench;
        default: return Clock;
    }
};

const getStatusText = (status: string) => {
    switch (status) {
        case 'operating': return 'Beroperasi';
        case 'stopped': return 'Berhenti';
        case 'maintenance': return 'Maintenance';
        default: return status;
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
            <!-- Header -->
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Factory class="w-6 h-6 text-blue-600" />
                    Line Produksi
                </h1>
                <div class="flex items-center gap-2">
                    <button
                        @click="openQrModalForStart"
                        class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm"
                    >
                        <PlayCircle class="w-4 h-4" />
                        Start Operation
                    </button>
                    <button
                        @click="openCreateModal"
                        class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"
                    >
                        <Plus class="w-4 h-4" />
                        Tambah Line
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">Total Line</div>
                    <div class="text-2xl font-bold">{{ stats.total_lines }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">Beroperasi</div>
                    <div class="text-2xl font-bold text-green-600">{{ stats.operating }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">Berhenti</div>
                    <div class="text-2xl font-bold text-red-600">{{ stats.stopped }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-xs text-gray-600 dark:text-gray-400">Maintenance</div>
                    <div class="text-2xl font-bold text-yellow-600">{{ stats.maintenance }}</div>
                </div>
            </div>

            <!-- Search & Filters -->
            <div class="flex flex-wrap gap-2 items-center">
                <div class="flex-1 min-w-[200px] max-w-md flex gap-2">
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
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent">
                            <tr>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Kode Line</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Nama Line</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Plant</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Jumlah Mesin</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">MTTR</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">MTBF (h)</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Line Stops</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">QR Code</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="line in lines.data" :key="line.id" class="hover:bg-gray-50 dark:hover:bg-sidebar-accent">
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
                                    <span v-if="line.average_mttr" class="font-mono text-sm text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/20 px-2 py-1 rounded">
                                        {{ line.average_mttr }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="line.average_mtbf" class="font-mono text-sm text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 px-2 py-1 rounded">
                                        {{ line.average_mtbf.toFixed(2) }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-sm font-medium">{{ line.total_line_stops }}</span>
                                        <span v-if="line.active_reports_count > 0" class="text-xs text-red-600 dark:text-red-400">
                                            {{ line.active_reports_count }} aktif
                                        </span>
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
                                    <div class="flex items-center gap-1 justify-center">
                                        <button
                                            @click="openEditModal(line)"
                                            class="p-1.5 bg-yellow-600 text-white rounded hover:bg-yellow-700"
                                            title="Edit"
                                        >
                                            <Edit class="w-4 h-4" />
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
                            <tr v-if="lines.data.length === 0">
                                <td colspan="10" class="px-4 py-8 text-center text-gray-500">
                                    <Factory class="w-12 h-12 mx-auto mb-2 opacity-50" />
                                    <p>Tidak ada data line</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
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
            <!-- Modal QR Scanner for Start Operation -->
            <Dialog :open="showQrScanModal" @update:open="val => !val && closeQrScanModal()">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Scan QR - Start Operation</DialogTitle>
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
                            <div class="absolute inset-0 border-2 border-green-500 m-12 rounded-lg pointer-events-none"></div>
                        </div>
                        <p v-if="scanError" class="text-red-500 text-sm text-center">{{ scanError }}</p>
                        <div class="bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 border rounded-lg p-3">
                            <div class="flex items-start gap-2">
                                <Camera class="w-5 h-5 flex-shrink-0 mt-0.5 text-green-600 dark:text-green-400" />
                                <p class="text-sm text-green-800 dark:text-green-300">
                                    Scan QR Code LINE untuk memulai operasi. Semua mesin di line akan mulai beroperasi.
                                </p>
                            </div>
                        </div>
                        <button @click="closeQrScanModal" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent">
                            Tutup
                        </button>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Modal Start Operation -->
            <Dialog :open="showStartOperationModal" @update:open="showStartOperationModal = $event">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Start Operation Line</DialogTitle>
                    </DialogHeader>

                    <form @submit.prevent="submitStartOperation" class="space-y-4 mt-4">
                        <div v-if="scannedLine" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <Factory class="w-5 h-5 text-green-600 dark:text-green-400" />
                                <span class="font-semibold text-green-700 dark:text-green-300">Line Hasil Scan</span>
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
                                    <span class="text-gray-600 dark:text-gray-400">Jumlah Mesin:</span>
                                    <span class="font-medium ml-2">{{ scannedMachines.length }}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">
                                Operator <span class="text-gray-500">(opsional)</span>
                            </label>
                            <input
                                v-model="startOperationForm.started_by"
                                type="text"
                                placeholder="Nama operator yang memulai"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">
                                Catatan <span class="text-gray-500">(opsional)</span>
                            </label>
                            <textarea
                                v-model="startOperationForm.notes"
                                rows="2"
                                placeholder="Catatan operasi..."
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            ></textarea>
                        </div>

                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                            <p class="text-sm text-blue-800 dark:text-blue-300">
                                ℹ️ Setelah start operation, sistem akan mencatat waktu mulai operasi. Waktu ini akan digunakan untuk menghitung MTBF ketika terjadi line stop.
                            </p>
                        </div>

                        <div class="flex gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                @click="showStartOperationModal = false"
                                class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                            >
                                <PlayCircle class="w-4 h-4" />
                                Mulai Operasi
                            </button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Modal Create Line -->
            <Dialog :open="showCreateModal" @update:open="showCreateModal = $event">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Tambah Line Baru</DialogTitle>
                        <DialogDescription>
                            Isi informasi line produksi yang akan ditambahkan
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submitCreate" class="space-y-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">
                                Nama Line <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="createForm.line_name"
                                type="text"
                                required
                                placeholder="Contoh: Production Line A1"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                                :class="{ 'border-red-500': createForm.errors.line_name }"
                            />
                            <p v-if="createForm.errors.line_name" class="text-red-500 text-sm mt-1">
                                {{ createForm.errors.line_name }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">
                                Plant <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="createForm.plant"
                                type="text"
                                required
                                placeholder="Contoh: Plant A"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                                :class="{ 'border-red-500': createForm.errors.plant }"
                            />
                            <p v-if="createForm.errors.plant" class="text-red-500 text-sm mt-1">
                                {{ createForm.errors.plant }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Deskripsi</label>
                            <textarea
                                v-model="createForm.description"
                                rows="3"
                                placeholder="Deskripsi line (opsional)"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            ></textarea>
                        </div>

                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                            <p class="text-sm text-blue-800 dark:text-blue-300">
                                💡 Kode line dan QR Code akan di-generate otomatis setelah disimpan.
                            </p>
                        </div>

                        <div class="flex gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                @click="showCreateModal = false"
                                :disabled="createForm.processing"
                                class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent disabled:opacity-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                            >
                                <Plus class="w-4 h-4" />
                                {{ createForm.processing ? 'Menyimpan...' : 'Tambah Line' }}
                            </button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Modal Edit Line -->
            <Dialog :open="showEditModal" @update:open="showEditModal = $event">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit Line</DialogTitle>
                        <DialogDescription>
                            Update informasi line produksi
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submitEdit" class="space-y-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">
                                Nama Line <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="editForm.line_name"
                                type="text"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                                :class="{ 'border-red-500': editForm.errors.line_name }"
                            />
                            <p v-if="editForm.errors.line_name" class="text-red-500 text-sm mt-1">
                                {{ editForm.errors.line_name }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">
                                Plant <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="editForm.plant"
                                type="text"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                                :class="{ 'border-red-500': editForm.errors.plant }"
                            />
                            <p v-if="editForm.errors.plant" class="text-red-500 text-sm mt-1">
                                {{ editForm.errors.plant }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Deskripsi</label>
                            <textarea
                                v-model="editForm.description"
                                rows="3"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                            ></textarea>
                        </div>

                        <div class="flex gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                @click="closeEditModal"
                                :disabled="editForm.processing"
                                class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent disabled:opacity-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="editForm.processing"
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ editForm.processing ? 'Menyimpan...' : 'Update Line' }}
                            </button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Modal View & Print QR Code -->
            <Dialog :open="showQrModal" @update:open="showQrModal = $event">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>QR Code Line</DialogTitle>
                        <DialogDescription v-if="selectedLine">
                            {{ selectedLine.line_name }}
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="selectedLine" class="space-y-4 mt-4">
                        <div id="qrcode-print-area" class="bg-white border-2 border-gray-300 rounded-lg p-6 text-center">
                            <div class="mb-4">
                                <h3 class="text-lg font-bold text-gray-800">{{ selectedLine.line_name }}</h3>
                                <p class="text-sm text-gray-600">{{ selectedLine.plant }} - {{ selectedLine.line_code }}</p>
                            </div>

                            <div class="bg-white p-6 rounded border border-gray-200 flex justify-center">
                                <div id="qrcode-display" class="w-64 h-64 flex items-center justify-center"></div>
                            </div>
                        </div>

                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-2">
                            <p class="text-sm text-center text-blue-800 dark:text-blue-300">
                                💡 <strong>Tips :</strong> <br> Cetak QR Code ini dan tempel di area line untuk memudahkan scanning saat start operation atau line stop.
                            </p>
                        </div>

                        <div class="flex gap-2 pt-4">
                            <button
                                @click="closeQrModal"
                                class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-sidebar-accent"
                            >
                                Tutup
                            </button>
                            <button
                                @click="printQrCode"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                            >
                                <Printer class="w-4 h-4" />
                                Cetak QR Code
                            </button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #qrcode-print-area,
    #qrcode-print-area * {
        visibility: visible;
    }
    #qrcode-print-area {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
    }
}
</style>
