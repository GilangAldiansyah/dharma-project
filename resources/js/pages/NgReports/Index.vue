<!-- eslint-disable @typescript-eslint/no-unused-vars -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { Plus, Trash2, Search, Image as ImageIcon, X, FileText, Upload, CheckCircle, Clock, AlertCircle, Download, XCircle } from 'lucide-vue-next';

interface NgReport {
    id: number;
    report_number: string;
    ng_images: string[];
    notes: string;
    reported_by: string;
    reported_at: string;
    status: 'open' | 'pica_submitted' | 'closed';
    pica_document: string | null;
    pica_uploaded_at: string | null;
    pica_uploaded_by: string | null;
    part: {
        id: number;
        part_code: string;
        part_name: string;
        product_images: string[];
        supplier: {
            supplier_name: string;
        };
    };
}

interface Props {
    reports: {
        data: NgReport[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    parts: Array<{
        id: number;
        part_code: string;
        part_name: string;
        product_images: string[];
        supplier: { supplier_name: string };
    }>;
    filters: {
        search: string;
        status: string;
    };
}

const props = defineProps<Props>();

const showModal = ref(false);
const showPicaModal = ref(false);
const searchQuery = ref(props.filters.search);
const statusFilter = ref(props.filters.status);
const ngImagePreviews = ref<string[]>([]);
const okImagePreviews = ref<string[]>([]);
const showImageModal = ref(false);
const showNotesModal = ref(false);
const selectedNotes = ref('');
const selectedImages = ref<string[]>([]);
const currentImageIndex = ref(0);
const selectedPart = ref<any>(null);
const selectedReport = ref<NgReport | null>(null);
const showPartDropdown = ref(false);
const partSearchQuery = ref('');
const partDropdownRef = ref<HTMLElement | null>(null);
const partSearchInputRef = ref<HTMLInputElement | null>(null);

const form = useForm({
    part_id: 0,
    ng_images: [] as File[],
    notes: '',
    reported_by: '',
});

const picaForm = useForm({
    pica_document: null as File | null,
    pica_uploaded_by: '',
});

watch(() => props.filters, (newFilters) => {
    searchQuery.value = newFilters.search;
    statusFilter.value = newFilters.status;
}, { deep: true });

const getStatusConfig = (status: string) => {
    const configs: Record<string, {
        label: string;
        icon: any;
        bgClass: string;
        textClass: string;
        borderClass: string;
    }> = {
        open: {
            label: 'Open',
            icon: AlertCircle,
            bgClass: 'bg-red-100 dark:bg-red-900/30',
            textClass: 'text-red-700 dark:text-red-400',
            borderClass: 'border-red-300 dark:border-red-700'
        },
        pica_submitted: {
            label: 'PICA Submitted',
            icon: Clock,
            bgClass: 'bg-yellow-100 dark:bg-yellow-900/30',
            textClass: 'text-yellow-700 dark:text-yellow-400',
            borderClass: 'border-yellow-300 dark:border-yellow-700'
        },
        closed: {
            label: 'Closed',
            icon: CheckCircle,
            bgClass: 'bg-green-100 dark:bg-green-900/30',
            textClass: 'text-green-700 dark:text-green-400',
            borderClass: 'border-green-300 dark:border-green-700'
        }
    };
    return configs[status] || configs.open;
};

const filteredParts = computed(() => {
    if (!partSearchQuery.value) return props.parts;

    const query = partSearchQuery.value.toLowerCase();
    return props.parts.filter(part =>
        part.part_name.toLowerCase().includes(query) ||
        part.part_code.toLowerCase().includes(query) ||
        part.supplier.supplier_name.toLowerCase().includes(query)
    );
});

const selectedPartDisplay = computed(() => {
    if (!form.part_id || form.part_id === 0) return '-- Pilih Part yang Bermasalah --';
    const part = props.parts.find(p => p.id === form.part_id);
    if (!part) return '-- Pilih Part yang Bermasalah --';
    return `${part.part_name} (${part.part_code}) - ${part.supplier.supplier_name}`;
});

const openPartDropdown = () => {
    showPartDropdown.value = true;
    partSearchQuery.value = '';
    setTimeout(() => partSearchInputRef.value?.focus(), 50);
};

const closePartDropdown = () => {
    showPartDropdown.value = false;
    partSearchQuery.value = '';
};

const selectPartOption = (partId: number) => {
    form.part_id = partId;
    closePartDropdown();
};

const handleClickOutside = (event: MouseEvent) => {
    if (partDropdownRef.value && !partDropdownRef.value.contains(event.target as Node)) {
        closePartDropdown();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

watch(() => form.part_id, (newPartId) => {
    const part = props.parts.find(p => p.id === newPartId);
    if (part) {
        selectedPart.value = part;
        okImagePreviews.value = [];
        if (part.product_images && part.product_images.length > 0) {
            part.product_images.forEach(img => {
                okImagePreviews.value.push(`/storage/${img}`);
            });
        }
    } else {
        selectedPart.value = null;
        okImagePreviews.value = [];
    }
});

const openModal = () => {
    form.reset();
    ngImagePreviews.value = [];
    okImagePreviews.value = [];
    selectedPart.value = null;
    showModal.value = true;
};

const openPicaModal = (report: NgReport) => {
    selectedReport.value = report;
    picaForm.reset();
    showPicaModal.value = true;
};

const handleNgImagesChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const files = target.files;

    if (files) {
        form.ng_images = [];
        ngImagePreviews.value = [];

        Array.from(files).forEach(file => {
            form.ng_images.push(file);
            ngImagePreviews.value.push(URL.createObjectURL(file));
        });
    }
};

const handlePicaChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        picaForm.pica_document = file;
    }
};

const cancelPica = (id: number) => {
    if (confirm('Yakin membatalkan PICA ini? Dokumen PICA akan dihapus dan status akan kembali ke Open. Anda bisa mengupload PICA yang sudah direvisi.')) {
        router.post(`/ng-reports/${id}/cancel-pica`, {}, { preserveScroll: true });
    }
};

const removeNgImage = (index: number) => {
    ngImagePreviews.value.splice(index, 1);
    form.ng_images.splice(index, 1);
};

const submit = () => {
    form.post('/ng-reports', {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
            ngImagePreviews.value = [];
            okImagePreviews.value = [];
            selectedPart.value = null;
        },
    });
};

const submitPica = () => {
    if (!selectedReport.value) return;

    picaForm.post(`/ng-reports/${selectedReport.value.id}/upload-pica`, {
        preserveScroll: true,
        onSuccess: () => {
            showPicaModal.value = false;
            picaForm.reset();
            selectedReport.value = null;
        },
    });
};

const closeReport = (id: number) => {
    if (confirm('Yakin tutup laporan ini?')) {
        router.post(`/ng-reports/${id}/close`, {}, { preserveScroll: true });
    }
};

const deleteReport = (id: number) => {
    if (confirm('Yakin hapus laporan NG ini?')) {
        router.delete(`/ng-reports/${id}`, { preserveScroll: true });
    }
};

const search = () => {
    router.get('/ng-reports', {
        search: searchQuery.value,
        status: statusFilter.value
    }, {
        preserveState: true,
        preserveScroll: true
    });
};

const viewReportImages = (report: NgReport) => {
    selectedImages.value = [];
    if (report.ng_images && report.ng_images.length > 0) {
        report.ng_images.forEach(img => {
            selectedImages.value.push(`/storage/${img}`);
        });
    }
    currentImageIndex.value = 0;
    showImageModal.value = true;
};

const viewPartImages = (part: any) => {
    selectedImages.value = [];
    if (part.product_images && part.product_images.length > 0) {
        part.product_images.forEach((img: any) => {
            selectedImages.value.push(`/storage/${img}`);
        });
    }
    currentImageIndex.value = 0;
    showImageModal.value = true;
};

const nextImage = () => {
    if (currentImageIndex.value < selectedImages.value.length - 1) {
        currentImageIndex.value++;
    }
};

const prevImage = () => {
    if (currentImageIndex.value > 0) {
        currentImageIndex.value--;
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('id-ID');
};

const viewNotes = (notes: string) => {
    selectedNotes.value = notes;
    showNotesModal.value = true;
};
</script>

<template>
    <Head title="Laporan NG" />
    <AppLayout :breadcrumbs="[{ title: 'Laporan NG', href: '/ng-reports' }]">
        <div class="p-4 space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Laporan NG</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Kelola laporan produk NG </p>
                </div>
                <button
                    @click="openModal()"
                    class="flex items-center gap-2 px-4 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 shadow-lg hover:shadow-xl transition-all"
                >
                    <Plus class="w-5 h-5" />
                    <span class="font-medium">Lapor NG</span>
                </button>
            </div>

            <div class="bg-white dark:bg-sidebar rounded-lg border border-sidebar-border p-4 shadow-sm">
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="searchQuery"
                                @keyup.enter="search"
                                type="text"
                                placeholder="Cari nomor laporan, part, atau supplier..."
                                class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-sidebar-border dark:bg-sidebar-accent focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            />
                        </div>
                    </div>
                    <select
                        v-model="statusFilter"
                        @change="search"
                        class="px-4 py-2.5 rounded-lg border border-sidebar-border dark:bg-sidebar-accent min-w-[160px]"
                    >
                        <option value="all">Semua Status</option>
                        <option value="open">Open</option>
                        <option value="pica_submitted">PICA Submitted</option>
                        <option value="closed">Closed</option>
                    </select>
                    <button
                        @click="search"
                        class="px-4 py-2.5 bg-gray-100 dark:bg-sidebar-accent hover:bg-gray-200 dark:hover:bg-sidebar-accent/80 rounded-lg transition-colors"
                    >
                        <Search class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-sidebar rounded-lg border border-sidebar-border overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">No. Laporan</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Part</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Supplier</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider">Foto NG</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider">Foto Referensi</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">PICA</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Pelapor</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr
                                v-for="report in reports.data"
                                :key="report.id"
                                class="hover:bg-gray-50 dark:hover:bg-sidebar-accent/50 transition-colors"
                            >
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-sm">{{ report.report_number }}</div>
                                    <div class="text-xs text-gray-500">{{ formatDate(report.reported_at) }}</div>
                                </td>

                                <td class="px-4 py-4">
                                    <div class="font-medium text-sm">{{ report.part.part_name }}</div>
                                    <div class="text-xs text-gray-500">{{ report.part.part_code }}</div>
                                    <button
                                        v-if="report.notes"
                                        @click="viewNotes(report.notes)"
                                        class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors"
                                    >
                                        <FileText class="w-3.5 h-3.5" />
                                        Lihat Masalah
                                    </button>
                                </td>

                                <td class="px-4 py-4 text-sm">{{ report.part.supplier.supplier_name }}</td>

                                <td class="px-4 py-4">
                                    <div class="flex justify-center">
                                        <div
                                            v-if="report.ng_images && report.ng_images.length > 0"
                                            class="relative cursor-pointer group"
                                            @click="viewReportImages(report)"
                                        >
                                            <img
                                                :src="`/storage/${report.ng_images[0]}`"
                                                class="w-20 h-20 object-cover rounded-lg border-2 border-red-400 shadow-md group-hover:scale-105 transition-transform"
                                                alt="Foto NG"
                                            />
                                            <div
                                                v-if="report.ng_images.length > 1"
                                                class="absolute -bottom-2 -right-2 bg-red-600 text-white text-xs px-2 py-1 rounded-full font-bold shadow-lg"
                                            >
                                                +{{ report.ng_images.length - 1 }}
                                            </div>
                                            <div class="absolute inset-0 bg-black/60 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                <span class="text-white text-sm font-medium">Lihat Semua</span>
                                            </div>
                                        </div>
                                        <div v-else class="w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center">
                                            <ImageIcon class="w-8 h-8 text-gray-400" />
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4">
                                    <div class="flex justify-center">
                                        <div
                                            v-if="report.part.product_images && report.part.product_images.length > 0"
                                            class="relative cursor-pointer group"
                                            @click="viewPartImages(report.part)"
                                        >
                                            <img
                                                :src="`/storage/${report.part.product_images[0]}`"
                                                class="w-20 h-20 object-cover rounded-lg border-2 border-green-400 shadow-md group-hover:scale-105 transition-transform"
                                                alt="Foto OK"
                                            />
                                            <div
                                                v-if="report.part.product_images.length > 1"
                                                class="absolute -bottom-2 -right-2 bg-green-600 text-white text-xs px-2 py-1 rounded-full font-bold shadow-lg"
                                            >
                                                +{{ report.part.product_images.length - 1 }}
                                            </div>
                                            <div class="absolute inset-0 bg-black/60 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                <span class="text-white text-sm font-medium">Lihat Semua</span>
                                            </div>
                                        </div>
                                        <div v-else class="w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center border-2 border-gray-300">
                                            <ImageIcon class="w-8 h-8 text-gray-400" />
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4">
                                    <div :class="[
                                        'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border',
                                        getStatusConfig(report.status).bgClass,
                                        getStatusConfig(report.status).textClass,
                                        getStatusConfig(report.status).borderClass
                                    ]">
                                        <component :is="getStatusConfig(report.status).icon" class="w-3.5 h-3.5" />
                                        {{ getStatusConfig(report.status).label }}
                                    </div>
                                </td>

                                <td class="px-4 py-4">
                                    <div v-if="report.pica_document" class="space-y-2">
                                        <a
                                            :href="`/storage/${report.pica_document}`"
                                            target="_blank"
                                            class="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 font-medium"
                                        >
                                            <FileText class="w-4 h-4" />
                                            Lihat PICA
                                        </a>
                                        <!-- <div class="text-xs text-gray-500">
                                            {{ report.pica_uploaded_by }}
                                        </div> -->
                                        <button
                                            v-if="report.status === 'pica_submitted'"
                                            @click="cancelPica(report.id)"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded-lg hover:bg-amber-100 dark:hover:bg-amber-900/50 transition-colors border border-amber-200 dark:border-amber-800"
                                            title="Batalkan PICA untuk revisi"
                                        >
                                            <XCircle class="w-3.5 h-3.5" />
                                            Revisi PICA
                                        </button>
                                    </div>
                                    <button
                                        v-else-if="report.status === 'open'"
                                        @click="openPicaModal(report)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors"
                                    >
                                        <Upload class="w-4 h-4" />
                                        Upload PICA
                                    </button>
                                    <span v-else class="text-xs text-gray-400">-</span>
                                </td>

                                <td class="px-4 py-4 text-sm">{{ report.reported_by }}</td>

                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            v-if="report.status === 'pica_submitted'"
                                            @click="closeReport(report.id)"
                                            class="p-2 text-green-600 hover:bg-green-100 dark:hover:bg-green-900/30 rounded-lg transition-colors"
                                            title="Tutup Laporan"
                                        >
                                            <CheckCircle class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deleteReport(report.id)"
                                            class="p-2 text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-lg transition-colors"
                                            title="Hapus"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <!-- Pagination -->
                    <div v-if="reports.last_page > 1" class="px-4 py-4 border-t border-sidebar-border bg-gray-50 dark:bg-sidebar-accent">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                Menampilkan {{ reports.data.length }} dari {{ reports.total }} laporan
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    v-for="link in reports.links"
                                    :key="link.label"
                                    @click="link.url && router.get(link.url)"
                                    :disabled="!link.url"
                                    :class="[
                                        'px-3 py-1.5 text-sm rounded-lg transition-colors',
                                        link.active
                                            ? 'bg-red-600 text-white font-semibold'
                                            : link.url
                                                ? 'bg-white dark:bg-sidebar border border-sidebar-border hover:bg-gray-100 dark:hover:bg-sidebar-accent'
                                                : 'bg-gray-100 dark:bg-gray-800 text-gray-400 cursor-not-allowed'
                                    ]"
                                    v-html="link.label"
                                ></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="showModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-xl max-w-6xl w-full p-6 max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Laporan Produk NG</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Laporkan produk yang tidak memenuhi standar kualitas</p>
                    </div>
                    <button
                        @click="showModal = false"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded-lg transition-colors"
                    >
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Pilih Part <span class="text-red-500">*</span>
                        </label>
                        <div ref="partDropdownRef" class="relative w-full">
                            <button
                                type="button"
                                @click="openPartDropdown"
                                class="w-full rounded-lg border border-sidebar-border px-4 py-3 dark:bg-sidebar-accent focus:ring-2 focus:ring-red-500 focus:border-transparent flex items-center justify-between bg-white dark:bg-sidebar text-left transition-colors hover:bg-gray-50 dark:hover:bg-sidebar-accent/80"
                                :class="{ 'ring-2 ring-red-500': showPartDropdown }"
                            >
                                <span :class="form.part_id === 0 ? 'text-gray-500' : 'text-gray-900 dark:text-white'">
                                    {{ selectedPartDisplay }}
                                </span>
                                <Search class="w-5 h-5 text-gray-400" />
                            </button>

                            <div
                                v-if="showPartDropdown"
                                class="absolute z-50 w-full mt-2 bg-white dark:bg-sidebar rounded-lg shadow-2xl border border-sidebar-border max-h-96 overflow-hidden"
                            >

                                <div class="p-3 border-b border-sidebar-border bg-gray-50 dark:bg-sidebar-accent sticky top-0">
                                    <div class="relative">
                                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                        <input
                                            ref="partSearchInputRef"
                                            v-model="partSearchQuery"
                                            type="text"
                                            placeholder="Cari part name, code, atau supplier..."
                                            class="w-full pl-10 pr-9 py-2 rounded-lg border border-sidebar-border dark:bg-sidebar focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm"
                                            @click.stop
                                        />
                                        <button
                                            v-if="partSearchQuery"
                                            type="button"
                                            @click="partSearchQuery = ''"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 p-1 hover:bg-gray-200 dark:hover:bg-sidebar-accent rounded"
                                        >
                                            <X class="w-4 h-4 text-gray-400" />
                                        </button>
                                    </div>
                                </div>

                                <div class="overflow-y-auto max-h-80">
                                    <div v-if="filteredParts.length === 0" class="p-4 text-center text-gray-500 text-sm">
                                        Tidak ada part yang ditemukan
                                    </div>
                                    <button
                                        v-for="part in filteredParts"
                                        :key="part.id"
                                        type="button"
                                        @click="selectPartOption(part.id)"
                                        class="w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-sidebar-accent transition-colors border-b border-sidebar-border last:border-b-0 flex items-center justify-between group"
                                    >
                                        <div class="flex-1">
                                            <div class="font-medium text-sm text-gray-900 dark:text-white">
                                                {{ part.part_name }}
                                            </div>
                                            <div class="text-xs text-gray-500 mt-0.5">
                                                {{ part.part_code }} • {{ part.supplier.supplier_name }}
                                            </div>
                                        </div>
                                        <div v-if="form.part_id === part.id" class="text-red-600 dark:text-red-400">
                                            <Check class="w-5 h-5" />
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-if="form.part_id && (!selectedPart?.product_images || selectedPart.product_images.length === 0)"
                        class="text-xs text-amber-600 dark:text-amber-400 mt-2 flex items-center gap-1.5 bg-amber-50 dark:bg-amber-900/20 px-3 py-2 rounded-lg">
                            <AlertCircle class="w-4 h-4" />
                            Part ini belum memiliki foto referensi standar
                        </p>
                    </div>

                    <div class="bg-gradient-to-br from-red-50 via-white to-green-50 dark:from-red-900/10 dark:via-sidebar dark:to-green-900/10 rounded-xl p-6 border-2 border-dashed border-gray-300 dark:border-gray-700">
                        <div class="flex items-center justify-center gap-3 mb-6">
                            <div class="h-px bg-gradient-to-r from-transparent via-gray-300 to-transparent flex-1"></div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <ImageIcon class="w-5 h-5" />
                                Perbandingan Visual Produk
                            </h3>
                            <div class="h-px bg-gradient-to-r from-transparent via-gray-300 to-transparent flex-1"></div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="bg-white dark:bg-sidebar rounded-xl p-5 shadow-sm border border-red-200 dark:border-red-900/50">
                                <label class="block text-sm font-semibold mb-3 flex items-center gap-2">
                                    <div class="w-3 h-3 bg-red-600 rounded-full animate-pulse"></div>
                                    <span>Upload Foto Produk NG</span>
                                    <span class="text-red-500">*</span>
                                    <span class="text-xs text-gray-500 font-normal ml-auto">(Bisa pilih beberapa foto)</span>
                                </label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    required
                                    @change="handleNgImagesChange"
                                    class="w-full rounded-lg border border-sidebar-border px-3 py-2.5 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-red-50 file:text-red-700 hover:file:bg-red-100 dark:file:bg-red-900/30 dark:file:text-red-400"
                                />

                                <div v-if="ngImagePreviews.length > 0" class="mt-4 grid grid-cols-3 gap-3">
                                    <div
                                        v-for="(preview, index) in ngImagePreviews"
                                        :key="index"
                                        class="relative group"
                                    >
                                        <img
                                            :src="preview"
                                            class="w-full h-28 object-cover rounded-lg shadow-md transition-transform group-hover:scale-105"
                                            :class="index === 0 ? 'border-3 border-red-500' : 'border-2 border-red-300'"
                                        />
                                        <button
                                            type="button"
                                            @click.stop="removeNgImage(index)"
                                            class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full p-1.5 shadow-lg hover:bg-red-700 transition-all z-20"
                                        >
                                            <X class="w-4 h-4" />
                                        </button>
                                        <div class="absolute top-2 left-2 bg-red-600 text-white text-[10px] px-2 py-1 rounded-md font-bold shadow z-10">
                                            {{ index === 0 ? '📸 Utama' : `#${index + 1}` }}
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="mt-4 w-full h-52 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-xl border-2 border-dashed border-red-300 dark:border-red-700 flex flex-col items-center justify-center">
                                    <ImageIcon class="w-14 h-14 text-red-400 mb-3" />
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Upload foto produk bermasalah</p>
                                    <p class="text-xs text-gray-500 mt-1">Klik input di atas untuk pilih foto</p>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-sidebar rounded-xl p-5 shadow-sm border border-green-200 dark:border-green-900/50">
                                <label class="block text-sm font-semibold mb-3 flex items-center gap-2">
                                    <div class="w-3 h-3 bg-green-600 rounded-full"></div>
                                    <span>Foto Referensi Standar (OK)</span>
                                </label>
                                <div class="w-full rounded-lg border border-sidebar-border px-4 py-2.5 bg-gray-50 dark:bg-gray-800 text-sm text-gray-600 dark:text-gray-400 flex items-center gap-2">
                                    <CheckCircle class="w-4 h-4 text-green-600" />
                                    {{ selectedPart ? 'Part Referensi' : 'Pilih part terlebih dahulu' }}
                                </div>

                                <div v-if="okImagePreviews.length > 0" class="mt-4 grid grid-cols-3 gap-3">
                                    <div
                                        v-for="(preview, index) in okImagePreviews"
                                        :key="index"
                                        class="relative group"
                                    >
                                        <img
                                            :src="preview"
                                            class="w-full h-28 object-cover rounded-lg shadow-md transition-transform group-hover:scale-105"
                                            :class="index === 0 ? 'border-3 border-green-500' : 'border-2 border-green-300'"
                                        />
                                        <div class="absolute top-2 left-2 bg-green-600 text-white text-[10px] px-2 py-1 rounded-md font-bold shadow z-10">
                                            {{ index === 0 ? '✓ Utama' : `#${index + 1}` }}
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="mt-4 w-full h-52 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-xl border-2 border-dashed border-green-300 dark:border-green-700 flex flex-col items-center justify-center">
                                    <ImageIcon class="w-14 h-14 text-green-400 mb-3" />
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 text-center px-4">
                                        {{ form.part_id ? 'Part ini belum ada foto referensi' : 'Foto standar akan muncul di sini' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Keterangan Masalah
                        </label>
                        <textarea
                            v-model="form.notes"
                            rows="4"
                            placeholder="Jelaskan secara detail masalah yang ditemukan pada produk (contoh: permukaan tidak rata, warna tidak sesuai, dimensi tidak akurat, dll)..."
                            class="w-full rounded-lg border border-sidebar-border px-4 py-3 dark:bg-sidebar-accent focus:ring-2 focus:ring-red-500 focus:border-transparent"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Nama Pelapor <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.reported_by"
                            type="text"
                            required
                            placeholder="Masukkan nama lengkap pelapor"
                            class="w-full rounded-lg border border-sidebar-border px-4 py-3 dark:bg-sidebar-accent focus:ring-2 focus:ring-red-500 focus:border-transparent"
                        />
                    </div>

                    <div class="flex gap-3 justify-end pt-6 border-t border-sidebar-border">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-6 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-sidebar-accent transition-colors font-medium"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 shadow-lg hover:shadow-xl transition-all font-medium"
                        >
                            <svg v-if="form.processing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <FileText class="w-5 h-5" />
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Laporan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div v-if="showPicaModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-xl max-w-2xl w-full p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Upload PICA Document</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Problem Identification & Corrective Action</p>
                    </div>
                    <button
                        @click="showPicaModal = false"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded-lg transition-colors"
                    >
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <form @submit.prevent="submitPica" class="space-y-6">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-start gap-3">
                            <FileText class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" />
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Laporan: {{ selectedReport?.report_number }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Part: {{ selectedReport?.part.part_name }} ({{ selectedReport?.part.part_code }})
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Upload Dokumen PICA (PDF) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input
                                type="file"
                                accept="application/pdf"
                                required
                                @change="handlePicaChange"
                                class="w-full rounded-lg border border-sidebar-border px-4 py-3 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-400"
                            />
                        </div>
                        <p class="text-xs text-gray-500 mt-2 flex items-center gap-1.5">
                            <AlertCircle class="w-3.5 h-3.5" />
                            Maksimal ukuran file: 10 MB
                        </p>

                        <div v-if="picaForm.pica_document" class="mt-4 bg-gray-50 dark:bg-gray-800 rounded-lg p-4 flex items-center gap-3">
                            <div class="bg-red-100 dark:bg-red-900/30 p-3 rounded-lg">
                                <FileText class="w-6 h-6 text-red-600 dark:text-red-400" />
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ picaForm.pica_document.name }}</p>
                                <p class="text-xs text-gray-500">{{ (picaForm.pica_document.size / 1024 / 1024).toFixed(2) }} MB</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Nama Pengunggah <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="picaForm.pica_uploaded_by"
                            type="text"
                            required
                            placeholder="Masukkan nama lengkap (biasanya dari supplier)"
                            class="w-full rounded-lg border border-sidebar-border px-4 py-3 dark:bg-sidebar-accent focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>

                    <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4 border border-amber-200 dark:border-amber-800">
                        <div class="flex items-start gap-3">
                            <AlertCircle class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" />
                            <div class="text-sm text-amber-800 dark:text-amber-300">
                                <p class="font-semibold mb-1">Informasi PICA:</p>
                                <ul class="list-disc list-inside space-y-1 text-xs">
                                    <li>PICA adalah dokumen tindakan korektif dari supplier</li>
                                    <li>Dokumen harus dalam format PDF</li>
                                    <li>Pastikan dokumen berisi analisa masalah dan solusi</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 justify-end pt-6 border-t border-sidebar-border">
                        <button
                            type="button"
                            @click="showPicaModal = false"
                            class="px-6 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-sidebar-accent transition-colors font-medium"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="picaForm.processing"
                            class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 shadow-lg hover:shadow-xl transition-all font-medium"
                        >
                            <svg v-if="picaForm.processing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <Upload class="w-5 h-5" />
                            {{ picaForm.processing ? 'Mengupload...' : 'Upload PICA' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showImageModal" class="fixed inset-0 bg-black/95 flex items-center justify-center z-50 p-4">
            <div class="relative max-w-6xl w-full h-full flex flex-col py-8">
                <button
                    @click="showImageModal = false"
                    class="absolute top-4 right-4 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white rounded-full p-3 transition-all z-50 shadow-lg"
                >
                    <X class="w-6 h-6" />
                </button>

                <div class="relative flex-1 flex items-center justify-center">
                    <img
                        :src="selectedImages[currentImageIndex]"
                        class="w-full h-auto max-h-[70vh] object-contain rounded-lg shadow-2xl"
                    />

                    <button
                        v-if="currentImageIndex > 0"
                        @click="prevImage"
                        class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-white text-black rounded-full p-3 shadow-lg z-10 transition-all hover:scale-110"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <button
                        v-if="currentImageIndex < selectedImages.length - 1"
                        @click="nextImage"
                        class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-white text-black rounded-full p-3 shadow-lg z-10 transition-all hover:scale-110"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-black/70 text-white px-4 py-2 rounded-full text-sm font-semibold">
                        {{ currentImageIndex + 1 }} / {{ selectedImages.length }}
                    </div>
                </div>

                <div v-if="selectedImages.length > 1" class="flex gap-3 justify-center mt-6 overflow-x-auto pb-2 px-4">
                    <img
                        v-for="(image, index) in selectedImages"
                        :key="index"
                        :src="image"
                        @click="currentImageIndex = index"
                        class="w-24 h-24 object-cover rounded-lg cursor-pointer border-3 transition-all flex-shrink-0"
                        :class="index === currentImageIndex ? 'border-blue-500 scale-110 shadow-xl' : 'border-white/30 opacity-60 hover:opacity-100 hover:scale-105'"
                    />
                </div>
            </div>
        </div>
        <!-- Modal Deskripsi Masalah -->
<div v-if="showNotesModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-sidebar rounded-xl max-w-2xl w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Deskripsi Masalah</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Detail permasalahan pada produk</p>
            </div>
            <button
                @click="showNotesModal = false"
                class="p-2 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded-lg transition-colors"
            >
                <X class="w-6 h-6" />
            </button>
        </div>

        <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-6 border border-sidebar-border">
            <div class="flex items-start gap-3">
                <div class="bg-red-100 dark:bg-red-900/30 p-3 rounded-lg">
                    <FileText class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-900 dark:text-white whitespace-pre-wrap leading-relaxed">{{ selectedNotes }}</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end mt-6 pt-4 border-t border-sidebar-border">
            <button
                @click="showNotesModal = false"
                class="px-6 py-2.5 bg-gray-100 dark:bg-sidebar-accent hover:bg-gray-200 dark:hover:bg-sidebar-accent/80 rounded-lg transition-colors font-medium"
            >
                Tutup
            </button>
        </div>
    </div>
</div>
    </AppLayout>
</template>
