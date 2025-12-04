<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    Wrench,
    AlertTriangle,
    CheckCircle,
    Clock,
    TrendingUp,
    Calendar,
    Eye,
    BarChart3,
    Plus,
} from 'lucide-vue-next';

interface Statistics {
    totalReports: number;
    pendingReports: number;
    inProgressReports: number;
    completedReports: number;
    correctiveReports: number;
    preventiveReports: number;
    totalSpareparts: number;
    activeDieParts: number;
}

interface RecentReport {
    id: number;
    report_no: string;
    activity_type: 'corrective' | 'preventive';
    pic_name: string;
    report_date: string;
    die_part: {
        part_no: string;
        part_name: string;
    };
    status: 'pending' | 'in_progress' | 'completed';
    created_at: string;
}

interface TrendData {
    date: string;
    corrective: number;
    preventive: number;
}

interface TopDiePart {
    die_part: {
        part_no: string;
        part_name: string;
    };
    report_count: number;
}

interface Props {
    statistics: Statistics;
    recentReports: RecentReport[];
    monthlyTrend: TrendData[];
    topDieParts: TopDiePart[];
    selectedMonth: string;
}

const props = defineProps<Props>();

const selectedMonth = ref(props.selectedMonth);
const hoveredBar = ref<number | null>(null);

const changeMonth = () => {
    router.get('/die-shop-dashboard', { month: selectedMonth.value }, { preserveState: false });
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

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const completionRate = computed(() => {
    if (props.statistics.totalReports === 0) return 0;
    return Math.round((props.statistics.completedReports / props.statistics.totalReports) * 100);
});

const maxTrendValue = computed(() => {
    const max = Math.max(
        ...props.monthlyTrend.map(d => d.corrective + d.preventive)
    );
    return Math.ceil(max / 5) * 5 || 10;
});

const getStackedBarHeights = (corrective: number, preventive: number) => {
    const total = corrective + preventive;
    if (maxTrendValue.value === 0 || total === 0) {
        return { correctiveHeight: 0, preventiveHeight: 0, totalHeight: 0 };
    }

    const totalHeight = (total / maxTrendValue.value) * 100;
    const correctiveHeight = (corrective / total) * totalHeight;
    const preventiveHeight = (preventive / total) * totalHeight;

    return { correctiveHeight, preventiveHeight, totalHeight };
};

const yAxisLabels = computed(() => {
    const max = maxTrendValue.value;
    return [max, Math.round(max * 0.75), Math.round(max * 0.5), Math.round(max * 0.25), 0];
});
</script>

<template>
    <Head title="Dashboard Die Shop" />
    <AppLayout :breadcrumbs="[
        { title: 'Die Shop System', href: '#' },
        { title: 'Dashboard', href: '/die-shop-dashboard' }
    ]">
        <div class="p-6 space-y-6 bg-gray-50 dark:bg-gray-900 min-h-screen">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <div class="p-2 bg-blue-600 rounded-lg">
                            <Wrench class="w-7 h-7 text-white" />
                        </div>
                        Dashboard Die Shop
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                        Monitoring sistem perbaikan & perawatan die secara real-time
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <Calendar class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                        <input
                            v-model="selectedMonth"
                            type="month"
                            @change="changeMonth"
                            class="pl-10 pr-4 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-800 dark:text-white transition-all"
                        />
                    </div>
                    <Link
                        href="/die-shop-reports/create"
                        class="flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30 hover:shadow-xl hover:shadow-blue-500/40 transition-all duration-200 font-medium"
                    >
                        <Plus class="w-5 h-5" />
                        <span class="hidden sm:inline">Buat Laporan</span>
                    </Link>
                </div>
            </div>

            <!-- Main Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total Reports -->
                <div class="group bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg group-hover:scale-110 transition-transform duration-300">
                                <BarChart3 class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="px-2.5 py-1 bg-blue-50 dark:bg-blue-900/20 rounded-full">
                                <span class="text-xs font-semibold text-blue-700 dark:text-blue-400">Total</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Total Laporan</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mb-2">{{ statistics.totalReports }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full"></span>
                                Bulan ini
                            </p>
                        </div>
                    </div>
                    <div class="h-1 bg-gradient-to-r from-blue-500 to-blue-600"></div>
                </div>

                <!-- Pending -->
                <div class="group bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-gray-100 dark:bg-gray-700 rounded-lg group-hover:scale-110 transition-transform duration-300">
                                <Clock class="w-6 h-6 text-gray-600 dark:text-gray-400" />
                            </div>
                            <div class="px-2.5 py-1 bg-gray-50 dark:bg-gray-700 rounded-full">
                                <span class="text-xs font-semibold text-gray-700 dark:text-gray-400">Waiting</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Pending</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mb-2">{{ statistics.pendingReports }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 bg-gray-500 rounded-full"></span>
                                Menunggu pengerjaan
                            </p>
                        </div>
                    </div>
                    <div class="h-1 bg-gradient-to-r from-gray-400 to-gray-500"></div>
                </div>

                <!-- In Progress -->
                <div class="group bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg group-hover:scale-110 transition-transform duration-300">
                                <TrendingUp class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                            </div>
                            <div class="px-2.5 py-1 bg-yellow-50 dark:bg-yellow-900/20 rounded-full">
                                <span class="text-xs font-semibold text-yellow-700 dark:text-yellow-400">Active</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Dalam Proses</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mb-2">{{ statistics.inProgressReports }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full animate-pulse"></span>
                                Sedang dikerjakan
                            </p>
                        </div>
                    </div>
                    <div class="h-1 bg-gradient-to-r from-yellow-400 to-yellow-500"></div>
                </div>

                <!-- Completed -->
                <div class="group bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg group-hover:scale-110 transition-transform duration-300">
                                <CheckCircle class="w-6 h-6 text-green-600 dark:text-green-400" />
                            </div>
                            <div class="px-2.5 py-1 bg-green-50 dark:bg-green-900/20 rounded-full">
                                <span class="text-xs font-semibold text-green-700 dark:text-green-400">{{ completionRate }}%</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Selesai</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mb-2">{{ statistics.completedReports }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                Completion rate
                            </p>
                        </div>
                    </div>
                    <div class="h-1 bg-gradient-to-r from-green-500 to-green-600"></div>
                </div>
            </div>
            <!-- Activity Type & Additional Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 rounded-xl p-5 border border-red-200 dark:border-red-800 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <AlertTriangle class="w-8 h-8 text-red-600 dark:text-red-400" />
                        <div class="px-2 py-1 bg-red-200 dark:bg-red-800 rounded-md">
                            <span class="text-xs font-bold text-red-800 dark:text-red-200">⚠️</span>
                        </div>
                    </div>
                    <p class="text-sm font-medium text-red-900 dark:text-red-300 mb-1">Corrective</p>
                    <p class="text-2xl font-bold text-red-700 dark:text-red-400">{{ statistics.correctiveReports }}</p>
                    <p class="text-xs text-red-600 dark:text-red-500 mt-1">Perbaikan darurat</p>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl p-5 border border-blue-200 dark:border-blue-800 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <Calendar class="w-8 h-8 text-blue-600 dark:text-blue-400" />
                        <div class="px-2 py-1 bg-blue-200 dark:bg-blue-800 rounded-md">
                            <span class="text-xs font-bold text-blue-800 dark:text-blue-200">📅</span>
                        </div>
                    </div>
                    <p class="text-sm font-medium text-blue-900 dark:text-blue-300 mb-1">Preventive</p>
                    <p class="text-2xl font-bold text-blue-700 dark:text-blue-400">{{ statistics.preventiveReports }}</p>
                    <p class="text-xs text-blue-600 dark:text-blue-500 mt-1">Perawatan rutin</p>
                </div>

                <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-xl p-5 border border-purple-200 dark:border-purple-800 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <Wrench class="w-8 h-8 text-purple-600 dark:text-purple-400" />
                        <div class="px-2 py-1 bg-purple-200 dark:bg-purple-800 rounded-md">
                            <span class="text-xs font-bold text-purple-800 dark:text-purple-200">🔧</span>
                        </div>
                    </div>
                    <p class="text-sm font-medium text-purple-900 dark:text-purple-300 mb-1">Sparepart</p>
                    <p class="text-2xl font-bold text-purple-700 dark:text-purple-400">{{ statistics.totalSpareparts }}</p>
                    <p class="text-xs text-purple-600 dark:text-purple-500 mt-1">Total digunakan</p>
                </div>

                <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 dark:from-indigo-900/20 dark:to-indigo-800/20 rounded-xl p-5 border border-indigo-200 dark:border-indigo-800 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-3xl">⚙️</div>
                        <div class="px-2 py-1 bg-indigo-200 dark:bg-indigo-800 rounded-md">
                            <span class="text-xs font-bold text-indigo-800 dark:text-indigo-200">✓</span>
                        </div>
                    </div>
                    <p class="text-sm font-medium text-indigo-900 dark:text-indigo-300 mb-1">Die Parts</p>
                    <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-400">{{ statistics.activeDieParts }}</p>
                    <p class="text-xs text-indigo-600 dark:text-indigo-500 mt-1">Total aktif</p>
                </div>
            </div>
            <!-- Charts Section -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <!-- Monthly Trend - Improved Stacked Bar Chart -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <BarChart3 class="w-5 h-5 text-blue-600" />
                                    Trend Perbaikan
                                </h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Data bulan ini</p>
                            </div>
                            <!-- Legend -->
                            <div class="flex items-center gap-4 text-xs">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-red-500 rounded"></div>
                                    <span class="text-gray-600 dark:text-gray-400">Corrective</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-blue-500 rounded"></div>
                                    <span class="text-gray-600 dark:text-gray-400">Preventive</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        <div v-if="monthlyTrend.length > 0" class="relative">
                            <div class="flex gap-4">
                                <!-- Y-axis -->
                                <div class="flex flex-col justify-between text-right pr-2 py-2" style="min-width: 32px;">
                                    <div v-for="label in yAxisLabels" :key="label" class="text-xs text-gray-500 dark:text-gray-400 leading-none">
                                        {{ label }}
                                    </div>
                                </div>

                                <!-- Chart Area -->
                                <div class="flex-1 relative">
                                    <!-- Grid lines -->
                                    <div class="absolute inset-0 flex flex-col justify-between pointer-events-none">
                                        <div v-for="i in 5" :key="i" class="border-t border-gray-200 dark:border-gray-700"></div>
                                    </div>

                                    <!-- Bars -->
                                    <div class="relative h-64 flex items-end justify-between gap-1 px-2">
                                        <div
                                            v-for="(trend, index) in monthlyTrend"
                                            :key="index"
                                            class="flex-1 relative flex flex-col justify-end"
                                            @mouseenter="hoveredBar = index"
                                            @mouseleave="hoveredBar = null"
                                        >
                                            <div class="relative w-full flex flex-col justify-end min-h-0">
                                                <!-- Stacked Bars -->
                                                <template v-if="trend.corrective > 0 || trend.preventive > 0">
                                                    <!-- Corrective (bottom) -->
                                                    <div
                                                        v-if="trend.corrective > 0"
                                                        class="w-full bg-red-500 hover:bg-red-600 transition-all duration-200 rounded-t cursor-pointer"
                                                        :style="{
                                                            height: `${getStackedBarHeights(trend.corrective, trend.preventive).correctiveHeight}%`,
                                                            minHeight: '4px'
                                                        }"
                                                    ></div>

                                                    <!-- Preventive (top) -->
                                                    <div
                                                        v-if="trend.preventive > 0"
                                                        class="w-full bg-blue-500 hover:bg-blue-600 transition-all duration-200 cursor-pointer"
                                                        :class="{ 'rounded-t': trend.corrective === 0 }"
                                                        :style="{
                                                            height: `${getStackedBarHeights(trend.corrective, trend.preventive).preventiveHeight}%`,
                                                            minHeight: '4px'
                                                        }"
                                                    ></div>
                                                </template>

                                                <!-- Tooltip -->
                                                <div
                                                    v-if="hoveredBar === index && (trend.corrective > 0 || trend.preventive > 0)"
                                                    class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 dark:bg-gray-700 text-white text-xs rounded-lg shadow-lg whitespace-nowrap z-10 pointer-events-none"
                                                >
                                                    <div class="font-semibold mb-1">{{ new Date(trend.date).getDate() }} {{ new Date(trend.date).toLocaleDateString('id-ID', { month: 'short' }) }}</div>
                                                    <div class="flex items-center gap-2 text-red-300" v-if="trend.corrective > 0">
                                                        <span class="w-2 h-2 bg-red-500 rounded"></span>
                                                        Corrective: {{ trend.corrective }}
                                                    </div>
                                                    <div class="flex items-center gap-2 text-blue-300" v-if="trend.preventive > 0">
                                                        <span class="w-2 h-2 bg-blue-500 rounded"></span>
                                                        Preventive: {{ trend.preventive }}
                                                    </div>
                                                    <div class="flex items-center gap-2 font-semibold mt-1 pt-1 border-t border-gray-600">
                                                        Total: {{ trend.corrective + trend.preventive }}
                                                    </div>
                                                    <!-- Arrow -->
                                                    <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900 dark:border-t-gray-700"></div>
                                                </div>
                                            </div>

                                            <!-- X-axis label -->
                                            <div class="text-center mt-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                                                {{ new Date(trend.date).getDate() }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- X-axis line -->
                                    <div class="border-t-2 border-gray-300 dark:border-gray-600 mt-2"></div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="h-64 flex flex-col items-center justify-center text-gray-400">
                            <BarChart3 class="w-16 h-16 mb-4 opacity-30" />
                            <p class="text-sm font-medium">Tidak ada data untuk bulan ini</p>
                            <p class="text-xs mt-1">Pilih bulan lain untuk melihat data</p>
                        </div>
                    </div>
                </div>
                <!-- Top Die Parts -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <TrendingUp class="w-5 h-5 text-orange-600" />
                                    Top Die Parts
                                </h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Paling sering diperbaiki</p>
                            </div>
                            <div class="px-3 py-1 bg-orange-100 dark:bg-orange-900/30 rounded-full">
                                <span class="text-xs font-bold text-orange-700 dark:text-orange-400">Top 5</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        <div v-if="topDieParts.length > 0" class="space-y-3">
                            <div
                                v-for="(item, index) in topDieParts"
                                :key="index"
                                class="group relative bg-gradient-to-r from-gray-50 to-white dark:from-gray-700 dark:to-gray-750 hover:from-blue-50 hover:to-blue-100 dark:hover:from-blue-900/20 dark:hover:to-blue-800/20 rounded-lg p-4 transition-all duration-300 border border-gray-200 dark:border-gray-600 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-md"
                            >
                                <div class="flex items-center gap-4">
                                    <!-- Rank Badge -->
                                    <div class="flex-shrink-0">
                                        <div
                                            class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white shadow-lg"
                                            :class="{
                                                'bg-gradient-to-br from-yellow-400 to-yellow-600': index === 0,
                                                'bg-gradient-to-br from-gray-300 to-gray-500': index === 1,
                                                'bg-gradient-to-br from-orange-400 to-orange-600': index === 2,
                                                'bg-gradient-to-br from-blue-400 to-blue-600': index > 2
                                            }"
                                        >
                                            <span v-if="index === 0">🥇</span>
                                            <span v-else-if="index === 1">🥈</span>
                                            <span v-else-if="index === 2">🥉</span>
                                            <span v-else>{{ index + 1 }}</span>
                                        </div>
                                    </div>

                                    <!-- Die Part Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0 flex-1">
                                                <p class="font-bold text-gray-900 dark:text-white truncate text-sm">
                                                    {{ item.die_part.part_no }}
                                                </p>
                                                <p class="text-xs text-gray-600 dark:text-gray-400 truncate mt-0.5">
                                                    {{ item.die_part.part_name }}
                                                </p>
                                            </div>

                                            <!-- Count Badge -->
                                            <div class="flex-shrink-0 text-right">
                                                <div class="px-3 py-1 bg-blue-600 text-white rounded-full">
                                                    <p class="text-lg font-bold leading-none">{{ item.report_count }}</p>
                                                </div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">laporan</p>
                                            </div>
                                        </div>

                                        <!-- Progress Bar -->
                                        <div class="mt-3">
                                            <div class="h-2 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                                                <div
                                                    class="h-full rounded-full transition-all duration-500"
                                                    :class="{
                                                        'bg-gradient-to-r from-yellow-400 to-yellow-600': index === 0,
                                                        'bg-gradient-to-r from-gray-400 to-gray-600': index === 1,
                                                        'bg-gradient-to-r from-orange-400 to-orange-600': index === 2,
                                                        'bg-gradient-to-r from-blue-400 to-blue-600': index > 2
                                                    }"
                                                    :style="{ width: `${(item.report_count / topDieParts[0].report_count) * 100}%` }"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="h-64 flex flex-col items-center justify-center text-gray-400">
                            <TrendingUp class="w-16 h-16 mb-4 opacity-30" />
                            <p class="text-sm font-medium">Belum ada data</p>
                            <p class="text-xs mt-1">Data akan muncul setelah ada laporan</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Recent Reports Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Clock class="w-5 h-5 text-blue-600" />
                                Laporan Terbaru
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">10 laporan terakhir</p>
                        </div>
                        <Link
                            href="/die-shop-reports"
                            class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-lg transition-all"
                        >
                            Lihat Semua
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </Link>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-600">
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    No Laporan
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Tanggal
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Jenis
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Die Part
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    PIC
                                </th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="report in recentReports"
                                :key="report.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-bold text-blue-600 dark:text-blue-400">
                                        {{ report.report_no }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900 dark:text-gray-300">
                                        {{ formatDate(report.report_date) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold',
                                        getActivityBadge(report.activity_type)
                                    ]">
                                        <span v-if="report.activity_type === 'corrective'">⚠️</span>
                                        <span v-else>📅</span>
                                        {{ report.activity_type === 'corrective' ? 'Corrective' : 'Preventive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-xs">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                            {{ report.die_part.part_no }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                            {{ report.die_part.part_name }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900 dark:text-gray-300">
                                        {{ report.pic_name }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span :class="[
                                        'inline-flex px-3 py-1 rounded-full text-xs font-semibold',
                                        getStatusBadge(report.status)
                                    ]">
                                        {{ getStatusLabel(report.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <Link
                                        :href="`/die-shop-reports/${report.id}`"
                                        class="inline-flex items-center justify-center p-2 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-lg transition-all"
                                        title="Lihat Detail"
                                    >
                                        <Eye class="w-5 h-5" />
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="recentReports.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center text-gray-400">
                                        <Clock class="w-16 h-16 mb-4 opacity-30" />
                                        <p class="text-sm font-medium">Belum ada laporan</p>
                                        <p class="text-xs mt-1">Laporan akan muncul di sini</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
