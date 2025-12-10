<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    Wrench,
    CheckCircle,
    Clock,
    TrendingUp,
    Calendar,
    Eye,
    BarChart3,
    Plus,
    Users,
    Activity,
    Zap,
} from 'lucide-vue-next';

interface Statistics {
    totalReports: number;
    pendingReports: number;
    inProgressReports: number;
    completedReports: number;
    shift1Reports: number;
    shift2Reports: number;
    totalSpareparts: number;
    activeDieParts: number;
}

interface RecentReport {
    id: number;
    report_no: string;
    shift: '1' | '2';
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
    shift1: number;
    shift2: number;
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

const getShiftBadge = (shift: string) => {
    return shift === '1'
        ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
        : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400';
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
        ...props.monthlyTrend.map(d => d.shift1 + d.shift2)
    );
    return Math.ceil(max / 5) * 5 || 10;
});

const getStackedBarHeights = (shift1: number, shift2: number) => {
    const total = shift1 + shift2;
    if (maxTrendValue.value === 0 || total === 0) {
        return { shift1Height: 0, shift2Height: 0, totalHeight: 0 };
    }

    const totalHeight = (total / maxTrendValue.value) * 100;
    const shift1Height = (shift1 / total) * totalHeight;
    const shift2Height = (shift2 / total) * totalHeight;

    return { shift1Height, shift2Height, totalHeight };
};

const yAxisLabels = computed(() => {
    const max = maxTrendValue.value;
    return [max, Math.round(max * 0.75), Math.round(max * 0.5), Math.round(max * 0.25), 0];
});

const avgReportsPerDay = computed(() => {
    if (props.monthlyTrend.length === 0) return 0;
    const totalReports = props.monthlyTrend.reduce((sum, d) => sum + d.shift1 + d.shift2, 0);
    return (totalReports / props.monthlyTrend.length).toFixed(1);
});
</script>
<template>
    <Head title="Dashboard Die Shop" />
    <AppLayout :breadcrumbs="[
        { title: 'Dashboard', href: '/die-shop-dashboard' }
    ]">
        <div class="p-6 space-y-6 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 min-h-screen">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <div class="p-2.5 bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl shadow-lg shadow-blue-500/30">
                            <Wrench class="w-7 h-7 text-white" />
                        </div>
                        Dashboard Die Shop
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 flex items-center gap-2">
                        <Activity class="w-4 h-4" />
                        Monitoring sistem perbaikan corrective secara real-time
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative group">
                        <Calendar class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none group-hover:text-blue-500 transition-colors" />
                        <input
                            v-model="selectedMonth"
                            type="month"
                            @change="changeMonth"
                            class="pl-10 pr-4 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-800 dark:text-white transition-all hover:border-blue-400 shadow-sm"
                        />
                    </div>
                    <Link
                        href="/die-shop-reports/create"
                        class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 shadow-lg shadow-blue-500/30 hover:shadow-xl hover:shadow-blue-500/40 transition-all duration-200 font-medium hover:scale-105 active:scale-95"
                    >
                        <Plus class="w-5 h-5" />
                        <span class="hidden sm:inline">Buat Laporan</span>
                    </Link>
                </div>
            </div>

            <!-- Main Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

                <!-- Total Reports -->
                <div class="group bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-900/30 dark:to-blue-800/30 rounded-xl group-hover:scale-110 transition-transform duration-300 shadow-sm">
                                <BarChart3 class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="px-3 py-1.5 bg-blue-50 dark:bg-blue-900/20 rounded-full">
                                <span class="text-xs font-bold text-blue-700 dark:text-blue-400">Total</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-1">Total Laporan</p>
                            <p class="text-4xl font-bold text-gray-900 dark:text-white mb-2">{{ statistics.totalReports }}</p>
                            <div class="flex items-center justify-between">
                                <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1.5">
                                    <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                                    Bulan ini
                                </p>
                                <p class="text-xs font-semibold text-blue-600 dark:text-blue-400">
                                    ~{{ avgReportsPerDay }}/hari
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="h-1.5 bg-gradient-to-r from-blue-500 via-blue-600 to-blue-700"></div>
                </div>

                <!-- Pending -->
                <div class="group bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-500">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 rounded-xl group-hover:scale-110 transition-transform duration-300 shadow-sm">
                                <Clock class="w-6 h-6 text-gray-600 dark:text-gray-400" />
                            </div>
                            <div class="px-3 py-1.5 bg-gray-50 dark:bg-gray-700 rounded-full">
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-400">Waiting</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-1">Pending</p>
                            <p class="text-4xl font-bold text-gray-900 dark:text-white mb-2">{{ statistics.pendingReports }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1.5">
                                <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                                Menunggu pengerjaan
                            </p>
                        </div>
                    </div>
                    <div class="h-1.5 bg-gradient-to-r from-gray-400 via-gray-500 to-gray-600"></div>
                </div>

                <!-- In Progress -->
                <div class="group bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-yellow-300 dark:hover:border-yellow-600">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-gradient-to-br from-yellow-100 to-yellow-200 dark:from-yellow-900/30 dark:to-yellow-800/30 rounded-xl group-hover:scale-110 transition-transform duration-300 shadow-sm">
                                <Zap class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                            </div>
                            <div class="px-3 py-1.5 bg-yellow-50 dark:bg-yellow-900/20 rounded-full">
                                <span class="text-xs font-bold text-yellow-700 dark:text-yellow-400">Active</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-1">Dalam Proses</p>
                            <p class="text-4xl font-bold text-gray-900 dark:text-white mb-2">{{ statistics.inProgressReports }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1.5">
                                <span class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></span>
                                Sedang dikerjakan
                            </p>
                        </div>
                    </div>
                    <div class="h-1.5 bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600"></div>
                </div>

                <!-- Completed -->
                <div class="group bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-green-300 dark:hover:border-green-600">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-gradient-to-br from-green-100 to-green-200 dark:from-green-900/30 dark:to-green-800/30 rounded-xl group-hover:scale-110 transition-transform duration-300 shadow-sm">
                                <CheckCircle class="w-6 h-6 text-green-600 dark:text-green-400" />
                            </div>
                            <div class="px-3 py-1.5 bg-green-50 dark:bg-green-900/20 rounded-full">
                                <span class="text-xs font-bold text-green-700 dark:text-green-400">{{ completionRate }}%</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-1">Selesai</p>
                            <p class="text-4xl font-bold text-gray-900 dark:text-white mb-2">{{ statistics.completedReports }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1.5">
                                <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                Completion rate
                            </p>
                        </div>
                    </div>
                    <div class="h-1.5 bg-gradient-to-r from-green-500 via-green-600 to-green-700"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

                <!-- Shift 1 -->
                <div class="bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/10 rounded-xl p-5 border-2 border-amber-200 dark:border-amber-800 hover:shadow-lg hover:border-amber-300 dark:hover:border-amber-700 transition-all duration-300">
                    <div class="flex items-center justify-between mb-3">
                        <div class="p-2.5 bg-amber-200 dark:bg-amber-800 rounded-lg">
                            <Users class="w-6 h-6 text-amber-700 dark:text-amber-300" />
                        </div>
                        <div class="px-2.5 py-1 bg-amber-200 dark:bg-amber-800 rounded-full">
                            <span class="text-xs font-bold text-amber-800 dark:text-amber-200">☀️</span>
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-300 mb-1">Shift 1</p>
                    <p class="text-3xl font-bold text-amber-700 dark:text-amber-400">{{ statistics.shift1Reports }}</p>
                    <p class="text-xs text-amber-600 dark:text-amber-500 mt-1.5">07.00 - 16.00</p>
                </div>

                <div class="bg-gradient-to-br from-indigo-50 to-indigo-100/50 dark:from-indigo-900/20 dark:to-indigo-800/10 rounded-xl p-5 border-2 border-indigo-200 dark:border-indigo-800 hover:shadow-lg hover:border-indigo-300 dark:hover:border-indigo-700 transition-all duration-300">
                    <div class="flex items-center justify-between mb-3">
                        <div class="p-2.5 bg-indigo-200 dark:bg-indigo-800 rounded-lg">
                            <Users class="w-6 h-6 text-indigo-700 dark:text-indigo-300" />
                        </div>
                        <div class="px-2.5 py-1 bg-indigo-200 dark:bg-indigo-800 rounded-full">
                            <span class="text-xs font-bold text-indigo-800 dark:text-indigo-200">🌙</span>
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-indigo-900 dark:text-indigo-300 mb-1">Shift 2</p>
                    <p class="text-3xl font-bold text-indigo-700 dark:text-indigo-400">{{ statistics.shift2Reports }}</p>
                    <p class="text-xs text-indigo-600 dark:text-indigo-500 mt-1.5">21.00 - 05.00</p>
                </div>

                <div class="bg-gradient-to-br from-purple-50 to-purple-100/50 dark:from-purple-900/20 dark:to-purple-800/10 rounded-xl p-5 border-2 border-purple-200 dark:border-purple-800 hover:shadow-lg hover:border-purple-300 dark:hover:border-purple-700 transition-all duration-300">
                    <div class="flex items-center justify-between mb-3">
                        <div class="p-2.5 bg-purple-200 dark:bg-purple-800 rounded-lg">
                            <Wrench class="w-6 h-6 text-purple-700 dark:text-purple-300" />
                        </div>
                        <div class="px-2.5 py-1 bg-purple-200 dark:bg-purple-800 rounded-full">
                            <span class="text-xs font-bold text-purple-800 dark:text-purple-200">🔧</span>
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-purple-900 dark:text-purple-300 mb-1">Sparepart</p>
                    <p class="text-3xl font-bold text-purple-700 dark:text-purple-400">{{ statistics.totalSpareparts }}</p>
                    <p class="text-xs text-purple-600 dark:text-purple-500 mt-1.5">Total digunakan</p>
                </div>

                <!-- Die Parts -->
                <div class="bg-gradient-to-br from-teal-50 to-teal-100/50 dark:from-teal-900/20 dark:to-teal-800/10 rounded-xl p-5 border-2 border-teal-200 dark:border-teal-800 hover:shadow-lg hover:border-teal-300 dark:hover:border-teal-700 transition-all duration-300">
                    <div class="flex items-center justify-between mb-3">
                        <div class="p-2.5 bg-teal-200 dark:bg-teal-800 rounded-lg">
                            <Activity class="w-6 h-6 text-teal-700 dark:text-teal-300" />
                        </div>
                        <div class="px-2.5 py-1 bg-teal-200 dark:bg-teal-800 rounded-full">
                            <span class="text-xs font-bold text-teal-800 dark:text-teal-200">⚙️</span>
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-teal-900 dark:text-teal-300 mb-1">Die Parts</p>
                    <p class="text-3xl font-bold text-teal-700 dark:text-teal-400">{{ statistics.activeDieParts }}</p>
                    <p class="text-xs text-teal-600 dark:text-teal-500 mt-1.5">Total aktif</p>
                </div>
            </div>
            <!-- Charts Section -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

                <!-- Trend Chart -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-750">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <div class="p-2 bg-blue-600 rounded-lg">
                                        <BarChart3 class="w-5 h-5 text-white" />
                                    </div>
                                    Perbaikan berdasarkan Shift
                                </h2>
                            </div>
                            <div class="flex items-center gap-4 text-xs">
                                <div class="flex items-center gap-2 px-3 py-1.5 bg-white dark:bg-gray-700 rounded-lg shadow-sm">
                                    <div class="w-3 h-3 bg-amber-500 rounded-sm"></div>
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">Shift 1</span>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-1.5 bg-white dark:bg-gray-700 rounded-lg shadow-sm">
                                    <div class="w-3 h-3 bg-indigo-500 rounded-sm"></div>
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">Shift 2</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        <div v-if="monthlyTrend.length > 0" class="relative">
                            <div class="flex gap-4">
                                <!-- Y-Axis -->
                                <div class="flex flex-col justify-between text-right pr-2 py-2" style="min-width: 36px;">
                                    <div v-for="label in yAxisLabels" :key="label" class="text-xs font-medium text-gray-600 dark:text-gray-400 leading-none">
                                        {{ label }}
                                    </div>
                                </div>

                                <!-- Chart Area -->
                                <div class="flex-1 relative">
                                    <!-- Grid Lines -->
                                    <div class="absolute inset-0 flex flex-col justify-between pointer-events-none">
                                        <div v-for="i in 5" :key="i" class="border-t border-gray-200 dark:border-gray-700"></div>
                                    </div>

                                    <!-- Bars -->
                                    <div class="relative h-72 flex items-end justify-between gap-1 px-2">
                                        <div
                                            v-for="(trend, index) in monthlyTrend"
                                            :key="index"
                                            class="flex-1 relative flex flex-col justify-end group"
                                            @mouseenter="hoveredBar = index"
                                            @mouseleave="hoveredBar = null"
                                        >
                                            <div class="relative w-full flex flex-col justify-end min-h-0">
                                                <template v-if="trend.shift1 > 0 || trend.shift2 > 0">
                                                    <!-- Shift 1 Bar -->
                                                    <div
                                                        v-if="trend.shift1 > 0"
                                                        class="w-full bg-gradient-to-t from-amber-500 to-amber-400 hover:from-amber-600 hover:to-amber-500 transition-all duration-200 rounded-t cursor-pointer shadow-md group-hover:shadow-lg"
                                                        :style="{
                                                            height: `${getStackedBarHeights(trend.shift1, trend.shift2).shift1Height}%`,
                                                            minHeight: '4px'
                                                        }"
                                                    ></div>

                                                    <!-- Shift 2 Bar -->
                                                    <div
                                                        v-if="trend.shift2 > 0"
                                                        class="w-full bg-gradient-to-t from-indigo-500 to-indigo-400 hover:from-indigo-600 hover:to-indigo-500 transition-all duration-200 cursor-pointer shadow-md group-hover:shadow-lg"
                                                        :class="{ 'rounded-t': trend.shift1 === 0 }"
                                                        :style="{
                                                            height: `${getStackedBarHeights(trend.shift1, trend.shift2).shift2Height}%`,
                                                            minHeight: '4px'
                                                        }"
                                                    ></div>
                                                </template>

                                                <!-- Tooltip -->
                                                <div
                                                    v-if="hoveredBar === index && (trend.shift1 > 0 || trend.shift2 > 0)"
                                                    class="absolute bottom-full left-1/2 -translate-x-1/2 mb-3 px-4 py-3 bg-gray-900 dark:bg-gray-700 text-white text-xs rounded-xl shadow-2xl whitespace-nowrap z-20 pointer-events-none border border-gray-700 dark:border-gray-600"
                                                >
                                                    <div class="font-bold mb-2 text-center border-b border-gray-700 pb-2">
                                                        {{ new Date(trend.date).getDate() }} {{ new Date(trend.date).toLocaleDateString('id-ID', { month: 'short' }) }}
                                                    </div>
                                                    <div class="space-y-1.5">
                                                        <div class="flex items-center justify-between gap-4" v-if="trend.shift1 > 0">
                                                            <div class="flex items-center gap-2">
                                                                <span class="w-2.5 h-2.5 bg-amber-400 rounded"></span>
                                                                <span class="text-amber-200">Shift 1</span>
                                                            </div>
                                                            <span class="font-bold">{{ trend.shift1 }}</span>
                                                        </div>
                                                        <div class="flex items-center justify-between gap-4" v-if="trend.shift2 > 0">
                                                            <div class="flex items-center gap-2">
                                                                <span class="w-2.5 h-2.5 bg-indigo-400 rounded"></span>
                                                                <span class="text-indigo-200">Shift 2</span>
                                                            </div>
                                                            <span class="font-bold">{{ trend.shift2 }}</span>
                                                        </div>
                                                        <div class="flex items-center justify-between gap-4 pt-2 mt-2 border-t border-gray-700">
                                                            <span class="font-bold">Total</span>
                                                            <span class="font-bold text-blue-300">{{ trend.shift1 + trend.shift2 }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900 dark:border-t-gray-700"></div>
                                                </div>
                                            </div>

                                            <!-- Date Label -->
                                            <div class="text-center mt-3 text-xs text-gray-600 dark:text-gray-400 font-semibold">
                                                {{ new Date(trend.date).getDate() }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- X-Axis Line -->
                                    <div class="border-t-2 border-gray-300 dark:border-gray-600 mt-2"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="h-72 flex flex-col items-center justify-center text-gray-400">
                            <div class="p-4 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
                                <BarChart3 class="w-12 h-12 opacity-40" />
                            </div>
                            <p class="text-sm font-semibold">Tidak ada data untuk bulan ini</p>
                            <p class="text-xs mt-1">Pilih bulan lain untuk melihat data</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-orange-50 to-red-50 dark:from-gray-800 dark:to-gray-750">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <div class="p-2 bg-orange-600 rounded-lg">
                                        <TrendingUp class="w-5 h-5 text-white" />
                                    </div>
                                    Top Die Parts
                                </h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1.5 ml-11">Paling sering diperbaiki</p>
                            </div>
                            <div class="px-3 py-1.5 bg-gradient-to-r from-orange-500 to-red-500 rounded-full shadow-lg">
                                <span class="text-xs font-bold text-white">Top 5</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        <div v-if="topDieParts.length > 0" class="space-y-3">
                            <div
                                v-for="(item, index) in topDieParts"
                                :key="index"
                                class="group relative bg-gradient-to-br from-white to-gray-50 dark:from-gray-700 dark:to-gray-750 hover:from-orange-50 hover:to-red-50 dark:hover:from-orange-900/10 dark:hover:to-red-900/10 rounded-xl p-4 transition-all duration-300 border-2 border-gray-200 dark:border-gray-600 hover:border-orange-300 dark:hover:border-orange-600 hover:shadow-lg cursor-pointer"
                            >
                                <div class="flex items-center gap-4">
                                    <!-- Rank Badge -->
                                    <div class="flex-shrink-0">
                                        <div
                                            class="w-12 h-12 rounded-xl flex items-center justify-center font-bold text-white shadow-lg text-lg relative overflow-hidden"
                                            :class="{
                                                'bg-gradient-to-br from-yellow-400 via-yellow-500 to-yellow-600': index === 0,
                                                'bg-gradient-to-br from-gray-300 via-gray-400 to-gray-500': index === 1,
                                                'bg-gradient-to-br from-orange-400 via-orange-500 to-orange-600': index === 2,
                                                'bg-gradient-to-br from-blue-400 via-blue-500 to-blue-600': index > 2
                                            }"
                                        >
                                            <span v-if="index === 0" class="text-2xl">🥇</span>
                                            <span v-else-if="index === 1" class="text-2xl">🥈</span>
                                            <span v-else-if="index === 2" class="text-2xl">🥉</span>
                                            <span v-else class="text-lg">{{ index + 1 }}</span>
                                            <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-20 transition-opacity"></div>
                                        </div>
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <p class="font-bold text-gray-900 dark:text-white truncate text-base">
                                                    {{ item.die_part.part_no }}
                                                </p>
                                                <p class="text-xs text-gray-600 dark:text-gray-400 truncate mt-1">
                                                    {{ item.die_part.part_name }}
                                                </p>
                                            </div>

                                            <!-- Count Badge -->
                                            <div class="flex-shrink-0 text-right">
                                                <div class="px-3 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg shadow-md">
                                                    <p class="text-xl font-bold leading-none">{{ item.report_count }}</p>
                                                </div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 font-medium">laporan</p>
                                            </div>
                                        </div>

                                        <!-- Progress Bar -->
                                        <div class="mt-3">
                                            <div class="h-2.5 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden shadow-inner">
                                                <div
                                                    class="h-full rounded-full transition-all duration-700 ease-out"
                                                    :class="{
                                                        'bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600': index === 0,
                                                        'bg-gradient-to-r from-gray-400 via-gray-500 to-gray-600': index === 1,
                                                        'bg-gradient-to-r from-orange-400 via-orange-500 to-orange-600': index === 2,
                                                        'bg-gradient-to-r from-blue-400 via-blue-500 to-blue-600': index > 2
                                                    }"
                                                    :style="{ width: `${(item.report_count / topDieParts[0].report_count) * 100}%` }"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="h-72 flex flex-col items-center justify-center text-gray-400">
                            <div class="p-4 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
                                <TrendingUp class="w-12 h-12 opacity-40" />
                            </div>
                            <p class="text-sm font-semibold">Belum ada data</p>
                            <p class="text-xs mt-1">Data akan muncul setelah ada laporan</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reports Table -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-750">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <div class="p-2 bg-blue-600 rounded-lg">
                                    <Clock class="w-5 h-5 text-white" />
                                </div>
                                Laporan Terbaru
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1.5 ml-11">10 laporan terakhir bulan ini</p>
                        </div>
                        <Link
                            href="/die-shop-reports"
                            class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 bg-white dark:bg-gray-700 hover:bg-blue-50 dark:hover:bg-gray-600 rounded-xl transition-all shadow-sm hover:shadow-md border border-blue-200 dark:border-gray-600"
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
                            <tr class="bg-gray-50 dark:bg-gray-700/50 border-b-2 border-gray-200 dark:border-gray-600">
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    No Laporan
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Tanggal
                                </th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Shift
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
                                class="hover:bg-blue-50 dark:hover:bg-gray-700/30 transition-colors"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-bold text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ report.report_no }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900 dark:text-gray-300 font-medium">
                                        {{ formatDate(report.report_date) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span :class="[
                                        'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold',
                                        getShiftBadge(report.shift)
                                    ]">
                                        <span v-if="report.shift === '1'">☀️</span>
                                        <span v-else>🌙</span>
                                        Shift {{ report.shift }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-xs">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white truncate">
                                            {{ report.die_part.part_no }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                            {{ report.die_part.part_name }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900 dark:text-gray-300 font-medium">
                                        {{ report.pic_name }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span :class="[
                                        'inline-flex px-3 py-1.5 rounded-full text-xs font-bold',
                                        getStatusBadge(report.status)
                                    ]">
                                        {{ getStatusLabel(report.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <Link
                                        :href="`/die-shop-reports/${report.id}`"
                                        class="inline-flex items-center justify-center p-2.5 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-lg transition-all"
                                        title="Lihat Detail"
                                    >
                                        <Eye class="w-5 h-5" />
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="recentReports.length === 0">
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center text-gray-400">
                                        <div class="p-4 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
                                            <Clock class="w-12 h-12 opacity-40" />
                                        </div>
                                        <p class="text-sm font-semibold">Belum ada laporan</p>
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
