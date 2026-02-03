<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import { TrendingUp, Download, BarChart3, ChevronLeft, ChevronRight, Activity, Zap, Target } from 'lucide-vue-next';
import Chart from 'chart.js/auto';

interface Line {
    id: number;
    line_code: string;
    line_name: string;
    plant: string;
    esp32_device?: { device_id: string };
    latest_oee_record?: {
        oee: number;
        availability: number;
        performance: number;
        quality: number;
        achievement_rate: number;
        period_date: string;
    };
}

interface OeeRecord {
    shift_label: any;
    id: number;
    line_id: number;
    period_type: string;
    period_date: string;
    period_start: string;
    period_end: string;
    operation_time_hours: number;
    uptime_hours: number;
    downtime_hours: number;
    total_counter_a: number;
    target_production: number;
    total_reject: number;
    good_count: number;
    avg_cycle_time: number;
    availability: number;
    performance: number;
    quality: number;
    achievement_rate: number;
    oee: number;
    total_failures: number;
    oee_status: string;
    oee_status_label: string;
}

interface LineStopData {
    labels: string[];
    data: number[];
}

interface Filters {
    line_id?: number;
    period_type: string;
    start_date: string;
    end_date: string;
    shift?: number;
}

interface Props {
    lines: Line[];
    selectedLine?: Line;
    oeeRecords: OeeRecord[];
    lineStopData: LineStopData;
    filters: Filters;
    shifts: Array<{ value: number; label: string }>;
}

const props = defineProps<Props>();

const lineStopChart = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;
const currentMetricIndex = ref(2);

const averageOee = computed(() => {
    if (props.oeeRecords.length === 0) return 0;
    const sum = props.oeeRecords.reduce((acc, record) => acc + Number(record.oee), 0);
    return parseFloat((sum / props.oeeRecords.length).toFixed(2));
});

const averageAvailability = computed(() => {
    if (props.oeeRecords.length === 0) return 0;
    const sum = props.oeeRecords.reduce((acc, record) => acc + Number(record.availability), 0);
    return parseFloat((sum / props.oeeRecords.length).toFixed(2));
});

const averagePerformance = computed(() => {
    if (props.oeeRecords.length === 0) return 0;
    const sum = props.oeeRecords.reduce((acc, record) => acc + Number(record.performance), 0);
    return parseFloat((sum / props.oeeRecords.length).toFixed(2));
});

const averageQuality = computed(() => {
    if (props.oeeRecords.length === 0) return 0;
    const sum = props.oeeRecords.reduce((acc, record) => acc + Number(record.quality), 0);
    return parseFloat((sum / props.oeeRecords.length).toFixed(2));
});

const averageAchievement = computed(() => {
    if (props.oeeRecords.length === 0) return 0;
    const sum = props.oeeRecords.reduce((acc, record) => acc + Number(record.achievement_rate), 0);
    return parseFloat((sum / props.oeeRecords.length).toFixed(2));
});

const metrics = computed(() => [
    { key: 'availability', label: 'Availability', subLabel: 'Time Availability', color: '#3b82f6', value: averageAvailability.value },
    { key: 'performance', label: 'Performance', subLabel: 'Speed & Efficiency', color: '#f59e0b', value: averagePerformance.value },
    { key: 'oee', label: 'OEE', subLabel: 'Overall Effectiveness', color: '#fbbf24', value: averageOee.value },
    { key: 'quality', label: 'Quality', subLabel: 'Product Quality', color: '#10b981', value: averageQuality.value },
    { key: 'achievement', label: 'Achievement', subLabel: 'Target Achievement', color: '#8b5cf6', value: averageAchievement.value }
]);

const filterForm = useForm({
    line_id: props.filters.line_id || null,
    period_type: props.filters.period_type || 'daily',
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
    shift: props.filters.shift || null
});

const nextSlide = () => {
    currentMetricIndex.value = currentMetricIndex.value < metrics.value.length - 1 ? currentMetricIndex.value + 1 : 0;
};

const prevSlide = () => {
    currentMetricIndex.value = currentMetricIndex.value > 0 ? currentMetricIndex.value - 1 : metrics.value.length - 1;
};

const goToSlide = (index: number) => {
    currentMetricIndex.value = index;
};

const formatNumber = (num: number) => {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const applyFilter = () => {
    router.get('/oee', {
        line_id: filterForm.line_id,
        period_type: filterForm.period_type,
        start_date: filterForm.start_date,
        end_date: filterForm.end_date,
        shift: filterForm.shift,
    }, { preserveState: false });
};

const resetFilter = () => {
    const today = new Date().toISOString().split('T')[0];
    const sevenDaysAgo = new Date();
    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
    filterForm.line_id = null;
    filterForm.period_type = 'daily';
    filterForm.start_date = sevenDaysAgo.toISOString().split('T')[0];
    filterForm.end_date = today;
    filterForm.shift = null;
};

const setQuickFilter = (days: number) => {
    const today = new Date();
    const pastDate = new Date();
    pastDate.setDate(pastDate.getDate() - days);
    filterForm.start_date = pastDate.toISOString().split('T')[0];
    filterForm.end_date = today.toISOString().split('T')[0];
};

const exportData = () => {
    const params = new URLSearchParams({
        line_id: filterForm.line_id?.toString() || '',
        period_type: filterForm.period_type,
        start_date: filterForm.start_date,
        end_date: filterForm.end_date
    });
    window.location.href = `/oee/export/data?${params.toString()}`;
};

const initLineStopChart = () => {
    if (!lineStopChart.value || !props.lineStopData.labels.length) return;
    if (chartInstance) chartInstance.destroy();
    const ctx = lineStopChart.value.getContext('2d');
    if (!ctx) return;
    chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: props.lineStopData.labels,
            datasets: [{
                label: 'Line Stop (count)',
                data: props.lineStopData.data,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true, position: 'top' }, tooltip: { mode: 'index', intersect: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
};

watch([() => filterForm.line_id, () => filterForm.period_type, () => filterForm.start_date, () => filterForm.end_date], () => {
    if (filterForm.line_id) applyFilter();
});

onMounted(() => {
    if (props.selectedLine && props.lineStopData.labels.length > 0) {
        setTimeout(initLineStopChart, 100);
    }
});

watch(() => props.lineStopData, () => {
    if (props.selectedLine && props.lineStopData.labels.length > 0) {
        setTimeout(initLineStopChart, 100);
    }
}, { deep: true });
</script>

<template>
    <Head title="OEE Dashboard" />
    <AppLayout :breadcrumbs="[{ title: 'OEE Dashboard', href: '/oee' }]">
        <div class="p-6 space-y-6 bg-white dark:bg-gray-900 min-h-screen">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-violet-600 to-purple-600 bg-clip-text text-transparent flex items-center gap-3">
                        <TrendingUp class="w-8 h-8 text-violet-600" />
                        OEE Dashboard
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Overall Equipment Effectiveness Monitoring</p>
                </div>
                <div class="flex gap-3">
                    <button @click="resetFilter" class="px-4 py-2.5 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all duration-300 font-medium">
                        Reset Filter
                    </button>
                    <button @click="exportData" :disabled="!filterForm.line_id || oeeRecords.length === 0" class="px-4 py-2.5 bg-gradient-to-r from-green-500 to-emerald-500 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <Download class="w-4 h-4" />
                        Export CSV
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border border-gray-100 dark:border-gray-700">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Line</label>
                        <select v-model="filterForm.line_id" class="w-full px-4 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-violet-500 focus:ring-2 focus:ring-violet-200 transition-all">
                            <option :value="null">Select Line</option>
                            <option v-for="line in lines" :key="line.id" :value="line.id">{{ line.line_name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Period Type</label>
                        <select v-model="filterForm.period_type" class="w-full px-4 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-violet-500 focus:ring-2 focus:ring-violet-200 transition-all">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Start Date</label>
                        <input v-model="filterForm.start_date" type="date" class="w-full px-4 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-violet-500 focus:ring-2 focus:ring-violet-200 transition-all" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">End Date</label>
                        <input v-model="filterForm.end_date" type="date" class="w-full px-4 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-violet-500 focus:ring-2 focus:ring-violet-200 transition-all" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Shift</label>
                        <select v-model="filterForm.shift" class="w-full px-4 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-violet-500 focus:ring-2 focus:ring-violet-200 transition-all">
                            <option :value="null">Semua Shift</option>
                            <option v-for="shift in shifts" :key="shift.value" :value="shift.value">{{ shift.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <span class="text-sm text-gray-600 dark:text-gray-400 self-center mr-2 font-medium">Quick Filter:</span>
                    <button @click="setQuickFilter(7)" class="px-4 py-2 text-sm rounded-xl bg-gradient-to-r from-blue-100 to-blue-50 dark:from-blue-900/30 dark:to-blue-800/30 hover:from-blue-200 hover:to-blue-100 dark:hover:from-blue-800/40 dark:hover:to-blue-700/40 transition-all duration-300 font-medium text-blue-700 dark:text-blue-300">
                        7 Days
                    </button>
                    <button @click="setQuickFilter(30)" class="px-4 py-2 text-sm rounded-xl bg-gradient-to-r from-purple-100 to-purple-50 dark:from-purple-900/30 dark:to-purple-800/30 hover:from-purple-200 hover:to-purple-100 dark:hover:from-purple-800/40 dark:hover:to-purple-700/40 transition-all duration-300 font-medium text-purple-700 dark:text-purple-300">
                        30 Days
                    </button>
                    <button @click="setQuickFilter(90)" class="px-4 py-2 text-sm rounded-xl bg-gradient-to-r from-indigo-100 to-indigo-50 dark:from-indigo-900/30 dark:to-indigo-800/30 hover:from-indigo-200 hover:to-indigo-100 dark:hover:from-indigo-800/40 dark:hover:to-indigo-700/40 transition-all duration-300 font-medium text-indigo-700 dark:text-indigo-300">
                        90 Days
                    </button>
                </div>
            </div>

            <div v-if="selectedLine && oeeRecords.length > 0" class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1">
                        <div class="relative">
                            <button @click="prevSlide" class="absolute left-0 top-1/2 -translate-y-1/2 z-10 bg-white dark:bg-gray-700 rounded-full p-2 shadow-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-all">
                                <ChevronLeft class="w-4 h-4" />
                            </button>
                            <button @click="nextSlide" class="absolute right-0 top-1/2 -translate-y-1/2 z-10 bg-white dark:bg-gray-700 rounded-full p-2 shadow-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-all">
                                <ChevronRight class="w-4 h-4" />
                            </button>

                            <div class="flex items-center justify-center overflow-hidden min-h-[280px] relative">
                                <Transition name="metric-slide" mode="out-in">
                                    <div :key="currentMetricIndex" class="w-full flex flex-col items-center">
                                        <div class="relative w-48 h-48">
                                            <svg class="w-full h-full transform -rotate-90">
                                                <circle cx="96" cy="96" r="86" stroke="currentColor" stroke-width="14" fill="none" class="text-gray-200 dark:text-gray-700" />
                                                <circle cx="96" cy="96" r="86" :stroke="metrics[currentMetricIndex].color" stroke-width="14" fill="none" stroke-dasharray="540.35" :stroke-dashoffset="540.35 - (540.35 * metrics[currentMetricIndex].value / 100)" class="transition-all duration-1000 ease-out" stroke-linecap="round" />
                                            </svg>
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <div class="text-center">
                                                    <div class="text-4xl font-black text-gray-900 dark:text-white">
                                                        {{ Math.round(metrics[currentMetricIndex].value) }}%
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-center mt-3">
                                            <h3 class="text-xl font-bold" :style="{ color: metrics[currentMetricIndex].color }">{{ metrics[currentMetricIndex].label }}</h3>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ metrics[currentMetricIndex].subLabel }}</p>
                                        </div>
                                    </div>
                                </Transition>
                            </div>

                            <div class="flex justify-center items-center gap-2 mt-4">
                                <button v-for="(metric, index) in metrics" :key="metric.key" @click="goToSlide(index)" :class="['h-2 rounded-full transition-all duration-300', currentMetricIndex === index ? 'w-8' : 'w-2']" :style="{ backgroundColor: currentMetricIndex === index ? metric.color : '#94a3b8' }"></button>
                            </div>
                        </div>
                    </div>

                    <!-- Stat Cards Grid -->
                    <div class="lg:col-span-2 grid grid-cols-2 gap-4">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl p-4 border border-blue-200 dark:border-blue-800">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs text-blue-700 dark:text-blue-300 font-semibold uppercase">Availability</p>
                                    <p class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-1">{{ formatNumber(averageAvailability) }}%</p>
                                </div>
                                <div class="p-2 bg-blue-200 dark:bg-blue-800 rounded-lg">
                                    <Activity class="w-5 h-5 text-blue-700 dark:text-blue-300" />
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/20 dark:to-orange-800/20 rounded-xl p-4 border border-orange-200 dark:border-orange-800">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs text-orange-700 dark:text-orange-300 font-semibold uppercase">Performance</p>
                                    <p class="text-2xl font-bold text-orange-900 dark:text-orange-100 mt-1">{{ formatNumber(averagePerformance) }}%</p>
                                </div>
                                <div class="p-2 bg-orange-200 dark:bg-orange-800 rounded-lg">
                                    <Zap class="w-5 h-5 text-orange-700 dark:text-orange-300" />
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl p-4 border border-green-200 dark:border-green-800">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs text-green-700 dark:text-green-300 font-semibold uppercase">Quality</p>
                                    <p class="text-2xl font-bold text-green-900 dark:text-green-100 mt-1">{{ formatNumber(averageQuality) }}%</p>
                                </div>
                                <div class="p-2 bg-green-200 dark:bg-green-800 rounded-lg">
                                    <BarChart3 class="w-5 h-5 text-green-700 dark:text-green-300" />
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-xl p-4 border border-purple-200 dark:border-purple-800">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs text-purple-700 dark:text-purple-300 font-semibold uppercase">Achievement</p>
                                    <p class="text-2xl font-bold text-purple-900 dark:text-purple-100 mt-1">{{ formatNumber(averageAchievement) }}%</p>
                                </div>
                                <div class="p-2 bg-purple-200 dark:bg-purple-800 rounded-lg">
                                    <Target class="w-5 h-5 text-purple-700 dark:text-purple-300" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="selectedLine && oeeRecords.length > 0" class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-bold text-lg flex items-center gap-2 text-gray-900 dark:text-white">
                        <BarChart3 class="w-5 h-5 text-violet-600" />
                        OEE Records - {{ selectedLine.line_name }}
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gradient-to-r from-violet-50 to-purple-50 dark:from-gray-700 dark:to-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Date</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Shift</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Stop</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Target</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Actual</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Quality</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Available</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Perfomance</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Achievement</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="record in oeeRecords" :key="record.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-3 py-2">
                                    <div class="text-xs font-semibold text-gray-900 dark:text-white">{{ formatDate(record.period_date) }}</div>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        {{ record.shift_label }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex items-center justify-center w-10 h-6 rounded text-xs font-bold bg-gradient-to-r from-red-100 to-rose-100 text-red-800 dark:from-red-900/30 dark:to-rose-900/30 dark:text-red-200">
                                        {{ record.total_failures }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center text-xs font-semibold text-gray-900 dark:text-white">
                                    {{ record.target_production.toLocaleString() }}
                                </td>
                                <td class="px-3 py-2 text-center text-xs font-semibold text-gray-900 dark:text-white">
                                    {{ record.total_counter_a.toLocaleString() }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">{{ formatNumber(record.quality) }}%</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">{{ formatNumber(record.availability) }}%</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">{{ formatNumber(record.performance) }}%</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-bold', record.achievement_rate >= 100 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : record.achievement_rate >= 80 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200']">
                                        {{ formatNumber(record.achievement_rate) }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gradient-to-r from-violet-100 to-purple-100 dark:from-gray-700 dark:to-gray-700 font-bold">
                            <tr>
                                <td class="px-3 py-2 text-xs text-gray-900 dark:text-white">Total</td>
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ oeeRecords.reduce((sum, r) => sum + r.total_failures, 0) }}</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ oeeRecords.reduce((sum, r) => sum + r.target_production, 0).toLocaleString() }}</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ oeeRecords.reduce((sum, r) => sum + r.total_counter_a, 0).toLocaleString() }}</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ averageQuality }}%</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ averageAvailability }}%</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ averagePerformance }}%</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ averageAchievement }}%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Empty States -->
            <div v-if="!selectedLine" class="bg-white dark:bg-gray-800 rounded-2xl p-12 text-center shadow-lg border border-gray-100 dark:border-gray-700">
                <BarChart3 class="w-20 h-20 mx-auto mb-4 text-gray-400 opacity-50" />
                <h3 class="text-xl font-bold text-gray-700 dark:text-gray-300 mb-2">Select a Line</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Please select a line from the filter above to view OEE data</p>
            </div>

            <div v-if="selectedLine && oeeRecords.length === 0" class="bg-white dark:bg-gray-800 rounded-2xl p-12 text-center shadow-lg border border-gray-100 dark:border-gray-700">
                <BarChart3 class="w-20 h-20 mx-auto mb-4 text-gray-400 opacity-50" />
                <h3 class="text-xl font-bold text-gray-700 dark:text-gray-300 mb-2">No OEE Data</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">No OEE records found for the selected period. Data will be calculated automatically when production data is available.</p>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.metric-slide-enter-active,
.metric-slide-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.metric-slide-enter-from {
    opacity: 0;
    transform: translateX(20px) scale(0.95);
}

.metric-slide-leave-to {
    opacity: 0;
    transform: translateX(-20px) scale(0.95);
}

.metric-slide-enter-to,
.metric-slide-leave-from {
    opacity: 1;
    transform: translateX(0) scale(1);
}
</style>
