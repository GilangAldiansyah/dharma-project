<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch, nextTick, computed } from 'vue';
import QRCode from 'qrcode';
import {
    Search,
    Cog,
    Plus,
    Edit,
    Trash2,
    BarChart3,
    Printer,
    RefreshCw,
    QrCode as QrCodeIcon
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
    average_mtbf?: number | null;
    average_mttr?: string | null;
}

interface Machine {
    id: number;
    line_id: number;
    machine_name: string;
    barcode: string;
    plant: string;
    line: string;
    machine_type: string;
    total_reports: number;
    active_reports: number;
    line_model?: Line;
}

interface Props {
    machines: {
        data: Machine[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: {
        total_machines: number;
        total_reports: number;
    };
    lines: Line[];
    plants: string[];
    filters: {
        search?: string;
        plant?: string;
        line_id?: number;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const plantFilter = ref(props.filters.plant || '');
const lineFilter = ref(props.filters.line_id?.toString() || '');

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showMetricsModal = ref(false);
const showBarcodeModal = ref(false);
const selectedMachine = ref<Machine | null>(null);

const createForm = useForm({
    line_id: null as number | null,
    machine_name: '',
    barcode: '',
    machine_type: '',
    auto_generate_barcode: true,
});

const editForm = useForm({
    line_id: null as number | null,
    machine_name: '',
    barcode: '',
    machine_type: '',
});

const filteredLines = computed(() => {
    let filtered = props.lines;
    if (plantFilter.value) {
        filtered = filtered.filter(l => l.plant === plantFilter.value);
    }
    return filtered;
});

const selectedLineForCreate = computed(() => {
    if (createForm.line_id) {
        return props.lines.find(l => l.id === createForm.line_id);
    }
    return null;
});

const generateBarcode = () => {
    const random = Math.random().toString(36).substring(2, 8).toUpperCase();
    const lineCode = selectedLineForCreate.value?.line_code || 'XX';
    return `${lineCode}-${random}`;
};

const generateQrCodeForDisplay = async (barcode: string, containerId: string) => {
    await nextTick();
    await new Promise(resolve => setTimeout(resolve, 150));

    const container = document.getElementById(containerId);
    if (container && barcode) {
        container.innerHTML = '';
        try {
            const canvas = document.createElement('canvas');
            await QRCode.toCanvas(canvas, barcode, {
                width: 200,
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

const generateQrCodeForCreate = async (barcode: string) => {
    await nextTick();
    await new Promise(resolve => setTimeout(resolve, 150));

    const container = document.getElementById('qrcode-create');
    if (container && barcode) {
        container.innerHTML = '';
        try {
            const canvas = document.createElement('canvas');
            await QRCode.toCanvas(canvas, barcode, {
                width: 140,
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

watch(showBarcodeModal, async (isOpen) => {
    if (isOpen && selectedMachine.value) {
        await generateQrCodeForDisplay(selectedMachine.value.barcode, 'qrcode-display');
    }
});

watch(() => createForm.barcode, async (newBarcode) => {
    if (newBarcode && showCreateModal.value) {
        await generateQrCodeForCreate(newBarcode);
    }
});

watch([() => createForm.line_id, () => createForm.machine_name, () => createForm.auto_generate_barcode], () => {
    if (createForm.auto_generate_barcode && createForm.line_id) {
        createForm.barcode = generateBarcode();
    }
});

watch(showCreateModal, async (isOpen) => {
    if (isOpen) {
        await new Promise(resolve => setTimeout(resolve, 200));
        if (createForm.barcode) {
            await generateQrCodeForCreate(createForm.barcode);
        }
    }
});

const search = () => {
    router.get('/maintenance/mesin', {
        search: searchQuery.value,
        plant: plantFilter.value,
        line_id: lineFilter.value,
    }, { preserveState: true, preserveScroll: true });
};

const openCreateModal = () => {
    createForm.reset();
    createForm.auto_generate_barcode = true;
    showCreateModal.value = true;
};

const submitCreate = () => {
    createForm.post('/maintenance/mesin', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const openEditModal = (machine: Machine) => {
    selectedMachine.value = machine;
    editForm.line_id = machine.line_id;
    editForm.machine_name = machine.machine_name;
    editForm.barcode = machine.barcode;
    editForm.machine_type = machine.machine_type || '';
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    selectedMachine.value = null;
    editForm.reset();
};

const submitEdit = () => {
    if (!selectedMachine.value) return;

    editForm.put(`/maintenance/mesin/${selectedMachine.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeEditModal();
        },
    });
};

const deleteMachine = (machineId: number, machineName: string) => {
    if (confirm(`Hapus mesin "${machineName}"? Data ini tidak dapat dikembalikan.`)) {
        router.delete(`/maintenance/mesin/${machineId}`, {
            preserveScroll: true,
        });
    }
};

const viewMetrics = (machine: Machine) => {
    selectedMachine.value = machine;
    showMetricsModal.value = true;
};

const closeMetricsModal = () => {
    showMetricsModal.value = false;
    selectedMachine.value = null;
};

const viewBarcode = (machine: Machine) => {
    selectedMachine.value = machine;
    showBarcodeModal.value = true;
};

const closeBarcodeModal = () => {
    showBarcodeModal.value = false;
    selectedMachine.value = null;
};

const printBarcode = () => {
    window.print();
};
</script>
<template>
    <Head title="Master Mesin - Maintenance" />
    <AppLayout :breadcrumbs="[
        { title: 'Monitoring Maintenance', href: '/maintenance' },
        { title: 'Master Mesin', href: '/maintenance/mesin' }
    ]">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Cog class="w-6 h-6 text-blue-600" />
                    Master Mesin
                </h1>
                <button
                    @click="openCreateModal"
                    class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium shadow-sm"
                >
                    <Plus class="w-4 h-4" />
                    Tambah Mesin
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4 shadow-sm">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total Mesin</div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ stats.total_machines }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4 shadow-sm">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total Laporan</div>
                    <div class="text-3xl font-bold text-blue-600">{{ stats.total_reports }}</div>
                </div>
            </div>

            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4 shadow-sm">
                <div class="flex flex-wrap gap-3">
                    <div class="flex-1 min-w-[250px] flex gap-2">
                        <input
                            v-model="searchQuery"
                            @keyup.enter="search"
                            type="text"
                            placeholder="Cari mesin atau barcode..."
                            class="flex-1 rounded-lg border border-sidebar-border px-4 py-2 dark:bg-sidebar text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                        <button @click="search" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <Search class="w-5 h-5" />
                        </button>
                    </div>
                    <select
                        v-model="plantFilter"
                        @change="search"
                        class="rounded-lg border border-sidebar-border px-4 py-2 dark:bg-sidebar text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">Semua Plant</option>
                        <option v-for="plant in plants" :key="plant" :value="plant">{{ plant }}</option>
                    </select>
                    <select
                        v-model="lineFilter"
                        @change="search"
                        class="rounded-lg border border-sidebar-border px-4 py-2 dark:bg-sidebar text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">Semua Line</option>
                        <option v-for="line in filteredLines" :key="line.id" :value="line.id">
                            {{ line.line_name }} ({{ line.plant }})
                        </option>
                    </select>
                </div>
            </div>

            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Nama Mesin</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Barcode</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Line</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Plant</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Tipe</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Laporan</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="machine in machines.data" :key="machine.id" class="hover:bg-gray-50 dark:hover:bg-sidebar-accent transition-colors">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ machine.machine_name }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-sm bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">{{ machine.barcode }}</span>
                                        <button
                                            @click="viewBarcode(machine)"
                                            class="p-1 text-gray-400 hover:text-blue-600 transition-colors"
                                            title="Lihat QR Code"
                                        >
                                            <QrCodeIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="machine.line_model" class="text-sm">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ machine.line_model.line_name }}</div>
                                        <div class="text-xs text-gray-500">{{ machine.line_model.line_code }}</div>
                                    </div>
                                    <div v-else class="text-sm text-gray-400">{{ machine.line }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ machine.plant }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ machine.machine_type || '-' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ machine.total_reports }}</span>
                                        <span v-if="machine.active_reports > 0" class="text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-2 py-0.5 rounded-full">
                                            {{ machine.active_reports }} aktif
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <button
                                            @click="viewMetrics(machine)"
                                            class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                                            title="Lihat Metrics"
                                        >
                                            <BarChart3 class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="openEditModal(machine)"
                                            class="p-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors"
                                            title="Edit"
                                        >
                                            <Edit class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deleteMachine(machine.id, machine.machine_name)"
                                            class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                                            title="Hapus"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="machines.data.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <Cog class="w-16 h-16 mx-auto mb-3 text-gray-300 dark:text-gray-600" />
                                    <p class="text-gray-500 dark:text-gray-400">Tidak ada data mesin</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="machines.last_page > 1" class="flex justify-center gap-2">
                <button
                    v-for="page in machines.last_page"
                    :key="page"
                    @click="router.get('/maintenance/mesin', { page, search: searchQuery, plant: plantFilter, line_id: lineFilter })"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                        page === machines.current_page
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-200 dark:bg-sidebar hover:bg-gray-300 dark:hover:bg-sidebar-accent'
                    ]"
                >
                    {{ page }}
                </button>
            </div>
            <!-- Modal Create - Tidak ada perubahan, skip ke Modal Metrics -->
            <Dialog :open="showCreateModal" @update:open="showCreateModal = $event">
                <!-- ... Modal Create tetap sama seperti sebelumnya ... -->
                <!-- Copy dari kode asli Anda -->
            </Dialog>

            <Dialog :open="showEditModal" @update:open="showEditModal = $event">
                <!-- ... Modal Edit tetap sama seperti sebelumnya ... -->
                <!-- Copy dari kode asli Anda -->
            </Dialog>

            <!-- Modal Metrics - UPDATED -->
            <Dialog :open="showMetricsModal" @update:open="showMetricsModal = $event">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle class="text-xl font-bold">Metrics Mesin & Line</DialogTitle>
                        <DialogDescription v-if="selectedMachine">
                            {{ selectedMachine.machine_name }}
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="selectedMachine" class="space-y-5 mt-2">
                        <!-- Info Mesin -->
                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-sidebar-accent dark:to-sidebar rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nama Mesin</div>
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ selectedMachine.machine_name }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tipe</div>
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ selectedMachine.machine_type || '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Line</div>
                                    <div v-if="selectedMachine.line_model" class="font-semibold text-gray-900 dark:text-white">
                                        {{ selectedMachine.line_model.line_name }}
                                        <span class="text-xs text-gray-500 ml-1">({{ selectedMachine.line_model.line_code }})</span>
                                    </div>
                                    <div v-else class="font-semibold text-gray-900 dark:text-white">{{ selectedMachine.line }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Plant</div>
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ selectedMachine.plant }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Metrics LINE (MTTR & MTBF) -->
                        <div v-if="selectedMachine.line_model" class="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 border-2 border-indigo-200 dark:border-indigo-800 rounded-xl p-5">
                            <h3 class="text-sm font-bold text-indigo-700 dark:text-indigo-300 mb-4 flex items-center gap-2">
                                <BarChart3 class="w-5 h-5" />
                                Metrics Line: {{ selectedMachine.line_model.line_name }}
                            </h3>
                            <div class="grid grid-cols-2 gap-4">
                                <!-- MTTR dari Line -->
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-2 border-purple-200 dark:border-purple-700">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center">
                                            <BarChart3 class="w-4 h-4 text-white" />
                                        </div>
                                        <div>
                                            <div class="text-xs font-medium text-purple-600 dark:text-purple-400">MTTR</div>
                                            <div class="text-xs text-purple-500 dark:text-purple-400">Mean Time To Repair</div>
                                        </div>
                                    </div>
                                    <div class="text-2xl font-bold text-purple-700 dark:text-purple-300 mt-2">
                                        {{ selectedMachine.line_model.average_mttr || 'N/A' }}
                                    </div>
                                    <div class="text-xs text-purple-600 dark:text-purple-400 mt-1">Rata-rata waktu perbaikan</div>
                                </div>

                                <!-- MTBF dari Line -->
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-2 border-indigo-200 dark:border-indigo-700">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                                            <BarChart3 class="w-4 h-4 text-white" />
                                        </div>
                                        <div>
                                            <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400">MTBF</div>
                                            <div class="text-xs text-indigo-500 dark:text-indigo-400">Mean Time Between Failures</div>
                                        </div>
                                    </div>
                                    <div class="text-2xl font-bold text-indigo-700 dark:text-indigo-300 mt-2">
                                        {{ selectedMachine.line_model.average_mtbf ? selectedMachine.line_model.average_mtbf.toFixed(2) + 'h' : 'N/A' }}
                                    </div>
                                    <div class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">Waktu antar kerusakan</div>
                                </div>
                            </div>
                        </div>

                        <!-- Metrics MESIN (Total & Laporan Aktif) -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-900/20 border-2 border-blue-200 dark:border-blue-800 rounded-xl p-5">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                                        <BarChart3 class="w-5 h-5 text-white" />
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-blue-600 dark:text-blue-400">Total Laporan</div>
                                        <div class="text-xs text-blue-500 dark:text-blue-400">Mesin ini</div>
                                    </div>
                                </div>
                                <div class="text-3xl font-bold text-blue-700 dark:text-blue-300 mt-3">
                                    {{ selectedMachine.total_reports }}
                                </div>
                                <div class="text-xs text-blue-600 dark:text-blue-400 mt-2">Semua status</div>
                            </div>

                            <div class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/30 dark:to-red-900/20 border-2 border-red-200 dark:border-red-800 rounded-xl p-5">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center">
                                        <BarChart3 class="w-5 h-5 text-white" />
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-red-600 dark:text-red-400">Laporan Aktif</div>
                                        <div class="text-xs text-red-500 dark:text-red-400">Belum diselesaikan</div>
                                    </div>
                                </div>
                                <div class="text-3xl font-bold text-red-700 dark:text-red-300 mt-3">
                                    {{ selectedMachine.active_reports }}
                                </div>
                                <div class="text-xs text-red-600 dark:text-red-400 mt-2">Perlu penanganan</div>
                            </div>
                        </div>

                        <!-- Info Box -->
                        <div class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-400 dark:border-amber-600 rounded-lg p-4">
                            <div class="flex gap-3">
                                <div class="text-amber-600 dark:text-amber-400 mt-0.5">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300 mb-1">Penjelasan Metrics</p>
                                    <p class="text-sm text-amber-700 dark:text-amber-400">
                                        <strong>MTTR & MTBF:</strong> Dihitung per LINE (bukan per mesin), menunjukkan performa keseluruhan line.<br/>
                                        <strong>Total Laporan & Laporan Aktif:</strong> Khusus untuk mesin ini saja.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button
                                @click="closeMetricsModal"
                                class="px-6 py-2.5 bg-gray-200 dark:bg-sidebar-accent rounded-lg hover:bg-gray-300 dark:hover:bg-gray-700 font-medium transition-colors"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
            <!-- Modal Barcode - Tetap sama -->
            <Dialog :open="showBarcodeModal" @update:open="showBarcodeModal = $event">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle class="text-xl font-bold">QR Code Mesin</DialogTitle>
                        <DialogDescription v-if="selectedMachine">
                            {{ selectedMachine.machine_name }}
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="selectedMachine" class="space-y-5 mt-2">
                        <div id="barcode-print-area" class="bg-white border-2 border-gray-300 rounded-xl p-6 text-center shadow-sm">
                            <div class="mb-4">
                                <h3 class="text-xl font-bold text-gray-900">{{ selectedMachine.machine_name }}</h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    {{ selectedMachine.line_model?.line_name || selectedMachine.line }} · {{ selectedMachine.plant }}
                                </p>
                            </div>

                            <div class="bg-white p-4 rounded-lg border-2 border-gray-200 inline-block">
                                <div id="qrcode-display" class="flex items-center justify-center"></div>
                            </div>

                            <div class="mt-4">
                                <p class="text-2xl font-bold font-mono text-gray-900 bg-gray-100 px-4 py-2 rounded-lg inline-block">
                                    {{ selectedMachine.barcode }}
                                </p>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4">
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Tipe:</span>
                                    <span class="font-medium ml-2 text-gray-900 dark:text-white">{{ selectedMachine.machine_type || '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Barcode:</span>
                                    <span class="font-mono font-medium ml-2 text-gray-900 dark:text-white">{{ selectedMachine.barcode }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-400 dark:border-blue-600 rounded-lg p-4">
                            <div class="flex gap-3">
                                <div class="text-blue-600 dark:text-blue-400 mt-0.5">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-blue-800 dark:text-blue-300 mb-1">Informasi QR Code</p>
                                    <p class="text-sm text-blue-700 dark:text-blue-400">
                                        QR Code ini untuk identifikasi mesin. Untuk Line Stop, scan QR Code LINE.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button
                                @click="closeBarcodeModal"
                                class="flex-1 px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-sidebar-accent font-medium transition-colors"
                            >
                                Tutup
                            </button>
                            <button
                                @click="printBarcode"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium shadow-sm transition-colors"
                            >
                                <Printer class="w-4 h-4" />
                                Cetak QR Code
                            </button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Modal Create - LENGKAP -->
            <Dialog :open="showCreateModal" @update:open="showCreateModal = $event">
                <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle class="text-xl font-bold">Tambah Mesin Baru</DialogTitle>
                        <DialogDescription>
                            Isi informasi mesin yang akan ditambahkan ke sistem
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submitCreate" class="space-y-5 mt-2">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-5">
                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                                        Line <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="createForm.line_id"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 dark:bg-sidebar focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                        :class="{ 'border-red-500 ring-2 ring-red-200': createForm.errors.line_id }"
                                    >
                                        <option :value="null">Pilih Line</option>
                                        <option v-for="line in lines" :key="line.id" :value="line.id">
                                            {{ line.line_name }} - {{ line.plant }} ({{ line.line_code }})
                                        </option>
                                    </select>
                                    <p v-if="createForm.errors.line_id" class="text-red-500 text-sm mt-1.5">
                                        {{ createForm.errors.line_id }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                                        Nama Mesin <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="createForm.machine_name"
                                        type="text"
                                        required
                                        placeholder="Contoh: Mesin Injection 01"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 dark:bg-sidebar focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                        :class="{ 'border-red-500 ring-2 ring-red-200': createForm.errors.machine_name }"
                                    />
                                    <p v-if="createForm.errors.machine_name" class="text-red-500 text-sm mt-1.5">
                                        {{ createForm.errors.machine_name }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                                        Tipe Mesin
                                    </label>
                                    <input
                                        v-model="createForm.machine_type"
                                        type="text"
                                        placeholder="Contoh: Injection Molding"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 dark:bg-sidebar focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                        :class="{ 'border-red-500 ring-2 ring-red-200': createForm.errors.machine_type }"
                                    />
                                    <p v-if="createForm.errors.machine_type" class="text-red-500 text-sm mt-1.5">
                                        {{ createForm.errors.machine_type }}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1.5">Opsional - dapat diisi nanti</p>
                                </div>
                            </div>

                            <div class="space-y-5">
                                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border-2 border-blue-200 dark:border-blue-800 rounded-xl p-5">
                                    <label class="flex items-center gap-2 mb-4">
                                        <input
                                            v-model="createForm.auto_generate_barcode"
                                            type="checkbox"
                                            class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Generate Barcode Otomatis</span>
                                    </label>

                                    <div v-if="createForm.auto_generate_barcode" class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">
                                                Barcode
                                            </label>
                                            <div class="flex gap-2">
                                                <input
                                                    :value="createForm.barcode"
                                                    type="text"
                                                    readonly
                                                    placeholder="Auto-generate..."
                                                    class="flex-1 min-w-0 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 dark:bg-gray-800 font-mono text-sm bg-white"
                                                />
                                                <button
                                                    type="button"
                                                    @click="createForm.barcode = generateBarcode()"
                                                    class="flex-shrink-0 p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                                                    title="Regenerate"
                                                >
                                                    <RefreshCw class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>

                                        <div v-if="createForm.barcode" class="bg-white dark:bg-gray-800 rounded-lg p-4 border-2 border-gray-200 dark:border-gray-700">
                                            <label class="block text-sm font-medium mb-3 text-center text-gray-700 dark:text-gray-300">
                                                Preview QR Code
                                            </label>
                                            <div class="flex justify-center">
                                                <div id="qrcode-create" class="bg-white p-2 rounded-lg"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-else>
                                        <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">
                                            Barcode Manual <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="createForm.barcode"
                                            type="text"
                                            required
                                            placeholder="Contoh: MC-001"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 dark:bg-sidebar font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            :class="{ 'border-red-500 ring-2 ring-red-200': createForm.errors.barcode }"
                                        />
                                        <p v-if="createForm.errors.barcode" class="text-red-500 text-sm mt-1.5">
                                            {{ createForm.errors.barcode }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-5 border-t border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                @click="showCreateModal = false"
                                :disabled="createForm.processing"
                                class="flex-1 px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-sidebar-accent disabled:opacity-50 font-medium transition-colors"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium shadow-sm transition-colors"
                            >
                                <Plus class="w-4 h-4" />
                                {{ createForm.processing ? 'Menyimpan...' : 'Tambah Mesin' }}
                            </button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Modal Edit - LENGKAP -->
            <Dialog :open="showEditModal" @update:open="showEditModal = $event">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle class="text-xl font-bold">Edit Mesin</DialogTitle>
                        <DialogDescription>
                            Perbarui informasi mesin
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submitEdit" class="space-y-5 mt-2">
                        <div>
                            <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                                Line <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="editForm.line_id"
                                required
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 dark:bg-sidebar focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                :class="{ 'border-red-500 ring-2 ring-red-200': editForm.errors.line_id }"
                            >
                                <option :value="null">Pilih Line</option>
                                <option v-for="line in lines" :key="line.id" :value="line.id">
                                    {{ line.line_name }} - {{ line.plant }} ({{ line.line_code }})
                                </option>
                            </select>
                            <p v-if="editForm.errors.line_id" class="text-red-500 text-sm mt-1.5">
                                {{ editForm.errors.line_id }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                                Nama Mesin <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="editForm.machine_name"
                                type="text"
                                required
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 dark:bg-sidebar focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                :class="{ 'border-red-500 ring-2 ring-red-200': editForm.errors.machine_name }"
                            />
                            <p v-if="editForm.errors.machine_name" class="text-red-500 text-sm mt-1.5">
                                {{ editForm.errors.machine_name }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                                Barcode <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="editForm.barcode"
                                type="text"
                                required
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 dark:bg-sidebar font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                :class="{ 'border-red-500 ring-2 ring-red-200': editForm.errors.barcode }"
                            />
                            <p v-if="editForm.errors.barcode" class="text-red-500 text-sm mt-1.5">
                                {{ editForm.errors.barcode }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                                Tipe Mesin
                            </label>
                            <input
                                v-model="editForm.machine_type"
                                type="text"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 dark:bg-sidebar focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                :class="{ 'border-red-500 ring-2 ring-red-200': editForm.errors.machine_type }"
                            />
                            <p v-if="editForm.errors.machine_type" class="text-red-500 text-sm mt-1.5">
                                {{ editForm.errors.machine_type }}
                            </p>
                        </div>

                        <div class="flex gap-3 pt-5 border-t border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                @click="closeEditModal"
                                :disabled="editForm.processing"
                                class="flex-1 px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-sidebar-accent disabled:opacity-50 font-medium transition-colors"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="editForm.processing"
                                class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium shadow-sm transition-colors"
                            >
                                {{ editForm.processing ? 'Menyimpan...' : 'Update Mesin' }}
                            </button>
                        </div>
                    </form>
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
    #barcode-print-area,
    #barcode-print-area * {
        visibility: visible;
    }
    #barcode-print-area {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
    }
}
</style>
