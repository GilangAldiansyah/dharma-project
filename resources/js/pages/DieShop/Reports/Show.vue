<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Edit, FileText, Calendar, User, Package, Wrench, AlertTriangle, CheckCircle, X, Clock } from 'lucide-vue-next';

interface DieShopReport {
    id: number;
    report_no: string;
    activity_type: 'corrective' | 'preventive';
    pic_name: string;
    report_date: string;
    die_part: {
        id: number;
        part_no: string;
        part_name: string;
        location: string | null;
    };
    repair_process: string | null;
    problem_description: string | null;
    cause: string | null;
    repair_action: string | null;
    photos: string[] | null;
    status: 'pending' | 'in_progress' | 'completed';
    duration_value: number | null;
    duration_unit: string | null;
    duration_formatted: string | null;
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

interface Props {
    report: DieShopReport;
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps<Props>();

const selectedPhoto = ref<string | null>(null);

const getActivityBadge = (type: string) => {
    return type === 'corrective'
        ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
        : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        case 'in_progress':
            return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400';
    }
};

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'completed': return 'Selesai';
        case 'in_progress': return 'Dalam Proses';
        default: return 'Pending';
    }
};

const getDurationLabel = (report: DieShopReport) => {
    if (report.duration_formatted) {
        return report.duration_formatted;
    }
    return 'Belum selesai';
};

const getDurationColor = (report: DieShopReport) => {
    if (!report.duration_value || !report.duration_unit) return 'text-gray-500';

    // Color based on unit and value
    if (report.duration_unit === 'menit') {
        return 'text-green-600 dark:text-green-400';
    } else if (report.duration_unit === 'jam') {
        return report.duration_value <= 12
            ? 'text-green-600 dark:text-green-400'
            : 'text-yellow-600 dark:text-yellow-400';
    } else {
        // Days
        if (report.duration_value <= 1) {
            return 'text-green-600 dark:text-green-400';
        } else if (report.duration_value <= 3) {
            return 'text-yellow-600 dark:text-yellow-400';
        } else {
            return 'text-red-600 dark:text-red-400';
        }
    }
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
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

const openPhotoModal = (photo: string) => {
    selectedPhoto.value = photo;
};

const closePhotoModal = () => {
    selectedPhoto.value = null;
};
</script>

<template>
    <Head :title="`Laporan ${report.report_no}`" />
    <AppLayout :breadcrumbs="[
        { title: 'Die Shop System', href: '#' },
        { title: 'Laporan Perbaikan', href: '/die-shop-reports' },
        { title: report.report_no, href: `/die-shop-reports/${report.id}` }
    ]">
        <div class="p-4">
            <div class="max-w-5xl mx-auto space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold flex items-center gap-2">
                            <FileText class="w-6 h-6 text-blue-600" />
                            {{ report.report_no }}
                        </h1>
                        <p class="text-sm text-gray-500 mt-1">
                            Dibuat: {{ formatDateTime(report.created_at) }}
                            {{ report.creator ? `oleh ${report.creator.name}` : '' }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Link
                            :href="`/die-shop-reports/${report.id}/edit`"
                            class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                        >
                            <Edit class="w-4 h-4" />
                            Edit
                        </Link>
                    </div>
                </div>

                <!-- Status & Info Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                                <Calendar class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Tanggal Laporan</p>
                                <p class="font-semibold">{{ formatDate(report.report_date) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                                <User class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">PIC</p>
                                <p class="font-semibold">{{ report.pic_name }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                                <CheckCircle class="w-5 h-5 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Status</p>
                                <span :class="[
                                    'inline-flex px-2 py-1 rounded-full text-xs font-semibold',
                                    getStatusBadge(report.status)
                                ]">
                                    {{ getStatusLabel(report.status) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                                <Clock class="w-5 h-5 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div class="flex-1">
                                <p class="text-sm text-gray-500">Durasi Pengerjaan</p>
                                <p :class="['font-semibold', getDurationColor(report)]">
                                    {{ getDurationLabel(report) }}
                                </p>
                                <p v-if="report.completed_at" class="text-xs text-gray-400 mt-1">
                                    Selesai: {{ formatDateTime(report.completed_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Type & Die Part -->
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                    <h2 class="text-lg font-semibold mb-4">Informasi Aktivitas</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-sm text-gray-500 block mb-2">Jenis Aktivitas</label>
                            <span :class="[
                                'inline-flex items-center gap-2 px-3 py-2 rounded-md text-sm font-semibold',
                                getActivityBadge(report.activity_type)
                            ]">
                                <Wrench class="w-4 h-4" />
                                {{ report.activity_type === 'corrective' ? 'Corrective' : 'Preventive' }}
                            </span>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500 block mb-2">Die Part</label>
                            <div class="flex items-center gap-2 p-3 bg-gray-50 dark:bg-sidebar-accent rounded-md">
                                <Package class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                                <div>
                                    <p class="font-semibold">{{ report.die_part.part_no }}</p>
                                    <p class="text-sm text-gray-500">{{ report.die_part.part_name }}</p>
                                    <p v-if="report.die_part.location" class="text-xs text-gray-400">
                                        Lokasi: {{ report.die_part.location }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Repair Details -->
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                    <h2 class="text-lg font-semibold mb-4">Detail Perbaikan</h2>
                    <div class="space-y-4">
                        <div v-if="report.repair_process">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">
                                Proses Perbaikan
                            </label>
                            <div class="p-4 bg-gray-50 dark:bg-sidebar-accent rounded-md">
                                <p class="whitespace-pre-wrap">{{ report.repair_process }}</p>
                            </div>
                        </div>

                        <div v-if="report.problem_description">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2 flex items-center gap-2">
                                <AlertTriangle class="w-4 h-4 text-red-500" />
                                Deskripsi Permasalahan
                            </label>
                            <div class="p-4 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-900/30 rounded-md">
                                <p class="whitespace-pre-wrap">{{ report.problem_description }}</p>
                            </div>
                        </div>

                        <div v-if="report.cause">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">
                                Penyebab
                            </label>
                            <div class="p-4 bg-yellow-50 dark:bg-yellow-900/10 border border-yellow-200 dark:border-yellow-900/30 rounded-md">
                                <p class="whitespace-pre-wrap">{{ report.cause }}</p>
                            </div>
                        </div>

                        <div v-if="report.repair_action">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2 flex items-center gap-2">
                                <CheckCircle class="w-4 h-4 text-green-500" />
                                Tindakan Perbaikan
                            </label>
                            <div class="p-4 bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-900/30 rounded-md">
                                <p class="whitespace-pre-wrap">{{ report.repair_action }}</p>
                            </div>
                        </div>

                        <div v-if="!report.repair_process && !report.problem_description && !report.cause && !report.repair_action" class="text-center py-8 text-gray-500">
                            Detail perbaikan belum diisi
                        </div>
                    </div>
                </div>

                <!-- Photos -->
                <div v-if="report.photos && report.photos.length > 0" class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                    <h2 class="text-lg font-semibold mb-4">Foto Dokumentasi</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div
                            v-for="(photo, index) in report.photos"
                            :key="index"
                            class="relative group cursor-pointer"
                            @click="openPhotoModal(photo)"
                        >
                            <img
                                :src="`/storage/${photo}`"
                                :alt="`Foto ${index + 1}`"
                                class="w-full h-40 object-cover rounded-md border border-sidebar-border hover:opacity-80 transition"
                            />
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center rounded-md">
                                <span class="text-white text-sm">Klik untuk perbesar</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Spareparts -->
                <div v-if="report.spareparts.length > 0" class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                    <h2 class="text-lg font-semibold mb-4">Penggantian Sparepart</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">No</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Nama Sparepart</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Kode</th>
                                    <th class="px-4 py-3 text-right text-sm font-semibold">Jumlah</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(sparepart, index) in report.spareparts"
                                    :key="sparepart.id"
                                    class="border-b border-sidebar-border"
                                >
                                    <td class="px-4 py-3 text-sm">{{ index + 1 }}</td>
                                    <td class="px-4 py-3 text-sm font-medium">{{ sparepart.sparepart_name }}</td>
                                    <td class="px-4 py-3 text-sm font-mono">{{ sparepart.sparepart_code || '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-right">{{ sparepart.quantity }}</td>
                                    <td class="px-4 py-3 text-sm">{{ sparepart.notes || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Photo Modal -->
        <div v-if="selectedPhoto" class="fixed inset-0 bg-black/90 flex items-center justify-center z-50 p-4" @click="closePhotoModal">
            <button
                @click="closePhotoModal"
                class="absolute top-4 right-4 p-2 bg-white/10 hover:bg-white/20 rounded-full text-white"
            >
                <X class="w-6 h-6" />
            </button>
            <img
                :src="`/storage/${selectedPhoto}`"
                class="max-w-full max-h-full object-contain"
                @click.stop
            />
        </div>
    </AppLayout>
</template>
