<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch, onUnmounted, nextTick } from 'vue';
import { TrendingUp, Download, BarChart3, ChevronLeft, ChevronRight, Activity, Zap, Target, Maximize2, Minimize2, Sun, Moon, Clock, Calendar, TrendingDown, AlertCircle } from 'lucide-vue-next';
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
    shift_label: string;
    id: number;
    line_id: number;
    line_code?: string;
    line_name?: string;
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
}

interface LineStopData {
    labels: string[];
    data: number[];
}

interface Filters {
    line_id?: number;
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
const oeeTrendChart = ref<HTMLCanvasElement | null>(null);
const productionChart = ref<HTMLCanvasElement | null>(null);
const oeeTrendChartMain = ref<HTMLCanvasElement | null>(null);
const productionChartMain = ref<HTMLCanvasElement | null>(null);
let chartInstanceLineStop: Chart | null = null;
let chartInstanceOeeTrend: Chart | null = null;
let chartInstanceProduction: Chart | null = null;
let chartInstanceOeeTrendMain: Chart | null = null;
let chartInstanceProductionMain: Chart | null = null;
const currentMetricIndex = ref(2);
const isFullscreen = ref(false);
const fullscreenContainer = ref<HTMLElement | null>(null);
const currentTime = ref(new Date());

const isDarkModeFullscreen = ref(false);

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

const totalProduction = computed(() => {
    return props.oeeRecords.reduce((sum, r) => sum + r.total_counter_a, 0);
});

const totalTarget = computed(() => {
    return props.oeeRecords.reduce((sum, r) => sum + r.target_production, 0);
});

const totalReject = computed(() => {
    return props.oeeRecords.reduce((sum, r) => sum + r.total_reject, 0);
});

const totalDowntime = computed(() => {
    return props.oeeRecords.reduce((sum, r) => sum + r.downtime_hours, 0);
});

const totalFailures = computed(() => {
    return props.oeeRecords.reduce((sum, r) => sum + r.total_failures, 0);
});

const metrics = computed(() => [
    { key: 'availability', label: 'Availability', subLabel: 'Time Availability', color: '#3b82f6', value: averageAvailability.value },
    { key: 'performance', label: 'Performance', subLabel: 'Speed & Efficiency', color: '#f59e0b', value: averagePerformance.value },
    { key: 'oee', label: 'OEE', subLabel: 'Overall Effectiveness', color: '#ec4899', value: averageOee.value },
    { key: 'quality', label: 'Quality', subLabel: 'Product Quality', color: '#10b981', value: averageQuality.value },
    { key: 'achievement', label: 'Achievement', subLabel: 'Target Achievement', color: '#8b5cf6', value: averageAchievement.value }
]);

const filterForm = useForm({
    line_id: props.filters.line_id || null,
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
    shift: props.filters.shift || null
});

const toggleFullscreen = async () => {
    if (!document.fullscreenElement) {
        if (fullscreenContainer.value?.requestFullscreen) {
            await fullscreenContainer.value.requestFullscreen();
            isFullscreen.value = true;
            await nextTick();
            initAllCharts();
        }
    } else {
        if (document.exitFullscreen) {
            await document.exitFullscreen();
            isFullscreen.value = false;
            await nextTick();
        }
    }
};

const toggleDarkMode = () => {
    isDarkModeFullscreen.value = !isDarkModeFullscreen.value;
    nextTick(() => {
        initAllCharts();
    });
};

const handleFullscreenChange = () => {
    isFullscreen.value = !!document.fullscreenElement;
    if (!isFullscreen.value) {
        nextTick(() => {
            destroyAllCharts();
            initOeeTrendChartMain();
            initProductionChartMain();
        });
    }
};

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

const formatTime = (date: Date) => {
    return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const applyFilter = () => {
    router.get('/oee', {
        line_id: filterForm.line_id,
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
    filterForm.start_date = sevenDaysAgo.toISOString().split('T')[0];
    filterForm.end_date = today;
    filterForm.shift = null;
};

const setQuickFilter = (days: number) => {
    const today = new Date();
    const pastDate = new Date();

    if (days === 0) {
        filterForm.start_date = today.toISOString().split('T')[0];
        filterForm.end_date = today.toISOString().split('T')[0];
    } else {
        pastDate.setDate(pastDate.getDate() - days);
        filterForm.start_date = pastDate.toISOString().split('T')[0];
        filterForm.end_date = today.toISOString().split('T')[0];
    }
};

const exportData = () => {
    const params = new URLSearchParams({
        line_id: filterForm.line_id?.toString() || '',
        start_date: filterForm.start_date,
        end_date: filterForm.end_date,
        shift: filterForm.shift?.toString() || ''
    });
    window.location.href = `/oee/export/data?${params.toString()}`;
};

const getChartColors = (useFullscreenMode: boolean = false) => {
    const darkMode = useFullscreenMode ? isDarkModeFullscreen.value : false;
    if (!useFullscreenMode) {
        const htmlElement = document.documentElement;
        const isDark = htmlElement.classList.contains('dark');
        return {
            textColor: isDark ? '#e5e7eb' : '#374151',
            gridColor: isDark ? '#374151' : '#e5e7eb',
            tickColor: isDark ? '#9ca3af' : '#6b7280'
        };
    }
    return {
        textColor: darkMode ? '#e5e7eb' : '#374151',
        gridColor: darkMode ? '#374151' : '#e5e7eb',
        tickColor: darkMode ? '#9ca3af' : '#6b7280'
    };
};

const initLineStopChart = () => {
    if (!lineStopChart.value || !props.lineStopData.labels.length) return;
    if (chartInstanceLineStop) chartInstanceLineStop.destroy();
    const ctx = lineStopChart.value.getContext('2d');
    if (!ctx) return;

    const colors = getChartColors(true);

    chartInstanceLineStop = new Chart(ctx, {
        type: 'line',
        data: {
            labels: props.lineStopData.labels,
            datasets: [{
                label: 'Line Stop (count)',
                data: props.lineStopData.data,
                borderColor: 'rgb(239, 68, 68)',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { color: colors.textColor }
                },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, color: colors.tickColor },
                    grid: { color: colors.gridColor }
                },
                x: {
                    ticks: { color: colors.tickColor },
                    grid: { color: colors.gridColor }
                }
            }
        }
    });
};

const initOeeTrendChart = () => {
    if (!oeeTrendChart.value || props.oeeRecords.length === 0) return;
    if (chartInstanceOeeTrend) chartInstanceOeeTrend.destroy();
    const ctx = oeeTrendChart.value.getContext('2d');
    if (!ctx) return;

    const colors = getChartColors(true);
    const sortedRecords = [...props.oeeRecords].sort((a, b) =>
        new Date(a.period_date).getTime() - new Date(b.period_date).getTime()
    );

    chartInstanceOeeTrend = new Chart(ctx, {
        type: 'line',
        data: {
            labels: sortedRecords.map(r => formatDate(r.period_date)),
            datasets: [
                {
                    label: 'OEE',
                    data: sortedRecords.map(r => r.oee),
                    borderColor: 'rgb(236, 72, 153)',
                    backgroundColor: 'rgba(236, 72, 153, 0.1)',
                    tension: 0.4,
                    fill: true,
                },
                {
                    label: 'Availability',
                    data: sortedRecords.map(r => r.availability),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                },
                {
                    label: 'Performance',
                    data: sortedRecords.map(r => r.performance),
                    borderColor: 'rgb(245, 158, 11)',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4,
                    fill: true,
                },
                {
                    label: 'Quality',
                    data: sortedRecords.map(r => r.quality),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { color: colors.textColor }
                },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { color: colors.tickColor },
                    grid: { color: colors.gridColor }
                },
                x: {
                    ticks: { color: colors.tickColor },
                    grid: { color: colors.gridColor }
                }
            }
        }
    });
};

const initOeeTrendChartMain = () => {
    if (!oeeTrendChartMain.value || props.oeeRecords.length === 0) return;
    if (chartInstanceOeeTrendMain) chartInstanceOeeTrendMain.destroy();
    const ctx = oeeTrendChartMain.value.getContext('2d');
    if (!ctx) return;

    const colors = getChartColors(false);
    const sortedRecords = [...props.oeeRecords].sort((a, b) =>
        new Date(a.period_date).getTime() - new Date(b.period_date).getTime()
    );

    chartInstanceOeeTrendMain = new Chart(ctx, {
        type: 'line',
        data: {
            labels: sortedRecords.map(r => formatDate(r.period_date)),
            datasets: [
                {
                    label: 'OEE',
                    data: sortedRecords.map(r => r.oee),
                    borderColor: 'rgb(236, 72, 153)',
                    backgroundColor: 'rgba(236, 72, 153, 0.1)',
                    tension: 0.4,
                    fill: true,
                },
                {
                    label: 'Availability',
                    data: sortedRecords.map(r => r.availability),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                },
                {
                    label: 'Performance',
                    data: sortedRecords.map(r => r.performance),
                    borderColor: 'rgb(245, 158, 11)',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4,
                    fill: true,
                },
                {
                    label: 'Quality',
                    data: sortedRecords.map(r => r.quality),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { color: colors.textColor }
                },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { color: colors.tickColor },
                    grid: { color: colors.gridColor }
                },
                x: {
                    ticks: { color: colors.tickColor },
                    grid: { color: colors.gridColor }
                }
            }
        }
    });
};

const initProductionChart = () => {
    if (!productionChart.value || props.oeeRecords.length === 0) return;
    if (chartInstanceProduction) chartInstanceProduction.destroy();
    const ctx = productionChart.value.getContext('2d');
    if (!ctx) return;

    const colors = getChartColors(true);
    const sortedRecords = [...props.oeeRecords].sort((a, b) =>
        new Date(a.period_date).getTime() - new Date(b.period_date).getTime()
    );

    chartInstanceProduction = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: sortedRecords.map(r => formatDate(r.period_date)),
            datasets: [
                {
                    label: 'Target Production',
                    data: sortedRecords.map(r => r.target_production),
                    backgroundColor: 'rgba(156, 163, 175, 0.5)',
                    borderColor: 'rgb(156, 163, 175)',
                    borderWidth: 1,
                },
                {
                    label: 'Actual Production',
                    data: sortedRecords.map(r => r.total_counter_a),
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1,
                },
                {
                    label: 'Good Count',
                    data: sortedRecords.map(r => r.good_count),
                    backgroundColor: 'rgba(16, 185, 129, 0.5)',
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 1,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { color: colors.textColor }
                },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: colors.tickColor },
                    grid: { color: colors.gridColor }
                },
                x: {
                    ticks: { color: colors.tickColor },
                    grid: { color: colors.gridColor }
                }
            }
        }
    });
};

const initProductionChartMain = () => {
    if (!productionChartMain.value || props.oeeRecords.length === 0) return;
    if (chartInstanceProductionMain) chartInstanceProductionMain.destroy();
    const ctx = productionChartMain.value.getContext('2d');
    if (!ctx) return;

    const colors = getChartColors(false);
    const sortedRecords = [...props.oeeRecords].sort((a, b) =>
        new Date(a.period_date).getTime() - new Date(b.period_date).getTime()
    );

    chartInstanceProductionMain = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: sortedRecords.map(r => formatDate(r.period_date)),
            datasets: [
                {
                    label: 'Target Production',
                    data: sortedRecords.map(r => r.target_production),
                    backgroundColor: 'rgba(156, 163, 175, 0.5)',
                    borderColor: 'rgb(156, 163, 175)',
                    borderWidth: 1,
                },
                {
                    label: 'Actual Production',
                    data: sortedRecords.map(r => r.total_counter_a),
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1,
                },
                {
                    label: 'Good Count',
                    data: sortedRecords.map(r => r.good_count),
                    backgroundColor: 'rgba(16, 185, 129, 0.5)',
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 1,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { color: colors.textColor }
                },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: colors.tickColor },
                    grid: { color: colors.gridColor }
                },
                x: {
                    ticks: { color: colors.tickColor },
                    grid: { color: colors.gridColor }
                }
            }
        }
    });
};

const initAllCharts = () => {
    nextTick(() => {
        initLineStopChart();
        initOeeTrendChart();
        initProductionChart();
    });
};

const destroyAllCharts = () => {
    if (chartInstanceLineStop) {
        chartInstanceLineStop.destroy();
        chartInstanceLineStop = null;
    }
    if (chartInstanceOeeTrend) {
        chartInstanceOeeTrend.destroy();
        chartInstanceOeeTrend = null;
    }
    if (chartInstanceProduction) {
        chartInstanceProduction.destroy();
        chartInstanceProduction = null;
    }
};

watch([() => filterForm.line_id, () => filterForm.start_date, () => filterForm.end_date, () => filterForm.shift], () => {
    applyFilter();
});

watch(() => isFullscreen.value, (newVal) => {
    if (newVal) {
        nextTick(() => {
            initAllCharts();
        });
    }
});

onMounted(() => {
    document.addEventListener('fullscreenchange', handleFullscreenChange);

    setInterval(() => {
        currentTime.value = new Date();
    }, 1000);

    nextTick(() => {
        if (!isFullscreen.value) {
            initOeeTrendChartMain();
            initProductionChartMain();
        }
    });

    const htmlElement = document.documentElement;
    const observer = new MutationObserver(() => {
        if (!isFullscreen.value) {
            nextTick(() => {
                if (chartInstanceOeeTrendMain) {
                    chartInstanceOeeTrendMain.destroy();
                    chartInstanceOeeTrendMain = null;
                }
                if (chartInstanceProductionMain) {
                    chartInstanceProductionMain.destroy();
                    chartInstanceProductionMain = null;
                }
                initOeeTrendChartMain();
                initProductionChartMain();
            });
        }
    });

    observer.observe(htmlElement, {
        attributes: true,
        attributeFilter: ['class']
    });

    onUnmounted(() => {
        observer.disconnect();
    });
});

onUnmounted(() => {
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    destroyAllCharts();
    if (chartInstanceOeeTrendMain) {
        chartInstanceOeeTrendMain.destroy();
        chartInstanceOeeTrendMain = null;
    }
    if (chartInstanceProductionMain) {
        chartInstanceProductionMain.destroy();
        chartInstanceProductionMain = null;
    }
});

watch(() => props.oeeRecords, () => {
    nextTick(() => {
        if (isFullscreen.value) {
            initAllCharts();
        } else {
            initOeeTrendChartMain();
            initProductionChartMain();
        }
    });
}, { deep: true });

watch(() => isDarkModeFullscreen.value, () => {
    if (isFullscreen.value) {
        nextTick(() => {
            initAllCharts();
        });
    }
});
</script>
<template>
    <Head title="OEE Dashboard" />
    <AppLayout :breadcrumbs="[{ title: 'OEE Dashboard', href: '/oee' }]">
        <div ref="fullscreenContainer" :class="['transition-all duration-300', isFullscreen ? (isDarkModeFullscreen ? 'fixed inset-0 z-50 bg-gradient-to-br from-gray-900 via-slate-900 to-gray-900 overflow-auto' : 'fixed inset-0 z-50 bg-gradient-to-br from-gray-50 via-white to-gray-100 overflow-auto') : 'p-6 space-y-6 bg-white dark:bg-gray-900 min-h-screen']">

            <div :class="['flex flex-col md:flex-row justify-between items-start md:items-center gap-4', isFullscreen ? 'p-6 pb-4' : '']">
                <div>
                    <h1 :class="['font-bold bg-gradient-to-r from-violet-600 to-purple-600 bg-clip-text text-transparent flex items-center gap-3', isFullscreen ? 'text-4xl' : 'text-3xl']">
                        <TrendingUp :class="['text-violet-600', isFullscreen ? 'w-10 h-10' : 'w-8 h-8']" />
                        OEE Dashboard
                    </h1>
                    <p :class="['text-gray-600 mt-1', isFullscreen && (isDarkModeFullscreen ? 'text-gray-300' : 'text-gray-600'), isFullscreen ? 'text-base' : 'text-sm']">Overall Equipment Effectiveness Monitoring</p>
                </div>
                <div class="flex gap-3 items-center">
                    <div v-if="isFullscreen" :class="['flex items-center gap-3 px-4 py-2 rounded-xl', isDarkModeFullscreen ? 'bg-gray-800 text-gray-200' : 'bg-white text-gray-800 shadow-md']">
                        <Clock class="w-5 h-5" />
                        <span class="font-mono font-semibold">{{ formatTime(currentTime) }}</span>
                    </div>
                    <button v-if="isFullscreen" @click="toggleDarkMode" :class="['px-4 py-2.5 rounded-xl transition-all duration-300 font-medium flex items-center gap-2', isDarkModeFullscreen ? 'bg-yellow-500 hover:bg-yellow-600 text-gray-900' : 'bg-gray-800 hover:bg-gray-900 text-white']">
                        <Sun v-if="isDarkModeFullscreen" class="w-4 h-4" />
                        <Moon v-else class="w-4 h-4" />
                    </button>
                    <button @click="toggleFullscreen" :class="['px-4 py-2.5 rounded-xl transition-all duration-300 font-medium flex items-center gap-2', isFullscreen ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-violet-600 hover:bg-violet-700 text-white']">
                        <Maximize2 v-if="!isFullscreen" class="w-4 h-4" />
                        <Minimize2 v-else class="w-4 h-4" />
                        {{ isFullscreen ? 'Exit Fullscreen' : 'Fullscreen' }}
                    </button>
                    <button v-if="!isFullscreen" @click="resetFilter" class="px-4 py-2.5 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all duration-300 font-medium">
                        Reset Filter
                    </button>
                    <button v-if="!isFullscreen" @click="exportData" :disabled="oeeRecords.length === 0" class="px-4 py-2.5 bg-gradient-to-r from-green-500 to-emerald-500 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <Download class="w-4 h-4" />
                        Export CSV
                    </button>
                </div>
            </div>

            <div v-if="!isFullscreen" class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border border-gray-100 dark:border-gray-700">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Line</label>
                        <select v-model="filterForm.line_id" class="w-full px-4 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-violet-500 focus:ring-2 focus:ring-violet-200 transition-all">
                            <option :value="null">All Lines</option>
                            <option v-for="line in lines" :key="line.id" :value="line.id">{{ line.line_name }}</option>
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
                    <button @click="setQuickFilter(0)" class="px-4 py-2 text-sm rounded-xl bg-gradient-to-r from-green-100 to-green-50 dark:from-green-900/30 dark:to-green-800/30 hover:from-green-200 hover:to-green-100 dark:hover:from-green-800/40 dark:hover:to-green-700/40 transition-all duration-300 font-medium text-green-700 dark:text-green-300">
                        Today
                    </button>
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

            <div v-if="oeeRecords.length > 0" :class="['rounded-2xl shadow-lg border', isFullscreen ? (isDarkModeFullscreen ? 'mx-6 bg-gray-800/50 backdrop-blur-lg border-gray-700 p-8' : 'mx-6 bg-white/80 backdrop-blur-lg border-gray-200 p-8') : 'bg-white dark:bg-gray-800 border-gray-100 dark:border-gray-700 p-6']">
                <div :class="['grid gap-6', isFullscreen ? 'grid-cols-1 lg:grid-cols-5' : 'grid-cols-1 lg:grid-cols-3']">
                    <div :class="[isFullscreen ? 'lg:col-span-1' : 'lg:col-span-1']">
                        <div class="relative">
                            <button @click="prevSlide" :class="['absolute left-0 top-1/2 -translate-y-1/2 z-10 rounded-full p-2 shadow-lg transition-all', isDarkModeFullscreen && isFullscreen ? 'bg-gray-700 hover:bg-gray-600' : 'bg-white hover:bg-gray-50 dark:bg-gray-700 dark:hover:bg-gray-600', isFullscreen && 'p-3']">
                                <ChevronLeft :class="[isFullscreen ? 'w-6 h-6' : 'w-4 h-4']" />
                            </button>
                            <button @click="nextSlide" :class="['absolute right-0 top-1/2 -translate-y-1/2 z-10 rounded-full p-2 shadow-lg transition-all', isDarkModeFullscreen && isFullscreen ? 'bg-gray-700 hover:bg-gray-600' : 'bg-white hover:bg-gray-50 dark:bg-gray-700 dark:hover:bg-gray-600', isFullscreen && 'p-3']">
                                <ChevronRight :class="[isFullscreen ? 'w-6 h-6' : 'w-4 h-4']" />
                            </button>

                            <div :class="['flex items-center justify-center overflow-hidden relative', isFullscreen ? 'min-h-[350px]' : 'min-h-[280px]']">
                                <Transition name="metric-slide" mode="out-in">
                                    <div :key="currentMetricIndex" class="w-full flex flex-col items-center">
                                        <div :class="['relative', isFullscreen ? 'w-56 h-56' : 'w-48 h-48']">
                                            <svg class="w-full h-full transform -rotate-90">
                                                <circle :cx="isFullscreen ? 112 : 96" :cy="isFullscreen ? 112 : 96" :r="isFullscreen ? 98 : 86" stroke="currentColor" :stroke-width="isFullscreen ? 16 : 14" fill="none" :class="isDarkModeFullscreen && isFullscreen ? 'text-gray-700' : 'text-gray-200 dark:text-gray-700'" />
                                                <circle :cx="isFullscreen ? 112 : 96" :cy="isFullscreen ? 112 : 96" :r="isFullscreen ? 98 : 86" :stroke="metrics[currentMetricIndex].color" :stroke-width="isFullscreen ? 16 : 14" fill="none" :stroke-dasharray="isFullscreen ? 615.75 : 540.35" :stroke-dashoffset="isFullscreen ? (615.75 - (615.75 * metrics[currentMetricIndex].value / 100)) : (540.35 - (540.35 * metrics[currentMetricIndex].value / 100))" class="transition-all duration-1000 ease-out" stroke-linecap="round" />
                                            </svg>
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <div class="text-center">
                                                    <div :class="['font-black', (isDarkModeFullscreen && isFullscreen) ? 'text-white' : 'text-gray-900 dark:text-white', isFullscreen ? 'text-5xl' : 'text-4xl']">
                                                        {{ Math.round(metrics[currentMetricIndex].value) }}%
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-center mt-3">
                                            <h3 :class="['font-bold', isFullscreen ? 'text-2xl' : 'text-xl']" :style="{ color: metrics[currentMetricIndex].color }">{{ metrics[currentMetricIndex].label }}</h3>
                                            <p :class="['mt-1', (isDarkModeFullscreen && isFullscreen) ? 'text-gray-400' : 'text-gray-600 dark:text-gray-400', isFullscreen ? 'text-sm' : 'text-xs']">{{ metrics[currentMetricIndex].subLabel }}</p>
                                        </div>
                                    </div>
                                </Transition>
                            </div>

                            <div class="flex justify-center items-center gap-2 mt-4">
                                <button v-for="(metric, index) in metrics" :key="metric.key" @click="goToSlide(index)" :class="['rounded-full transition-all duration-300', currentMetricIndex === index ? (isFullscreen ? 'w-10 h-2.5' : 'w-8 h-2') : (isFullscreen ? 'w-2.5 h-2.5' : 'w-2 h-2')]" :style="{ backgroundColor: currentMetricIndex === index ? metric.color : '#94a3b8' }"></button>
                            </div>
                        </div>
                    </div>

                    <div :class="['grid grid-cols-2 gap-4', isFullscreen ? 'lg:col-span-2' : 'lg:col-span-2']">
                        <div :class="['rounded-xl border', (isDarkModeFullscreen && isFullscreen) ? 'bg-gradient-to-br from-blue-900/30 to-blue-800/30 border-blue-800' : 'bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200 dark:from-blue-900/30 dark:to-blue-800/30 dark:border-blue-800', isFullscreen ? 'p-6' : 'p-4']">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p :class="['font-semibold uppercase', (isDarkModeFullscreen && isFullscreen) ? 'text-blue-300' : 'text-blue-700 dark:text-blue-300', isFullscreen ? 'text-xs' : 'text-xs']">Availability</p>
                                    <p :class="['font-bold mt-1', (isDarkModeFullscreen && isFullscreen) ? 'text-blue-100' : 'text-blue-900 dark:text-blue-100', isFullscreen ? 'text-3xl' : 'text-2xl']">{{ formatNumber(averageAvailability) }}%</p>
                                </div>
                                <div :class="['rounded-lg', (isDarkModeFullscreen && isFullscreen) ? 'bg-blue-800' : 'bg-blue-200 dark:bg-blue-800', isFullscreen ? 'p-3' : 'p-2']">
                                    <Activity :class="[(isDarkModeFullscreen && isFullscreen) ? 'text-blue-300' : 'text-blue-700 dark:text-blue-300', isFullscreen ? 'w-6 h-6' : 'w-5 h-5']" />
                                </div>
                            </div>
                        </div>

                        <div :class="['rounded-xl border', (isDarkModeFullscreen && isFullscreen) ? 'bg-gradient-to-br from-orange-900/30 to-orange-800/30 border-orange-800' : 'bg-gradient-to-br from-orange-50 to-orange-100 border-orange-200 dark:from-orange-900/30 dark:to-orange-800/30 dark:border-orange-800', isFullscreen ? 'p-6' : 'p-4']">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p :class="['font-semibold uppercase', (isDarkModeFullscreen && isFullscreen) ? 'text-orange-300' : 'text-orange-700 dark:text-orange-300', isFullscreen ? 'text-xs' : 'text-xs']">Performance</p>
                                    <p :class="['font-bold mt-1', (isDarkModeFullscreen && isFullscreen) ? 'text-orange-100' : 'text-orange-900 dark:text-orange-100', isFullscreen ? 'text-3xl' : 'text-2xl']">{{ formatNumber(averagePerformance) }}%</p>
                                </div>
                                <div :class="['rounded-lg', (isDarkModeFullscreen && isFullscreen) ? 'bg-orange-800' : 'bg-orange-200 dark:bg-orange-800', isFullscreen ? 'p-3' : 'p-2']">
                                    <Zap :class="[(isDarkModeFullscreen && isFullscreen) ? 'text-orange-300' : 'text-orange-700 dark:text-orange-300', isFullscreen ? 'w-6 h-6' : 'w-5 h-5']" />
                                </div>
                            </div>
                        </div>

                        <div :class="['rounded-xl border', (isDarkModeFullscreen && isFullscreen) ? 'bg-gradient-to-br from-green-900/30 to-green-800/30 border-green-800' : 'bg-gradient-to-br from-green-50 to-green-100 border-green-200 dark:from-green-900/30 dark:to-green-800/30 dark:border-green-800', isFullscreen ? 'p-6' : 'p-4']">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p :class="['font-semibold uppercase', (isDarkModeFullscreen && isFullscreen) ? 'text-green-300' : 'text-green-700 dark:text-green-300', isFullscreen ? 'text-xs' : 'text-xs']">Quality</p>
                                    <p :class="['font-bold mt-1', (isDarkModeFullscreen && isFullscreen) ? 'text-green-100' : 'text-green-900 dark:text-green-100', isFullscreen ? 'text-3xl' : 'text-2xl']">{{ formatNumber(averageQuality) }}%</p>
                                </div>
                                <div :class="['rounded-lg', (isDarkModeFullscreen && isFullscreen) ? 'bg-green-800' : 'bg-green-200 dark:bg-green-800', isFullscreen ? 'p-3' : 'p-2']">
                                    <BarChart3 :class="[(isDarkModeFullscreen && isFullscreen) ? 'text-green-300' : 'text-green-700 dark:text-green-300', isFullscreen ? 'w-6 h-6' : 'w-5 h-5']" />
                                </div>
                            </div>
                        </div>

                        <div :class="['rounded-xl border', (isDarkModeFullscreen && isFullscreen) ? 'bg-gradient-to-br from-purple-900/30 to-purple-800/30 border-purple-800' : 'bg-gradient-to-br from-purple-50 to-purple-100 border-purple-200 dark:from-purple-900/30 dark:to-purple-800/30 dark:border-purple-800', isFullscreen ? 'p-6' : 'p-4']">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p :class="['font-semibold uppercase', (isDarkModeFullscreen && isFullscreen) ? 'text-purple-300' : 'text-purple-700 dark:text-purple-300', isFullscreen ? 'text-xs' : 'text-xs']">Achievement</p>
                                    <p :class="['font-bold mt-1', (isDarkModeFullscreen && isFullscreen) ? 'text-purple-100' : 'text-purple-900 dark:text-purple-100', isFullscreen ? 'text-3xl' : 'text-2xl']">{{ formatNumber(averageAchievement) }}%</p>
                                </div>
                                <div :class="['rounded-lg', (isDarkModeFullscreen && isFullscreen) ? 'bg-purple-800' : 'bg-purple-200 dark:bg-purple-800', isFullscreen ? 'p-3' : 'p-2']">
                                    <Target :class="[(isDarkModeFullscreen && isFullscreen) ? 'text-purple-300' : 'text-purple-700 dark:text-purple-300', isFullscreen ? 'w-6 h-6' : 'w-5 h-5']" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="isFullscreen" class="lg:col-span-2 grid grid-cols-2 gap-4">
                        <div :class="['rounded-xl border p-4', isDarkModeFullscreen ? 'bg-gradient-to-br from-cyan-900/30 to-cyan-800/30 border-cyan-800' : 'bg-gradient-to-br from-cyan-50 to-cyan-100 border-cyan-200']">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p :class="['text-xs font-semibold uppercase', isDarkModeFullscreen ? 'text-cyan-300' : 'text-cyan-700']">Total Production</p>
                                    <p :class="['text-2xl font-bold mt-1', isDarkModeFullscreen ? 'text-cyan-100' : 'text-cyan-900']">{{ totalProduction.toLocaleString() }}</p>
                                    <p :class="['text-xs mt-1', isDarkModeFullscreen ? 'text-cyan-400' : 'text-cyan-600']">Target: {{ totalTarget.toLocaleString() }}</p>
                                </div>
                                <div :class="['p-3 rounded-lg', isDarkModeFullscreen ? 'bg-cyan-800' : 'bg-cyan-200']">
                                    <TrendingUp :class="['w-6 h-6', isDarkModeFullscreen ? 'text-cyan-300' : 'text-cyan-700']" />
                                </div>
                            </div>
                        </div>

                        <div :class="['rounded-xl border p-4', isDarkModeFullscreen ? 'bg-gradient-to-br from-red-900/30 to-red-800/30 border-red-800' : 'bg-gradient-to-br from-red-50 to-red-100 border-red-200']">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p :class="['text-xs font-semibold uppercase', isDarkModeFullscreen ? 'text-red-300' : 'text-red-700']">Total Reject</p>
                                    <p :class="['text-2xl font-bold mt-1', isDarkModeFullscreen ? 'text-red-100' : 'text-red-900']">{{ totalReject.toLocaleString() }}</p>
                                    <p :class="['text-xs mt-1', isDarkModeFullscreen ? 'text-red-400' : 'text-red-600']">{{ ((totalReject / totalProduction) * 100).toFixed(2) }}% of total</p>
                                </div>
                                <div :class="['p-3 rounded-lg', isDarkModeFullscreen ? 'bg-red-800' : 'bg-red-200']">
                                    <AlertCircle :class="['w-6 h-6', isDarkModeFullscreen ? 'text-red-300' : 'text-red-700']" />
                                </div>
                            </div>
                        </div>

                        <div :class="['rounded-xl border p-4', isDarkModeFullscreen ? 'bg-gradient-to-br from-amber-900/30 to-amber-800/30 border-amber-800' : 'bg-gradient-to-br from-amber-50 to-amber-100 border-amber-200']">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p :class="['text-xs font-semibold uppercase', isDarkModeFullscreen ? 'text-amber-300' : 'text-amber-700']">Total Downtime</p>
                                    <p :class="['text-2xl font-bold mt-1', isDarkModeFullscreen ? 'text-amber-100' : 'text-amber-900']">{{ formatNumber(totalDowntime) }}h</p>
                                    <p :class="['text-xs mt-1', isDarkModeFullscreen ? 'text-amber-400' : 'text-amber-600']">{{ totalFailures }} failures</p>
                                </div>
                                <div :class="['p-3 rounded-lg', isDarkModeFullscreen ? 'bg-amber-800' : 'bg-amber-200']">
                                    <TrendingDown :class="['w-6 h-6', isDarkModeFullscreen ? 'text-amber-300' : 'text-amber-700']" />
                                </div>
                            </div>
                        </div>

                        <div :class="['rounded-xl border p-4', isDarkModeFullscreen ? 'bg-gradient-to-br from-indigo-900/30 to-indigo-800/30 border-indigo-800' : 'bg-gradient-to-br from-indigo-50 to-indigo-100 border-indigo-200']">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p :class="['text-xs font-semibold uppercase', isDarkModeFullscreen ? 'text-indigo-300' : 'text-indigo-700']">Period</p>
                                    <p :class="['text-lg font-bold mt-1', isDarkModeFullscreen ? 'text-indigo-100' : 'text-indigo-900']">{{ formatDate(filterForm.start_date) }}</p>
                                    <p :class="['text-xs mt-1', isDarkModeFullscreen ? 'text-indigo-400' : 'text-indigo-600']">to {{ formatDate(filterForm.end_date) }}</p>
                                </div>
                                <div :class="['p-3 rounded-lg', isDarkModeFullscreen ? 'bg-indigo-800' : 'bg-indigo-200']">
                                    <Calendar :class="['w-6 h-6', isDarkModeFullscreen ? 'text-indigo-300' : 'text-indigo-700']" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="oeeRecords.length > 0 && isFullscreen" class="px-6 pb-6 grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
                <div :class="['rounded-2xl shadow-lg border p-5', isDarkModeFullscreen ? 'bg-gray-800/50 backdrop-blur-lg border-gray-700' : 'bg-white/80 backdrop-blur-lg border-gray-200']">
                    <h3 :class="['font-bold text-lg flex items-center gap-2 mb-4', isDarkModeFullscreen ? 'text-white' : 'text-gray-900']">
                        <TrendingUp class="w-5 h-5 text-amber-500" />
                        OEE Progress
                    </h3>
                    <div class="h-64">
                        <canvas ref="oeeTrendChart"></canvas>
                    </div>
                </div>

                <div :class="['rounded-2xl shadow-lg border p-5', isDarkModeFullscreen ? 'bg-gray-800/50 backdrop-blur-lg border-gray-700' : 'bg-white/80 backdrop-blur-lg border-gray-200']">
                    <h3 :class="['font-bold text-lg flex items-center gap-2 mb-4', isDarkModeFullscreen ? 'text-white' : 'text-gray-900']">
                        <BarChart3 class="w-5 h-5 text-blue-500" />
                        Production Analysis
                    </h3>
                    <div class="h-64">
                        <canvas ref="productionChart"></canvas>
                    </div>
                </div>

                <div :class="['rounded-2xl shadow-lg border p-5', isDarkModeFullscreen ? 'bg-gray-800/50 backdrop-blur-lg border-gray-700' : 'bg-white/80 backdrop-blur-lg border-gray-200']">
                    <h3 :class="['font-bold text-lg flex items-center gap-2 mb-4', isDarkModeFullscreen ? 'text-white' : 'text-gray-900']">
                        <AlertCircle class="w-5 h-5 text-red-500" />
                        Line Stop Overview
                    </h3>
                    <div class="h-64">
                        <canvas ref="lineStopChart"></canvas>
                    </div>
                </div>
            </div>

            <div v-if="oeeRecords.length > 0 && !isFullscreen" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-lg flex items-center gap-2 text-gray-900 dark:text-white">
                            <TrendingUp class="w-5 h-5 text-amber-500" />
                            OEE Progress
                        </h3>
                    </div>
                    <div class="p-5">
                        <div class="h-80">
                            <canvas ref="oeeTrendChartMain"></canvas>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-lg flex items-center gap-2 text-gray-900 dark:text-white">
                            <BarChart3 class="w-5 h-5 text-blue-500" />
                            Production Analysis
                        </h3>
                    </div>
                    <div class="p-5">
                        <div class="h-80">
                            <canvas ref="productionChartMain"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="oeeRecords.length > 0 && !isFullscreen" class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-bold text-lg flex items-center gap-2 text-gray-900 dark:text-white">
                        <BarChart3 class="w-5 h-5 text-violet-600" />
                        OEE Records{{ selectedLine ? ' - ' + selectedLine.line_name : ' - All Lines' }}
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gradient-to-r from-violet-50 to-purple-50 dark:from-gray-700 dark:to-gray-700">
                            <tr>
                                <th v-if="!selectedLine" class="px-3 py-2 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Line</th>
                                <th class="px-3 py-2 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Date</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Shift</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Operation Time (h)</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Uptime (h)</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Downtime (h)</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Stop</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Target</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Actual</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Reject</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Good</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Avg Cycle (s)</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Available (%)</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Performance (%)</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Quality (%)</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Achievement (%)</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">OEE (%)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="record in oeeRecords" :key="record.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td v-if="!selectedLine" class="px-3 py-2">
                                    <div class="text-xs font-semibold text-gray-900 dark:text-white">{{ record.line_name }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="text-xs font-semibold text-gray-900 dark:text-white">{{ formatDate(record.period_date) }}</div>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        {{ record.shift_label }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center text-xs font-semibold text-gray-900 dark:text-white">
                                    {{ formatNumber(record.operation_time_hours) }}
                                </td>
                                <td class="px-3 py-2 text-center text-xs font-semibold text-gray-900 dark:text-white">
                                    {{ formatNumber(record.uptime_hours) }}
                                </td>
                                <td class="px-3 py-2 text-center text-xs font-semibold text-gray-900 dark:text-white">
                                    {{ formatNumber(record.downtime_hours) }}
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
                                <td class="px-3 py-2 text-center text-xs font-semibold text-gray-900 dark:text-white">
                                    {{ record.total_reject.toLocaleString() }}
                                </td>
                                <td class="px-3 py-2 text-center text-xs font-semibold text-gray-900 dark:text-white">
                                    {{ record.good_count.toLocaleString() }}
                                </td>
                                <td class="px-3 py-2 text-center text-xs font-semibold text-gray-900 dark:text-white">
                                    {{ formatNumber(record.avg_cycle_time) }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">{{ formatNumber(record.availability) }}%</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">{{ formatNumber(record.performance) }}%</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">{{ formatNumber(record.quality) }}%</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-bold', record.achievement_rate >= 100 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : record.achievement_rate >= 80 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200']">
                                        {{ formatNumber(record.achievement_rate) }}%
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-bold', record.oee >= 85 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : record.oee >= 70 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200']">
                                        {{ formatNumber(record.oee) }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gradient-to-r from-violet-100 to-purple-100 dark:from-gray-700 dark:to-gray-700 font-bold">
                            <tr>
                                <td v-if="!selectedLine" class="px-3 py-2"></td>
                                <td class="px-3 py-2 text-xs text-gray-900 dark:text-white">Total / Avg</td>
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ formatNumber(oeeRecords.reduce((sum, r) => sum + r.operation_time_hours, 0)) }}</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ formatNumber(oeeRecords.reduce((sum, r) => sum + r.uptime_hours, 0)) }}</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ formatNumber(oeeRecords.reduce((sum, r) => sum + r.downtime_hours, 0)) }}</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ oeeRecords.reduce((sum, r) => sum + r.total_failures, 0) }}</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ oeeRecords.reduce((sum, r) => sum + r.target_production, 0).toLocaleString() }}</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ oeeRecords.reduce((sum, r) => sum + r.total_counter_a, 0).toLocaleString() }}</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ oeeRecords.reduce((sum, r) => sum + r.total_reject, 0).toLocaleString() }}</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ oeeRecords.reduce((sum, r) => sum + r.good_count, 0).toLocaleString() }}</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ formatNumber(oeeRecords.reduce((sum, r) => sum + r.avg_cycle_time, 0) / oeeRecords.length) }}</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ formatNumber(averageAvailability) }}%</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ formatNumber(averagePerformance) }}%</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ formatNumber(averageQuality) }}%</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ formatNumber(averageAchievement) }}%</td>
                                <td class="px-3 py-2 text-center text-xs text-gray-900 dark:text-white">{{ formatNumber(averageOee) }}%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div v-if="oeeRecords.length === 0" class="bg-white dark:bg-gray-800 rounded-2xl p-12 text-center shadow-lg border border-gray-100 dark:border-gray-700">
                <BarChart3 class="w-20 h-20 mx-auto mb-4 text-gray-400 opacity-50" />
                <h3 class="text-xl font-bold text-gray-700 dark:text-gray-300 mb-2">No OEE Data</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">No OEE records found for the selected period.</p>
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
