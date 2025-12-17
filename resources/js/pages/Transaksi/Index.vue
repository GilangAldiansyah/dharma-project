<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Search, Plus, Package, Eye, Filter, Trash2, X, Camera, Calendar, User, Hash, ChevronLeft, ChevronRight, Download, Undo2, History, ChevronDown, ChevronUp } from 'lucide-vue-next';
import * as XLSX from 'xlsx';

interface Material {
    id: number;
    material_id: string;
    nama_material: string;
    material_type: string;
    satuan: string;
    part_materials: PartMaterial[];
}

interface PartMaterial {
    id: number;
    part_id: string;
    nama_part: string;
    material_id: number;
}

interface Transaksi {
    id: number;
    transaksi_id: string;
    tanggal: string;
    shift: number;
    qty: number;
    foto: string[];
    material: {
        material_id: string;
        nama_material: string;
        satuan: string;
        material_type: string;
    };
    part_material?: {
        part_id: string;
        nama_part: string;
    };
    user?: {
        name: string;
    };
    created_at: string;
    total_pengembalian?: number;
    sisa_pengembalian?: number;
    tanggal_pengembalian_terakhir?: string;
}

interface PengembalianHistory {
    id: number;
    pengembalian_id: string;
    tanggal_pengembalian: string;
    qty_pengembalian: number;
    foto: string[];
    keterangan: string;
    user: {
        name: string;
    };
    created_at: string;
}

interface Props {
    transaksi: {
        data: Transaksi[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search?: string;
        shift?: number;
        date_from?: string;
        date_to?: string;
    };
    effectiveDate: string;
    currentShift: number;
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const filterShift = ref(props.filters.shift || '');
const filterDateFrom = ref(props.filters.date_from || '');
const filterDateTo = ref(props.filters.date_to || '');
const showFilters = ref(false);

const selectedItems = ref<number[]>([]);
const selectAll = ref(false);
const expandedRows = ref<number[]>([]);

const showModal = ref(false);
const showDetailModal = ref(false);
const showPengembalianModal = ref(false);
const showPengembalianHistoryModal = ref(false);
const selectedTransaksi = ref<Transaksi | null>(null);
const pengembalianHistory = ref<PengembalianHistory[]>([]);
const searchMaterialQuery = ref('');
const searchResults = ref<Material[]>([]);
const selectedMaterial = ref<Material | null>(null);
const isSearching = ref(false);
const previewImages = ref<string[]>([]);
const pengembalianPreviewImages = ref<string[]>([]);

const form = useForm({
    tanggal: new Date().toISOString().split('T')[0],
    shift: 1,
    material_id: null as number | null,
    part_material_id: null as number | null,
    qty: '',
    foto: [] as File[],
});

const pengembalianForm = useForm({
    transaksi_material_id: null as number | null,
    tanggal_pengembalian: new Date().toISOString().split('T')[0],
    qty_pengembalian: '',
    keterangan: '',
    foto: [] as File[],
});
const formatQty = (qty: number) => {
    return parseFloat(qty.toString());
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const getSisaQty = (item: Transaksi): number => {
    if (!item.total_pengembalian || item.total_pengembalian === 0) {
        return item.qty;
    }
    return item.sisa_pengembalian ?? (item.qty - item.total_pengembalian);
};

const toggleExpandRow = (id: number) => {
    const index = expandedRows.value.indexOf(id);
    if (index > -1) {
        expandedRows.value.splice(index, 1);
    } else {
        expandedRows.value.push(id);
    }
};

const exportToExcel = () => {
    const excelData = props.transaksi.data.map(item => ({
        'ID Transaksi': item.transaksi_id,
        'Tanggal': formatDate(item.tanggal),
        'Shift': `Shift ${item.shift}`,
        'ID Material': item.material.material_id,
        'Nama Material': item.material.nama_material,
        'Tipe Material': item.material.material_type,
        'Satuan': item.material.satuan,
        'Quantity': formatQty(item.qty),
        'Total Pengembalian': item.total_pengembalian || 0,
        'Tanggal Pengembalian': item.tanggal_pengembalian_terakhir ? formatDate(item.tanggal_pengembalian_terakhir) : '-',
        'Dipakai': item.sisa_pengembalian || item.qty,
        'Dibuat Oleh': item.user?.name || '-',
        'Waktu Input': new Date(item.created_at).toLocaleString('id-ID')
    }));

    const ws = XLSX.utils.json_to_sheet(excelData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Transaksi Material');

    const colWidths = [
        { wch: 18 }, { wch: 12 }, { wch: 8 }, { wch: 15 }, { wch: 30 },
        { wch: 20 }, { wch: 10 }, { wch: 15 }, { wch: 18 }, { wch: 12 },
        { wch: 18 }, { wch: 15 }, { wch: 20 },
    ];
    ws['!cols'] = colWidths;

    const fileName = `Transaksi_Material_${new Date().toISOString().split('T')[0]}.xlsx`;
    XLSX.writeFile(wb, fileName);
};

const search = () => {
    router.get('/transaksi', {
        search: searchQuery.value,
        shift: filterShift.value,
        date_from: filterDateFrom.value,
        date_to: filterDateTo.value,
    }, { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
    searchQuery.value = '';
    filterShift.value = '';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    router.get('/transaksi');
};

const goToPage = (page: number) => {
    router.get(`/transaksi?page=${page}`, {
        search: searchQuery.value,
        shift: filterShift.value,
        date_from: filterDateFrom.value,
        date_to: filterDateTo.value,
    }, { preserveState: true, preserveScroll: true });
};

const getPaginationRange = () => {
    const currentPage = props.transaksi.current_page;
    const lastPage = props.transaksi.last_page;
    const delta = 2;
    const range: (number | string)[] = [];

    for (let i = Math.max(2, currentPage - delta); i <= Math.min(lastPage - 1, currentPage + delta); i++) {
        range.push(i);
    }

    if (currentPage - delta > 2) {
        range.unshift('...');
    }
    if (currentPage + delta < lastPage - 1) {
        range.push('...');
    }

    range.unshift(1);
    if (lastPage > 1) {
        range.push(lastPage);
    }

    return range;
};

const deleteTransaksi = (id: number) => {
    if (confirm('Yakin ingin menghapus transaksi ini?')) {
        router.delete(`/transaksi/${id}`, {
            onSuccess: () => {},
            onError: () => {
                alert('Gagal menghapus transaksi. Silakan coba lagi.');
            }
        });
    }
};

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedItems.value = props.transaksi.data.map(item => item.id);
    } else {
        selectedItems.value = [];
    }
};

const toggleSelectItem = (id: number) => {
    const index = selectedItems.value.indexOf(id);
    if (index > -1) {
        selectedItems.value.splice(index, 1);
    } else {
        selectedItems.value.push(id);
    }
    selectAll.value = selectedItems.value.length === props.transaksi.data.length;
};

const deleteMultiple = () => {
    if (selectedItems.value.length === 0) {
        alert('Pilih minimal 1 transaksi untuk dihapus');
        return;
    }

    if (confirm(`Yakin ingin menghapus ${selectedItems.value.length} transaksi yang dipilih?`)) {
        router.post('/transaksi/delete-multiple', {
            ids: selectedItems.value
        }, {
            onSuccess: () => {
                selectedItems.value = [];
                selectAll.value = false;
            },
            onError: () => {
                alert('Gagal menghapus transaksi. Silakan coba lagi.');
            }
        });
    }
};

watch(() => props.transaksi.data, () => {
    selectAll.value = selectedItems.value.length === props.transaksi.data.length && props.transaksi.data.length > 0;
}, { deep: true });

const openModal = () => {
    showModal.value = true;
    form.reset();
    selectedMaterial.value = null;
    searchMaterialQuery.value = '';
    searchResults.value = [];
    previewImages.value = [];
    form.tanggal = props.effectiveDate;
    form.shift = props.currentShift;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    selectedMaterial.value = null;
    searchMaterialQuery.value = '';
    searchResults.value = [];
    previewImages.value = [];
};

const viewDetail = (transaksi: Transaksi) => {
    selectedTransaksi.value = transaksi;
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    selectedTransaksi.value = null;
};
// Pengembalian functions
const openPengembalianModal = (transaksi: Transaksi) => {
    selectedTransaksi.value = transaksi;
    pengembalianForm.reset();
    pengembalianForm.transaksi_material_id = transaksi.id;
    pengembalianForm.tanggal_pengembalian = props.effectiveDate;
    pengembalianPreviewImages.value = [];
    showPengembalianModal.value = true;
};

const closePengembalianModal = () => {
    showPengembalianModal.value = false;
    selectedTransaksi.value = null;
    pengembalianForm.reset();
    pengembalianPreviewImages.value = [];
};

const handlePengembalianFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files || []);

    if (files.length + pengembalianForm.foto.length > 5) {
        alert('Maksimal 5 foto');
        return;
    }

    pengembalianForm.foto = [...pengembalianForm.foto, ...files].slice(0, 5);

    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => {
            pengembalianPreviewImages.value.push(e.target?.result as string);
        };
        reader.readAsDataURL(file);
    });
};

const removePengembalianImage = (index: number) => {
    pengembalianForm.foto.splice(index, 1);
    pengembalianPreviewImages.value.splice(index, 1);
};

const submitPengembalian = () => {
    pengembalianForm.post('/pengembalian', {
        onSuccess: () => {
            closePengembalianModal();
        },
    });
};

const viewPengembalianHistory = async (transaksi: Transaksi) => {
    try {
        const response = await fetch(`/transaksi/${transaksi.id}/pengembalian-history`);
        const data = await response.json();
        pengembalianHistory.value = data.pengembalian;
        selectedTransaksi.value = { ...transaksi, total_pengembalian: data.totalPengembalian };
        showPengembalianHistoryModal.value = true;
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    } catch (error) {
        alert('Gagal memuat riwayat pengembalian');
    }
};

const closePengembalianHistoryModal = () => {
    showPengembalianHistoryModal.value = false;
    selectedTransaksi.value = null;
    pengembalianHistory.value = [];
};

const searchMaterial = async () => {
    if (!searchMaterialQuery.value || searchMaterialQuery.value.length < 2) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(`/materials/search/api?query=${encodeURIComponent(searchMaterialQuery.value)}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) throw new Error("Response is not JSON");
        searchResults.value = await response.json();
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    } catch (error) {
        searchResults.value = [];
        alert('Gagal mencari material. Silakan coba lagi.');
    } finally {
        isSearching.value = false;
    }
};

const selectMaterial = (material: Material) => {
    selectedMaterial.value = material;
    form.material_id = material.id;
    if (material.part_materials.length === 1) {
        form.part_material_id = material.part_materials[0].id;
    } else {
        form.part_material_id = null;
    }
    searchResults.value = [];
    searchMaterialQuery.value = '';
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files || []);
    if (files.length + form.foto.length > 5) {
        alert('Maksimal 5 foto');
        return;
    }
    form.foto = [...form.foto, ...files].slice(0, 5);
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImages.value.push(e.target?.result as string);
        };
        reader.readAsDataURL(file);
    });
};

const removeImage = (index: number) => {
    form.foto.splice(index, 1);
    previewImages.value.splice(index, 1);
};

const submit = () => {
    form.post('/transaksi', {
        onSuccess: () => {
            closeModal();
        },
    });
};

watch(searchMaterialQuery, () => {
    if (searchMaterialQuery.value.length >= 2) {
        searchMaterial();
    } else {
        searchResults.value = [];
    }
});
</script>
<template>
    <Head title="Transaksi Material" />
    <AppLayout :breadcrumbs="[
        { title: 'Transaksi Material', href: '/transaksi' }
    ]">
        <div class="p-4 space-y-4">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Package class="w-6 h-6 text-blue-600" />
                    Transaksi Pengambilan Material
                </h1>
                <div class="flex items-center gap-2">
                    <button
                        @click="exportToExcel"
                        class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
                        title="Export ke Excel"
                    >
                        <Download class="w-4 h-4" />
                        <span class="hidden sm:inline">Export</span>
                    </button>
                    <button
                        v-if="selectedItems.length > 0"
                        @click="deleteMultiple"
                        class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
                        title="Hapus yang dipilih"
                    >
                        <Trash2 class="w-4 h-4" />
                        <span class="hidden sm:inline">Hapus ({{ selectedItems.length }})</span>
                    </button>
                    <button
                        @click="openModal"
                        class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors shadow-sm"
                        title="Input Pengambilan Material"
                    >
                        <Plus class="w-4 h-4" />
                        Pengambilan
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Transaksi</div>
                    <div class="text-2xl font-bold">{{ transaksi.total }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-1">
                        <Undo2 class="w-3.5 h-3.5" />
                        Ada Pengembalian
                    </div>
                    <div class="text-2xl font-bold text-orange-600">
                        {{ transaksi.data.filter(t => t.total_pengembalian && t.total_pengembalian > 0).length }}
                    </div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Shift 1</div>
                    <div class="text-2xl font-bold text-blue-600">
                        {{ transaksi.data.filter(t => t.shift === 1).length }}
                    </div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Shift 2</div>
                    <div class="text-2xl font-bold text-green-600">
                        {{ transaksi.data.filter(t => t.shift === 2).length }}
                    </div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Shift 3</div>
                    <div class="text-2xl font-bold text-purple-600">
                        {{ transaksi.data.filter(t => t.shift === 3).length }}
                    </div>
                </div>
            </div>

            <!-- Search & Filter -->
            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4 space-y-3">
                <div class="flex gap-2">
                    <input
                        v-model="searchQuery"
                        @keyup.enter="search"
                        type="text"
                        placeholder="Cari transaksi atau material..."
                        class="flex-1 rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                    <button
                        @click="showFilters = !showFilters"
                        :class="[
                            'px-4 py-2 rounded-md flex items-center gap-2 transition-colors',
                            showFilters ? 'bg-blue-600 text-white' : 'bg-sidebar hover:bg-sidebar-accent'
                        ]"
                    >
                        <Filter class="w-4 h-4" />
                        Filter
                    </button>
                    <button
                        @click="search"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center gap-2 transition-colors"
                    >
                        <Search class="w-4 h-4" />
                        Cari
                    </button>
                </div>

                <div v-if="showFilters" class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-3 border-t border-sidebar-border">
                    <div>
                        <label class="block text-sm mb-1 font-medium">Shift</label>
                        <select
                            v-model="filterShift"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar text-sm focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Semua Shift</option>
                            <option :value="1">Shift 1</option>
                            <option :value="2">Shift 2</option>
                            <option :value="3">Shift 3</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm mb-1 font-medium">Dari Tanggal</label>
                        <input
                            v-model="filterDateFrom"
                            type="date"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar text-sm focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm mb-1 font-medium">Sampai Tanggal</label>
                        <input
                            v-model="filterDateTo"
                            type="date"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar text-sm focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                <div v-if="showFilters" class="flex gap-2">
                    <button
                        @click="search"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm transition-colors"
                    >
                        Terapkan Filter
                    </button>
                    <button
                        @click="resetFilters"
                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm transition-colors"
                    >
                        Reset Filter
                    </button>
                </div>
            </div>
            <!-- Table -->
            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left">
                                    <input
                                        type="checkbox"
                                        v-model="selectAll"
                                        @change="toggleSelectAll"
                                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                </th>
                                <th class="px-4 py-3 text-left text-sm font-medium">ID Transaksi</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Tanggal</th>
                                <th class="px-7 py-3 text-left text-sm font-medium">Shift</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Material</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">QTY</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Foto</th>
                                <th class="px-4 py-3 text-center text-sm font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <template v-for="item in transaksi.data" :key="item.id">
                                <!-- Main Row -->
                                <tr
                                    :class="[
                                        'hover:bg-sidebar-accent transition-colors',
                                        selectedItems.includes(item.id) ? 'bg-blue-50 dark:bg-blue-900/10' : '',
                                        item.total_pengembalian && item.total_pengembalian > 0 ? 'border-l-4 border-orange-400' : ''
                                    ]"
                                >
                                    <td class="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            :checked="selectedItems.includes(item.id)"
                                            @change="toggleSelectItem(item.id)"
                                            class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium">
                                        <div class="flex items-center gap-2">
                                            {{ item.transaksi_id }}
                                            <span
                                                v-if="item.total_pengembalian && item.total_pengembalian > 0"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 rounded"
                                                title="Ada Pengembalian"
                                            >
                                                <Undo2 class="w-3 h-3" />
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm">{{ formatDate(item.tanggal) }}</td>
                                    <td class="px-4 py-3">
                                        <span :class="[
                                            'px-2 py-1 rounded-full text-xs font-medium',
                                            item.shift === 1 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' :
                                            item.shift === 2 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                            'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                                        ]">
                                            Shift {{ item.shift }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="font-medium">{{ item.material.nama_material }}</div>
                                        <div class="text-gray-500 text-xs">{{ item.material.material_id }}</div>
                                    </td>
                                   <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1">
                                            <div class="font-medium">
                                                {{ formatQty(item.total_pengembalian && item.total_pengembalian > 0 ? getSisaQty(item) : item.qty) }} {{ item.material.satuan }}
                                            </div>
                                        </div>
                                            <button
                                                v-if="!item.total_pengembalian || item.total_pengembalian === 0"
                                                @click="openPengembalianModal(item)"
                                                class="p-1.5 text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20 rounded transition-colors"
                                                title="Input Pengembalian"
                                            >
                                                <Undo2 class="w-4 h-4" />
                                            </button>

                                            <button
                                                v-if="item.total_pengembalian && item.total_pengembalian > 0"
                                                @click="toggleExpandRow(item.id)"
                                                class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                                                title="Lihat Detail"
                                            >
                                                <ChevronDown v-if="!expandedRows.includes(item.id)" class="w-4 h-4 text-gray-400" />
                                                <ChevronUp v-else class="w-4 h-4 text-gray-400" />
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span v-if="item.foto && item.foto.length > 0" class="text-blue-600 flex items-center gap-1">
                                            <Camera class="w-3 h-3" />
                                            {{ item.foto.length }}
                                        </span>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center gap-1">
                                            <button
                                                @click="viewDetail(item)"
                                                class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded transition-colors"
                                                title="Lihat Detail"
                                            >
                                                <Eye class="w-4 h-4" />
                                            </button>
                                            <button
                                                @click="deleteTransaksi(item.id)"
                                                class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors"
                                                title="Hapus"
                                            >
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Expanded Row - Detail Pengembalian -->
                                <tr v-if="expandedRows.includes(item.id) && item.total_pengembalian && item.total_pengembalian > 0">

                                    <td colspan="8" class="px-4 py-3 bg-orange-50 dark:bg-orange-900/10">

                                        <div class="space-y-3">
                                            <div class="flex items-center justify-between">
    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2">
        <Undo2 class="w-4 h-4 text-orange-600" />
        Detail Pengembalian
    </div>
    <div class="flex items-center gap-3">
        <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400 bg-white dark:bg-sidebar px-3 py-1.5 rounded-md border border-sidebar-border">
            <Calendar class="w-3.5 h-3.5 text-purple-600" />
            <span class="font-medium">{{ item.tanggal_pengembalian_terakhir ? formatDate(item.tanggal_pengembalian_terakhir) : '-' }}</span>
        </div>
        <button
            @click="viewPengembalianHistory(item)"
            class="text-xs px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors flex items-center gap-1"
        >
            <History class="w-3 h-3" />
            Lihat Detail
        </button>
    </div>
                                                </div>
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                    <div class="bg-white dark:bg-sidebar rounded-lg p-3 border border-sidebar-border">
                                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Qty Pengambilan</div>
                                                        <div class="text-lg font-bold text-blue-600">
                                                            {{ formatQty(item.qty) }} {{ item.material.satuan }}
                                                        </div>
                                                    </div>
                                                    <div class="bg-white dark:bg-sidebar rounded-lg p-3 border border-sidebar-border">
                                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Dikembalikan</div>
                                                        <div class="text-lg font-bold text-orange-600">
                                                            {{ formatQty(item.total_pengembalian) }} {{ item.material.satuan }}
                                                        </div>
                                                    </div>
                                                    <div class="bg-white dark:bg-sidebar rounded-lg p-3 border border-sidebar-border">
                                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Qty Terpakai</div>
                                                        <div class="text-lg font-bold text-green-600">
                                                            {{ formatQty(getSisaQty(item)) }} {{ item.material.satuan }}
                                                        </div>
                                                    </div>
                                                </div>
                                            <div class="flex gap-2 pt-2">
                                                <button
                                                    @click="openPengembalianModal(item)"
                                                    class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors flex items-center justify-center gap-2 text-sm"
                                                    :disabled="getSisaQty(item) <= 0"
                                                    :class="{ 'opacity-50 cursor-not-allowed': getSisaQty(item) <= 0 }"
                                                >
                                                    <Undo2 class="w-4 h-4" />
                                                    Input Pengembalian Lagi
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div v-if="transaksi.data.length === 0" class="text-center py-12 text-gray-500">
                    <Package class="w-16 h-16 mx-auto mb-4 opacity-50" />
                    <p class="text-lg font-medium">Tidak ada transaksi</p>
                    <p class="text-sm mt-1">Mulai dengan menambahkan transaksi pengambilan material</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="transaksi.last_page > 1" class="flex items-center justify-between bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Halaman {{ transaksi.current_page }} dari {{ transaksi.last_page }}
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="goToPage(transaksi.current_page - 1)"
                        :disabled="transaksi.current_page === 1"
                        class="p-2 rounded-md border border-sidebar-border hover:bg-sidebar-accent disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>

                    <template v-for="(page, index) in getPaginationRange()" :key="index">
                        <button
                            v-if="typeof page === 'number'"
                            @click="goToPage(page)"
                            :class="[
                                'min-w-[40px] h-10 px-3 rounded-md text-sm font-medium transition-colors',
                                page === transaksi.current_page
                                    ? 'bg-blue-600 text-white'
                                    : 'border border-sidebar-border hover:bg-sidebar-accent'
                            ]"
                        >
                            {{ page }}
                        </button>
                        <span v-else class="px-2 text-gray-500">{{ page }}</span>
                    </template>

                    <button
                        @click="goToPage(transaksi.current_page + 1)"
                        :disabled="transaksi.current_page === transaksi.last_page"
                        class="p-2 rounded-md border border-sidebar-border hover:bg-sidebar-accent disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>

                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Total {{ transaksi.total }} data
                </div>
            </div>
        </div>
        <!-- Modal Detail Transaksi -->
        <div v-if="showDetailModal && selectedTransaksi" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-6 border-b border-sidebar-border sticky top-0 bg-white dark:bg-sidebar z-10">
                    <h2 class="text-xl font-bold">Detail Transaksi</h2>
                    <button @click="closeDetailModal" class="p-1 hover:bg-sidebar-accent rounded transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                                <Hash class="w-4 h-4" />
                                ID Transaksi
                            </div>
                            <div class="font-semibold text-lg">{{ selectedTransaksi.transaksi_id }}</div>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                                <Calendar class="w-4 h-4" />
                                Tanggal
                            </div>
                            <div class="font-semibold">{{ formatDate(selectedTransaksi.tanggal) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Shift</div>
                            <span :class="[
                                'inline-block px-3 py-1 rounded-full text-sm font-medium',
                                selectedTransaksi.shift === 1 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' :
                                selectedTransaksi.shift === 2 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                            ]">
                                Shift {{ selectedTransaksi.shift }}
                            </span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                                <User class="w-4 h-4" />
                                Dibuat Oleh
                            </div>
                            <div class="font-semibold">{{ selectedTransaksi.user?.name || '-' }}</div>
                        </div>
                    </div>

                    <div class="border-t border-sidebar-border pt-6">
                        <h3 class="font-semibold mb-4 flex items-center gap-2">
                            <Package class="w-5 h-5 text-blue-600" />
                            Informasi Material
                        </h3>
                        <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4 space-y-3">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">ID Material</div>
                                    <div class="font-medium">{{ selectedTransaksi.material.material_id }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">Nama Material</div>
                                    <div class="font-medium">{{ selectedTransaksi.material.nama_material }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">Tipe Material</div>
                                    <div class="font-medium">{{ selectedTransaksi.material.material_type }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">Satuan</div>
                                    <div class="font-medium">{{ selectedTransaksi.material.satuan }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-sidebar-border pt-6">
                        <h3 class="font-semibold mb-4">Quantity</h3>
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-6 text-center">
                            <div class="text-4xl font-bold text-blue-600">
                                {{ formatQty(selectedTransaksi.qty) }}
                            </div>
                            <div class="text-lg text-gray-600 dark:text-gray-400 mt-2">
                                {{ selectedTransaksi.material.satuan }}
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedTransaksi.foto && selectedTransaksi.foto.length > 0" class="border-t border-sidebar-border pt-6">
                        <h3 class="font-semibold mb-4 flex items-center gap-2">
                            <Camera class="w-5 h-5" />
                            Foto ({{ selectedTransaksi.foto.length }})
                        </h3>
                        <div class="grid grid-cols-3 gap-4">
                            <a
                                v-for="(foto, index) in selectedTransaksi.foto"
                                :key="index"
                                :href="`/storage/${foto}`"
                                target="_blank"
                                class="relative aspect-square rounded-lg overflow-hidden hover:opacity-80 transition-opacity"
                            >
                                <img
                                    :src="`/storage/${foto}`"
                                    :alt="`Foto ${index + 1}`"
                                    class="w-full h-full object-cover"
                                />
                            </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 p-6 border-t border-sidebar-border">
                    <button
                        @click="closeDetailModal"
                        class="flex-1 px-6 py-3 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors"
                    >
                        Tutup
                    </button>
                </div>
            </div>

        <!-- Modal Input Transaksi -->
        <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-6 border-b border-sidebar-border sticky top-0 bg-white dark:bg-sidebar z-10">
                    <h2 class="text-xl font-bold">Input Pengambilan Material</h2>
                    <button @click="closeModal" class="p-1 hover:bg-sidebar-accent rounded transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="p-6 space-y-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">Cari Material</label>
                        <div class="relative">
                            <input
                                v-model="searchMaterialQuery"
                                type="text"
                                placeholder="Ketik ID atau nama material (min 2 karakter)..."
                                class="w-full px-4 py-2 border border-sidebar-border rounded-md dark:bg-sidebar focus:ring-2 focus:ring-blue-500"
                            />
                            <Search class="absolute right-3 top-2.5 w-5 h-5 text-gray-400" />
                        </div>

                        <div v-if="searchResults.length > 0" class="mt-2 border border-sidebar-border rounded-md max-h-60 overflow-y-auto">
                            <button
                                v-for="material in searchResults"
                                :key="material.id"
                                type="button"
                                @click="selectMaterial(material)"
                                class="w-full text-left px-4 py-3 hover:bg-sidebar-accent border-b border-sidebar-border last:border-b-0 transition-colors"
                            >
                                <div class="font-medium">{{ material.material_id }} - {{ material.nama_material }}</div>
                                <div class="text-sm text-gray-500">{{ material.material_type }} | {{ material.satuan }}</div>
                            </button>
                        </div>

                        <div v-if="selectedMaterial" class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-md border border-blue-200 dark:border-blue-800">
                            <div class="flex items-start gap-3">
                                <Package class="w-5 h-5 text-blue-600 mt-0.5" />
                                <div class="flex-1">
                                    <div class="font-medium">{{ selectedMaterial.material_id }}</div>
                                    <div class="text-sm">{{ selectedMaterial.nama_material }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">
                                        {{ selectedMaterial.material_type }} | {{ selectedMaterial.satuan }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="form.errors.material_id" class="mt-2 text-sm text-red-600">
                            {{ form.errors.material_id }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Tanggal</label>
                            <input
                                v-model="form.tanggal"
                                type="date"
                                class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar focus:ring-2 focus:ring-blue-500"
                                required
                            />
                            <div v-if="form.errors.tanggal" class="mt-1 text-sm text-red-600">
                                {{ form.errors.tanggal }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Shift</label>
                            <select
                                v-model="form.shift"
                                class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar focus:ring-2 focus:ring-blue-500"
                                required
                            >
                                <option :value="1">Shift 1</option>
                                <option :value="2">Shift 2</option>
                                <option :value="3">Shift 3</option>
                            </select>
                            <div v-if="form.errors.shift" class="mt-1 text-sm text-red-600">
                                {{ form.errors.shift }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">
                            Quantity
                            <span v-if="selectedMaterial" class="text-gray-500">({{ selectedMaterial.satuan }})</span>
                        </label>
                        <input
                            v-model="form.qty"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar focus:ring-2 focus:ring-blue-500"
                            required
                        />
                        <div v-if="form.errors.qty" class="mt-1 text-sm text-red-600">
                            {{ form.errors.qty }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Foto (Opsional)</label>
                        <div class="flex items-center gap-4">
                            <label class="cursor-pointer flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                <Camera class="w-4 h-4" />
                                Pilih Foto
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    @change="handleFileChange"
                                    class="hidden"
                                    :disabled="form.foto.length >= 5"
                                />
                            </label>
                            <span class="text-sm text-gray-500">{{ form.foto.length }}/5 foto</span>
                        </div>

                        <div v-if="previewImages.length > 0" class="mt-4 grid grid-cols-5 gap-2">
                            <div
                                v-for="(image, index) in previewImages"
                                :key="index"
                                class="relative aspect-square"
                            >
                                <img :src="image" class="w-full h-full object-cover rounded-md" />
                                <button
                                    type="button"
                                    @click="removeImage(index)"
                                    class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-700 transition-colors"
                                >
                                    ×
                                </button>
                            </div>
                        </div>

                        <div v-if="form.errors.foto" class="mt-2 text-sm text-red-600">
                            {{ form.errors.foto }}
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4 border-t border-sidebar-border">
                        <button
                            type="submit"
                            :disabled="form.processing || !selectedMaterial"
                            class="flex-1 px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Transaksi' }}
                        </button>
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-6 py-3 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors"
                        >
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Input Pengembalian -->
        <div v-if="showPengembalianModal && selectedTransaksi" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-6 border-b border-sidebar-border sticky top-0 bg-white dark:bg-sidebar z-10">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <Undo2 class="w-5 h-5 text-orange-600" />
                        Input Pengembalian Material
                    </h2>
                    <button @click="closePengembalianModal" class="p-1 hover:bg-sidebar-accent rounded transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitPengembalian" class="p-6 space-y-6">
                    <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4">
                        <div class="text-sm font-medium mb-3">Transaksi Pengambilan</div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">ID Transaksi</div>
                                <div class="font-medium">{{ selectedTransaksi.transaksi_id }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Material</div>
                                <div class="font-medium">{{ selectedTransaksi.material.nama_material }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Qty Pengambilan</div>
                                <div class="font-medium">{{ formatQty(selectedTransaksi.qty) }} {{ selectedTransaksi.material.satuan }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Sisa Bisa Dikembalikan</div>
                                <div class="font-medium text-orange-600">
                                    {{ formatQty(selectedTransaksi.sisa_pengembalian || selectedTransaksi.qty) }} {{ selectedTransaksi.material.satuan }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Tanggal Pengembalian</label>
                        <input
                            v-model="pengembalianForm.tanggal_pengembalian"
                            type="date"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar focus:ring-2 focus:ring-orange-500"
                            required
                        />
                        <div v-if="pengembalianForm.errors.tanggal_pengembalian" class="mt-1 text-sm text-red-600">
                            {{ pengembalianForm.errors.tanggal_pengembalian }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">
                            Quantity Dikembalikan ({{ selectedTransaksi.material.satuan }})
                        </label>
                        <input
                            v-model="pengembalianForm.qty_pengembalian"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :max="selectedTransaksi.sisa_pengembalian || selectedTransaksi.qty"
                            placeholder="0.00"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar focus:ring-2 focus:ring-orange-500"
                            required
                        />
                        <div v-if="pengembalianForm.errors.qty_pengembalian" class="mt-1 text-sm text-red-600">
                            {{ pengembalianForm.errors.qty_pengembalian }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Keterangan (Opsional)</label>
                        <textarea
                            v-model="pengembalianForm.keterangan"
                            rows="3"
                            placeholder="Alasan pengembalian atau keterangan lainnya..."
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar focus:ring-2 focus:ring-orange-500"
                        ></textarea>
                        <div v-if="pengembalianForm.errors.keterangan" class="mt-1 text-sm text-red-600">
                            {{ pengembalianForm.errors.keterangan }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Foto (Opsional)</label>
                        <div class="flex items-center gap-4">
                            <label class="cursor-pointer flex items-center gap-2 px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors">
                                <Camera class="w-4 h-4" />
                                Pilih Foto
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    @change="handlePengembalianFileChange"
                                    class="hidden"
                                    :disabled="pengembalianForm.foto.length >= 5"
                                />
                            </label>
                            <span class="text-sm text-gray-500">{{ pengembalianForm.foto.length }}/5 foto</span>
                        </div>

                        <div v-if="pengembalianPreviewImages.length > 0" class="mt-4 grid grid-cols-5 gap-2">
                            <div
                                v-for="(image, index) in pengembalianPreviewImages"
                                :key="index"
                                class="relative aspect-square"
                            >
                                <img :src="image" class="w-full h-full object-cover rounded-md" />
                                <button
                                    type="button"
                                    @click="removePengembalianImage(index)"
                                    class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-700 transition-colors"
                                >
                                    ×
                                </button>
                            </div>
                        </div>

                        <div v-if="pengembalianForm.errors.foto" class="mt-2 text-sm text-red-600">
                            {{ pengembalianForm.errors.foto }}
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4 border-t border-sidebar-border">
                        <button
                            type="submit"
                            :disabled="pengembalianForm.processing"
                            class="flex-1 px-6 py-3 bg-orange-600 text-white rounded-md hover:bg-orange-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ pengembalianForm.processing ? 'Menyimpan...' : 'Simpan Pengembalian' }}
                        </button>
                        <button
                            type="button"
                            @click="closePengembalianModal"
                            class="px-6 py-3 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors"
                        >
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Riwayat Pengembalian -->
        <div v-if="showPengembalianHistoryModal && selectedTransaksi" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-6 border-b border-sidebar-border sticky top-0 bg-white dark:bg-sidebar z-10">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <History class="w-5 h-5 text-purple-600" />
                        Riwayat Pengembalian
                    </h2>
                    <button @click="closePengembalianHistoryModal" class="p-1 hover:bg-sidebar-accent rounded transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-6 space-y-6">
                    <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4">
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">ID Transaksi</div>
                                <div class="font-medium">{{ selectedTransaksi.transaksi_id }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Qty Pengambilan</div>
                                <div class="font-medium">{{ formatQty(selectedTransaksi.qty) }} {{ selectedTransaksi.material.satuan }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Total Dikembalikan</div>
                                <div class="font-medium text-orange-600">
                                    {{ formatQty(selectedTransaksi.total_pengembalian || 0) }} {{ selectedTransaksi.material.satuan }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="pengembalianHistory.length === 0" class="text-center py-12 text-gray-500">
                        <Undo2 class="w-16 h-16 mx-auto mb-4 opacity-50" />
                        <p class="text-lg font-medium">Belum ada pengembalian</p>
                        <p class="text-sm mt-1">Belum ada pengembalian untuk transaksi ini</p>
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="pengembalian in pengembalianHistory"
                            :key="pengembalian.id"
                            class="border border-sidebar-border rounded-lg p-4 hover:shadow-md transition-shadow"
                        >
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <div class="font-medium">{{ selectedTransaksi.material.nama_material }}</div>
                                    <div class="text-sm text-gray-500">{{ formatDate(pengembalian.tanggal_pengembalian) }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-orange-600">
                                        {{ formatQty(pengembalian.qty_pengembalian) }} {{ selectedTransaksi.material.satuan }}
                                    </div>
                                    <div class="text-xs text-gray-500">{{ pengembalian.user.name }}</div>
                                </div>
                            </div>

                            <div v-if="pengembalian.keterangan" class="text-sm text-gray-600 dark:text-gray-400 mb-3 p-3 bg-gray-50 dark:bg-sidebar-accent rounded">
                                {{ pengembalian.keterangan }}
                            </div>

                            <div v-if="pengembalian.foto && pengembalian.foto.length > 0" class="grid grid-cols-5 gap-2">
                                <a
                                    v-for="(foto, index) in pengembalian.foto"
                                    :key="index"
                                    :href="`/storage/${foto}`"
                                    target="_blank"
                                    class="relative aspect-square rounded-lg overflow-hidden hover:opacity-80 transition-opacity"
                                >
                                    <img
                                        :src="`/storage/${foto}`"
                                        :alt="`Foto ${index + 1}`"
                                        class="w-full h-full object-cover"
                                    />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 p-6 border-t border-sidebar-border">
                    <button
                        @click="closePengembalianHistoryModal"
                        class="flex-1 px-6 py-3 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
