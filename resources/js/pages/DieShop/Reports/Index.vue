<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Plus, Search, Eye, Edit, Trash2, FileText, Filter, Clock, CheckCircle, X, Upload } from 'lucide-vue-next';

interface DieShopReport {
    id: number;
    report_no: string;
    pic_name: string;
    shift: string;
    report_date: string;
    die_part: {
        id: number;
        part_no: string;
        part_name: string;
        location: string | null;
    };
    status: 'pending' | 'in_progress' | 'completed';
    duration_formatted: string | null;
    duration_human: string | null;
    repair_process: string | null;
    problem_description: string | null;
    cause: string | null;
    repair_action: string | null;
    photos: string[] | null;
    spareparts: {
        id: number;
        sparepart_name: string;
        sparepart_code: string | null;
        quantity: number;
        notes: string | null;
    }[];
    creator: {
        name: string;
        email: string;
    } | null;
    created_at: string;
    updated_at: string;
    completed_at: string | null;
}

interface DiePart {
    id: number;
    part_no: string;
    part_name: string;
    location: string | null;
}

interface Sparepart {
    sparepart_name: string;
    sparepart_code: string;
    quantity: number;
    notes: string;
}

interface Props {
    reports: {
        data: DieShopReport[];
        current_page: number;
        last_page: number;
    };
    dieParts: DiePart[];
    filters: {
        search?: string;
        status?: string;
        shift?: string;
        date_from?: string;
        date_to?: string;
    };
}

const props = defineProps<Props>();

// Filter States
const searchQuery = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const shiftFilter = ref(props.filters.shift || '');
const dateFromFilter = ref(props.filters.date_from || '');
const dateToFilter = ref(props.filters.date_to || '');
const showFilters = ref(false);

// Modal States
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDetailModal = ref(false);
const selectedReport = ref<DieShopReport | null>(null);

// Photo States
const selectedPhoto = ref<string | null>(null);
const photoPreview = ref<string[]>([]);

// Part Search - Improved
const partSearchQuery = ref('');
const showPartDropdown = ref(false);

const filteredDieParts = computed(() => {
    if (!partSearchQuery.value) return props.dieParts.slice(0, 10);
    const query = partSearchQuery.value.toLowerCase();
    return props.dieParts.filter(part =>
        part.part_no.toLowerCase().includes(query) ||
        part.part_name.toLowerCase().includes(query)
    ).slice(0, 20);
});

// Forms
const createForm = useForm({
    pic_name: '',
    shift: '1',
    report_date: new Date().toISOString().split('T')[0],
    die_part_id: '',
    repair_process: '',
    problem_description: '',
    cause: '',
    repair_action: '',
    photos: [] as File[],
    status: 'pending',
    spareparts: [] as Sparepart[],
});

const editForm = useForm({
    pic_name: '',
    shift: '1',
    report_date: '',
    die_part_id: '',
    repair_process: '',
    problem_description: '',
    cause: '',
    repair_action: '',
    photos: [] as File[],
    existing_photos: [] as string[],
    status: 'pending',
    spareparts: [] as Sparepart[],
    _method: 'PUT' as const,
});

// Watch for edit modal open - AUTO POPULATE DATA
watch(showEditModal, (isOpen) => {
    if (isOpen && selectedReport.value) {
        editForm.pic_name = selectedReport.value.pic_name;
        editForm.shift = String(selectedReport.value.shift);
        editForm.report_date = selectedReport.value.report_date;
        editForm.die_part_id = String(selectedReport.value.die_part.id);
        editForm.repair_process = selectedReport.value.repair_process || '';
        editForm.problem_description = selectedReport.value.problem_description || '';
        editForm.cause = selectedReport.value.cause || '';
        editForm.repair_action = selectedReport.value.repair_action || '';
        editForm.status = selectedReport.value.status;
        editForm.existing_photos = selectedReport.value.photos || [];
        editForm.spareparts = selectedReport.value.spareparts.map(sp => ({
            sparepart_name: sp.sparepart_name,
            sparepart_code: sp.sparepart_code || '',
            quantity: sp.quantity,
            notes: sp.notes || '',
        }));
        photoPreview.value = [];
        partSearchQuery.value = `${selectedReport.value.die_part.part_no} - ${selectedReport.value.die_part.part_name}`;
        showPartDropdown.value = false;
    }
});

// Search & Filter Functions
const search = () => {
    router.get('/die-shop-reports', {
        search: searchQuery.value,
        status: statusFilter.value,
        shift: shiftFilter.value,
        date_from: dateFromFilter.value,
        date_to: dateToFilter.value,
    }, { preserveState: true });
};

const clearFilters = () => {
    searchQuery.value = '';
    statusFilter.value = '';
    shiftFilter.value = '';
    dateFromFilter.value = '';
    dateToFilter.value = '';
    search();
};

// Part Selection Functions
const selectPart = (part: DiePart, form: typeof createForm | typeof editForm) => {
    form.die_part_id = part.id.toString();
    partSearchQuery.value = `${part.part_no} - ${part.part_name}`;
    showPartDropdown.value = false;
};

const clearPartSelection = (form: typeof createForm | typeof editForm) => {
    form.die_part_id = '';
    partSearchQuery.value = '';
};

// Handle dropdown blur with delay
const handlePartBlur = () => {
    window.setTimeout(() => {
        showPartDropdown.value = false;
    }, 200);
};

// Create Modal Functions
const openCreateModal = () => {
    createForm.reset();
    createForm.report_date = new Date().toISOString().split('T')[0];
    photoPreview.value = [];
    partSearchQuery.value = '';
    showPartDropdown.value = false;
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    createForm.reset();
    photoPreview.value = [];
    partSearchQuery.value = '';
};

const submitCreate = () => {
    createForm.post('/die-shop-reports', {
        preserveScroll: true,
        onSuccess: () => closeCreateModal(),
    });
};

// Edit Modal Functions
const openEditModal = (report: DieShopReport) => {
    selectedReport.value = report;
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    selectedReport.value = null;
    editForm.reset();
    photoPreview.value = [];
    partSearchQuery.value = '';
};

const submitEdit = () => {
    if (selectedReport.value) {
        editForm.post(`/die-shop-reports/${selectedReport.value.id}`, {
            preserveScroll: true,
            onSuccess: () => closeEditModal(),
        });
    }
};

// Detail Modal Functions
const openDetailModal = (report: DieShopReport) => {
    selectedReport.value = report;
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    selectedReport.value = null;
};

// Quick Actions
const quickComplete = (id: number) => {
    if (confirm('Tandai laporan sebagai selesai?')) {
        router.post(`/die-shop-reports/${id}/quick-complete`, {}, { preserveScroll: true });
    }
};

const deleteReport = (id: number) => {
    if (confirm('Yakin hapus laporan ini?')) {
        router.delete(`/die-shop-reports/${id}`, { preserveScroll: true });
    }
};

// Sparepart Functions
const addSparepart = (form: typeof createForm | typeof editForm) => {
    form.spareparts.push({
        sparepart_name: '',
        sparepart_code: '',
        quantity: 1,
        notes: '',
    });
};

const removeSparepart = (form: typeof createForm | typeof editForm, index: number) => {
    form.spareparts.splice(index, 1);
};

// Photo Functions
const handlePhotoUpload = (event: Event, form: typeof createForm | typeof editForm) => {
    const target = event.target as HTMLInputElement;
    const files = target.files;

    if (files) {
        Array.from(files).forEach(file => {
            form.photos.push(file);
            const reader = new FileReader();
            reader.onload = (e) => {
                photoPreview.value.push(e.target?.result as string);
            };
            reader.readAsDataURL(file);
        });
    }
};

const removePhoto = (form: typeof createForm | typeof editForm, index: number) => {
    form.photos.splice(index, 1);
    photoPreview.value.splice(index, 1);
};

const removeExistingPhoto = (index: number) => {
    editForm.existing_photos.splice(index, 1);
};

// Utility Functions
const getStatusBadge = (status: string) => {
    switch (status) {
        case 'completed': return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        case 'in_progress': return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
        default: return 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400';
    }
};

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'completed': return 'Selesai';
        case 'in_progress': return 'Dalam Proses';
        default: return 'Pending';
    }
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const formatDateTime = (date: string) => {
    return new Date(date).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Head title="Laporan Corrective" />
    <AppLayout :breadcrumbs="[{ title: 'Laporan Corrective', href: '/die-shop-reports' }]">
        <div class="p-4 space-y-4">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <FileText class="w-6 h-6 text-blue-600" />
                    Laporan Corrective
                </h1>
                <button
                    @click="openCreateModal"
                    class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                >
                    <Plus class="w-4 h-4" />
                    Buat Laporan
                </button>
            </div>

            <!-- Search & Filters -->
            <div class="space-y-3">
                <div class="flex gap-2 items-center">
                    <div class="flex-1 max-w-md">
                        <input
                            v-model="searchQuery"
                            @keyup.enter="search"
                            type="text"
                            placeholder="Cari no laporan, PIC, atau part..."
                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                        />
                    </div>
                    <button @click="search" class="p-2 bg-sidebar hover:bg-sidebar-accent rounded-md">
                        <Search class="w-5 h-5" />
                    </button>
                    <button
                        @click="showFilters = !showFilters"
                        class="p-2 bg-sidebar hover:bg-sidebar-accent rounded-md flex items-center gap-2"
                    >
                        <Filter class="w-5 h-5" />
                        Filter
                    </button>
                </div>

                <!-- Filter Panel -->
                <div v-if="showFilters" class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Shift</label>
                            <select
                                v-model="shiftFilter"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            >
                                <option value="">Semua</option>
                                <option value="1">Shift 1</option>
                                <option value="2">Shift 2</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Status</label>
                            <select
                                v-model="statusFilter"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            >
                                <option value="">Semua</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">Dalam Proses</option>
                                <option value="completed">Selesai</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Dari Tanggal</label>
                            <input
                                v-model="dateFromFilter"
                                type="date"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Sampai Tanggal</label>
                            <input
                                v-model="dateToFilter"
                                type="date"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                    </div>
                    <div class="flex gap-2 mt-3">
                        <button @click="search" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Terapkan Filter
                        </button>
                        <button @click="clearFilters" class="px-4 py-2 border rounded-md hover:bg-gray-100 dark:hover:bg-sidebar-accent">
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="border border-sidebar-border rounded-lg overflow-hidden bg-white dark:bg-sidebar">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">No Laporan</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Tanggal</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Shift</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Part</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">PIC</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Status</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">
                                    <div class="flex items-center justify-center gap-1">
                                        <Clock class="w-4 h-4" />
                                        Durasi
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="report in reports.data"
                                :key="report.id"
                                class="border-b border-sidebar-border hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                            >
                                <td class="px-4 py-3 text-sm font-medium">{{ report.report_no }}</td>
                                <td class="px-4 py-3 text-sm">{{ formatDate(report.report_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">
                                        Shift {{ report.shift }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium">{{ report.die_part.part_no }}</div>
                                    <div class="text-xs text-gray-500">{{ report.die_part.part_name }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ report.pic_name }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-flex px-2 py-1 rounded-full text-xs font-semibold', getStatusBadge(report.status)]">
                                        {{ getStatusLabel(report.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div v-if="report.duration_formatted" class="font-mono text-sm font-medium text-blue-600 dark:text-blue-400">
                                        {{ report.duration_formatted }}
                                    </div>
                                    <div v-else class="text-sm text-gray-400">-</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            @click="openDetailModal(report)"
                                            class="p-1 text-green-600 hover:bg-green-100 dark:hover:bg-green-900 rounded"
                                            title="Lihat Detail"
                                        >
                                            <Eye class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="openEditModal(report)"
                                            class="p-1 text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-900 rounded"
                                            title="Edit"
                                        >
                                            <Edit class="w-4 h-4" />
                                        </button>
                                        <button
                                            v-if="report.status !== 'completed'"
                                            @click="quickComplete(report.id)"
                                            class="p-1 text-purple-600 hover:bg-purple-100 dark:hover:bg-purple-900 rounded"
                                            title="Tandai Selesai"
                                        >
                                            <CheckCircle class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deleteReport(report.id)"
                                            class="p-1 text-red-600 hover:bg-red-100 dark:hover:bg-red-900 rounded"
                                            title="Hapus"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="reports.data.length === 0">
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    Tidak ada data laporan
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- CREATE MODAL -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click="closeCreateModal">
            <div class="bg-white dark:bg-sidebar rounded-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="sticky top-0 bg-white dark:bg-sidebar border-b border-sidebar-border p-4 flex justify-between items-center z-10">
                    <h2 class="text-lg font-bold">Buat Laporan Baru</h2>
                    <button @click="closeCreateModal" class="p-1 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitCreate" class="p-4 space-y-4">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">PIC *</label>
                            <input
                                v-model="createForm.pic_name"
                                type="text"
                                required
                                placeholder="Nama PIC"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Shift *</label>
                            <select
                                v-model="createForm.shift"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            >
                                <option value="1">Shift 1</option>
                                <option value="2">Shift 2</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Tanggal *</label>
                            <input
                                v-model="createForm.report_date"
                                type="date"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Status *</label>
                            <select
                                v-model="createForm.status"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            >
                                <option value="pending">Pending</option>
                                <option value="in_progress">Dalam Proses</option>
                                <option value="completed">Selesai</option>
                            </select>
                        </div>
                    </div>

                    <!-- Die Part Selection - IMPROVED -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Die Part *</label>

                        <!-- Selected Part Display -->
                        <div v-if="createForm.die_part_id" class="mb-2">
                            <div class="flex items-center justify-between bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900/30 rounded-md px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <CheckCircle class="w-4 h-4 text-blue-600" />
                                    <span class="text-sm font-medium">{{ partSearchQuery }}</span>
                                </div>
                                <button
                                    type="button"
                                    @click="clearPartSelection(createForm)"
                                    class="text-red-600 hover:text-red-700"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div v-else class="relative">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input
                                    v-model="partSearchQuery"
                                    @focus="showPartDropdown = true"
                                    @blur="handlePartBlur"
                                    type="text"
                                    placeholder="Ketik untuk mencari part number atau nama part..."
                                    class="w-full rounded-md border border-sidebar-border pl-10 pr-3 py-2 dark:bg-sidebar-accent"
                                    autocomplete="off"
                                />
                            </div>

                            <!-- Dropdown Results -->
                            <div
                                v-if="showPartDropdown && filteredDieParts.length > 0"
                                class="absolute z-20 w-full mt-1 bg-white dark:bg-sidebar border border-sidebar-border rounded-md shadow-lg max-h-60 overflow-y-auto"
                            >
                                <button
                                    v-for="part in filteredDieParts"
                                    :key="part.id"
                                    type="button"
                                    @click="selectPart(part, createForm)"
                                    class="w-full text-left px-3 py-2 hover:bg-blue-50 dark:hover:bg-blue-900/20 border-b border-sidebar-border last:border-b-0"
                                >
                                    <div class="font-medium text-sm">{{ part.part_no }}</div>
                                    <div class="text-xs text-gray-500">{{ part.part_name }}</div>
                                </button>
                            </div>

                            <!-- No Results -->
                            <div
                                v-if="showPartDropdown && partSearchQuery && filteredDieParts.length === 0"
                                class="absolute z-20 w-full mt-1 bg-white dark:bg-sidebar border border-sidebar-border rounded-md shadow-lg p-3 text-center text-sm text-gray-500"
                            >
                                Tidak ada part yang ditemukan
                            </div>

                            <!-- Helper Text -->
                            <p class="text-xs text-gray-500 mt-1">
                                {{ partSearchQuery ? `${filteredDieParts.length} part ditemukan` : 'Klik untuk melihat daftar part' }}
                            </p>
                        </div>
                    </div>

                    <!-- Problem & Action -->
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Deskripsi Masalah</label>
                            <textarea
                                v-model="createForm.problem_description"
                                rows="2"
                                placeholder="Jelaskan masalah yang ditemukan..."
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            ></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Penyebab</label>
                            <textarea
                                v-model="createForm.cause"
                                rows="2"
                                placeholder="Jelaskan penyebab masalah..."
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            ></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Tindakan Perbaikan</label>
                            <textarea
                                v-model="createForm.repair_action"
                                rows="2"
                                placeholder="Jelaskan tindakan yang dilakukan..."
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            ></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Proses Perbaikan</label>
                            <textarea
                                v-model="createForm.repair_process"
                                rows="2"
                                placeholder="Jelaskan proses perbaikan..."
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Photos -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Upload Foto</label>
                        <div class="flex items-center gap-3 mb-3">
                            <label class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 cursor-pointer">
                                <Upload class="w-4 h-4" />
                                Pilih Foto
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    @change="handlePhotoUpload($event, createForm)"
                                    class="hidden"
                                />
                            </label>
                            <span class="text-sm text-gray-500">Max 5MB per foto</span>
                        </div>
                        <div v-if="photoPreview.length > 0" class="grid grid-cols-4 gap-2">
                            <div v-for="(preview, index) in photoPreview" :key="index" class="relative group">
                                <img :src="preview" class="w-full h-20 object-cover rounded border" />
                                <button
                                    type="button"
                                    @click="removePhoto(createForm, index)"
                                    class="absolute top-1 right-1 p-1 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition"
                                >
                                    <X class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Spareparts -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium">Sparepart</label>
                            <button
                                type="button"
                                @click="addSparepart(createForm)"
                                class="text-sm text-blue-600 hover:text-blue-700 flex items-center gap-1"
                            >
                                <Plus class="w-4 h-4" />
                                Tambah Sparepart
                            </button>
                        </div>
                        <div v-if="createForm.spareparts.length === 0" class="text-center py-4 text-gray-500 text-sm border border-dashed rounded">
                            Belum ada sparepart
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="(sp, index) in createForm.spareparts"
                                :key="index"
                                class="border border-sidebar-border rounded p-3 relative"
                            >
                                <button
                                    type="button"
                                    @click="removeSparepart(createForm, index)"
                                    class="absolute top-2 right-2 p-1 text-red-600 hover:bg-red-100 rounded"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                                <div class="grid grid-cols-2 gap-2 pr-8">
                                    <div>
                                        <input
                                            v-model="sp.sparepart_name"
                                            type="text"
                                            placeholder="Nama Sparepart *"
                                            required
                                            class="w-full rounded border px-2 py-1 text-sm dark:bg-sidebar-accent"
                                        />
                                    </div>
                                    <div>
                                        <input
                                            v-model="sp.sparepart_code"
                                            type="text"
                                            placeholder="Kode"
                                            class="w-full rounded border px-2 py-1 text-sm dark:bg-sidebar-accent"
                                        />
                                    </div>
                                    <div>
                                        <input
                                            v-model.number="sp.quantity"
                                            type="number"
                                            min="1"
                                            placeholder="Qty *"
                                            required
                                            class="w-full rounded border px-2 py-1 text-sm dark:bg-sidebar-accent"
                                        />
                                    </div>
                                    <div>
                                        <input
                                            v-model="sp.notes"
                                            type="text"
                                            placeholder="Catatan"
                                            class="w-full rounded border px-2 py-1 text-sm dark:bg-sidebar-accent"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 pt-4 border-t">
                        <button
                            type="button"
                            @click="closeCreateModal"
                            class="px-4 py-2 border border-sidebar-border rounded-md hover:bg-gray-100 dark:hover:bg-sidebar-accent"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ createForm.processing ? 'Menyimpan...' : 'Simpan Laporan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- EDIT MODAL -->
        <div v-if="showEditModal && selectedReport" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click="closeEditModal">
            <div class="bg-white dark:bg-sidebar rounded-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="sticky top-0 bg-white dark:bg-sidebar border-b border-sidebar-border p-4 flex justify-between items-center z-10">
                    <h2 class="text-lg font-bold">Edit Laporan - {{ selectedReport.report_no }}</h2>
                    <button @click="closeEditModal" class="p-1 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitEdit" class="p-4 space-y-4">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">PIC *</label>
                            <input
                                v-model="editForm.pic_name"
                                type="text"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Shift *</label>
                            <select
                                v-model="editForm.shift"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            >
                                <option value="1">Shift 1</option>
                                <option value="2">Shift 2</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Tanggal *</label>
                            <input
                                v-model="editForm.report_date"
                                type="date"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Status *</label>
                            <select
                                v-model="editForm.status"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            >
                                <option value="pending">Pending</option>
                                <option value="in_progress">Dalam Proses</option>
                                <option value="completed">Selesai</option>
                            </select>
                        </div>
                    </div>

                    <!-- Die Part Selection - IMPROVED -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Die Part *</label>

                        <!-- Selected Part Display -->
                        <div v-if="editForm.die_part_id" class="mb-2">
                            <div class="flex items-center justify-between bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900/30 rounded-md px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <CheckCircle class="w-4 h-4 text-blue-600" />
                                    <span class="text-sm font-medium">{{ partSearchQuery }}</span>
                                </div>
                                <button
                                    type="button"
                                    @click="clearPartSelection(editForm)"
                                    class="text-red-600 hover:text-red-700"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div v-else class="relative">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input
                                    v-model="partSearchQuery"
                                    @focus="showPartDropdown = true"
                                    @blur="handlePartBlur"
                                    type="text"
                                    placeholder="Ketik untuk mencari part number atau nama part..."
                                    class="w-full rounded-md border border-sidebar-border pl-10 pr-3 py-2 dark:bg-sidebar-accent"
                                    autocomplete="off"
                                />
                            </div>

                            <!-- Dropdown Results -->
                            <div
                                v-if="showPartDropdown && filteredDieParts.length > 0"
                                class="absolute z-20 w-full mt-1 bg-white dark:bg-sidebar border border-sidebar-border rounded-md shadow-lg max-h-60 overflow-y-auto"
                            >
                                <button
                                    v-for="part in filteredDieParts"
                                    :key="part.id"
                                    type="button"
                                    @click="selectPart(part, editForm)"
                                    class="w-full text-left px-3 py-2 hover:bg-blue-50 dark:hover:bg-blue-900/20 border-b border-sidebar-border last:border-b-0"
                                >
                                    <div class="font-medium text-sm">{{ part.part_no }}</div>
                                    <div class="text-xs text-gray-500">{{ part.part_name }}</div>
                                </button>
                            </div>

                            <!-- No Results -->
                            <div
                                v-if="showPartDropdown && partSearchQuery && filteredDieParts.length === 0"
                                class="absolute z-20 w-full mt-1 bg-white dark:bg-sidebar border border-sidebar-border rounded-md shadow-lg p-3 text-center text-sm text-gray-500"
                            >
                                Tidak ada part yang ditemukan
                            </div>

                            <!-- Helper Text -->
                            <p class="text-xs text-gray-500 mt-1">
                                {{ partSearchQuery ? `${filteredDieParts.length} part ditemukan` : 'Klik untuk melihat daftar part' }}
                            </p>
                        </div>
                    </div>

                    <!-- Problem & Action -->
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Deskripsi Masalah</label>
                            <textarea
                                v-model="editForm.problem_description"
                                rows="2"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            ></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Penyebab</label>
                            <textarea
                                v-model="editForm.cause"
                                rows="2"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            ></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Tindakan Perbaikan</label>
                            <textarea
                                v-model="editForm.repair_action"
                                rows="2"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            ></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Proses Perbaikan</label>
                            <textarea
                                v-model="editForm.repair_process"
                                rows="2"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Existing Photos -->
                    <div v-if="editForm.existing_photos.length > 0">
                        <label class="block text-sm font-medium mb-2">Foto Existing</label>
                        <div class="grid grid-cols-4 gap-2">
                            <div v-for="(photo, index) in editForm.existing_photos" :key="index" class="relative group">
                                <img :src="`/storage/${photo}`" class="w-full h-20 object-cover rounded border" />
                                <button
                                    type="button"
                                    @click="removeExistingPhoto(index)"
                                    class="absolute top-1 right-1 p-1 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition"
                                >
                                    <X class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- New Photos -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Upload Foto Baru</label>
                        <div class="flex items-center gap-3 mb-3">
                            <label class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 cursor-pointer">
                                <Upload class="w-4 h-4" />
                                Pilih Foto
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    @change="handlePhotoUpload($event, editForm)"
                                    class="hidden"
                                />
                            </label>
                        </div>
                        <div v-if="photoPreview.length > 0" class="grid grid-cols-4 gap-2">
                            <div v-for="(preview, index) in photoPreview" :key="index" class="relative group">
                                <img :src="preview" class="w-full h-20 object-cover rounded border" />
                                <button
                                    type="button"
                                    @click="removePhoto(editForm, index)"
                                    class="absolute top-1 right-1 p-1 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition"
                                >
                                    <X class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Spareparts -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium">Sparepart</label>
                            <button
                                type="button"
                                @click="addSparepart(editForm)"
                                class="text-sm text-blue-600 hover:text-blue-700 flex items-center gap-1"
                            >
                                <Plus class="w-4 h-4" />
                                Tambah Sparepart
                            </button>
                        </div>
                        <div v-if="editForm.spareparts.length === 0" class="text-center py-4 text-gray-500 text-sm border border-dashed rounded">
                            Belum ada sparepart
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="(sp, index) in editForm.spareparts"
                                :key="index"
                                class="border border-sidebar-border rounded p-3 relative"
                            >
                                <button
                                    type="button"
                                    @click="removeSparepart(editForm, index)"
                                    class="absolute top-2 right-2 p-1 text-red-600 hover:bg-red-100 rounded"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                                <div class="grid grid-cols-2 gap-2 pr-8">
                                    <div>
                                        <input
                                            v-model="sp.sparepart_name"
                                            type="text"
                                            placeholder="Nama Sparepart *"
                                            required
                                            class="w-full rounded border px-2 py-1 text-sm dark:bg-sidebar-accent"
                                        />
                                    </div>
                                    <div>
                                        <input
                                            v-model="sp.sparepart_code"
                                            type="text"
                                            placeholder="Kode"
                                            class="w-full rounded border px-2 py-1 text-sm dark:bg-sidebar-accent"
                                        />
                                    </div>
                                    <div>
                                        <input
                                            v-model.number="sp.quantity"
                                            type="number"
                                            min="1"
                                            placeholder="Qty *"
                                            required
                                            class="w-full rounded border px-2 py-1 text-sm dark:bg-sidebar-accent"
                                        />
                                    </div>
                                    <div>
                                        <input
                                            v-model="sp.notes"
                                            type="text"
                                            placeholder="Catatan"
                                            class="w-full rounded border px-2 py-1 text-sm dark:bg-sidebar-accent"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 pt-4 border-t">
                        <button
                            type="button"
                            @click="closeEditModal"
                            class="px-4 py-2 border border-sidebar-border rounded-md hover:bg-gray-100 dark:hover:bg-sidebar-accent"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ editForm.processing ? 'Menyimpan...' : 'Update Laporan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- DETAIL MODAL -->
        <div v-if="showDetailModal && selectedReport" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click="closeDetailModal">
            <div class="bg-white dark:bg-sidebar rounded-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="sticky top-0 bg-white dark:bg-sidebar border-b border-sidebar-border p-4 flex justify-between items-center z-10">
                    <h2 class="text-lg font-bold">{{ selectedReport.report_no }}</h2>
                    <button @click="closeDetailModal" class="p-1 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-4 space-y-4">
                    <!-- Info Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="bg-gray-50 dark:bg-sidebar-accent p-3 rounded">
                            <label class="text-xs text-gray-500">PIC</label>
                            <p class="font-medium">{{ selectedReport.pic_name }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-sidebar-accent p-3 rounded">
                            <label class="text-xs text-gray-500">Shift</label>
                            <p class="font-medium">Shift {{ selectedReport.shift }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-sidebar-accent p-3 rounded">
                            <label class="text-xs text-gray-500">Tanggal</label>
                            <p class="font-medium">{{ formatDate(selectedReport.report_date) }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-sidebar-accent p-3 rounded">
                            <label class="text-xs text-gray-500">Status</label>
                            <span :class="['inline-flex px-2 py-1 rounded-full text-xs font-semibold', getStatusBadge(selectedReport.status)]">
                                {{ getStatusLabel(selectedReport.status) }}
                            </span>
                        </div>
                    </div>

                    <!-- Die Part Info -->
                    <div class="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-900/30 p-3 rounded">
                        <label class="text-xs text-gray-500 block mb-1">Die Part</label>
                        <p class="font-medium">{{ selectedReport.die_part.part_no }} - {{ selectedReport.die_part.part_name }}</p>
                        <p v-if="selectedReport.die_part.location" class="text-sm text-gray-600">Lokasi: {{ selectedReport.die_part.location }}</p>
                    </div>

                    <!-- Duration Info -->
                    <div v-if="selectedReport.duration_formatted" class="bg-purple-50 dark:bg-purple-900/10 border border-purple-200 dark:border-purple-900/30 p-3 rounded">
                        <div class="flex items-center gap-2 mb-1">
                            <Clock class="w-4 h-4 text-purple-600" />
                            <label class="text-sm font-medium">Durasi Pengerjaan</label>
                        </div>
                        <p class="text-2xl font-mono font-bold text-purple-700 dark:text-purple-400">
                            {{ selectedReport.duration_formatted }}
                        </p>
                        <p v-if="selectedReport.duration_human" class="text-sm text-gray-600 mt-1">
                            {{ selectedReport.duration_human }}
                        </p>
                        <p v-if="selectedReport.completed_at" class="text-xs text-gray-500 mt-2">
                            Selesai: {{ formatDateTime(selectedReport.completed_at) }}
                        </p>
                    </div>

                    <!-- Problem Description -->
                    <div v-if="selectedReport.problem_description" class="border-t pt-4">
                        <label class="text-sm font-medium block mb-2 flex items-center gap-2">
                            <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                            Deskripsi Masalah
                        </label>
                        <p class="text-sm whitespace-pre-wrap bg-red-50 dark:bg-red-900/10 p-3 rounded border border-red-200 dark:border-red-900/30">
                            {{ selectedReport.problem_description }}
                        </p>
                    </div>

                    <!-- Cause -->
                    <div v-if="selectedReport.cause">
                        <label class="text-sm font-medium block mb-2 flex items-center gap-2">
                            <span class="w-2 h-2 bg-yellow-500 rounded-full"></span>
                            Penyebab
                        </label>
                        <p class="text-sm whitespace-pre-wrap bg-yellow-50 dark:bg-yellow-900/10 p-3 rounded border border-yellow-200 dark:border-yellow-900/30">
                            {{ selectedReport.cause }}
                        </p>
                    </div>

                    <!-- Repair Action -->
                    <div v-if="selectedReport.repair_action">
                        <label class="text-sm font-medium block mb-2 flex items-center gap-2">
                            <CheckCircle class="w-4 h-4 text-green-500" />
                            Tindakan Perbaikan
                        </label>
                        <p class="text-sm whitespace-pre-wrap bg-green-50 dark:bg-green-900/10 p-3 rounded border border-green-200 dark:border-green-900/30">
                            {{ selectedReport.repair_action }}
                        </p>
                    </div>

                    <!-- Repair Process -->
                    <div v-if="selectedReport.repair_process">
                        <label class="text-sm font-medium block mb-2">Proses Perbaikan</label>
                        <p class="text-sm whitespace-pre-wrap bg-gray-50 dark:bg-sidebar-accent p-3 rounded border">
                            {{ selectedReport.repair_process }}
                        </p>
                    </div>

                    <!-- Spareparts -->
                    <div v-if="selectedReport.spareparts.length > 0" class="border-t pt-4">
                        <label class="text-sm font-medium block mb-2">Sparepart yang Digunakan</label>
                        <div class="space-y-2">
                            <div
                                v-for="sp in selectedReport.spareparts"
                                :key="sp.id"
                                class="flex justify-between items-center bg-gray-50 dark:bg-sidebar-accent p-3 rounded border"
                            >
                                <div>
                                    <p class="font-medium">{{ sp.sparepart_name }}</p>
                                    <p v-if="sp.sparepart_code" class="text-xs text-gray-500 font-mono">{{ sp.sparepart_code }}</p>
                                    <p v-if="sp.notes" class="text-xs text-gray-600 mt-1">{{ sp.notes }}</p>
                                </div>
                                <span class="text-lg font-semibold text-blue-600">x{{ sp.quantity }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Photos -->
                    <div v-if="selectedReport.photos && selectedReport.photos.length > 0" class="border-t pt-4">
                        <label class="text-sm font-medium block mb-2">Foto Dokumentasi</label>
                        <div class="grid grid-cols-3 gap-2">
                            <img
                                v-for="(photo, idx) in selectedReport.photos"
                                :key="idx"
                                :src="`/storage/${photo}`"
                                class="w-full h-32 object-cover rounded border cursor-pointer hover:opacity-80 transition"
                                @click="selectedPhoto = photo"
                            />
                        </div>
                    </div>

                    <!-- Creator Info -->
                    <div v-if="selectedReport.creator" class="border-t pt-4 text-xs text-gray-500">
                        <p>Dibuat oleh: {{ selectedReport.creator.name }} ({{ selectedReport.creator.email }})</p>
                        <p>Pada: {{ formatDateTime(selectedReport.created_at) }}</p>
                        <p v-if="selectedReport.updated_at !== selectedReport.created_at">
                            Terakhir diupdate: {{ formatDateTime(selectedReport.updated_at) }}
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-2 pt-4 border-t">
                        <button
                            @click="openEditModal(selectedReport); closeDetailModal();"
                            class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                        >
                            <Edit class="w-4 h-4" />
                            Edit Laporan
                        </button>
                        <button
                            v-if="selectedReport.status !== 'completed'"
                            @click="quickComplete(selectedReport.id); closeDetailModal();"
                            class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                        >
                            <CheckCircle class="w-4 h-4" />
                            Tandai Selesai
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- PHOTO MODAL -->
        <div v-if="selectedPhoto" class="fixed inset-0 bg-black/90 flex items-center justify-center z-[60] p-4" @click="selectedPhoto = null">
            <button
                @click="selectedPhoto = null"
                class="absolute top-4 right-4 p-2 bg-white/10 hover:bg-white/20 rounded-full text-white transition"
            >
                <X class="w-6 h-6" />
            </button>
            <img
                :src="`/storage/${selectedPhoto}`"
                class="max-w-full max-h-full object-contain rounded shadow-2xl"
                @click.stop
            />
        </div>
    </AppLayout>
</template>
