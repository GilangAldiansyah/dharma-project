<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { Scan, Plus, Search, ArrowUpCircle, ArrowDownCircle, X, AlertCircle, CheckCircle, Info, Edit, Trash2, Database, History } from 'lucide-vue-next';

interface Product {
    id: string;
    product_code: string;
    product_name: string;
    customer: string;
    line: {
        id: number;
        line_name: string;
    };
}

interface Kanban {
    id: string;
    rfid_tag: string;
    kanban_no: string;
    scan_type: string;
    route: string;
    address: string;
    packaging_type: string;
    quantity: number;
    scanned_at: string;
    operator_name: string;
    shift: number;
    product: Product;
}

interface RfidMaster {
    id: number;
    rfid_tag: string;
    product_id: string;
    scan_type: string;
    route: string;
    address: string;
    packaging_type: string;
    quantity: number;
    operator_name: string;
    shift: number;
    notes: string;
    created_at: string;
    product: Product;
}

interface Props {
    kanbans: {
        data: Kanban[];
        current_page: number;
        last_page: number;
        total: number;
    };
    rfidMasters: {
        data: RfidMaster[];
        current_page: number;
        last_page: number;
        total: number;
    };
    products: Product[];
    filters: {
        search?: string;
        product_id?: string;
        scan_type?: string;
        date?: string;
        master_search?: string;
    };
}

const props = defineProps<Props>();

const activeTab = ref<'scans' | 'masters'>('scans');
const search = ref(props.filters.search || '');
const masterSearch = ref(props.filters.master_search || '');
const scanType = ref(props.filters.scan_type || '');
const selectedDate = ref(props.filters.date || '');
const showManualModal = ref(false);
const showEditMasterModal = ref(false);
const showDeleteMasterModal = ref(false);
const showWarningModal = ref(false);
const selectedMaster = ref<RfidMaster | null>(null);
const warningData = ref<any>(null);
const pendingRfidScan = ref('');

const rfidBuffer = ref('');
const isProcessing = ref(false);

const manualForm = ref({
    rfid_tag: '',
    product_id: '',
    scan_type: 'in',
    route: '',
    address: '',
    packaging_type: '',
    quantity: 1,
    operator_name: '',
    shift: 1,
    notes: ''
});

const editMasterForm = ref({
    product_id: '',
    scan_type: 'in',
    route: '',
    address: '',
    packaging_type: '',
    quantity: 1,
    operator_name: '',
    shift: 1,
    notes: ''
});

const errors = ref<Record<string, string>>({});
const rfidStatus = ref<{
    type: 'new' | 'registered' | 'used_today' | 'error' | 'success' | null;
    message: string;
}>({
    type: null,
    message: ''
});

let bufferTimeout: any = null;

watch([search, scanType, selectedDate], () => {
    router.get('/kanbans', {
        search: search.value,
        scan_type: scanType.value,
        date: selectedDate.value,
        master_search: masterSearch.value
    }, {
        preserveState: true,
        preserveScroll: true
    });
}, { deep: true });

watch(masterSearch, () => {
    router.get('/kanbans', {
        master_search: masterSearch.value,
        search: search.value,
        scan_type: scanType.value,
        date: selectedDate.value
    }, {
        preserveState: true,
        preserveScroll: true
    });
});

const clearFilters = () => {
    search.value = '';
    scanType.value = '';
    selectedDate.value = '';
    masterSearch.value = '';
};

const openManualModal = () => {
    manualForm.value = {
        rfid_tag: '',
        product_id: '',
        scan_type: 'in',
        route: '',
        address: '',
        packaging_type: '',
        quantity: 1,
        operator_name: '',
        shift: 1,
        notes: ''
    };
    errors.value = {};
    showManualModal.value = true;
};

const closeManualModal = () => {
    showManualModal.value = false;
    manualForm.value = {
        rfid_tag: '',
        product_id: '',
        scan_type: 'in',
        route: '',
        address: '',
        packaging_type: '',
        quantity: 1,
        operator_name: '',
        shift: 1,
        notes: ''
    };
    errors.value = {};
};

const openEditMasterModal = (master: RfidMaster) => {
    selectedMaster.value = master;
    editMasterForm.value = {
        product_id: master.product_id,
        scan_type: master.scan_type,
        route: master.route || '',
        address: master.address || '',
        packaging_type: master.packaging_type || '',
        quantity: master.quantity,
        operator_name: master.operator_name || '',
        shift: master.shift,
        notes: master.notes || ''
    };
    errors.value = {};
    showEditMasterModal.value = true;
};

const closeEditMasterModal = () => {
    showEditMasterModal.value = false;
    selectedMaster.value = null;
    errors.value = {};
};

const openDeleteMasterModal = (master: RfidMaster) => {
    selectedMaster.value = master;
    showDeleteMasterModal.value = true;
};

const closeDeleteMasterModal = () => {
    showDeleteMasterModal.value = false;
    selectedMaster.value = null;
};

const checkRfidMasterData = async (rfidTag: string) => {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) return null;

        const response = await fetch('/kanbans/rfid-master', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ rfid_tag: rfidTag })
        });

        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return await response.json();
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    } catch (error) {
        return null;
    }
};

const confirmWarningAndProceed = () => {
    if (!warningData.value || !pendingRfidScan.value) return;

    const formData = {
        rfid_tag: pendingRfidScan.value,
        product_id: warningData.value.data.product_id,
        scan_type: warningData.value.data.last_scan_type,
        route: warningData.value.data.route,
        address: warningData.value.data.address,
        packaging_type: warningData.value.data.packaging_type,
        quantity: warningData.value.data.quantity,
        operator_name: warningData.value.data.operator_name,
        shift: warningData.value.data.shift,
        force_scan: true
    };

    showWarningModal.value = false;
    rfidStatus.value = {
        type: null,
        message: 'Memproses scan...'
    };

    router.post('/kanbans', formData, {
        onError: () => {
            rfidStatus.value = {
                type: 'error',
                message: 'Gagal memproses scan'
            };
            isProcessing.value = false;
            setTimeout(() => {
                rfidStatus.value = { type: null, message: '' };
            }, 3000);
        },
        onSuccess: () => {
            const productName = warningData.value.data.product.product_name;
            const scanTypeText = warningData.value.data.last_scan_type.toUpperCase();
            rfidStatus.value = {
                type: 'success',
                message: `Scan berhasil! ${productName} - ${scanTypeText}`
            };
            isProcessing.value = false;
            setTimeout(() => {
                rfidStatus.value = { type: null, message: '' };
            }, 3000);
        }
    });

    warningData.value = null;
    pendingRfidScan.value = '';
};

const cancelWarning = () => {
    showWarningModal.value = false;
    warningData.value = null;
    pendingRfidScan.value = '';
    isProcessing.value = false;
    rfidStatus.value = { type: null, message: '' };
};

const processRfidScan = async (rfidTag: string) => {
    if (isProcessing.value) return;

    isProcessing.value = true;
    rfidStatus.value = {
        type: null,
        message: 'Memeriksa RFID...'
    };

    const rfidData = await checkRfidMasterData(rfidTag);

    if (!rfidData) {
        rfidStatus.value = {
            type: 'error',
            message: 'Error memeriksa RFID. Silakan coba lagi.'
        };
        isProcessing.value = false;
        setTimeout(() => {
            rfidStatus.value = { type: null, message: '' };
        }, 3000);
        return;
    }

    if (rfidData.is_new) {
        rfidStatus.value = {
            type: 'new',
            message: 'RFID Baru Terdeteksi - Membuka Form Registrasi'
        };
        manualForm.value.rfid_tag = rfidTag;
        openManualModal();
        isProcessing.value = false;
        setTimeout(() => {
            rfidStatus.value = { type: null, message: '' };
        }, 2000);
        return;
    }

    if (rfidData.is_used_today) {
        warningData.value = rfidData;
        pendingRfidScan.value = rfidTag;
        showWarningModal.value = true;
        return;
    }

    const formData = {
        rfid_tag: rfidTag,
        product_id: rfidData.data.product_id,
        scan_type: rfidData.data.last_scan_type,
        route: rfidData.data.route,
        address: rfidData.data.address,
        packaging_type: rfidData.data.packaging_type,
        quantity: rfidData.data.quantity,
        operator_name: rfidData.data.operator_name,
        shift: rfidData.data.shift,
        force_scan: false
    };

    rfidStatus.value = {
        type: null,
        message: 'Memproses scan...'
    };

    router.post('/kanbans', formData, {
        onError: () => {
            rfidStatus.value = {
                type: 'error',
                message: 'Gagal memproses scan'
            };
            isProcessing.value = false;
            setTimeout(() => {
                rfidStatus.value = { type: null, message: '' };
            }, 3000);
        },
        onSuccess: () => {
            const productName = rfidData.data.product.product_name;
            const scanTypeText = rfidData.data.last_scan_type.toUpperCase();
            rfidStatus.value = {
                type: 'success',
                message: `Scan berhasil! ${productName} - ${scanTypeText}`
            };
            isProcessing.value = false;
            setTimeout(() => {
                rfidStatus.value = { type: null, message: '' };
            }, 3000);
        }
    });
};

const handleKeyPress = (event: KeyboardEvent) => {
    if (showManualModal.value || showEditMasterModal.value || showWarningModal.value) return;

    if (event.key === 'Enter') {
        if (rfidBuffer.value.length > 0) {
            const scannedRfid = rfidBuffer.value.trim();
            processRfidScan(scannedRfid);
            rfidBuffer.value = '';
        }
        return;
    }

    if (event.key.length === 1) {
        rfidBuffer.value += event.key;

        if (bufferTimeout) clearTimeout(bufferTimeout);
        bufferTimeout = setTimeout(() => {
            rfidBuffer.value = '';
        }, 100);
    }
};

const handleManualSubmit = () => {
    router.post('/kanbans/master', manualForm.value, {
        onError: (err) => {
            errors.value = err;
        },
        onSuccess: () => {
            closeManualModal();
            activeTab.value = 'masters';
        }
    });
};

const handleEditMasterSubmit = () => {
    if (!selectedMaster.value) return;

    router.put(`/kanbans/master/${selectedMaster.value.id}`, editMasterForm.value, {
        onError: (err) => {
            errors.value = err;
        },
        onSuccess: () => {
            closeEditMasterModal();
        }
    });
};

const handleDeleteMaster = () => {
    if (!selectedMaster.value) return;

    router.delete(`/kanbans/master/${selectedMaster.value.id}`, {
        onSuccess: () => {
            closeDeleteMasterModal();
        }
    });
};

const getScanTypeBadgeClass = (type: string) => {
    return type === 'in'
        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
};

const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

onMounted(() => {
    window.addEventListener('keypress', handleKeyPress);
});

onUnmounted(() => {
    if (bufferTimeout) clearTimeout(bufferTimeout);
    window.removeEventListener('keypress', handleKeyPress);
});
</script>

<template>
    <Head title="Kanban Scans" />
    <AppLayout :breadcrumbs="[
        { title: 'Kanban System', href: '/products' },
        { title: 'Scans', href: '/kanbans' }
    ]">
        <div class="p-6 space-y-6 bg-gray-50 dark:!bg-gray-900 min-h-screen">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent flex items-center gap-3">
                        <Scan class="w-8 h-8 text-blue-600" />
                        Kanban Scan
                    </h1>
                </div>
                <div class="flex gap-3">
                    <button @click="openManualModal" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium flex items-center gap-2">
                        <Plus class="w-5 h-5" />
                        Daftar RFID
                    </button>
                </div>
            </div>

            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 rounded-xl p-6 shadow-md border border-blue-100 dark:border-gray-600">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="bg-white dark:bg-gray-700 p-3 rounded-lg">
                            <Scan class="w-8 h-8 text-blue-600" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                </span>
                                Scanner Siap
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                {{ isProcessing ? 'Memproses...' : 'Scan RFID tag Anda' }}
                            </p>
                        </div>
                    </div>

                    <div v-if="rfidStatus.message" :class="[
                        'px-4 py-2 rounded-lg flex items-center gap-2',
                        rfidStatus.type === 'success' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' :
                        rfidStatus.type === 'new' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' :
                        rfidStatus.type === 'error' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' :
                        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                    ]">
                        <CheckCircle v-if="rfidStatus.type === 'success'" class="w-4 h-4" />
                        <Info v-else-if="rfidStatus.type === 'new'" class="w-4 h-4" />
                        <AlertCircle v-else class="w-4 h-4" />
                        <span class="text-sm font-medium">{{ rfidStatus.message }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <button
                        @click="activeTab = 'scans'"
                        :class="[
                            'flex-1 px-6 py-4 font-semibold transition-all flex items-center justify-center gap-2',
                            activeTab === 'scans'
                                ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 border-b-2 border-blue-600'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                        ]"
                    >
                        <History class="w-5 h-5" />
                        Riwayat Scan
                    </button>
                    <button
                        @click="activeTab = 'masters'"
                        :class="[
                            'flex-1 px-6 py-4 font-semibold transition-all flex items-center justify-center gap-2',
                            activeTab === 'masters'
                                ? 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 border-b-2 border-purple-600'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                        ]"
                    >
                        <Database class="w-5 h-5" />
                        Data RFID
                    </button>
                </div>

                <div v-show="activeTab === 'scans'" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Cari RFID, Kanban No..."
                                class="w-full pl-11 pr-4 py-3 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                            />
                        </div>
                        <select v-model="scanType" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                            <option value="">Semua Tipe</option>
                            <option value="in">IN</option>
                            <option value="out">OUT</option>
                        </select>
                        <input v-model="selectedDate" type="date" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                        <button @click="clearFilters" class="px-4 py-3 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all font-medium">
                            Reset
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Waktu Scan</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">RFID Tag</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Kanban No</th>
                                    <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Tipe</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Produk</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Rute</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Alamat</th>
                                    <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="kanban in kanbans.data" :key="kanban.id" class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ formatDateTime(kanban.scanned_at) }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-blue-600">{{ kanban.rfid_tag }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ kanban.kanban_no }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <span :class="['px-3 py-1 rounded-lg text-xs font-semibold inline-flex items-center gap-1', getScanTypeBadgeClass(kanban.scan_type)]">
                                            <ArrowUpCircle v-if="kanban.scan_type === 'in'" class="w-3 h-3" />
                                            <ArrowDownCircle v-else class="w-3 h-3" />
                                            {{ kanban.scan_type.toUpperCase() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ kanban.product.product_name }}</div>
                                        <div class="text-xs text-gray-500">{{ kanban.product.product_code }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ kanban.route || '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ kanban.address || '-' }}</td>
                                    <td class="px-6 py-4 text-center text-sm font-medium text-blue-600">{{ kanban.quantity }}</td>
                                </tr>
                                <tr v-if="kanbans.data.length === 0">
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        Tidak ada data scan
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="kanbans.last_page > 1" class="mt-6 flex justify-center gap-2">
                        <button
                            v-for="page in kanbans.last_page"
                            :key="page"
                            @click="router.get('/kanbans', { page, search: search, scan_type: scanType, date: selectedDate })"
                            :class="[
                                'px-4 py-2 rounded-lg font-medium transition-all',
                                page === kanbans.current_page
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                            ]"
                        >
                            {{ page }}
                        </button>
                    </div>
                </div>

                <div v-show="activeTab === 'masters'" class="p-6">
                    <div class="flex gap-4 mb-6">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="masterSearch"
                                type="text"
                                placeholder="Cari RFID tag, produk..."
                                class="w-full pl-11 pr-4 py-3 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                            />
                        </div>
                        <button @click="clearFilters" class="px-6 py-3 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all font-medium">
                            Reset
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">RFID Tag</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Produk</th>
                                    <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Tipe</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Rute</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Alamat</th>
                                    <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Qty</th>
                                    <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="master in rfidMasters.data" :key="master.id" class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                    <td class="px-6 py-4 text-sm font-medium text-blue-600">{{ master.rfid_tag }}</td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ master.product.product_name }}</div>
                                        <div class="text-xs text-gray-500">{{ master.product.product_code }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span :class="['px-3 py-1 rounded-lg text-xs font-semibold inline-flex items-center gap-1', getScanTypeBadgeClass(master.scan_type)]">
                                            <ArrowUpCircle v-if="master.scan_type === 'in'" class="w-3 h-3" />
                                            <ArrowDownCircle v-else class="w-3 h-3" />
                                            {{ master.scan_type.toUpperCase() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ master.route || '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ master.address || '-' }}</td>
                                    <td class="px-6 py-4 text-center text-sm font-medium text-blue-600">{{ master.quantity }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <button @click="openEditMasterModal(master)" class="p-2 bg-green-100 dark:bg-green-900/30 text-green-600 rounded-lg hover:bg-green-200 dark:hover:bg-green-900/50 transition-all">
                                                <Edit class="w-4 h-4" />
                                            </button>
                                            <button @click="openDeleteMasterModal(master)" class="p-2 bg-red-100 dark:bg-red-900/30 text-red-600 rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 transition-all">
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="rfidMasters.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        Tidak ada data RFID
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="rfidMasters.last_page > 1" class="mt-6 flex justify-center gap-2">
                        <button
                            v-for="page in rfidMasters.last_page"
                            :key="page"
                            @click="router.get('/kanbans', { master_page: page, master_search: masterSearch })"
                            :class="[
                                'px-4 py-2 rounded-lg font-medium transition-all',
                                page === rfidMasters.current_page
                                    ? 'bg-purple-600 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                            ]"
                        >
                            {{ page }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showWarningModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-3xl w-full max-w-md shadow-2xl overflow-hidden animate-scale-in">
                <div class="bg-gradient-to-r from-amber-500 to-orange-500 p-6 text-white">
                    <div class="flex items-center gap-4">
                        <div class="bg-white/20 p-3 rounded-full backdrop-blur-sm">
                            <AlertCircle class="w-8 h-8" />
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold">Warning!</h3>
                            <p class="text-amber-50 text-sm mt-1">RFID Sudah Di-scan Hari Ini</p>
                        </div>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    <div class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-500 p-4 rounded-lg">
                        <p class="text-sm font-semibold text-amber-900 dark:text-amber-200 mb-2">
                            ⚠️ RFID ini sudah pernah di-scan hari ini!
                        </p>
                        <p class="text-xs text-amber-700 dark:text-amber-300">
                            Pastikan Anda yakin untuk melanjutkan scan ulang.
                        </p>
                    </div>

                    <div class="space-y-3 bg-gray-50 dark:bg-gray-700/50 p-4 rounded-xl">
                        <div class="border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Produk:</span>
                            <p class="text-sm font-bold text-gray-900 dark:text-white mt-1">{{ warningData?.data.product.product_name }}</p>
                        </div>
                        <div class="flex justify-between items-center border-t border-gray-200 dark:border-gray-600 pt-2">
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Tipe Scan:</span>
                            <span :class="['px-3 py-1 rounded-lg text-xs font-semibold', getScanTypeBadgeClass(warningData?.data.last_scan_type)]">
                                {{ warningData?.data.last_scan_type.toUpperCase() }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center border-t border-gray-200 dark:border-gray-600 pt-2">
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Terakhir Scan:</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ warningData?.data.last_scanned_at }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-gray-50 dark:bg-gray-700/30 flex gap-3">
                    <button
                        @click="cancelWarning"
                        class="flex-1 px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl"
                    >
                        Batal
                    </button>
                    <button
                        @click="confirmWarningAndProceed"
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl"
                    >
                        Lanjutkan Scan
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showManualModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="closeManualModal">
            <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-3xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-gradient-to-r from-purple-600 to-pink-600 p-6 text-white z-10">
                    <div class="flex justify-between items-center">
                        <h3 class="text-2xl font-bold flex items-center gap-2">
                            <Plus class="w-6 h-6" />
                            Daftar RFID Baru
                        </h3>
                        <button @click="closeManualModal" class="p-2 hover:bg-white/20 rounded-lg transition-all">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-4 rounded-lg mb-6">
                        <p class="text-sm text-blue-800 dark:text-blue-300">
                            <strong>Registrasi Master:</strong> Isi form ini untuk mendaftarkan RFID baru ke sistem
                        </p>
                    </div>

                    <form @submit.prevent="handleManualSubmit" class="space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                                    RFID Tag <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="manualForm.rfid_tag"
                                    type="text"
                                    class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                                    placeholder="Masukkan RFID tag"
                                />
                                <p v-if="errors.rfid_tag" class="text-red-500 text-sm mt-1">{{ errors.rfid_tag }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Produk <span class="text-red-500">*</span></label>
                                <select v-model="manualForm.product_id" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                    <option value="">Pilih Produk</option>
                                    <option v-for="product in products" :key="product.id" :value="product.id">
                                        {{ product.product_name }} ({{ product.product_code }})
                                    </option>
                                </select>
                                <p v-if="errors.product_id" class="text-red-500 text-sm mt-1">{{ errors.product_id }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                                    Tipe Kanban <span class="text-red-500">*</span>
                                </label>
                                <select v-model="manualForm.scan_type" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                    <option value="in">IN - Material Masuk</option>
                                    <option value="out">OUT - Material Keluar</option>
                                </select>
                                <p v-if="errors.scan_type" class="text-red-500 text-sm mt-1">{{ errors.scan_type }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Kuantitas <span class="text-red-500">*</span></label>
                                <input v-model.number="manualForm.quantity" type="number" min="1" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                                <p v-if="errors.quantity" class="text-red-500 text-sm mt-1">{{ errors.quantity }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Rute (Dari)</label>
                                <input v-model="manualForm.route" type="text" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" placeholder="Contoh: Gudang, Produksi" />
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Alamat (Ke)</label>
                                <input v-model="manualForm.address" type="text" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" placeholder="Contoh: Line Produksi, FG" />
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Tipe Kemasan</label>
                                <input v-model="manualForm.packaging_type" type="text" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" placeholder="Contoh: Box, Pallet" />
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button
                                type="submit"
                                class="flex-1 bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all flex items-center justify-center gap-2"
                            >
                                <Plus class="w-5 h-5" />
                                Daftar
                            </button>
                            <button type="button" @click="closeManualModal" class="flex-1 bg-gray-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-gray-700 transition-all">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div v-if="showEditMasterModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="closeEditMasterModal">
            <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-3xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-gradient-to-r from-green-600 to-emerald-600 p-6 text-white z-10">
                    <div class="flex justify-between items-center">
                        <h3 class="text-2xl font-bold flex items-center gap-2">
                            <Edit class="w-6 h-6" />
                            Edit RFID Master - {{ selectedMaster?.rfid_tag }}
                        </h3>
                        <button @click="closeEditMasterModal" class="p-2 hover:bg-white/20 rounded-lg transition-all">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <form @submit.prevent="handleEditMasterSubmit" class="space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Produk <span class="text-red-500">*</span></label>
                                <select v-model="editMasterForm.product_id" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                    <option value="">Pilih Produk</option>
                                    <option v-for="product in products" :key="product.id" :value="product.id">
                                        {{ product.product_name }} ({{ product.product_code }})
                                    </option>
                                </select>
                                <p v-if="errors.product_id" class="text-red-500 text-sm mt-1">{{ errors.product_id }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Tipe Kanban <span class="text-red-500">*</span></label>
                                <select v-model="editMasterForm.scan_type" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                    <option value="in">IN - Material Masuk</option>
                                    <option value="out">OUT - Material Keluar</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Kuantitas <span class="text-red-500">*</span></label>
                                <input v-model.number="editMasterForm.quantity" type="number" min="1" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Rute (Dari)</label>
                                <input v-model="editMasterForm.route" type="text" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Alamat (Ke)</label>
                                <input v-model="editMasterForm.address" type="text" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Tipe Kemasan</label>
                                <input v-model="editMasterForm.packaging_type" type="text" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Catatan</label>
                                <textarea v-model="editMasterForm.notes" rows="3" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"></textarea>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all flex items-center justify-center gap-2">
                                <Edit class="w-5 h-5" />
                                Update
                            </button>
                            <button type="button" @click="closeEditMasterModal" class="flex-1 bg-gray-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-gray-700 transition-all">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div v-if="showDeleteMasterModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="closeDeleteMasterModal">
            <div class="bg-white dark:bg-gray-800 rounded-2xl w-96 shadow-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-red-600 to-rose-600 p-6 text-white">
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <Trash2 class="w-5 h-5" />
                        Hapus RFID Master
                    </h3>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        Yakin ingin menghapus RFID master <strong class="text-gray-900 dark:text-white">{{ selectedMaster?.rfid_tag }}</strong>?
                    </p>
                    <div class="flex gap-3">
                        <button @click="handleDeleteMaster" class="flex-1 bg-red-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:bg-red-700 transition-all">
                            Hapus
                        </button>
                        <button @click="closeDeleteMasterModal" class="flex-1 bg-gray-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:bg-gray-700 transition-all">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes scale-in {
    from {
        transform: scale(0.9);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.animate-scale-in {
    animation: scale-in 0.2s ease-out;
}
</style>
