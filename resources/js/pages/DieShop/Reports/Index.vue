<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Search, Eye, Edit, Trash2, FileText, Filter, Clock } from 'lucide-vue-next';

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
    };
    status: 'pending' | 'in_progress' | 'completed';
    duration_value: number | null;
    duration_unit: string | null;
    duration_formatted: string | null;
    spareparts: any[];
}

interface Props {
    reports: {
        data: DieShopReport[];
        current_page: number;
        last_page: number;
    };
    filters: {
        search?: string;
        activity_type?: string;
        status?: string;
        date_from?: string;
        date_to?: string;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const activityTypeFilter = ref(props.filters.activity_type || '');
const statusFilter = ref(props.filters.status || '');
const dateFromFilter = ref(props.filters.date_from || '');
const dateToFilter = ref(props.filters.date_to || '');
const showFilters = ref(false);

const search = () => {
    router.get('/die-shop-reports', {
        search: searchQuery.value,
        activity_type: activityTypeFilter.value,
        status: statusFilter.value,
        date_from: dateFromFilter.value,
        date_to: dateToFilter.value,
    }, { preserveState: true });
};

const clearFilters = () => {
    searchQuery.value = '';
    activityTypeFilter.value = '';
    statusFilter.value = '';
    dateFromFilter.value = '';
    dateToFilter.value = '';
    search();
};

const deleteReport = (id: number) => {
    if (confirm('Yakin hapus laporan ini?')) {
        router.delete(`/die-shop-reports/${id}`, { preserveScroll: true });
    }
};

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
    if (!report.duration_value || !report.duration_unit) {
        return '-';
    }

    return `${report.duration_value} ${report.duration_unit}`;
};

const getDurationColor = (report: DieShopReport) => {
    if (!report.duration_value || !report.duration_unit) return '';

    // Color based on unit and value
    if (report.duration_unit === 'menit') {
        // Less than 1 hour is very fast
        return 'text-green-600 dark:text-green-400';
    } else if (report.duration_unit === 'jam') {
        // Less than 24 hours is fast
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
        month: 'short',
        year: 'numeric'
    });
};
</script>

<template>
    <Head title="Laporan Die Shop" />
    <AppLayout :breadcrumbs="[
        { title: 'Die Shop System', href: '#' },
        { title: 'Laporan Perbaikan', href: '/die-shop-reports' }
    ]">
        <div class="p-4 space-y-4">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <FileText class="w-6 h-6 text-blue-600" />
                    Laporan Perbaikan Die
                </h1>
                <Link
                    href="/die-shop-reports/create"
                    class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                >
                    <Plus class="w-4 h-4" />
                    Buat Laporan
                </Link>
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

                <!-- Advanced Filters -->
                <div v-if="showFilters" class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Jenis Aktivitas</label>
                            <select
                                v-model="activityTypeFilter"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            >
                                <option value="">Semua</option>
                                <option value="corrective">Corrective</option>
                                <option value="preventive">Preventive</option>
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
                                <th class="px-4 py-3 text-left text-sm font-semibold">Jenis</th>
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
                                <td class="px-4 py-3">
                                    <span :class="[
                                        'inline-flex px-2 py-1 rounded-full text-xs font-semibold',
                                        getActivityBadge(report.activity_type)
                                    ]">
                                        {{ report.activity_type === 'corrective' ? 'Corrective' : 'Preventive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium">{{ report.die_part.part_no }}</div>
                                    <div class="text-xs text-gray-500">{{ report.die_part.part_name }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ report.pic_name }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="[
                                        'inline-flex px-2 py-1 rounded-full text-xs font-semibold',
                                        getStatusBadge(report.status)
                                    ]">
                                        {{ getStatusLabel(report.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        :class="[
                                            'text-sm font-medium',
                                            getDurationColor(report)
                                        ]"
                                    >
                                        {{ getDurationLabel(report) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <Link
                                            :href="`/die-shop-reports/${report.id}`"
                                            class="p-1 text-green-600 hover:bg-green-100 dark:hover:bg-green-900 rounded"
                                        >
                                            <Eye class="w-4 h-4" />
                                        </Link>
                                        <Link
                                            :href="`/die-shop-reports/${report.id}/edit`"
                                            class="p-1 text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-900 rounded"
                                        >
                                            <Edit class="w-4 h-4" />
                                        </Link>
                                        <button
                                            @click="deleteReport(report.id)"
                                            class="p-1 text-red-600 hover:bg-red-100 dark:hover:bg-red-900 rounded"
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
    </AppLayout>
</template>
