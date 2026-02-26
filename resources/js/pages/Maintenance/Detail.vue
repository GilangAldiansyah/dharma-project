<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import {
    ArrowLeft, History, BarChart3, Loader2, ChevronDown, ChevronUp,
    Calendar, Archive, TrendingUp, Activity, Wrench, RotateCcw
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
    has_data: boolean;
    line: Line;
    machines: Machine[];
    reason: string;
}

interface HistoryData {
    current: { line: Line; period: string; };
    history: ArchivedPeriod[];
}

interface SummaryData {
    line: { id: number; line_code: string; line_name: string; };
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
    filter_info: { type: string; start_date: string | null; end_date: string | null; };
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
const expandedRows = ref<Set<number>>(new Set());
const historyFilter = ref({ type: 'all', shift: '', date: '' });
const summaryFilter = ref({ type: 'all', start_date: '', end_date: '' });

const formatDuration = (hours: number): string => {
    const h = Math.floor(hours);
    const m = Math.floor((hours - h) * 60);
    const s = Math.floor(((hours - h) * 60 - m) * 60);
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
};

const toggleRow = (index: number) => {
    if (expandedRows.value.has(index)) {
        expandedRows.value.delete(index);
    } else {
        expandedRows.value.add(index);
    }
};

const isRowExpanded = (index: number) => expandedRows.value.has(index);

const parsePeriodLabel = (period: string) => {
    if (period.includes(',')) {
        const [datePart, timePart] = period.split(',');
        return { date: datePart.trim(), time: timePart.trim() };
    }
    const parts = period.split(' - ');
    if (parts.length === 2) {
        return { date: parts[0].trim(), time: `s/d ${parts[1].trim()}` };
    }
    return { date: period, time: '' };
};

const showCustomSummary = computed(() => summaryFilter.value.type === 'custom');

const loadHistoryData = async () => {
    isLoadingHistory.value = true;
    expandedRows.value.clear();
    try {
        const params = new URLSearchParams({ filter_type: historyFilter.value.type });
        if (historyFilter.value.shift) params.append('shift', historyFilter.value.shift);
        if (historyFilter.value.date) params.append('date', historyFilter.value.date);
        const response = await fetch(`/maintenance/lines/${props.line.id}/history?${params.toString()}`);
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

const resetHistoryFilter = async () => {
    historyFilter.value = { type: 'all', shift: '', date: '' };
    await loadHistoryData();
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
            <div class="flex items-center gap-4">
                <button @click="router.visit('/maintenance/lines')" class="p-2.5 bg-white dark:bg-gray-800 rounded-xl hover:shadow-lg transition-all">
                    <ArrowLeft class="w-5 h-5" />
                </button>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ line.line_name }}</h1>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
                <div class="flex border-b-2 border-gray-200 dark:border-gray-700">
                    <button @click="changeTab('history')" :class="['flex-1 px-6 py-4 font-semibold flex items-center justify-center gap-2 transition-all', activeTab === 'history' ? 'border-b-4 border-purple-600 text-purple-600' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']">
                        <History class="w-5 h-5" /> History
                    </button>
                    <button @click="changeTab('summary')" :class="['flex-1 px-6 py-4 font-semibold flex items-center justify-center gap-2 transition-all', activeTab === 'summary' ? 'border-b-4 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']">
                        <BarChart3 class="w-5 h-5" /> Summary
                    </button>
                </div>

                <div class="p-6">
                    <!-- TAB HISTORY -->
                    <div v-if="activeTab === 'history'">
                        <div v-if="isLoadingHistory" class="flex items-center justify-center py-12">
                            <Loader2 class="w-8 h-8 animate-spin text-purple-600" />
                        </div>
                        <div v-else-if="historyData" class="space-y-4">

                            <!-- Filter Bar History -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <select
                                    :value="historyFilter.type"
                                    @change="historyFilter.type = ($event.target as HTMLSelectElement).value; loadHistoryData()"
                                    class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                >
                                    <option value="all">Semua Periode</option>
                                    <option value="today">Hari Ini</option>
                                    <option value="week">Minggu Ini</option>
                                    <option value="month">Bulan Ini</option>
                                </select>
                                <select
                                    :value="historyFilter.shift"
                                    @change="historyFilter.shift = ($event.target as HTMLSelectElement).value; loadHistoryData()"
                                    class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                >
                                    <option value="">Semua Shift</option>
                                    <option value="1">Shift 1</option>
                                    <option value="2">Shift 2</option>
                                    <option value="3">Shift 3</option>
                                </select>
                                <input
                                    v-model="historyFilter.date"
                                    type="date"
                                    @change="loadHistoryData()"
                                    class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                />
                                <button @click="resetHistoryFilter" class="px-4 py-3 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all font-medium flex items-center justify-center gap-2">
                                    <RotateCcw class="w-4 h-4" /> Reset
                                </button>
                            </div>

                            <!-- Periode Aktif -->
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border-2 border-blue-200 dark:border-blue-800 rounded-2xl p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <Activity class="w-4 h-4 text-blue-600" />
                                    <span class="font-bold text-blue-800 dark:text-blue-300 text-sm">Periode Saat Ini (Aktif)</span>
                                </div>
                                <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-2.5 text-center">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Operation</div>
                                        <div class="text-sm font-bold text-blue-600">{{ formatDuration(line.total_operation_hours) }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-2.5 text-center">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Repair</div>
                                        <div class="text-sm font-bold text-orange-600">{{ formatDuration(line.total_repair_hours) }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-2.5 text-center">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Uptime</div>
                                        <div class="text-sm font-bold text-green-600">{{ formatDuration(line.uptime_hours) }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-2.5 text-center">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Failures</div>
                                        <div class="text-sm font-bold text-red-600">{{ line.total_failures }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-2.5 text-center">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">MTTR</div>
                                        <div class="text-sm font-bold text-purple-600">{{ line.average_mttr || '-' }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-2.5 text-center">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">MTBF</div>
                                        <div class="text-sm font-bold text-indigo-600">{{ line.average_mtbf ? formatDuration(line.average_mtbf) : '-' }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabel History -->
                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden">
                                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b-2 border-gray-200 dark:border-gray-700 flex items-center gap-2">
                                    <Archive class="w-4 h-4 text-gray-500" />
                                    <span class="font-semibold text-sm text-gray-700 dark:text-gray-300">Periode Sebelumnya</span>
                                    <span class="ml-1 px-2 py-0.5 bg-purple-100 dark:bg-purple-900/30 text-purple-600 rounded-full text-xs font-semibold">{{ historyData.history.length }}</span>
                                </div>
                                <div v-if="historyData.history.length > 0" class="overflow-x-auto">
                                    <table class="w-full">
                                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b-2 border-gray-200 dark:border-gray-600">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Periode</th>
                                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Operation</th>
                                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Repair</th>
                                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Uptime</th>
                                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Failures</th>
                                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">MTTR</th>
                                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">MTBF</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Alasan</th>
                                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Mesin</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            <template v-for="(period, index) in historyData.history" :key="index">
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-start gap-2">
                                                            <Calendar class="w-4 h-4 text-purple-500 flex-shrink-0 mt-1" />
                                                            <div>
                                                                <div class="font-bold text-gray-800 dark:text-gray-200 text-sm">{{ parsePeriodLabel(period.period).date }}</div>
                                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ parsePeriodLabel(period.period).time }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 text-center"><span class="text-sm font-bold text-blue-600 font-mono">{{ formatDuration(period.line.total_operation_hours) }}</span></td>
                                                    <td class="px-4 py-3 text-center"><span class="text-sm font-bold text-orange-600 font-mono">{{ formatDuration(period.line.total_repair_hours) }}</span></td>
                                                    <td class="px-4 py-3 text-center"><span class="text-sm font-bold text-green-600 font-mono">{{ formatDuration(period.line.uptime_hours) }}</span></td>
                                                    <td class="px-4 py-3 text-center"><span class="text-sm font-bold text-red-600">{{ period.line.total_failures }}</span></td>
                                                    <td class="px-4 py-3 text-center"><span class="text-sm font-bold text-purple-600 font-mono">{{ period.line.average_mttr || '-' }}</span></td>
                                                    <td class="px-4 py-3 text-center"><span class="text-sm font-bold text-indigo-600 font-mono">{{ period.line.average_mtbf ? formatDuration(period.line.average_mtbf) : '-' }}</span></td>
                                                    <td class="px-4 py-3"><span class="text-xs text-gray-500 dark:text-gray-400">{{ period.reason || '-' }}</span></td>
                                                    <td class="px-4 py-3 text-center">
                                                        <button
                                                            v-if="period.machines.length > 0"
                                                            @click="toggleRow(index)"
                                                            :class="['inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all', isRowExpanded(index) ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 hover:bg-purple-50 hover:text-purple-600']"
                                                        >
                                                            <Wrench class="w-3 h-3" />
                                                            {{ period.machines.length }}
                                                            <component :is="isRowExpanded(index) ? ChevronUp : ChevronDown" class="w-3 h-3" />
                                                        </button>
                                                        <span v-else class="text-xs text-gray-400">-</span>
                                                    </td>
                                                </tr>
                                                <tr v-if="isRowExpanded(index)" class="bg-gray-50 dark:bg-gray-800/50">
                                                    <td colspan="9" class="px-6 py-4">
                                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                            <div v-for="machine in period.machines" :key="machine.id" class="bg-white dark:bg-gray-800 rounded-xl p-3 border-2 border-gray-200 dark:border-gray-700 shadow-sm">
                                                                <div class="mb-2">
                                                                    <div class="font-semibold text-sm text-gray-900 dark:text-white">{{ machine.machine_name }}</div>
                                                                    <div class="text-xs text-gray-500">{{ machine.machine_type }}</div>
                                                                </div>
                                                                <div class="grid grid-cols-2 gap-1.5 text-xs">
                                                                    <div class="flex justify-between bg-gray-50 dark:bg-gray-700/50 rounded px-2 py-1">
                                                                        <span class="text-gray-500">Operation</span>
                                                                        <span class="font-mono font-bold text-blue-600">{{ formatDuration(machine.total_operation_hours) }}</span>
                                                                    </div>
                                                                    <div class="flex justify-between bg-gray-50 dark:bg-gray-700/50 rounded px-2 py-1">
                                                                        <span class="text-gray-500">Repair</span>
                                                                        <span class="font-mono font-bold text-orange-600">{{ formatDuration(machine.total_repair_hours) }}</span>
                                                                    </div>
                                                                    <div class="flex justify-between bg-gray-50 dark:bg-gray-700/50 rounded px-2 py-1">
                                                                        <span class="text-gray-500">MTTR</span>
                                                                        <span class="font-mono font-bold text-purple-600">{{ machine.mttr_hours ? formatDuration(machine.mttr_hours) : '-' }}</span>
                                                                    </div>
                                                                    <div class="flex justify-between bg-gray-50 dark:bg-gray-700/50 rounded px-2 py-1">
                                                                        <span class="text-gray-500">MTBF</span>
                                                                        <span class="font-mono font-bold text-indigo-600">{{ machine.mtbf_hours ? formatDuration(machine.mtbf_hours) : '-' }}</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="text-center py-12 text-gray-500">
                                    <Archive class="w-12 h-12 mx-auto mb-3 opacity-30" />
                                    <p class="text-sm">Belum ada history periode sebelumnya</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB SUMMARY -->
                    <div v-if="activeTab === 'summary'">
                        <div v-if="isLoadingSummary" class="flex items-center justify-center py-12">
                            <Loader2 class="w-8 h-8 animate-spin text-indigo-600" />
                        </div>
                        <div v-else-if="summaryData" class="space-y-4">

                            <!-- Filter Bar Summary -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <select
                                    :value="summaryFilter.type"
                                    @change="summaryFilter.type = ($event.target as HTMLSelectElement).value; if (summaryFilter.type !== 'custom') loadSummaryData()"
                                    class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                                >
                                    <option value="all">Semua Periode</option>
                                    <option value="week">Minggu Ini</option>
                                    <option value="month">Bulan Ini</option>
                                    <option value="custom">Custom</option>
                                </select>
                                <input
                                    v-model="summaryFilter.start_date"
                                    type="date"
                                    :disabled="!showCustomSummary"
                                    @change="loadSummaryData()"
                                    :class="['w-full border-2 rounded-xl px-4 py-3 transition-all', showCustomSummary ? 'border-gray-200 dark:border-gray-700 dark:bg-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200' : 'border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 text-gray-400 cursor-not-allowed']"
                                    placeholder="Dari tanggal"
                                />
                                <input
                                    v-model="summaryFilter.end_date"
                                    type="date"
                                    :disabled="!showCustomSummary"
                                    @change="loadSummaryData()"
                                    :class="['w-full border-2 rounded-xl px-4 py-3 transition-all', showCustomSummary ? 'border-gray-200 dark:border-gray-700 dark:bg-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200' : 'border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 text-gray-400 cursor-not-allowed']"
                                    placeholder="Sampai tanggal"
                                />
                                <button @click="resetSummaryFilter" class="px-4 py-3 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all font-medium flex items-center justify-center gap-2">
                                    <RotateCcw class="w-4 h-4" /> Reset
                                </button>
                            </div>

                            <!-- Total Summary -->
                            <div class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 border-2 border-indigo-200 dark:border-indigo-800 rounded-2xl p-5 shadow-lg">
                                <div class="flex items-center gap-2 mb-3">
                                    <TrendingUp class="w-5 h-5 text-indigo-600" />
                                    <h3 class="font-bold text-indigo-800 dark:text-indigo-300">Total Keseluruhan</h3>
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
                                            <div class="text-xs font-bold mb-2">{{ period.period }}</div>
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
