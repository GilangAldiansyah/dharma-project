<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import {
    BarChart3, Package, Building2, Calendar, RefreshCw, Table,
    TrendingUp, AlertCircle, CheckCircle, Clock, Download,
    Filter, X, ChevronRight, Eye, FileText, PieChart, Upload, Trash2 }
from 'lucide-vue-next';
import * as XLSX from 'xlsx';

interface Props {
    ngByPart: Array<{
        part_name: string;
        part_code: string;
        total: number;
    }>;
    ngBySupplier: Array<{
        supplier_name: string;
        total: number;
    }>;
    ngByType: Array<{
        type: string;
        label: string;
        total: number;
    }>;
    summary: {
        total_ng: number;
        open_ng: number;
        pica_submitted: number;
        closed_ng: number;
        total_parts: number;
        total_suppliers: number;
    };
    dailyTrend: Array<{
        date: string;
        full_date: string;
        total: number;
    }>;
    statusDistribution: Array<{
        status: string;
        count: number;
        color: string;
    }>;
    criticalParts: Array<{
        part_name: string;
        part_code: string;
        total: number;
    }>;
    criticalSuppliers: Array<{
        supplier_name: string;
        total: number;
    }>;
    filters: {
        start_date: string;
        end_date: string;
    };
    template_exists: boolean;
    template_url: string | null;

}

const props = defineProps<Props>();

const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);
const viewMode = ref<'bar' | 'table'>('bar');
const showFilterModal = ref(false);
const isLoading = ref(false);
const isExporting = ref(false);

const hoveredTrendBar = ref<number | null>(null);
const hoveredPartBar = ref<number | null>(null);
const hoveredSupplierBar = ref<number | null>(null);
const hoveredPieSlice = ref<number | null>(null);
const tooltipPosition = ref({ top: '0px', left: '0px' });

const updateTooltipPosition = (event: MouseEvent) => {
    const x = event.clientX;
    const y = event.clientY;
    tooltipPosition.value = {
        top: `${y - 120}px`,
        left: `${x}px`,
    };
};

const clearHover = () => {
    hoveredTrendBar.value = null;
    hoveredPartBar.value = null;
    hoveredSupplierBar.value = null;
    hoveredPieSlice.value = null;
};

const ngTypeChartData = computed(() => {
    if (!props.ngByType || props.ngByType.length === 0) return [];

    const total = props.ngByType.reduce((sum, item) => sum + item.total, 0);
    let currentAngle = 0;

    return props.ngByType.map(item => {
        const percentage = (item.total / total) * 100;
        const angle = (item.total / total) * 360;
        const startAngle = currentAngle;
        const endAngle = currentAngle + angle;
        currentAngle = endAngle;

        return {
            type: item.type,
            label: item.label,
            total: item.total,
            percentage,
            startAngle,
            endAngle,
        };
    });
});

const createPieSlicePath = (startAngle: number, endAngle: number): string => {
    const cx = 120;
    const cy = 120;
    const radius = 90;

    const startRad = (startAngle - 90) * (Math.PI / 180);
    const endRad = (endAngle - 90) * (Math.PI / 180);

    const x1 = cx + radius * Math.cos(startRad);
    const y1 = cy + radius * Math.sin(startRad);
    const x2 = cx + radius * Math.cos(endRad);
    const y2 = cy + radius * Math.sin(endRad);

    const largeArcFlag = endAngle - startAngle > 180 ? 1 : 0;

    return `M ${cx} ${cy} L ${x1} ${y1} A ${radius} ${radius} 0 ${largeArcFlag} 1 ${x2} ${y2} Z`;
};

const getNgTypeColor = (type: string): string => {
    const colors: Record<string, string> = {
        'fungsi': '#ef4444',
        'dimensi': '#f59e0b',
        'tampilan': '#3b82f6',
    };
    return colors[type] || '#6b7280';
};

const setQuickFilter = (type: 'today' | 'week' | 'month' | 'year') => {
    const now = new Date();

    switch(type) {
        case 'today':
            startDate.value = now.toISOString().split('T')[0];
            endDate.value = now.toISOString().split('T')[0];
            break;
        case 'week':
            const weekStart = new Date(now);
            weekStart.setDate(now.getDate() - now.getDay());
            startDate.value = weekStart.toISOString().split('T')[0];
            endDate.value = now.toISOString().split('T')[0];
            break;
        case 'month':
            startDate.value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
            endDate.value = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
            break;
        case 'year':
            startDate.value = new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0];
            endDate.value = new Date(now.getFullYear(), 11, 31).toISOString().split('T')[0];
            break;
    }

    applyFilter();
};

const applyFilter = () => {
    isLoading.value = true;
    router.get('/ng-reports/dashboard', {
        start_date: startDate.value,
        end_date: endDate.value,
    }, {
        preserveState: false,
        preserveScroll: false,
        onFinish: () => {
            isLoading.value = false;
            showFilterModal.value = false;
        }
    });
};

const resetFilter = () => {
    const now = new Date();
    startDate.value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
    endDate.value = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
    applyFilter();
};

const refreshData = () => {
    isLoading.value = true;
    router.reload({
        only: ['ngByPart', 'ngBySupplier', 'ngByType', 'summary', 'dailyTrend', 'statusDistribution', 'criticalParts', 'criticalSuppliers'],
        onFinish: () => {
            isLoading.value = false;
        }
    });
};

const uploadForm = useForm({
    template: null as File | null,
});

const fileInputRef = ref<HTMLInputElement | null>(null);
const showTemplateModal = ref(false);
const showDeleteConfirm = ref(false);

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        uploadForm.template = file;
    }
};

const uploadTemplate = () => {
    if (!uploadForm.template) {
        alert('Pilih file template terlebih dahulu');
        return;
    }

    uploadForm.post('/ng-reports/upload-template', {
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset();
            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }
            showTemplateModal.value = false;
        },
    });
};

const deleteTemplate = () => {
    router.delete('/ng-reports/delete-template', {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirm.value = false;
        },
    });
};

const downloadTemplateFile = () => {
    if (props.template_url) {
        window.open(props.template_url, '_blank');
    }
};

const exportData = async () => {
    try {
        isExporting.value = true;

        // Fetch data dari backend
        const response = await fetch(`/ng-reports/dashboard/export?start_date=${startDate.value}&end_date=${endDate.value}`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.data || !Array.isArray(result.data)) {
            throw new Error('Data tidak valid dari server');
        }

        // Create workbook
        const wb = XLSX.utils.book_new();

        // Create worksheet from data
        const ws = XLSX.utils.json_to_sheet(result.data);

        // Add worksheet to workbook
        XLSX.utils.book_append_sheet(wb, ws, 'NG Reports');

        // Generate and download file
        XLSX.writeFile(wb, result.filename);

    } catch (error) {
        console.error('Export error:', error);
        alert('Gagal mengekspor data. Silakan coba lagi.');
    } finally {
        isExporting.value = false;
    }
};

const maxPartValue = computed(() =>
    Math.max(...props.ngByPart.map(item => item.total), 1)
);

const maxSupplierValue = computed(() =>
    Math.max(...props.ngBySupplier.map(item => item.total), 1)
);

const maxTrendValue = computed(() =>
    Math.max(...props.dailyTrend.map(item => item.total), 1)
);

const scrollToTable = () => {
    viewMode.value = 'table';
    setTimeout(() => {
        const tableElement = document.getElementById('table-section');
        if (tableElement) {
            tableElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, 100);
};

const scrollToTableSection = () => {
    viewMode.value = 'table';
    window.setTimeout(() => {
        const tableElement = document.getElementById('table-section');
        if (tableElement) {
            tableElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, 100);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
};

const getStatusColor = (color: string) => {
    const colors: Record<string, string> = {
        red: 'bg-red-500',
        yellow: 'bg-yellow-500',
        green: 'bg-green-500',
    };
    return colors[color] || 'bg-gray-500';
};

const getStatusBgColor = (color: string) => {
    const colors: Record<string, string> = {
        red: 'bg-red-100 dark:bg-red-900/30',
        yellow: 'bg-yellow-100 dark:bg-yellow-900/30',
        green: 'bg-green-100 dark:bg-green-900/30',
    };
    return colors[color] || 'bg-gray-100';
};

const getStatusTextColor = (color: string) => {
    const colors: Record<string, string> = {
        red: 'text-red-700 dark:text-red-400',
        yellow: 'text-yellow-700 dark:text-yellow-400',
        green: 'text-green-700 dark:text-green-400',
    };
    return colors[color] || 'text-gray-700';
};

const dateRangeText = computed(() => {
    if (startDate.value === endDate.value) {
        return formatDate(startDate.value);
    }
    return `${formatDate(startDate.value)} - ${formatDate(endDate.value)}`;
});

onMounted(() => {
    document.addEventListener('mousemove', updateTooltipPosition);
});

onUnmounted(() => {
    document.removeEventListener('mousemove', updateTooltipPosition);
});
</script>
<template>
    <Head title="Dashboard NG Reports" />
    <AppLayout :breadcrumbs="[
        { title: 'Dashboard NG', href: '/ng-reports/dashboard' }
    ]">
        <div class="p-4 sm:p-6 space-y-6">
            <!-- Header -->
            <div class="bg-gradient-to-r from-red-600 to-orange-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold flex items-center gap-3">
                            <div class="bg-white/20 backdrop-blur-sm p-3 rounded-lg">
                                <BarChart3 class="w-7 h-7" />
                            </div>
                            Dashboard NG Reports
                        </h1>
                        <p class="text-red-100 mt-2 text-sm lg:text-base">
                            Monitoring dan analisis laporan produk NG
                        </p>
                        <p class="text-white/90 mt-1 text-xs lg:text-sm font-medium">
                            📅 {{ dateRangeText }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            @click="showFilterModal = true"
                            class="px-4 py-2.5 bg-white/20 backdrop-blur-sm hover:bg-white/30 rounded-lg transition-all flex items-center gap-2 text-sm font-medium"
                        >
                            <Filter class="w-4 h-4" />
                            Filter Periode
                        </button>
                        <button
                            @click="refreshData"
                            :disabled="isLoading"
                            class="px-4 py-2.5 bg-white/20 backdrop-blur-sm hover:bg-white/30 rounded-lg transition-all flex items-center gap-2 text-sm font-medium disabled:opacity-50"
                        >
                            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
                            Refresh
                        </button>
                        <button
                            @click="exportData"
                            :disabled="isExporting"
                            class="px-4 py-2.5 bg-white text-red-600 hover:bg-red-50 rounded-lg transition-all flex items-center gap-2 text-sm font-semibold shadow-lg disabled:opacity-50"
                        >
                            <Download class="w-4 h-4" :class="{ 'animate-bounce': isExporting }" />
                            {{ isExporting ? 'Exporting...' : 'Export Excel' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-sidebar rounded-xl shadow-md border border-sidebar-border p-6 hover:shadow-lg transition-shadow">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium mb-1">Total Laporan NG</p>
                            <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                                {{ summary.total_ng }}
                            </h3>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-full font-semibold flex items-center gap-1">
                                    <AlertCircle class="w-3 h-3" />
                                    {{ summary.open_ng }} Open
                                </span>
                                <span class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 rounded-full font-semibold flex items-center gap-1">
                                    <Clock class="w-3 h-3" />
                                    {{ summary.pica_submitted }} PICA
                                </span>
                            </div>
                        </div>
                        <div class="bg-red-100 dark:bg-red-900/30 p-3 rounded-xl">
                            <AlertCircle class="w-8 h-8 text-red-600 dark:text-red-400" />
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-sidebar rounded-xl shadow-md border border-sidebar-border p-6 hover:shadow-lg transition-shadow">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium mb-1">Part Terpengaruh</p>
                            <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                                {{ summary.total_parts }}
                            </h3>
                            <div class="flex items-center gap-1 text-xs">
                                <TrendingUp class="w-3 h-3 text-orange-600" />
                                <span class="text-gray-600 dark:text-gray-400">
                                    dari berbagai supplier
                                </span>
                            </div>
                        </div>
                        <div class="bg-orange-100 dark:bg-orange-900/30 p-3 rounded-xl">
                            <Package class="w-8 h-8 text-orange-600 dark:text-orange-400" />
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-sidebar rounded-xl shadow-md border border-sidebar-border p-6 hover:shadow-lg transition-shadow">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium mb-1">Supplier Terlibat</p>
                            <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                                {{ summary.total_suppliers }}
                            </h3>
                            <div class="flex items-center gap-1 text-xs">
                                <Building2 class="w-3 h-3 text-blue-600" />
                                <span class="text-gray-600 dark:text-gray-400">
                                    supplier aktif
                                </span>
                            </div>
                        </div>
                        <div class="bg-blue-100 dark:bg-blue-900/30 p-3 rounded-xl">
                            <Building2 class="w-8 h-8 text-blue-600 dark:text-blue-400" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Distribution & NG Type Pie Chart -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Status Distribution - 2 columns -->
                <div class="lg:col-span-2 bg-white dark:bg-sidebar rounded-xl shadow-md border border-sidebar-border p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <CheckCircle class="w-5 h-5 text-green-600" />
                        Distribusi Status
                    </h3>
                    <div class="grid grid-cols-1 gap-3">
                        <div
                            v-for="status in statusDistribution"
                            :key="status.status"
                            class="relative overflow-hidden rounded-lg border-2"
                            :class="[
                                getStatusBgColor(status.color),
                                `border-${status.color}-300 dark:border-${status.color}-700`
                            ]"
                        >
                            <div class="p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-3 h-3 rounded-full flex-shrink-0" :class="getStatusColor(status.color)"></div>
                                        <div>
                                            <span class="text-sm font-semibold block" :class="getStatusTextColor(status.color)">
                                                {{ status.status }}
                                            </span>
                                            <span class="text-xs" :class="getStatusTextColor(status.color)">
                                                {{ summary.total_ng > 0 ? ((status.count / summary.total_ng) * 100).toFixed(1) : 0 }}% dari total
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-3xl font-bold" :class="getStatusTextColor(status.color)">
                                            {{ status.count }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="absolute bottom-0 left-0 h-1 transition-all duration-500"
                                :class="getStatusColor(status.color)"
                                :style="{ width: summary.total_ng > 0 ? `${(status.count / summary.total_ng) * 100}%` : '0%' }"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- NG Type Pie Chart - 1 column -->
                <div class="bg-white dark:bg-sidebar rounded-xl shadow-md border border-sidebar-border p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                        <PieChart class="w-5 h-5 text-purple-600" />
                        Distribusi Jenis NG
                    </h3>
                    <div v-if="ngTypeChartData.length > 0" class="flex flex-col items-center gap-2">
                        <svg width="220" height="220" viewBox="0 0 240 240" class="flex-shrink-0">
                            <g v-for="(data, index) in ngTypeChartData" :key="index">
                                <path
                                    :d="createPieSlicePath(data.startAngle, data.endAngle)"
                                    :fill="getNgTypeColor(data.type)"
                                    class="transition-all duration-300 hover:opacity-80 cursor-pointer"
                                    :class="{ 'opacity-90': hoveredPieSlice === index }"
                                    @mouseenter="hoveredPieSlice = index"
                                    @mouseleave="clearHover"
                                />
                            </g>
                            <circle cx="120" cy="120" r="55" fill="white" class="dark:fill-gray-800" />
                            <text x="120" y="115" text-anchor="middle" class="fill-gray-700 dark:fill-gray-300 text-sm font-semibold">Total</text>
                            <text x="120" y="135" text-anchor="middle" class="fill-gray-900 dark:fill-gray-100 text-xl font-bold">
                                {{ summary.total_ng }}
                            </text>
                        </svg>
                        <div class="w-full">
                            <div v-for="(data, index) in ngTypeChartData" :key="data.type"
                                 class="flex items-center justify-between text-sm rounded-lg hover:bg-gray-50 dark:hover:bg-sidebar-accent transition-colors cursor-pointer"
                                 @mouseenter="hoveredPieSlice = index"
                                 @mouseleave="clearHover">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: getNgTypeColor(data.type) }"></div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ data.label }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-gray-900 dark:text-gray-100">{{ data.total }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 text-gray-500">
                        <PieChart class="w-12 h-12 mx-auto mb-2 opacity-50" />
                        <p class="text-sm">Tidak ada data jenis NG</p>
                    </div>

                    <!-- Tooltip for Pie Chart -->
                    <Teleport to="body">
                        <div
                            v-if="hoveredPieSlice !== null"
                            class="fixed pointer-events-none z-[9999] -translate-x-1/2"
                            :style="tooltipPosition"
                        >
                            <div class="bg-gray-900 text-white text-xs rounded-lg shadow-2xl p-3 min-w-[140px]">
                                <div class="font-bold text-center mb-1">{{ ngTypeChartData[hoveredPieSlice].label }}</div>
                                <div class="text-center pt-1 border-t border-gray-700">
                                    <span class="font-bold text-base" :style="{ color: getNgTypeColor(ngTypeChartData[hoveredPieSlice].type) }">
                                        {{ ngTypeChartData[hoveredPieSlice].total }} NG
                                    </span>
                                    <div class="text-gray-400 text-[10px] mt-1">
                                        {{ ngTypeChartData[hoveredPieSlice].percentage.toFixed(1) }}% dari total
                                    </div>
                                </div>
                                <div class="absolute top-full left-1/2 -translate-x-1/2">
                                    <div class="border-4 border-transparent border-t-gray-900"></div>
                                </div>
                            </div>
                        </div>
                    </Teleport>
                </div>
            </div>
            <!-- Top Parts & Suppliers -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-sidebar rounded-xl shadow-md border border-sidebar-border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <Package class="w-5 h-5 text-red-600" />
                            Top 5 Part Bermasalah
                        </h3>
                        <button
                            @click="scrollToTableSection"
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1"
                        >
                            Lihat Semua
                            <ChevronRight class="w-3 h-3" />
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="(part, index) in criticalParts"
                            :key="index"
                            class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-sidebar-accent hover:bg-gray-100 dark:hover:bg-sidebar-accent/80 transition-colors"
                        >
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                                <span class="text-sm font-bold text-red-600 dark:text-red-400">
                                    {{ index + 1 }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                    {{ part.part_name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ part.part_code }}
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="px-3 py-1 bg-red-600 text-white text-sm font-bold rounded-full">
                                    {{ part.total }}
                                </span>
                            </div>
                        </div>
                        <div v-if="criticalParts.length === 0" class="text-center py-8 text-gray-500">
                            <Package class="w-12 h-12 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">Tidak ada data</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-sidebar rounded-xl shadow-md border border-sidebar-border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <Building2 class="w-5 h-5 text-orange-600" />
                            Top 5 Supplier Bermasalah
                        </h3>
                        <button
                            @click="scrollToTableSection"
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1"
                        >
                            Lihat Semua
                            <ChevronRight class="w-3 h-3" />
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="(supplier, index) in criticalSuppliers"
                            :key="index"
                            class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-sidebar-accent hover:bg-gray-100 dark:hover:bg-sidebar-accent/80 transition-colors"
                        >
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                                <span class="text-sm font-bold text-orange-600 dark:text-orange-400">
                                    {{ index + 1 }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                    {{ supplier.supplier_name }}
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="px-3 py-1 bg-orange-600 text-white text-sm font-bold rounded-full">
                                    {{ supplier.total }}
                                </span>
                            </div>
                        </div>
                        <div v-if="criticalSuppliers.length === 0" class="text-center py-8 text-gray-500">
                            <Building2 class="w-12 h-12 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">Tidak ada data</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="bg-white dark:bg-sidebar rounded-xl shadow-md border border-sidebar-border overflow-hidden">
                <div class="flex border-b border-sidebar-border">
                    <button
                        @click="viewMode = 'bar'"
                        :class="[
                            'flex-1 px-6 py-4 font-semibold text-sm transition-all flex items-center justify-center gap-2',
                            viewMode === 'bar'
                                ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg'
                                : 'bg-gray-50 dark:bg-sidebar-accent text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-sidebar-accent/80'
                        ]"
                    >
                        <BarChart3 class="w-5 h-5" />
                        Grafik Visual
                    </button>
                    <button
                        @click="viewMode = 'table'"
                        :class="[
                            'flex-1 px-6 py-4 font-semibold text-sm transition-all flex items-center justify-center gap-2',
                            viewMode === 'table'
                                ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg'
                                : 'bg-gray-50 dark:bg-sidebar-accent text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-sidebar-accent/80'
                        ]"
                    >
                        <Table class="w-5 h-5" />
                        Tabel Detail
                    </button>
                </div>

                <!-- Bar Charts View -->
                <div v-if="viewMode === 'bar'" class="p-6 space-y-8">
                    <!-- Daily Trend -->
                    <div v-if="dailyTrend.length > 0" class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/10 dark:to-indigo-900/10 rounded-xl p-6 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Calendar class="w-5 h-5 text-blue-600" />
                                Tren Harian NG Reports
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400 bg-white dark:bg-sidebar px-3 py-1 rounded-full">
                                {{ dailyTrend.length }} hari
                            </span>
                        </div>

                        <div class="bg-white dark:bg-sidebar rounded-xl p-6 shadow-sm">
                            <div class="space-y-2">
                                <div v-for="(item, index) in dailyTrend" :key="index"
                                     class="flex items-center gap-3"
                                     @mouseenter="hoveredTrendBar = index"
                                     @mouseleave="clearHover">
                                    <div class="w-24 text-xs text-gray-600 dark:text-gray-400 font-medium truncate">
                                        {{ item.date }}
                                    </div>
                                    <div class="flex-1 flex items-center gap-2">
                                        <div class="flex-1 h-8 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden relative">
                                            <div
                                                class="h-full bg-gradient-to-r from-blue-500 to-blue-600 transition-all duration-300 rounded-lg flex items-center justify-end pr-3"
                                                :class="{ 'from-blue-600 to-blue-700 shadow-lg': hoveredTrendBar === index }"
                                                :style="{ width: `${(item.total / maxTrendValue) * 100}%` }">
                                                <span v-if="item.total > 0" class="text-white text-xs font-bold">{{ item.total }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Teleport to="body">
                            <div
                                v-if="hoveredTrendBar !== null"
                                class="fixed pointer-events-none z-[9999] -translate-x-1/2"
                                :style="tooltipPosition"
                            >
                                <div class="bg-gray-900 text-white text-xs rounded-lg shadow-2xl p-3 min-w-[160px]">
                                    <div class="font-bold text-center mb-1">📅 {{ dailyTrend[hoveredTrendBar].full_date }}</div>
                                    <div class="text-center pt-1 border-t border-gray-700">
                                        <span class="text-blue-400 font-bold text-base">{{ dailyTrend[hoveredTrendBar].total }} NG</span>
                                    </div>
                                    <div class="absolute top-full left-1/2 -translate-x-1/2">
                                        <div class="border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            </div>
                        </Teleport>
                    </div>

                    <!-- NG by Part Chart -->
                    <div class="bg-gradient-to-br from-red-50 to-pink-50 dark:from-red-900/10 dark:to-pink-900/10 rounded-xl p-6 border border-red-200 dark:border-red-800">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Package class="w-5 h-5 text-red-600" />
                                NG Berdasarkan Part
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400 bg-white dark:bg-sidebar px-3 py-1 rounded-full">
                                {{ ngByPart.length }} part
                            </span>
                        </div>

                        <div v-if="ngByPart.length > 0" class="bg-white dark:bg-sidebar rounded-xl p-6 shadow-sm">
                            <div class="space-y-2">
                                <div v-for="(item, index) in ngByPart" :key="index"
                                     class="flex items-center gap-3 cursor-pointer"
                                     @click="scrollToTable"
                                     @mouseenter="hoveredPartBar = index"
                                     @mouseleave="clearHover">
                                    <div class="w-32 text-xs text-gray-600 dark:text-gray-400 font-medium truncate" :title="item.part_code">
                                        {{ item.part_code }}
                                    </div>
                                    <div class="flex-1 flex items-center gap-2">
                                        <div class="flex-1 h-10 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden relative">
                                            <div
                                                class="h-full bg-gradient-to-r from-red-500 to-red-600 transition-all duration-300 rounded-lg flex items-center justify-end pr-3"
                                                :class="{ 'from-red-600 to-red-700 shadow-lg': hoveredPartBar === index }"
                                                :style="{ width: `${(item.total / maxPartValue) * 100}%` }">
                                                <span v-if="item.total > 0" class="text-white text-sm font-bold">{{ item.total }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-20 text-gray-500">
                            <Package class="w-16 h-16 mx-auto mb-3 opacity-30" />
                            <p class="font-medium">Tidak ada data untuk periode ini</p>
                        </div>

                        <Teleport to="body">
                            <div
                                v-if="hoveredPartBar !== null"
                                class="fixed pointer-events-none z-[9999] -translate-x-1/2"
                                :style="tooltipPosition"
                            >
                                <div class="bg-gray-900 text-white text-xs rounded-lg shadow-2xl p-3 min-w-[200px]">
                                    <div class="font-bold mb-1">{{ ngByPart[hoveredPartBar].part_name }}</div>
                                    <div class="text-gray-300 text-[10px] mb-2">{{ ngByPart[hoveredPartBar].part_code }}</div>
                                    <div class="pt-2 border-t border-gray-700 text-center">
                                        <span class="text-red-400 font-bold text-base">{{ ngByPart[hoveredPartBar].total }} NG</span>
                                    </div>
                                    <div class="mt-2 text-blue-400 text-center text-[10px] flex items-center justify-center gap-1">
                                        <Eye class="w-3 h-3" />
                                        Klik untuk detail
                                    </div>
                                    <div class="absolute top-full left-1/2 -translate-x-1/2">
                                        <div class="border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            </div>
                        </Teleport>
                    </div>
                    <!-- NG by Supplier Chart -->
                    <div class="bg-gradient-to-br from-orange-50 to-amber-50 dark:from-orange-900/10 dark:to-amber-900/10 rounded-xl p-6 border border-orange-200 dark:border-orange-800">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Building2 class="w-5 h-5 text-orange-600" />
                                NG Berdasarkan Supplier
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400 bg-white dark:bg-sidebar px-3 py-1 rounded-full">
                                {{ ngBySupplier.length }} supplier
                            </span>
                        </div>

                        <div v-if="ngBySupplier.length > 0" class="bg-white dark:bg-sidebar rounded-xl p-6 shadow-sm">
                            <div class="space-y-2">
                                <div v-for="(item, index) in ngBySupplier" :key="index"
                                     class="flex items-center gap-3 cursor-pointer"
                                     @click="scrollToTable"
                                     @mouseenter="hoveredSupplierBar = index"
                                     @mouseleave="clearHover">
                                    <div class="w-40 text-xs text-gray-600 dark:text-gray-400 font-medium truncate" :title="item.supplier_name">
                                        {{ item.supplier_name }}
                                    </div>
                                    <div class="flex-1 flex items-center gap-2">
                                        <div class="flex-1 h-10 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden relative">
                                            <div
                                                class="h-full bg-gradient-to-r from-orange-500 to-orange-600 transition-all duration-300 rounded-lg flex items-center justify-end pr-3"
                                                :class="{ 'from-orange-600 to-orange-700 shadow-lg': hoveredSupplierBar === index }"
                                                :style="{ width: `${(item.total / maxSupplierValue) * 100}%` }">
                                                <span v-if="item.total > 0" class="text-white text-sm font-bold">{{ item.total }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-20 text-gray-500">
                            <Building2 class="w-16 h-16 mx-auto mb-3 opacity-30" />
                            <p class="font-medium">Tidak ada data untuk periode ini</p>
                        </div>

                        <Teleport to="body">
                            <div
                                v-if="hoveredSupplierBar !== null"
                                class="fixed pointer-events-none z-[9999] -translate-x-1/2"
                                :style="tooltipPosition"
                            >
                                <div class="bg-gray-900 text-white text-xs rounded-lg shadow-2xl p-3 min-w-[180px]">
                                    <div class="font-bold text-center mb-2">{{ ngBySupplier[hoveredSupplierBar].supplier_name }}</div>
                                    <div class="pt-2 border-t border-gray-700 text-center">
                                        <span class="text-orange-400 font-bold text-base">{{ ngBySupplier[hoveredSupplierBar].total }} NG</span>
                                    </div>
                                    <div class="mt-2 text-blue-400 text-center text-[10px] flex items-center justify-center gap-1">
                                        <Eye class="w-3 h-3" />
                                        Klik untuk detail
                                    </div>
                                    <div class="absolute top-full left-1/2 -translate-x-1/2">
                                        <div class="border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            </div>
                        </Teleport>
                    </div>
                </div>
                <!-- Table View -->
                <div v-if="viewMode === 'table'" id="table-section" class="p-6 space-y-6">
                    <div v-if="dailyTrend.length > 0">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Calendar class="w-5 h-5 text-blue-600" />
                                Tren Harian
                            </h3>
                            <span class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 px-3 py-1 rounded-full font-semibold">
                                {{ dailyTrend.length }} hari
                            </span>
                        </div>
                        <div class="overflow-x-auto border border-sidebar-border rounded-lg shadow-sm">
                            <table class="w-full text-sm">
                                <thead class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border-b-2 border-blue-200 dark:border-blue-800">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            No
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            Tanggal
                                        </th>
                                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            Jumlah NG
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-sidebar-border bg-white dark:bg-sidebar">
                                    <tr v-for="(item, index) in dailyTrend" :key="index" class="hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-colors">
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400 font-medium">
                                            {{ index + 1 }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <Calendar class="w-4 h-4 text-blue-500" />
                                                <span class="font-medium text-gray-900 dark:text-white">{{ item.full_date }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="inline-flex px-3 py-1.5 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 rounded-lg font-bold text-base">
                                                {{ item.total }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Package class="w-5 h-5 text-red-600" />
                                NG Berdasarkan Part
                            </h3>
                            <span class="text-xs bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 px-3 py-1 rounded-full font-semibold">
                                {{ ngByPart.length }} part
                            </span>
                        </div>
                        <div class="overflow-x-auto border border-sidebar-border rounded-lg shadow-sm">
                            <table class="w-full text-sm">
                                <thead class="bg-gradient-to-r from-red-50 to-pink-50 dark:from-red-900/20 dark:to-pink-900/20 border-b-2 border-red-200 dark:border-red-800">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            No
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            Kode Part
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            Nama Part
                                        </th>
                                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            Jumlah NG
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-sidebar-border bg-white dark:bg-sidebar">
                                    <tr v-for="(item, index) in ngByPart" :key="index" class="hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors">
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400 font-medium">
                                            {{ index + 1 }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded font-semibold">
                                                {{ item.part_code }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                {{ item.part_name }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="inline-flex px-3 py-1.5 bg-red-600 text-white rounded-lg font-bold text-base shadow-sm">
                                                {{ item.total }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="ngByPart.length === 0">
                                        <td colspan="4" class="px-6 py-12 text-center">
                                            <Package class="w-12 h-12 mx-auto mb-2 text-gray-400 opacity-50" />
                                            <p class="text-gray-500 font-medium">Tidak ada data untuk periode ini</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Building2 class="w-5 h-5 text-orange-600" />
                                NG Berdasarkan Supplier
                            </h3>
                            <span class="text-xs bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 px-3 py-1 rounded-full font-semibold">
                                {{ ngBySupplier.length }} supplier
                            </span>
                        </div>
                        <div class="overflow-x-auto border border-sidebar-border rounded-lg shadow-sm">
                            <table class="w-full text-sm">
                                <thead class="bg-gradient-to-r from-orange-50 to-amber-50 dark:from-orange-900/20 dark:to-amber-900/20 border-b-2 border-orange-200 dark:border-orange-800">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            No
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            Nama Supplier
                                        </th>
                                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            Jumlah NG
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-sidebar-border bg-white dark:bg-sidebar">
                                    <tr v-for="(item, index) in ngBySupplier" :key="index" class="hover:bg-orange-50 dark:hover:bg-orange-900/10 transition-colors">
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400 font-medium">
                                            {{ index + 1 }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <Building2 class="w-4 h-4 text-orange-500" />
                                                <span class="font-semibold text-gray-900 dark:text-white">
                                                    {{ item.supplier_name }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="inline-flex px-3 py-1.5 bg-orange-600 text-white rounded-lg font-bold text-base shadow-sm">
                                                {{ item.total }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="ngBySupplier.length === 0">
                                        <td colspan="3" class="px-6 py-12 text-center">
                                            <Building2 class="w-12 h-12 mx-auto mb-2 text-gray-400 opacity-50" />
                                            <p class="text-gray-500 font-medium">Tidak ada data untuk periode ini</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

             <div class="bg-white dark:bg-sidebar rounded-xl shadow-md border border-sidebar-border p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <FileText class="w-5 h-5 text-purple-600" />
                            Template PICA / Temporary Action
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Kelola template yang digunakan oleh user
                        </p>
                    </div>
                    <button
                        @click="showTemplateModal = true"
                        class="px-4 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-all flex items-center gap-2 text-sm font-medium shadow-lg hover:shadow-xl"
                    >
                        <Upload class="w-4 h-4" />
                        Kelola Template
                    </button>
                </div>

                <!-- Current Template Status -->
                <div v-if="template_exists" class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <CheckCircle class="w-5 h-5 text-green-600 dark:text-green-400" />
                            <div>
                                <p class="text-sm font-semibold text-green-900 dark:text-green-200">Template Aktif</p>
                                <p class="text-xs text-green-700 dark:text-green-400 mt-1">temporary_action_template.pdf</p>
                            </div>
                        </div>
                        <button
                            @click="downloadTemplateFile"
                            class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 text-sm font-medium"
                        >
                            <Download class="w-4 h-4" />
                            Preview
                        </button>
                    </div>
                </div>

                <div v-else class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                    <div class="flex items-start gap-3">
                        <AlertCircle class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5" />
                        <div>
                            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Template Belum Diupload</p>
                            <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                                User tidak dapat mendownload template. Klik "Kelola Template" untuk upload.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 text-sm">
                <div class="text-gray-600 dark:text-gray-400">
                    <span class="font-medium">Terakhir diperbarui:</span>
                    <span class="ml-2">{{ new Date().toLocaleString('id-ID', {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    }) }}</span>
                </div>
                <a
                    href="/ng-reports"
                    class="px-6 py-3 bg-gradient-to-r from-red-600 to-orange-600 text-white rounded-lg hover:from-red-700 hover:to-orange-700 flex items-center gap-2 font-semibold shadow-lg hover:shadow-xl transition-all"
                >
                    <FileText class="w-5 h-5" />
                    Lihat Semua Laporan
                    <ChevronRight class="w-4 h-4" />
                </a>
            </div>
         <!-- Template Management Modal -->
    <div v-if="showTemplateModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-sidebar rounded-xl max-w-2xl w-full p-6 shadow-2xl">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Kelola Template PICA</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Upload atau hapus template</p>
                </div>
                <button
                    @click="showTemplateModal = false"
                    class="p-2 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded-lg transition-colors"
                >
                    <X class="w-6 h-6" />
                </button>
            </div>

            <div class="space-y-6">
                <!-- Current Status -->
                <div v-if="template_exists" class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3 flex-1">
                            <CheckCircle class="w-5 h-5 text-green-600 dark:text-green-400 mt-0.5" />
                            <div>
                                <p class="text-sm font-semibold text-green-900 dark:text-green-200">Template Aktif</p>
                                <p class="text-xs text-green-700 dark:text-green-400 mt-1">temporary_action_template.pdf</p>
                            </div>
                        </div>
                        <button
                            @click="showDeleteConfirm = true"
                            class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2 text-sm font-medium"
                        >
                            <Trash2 class="w-4 h-4" />
                            Hapus
                        </button>
                    </div>
                </div>

                <!-- Upload Form -->
                <form @submit.prevent="uploadTemplate" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            {{ template_exists ? 'Upload Template Baru (akan mengganti yang lama)' : 'Upload Template' }}
                            <span class="text-red-500">*</span>
                        </label>
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept="application/pdf"
                            @change="handleFileChange"
                            class="w-full rounded-lg border border-sidebar-border px-4 py-3 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 dark:file:bg-purple-900/30 dark:file:text-purple-400"
                        />
                        <p class="text-xs text-gray-500 mt-2 flex items-center gap-1.5">
                            <AlertCircle class="w-3.5 h-3.5" />
                            Format: PDF | Maksimal: 10 MB
                        </p>

                        <div v-if="uploadForm.template" class="mt-4 bg-gray-50 dark:bg-gray-800 rounded-lg p-4 flex items-center gap-3">
                            <div class="bg-purple-100 dark:bg-purple-900/30 p-3 rounded-lg">
                                <FileText class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ uploadForm.template.name }}</p>
                                <p class="text-xs text-gray-500">{{ (uploadForm.template.size / 1024 / 1024).toFixed(2) }} MB</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                        <p class="text-xs text-blue-800 dark:text-blue-300">
                            💡 <strong>Info:</strong> Template ini akan digunakan oleh seluruh user untuk mengisi Temporary Action. Pastikan template sudah sesuai sebelum diupload.
                        </p>
                    </div>

                    <div class="flex gap-3 justify-end pt-4 border-t border-sidebar-border">
                        <button
                            type="button"
                            @click="showTemplateModal = false"
                            class="px-6 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-sidebar-accent transition-colors font-medium"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="!uploadForm.template || uploadForm.processing"
                            class="px-6 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 shadow-lg hover:shadow-xl transition-all font-medium"
                        >
                            <svg v-if="uploadForm.processing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <Upload class="w-5 h-5" />
                            {{ uploadForm.processing ? 'Uploading...' : 'Upload Template' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteConfirm" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[60] p-4">
        <div class="bg-white dark:bg-sidebar rounded-xl max-w-md w-full p-6 shadow-2xl">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Konfirmasi Hapus</h2>
                <button
                    @click="showDeleteConfirm = false"
                    class="p-2 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded-lg transition-colors"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <div class="mb-6">
                <div class="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <AlertCircle class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 flex-shrink-0" />
                    <div>
                        <p class="text-sm font-semibold text-red-900 dark:text-red-200">Apakah Anda yakin?</p>
                        <p class="text-xs text-red-700 dark:text-red-400 mt-1">
                            Template akan dihapus dan user tidak dapat mendownload template sampai Anda upload template baru.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 justify-end">
                <button
                    @click="showDeleteConfirm = false"
                    class="px-4 py-2 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-sidebar-accent transition-colors font-medium"
                >
                    Batal
                </button>
                <button
                    @click="deleteTemplate"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium flex items-center gap-2"
                >
                    <Trash2 class="w-4 h-4" />
                    Ya, Hapus
                </button>
            </div>
        </div>
    </div>
        </div>
        <!-- Filter Modal -->
        <div v-if="showFilterModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-xl max-w-lg w-full p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Filter Periode</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Pilih rentang tanggal untuk analisis</p>
                    </div>
                    <button
                        @click="showFilterModal = false"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded-lg transition-colors"
                    >
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold mb-3 text-gray-700 dark:text-gray-300">
                            Filter Cepat
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                @click="setQuickFilter('today')"
                                class="px-4 py-3 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors font-medium text-sm"
                            >
                                📅 Hari Ini
                            </button>
                            <button
                                @click="setQuickFilter('week')"
                                class="px-4 py-3 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors font-medium text-sm"
                            >
                                📆 Minggu Ini
                            </button>
                            <button
                                @click="setQuickFilter('month')"
                                class="px-4 py-3 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors font-medium text-sm"
                            >
                                🗓️ Bulan Ini
                            </button>
                            <button
                                @click="setQuickFilter('year')"
                                class="px-4 py-3 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors font-medium text-sm"
                            >
                                📊 Tahun Ini
                            </button>
                        </div>
                    </div>

                    <div class="border-t border-sidebar-border pt-6">
                        <label class="block text-sm font-semibold mb-3 text-gray-700 dark:text-gray-300">
                            Custom Range
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-600 dark:text-gray-400 mb-2">Tanggal Mulai</label>
                                <input
                                    v-model="startDate"
                                    type="date"
                                    class="w-full px-4 py-3 rounded-lg border border-sidebar-border dark:bg-sidebar-accent focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 dark:text-gray-400 mb-2">Tanggal Akhir</label>
                                <input
                                    v-model="endDate"
                                    type="date"
                                    class="w-full px-4 py-3 rounded-lg border border-sidebar-border dark:bg-sidebar-accent focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 justify-end pt-4 border-t border-sidebar-border">
                        <button
                            @click="resetFilter"
                            class="px-6 py-3 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-sidebar-accent transition-colors font-medium"
                        >
                            Reset
                        </button>
                        <button
                            @click="applyFilter"
                            :disabled="isLoading"
                            class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 shadow-lg hover:shadow-xl transition-all font-medium"
                        >
                            <svg v-if="isLoading" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <Filter class="w-5 h-5" />
                            {{ isLoading ? 'Memproses...' : 'Terapkan Filter' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
    height: 6px;
    width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}

.dark .scrollbar-thin::-webkit-scrollbar-thumb {
    background: #4b5563;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

.dark .scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #6b7280;
}
</style>
