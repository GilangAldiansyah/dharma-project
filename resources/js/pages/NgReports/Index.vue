<!-- eslint-disable @typescript-eslint/no-unused-vars -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import {
    Plus, Trash2, Search, Image as ImageIcon, X, FileText, Upload,
    CheckCircle, Clock, AlertCircle, Download, XCircle, Wrench,
    Ruler, Eye, AlertTriangle, FileDown, ThumbsUp, ThumbsDown,
    ClipboardCheck, PackageCheck, RotateCcw
} from 'lucide-vue-next';

interface NgReport {
    id: number;
    report_number: string;
    ng_types: ('fungsi' | 'dimensi' | 'tampilan')[];
    ng_images: string[];
    notes: string;
    reported_by: string;
    reported_at: string;
    status: 'open' | 'closed';
    temporary_actions: ('repair' | 'tukar_guling' | 'sortir')[] | null;
    temporary_action_notes: string | null;
    ta_submitted_at: string | null;
    ta_submitted_by: string | null;
    ta_status: 'submitted' | 'approved' | 'rejected' | null;
    ta_reviewed_at: string | null;
    ta_reviewed_by: string | null;
    ta_rejection_reason: string | null;
    ta_deadline: string;
    is_ta_deadline_exceeded: boolean;

    // PICA fields
    pica_document: string | null;
    pica_uploaded_at: string | null;
    pica_uploaded_by: string | null;
    pica_status: 'submitted' | 'approved' | 'rejected' | null;
    pica_reviewed_at: string | null;
    pica_reviewed_by: string | null;
    pica_rejection_reason: string | null;
    pica_deadline: string;
    is_pica_deadline_exceeded: boolean;

    can_be_closed: boolean;

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
        ng_type: string;
        ta_status: string;
        pica_status: string;
    };
    template_exists: boolean;
    template_url: string | null;
}

const props = defineProps<Props>();
const showModal = ref(false);
const showTaModal = ref(false);
const showPicaModal = ref(false);
const showImageModal = ref(false);
const showNotesModal = ref(false);
const showTaReviewModal = ref(false);
const showPicaReviewModal = ref(false);
const showTaDetailsModal = ref(false);
const showPicaDetailsModal = ref(false);

const searchQuery = ref(props.filters.search);
const statusFilter = ref(props.filters.status);
const ngTypeFilter = ref(props.filters.ng_type);
const taStatusFilter = ref(props.filters.ta_status);
const picaStatusFilter = ref(props.filters.pica_status);

const ngImagePreviews = ref<string[]>([]);
const okImagePreviews = ref<string[]>([]);
const selectedImages = ref<string[]>([]);
const currentImageIndex = ref(0);
const selectedReport = ref<NgReport | null>(null);
const selectedNotes = ref('');
const selectedPart = ref<any>(null);
const reviewAction = ref<'approve' | 'reject'>('approve');
const showPartDropdown = ref(false);
const partSearchQuery = ref('');
const partDropdownRef = ref<HTMLElement | null>(null);
const partSearchInputRef = ref<HTMLInputElement | null>(null);
const form = useForm({
    part_id: 0,
    ng_types: [] as ('fungsi' | 'dimensi' | 'tampilan')[],
    ng_images: [] as File[],
    notes: '',
    reported_by: '',
});

const taForm = useForm({
    temporary_actions: [] as ('repair' | 'tukar_guling' | 'sortir')[],
    temporary_action_notes: '',
    ta_submitted_by: '',
});

const picaForm = useForm({
    pica_document: null as File | null,
    pica_uploaded_by: '',
});

const taReviewForm = useForm({
    ta_reviewed_by: '',
    ta_rejection_reason: '',
});

const picaReviewForm = useForm({
    pica_reviewed_by: '',
    pica_rejection_reason: '',
});

watch(() => props.filters, (newFilters) => {
    searchQuery.value = newFilters.search;
    statusFilter.value = newFilters.status;
    ngTypeFilter.value = newFilters.ng_type;
    taStatusFilter.value = newFilters.ta_status;
    picaStatusFilter.value = newFilters.pica_status;
}, { deep: true });

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

const getTaTypeConfig = (type: string) => {
    const configs: Record<string, {
        label: string;
        icon: any;
        bgClass: string;
        textClass: string;
        borderClass: string;
    }> = {
        repair: {
            label: 'Repair',
            icon: Wrench,
            bgClass: 'bg-blue-100 dark:bg-blue-900/30',
            textClass: 'text-blue-700 dark:text-blue-400',
            borderClass: 'border-blue-300 dark:border-blue-700'
        },
        tukar_guling: {
            label: 'Tukar Guling',
            icon: RotateCcw,
            bgClass: 'bg-purple-100 dark:bg-purple-900/30',
            textClass: 'text-purple-700 dark:text-purple-400',
            borderClass: 'border-purple-300 dark:border-purple-700'
        },
        sortir: {
            label: 'Sortir',
            icon: PackageCheck,
            bgClass: 'bg-orange-100 dark:bg-orange-900/30',
            textClass: 'text-orange-700 dark:text-orange-400',
            borderClass: 'border-orange-300 dark:border-orange-700'
        }
    };
    return configs[type] || configs.repair;
};

const getNgTypeConfig = (type: string) => {
    const configs: Record<string, {
        label: string;
        icon: any;
        bgClass: string;
        textClass: string;
        borderClass: string;
    }> = {
        fungsi: {
            label: 'Fungsi',
            icon: Wrench,
            bgClass: 'bg-purple-100 dark:bg-purple-900/30',
            textClass: 'text-purple-700 dark:text-purple-400',
            borderClass: 'border-purple-300 dark:border-purple-700'
        },
        dimensi: {
            label: 'Dimensi',
            icon: Ruler,
            bgClass: 'bg-blue-100 dark:bg-blue-900/30',
            textClass: 'text-blue-700 dark:text-blue-400',
            borderClass: 'border-blue-300 dark:border-blue-700'
        },
        tampilan: {
            label: 'Tampilan',
            icon: Eye,
            bgClass: 'bg-orange-100 dark:bg-orange-900/30',
            textClass: 'text-orange-700 dark:text-orange-400',
            borderClass: 'border-orange-300 dark:border-orange-700'
        }
    };
    return configs[type] || configs.fungsi;
};

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
const getTaStatusConfig = (status: string | null | undefined) => {
    const configs: Record<string, {
        label: string;
        icon: any;
        bgClass: string;
        textClass: string;
        borderClass: string;
    }> = {
        submitted: {
            label: 'Menunggu Review',
            icon: Clock,
            bgClass: 'bg-yellow-100 dark:bg-yellow-900/30',
            textClass: 'text-yellow-700 dark:text-yellow-400',
            borderClass: 'border-yellow-300 dark:border-yellow-700'
        },
        approved: {
            label: 'Disetujui',
            icon: CheckCircle,
            bgClass: 'bg-green-100 dark:bg-green-900/30',
            textClass: 'text-green-700 dark:text-green-400',
            borderClass: 'border-green-300 dark:border-green-700'
        },
        rejected: {
            label: 'Ditolak',
            icon: XCircle,
            bgClass: 'bg-red-100 dark:bg-red-900/30',
            textClass: 'text-red-700 dark:text-red-400',
            borderClass: 'border-red-300 dark:border-red-700'
        }
    };

    if (!status || !(status in configs)) {
        return {
            label: 'Belum Submit',
            icon: AlertCircle,
            bgClass: 'bg-gray-100 dark:bg-gray-800',
            textClass: 'text-gray-600 dark:text-gray-400',
            borderClass: 'border-gray-300 dark:border-gray-700'
        };
    }

    return configs[status];
};

const getPicaStatusConfig = (status: string | null | undefined) => {
    const configs: Record<string, {
        label: string;
        icon: any;
        bgClass: string;
        textClass: string;
        borderClass: string;
    }> = {
        submitted: {
            label: 'Menunggu Review',
            icon: Clock,
            bgClass: 'bg-yellow-100 dark:bg-yellow-900/30',
            textClass: 'text-yellow-700 dark:text-yellow-400',
            borderClass: 'border-yellow-300 dark:border-yellow-700'
        },
        approved: {
            label: 'Disetujui',
            icon: CheckCircle,
            bgClass: 'bg-green-100 dark:bg-green-900/30',
            textClass: 'text-green-700 dark:text-green-400',
            borderClass: 'border-green-300 dark:border-green-700'
        },
        rejected: {
            label: 'Ditolak',
            icon: XCircle,
            bgClass: 'bg-red-100 dark:bg-red-900/30',
            textClass: 'text-red-700 dark:text-red-400',
            borderClass: 'border-red-300 dark:border-red-700'
        }
    };

    if (!status || !(status in configs)) {
        return {
            label: 'Belum Upload',
            icon: AlertCircle,
            bgClass: 'bg-gray-100 dark:bg-gray-800',
            textClass: 'text-gray-600 dark:text-gray-400',
            borderClass: 'border-gray-300 dark:border-gray-700'
        };
    }

    return configs[status];
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

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('id-ID');
};

const formatDateShort = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

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
const openModal = () => {
    form.reset();
    form.ng_types = [];
    ngImagePreviews.value = [];
    okImagePreviews.value = [];
    selectedPart.value = null;
    showModal.value = true;
};

const openTaModal = (report: NgReport) => {
    selectedReport.value = report;
    taForm.reset();
    taForm.temporary_actions = [];
    showTaModal.value = true;
};

const openPicaModal = (report: NgReport) => {
    selectedReport.value = report;
    picaForm.reset();
    showPicaModal.value = true;
};

const openTaReviewModal = (report: NgReport, action: 'approve' | 'reject') => {
    selectedReport.value = report;
    reviewAction.value = action;
    taReviewForm.reset();
    showTaReviewModal.value = true;
};

const openPicaReviewModal = (report: NgReport, action: 'approve' | 'reject') => {
    selectedReport.value = report;
    reviewAction.value = action;
    picaReviewForm.reset();
    showPicaReviewModal.value = true;
};

const openTaDetailsModal = (report: NgReport) => {
    selectedReport.value = report;
    showTaDetailsModal.value = true;
};

const openPicaDetailsModal = (report: NgReport) => {
    selectedReport.value = report;
    showPicaDetailsModal.value = true;
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

const removeNgImage = (index: number) => {
    ngImagePreviews.value.splice(index, 1);
    form.ng_images.splice(index, 1);
};

const submit = () => {
    if (form.ng_types.length === 0) {
        alert('Pilih minimal 1 jenis NG');
        return;
    }

    form.post('/ng-reports', {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
            form.ng_types = [];
            ngImagePreviews.value = [];
            okImagePreviews.value = [];
            selectedPart.value = null;
        },
    });
};

const submitTemporaryAction = () => {
    if (!selectedReport.value) return;
    if (taForm.temporary_actions.length === 0) {
        alert('Pilih minimal 1 temporary action');
        return;
    }

    taForm.post(`/ng-reports/${selectedReport.value.id}/temporary-action`, {
        preserveScroll: true,
        onSuccess: () => {
            showTaModal.value = false;
            taForm.reset();
            selectedReport.value = null;
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

const submitTaReview = () => {
    if (!selectedReport.value) {
        console.error('No report selected');
        return;
    }

    // Validate rejection reason if rejecting
    if (reviewAction.value === 'reject' && (!taReviewForm.ta_rejection_reason || taReviewForm.ta_rejection_reason.length < 10)) {
        alert('Alasan penolakan minimal 10 karakter');
        return;
    }

    // Validate reviewer name
    if (!taReviewForm.ta_reviewed_by) {
        alert('Nama reviewer harus diisi');
        return;
    }

    const url = reviewAction.value === 'approve'
        ? `/ng-reports/${selectedReport.value.id}/temporary-action/approve`
        : `/ng-reports/${selectedReport.value.id}/temporary-action/reject`;

    console.log('Submitting TA Review:', {
        url,
        action: reviewAction.value,
        data: {
            ta_reviewed_by: taReviewForm.ta_reviewed_by,
            ta_rejection_reason: taReviewForm.ta_rejection_reason
        }
    });

    taReviewForm.post(url, {
        preserveScroll: true,
        onSuccess: (response) => {
            console.log('TA Review Success', response);
            showTaReviewModal.value = false;
            taReviewForm.reset();
            selectedReport.value = null;
        },
        onError: (errors) => {
            console.error('TA Review Error:', errors);
            alert('Terjadi kesalahan: ' + (Object.values(errors)[0] || 'Unknown error'));
        },
        onFinish: () => {
            console.log('TA Review Request Finished');
        }
    });
};

const submitPicaReview = () => {
    if (!selectedReport.value) {
        console.error('No report selected');
        return;
    }

    // Validate rejection reason if rejecting
    if (reviewAction.value === 'reject' && (!picaReviewForm.pica_rejection_reason || picaReviewForm.pica_rejection_reason.length < 10)) {
        alert('Alasan penolakan minimal 10 karakter');
        return;
    }

    // Validate reviewer name
    if (!picaReviewForm.pica_reviewed_by) {
        alert('Nama reviewer harus diisi');
        return;
    }

    const url = reviewAction.value === 'approve'
        ? `/ng-reports/${selectedReport.value.id}/pica/approve`
        : `/ng-reports/${selectedReport.value.id}/pica/reject`;

    console.log('Submitting PICA Review:', {
        url,
        action: reviewAction.value,
        data: {
            pica_reviewed_by: picaReviewForm.pica_reviewed_by,
            pica_rejection_reason: picaReviewForm.pica_rejection_reason
        }
    });

    picaReviewForm.post(url, {
        preserveScroll: true,
        onSuccess: (response) => {
            console.log('PICA Review Success', response);
            showPicaReviewModal.value = false;
            picaReviewForm.reset();
            selectedReport.value = null;
        },
        onError: (errors) => {
            console.error('PICA Review Error:', errors);
            alert('Terjadi kesalahan: ' + (Object.values(errors)[0] || 'Unknown error'));
        },
        onFinish: () => {
            console.log('PICA Review Request Finished');
        }
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
        status: statusFilter.value,
        ng_type: ngTypeFilter.value,
        ta_status: taStatusFilter.value,
        pica_status: picaStatusFilter.value
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

const viewNotes = (notes: string) => {
    selectedNotes.value = notes;
    showNotesModal.value = true;
};

const downloadTemplate = () => {
    if (!props.template_exists) {
        alert('Template belum tersedia! Silakan hubungi admin untuk mengupload template.');
        return;
    }

    if (props.template_url) {
        window.open(props.template_url, '_blank');
    }
};

</script>
<template>
    <Head title="Laporan NG" />
    <AppLayout :breadcrumbs="[{ title: 'Laporan NG', href: '/ng-reports' }]">
        <div class="p-4 space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Laporan NG</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Kelola laporan produk NG dengan Temporary Action & PICA</p>
                </div>
                <div class="flex gap-3">
                    <button
                        @click="downloadTemplate"
                        :disabled="!template_exists"
                        :class="[
                            'flex items-center gap-2 px-4 py-2.5 rounded-lg shadow-lg hover:shadow-xl transition-all',
                            template_exists
                                ? 'bg-blue-600 text-white hover:bg-blue-700'
                                : 'bg-gray-300 text-gray-500 cursor-not-allowed'  // ← TAMBAHKAN styling disabled
                        ]"
                    >
                        <FileDown class="w-5 h-5" />
                        <span class="font-medium">Download Template</span>
                    </button>
                    <button
                        @click="openModal()"
                        class="flex items-center gap-2 px-4 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 shadow-lg hover:shadow-xl transition-all"
                    >
                        <Plus class="w-5 h-5" />
                        <span class="font-medium">Lapor NG</span>
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-sidebar rounded-lg border border-sidebar-border p-4 shadow-sm">
                <div class="flex flex-col gap-3">
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
                            v-model="ngTypeFilter"
                            @change="search"
                            class="px-4 py-2.5 rounded-lg border border-sidebar-border dark:bg-sidebar-accent min-w-[160px]"
                        >
                            <option value="all">Semua Jenis NG</option>
                            <option value="fungsi">Fungsi</option>
                            <option value="dimensi">Dimensi</option>
                            <option value="tampilan">Tampilan</option>
                        </select>
                        <button
                            @click="search"
                            class="px-4 py-2.5 bg-gray-100 dark:bg-sidebar-accent hover:bg-gray-200 dark:hover:bg-sidebar-accent/80 rounded-lg transition-colors"
                        >
                            <Search class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <select
                            v-model="statusFilter"
                            @change="search"
                            class="px-4 py-2.5 rounded-lg border border-sidebar-border dark:bg-sidebar-accent flex-1"
                        >
                            <option value="all">Semua Status</option>
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </select>
                        <select
                            v-model="taStatusFilter"
                            @change="search"
                            class="px-4 py-2.5 rounded-lg border border-sidebar-border dark:bg-sidebar-accent flex-1"
                        >
                            <option value="all">Semua TA Status</option>
                            <option value="submitted">TA: Menunggu Review</option>
                            <option value="approved">TA: Disetujui</option>
                            <option value="rejected">TA: Ditolak</option>
                        </select>
                        <select
                            v-model="picaStatusFilter"
                            @change="search"
                            class="px-4 py-2.5 rounded-lg border border-sidebar-border dark:bg-sidebar-accent flex-1"
                        >
                            <option value="all">Semua PICA Status</option>
                            <option value="submitted">PICA: Menunggu Review</option>
                            <option value="approved">PICA: Disetujui</option>
                            <option value="rejected">PICA: Ditolak</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-sidebar rounded-lg border border-sidebar-border overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1600px]">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b-2 border-sidebar-border">
                            <tr>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 w-[160px]">
                                    No. Laporan
                                </th>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 w-[200px]">
                                    Part
                                </th>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 w-[150px]">
                                    Supplier
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 w-[110px]">
                                    Foto NG
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 w-[140px]">
                                    Jenis NG
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 w-[110px]">
                                    Foto Referensi
                                </th>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 w-[120px]">
                                    Pelapor
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 w-[160px]">
                                    TA Status
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 w-[160px]">
                                    PICA Status
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 w-[110px]">
                                    Status
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 w-[180px]">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr
                                v-for="report in reports.data"
                                :key="report.id"
                                class="hover:bg-gray-50 dark:hover:bg-sidebar-accent/50 transition-colors"
                            >
                                <td class="px-4 py-4 align-top">
                                    <div class="flex items-start gap-2">
                                        <!-- Warning Indicators with Tooltip -->
                                        <div class="flex flex-col gap-1 pt-1">
                                            <div
                                                v-if="report.is_ta_deadline_exceeded && !report.ta_submitted_at"
                                                class="relative group"
                                            >
                                                <div class="w-3 h-3 bg-red-500 rounded-full animate-pulse cursor-help"></div>
                                                <div class="absolute left-full ml-2 top-1/2 -translate-y-1/2 hidden group-hover:block z-10">
                                                    <div class="bg-red-600 text-white text-xs px-2 py-1 rounded whitespace-nowrap shadow-lg">
                                                        ⚠️ TA Deadline Terlewat!
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                v-if="report.is_pica_deadline_exceeded && !report.pica_uploaded_at"
                                                class="relative group"
                                            >
                                                <div class="w-3 h-3 bg-orange-500 rounded-full animate-pulse cursor-help"></div>
                                                <div class="absolute left-full ml-2 top-1/2 -translate-y-1/2 hidden group-hover:block z-10">
                                                    <div class="bg-orange-600 text-white text-xs px-2 py-1 rounded whitespace-nowrap shadow-lg">
                                                        ⚠️ PICA Deadline Terlewat!
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Report Info -->
                                        <div class="flex-1">
                                            <div class="font-semibold text-sm text-gray-900 dark:text-white">{{ report.report_number }}</div>
                                            <div class="text-xs text-gray-500 mt-1">{{ formatDate(report.reported_at) }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4 align-top">
                                    <div class="font-medium text-sm text-gray-900 dark:text-white">{{ report.part.part_name }}</div>
                                    <div class="text-xs text-gray-500 mt-1">{{ report.part.part_code }}</div>
                                    <button
                                        v-if="report.notes"
                                        @click="viewNotes(report.notes)"
                                        class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors"
                                    >
                                        <FileText class="w-3.5 h-3.5" />
                                        Lihat Masalah
                                    </button>
                                </td>

                                <td class="px-4 py-4 align-top text-sm text-gray-900 dark:text-white">{{ report.part.supplier.supplier_name }}</td>

                                <td class="px-4 py-4 align-top">
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
                                                <span class="text-white text-xs font-medium">Lihat Semua</span>
                                            </div>
                                        </div>
                                        <div v-else class="w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center">
                                            <ImageIcon class="w-8 h-8 text-gray-400" />
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4 align-top">
                                    <div class="flex flex-wrap justify-center gap-1.5">
                                        <div
                                            v-for="type in report.ng_types"
                                            :key="type"
                                            :class="[
                                                'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold border',
                                                getNgTypeConfig(type).bgClass,
                                                getNgTypeConfig(type).textClass,
                                                getNgTypeConfig(type).borderClass
                                            ]"
                                        >
                                            <component :is="getNgTypeConfig(type).icon" class="w-3 h-3" />
                                            {{ getNgTypeConfig(type).label }}
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4 align-top">
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
                                                <span class="text-white text-xs font-medium">Lihat Semua</span>
                                            </div>
                                        </div>
                                        <div v-else class="w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center border-2 border-gray-300">
                                            <ImageIcon class="w-8 h-8 text-gray-400" />
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4 align-top text-sm text-gray-900 dark:text-white">{{ report.reported_by }}</td>

                                <td class="px-4 py-4 align-top">
                                    <div class="flex flex-col items-center gap-2">
                                        <div :class="[
                                            'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border whitespace-nowrap',
                                            getTaStatusConfig(report.ta_status).bgClass,
                                            getTaStatusConfig(report.ta_status).textClass,
                                            getTaStatusConfig(report.ta_status).borderClass
                                        ]">
                                            <component :is="getTaStatusConfig(report.ta_status).icon" class="w-3.5 h-3.5" />
                                            {{ getTaStatusConfig(report.ta_status).label }}
                                        </div>

                                        <div v-if="report.ta_submitted_at" class="text-xs text-gray-500 text-center">
                                            <div>Deadline: {{ formatDateShort(report.ta_deadline) }}</div>
                                        </div>

                                        <button
                                            v-if="report.temporary_actions && report.temporary_actions.length > 0"
                                            @click="openTaDetailsModal(report)"
                                            class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 underline"
                                        >
                                            Lihat Detail
                                        </button>
                                    </div>
                                </td>

                                <td class="px-4 py-4 align-top">
                                    <div class="flex flex-col items-center gap-2">
                                        <div :class="[
                                            'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border whitespace-nowrap',
                                            getPicaStatusConfig(report.pica_status).bgClass,
                                            getPicaStatusConfig(report.pica_status).textClass,
                                            getPicaStatusConfig(report.pica_status).borderClass
                                        ]">
                                            <component :is="getPicaStatusConfig(report.pica_status).icon" class="w-3.5 h-3.5" />
                                            {{ getPicaStatusConfig(report.pica_status).label }}
                                        </div>

                                        <div v-if="report.pica_uploaded_at" class="text-xs text-gray-500 text-center">
                                            <div>Deadline: {{ formatDateShort(report.pica_deadline) }}</div>
                                        </div>

                                        <a
                                            v-if="report.pica_document"
                                            :href="`/storage/${report.pica_document}`"
                                            target="_blank"
                                            class="inline-flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 font-medium underline"
                                        >
                                            <FileText class="w-3.5 h-3.5" />
                                            Lihat PICA
                                        </a>
                                    </div>
                                </td>

                                <td class="px-4 py-4 align-top">
                                    <div class="flex justify-center">
                                        <div :class="[
                                            'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border whitespace-nowrap',
                                            getStatusConfig(report.status).bgClass,
                                            getStatusConfig(report.status).textClass,
                                            getStatusConfig(report.status).borderClass
                                        ]">
                                            <component :is="getStatusConfig(report.status).icon" class="w-3.5 h-3.5" />
                                            {{ getStatusConfig(report.status).label }}
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4 align-top">
                                    <div class="flex flex-col items-stretch gap-2">
                                        <button
                                            v-if="!report.ta_submitted_at || report.ta_status === 'rejected'"
                                            @click="openTaModal(report)"
                                            class="w-full px-3 py-1.5 text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors font-medium whitespace-nowrap"
                                        >
                                            {{ report.ta_status === 'rejected' ? 'Revisi TA' : 'Input TA' }}
                                        </button>

                                        <button
                                            v-if="report.ta_status === 'submitted'"
                                            @click="openTaReviewModal(report, 'approve')"
                                            class="w-full px-3 py-1.5 text-xs bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/50 transition-colors font-medium flex items-center justify-center gap-1.5 whitespace-nowrap"
                                        >
                                            <ThumbsUp class="w-3.5 h-3.5" />
                                            Approve TA
                                        </button>

                                        <button
                                            v-if="report.ta_status === 'submitted'"
                                            @click="openTaReviewModal(report, 'reject')"
                                            class="w-full px-3 py-1.5 text-xs bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors font-medium flex items-center justify-center gap-1.5 whitespace-nowrap"
                                        >
                                            <ThumbsDown class="w-3.5 h-3.5" />
                                            Reject TA
                                        </button>

                                        <button
                                            v-if="report.ta_submitted_at && (!report.pica_uploaded_at || report.pica_status === 'rejected')"
                                            @click="openPicaModal(report)"
                                            class="w-full px-3 py-1.5 text-xs bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/50 transition-colors font-medium whitespace-nowrap"
                                        >
                                            {{ report.pica_status === 'rejected' ? 'Revisi PICA' : 'Upload PICA' }}
                                        </button>

                                        <button
                                            v-if="report.pica_status === 'submitted'"
                                            @click="openPicaReviewModal(report, 'approve')"
                                            class="w-full px-3 py-1.5 text-xs bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/50 transition-colors font-medium flex items-center justify-center gap-1.5 whitespace-nowrap"
                                        >
                                            <ThumbsUp class="w-3.5 h-3.5" />
                                            Approve PICA
                                        </button>

                                        <button
                                            v-if="report.pica_status === 'submitted'"
                                            @click="openPicaReviewModal(report, 'reject')"
                                            class="w-full px-3 py-1.5 text-xs bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors font-medium flex items-center justify-center gap-1.5 whitespace-nowrap"
                                        >
                                            <ThumbsDown class="w-3.5 h-3.5" />
                                            Reject PICA
                                        </button>

                                        <button
                                            v-if="report.can_be_closed"
                                            @click="closeReport(report.id)"
                                            class="w-full px-3 py-1.5 text-xs bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/50 transition-colors font-medium flex items-center justify-center gap-1.5 whitespace-nowrap"
                                        >
                                            <CheckCircle class="w-3.5 h-3.5" />
                                            Tutup Laporan
                                        </button>

                                        <button
                                            @click="deleteReport(report.id)"
                                            class="w-full px-3 py-1.5 text-xs bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors font-medium flex items-center justify-center gap-1.5 whitespace-nowrap"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="reports.data.length === 0" class="text-center py-12">
                        <AlertCircle class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                        <p class="text-gray-500 dark:text-gray-400 font-medium">Tidak ada data laporan NG</p>
                        <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Silakan buat laporan baru dengan klik tombol "Lapor NG"</p>
                    </div>

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

        <!-- Modal Lapor NG -->
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
                                            <CheckCircle class="w-5 h-5" />
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

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Jenis NG <span class="text-red-500">*</span>
                            <span class="text-xs font-normal text-gray-500 ml-2">(Bisa pilih lebih dari 1)</span>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label
                                class="relative flex items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                                :class="form.ng_types.includes('fungsi') ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20' : 'border-sidebar-border'"
                            >
                                <input
                                    type="checkbox"
                                    v-model="form.ng_types"
                                    value="fungsi"
                                    class="sr-only"
                                />
                                <div class="flex items-center justify-center w-10 h-10 rounded-lg" :class="form.ng_types.includes('fungsi') ? 'bg-purple-100 dark:bg-purple-900/40' : 'bg-gray-100 dark:bg-gray-800'">
                                    <Wrench :class="form.ng_types.includes('fungsi') ? 'text-purple-600 dark:text-purple-400' : 'text-gray-400'" class="w-5 h-5" />
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-sm" :class="form.ng_types.includes('fungsi') ? 'text-purple-700 dark:text-purple-400' : 'text-gray-900 dark:text-white'">
                                        Fungsi
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Tidak berfungsi dengan baik
                                    </div>
                                </div>
                                <div v-if="form.ng_types.includes('fungsi')" class="absolute top-2 right-2">
                                    <CheckCircle class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                                </div>
                            </label>

                            <label
                                class="relative flex items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                                :class="form.ng_types.includes('dimensi') ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-sidebar-border'"
                            >
                                <input
                                    type="checkbox"
                                    v-model="form.ng_types"
                                    value="dimensi"
                                    class="sr-only"
                                />
                                <div class="flex items-center justify-center w-10 h-10 rounded-lg" :class="form.ng_types.includes('dimensi') ? 'bg-blue-100 dark:bg-blue-900/40' : 'bg-gray-100 dark:bg-gray-800'">
                                    <Ruler :class="form.ng_types.includes('dimensi') ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400'" class="w-5 h-5" />
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-sm" :class="form.ng_types.includes('dimensi') ? 'text-blue-700 dark:text-blue-400' : 'text-gray-900 dark:text-white'">
                                        Dimensi
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Ukuran tidak sesuai standar
                                    </div>
                                </div>
                                <div v-if="form.ng_types.includes('dimensi')" class="absolute top-2 right-2">
                                    <CheckCircle class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                </div>
                            </label>

                            <label
                                class="relative flex items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                                :class="form.ng_types.includes('tampilan') ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20' : 'border-sidebar-border'"
                            >
                                <input
                                    type="checkbox"
                                    v-model="form.ng_types"
                                    value="tampilan"
                                    class="sr-only"
                                />
                                <div class="flex items-center justify-center w-10 h-10 rounded-lg" :class="form.ng_types.includes('tampilan') ? 'bg-orange-100 dark:bg-orange-900/40' : 'bg-gray-100 dark:bg-gray-800'">
                                    <Eye :class="form.ng_types.includes('tampilan') ? 'text-orange-600 dark:text-orange-400' : 'text-gray-400'" class="w-5 h-5" />
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-sm" :class="form.ng_types.includes('tampilan') ? 'text-orange-700 dark:text-orange-400' : 'text-gray-900 dark:text-white'">
                                        Tampilan
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Cacat visual, warna, dll
                                    </div>
                                </div>
                                <div v-if="form.ng_types.includes('tampilan')" class="absolute top-2 right-2">
                                    <CheckCircle class="w-5 h-5 text-orange-600 dark:text-orange-400" />
                                </div>
                            </label>
                        </div>

                        <div v-if="form.ng_types.length > 0" class="mt-3 flex items-center gap-2 flex-wrap">
                            <span class="text-xs text-gray-600 dark:text-gray-400">Dipilih:</span>
                            <span v-for="type in form.ng_types" :key="type" :class="[
                                'inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium',
                                type === 'fungsi' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' :
                                type === 'dimensi' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' :
                                'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                            ]">
                                <component :is="type === 'fungsi' ? Wrench : type === 'dimensi' ? Ruler : Eye" class="w-3 h-3" />
                                {{ type === 'fungsi' ? 'Fungsi' : type === 'dimensi' ? 'Dimensi' : 'Tampilan' }}
                            </span>
                        </div>

                        <p v-if="form.errors.ng_types" class="text-xs text-red-600 dark:text-red-400 mt-2">
                            {{ form.errors.ng_types }}
                        </p>
                        <p v-else-if="form.ng_types.length === 0" class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Minimal pilih 1 jenis NG
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

        <!-- Modal Input TA -->
        <div v-if="showTaModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-xl max-w-3xl w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Input Temporary Action</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Tindakan sementara untuk mengatasi masalah NG</p>
                    </div>
                    <button
                        @click="showTaModal = false"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded-lg transition-colors"
                    >
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <form @submit.prevent="submitTemporaryAction" class="space-y-6">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-start gap-3">
                            <FileText class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Laporan: {{ selectedReport?.report_number }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Part: {{ selectedReport?.part.part_name }} ({{ selectedReport?.part.part_code }})
                                </p>
                                <div class="mt-2 flex items-center gap-2 text-xs">
                                    <Clock class="w-3.5 h-3.5 text-amber-600" />
                                    <span class="text-amber-700 dark:text-amber-400 font-semibold">
                                        Deadline: {{ selectedReport ? formatDateShort(selectedReport.ta_deadline) : '-' }} (Max 1 hari dari laporan)
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="selectedReport?.ta_status === 'rejected' && selectedReport?.ta_rejection_reason" class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-800">
                        <div class="flex items-start gap-3">
                            <XCircle class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 flex-shrink-0" />
                            <div>
                                <p class="text-sm font-semibold text-red-900 dark:text-red-200">Alasan Penolakan:</p>
                                <p class="text-sm text-red-700 dark:text-red-300 mt-1">{{ selectedReport.ta_rejection_reason }}</p>
                                <p class="text-xs text-red-600 dark:text-red-400 mt-2">Silakan perbaiki dan submit ulang</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-3 text-gray-700 dark:text-gray-300">
                            Pilih Temporary Action <span class="text-red-500">*</span>
                            <span class="text-xs font-normal text-gray-500 ml-2">(Bisa pilih lebih dari 1)</span>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label
                                class="relative flex flex-col items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                                :class="taForm.temporary_actions.includes('repair') ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-sidebar-border'"
                            >
                                <input
                                    type="checkbox"
                                    v-model="taForm.temporary_actions"
                                    value="repair"
                                    class="sr-only"
                                />
                                <div class="flex items-center justify-center w-14 h-14 rounded-lg" :class="taForm.temporary_actions.includes('repair') ? 'bg-blue-100 dark:bg-blue-900/40' : 'bg-gray-100 dark:bg-gray-800'">
                                    <Wrench :class="taForm.temporary_actions.includes('repair') ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400'" class="w-7 h-7" />
                                </div>
                                <div class="text-center">
                                    <div class="font-semibold text-sm" :class="taForm.temporary_actions.includes('repair') ? 'text-blue-700 dark:text-blue-400' : 'text-gray-900 dark:text-white'">
                                        Repair
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Perbaikan produk NG
                                    </div>
                                </div>
                                <div v-if="taForm.temporary_actions.includes('repair')" class="absolute top-2 right-2">
                                    <CheckCircle class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                </div>
                            </label>

                            <label
                                class="relative flex flex-col items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                                :class="taForm.temporary_actions.includes('tukar_guling') ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20' : 'border-sidebar-border'"
                            >
                                <input
                                    type="checkbox"
                                    v-model="taForm.temporary_actions"
                                    value="tukar_guling"
                                    class="sr-only"
                                />
                                <div class="flex items-center justify-center w-14 h-14 rounded-lg" :class="taForm.temporary_actions.includes('tukar_guling') ? 'bg-purple-100 dark:bg-purple-900/40' : 'bg-gray-100 dark:bg-gray-800'">
                                    <RotateCcw :class="taForm.temporary_actions.includes('tukar_guling') ? 'text-purple-600 dark:text-purple-400' : 'text-gray-400'" class="w-7 h-7" />
                                </div>
                                <div class="text-center">
                                    <div class="font-semibold text-sm" :class="taForm.temporary_actions.includes('tukar_guling') ? 'text-purple-700 dark:text-purple-400' : 'text-gray-900 dark:text-white'">
                                        Tukar Guling
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Penggantian produk
                                    </div>
                                </div>
                                <div v-if="taForm.temporary_actions.includes('tukar_guling')" class="absolute top-2 right-2">
                                    <CheckCircle class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                                </div>
                            </label>

                            <label
                                class="relative flex flex-col items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                                :class="taForm.temporary_actions.includes('sortir') ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20' : 'border-sidebar-border'"
                            >
                                <input
                                    type="checkbox"
                                    v-model="taForm.temporary_actions"
                                    value="sortir"
                                    class="sr-only"
                                />
                                <div class="flex items-center justify-center w-14 h-14 rounded-lg" :class="taForm.temporary_actions.includes('sortir') ? 'bg-orange-100 dark:bg-orange-900/40' : 'bg-gray-100 dark:bg-gray-800'">
                                    <PackageCheck :class="taForm.temporary_actions.includes('sortir') ? 'text-orange-600 dark:text-orange-400' : 'text-gray-400'" class="w-7 h-7" />
                                </div>
                                <div class="text-center">
                                    <div class="font-semibold text-sm" :class="taForm.temporary_actions.includes('sortir') ? 'text-orange-700 dark:text-orange-400' : 'text-gray-900 dark:text-white'">
                                        Sortir
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Pemilahan/pemisahan
                                    </div>
                                </div>
                                <div v-if="taForm.temporary_actions.includes('sortir')" class="absolute top-2 right-2">
                                    <CheckCircle class="w-5 h-5 text-orange-600 dark:text-orange-400" />
                                </div>
                            </label>
                        </div>

                        <div v-if="taForm.temporary_actions.length > 0" class="mt-3 flex items-center gap-2 flex-wrap">
                            <span class="text-xs text-gray-600 dark:text-gray-400">Dipilih:</span>
                            <span v-for="action in taForm.temporary_actions" :key="action" :class="[
                                'inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium',
                                action === 'repair' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' :
                                action === 'tukar_guling' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' :
                                'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                            ]">
                                <component :is="getTaTypeConfig(action).icon" class="w-3 h-3" />
                                {{ getTaTypeConfig(action).label }}
                            </span>
                        </div>

                        <p v-if="taForm.temporary_actions.length === 0" class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Minimal pilih 1 temporary action
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Keterangan Tambahan
                            <span class="text-xs font-normal text-gray-500 ml-2">(Opsional - untuk tindakan lain atau detail lebih lanjut)</span>
                        </label>
                        <textarea
                            v-model="taForm.temporary_action_notes"
                            rows="4"
                            placeholder="Contoh: Akan dilakukan rework pada bagian permukaan yang cacat, atau tindakan tambahan lainnya..."
                            class="w-full rounded-lg border border-sidebar-border px-4 py-3 dark:bg-sidebar-accent focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Nama Penanggung Jawab <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="taForm.ta_submitted_by"
                            type="text"
                            required
                            placeholder="Masukkan nama lengkap penanggung jawab TA"
                            class="w-full rounded-lg border border-sidebar-border px-4 py-3 dark:bg-sidebar-accent focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>

                    <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4 border border-amber-200 dark:border-amber-800">
                        <div class="flex items-start gap-3">
                            <AlertTriangle class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" />
                            <div class="text-sm text-amber-800 dark:text-amber-300">
                                <p class="font-semibold mb-1">Catatan Penting:</p>
                                <ul class="list-disc list-inside space-y-1 text-xs">
                                    <li>Temporary Action harus disubmit maksimal <strong>1 hari</strong> setelah laporan dibuat</li>
                                    <li>Setelah TA disubmit, Anda dapat langsung upload PICA tanpa menunggu approval</li>
                                    <li>TA yang ditolak harus diperbaiki dan disubmit ulang</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 justify-end pt-6 border-t border-sidebar-border">
                        <button
                            type="button"
                            @click="showTaModal = false"
                            class="px-6 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-sidebar-accent transition-colors font-medium"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="taForm.processing"
                            class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 shadow-lg hover:shadow-xl transition-all font-medium"
                        >
                            <svg v-if="taForm.processing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <ClipboardCheck class="w-5 h-5" />
                            {{ taForm.processing ? 'Menyimpan...' : 'Submit Temporary Action' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Upload PICA -->
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
                            <FileText class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Laporan: {{ selectedReport?.report_number }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Part: {{ selectedReport?.part.part_name }} ({{ selectedReport?.part.part_code }})
                                </p>
                                <div class="mt-2 flex items-center gap-2 text-xs">
                                    <Clock class="w-3.5 h-3.5 text-amber-600" />
                                    <span class="text-amber-700 dark:text-amber-400 font-semibold">
                                        Deadline: {{ selectedReport ? formatDateShort(selectedReport.pica_deadline) : '-' }} (Max 3 hari dari laporan)
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedReport?.pica_status === 'rejected' && selectedReport?.pica_rejection_reason" class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-800">
                        <div class="flex items-start gap-3">
                            <XCircle class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 flex-shrink-0" />
                            <div>
                                <p class="text-sm font-semibold text-red-900 dark:text-red-200">Alasan Penolakan:</p>
                                <p class="text-sm text-red-700 dark:text-red-300 mt-1">{{ selectedReport.pica_rejection_reason }}</p>
                                <p class="text-xs text-red-600 dark:text-red-400 mt-2">Silakan perbaiki dan upload ulang</p>
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
                                    <li>PICA harus diupload maksimal <strong>3 hari</strong> setelah laporan dibuat</li>
                                    <li>Pastikan dokumen berisi analisa masalah dan solusi</li>
                                    <li>PICA yang ditolak harus diperbaiki dan diupload ulang</li>
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

        <!-- Modal TA Review -->
        <div v-if="showTaReviewModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-xl max-w-2xl w-full p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ reviewAction === 'approve' ? 'Approve' : 'Reject' }} Temporary Action
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ reviewAction === 'approve' ? 'Setujui tindakan sementara' : 'Tolak dan minta revisi' }}
                        </p>
                    </div>
                    <button
                        @click="showTaReviewModal = false"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded-lg transition-colors"
                    >
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <form @submit.prevent="submitTaReview" class="space-y-6">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-start gap-3">
                            <FileText class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Laporan: {{ selectedReport?.report_number }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Part: {{ selectedReport?.part.part_name }} ({{ selectedReport?.part.part_code }})
                                </p>
                                <div v-if="selectedReport?.temporary_actions" class="mt-3">
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Temporary Action:</p>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="action in selectedReport.temporary_actions"
                                            :key="action"
                                            :class="[
                                                'inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium',
                                                getTaTypeConfig(action).bgClass,
                                                getTaTypeConfig(action).textClass
                                            ]"
                                        >
                                            <component :is="getTaTypeConfig(action).icon" class="w-3 h-3" />
                                            {{ getTaTypeConfig(action).label }}
                                        </span>
                                    </div>
                                    <p v-if="selectedReport.temporary_action_notes" class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                                        <span class="font-semibold">Notes:</span> {{ selectedReport.temporary_action_notes }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="reviewAction === 'reject'">
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Alasan Penolakan <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="taReviewForm.ta_rejection_reason"
                            rows="4"
                            placeholder="Jelaskan alasan penolakan dan apa yang perlu diperbaiki..."
                            class="w-full rounded-lg border border-sidebar-border px-4 py-3 dark:bg-sidebar-accent focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            :class="{ 'border-red-500': taReviewForm.errors.ta_rejection_reason }"
                        ></textarea>
                        <p v-if="taReviewForm.errors.ta_rejection_reason" class="text-xs text-red-600 dark:text-red-400 mt-1">
                            {{ taReviewForm.errors.ta_rejection_reason }}
                        </p>
                        <p v-else class="text-xs text-gray-500 mt-1">Minimal 10 karakter</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Nama PIC <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="taReviewForm.ta_reviewed_by"
                            type="text"
                            required
                            placeholder="Masukkan nama lengkap reviewer"
                            class="w-full rounded-lg border border-sidebar-border px-4 py-3 dark:bg-sidebar-accent focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>

                    <div class="flex gap-3 justify-end pt-6 border-t border-sidebar-border">
                        <button
                            type="button"
                            @click="showTaReviewModal = false"
                            class="px-6 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-sidebar-accent transition-colors font-medium"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="taReviewForm.processing"
                            :class="[
                                'px-6 py-2.5 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 shadow-lg hover:shadow-xl transition-all font-medium',
                                reviewAction === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'
                            ]"
                        >
                            <svg v-if="taReviewForm.processing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <component :is="reviewAction === 'approve' ? ThumbsUp : ThumbsDown" class="w-5 h-5" />
                            {{ taReviewForm.processing ? 'Processing...' : (reviewAction === 'approve' ? 'Approve TA' : 'Reject TA') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Modal PICA Review -->
        <div v-if="showPicaReviewModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-xl max-w-2xl w-full p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ reviewAction === 'approve' ? 'Approve' : 'Reject' }} PICA
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ reviewAction === 'approve' ? 'Setujui dokumen PICA' : 'Tolak dan minta revisi' }}
                        </p>
                    </div>
                    <button
                        @click="showPicaReviewModal = false"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded-lg transition-colors"
                    >
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <form @submit.prevent="submitPicaReview" class="space-y-6">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-start gap-3">
                            <FileText class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Laporan: {{ selectedReport?.report_number }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Part: {{ selectedReport?.part.part_name }} ({{ selectedReport?.part.part_code }})
                                </p>
                                <a
                                    v-if="selectedReport?.pica_document"
                                    :href="`/storage/${selectedReport.pica_document}`"
                                    target="_blank"
                                    class="mt-3 inline-flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 font-medium underline"
                                >
                                    <FileText class="w-3.5 h-3.5" />
                                    Lihat Dokumen PICA
                                </a>
                            </div>
                        </div>
                    </div>

                    <div v-if="reviewAction === 'reject'">
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Alasan Penolakan <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="picaReviewForm.pica_rejection_reason"
                            rows="4"
                            placeholder="Jelaskan alasan penolakan dan apa yang perlu diperbaiki..."
                            class="w-full rounded-lg border border-sidebar-border px-4 py-3 dark:bg-sidebar-accent focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            :class="{ 'border-red-500': picaReviewForm.errors.pica_rejection_reason }"
                        ></textarea>
                        <p v-if="picaReviewForm.errors.pica_rejection_reason" class="text-xs text-red-600 dark:text-red-400 mt-1">
                            {{ picaReviewForm.errors.pica_rejection_reason }}
                        </p>
                        <p v-else class="text-xs text-gray-500 mt-1">Minimal 10 karakter</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Nama Reviewer <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="picaReviewForm.pica_reviewed_by"
                            type="text"
                            required
                            placeholder="Masukkan nama lengkap reviewer"
                            class="w-full rounded-lg border border-sidebar-border px-4 py-3 dark:bg-sidebar-accent focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>

                    <div class="flex gap-3 justify-end pt-6 border-t border-sidebar-border">
                        <button
                            type="button"
                            @click="showPicaReviewModal = false"
                            class="px-6 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-sidebar-accent transition-colors font-medium"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="picaReviewForm.processing"
                            :class="[
                                'px-6 py-2.5 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 shadow-lg hover:shadow-xl transition-all font-medium',
                                reviewAction === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'
                            ]"
                        >
                            <svg v-if="picaReviewForm.processing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <component :is="reviewAction === 'approve' ? ThumbsUp : ThumbsDown" class="w-5 h-5" />
                            {{ picaReviewForm.processing ? 'Processing...' : (reviewAction === 'approve' ? 'Approve PICA' : 'Reject PICA') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal TA Details -->
        <div v-if="showTaDetailsModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-xl max-w-2xl w-full p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Detail Temporary Action</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Informasi lengkap TA</p>
                    </div>
                    <button
                        @click="showTaDetailsModal = false"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded-lg transition-colors"
                    >
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4 border border-sidebar-border">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">LAPORAN</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ selectedReport?.report_number }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ selectedReport?.part.part_name }} ({{ selectedReport?.part.part_code }})
                        </p>
                    </div>

                    <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4 border border-sidebar-border">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">TEMPORARY ACTION</p>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="action in selectedReport?.temporary_actions"
                                :key="action"
                                :class="[
                                    'inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium',
                                    getTaTypeConfig(action).bgClass,
                                    getTaTypeConfig(action).textClass,
                                    getTaTypeConfig(action).borderClass,
                                    'border'
                                ]"
                            >
                                <component :is="getTaTypeConfig(action).icon" class="w-4 h-4" />
                                {{ getTaTypeConfig(action).label }}
                            </span>
                        </div>
                    </div>

                    <div v-if="selectedReport?.temporary_action_notes" class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4 border border-sidebar-border">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">KETERANGAN TAMBAHAN</p>
                        <p class="text-sm text-gray-900 dark:text-white whitespace-pre-wrap">{{ selectedReport.temporary_action_notes }}</p>
                    </div>

                    <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4 border border-sidebar-border">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">STATUS</p>
                        <div :class="[
                            'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold border',
                            getTaStatusConfig(selectedReport?.ta_status).bgClass,
                            getTaStatusConfig(selectedReport?.ta_status).textClass,
                            getTaStatusConfig(selectedReport?.ta_status).borderClass
                        ]">
                            <component :is="getTaStatusConfig(selectedReport?.ta_status).icon" class="w-4 h-4" />
                            {{ getTaStatusConfig(selectedReport?.ta_status).label }}
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4 border border-sidebar-border">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">TIMELINE</p>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Disubmit:</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ selectedReport?.ta_submitted_at ? formatDate(selectedReport.ta_submitted_at) : '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Oleh:</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ selectedReport?.ta_submitted_by || '-' }}</span>
                            </div>
                            <div v-if="selectedReport?.ta_reviewed_at" class="flex justify-between border-t border-sidebar-border pt-2 mt-2">
                                <span class="text-gray-600 dark:text-gray-400">Direview:</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ formatDate(selectedReport.ta_reviewed_at) }}
                                </span>
                            </div>
                            <div v-if="selectedReport?.ta_reviewed_by" class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Oleh:</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ selectedReport.ta_reviewed_by }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedReport?.ta_status === 'rejected' && selectedReport?.ta_rejection_reason" class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-800">
                        <p class="text-xs font-semibold text-red-700 dark:text-red-400 mb-2">ALASAN PENOLAKAN</p>
                        <p class="text-sm text-red-900 dark:text-red-300 whitespace-pre-wrap">{{ selectedReport.ta_rejection_reason }}</p>
                    </div>
                </div>

                <div class="flex justify-end mt-6 pt-4 border-t border-sidebar-border">
                    <button
                        @click="showTaDetailsModal = false"
                        class="px-6 py-2.5 bg-gray-100 dark:bg-sidebar-accent hover:bg-gray-200 dark:hover:bg-sidebar-accent/80 rounded-lg transition-colors font-medium"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal PICA Details -->
        <div v-if="showPicaDetailsModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-xl max-w-2xl w-full p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Detail PICA</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Informasi lengkap dokumen PICA</p>
                    </div>
                    <button
                        @click="showPicaDetailsModal = false"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded-lg transition-colors"
                    >
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4 border border-sidebar-border">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">LAPORAN</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ selectedReport?.report_number }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ selectedReport?.part.part_name }} ({{ selectedReport?.part.part_code }})
                        </p>
                    </div>

                    <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4 border border-sidebar-border">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">DOKUMEN PICA</p>
                        <a
                            v-if="selectedReport?.pica_document"
                            :href="`/storage/${selectedReport.pica_document}`"
                            target="_blank"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors font-medium"
                        >
                            <FileText class="w-5 h-5" />
                            Buka Dokumen PICA
                        </a>
                        <p v-else class="text-sm text-gray-500">Belum ada dokumen PICA</p>
                    </div>

                    <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4 border border-sidebar-border">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">STATUS</p>
                        <div :class="[
                            'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold border',
                            getPicaStatusConfig(selectedReport?.pica_status).bgClass,
                            getPicaStatusConfig(selectedReport?.pica_status).textClass,
                            getPicaStatusConfig(selectedReport?.pica_status).borderClass
                        ]">
                            <component :is="getPicaStatusConfig(selectedReport?.pica_status).icon" class="w-4 h-4" />
                            {{ getPicaStatusConfig(selectedReport?.pica_status).label }}
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4 border border-sidebar-border">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">TIMELINE</p>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Diupload:</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ selectedReport?.pica_uploaded_at ? formatDate(selectedReport.pica_uploaded_at) : '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Oleh:</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ selectedReport?.pica_uploaded_by || '-' }}</span>
                            </div>
                            <div v-if="selectedReport?.pica_reviewed_at" class="flex justify-between border-t border-sidebar-border pt-2 mt-2">
                                <span class="text-gray-600 dark:text-gray-400">Direview:</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ formatDate(selectedReport.pica_reviewed_at) }}
                                </span>
                            </div>
                            <div v-if="selectedReport?.pica_reviewed_by" class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Oleh:</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ selectedReport.pica_reviewed_by }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedReport?.pica_status === 'rejected' && selectedReport?.pica_rejection_reason" class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-800">
                        <p class="text-xs font-semibold text-red-700 dark:text-red-400 mb-2">ALASAN PENOLAKAN</p>
                        <p class="text-sm text-red-900 dark:text-red-300 whitespace-pre-wrap">{{ selectedReport.pica_rejection_reason }}</p>
                    </div>
                </div>

                <div class="flex justify-end mt-6 pt-4 border-t border-sidebar-border">
                    <button
                        @click="showPicaDetailsModal = false"
                        class="px-6 py-2.5 bg-gray-100 dark:bg-sidebar-accent hover:bg-gray-200 dark:hover:bg-sidebar-accent/80 rounded-lg transition-colors font-medium"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
        <!-- Modal Image Viewer -->
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

        <!-- Modal Notes Viewer -->
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
