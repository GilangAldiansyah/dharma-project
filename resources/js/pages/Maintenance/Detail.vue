<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import {
    ArrowLeft, History, BarChart3, Loader2, ChevronDown, ChevronUp,
    Calendar, Archive, TrendingUp, Activity, Wrench, Search, RotateCcw
} from 'lucide-vue-next';

interface Machine {
    id: number;
    machine_name: string;
    barcode: string;
    machine_type: string;
    total_operation_hours: number;
    total_repair_hours: number;
    total_failures: number;
    mttr_hours: number | null;
    mtbf_hours: number | null;
}

interface Line {
    id: number;
    line_code: string;
    line_name: string;
    plant: string;
    total_operation_hours: number;
    total_repair_hours: number;
    uptime_hours: number;
    total_failures: number;
    total_line_stops: number;
    average_mtbf: number | null;
    average_mttr: string | null;
}

interface ArchivedPeriod {
    period: string;
    line: Line;
    machines: Machine[];
    reason: string;
}

interface HistoryData {
    current: {
        line: Line;
        period: string;
    };
    history: ArchivedPeriod[];
}

interface SummaryData {
    line: {
        id: number;
        line_code: string;
        line_name: string;
    };
    current_period: {
        operation_hours: number;
        repair_hours: number;
        uptime_hours: number;
        failures: number;
        mtbf: number | null;
        mttr: string | null;
        included_in_filter: boolean;
    };
    total_all_time: {
        operation_hours: number;
        repair_hours: number;
        uptime_hours: number;
        failures: number;
        line_stops: number;
    };
    periods_count: number;
    filter_info: {
        type: string;
        start_date: string | null;
        end_date: string | null;
    };
    archived_periods: Array<{
        period: string;
        operation_hours: number;
        repair_hours: number;
        uptime_hours: number;
        failures: number;
    }>;
}

interface Props {
    line: Line & { machines: Machine[] };
}

const props = defineProps<Props>();

const activeTab = ref<'history' | 'summary'>('history');
const historyData = ref<HistoryData | null>(null);
const summaryData = ref<SummaryData | null>(null);
const isLoadingHistory = ref(false);
const isLoadingSummary = ref(false);
const expandedHistoryPeriods = ref<Set<number>>(new Set());
const showFilterExpand = ref(false);
const summaryFilter = ref({ type: 'all', start_date: '', end_date: '' });

const formatDuration = (hours: number): string => {
    const h = Math.floor(hours);
    const m = Math.floor((hours - h) * 60);
    const s = Math.floor(((hours - h) * 60 - m) * 60);
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
};

const toggleHistoryPeriodExpand = (index: number) => {
    if (expandedHistoryPeriods.value.has(index)) {
        expandedHistoryPeriods.value.delete(index);
    } else {
        expandedHistoryPeriods.value.add(index);
    }
};

const isHistoryPeriodExpanded = (index: number) => expandedHistoryPeriods.value.has(index);

const getFilterLabel = computed(() => {
    switch (summaryFilter.value.type) {
        case 'week': return 'Minggu Ini';
        case 'month': return 'Bulan Ini';
        case 'custom': return 'Custom';
        default: return 'Semua Periode';
    }
});
const loadHistoryData = async () => {
    isLoadingHistory.value = true;
    expandedHistoryPeriods.value.clear();
    try {
        const response = await fetch(`/maintenance/lines/${props.line.id}/history`);
        const data = await response.json();
        if (data.success) {
            historyData.value = data;
        } else {
            alert(data.message || 'Gagal mengambil data history');
        }
    } catch {
        alert('Gagal mengambil data history');
    } finally {
        isLoadingHistory.value = false;
    }
};

const loadSummaryData = async () => {
    isLoadingSummary.value = true;
    try {
        const params = new URLSearchParams({ filter_type: summaryFilter.value.type });
        if (summaryFilter.value.type === 'custom') {
            if (summaryFilter.value.start_date) params.append('start_date', summaryFilter.value.start_date);
            if (summaryFilter.value.end_date) params.append('end_date', summaryFilter.value.end_date);
        }
        const response = await fetch(`/maintenance/lines/${props.line.id}/summary?${params.toString()}`);
        const data = await response.json();
        if (data.success) {
            summaryData.value = data;
        } else {
            alert(data.message || 'Gagal mengambil data summary');
        }
    } catch {
        alert('Gagal mengambil data summary');
    } finally {
        isLoadingSummary.value = false;
    }
};

const applySummaryFilter = async () => {
    if (summaryFilter.value.type === 'custom') {
        if (!summaryFilter.value.start_date || !summaryFilter.value.end_date) {
            alert('Pilih tanggal mulai dan tanggal akhir!');
            return;
        }
        const start = new Date(summaryFilter.value.start_date);
        const end = new Date(summaryFilter.value.end_date);
        if (start > end) {
            alert('Tanggal mulai tidak boleh lebih besar dari tanggal akhir!');
            return;
        }
    }
    await loadSummaryData();
};

const resetSummaryFilter = async () => {
    summaryFilter.value = { type: 'all', start_date: '', end_date: '' };
    await loadSummaryData();
};

const changeTab = async (tab: 'history' | 'summary') => {
    activeTab.value = tab;
    if (tab === 'history' && !historyData.value) {
        await loadHistoryData();
    } else if (tab === 'summary' && !summaryData.value) {
        await loadSummaryData();
    }
};

onMounted(async () => {
    await loadHistoryData();
});
</script>
<template>
    <Head :title="`Detail - ${line.line_name}`" />
    <AppLayout :breadcrumbs="[
        { title: 'Monitoring Maintenance', href: '/maintenance' },
        { title: 'Line', href: '/maintenance/lines' },
        { title: 'Detail', href: '#' }
    ]">
        <div class="p-6 space-y-6 bg-gray-50 dark:!bg-gray-900 min-h-screen">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button @click="router.visit('/maintenance/lines')" class="p-2.5 bg-white dark:bg-gray-800 rounded-xl hover:shadow-lg transition-all">
                        <ArrowLeft class="w-5 h-5" />
                    </button>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ line.line_name }}</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ line.line_code }} - {{ line.plant }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
                <div class="flex border-b-2 border-gray-200 dark:border-gray-700">
                    <button @click="changeTab('history')" :class="['flex-1 px-6 py-4 font-semibold flex items-center justify-center gap-2 transition-all', activeTab === 'history' ? 'border-b-4 border-purple-600 text-purple-600' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']">
                        <History class="w-5 h-5" />
                        History
                    </button>
                    <button @click="changeTab('summary')" :class="['flex-1 px-6 py-4 font-semibold flex items-center justify-center gap-2 transition-all', activeTab === 'summary' ? 'border-b-4 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']">
                        <BarChart3 class="w-5 h-5" />
                        Summary
                    </button>
                </div>

                <div class="p-6">
                    <div v-if="activeTab === 'history'">
                        <div v-if="isLoadingHistory" class="flex items-center justify-center py-12">
                            <Loader2 class="w-8 h-8 animate-spin text-purple-600" />
                        </div>
                        <div v-else-if="historyData" class="space-y-4">
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border-2 border-blue-200 dark:border-blue-800 rounded-2xl p-5 shadow-lg">
                                <div class="flex items-center gap-2 mb-3">
                                    <Activity class="w-5 h-5 text-blue-600" />
                                    <h3 class="font-bold text-blue-800 dark:text-blue-300">Periode Saat Ini (Aktif)</h3>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 shadow">
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Operation</div>
                                        <div class="text-lg font-bold text-blue-600">{{ formatDuration(line.total_operation_hours) }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 shadow">
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Repair</div>
                                        <div class="text-lg font-bold text-orange-600">{{ formatDuration(line.total_repair_hours) }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 shadow">
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Uptime</div>
                                        <div class="text-lg font-bold text-green-600">{{ formatDuration(line.uptime_hours) }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 shadow">
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Failures</div>
                                        <div class="text-lg font-bold text-red-600">{{ line.total_failures }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 shadow">
                                        <div class="text-xs text-gray-600 dark:text-gray-400">MTTR</div>
                                        <div class="text-lg font-bold text-purple-600">{{ line.average_mttr || '-' }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 shadow">
                                        <div class="text-xs text-gray-600 dark:text-gray-400">MTBF</div>
                                        <div class="text-lg font-bold text-indigo-600">{{ line.average_mtbf ? formatDuration(line.average_mtbf) : '-' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="historyData.history.length > 0">
                                <h3 class="font-bold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                                    <Archive class="w-5 h-5 text-gray-600" />
                                    Periode Sebelumnya ({{ historyData.history.length }})
                                </h3>
                                <div class="space-y-3">
                                    <div v-for="(period, index) in historyData.history" :key="index" class="border-2 border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden shadow-lg">
                                        <div @click="toggleHistoryPeriodExpand(index)" class="bg-gray-50 dark:bg-gray-800 p-4 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-750 transition-colors">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <Calendar class="w-5 h-5 text-purple-600" />
                                                    <div>
                                                        <div class="font-bold text-gray-800 dark:text-gray-200">{{ period.period.replace(' 00:00', '') }}</div>
                                                        <div v-if="period.reason" class="text-xs text-gray-600 dark:text-gray-400 mt-1">Alasan: {{ period.reason }}</div>
                                                    </div>
                                                </div>
                                                <component :is="isHistoryPeriodExpanded(index) ? ChevronUp : ChevronDown" class="w-5 h-5 text-gray-500" />
                                            </div>
                                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mt-3">
                                                <div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-400">Operation</div>
                                                    <div class="text-sm font-bold text-blue-600">{{ formatDuration(period.line.total_operation_hours) }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-400">Repair</div>
                                                    <div class="text-sm font-bold text-orange-600">{{ formatDuration(period.line.total_repair_hours) }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-400">Uptime</div>
                                                    <div class="text-sm font-bold text-green-600">{{ formatDuration(period.line.uptime_hours) }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-400">Failures</div>
                                                    <div class="text-sm font-bold text-red-600">{{ period.line.total_failures }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-400">MTTR</div>
                                                    <div class="text-sm font-bold text-purple-600">{{ period.line.average_mttr || '-' }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-400">MTBF</div>
                                                    <div class="text-sm font-bold text-indigo-600">{{ period.line.average_mtbf ? formatDuration(period.line.average_mtbf) : '-' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="isHistoryPeriodExpanded(index)" class="p-4 bg-white dark:bg-gray-900">
                                            <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-3">Mesin ({{ period.machines.length }})</h4>
                                            <div v-if="period.machines.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                <div v-for="machine in period.machines" :key="machine.id" class="p-3 bg-gray-50 dark:bg-gray-800 rounded-xl text-sm border-2 border-gray-200 dark:border-gray-700">
                                                    <div class="flex justify-between items-start mb-2">
                                                        <div>
                                                            <div class="font-semibold">{{ machine.machine_name }}</div>
                                                            <div class="text-xs text-gray-500">{{ machine.machine_type }}</div>
                                                        </div>
                                                        <span class="font-mono text-xs bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded">{{ machine.barcode }}</span>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-600 dark:text-gray-400">Operation:</span>
                                                            <span class="font-mono text-blue-600">{{ formatDuration(machine.total_operation_hours) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-600 dark:text-gray-400">Repair:</span>
                                                            <span class="font-mono text-orange-600">{{ formatDuration(machine.total_repair_hours) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-600 dark:text-gray-400">MTTR:</span>
                                                            <span class="font-mono text-purple-600">{{ machine.mttr_hours ? formatDuration(machine.mttr_hours) : '-' }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-600 dark:text-gray-400">MTBF:</span>
                                                            <span class="font-mono text-indigo-600">{{ machine.mtbf_hours ? formatDuration(machine.mtbf_hours) : '-' }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="text-center py-4 text-gray-500">
                                                <Wrench class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                                <p class="text-sm">Tidak ada data mesin</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500">
                                <Archive class="w-12 h-12 mx-auto mb-3 opacity-50" />
                                <p class="text-sm">Belum ada history periode sebelumnya</p>
                            </div>
                        </div>
                    </div>
                    <div v-if="activeTab === 'summary'">
                        <div v-if="isLoadingSummary" class="flex items-center justify-center py-12">
                            <Loader2 class="w-8 h-8 animate-spin text-indigo-600" />
                        </div>
                        <div v-else-if="summaryData" class="space-y-4">
                            <div class="bg-white dark:bg-gray-800 border-2 rounded-2xl shadow-lg">
                                <button @click="showFilterExpand = !showFilterExpand" class="w-full flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors rounded-t-2xl">
                                    <div class="flex items-center gap-2">
                                        <Calendar class="w-4 h-4 text-blue-600" />
                                        <span class="text-sm font-semibold">Filter Periode: {{ getFilterLabel }}</span>
                                    </div>
                                    <component :is="showFilterExpand ? ChevronUp : ChevronDown" class="w-4 h-4 text-gray-500" />
                                </button>
                                <div v-if="showFilterExpand" class="p-4 border-t-2 space-y-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button @click="summaryFilter.type = 'all'; applySummaryFilter()" :class="['px-4 py-2 rounded-xl text-sm font-semibold transition-all', summaryFilter.type === 'all' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg' : 'bg-gray-100 dark:bg-gray-700']">Semua</button>
                                        <button @click="summaryFilter.type = 'week'; applySummaryFilter()" :class="['px-4 py-2 rounded-xl text-sm font-semibold transition-all', summaryFilter.type === 'week' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg' : 'bg-gray-100 dark:bg-gray-700']">Minggu Ini</button>
                                        <button @click="summaryFilter.type = 'month'; applySummaryFilter()" :class="['px-4 py-2 rounded-xl text-sm font-semibold transition-all', summaryFilter.type === 'month' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg' : 'bg-gray-100 dark:bg-gray-700']">Bulan Ini</button>
                                        <button @click="summaryFilter.type = 'custom'" :class="['px-4 py-2 rounded-xl text-sm font-semibold transition-all', summaryFilter.type === 'custom' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg' : 'bg-gray-100 dark:bg-gray-700']">Custom</button>
                                    </div>
                                    <div v-if="summaryFilter.type === 'custom'" class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        <input v-model="summaryFilter.start_date" type="date" class="rounded-xl border-2 px-3 py-2 dark:bg-gray-700" />
                                        <input v-model="summaryFilter.end_date" type="date" class="rounded-xl border-2 px-3 py-2 dark:bg-gray-700" />
                                        <div class="flex gap-2">
                                            <button @click="applySummaryFilter" class="flex-1 px-3 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-semibold hover:shadow-lg flex items-center justify-center gap-2">
                                                <Search class="w-4 h-4" />
                                                Terapkan
                                            </button>
                                            <button @click="resetSummaryFilter" class="px-3 py-2 bg-gray-600 text-white rounded-xl hover:shadow-lg" title="Reset">
                                                <RotateCcw class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 border-2 border-indigo-200 dark:border-indigo-800 rounded-2xl p-5 shadow-lg">
                                <div class="flex items-center gap-2 mb-3">
                                    <TrendingUp class="w-5 h-5 text-indigo-600" />
                                    <h3 class="font-bold text-indigo-800 dark:text-indigo-300">Total {{ getFilterLabel }}</h3>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3">
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 shadow">
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Total Operation</div>
                                        <div class="text-lg font-bold text-blue-600">{{ formatDuration(summaryData.total_all_time.operation_hours) }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 shadow">
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Total Repair</div>
                                        <div class="text-lg font-bold text-orange-600">{{ formatDuration(summaryData.total_all_time.repair_hours) }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 shadow">
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Total Uptime</div>
                                        <div class="text-lg font-bold text-green-600">{{ formatDuration(summaryData.total_all_time.uptime_hours) }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 shadow">
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Total Failures</div>
                                        <div class="text-lg font-bold text-red-600">{{ summaryData.total_all_time.failures }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 shadow">
                                        <div class="text-xs text-gray-600 dark:text-gray-400">MTBF</div>
                                        <div class="text-lg font-bold text-indigo-600">{{ summaryData.total_all_time.failures > 0 ? formatDuration(summaryData.total_all_time.operation_hours / summaryData.total_all_time.failures) : '-' }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 shadow">
                                        <div class="text-xs text-gray-600 dark:text-gray-400">MTTR</div>
                                        <div class="text-lg font-bold text-purple-600">{{ summaryData.total_all_time.failures > 0 ? formatDuration(summaryData.total_all_time.repair_hours / summaryData.total_all_time.failures) : '-' }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 shadow">
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Total Periode</div>
                                        <div class="text-lg font-bold text-gray-600">{{ summaryData.periods_count }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-white dark:bg-gray-800 border-2 rounded-2xl p-4 shadow-lg">
                                    <h4 class="font-bold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                                        <Activity class="w-5 h-5 text-blue-600" />
                                        Periode Saat Ini
                                    </h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Operation:</span><span class="font-bold text-blue-600">{{ formatDuration(summaryData.current_period.operation_hours) }}</span></div>
                                        <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Repair:</span><span class="font-bold text-orange-600">{{ formatDuration(summaryData.current_period.repair_hours) }}</span></div>
                                        <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Uptime:</span><span class="font-bold text-green-600">{{ formatDuration(summaryData.current_period.uptime_hours) }}</span></div>
                                        <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Failures:</span><span class="font-bold text-red-600">{{ summaryData.current_period.failures }}</span></div>
                                        <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">MTBF:</span><span class="font-bold text-indigo-600">{{ summaryData.current_period.mtbf ? formatDuration(summaryData.current_period.mtbf) : '-' }}</span></div>
                                        <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">MTTR:</span><span class="font-bold text-purple-600">{{ summaryData.current_period.mttr || '-' }}</span></div>
                                    </div>
                                </div>
                                <div class="bg-white dark:bg-gray-800 border-2 rounded-2xl p-4 shadow-lg">
                                    <h4 class="font-bold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                                        <Archive class="w-5 h-5 text-purple-600" />
                                        Ringkasan Periode Lama
                                    </h4>
                                    <div v-if="summaryData.archived_periods.length > 0" class="space-y-2 max-h-64 overflow-y-auto">
                                        <div v-for="(period, index) in summaryData.archived_periods" :key="index" class="p-3 bg-gray-50 dark:bg-gray-900 rounded-xl border-2">
                                            <div class="text-xs font-bold mb-2">{{ period.period.replace(' 00:00', '') }}</div>
                                            <div class="grid grid-cols-2 gap-2 text-xs">
                                                <div><span class="text-gray-500">Operation:</span> <span class="font-bold text-blue-600">{{ formatDuration(period.operation_hours) }}</span></div>
                                                <div><span class="text-gray-500">Repair:</span> <span class="font-bold text-orange-600">{{ formatDuration(period.repair_hours) }}</span></div>
                                                <div><span class="text-gray-500">Uptime:</span> <span class="font-bold text-green-600">{{ formatDuration(period.uptime_hours) }}</span></div>
                                                <div><span class="text-gray-500">Failures:</span> <span class="font-bold text-red-600">{{ period.failures }}</span></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-center py-4 text-gray-500">
                                        <p class="text-sm">Belum ada periode sebelumnya</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
