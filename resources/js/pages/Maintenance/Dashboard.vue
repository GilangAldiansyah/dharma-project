<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import {
    BarChart3,
    TrendingUp,
    AlertTriangle,
    Clock,
    Factory,
    Wrench,
    Filter,
    X,
    Eye,
    Zap,
    Download,
    RefreshCw,
    Table as TableIcon,
} from 'lucide-vue-next';
import * as XLSX from 'xlsx';

interface Machine {
    machine_id: number;
    machine_name: string;
    machine_type: string;
    stops_count: number;
    downtime_minutes: number;
}

interface LineStop {
    line_id: number;
    line_name: string;
    line_code: string;
    plant: string;
    total_stops: number;
    total_downtime_minutes: number;
    machines: Machine[];
}

interface MttrMtbf {
    line_id: number;
    line_name: string;
    line_code: string;
    plant: string;
    mttr_hours: number | null;
    mtbf_hours: number | null;
    total_stops: number;
}

interface DailyStop {
    date: string;
    full_date: string;
    stops_count: number;
}

interface MachineProblem {
    machine_id: number;
    machine_name: string;
    line_name: string;
    plant: string;
    problem_count: number;
}

interface TopProblem {
    problem: string;
    occurrence: number;
}

interface Props {
    lineStopByLine: LineStop[];
    mttrMtbfByLine: MttrMtbf[];
    dailyLineStops: DailyStop[];
    machineProblemFrequency: MachineProblem[];
    avgRepairTimeByStatus: Record<string, number>;
    topProblems: TopProblem[];
    overallStats: {
        total_line_stops: number;
        total_downtime_hours: number;
        avg_mttr_hours: number;
        avg_mtbf_hours: number;
        active_maintenance: number;
    };
    lineStatusDistribution: Record<string, number>;
    plants: string[];
    filters: {
        start_date: string;
        end_date: string;
        plant?: string;
    };
}

const props = defineProps<Props>();

const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);
const plantFilter = ref(props.filters.plant || '');
const selectedLine = ref<LineStop | null>(null);
const showMachineBreakdown = ref(false);
const showFilterModal = ref(false);
const isLoading = ref(false);
const isExporting = ref(false);
const viewMode = ref<'bar' | 'table'>('bar');

const hoveredBar = ref<number | null>(null);
const hoveredLineBar = ref<number | null>(null);
const hoveredMachineBar = ref<number | null>(null);
const hoveredProblemBar = ref<number | null>(null);
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
    hoveredBar.value = null;
    hoveredLineBar.value = null;
    hoveredMachineBar.value = null;
    hoveredProblemBar.value = null;
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

    applyFilters();
};

const applyFilters = () => {
    isLoading.value = true;
    router.get('/maintenance/dashboard', {
        start_date: startDate.value,
        end_date: endDate.value,
        plant: plantFilter.value,
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
    plantFilter.value = '';
    applyFilters();
};

const refreshData = () => {
    isLoading.value = true;
    router.reload({
        only: ['lineStopByLine', 'mttrMtbfByLine', 'dailyLineStops', 'machineProblemFrequency', 'topProblems', 'overallStats'],
        onFinish: () => {
            isLoading.value = false;
        }
    });
};

const exportData = async () => {
    try {
        isExporting.value = true;

        const response = await fetch(`/maintenance/dashboard/export?start_date=${startDate.value}&end_date=${endDate.value}&plant=${plantFilter.value}`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.data || !Array.isArray(result.data)) {
            throw new Error('Data tidak valid dari server');
        }

        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.json_to_sheet(result.data);
        XLSX.utils.book_append_sheet(wb, ws, 'Maintenance Dashboard');
        XLSX.writeFile(wb, result.filename);

    } catch (error) {
        console.error('Export error:', error);
        alert('Gagal mengekspor data. Silakan coba lagi.');
    } finally {
        isExporting.value = false;
    }
};

const formatMinutesToHours = (minutes: number): string => {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
};

const formatHours = (hours: number | null): string => {
    if (hours === null || hours === 0) return 'N/A';
    return `${hours.toFixed(2)}h`;
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
};

const viewMachineBreakdown = (line: LineStop) => {
    selectedLine.value = line;
    showMachineBreakdown.value = true;
};

const closeMachineBreakdown = () => {
    showMachineBreakdown.value = false;
    selectedLine.value = null;
};

const getPercentage = (value: number, total: number): string => {
    if (total === 0) return '0.0';
    return ((value / total) * 100).toFixed(1);
};

const avgStopsPerDay = computed(() => {
    if (props.dailyLineStops.length === 0) return 0;
    const totalStops = props.dailyLineStops.reduce((sum, d) => sum + d.stops_count, 0);
    return (totalStops / props.dailyLineStops.length).toFixed(1);
});

const maxLineStopValue = computed(() =>
    Math.max(...props.lineStopByLine.map(item => item.total_stops), 1)
);

const maxMachineProblemValue = computed(() =>
    Math.max(...props.machineProblemFrequency.map(item => item.problem_count), 1)
);

const maxTopProblemValue = computed(() =>
    Math.max(...props.topProblems.map(item => item.occurrence), 1)
);

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
    <Head title="Dashboard Maintenance" />
    <AppLayout :breadcrumbs="[
        { title: 'Maintenance', href: '/maintenance' },
        { title: 'Dashboard', href: '/maintenance/dashboard' }
    ]">
        <div class="p-4 sm:p-6 space-y-6">

            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold flex items-center gap-3">
                            <div class="bg-white/20 backdrop-blur-sm p-3 rounded-lg">
                                <BarChart3 class="w-7 h-7" />
                            </div>
                            Dashboard Line Stop
                        </h1>
                        <p class="text-blue-100 mt-2 text-sm lg:text-base">
                            Visualisasi data line stop, MTTR, MTBF, dan analisis maintenance
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
                            Filter
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
                            class="px-4 py-2.5 bg-white text-blue-600 hover:bg-blue-50 rounded-lg transition-all flex items-center gap-2 text-sm font-semibold shadow-lg disabled:opacity-50"
                        >
                            <Download class="w-4 h-4" :class="{ 'animate-bounce': isExporting }" />
                            {{ isExporting ? 'Exporting...' : 'Export Excel' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 lg:gap-6">
                <!-- Total Line Stops -->
                <div class="group bg-white dark:bg-sidebar rounded-xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-sidebar-border">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-900/30 dark:to-blue-800/30 rounded-xl group-hover:scale-110 transition-transform duration-300 shadow-sm">
                                <AlertTriangle class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="px-3 py-1.5 bg-blue-50 dark:bg-blue-900/20 rounded-full">
                                <span class="text-xs font-bold text-blue-700 dark:text-blue-400">Total</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-1">Line Stops</p>
                            <p class="text-3xl lg:text-4xl font-bold text-gray-900 dark:text-white mb-2">{{ overallStats.total_line_stops }}</p>
                            <div class="flex items-center justify-between">
                                <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1.5">
                                    <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                                    Periode ini
                                </p>
                                <p class="text-xs font-semibold text-blue-600 dark:text-blue-400">
                                    {{ avgStopsPerDay }}/hari
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="h-1.5 bg-gradient-to-r from-blue-500 via-blue-600 to-blue-700"></div>
                </div>

                <!-- Total Downtime -->
                <div class="group bg-white dark:bg-sidebar rounded-xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-sidebar-border">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-gradient-to-br from-red-100 to-red-200 dark:from-red-900/30 dark:to-red-800/30 rounded-xl group-hover:scale-110 transition-transform duration-300 shadow-sm">
                                <Clock class="w-6 h-6 text-red-600 dark:text-red-400" />
                            </div>
                            <div class="px-3 py-1.5 bg-red-50 dark:bg-red-900/20 rounded-full">
                                <span class="text-xs font-bold text-red-700 dark:text-red-400">Hours</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-1">Total Downtime</p>
                            <p class="text-3xl lg:text-4xl font-bold text-gray-900 dark:text-white mb-2">{{ overallStats.total_downtime_hours.toFixed(1) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1.5">
                                <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                Total jam downtime
                            </p>
                        </div>
                    </div>
                    <div class="h-1.5 bg-gradient-to-r from-red-500 via-red-600 to-red-700"></div>
                </div>

                <!-- MTTR -->
                <div class="group bg-white dark:bg-sidebar rounded-xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-sidebar-border">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-gradient-to-br from-purple-100 to-purple-200 dark:from-purple-900/30 dark:to-purple-800/30 rounded-xl group-hover:scale-110 transition-transform duration-300 shadow-sm">
                                <Wrench class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div class="px-3 py-1.5 bg-purple-50 dark:bg-purple-900/20 rounded-full">
                                <span class="text-xs font-bold text-purple-700 dark:text-purple-400">MTTR</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-1">Avg Repair Time</p>
                            <p class="text-3xl lg:text-4xl font-bold text-gray-900 dark:text-white mb-2">{{ formatHours(overallStats.avg_mttr_hours) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1.5">
                                <span class="w-2 h-2 bg-purple-500 rounded-full"></span>
                                Mean Time To Repair
                            </p>
                        </div>
                    </div>
                    <div class="h-1.5 bg-gradient-to-r from-purple-500 via-purple-600 to-purple-700"></div>
                </div>

                <!-- MTBF -->
                <div class="group bg-white dark:bg-sidebar rounded-xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-sidebar-border">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-gradient-to-br from-green-100 to-green-200 dark:from-green-900/30 dark:to-green-800/30 rounded-xl group-hover:scale-110 transition-transform duration-300 shadow-sm">
                                <TrendingUp class="w-6 h-6 text-green-600 dark:text-green-400" />
                            </div>
                            <div class="px-3 py-1.5 bg-green-50 dark:bg-green-900/20 rounded-full">
                                <span class="text-xs font-bold text-green-700 dark:text-green-400">MTBF</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-1">Between Failures</p>
                            <p class="text-3xl lg:text-4xl font-bold text-gray-900 dark:text-white mb-2">{{ formatHours(overallStats.avg_mtbf_hours) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1.5">
                                <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                Mean Time Between Fail
                            </p>
                        </div>
                    </div>
                    <div class="h-1.5 bg-gradient-to-r from-green-500 via-green-600 to-green-700"></div>
                </div>

                <!-- Active Maintenance -->
                <div class="group bg-white dark:bg-sidebar rounded-xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-sidebar-border">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-gradient-to-br from-orange-100 to-orange-200 dark:from-orange-900/30 dark:to-orange-800/30 rounded-xl group-hover:scale-110 transition-transform duration-300 shadow-sm">
                                <Zap class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div class="px-3 py-1.5 bg-orange-50 dark:bg-orange-900/20 rounded-full">
                                <span class="text-xs font-bold text-orange-700 dark:text-orange-400">Active</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-1">Ongoing Repairs</p>
                            <p class="text-3xl lg:text-4xl font-bold text-gray-900 dark:text-white mb-2">{{ overallStats.active_maintenance }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1.5">
                                <span class="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></span>
                                Sedang diperbaiki
                            </p>
                        </div>
                    </div>
                    <div class="h-1.5 bg-gradient-to-r from-orange-500 via-orange-600 to-orange-700"></div>
                </div>
            </div>
            <!-- Charts Section -->
            <div class="bg-white dark:bg-sidebar rounded-xl shadow-lg border border-sidebar-border overflow-hidden">
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
                        <TableIcon class="w-5 h-5" />
                        Tabel Detail
                    </button>
                </div>

                <!-- Bar Charts View -->
                <div v-if="viewMode === 'bar'" class="p-6 space-y-8">
                    <!-- Line Stop by Line Chart -->
                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/10 dark:to-indigo-900/10 rounded-xl p-6 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Factory class="w-5 h-5 text-blue-600" />
                                Line Stop berdasarkan Line (Top 15)
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400 bg-white dark:bg-sidebar px-3 py-1 rounded-full">
                                {{ lineStopByLine.length }} lines
                            </span>
                        </div>

                        <div v-if="lineStopByLine.length > 0" class="bg-white dark:bg-sidebar rounded-xl p-6 shadow-sm">
                            <div class="space-y-2">
                                <div v-for="(item, index) in lineStopByLine.slice(0, 15)" :key="index"
                                     class="flex items-center gap-3 cursor-pointer"
                                     @click="viewMachineBreakdown(item)"
                                     @mouseenter="hoveredLineBar = index"
                                     @mouseleave="clearHover">
                                    <div class="w-32 text-xs text-gray-600 dark:text-gray-400 font-medium truncate" :title="item.line_name">
                                        {{ item.line_code }}
                                    </div>
                                    <div class="flex-1 flex items-center gap-2">
                                        <div class="flex-1 h-10 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden relative">
                                            <div
                                                class="h-full bg-gradient-to-r from-blue-500 to-blue-600 transition-all duration-300 rounded-lg flex items-center justify-end pr-3"
                                                :class="{ 'from-blue-600 to-blue-700 shadow-lg': hoveredLineBar === index }"
                                                :style="{ width: `${(item.total_stops / maxLineStopValue) * 100}%` }">
                                                <span v-if="item.total_stops > 0" class="text-white text-sm font-bold">{{ item.total_stops }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-20 text-gray-500">
                            <Factory class="w-16 h-16 mx-auto mb-3 opacity-30" />
                            <p class="font-medium">Tidak ada data untuk periode ini</p>
                        </div>

                        <Teleport to="body">
                            <div
                                v-if="hoveredLineBar !== null && lineStopByLine[hoveredLineBar]"
                                class="fixed pointer-events-none z-[9999] -translate-x-1/2"
                                :style="tooltipPosition"
                            >
                                <div class="bg-gray-900 text-white text-xs rounded-lg shadow-2xl p-3 min-w-[200px]">
                                    <div class="font-bold mb-1">{{ lineStopByLine[hoveredLineBar].line_name }}</div>
                                    <div class="text-gray-300 text-[10px] mb-2">{{ lineStopByLine[hoveredLineBar].line_code }} - {{ lineStopByLine[hoveredLineBar].plant }}</div>
                                    <div class="pt-2 border-t border-gray-700">
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-400">Total Stops:</span>
                                            <span class="text-blue-400 font-bold">{{ lineStopByLine[hoveredLineBar].total_stops }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-400">Downtime:</span>
                                            <span class="text-red-400 font-bold">{{ formatMinutesToHours(lineStopByLine[hoveredLineBar].total_downtime_minutes) }}</span>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-blue-400 text-center text-[10px] flex items-center justify-center gap-1">
                                        <Eye class="w-3 h-3" />
                                        Klik untuk detail mesin
                                    </div>
                                    <div class="absolute top-full left-1/2 -translate-x-1/2">
                                        <div class="border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            </div>
                        </Teleport>
                    </div>

                    <!-- Machine Problem Chart -->
                    <div class="bg-gradient-to-br from-orange-50 to-red-50 dark:from-orange-900/10 dark:to-red-900/10 rounded-xl p-6 border border-orange-200 dark:border-orange-800">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Wrench class="w-5 h-5 text-orange-600" />
                                Mesin Bermasalah
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400 bg-white dark:bg-sidebar px-3 py-1 rounded-full">
                                {{ machineProblemFrequency.length }} mesin
                            </span>
                        </div>

                        <div v-if="machineProblemFrequency.length > 0" class="bg-white dark:bg-sidebar rounded-xl p-6 shadow-sm">
                            <div class="space-y-2">
                                <div v-for="(item, index) in machineProblemFrequency" :key="index"
                                     class="flex items-center gap-3"
                                     @mouseenter="hoveredMachineBar = index"
                                     @mouseleave="clearHover">
                                    <div class="w-40 text-xs text-gray-600 dark:text-gray-400 font-medium truncate" :title="item.machine_name">
                                        {{ item.machine_name }}
                                    </div>
                                    <div class="flex-1 flex items-center gap-2">
                                        <div class="flex-1 h-10 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden relative">
                                            <div
                                                class="h-full bg-gradient-to-r from-orange-500 to-orange-600 transition-all duration-300 rounded-lg flex items-center justify-end pr-3"
                                                :class="{ 'from-orange-600 to-orange-700 shadow-lg': hoveredMachineBar === index }"
                                                :style="{ width: `${(item.problem_count / maxMachineProblemValue) * 100}%` }">
                                                <span v-if="item.problem_count > 0" class="text-white text-sm font-bold">{{ item.problem_count }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-20 text-gray-500">
                            <Wrench class="w-16 h-16 mx-auto mb-3 opacity-30" />
                            <p class="font-medium">Tidak ada data untuk periode ini</p>
                        </div>

                        <Teleport to="body">
                            <div
                                v-if="hoveredMachineBar !== null && machineProblemFrequency[hoveredMachineBar]"
                                class="fixed pointer-events-none z-[9999] -translate-x-1/2"
                                :style="tooltipPosition"
                            >
                                <div class="bg-gray-900 text-white text-xs rounded-lg shadow-2xl p-3 min-w-[180px]">
                                    <div class="font-bold mb-1">{{ machineProblemFrequency[hoveredMachineBar].machine_name }}</div>
                                    <div class="text-gray-300 text-[10px] mb-2">{{ machineProblemFrequency[hoveredMachineBar].line_name }} - {{ machineProblemFrequency[hoveredMachineBar].plant }}</div>
                                    <div class="pt-2 border-t border-gray-700 text-center">
                                        <span class="text-orange-400 font-bold text-base">{{ machineProblemFrequency[hoveredMachineBar].problem_count }} Masalah</span>
                                    </div>
                                    <div class="absolute top-full left-1/2 -translate-x-1/2">
                                        <div class="border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            </div>
                        </Teleport>
                    </div>

                    <!-- Top Problems Chart -->
                    <div class="bg-gradient-to-br from-red-50 to-pink-50 dark:from-red-900/10 dark:to-pink-900/10 rounded-xl p-6 border border-red-200 dark:border-red-800">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <AlertTriangle class="w-5 h-5 text-red-600" />
                                Jenis Masalah
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400 bg-white dark:bg-sidebar px-3 py-1 rounded-full">
                                {{ topProblems.length }} masalah
                            </span>
                        </div>

                        <div v-if="topProblems.length > 0" class="bg-white dark:bg-sidebar rounded-xl p-6 shadow-sm">
                            <div class="space-y-2">
                                <div v-for="(item, index) in topProblems" :key="index"
                                     class="flex items-center gap-3"
                                     @mouseenter="hoveredProblemBar = index"
                                     @mouseleave="clearHover">
                                    <div class="w-48 text-xs text-gray-600 dark:text-gray-400 font-medium truncate" :title="item.problem">
                                        {{ item.problem }}
                                    </div>
                                    <div class="flex-1 flex items-center gap-2">
                                        <div class="flex-1 h-10 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden relative">
                                            <div
                                                class="h-full bg-gradient-to-r from-red-500 to-red-600 transition-all duration-300 rounded-lg flex items-center justify-end pr-3"
                                                :class="{ 'from-red-600 to-red-700 shadow-lg': hoveredProblemBar === index }"
                                                :style="{ width: `${(item.occurrence / maxTopProblemValue) * 100}%` }">
                                                <span v-if="item.occurrence > 0" class="text-white text-sm font-bold">{{ item.occurrence }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-20 text-gray-500">
                            <AlertTriangle class="w-16 h-16 mx-auto mb-3 opacity-30" />
                            <p class="font-medium">Tidak ada data untuk periode ini</p>
                        </div>

                        <Teleport to="body">
                            <div
                                v-if="hoveredProblemBar !== null && topProblems[hoveredProblemBar]"
                                class="fixed pointer-events-none z-[9999] -translate-x-1/2"
                                :style="tooltipPosition"
                            >
                                <div class="bg-gray-900 text-white text-xs rounded-lg shadow-2xl p-3 min-w-[180px]">
                                    <div class="font-bold text-center mb-2">{{ topProblems[hoveredProblemBar].problem }}</div>
                                    <div class="pt-2 border-t border-gray-700 text-center">
                                        <span class="text-red-400 font-bold text-base">{{ topProblems[hoveredProblemBar].occurrence }} Kejadian</span>
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
                    <!-- Line Stop Table -->
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Factory class="w-5 h-5 text-blue-600" />
                                Line Stop berdasarkan Line
                            </h3>
                            <span class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 px-3 py-1 rounded-full font-semibold">
                                {{ lineStopByLine.length }} lines
                            </span>
                        </div>
                        <div class="overflow-x-auto border border-sidebar-border rounded-lg shadow-sm">
                            <table class="w-full text-sm">
                                <thead class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border-b-2 border-blue-200 dark:border-blue-800">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">No</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Line</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Plant</th>
                                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Total Stops</th>
                                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Downtime</th>
                                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-sidebar-border bg-white dark:bg-sidebar">
                                    <tr v-for="(line, index) in lineStopByLine" :key="line.line_id" class="hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-colors">
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400 font-medium">{{ index + 1 }}</td>
                                        <td class="px-6 py-4">
                                            <div class="max-w-xs">
                                                <div class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ line.line_name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">{{ line.line_code }}</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-900 dark:text-gray-300 font-medium">{{ line.plant }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <span class="text-sm font-bold text-red-600 dark:text-red-400">{{ line.total_stops }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-300">{{ formatMinutesToHours(line.total_downtime_minutes) }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-center whitespace-nowrap">
                                            <button
                                                @click="viewMachineBreakdown(line)"
                                                class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 bg-white dark:bg-gray-700 hover:bg-blue-50 dark:hover:bg-gray-600 rounded-lg transition-all shadow-sm hover:shadow-md border border-blue-200 dark:border-gray-600"
                                            >
                                                <Eye class="w-4 h-4" />
                                                {{ line.machines.length }} Mesin
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="lineStopByLine.length === 0">
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <Factory class="w-12 h-12 mx-auto mb-2 text-gray-400 opacity-50" />
                                            <p class="text-gray-500 font-medium">Tidak ada data untuk periode ini</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Machine Problem Table -->
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Wrench class="w-5 h-5 text-orange-600" />
                                Mesin Bermasalah
                            </h3>
                            <span class="text-xs bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 px-3 py-1 rounded-full font-semibold">
                                {{ machineProblemFrequency.length }} mesin
                            </span>
                        </div>
                        <div class="overflow-x-auto border border-sidebar-border rounded-lg shadow-sm">
                            <table class="w-full text-sm">
                                <thead class="bg-gradient-to-r from-orange-50 to-red-50 dark:from-orange-900/20 dark:to-red-900/20 border-b-2 border-orange-200 dark:border-orange-800">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">No</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Nama Mesin</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Line</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Plant</th>
                                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Jumlah Masalah</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-sidebar-border bg-white dark:bg-sidebar">
                                    <tr v-for="(machine, index) in machineProblemFrequency" :key="index" class="hover:bg-orange-50 dark:hover:bg-orange-900/10 transition-colors">
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400 font-medium">{{ index + 1 }}</td>
                                        <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">{{ machine.machine_name }}</td>
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ machine.line_name }}</td>
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ machine.plant }}</td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="inline-flex px-3 py-1.5 bg-orange-600 text-white rounded-lg font-bold text-base shadow-sm">
                                                {{ machine.problem_count }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="machineProblemFrequency.length === 0">
                                        <td colspan="5" class="px-6 py-12 text-center">
                                            <Wrench class="w-12 h-12 mx-auto mb-2 text-gray-400 opacity-50" />
                                            <p class="text-gray-500 font-medium">Tidak ada data untuk periode ini</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Problems Table -->
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <AlertTriangle class="w-5 h-5 text-red-600" />
                                Jenis Masalah
                            </h3>
                            <span class="text-xs bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 px-3 py-1 rounded-full font-semibold">
                                {{ topProblems.length }} masalah
                            </span>
                        </div>
                        <div class="overflow-x-auto border border-sidebar-border rounded-lg shadow-sm">
                            <table class="w-full text-sm">
                                <thead class="bg-gradient-to-r from-red-50 to-pink-50 dark:from-red-900/20 dark:to-pink-900/20 border-b-2 border-red-200 dark:border-red-800">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">No</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Jenis Masalah</th>
                                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Jumlah Kejadian</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-sidebar-border bg-white dark:bg-sidebar">
                                    <tr v-for="(problem, index) in topProblems" :key="index" class="hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors">
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400 font-medium">{{ index + 1 }}</td>
                                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ problem.problem }}</td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="inline-flex px-3 py-1.5 bg-red-600 text-white rounded-lg font-bold text-base shadow-sm">
                                                {{ problem.occurrence }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="topProblems.length === 0">
                                        <td colspan="3" class="px-6 py-12 text-center">
                                            <AlertTriangle class="w-12 h-12 mx-auto mb-2 text-gray-400 opacity-50" />
                                            <p class="text-gray-500 font-medium">Tidak ada data untuk periode ini</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Machine Breakdown Modal -->
            <div
                v-if="showMachineBreakdown && selectedLine"
                class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4"
                @click.self="closeMachineBreakdown"
            >
                <div class="bg-white dark:bg-sidebar rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-hidden border-2 border-sidebar-border">
                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-2xl font-bold">{{ selectedLine.line_name }}</h3>
                                <p class="text-blue-100 text-sm mt-1">
                                    {{ selectedLine.line_code }} - {{ selectedLine.plant }}
                                </p>
                            </div>
                            <button
                                @click="closeMachineBreakdown"
                                class="p-2 hover:bg-white/20 rounded-lg transition-colors"
                            >
                                <X class="w-6 h-6" />
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div class="bg-white/10 rounded-lg p-3">
                                <div class="text-blue-100 text-xs mb-1">Total Line Stops</div>
                                <div class="text-3xl font-bold">{{ selectedLine.total_stops }}</div>
                            </div>
                            <div class="bg-white/10 rounded-lg p-3">
                                <div class="text-blue-100 text-xs mb-1">Total Downtime</div>
                                <div class="text-3xl font-bold">
                                    {{ formatMinutesToHours(selectedLine.total_downtime_minutes) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-280px)]">
                        <h4 class="text-lg font-semibold mb-4 flex items-center gap-2">
                            <Wrench class="w-5 h-5 text-orange-600" />
                            Breakdown per Mesin ({{ selectedLine.machines.length }} mesin)
                        </h4>

                        <!-- Machine Table -->
                        <div class="overflow-x-auto rounded-xl border border-sidebar-border">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-700/50 border-b-2 border-sidebar-border">
                                        <th class="px-4 py-3 text-left font-bold text-gray-700 dark:text-gray-300 uppercase text-xs">Rank</th>
                                        <th class="px-4 py-3 text-left font-bold text-gray-700 dark:text-gray-300 uppercase text-xs">Nama Mesin</th>
                                        <th class="px-4 py-3 text-left font-bold text-gray-700 dark:text-gray-300 uppercase text-xs">Tipe</th>
                                        <th class="px-4 py-3 text-right font-bold text-gray-700 dark:text-gray-300 uppercase text-xs">Stops</th>
                                        <th class="px-4 py-3 text-right font-bold text-gray-700 dark:text-gray-300 uppercase text-xs">Downtime</th>
                                        <th class="px-4 py-3 text-right font-bold text-gray-700 dark:text-gray-300 uppercase text-xs">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-sidebar-border">
                                    <tr
                                        v-for="(machine, index) in selectedLine.machines"
                                        :key="machine.machine_id"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                                    >
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold"
                                                :class="index === 0 ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' :
                                                        index === 1 ? 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' :
                                                        index === 2 ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' :
                                                        'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                                            >
                                                {{ index + 1 }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ machine.machine_name }}</td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ machine.machine_type }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="font-bold text-red-600 dark:text-red-400">{{ machine.stops_count }}x</span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-300">
                                            {{ formatMinutesToHours(machine.downtime_minutes) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 font-medium">
                                            {{ getPercentage(machine.stops_count, selectedLine.total_stops) }}%
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-100 dark:bg-gray-700/50 font-bold border-t-2 border-sidebar-border">
                                    <tr>
                                        <td colspan="3" class="px-4 py-3 text-right uppercase text-gray-700 dark:text-gray-300">TOTAL:</td>
                                        <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">{{ selectedLine.total_stops }}x</td>
                                        <td class="px-4 py-3 text-right text-gray-900 dark:text-white">
                                            {{ formatMinutesToHours(selectedLine.total_downtime_minutes) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">100%</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="border-t-2 border-sidebar-border p-4 bg-gray-50 dark:bg-gray-750">
                        <button
                            @click="closeMachineBreakdown"
                            class="w-full px-4 py-3 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl hover:from-gray-700 hover:to-gray-800 font-semibold transition-all shadow-lg hover:shadow-xl"
                        >
                            Tutup
                        </button>
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

                        <div>
                            <label class="block text-sm font-semibold mb-3 text-gray-700 dark:text-gray-300">
                                Filter Plant
                            </label>
                            <select
                                v-model="plantFilter"
                                class="w-full px-4 py-3 rounded-lg border border-sidebar-border dark:bg-sidebar-accent focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                            >
                                <option value="">Semua Plant</option>
                                <option v-for="plant in plants" :key="plant" :value="plant">
                                    {{ plant }}
                                </option>
                            </select>
                        </div>

                        <div class="flex gap-3 justify-end pt-4 border-t border-sidebar-border">
                            <button
                                @click="resetFilter"
                                class="px-6 py-3 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-sidebar-accent transition-colors font-medium"
                            >
                                Reset
                            </button>
                            <button
                                @click="applyFilters"
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
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.overflow-y-auto::-webkit-scrollbar {
    width: 8px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.dark .overflow-y-auto::-webkit-scrollbar-track {
    background: #374151;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #555;
}

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
