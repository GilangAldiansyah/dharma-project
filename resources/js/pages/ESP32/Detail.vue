<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import { ArrowLeft, Activity, CheckCircle, Clock, RefreshCw, Settings, History } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

interface Device {
    id: number;
    device_id: string;
    counter_a: number;
    counter_b: number;
    reject: number;
    cycle_time: number;
    max_count: number;
    max_stroke: number;
    loading_time: number;
    production_started_at: string | null;
    relay_status: boolean;
    error_b: boolean;
    has_counter_b: boolean;
    last_update: string;
    progress_percentage: number;
    expected_finish_time: string | null;
    delay_seconds: number;
    is_delayed: boolean;
    is_completed: boolean;
}

interface Log {
    id: number;
    device_id: string;
    counter_a: number;
    counter_b: number;
    reject: number;
    cycle_time: number;
    max_count: number;
    max_stroke: number;
    loading_time: number;
    production_started_at: string | null;
    relay_status: boolean;
    error_b: boolean;
    has_counter_b: boolean;
    logged_at: string;
    shift: number;
    shift_label: string;
}

interface ProductionHistory {
    id: number;
    device_id: string;
    total_counter_a: number;
    total_counter_b: number;
    total_reject: number;
    cycle_time: number;
    max_count: number;
    max_stroke: number;
    expected_time_seconds: number;
    actual_time_seconds: number;
    delay_seconds: number;
    production_started_at: string;
    production_finished_at: string;
    completion_status: string;
    created_at: string;
    shift: number;
    shift_label: string;
}

interface Props {
    device: Device;
    logs: {
        data: Log[];
        current_page: number;
        last_page: number;
        total: number;
    };
    productionHistories: ProductionHistory[];
    shifts: Array<{ value:number; label:string }>;
}

const props = defineProps<Props>();

const autoRefresh = ref(true);
let refreshInterval: number | null = null;
const showSettingsModal = ref(false);
const editMaxCount = ref(props.device.max_count);
const editMaxStroke = ref(props.device.max_stroke);
const editReject = ref(props.device.reject);
const editCycleTime = ref(props.device.cycle_time);
const previousDeviceData = ref<string>('');
const currentRefreshInterval = ref(5000);
const noChangeCount = ref(0);
const lastActivityTime = ref(Date.now());

const getShiftBadgeColor = (shift: number) => {
    switch (shift) {
        case 1: return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 2: return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
        case 3: return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
    }
};

const refreshData = () => {
    router.reload({
        only: ['device', 'logs', 'productionHistories'],
        onSuccess: () => adaptRefreshInterval()
    });
};

const adaptRefreshInterval = () => {
    const currentData = JSON.stringify({
        counter_a: props.device.counter_a,
        counter_b: props.device.counter_b,
        reject: props.device.reject,
        relay_status: props.device.relay_status,
        error_b: props.device.error_b,
        last_update: props.device.last_update,
    });

    if (previousDeviceData.value === currentData) {
        noChangeCount.value++;
    } else {
        noChangeCount.value = 0;
    }
    previousDeviceData.value = currentData;

    const timeSinceActivity = Date.now() - lastActivityTime.value;
    const isUserIdle = timeSinceActivity > 60000;

    let newInterval = 5000;
    if (isUserIdle) {
        newInterval = 30000;
    } else if (noChangeCount.value === 0) {
        newInterval = 5000;
    } else if (noChangeCount.value < 3) {
        newInterval = 10000;
    } else {
        newInterval = 15000;
    }

    if (currentRefreshInterval.value !== newInterval) {
        currentRefreshInterval.value = newInterval;
        if (refreshInterval && autoRefresh.value) {
            clearInterval(refreshInterval);
            refreshInterval = window.setInterval(refreshData, newInterval);
        }
    }
};

const resetActivity = () => {
    lastActivityTime.value = Date.now();
};

const getProgressPercentage = (counter: number, max: number) => {
    if (max === 0) return 0;
    return Math.min((counter / max) * 100, 100);
};

const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
};

const formatTime = (seconds: number) => {
    const absSeconds = Math.abs(seconds);
    const hours = Math.floor(absSeconds / 3600);
    const minutes = Math.floor((absSeconds % 3600) / 60);
    const secs = absSeconds % 60;
    if (hours > 0) return `${hours}j ${minutes}m`;
    if (minutes > 0) return `${minutes}m ${secs}d`;
    return `${secs}d`;
};

const formatDelayTime = (seconds: number, isCompleted: boolean, cycleTime: number) => {
    if (Math.abs(seconds) <= cycleTime) return 'Tepat Waktu';
    const formatted = formatTime(seconds);
    if (isCompleted) {
        return seconds > 0 ? `+${formatted}` : `-${formatted}`;
    }
    return seconds > 0 ? `Delay ${formatted}` : `Cepat ${formatted}`;
};

const getDelayPercentage = (device: Device) => {
    if (!device.production_started_at || device.loading_time === 0) return 0;
    if (device.is_completed) {
        if (device.delay_seconds <= device.cycle_time) return 100;
        return Math.max(100 - (device.delay_seconds / device.loading_time * 100), 0);
    }
    if (device.delay_seconds <= 0) return 100;
    return Math.max(100 - (device.delay_seconds / device.loading_time * 100), 0);
};

const openSettingsModal = () => {
    editMaxCount.value = props.device.max_count;
    editMaxStroke.value = props.device.max_stroke;
    editReject.value = props.device.reject;
    editCycleTime.value = props.device.cycle_time;
    showSettingsModal.value = true;
};

const updateSettings = () => {
    if (!props.device) return;
    const updateData: any = {
        device_id: props.device.device_id,
        max_count: editMaxCount.value,
        reject: editReject.value,
        cycle_time: editCycleTime.value
    };
    if (props.device.has_counter_b) {
        updateData.max_stroke = editMaxStroke.value;
    }
    router.post('/esp32/monitor/update-settings', updateData, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
            showSettingsModal.value = false;
            refreshData();
        }
    });
};

onMounted(() => {
    previousDeviceData.value = JSON.stringify({
        counter_a: props.device.counter_a,
        counter_b: props.device.counter_b,
        reject: props.device.reject,
        relay_status: props.device.relay_status,
        error_b: props.device.error_b,
        last_update: props.device.last_update,
    });
    if (autoRefresh.value) {
        refreshInterval = window.setInterval(refreshData, currentRefreshInterval.value);
    }
    window.addEventListener('mousemove', resetActivity);
    window.addEventListener('keydown', resetActivity);
    window.addEventListener('click', resetActivity);
});

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
    window.removeEventListener('mousemove', resetActivity);
    window.removeEventListener('keydown', resetActivity);
    window.removeEventListener('click', resetActivity);
});

const toggleAutoRefresh = () => {
    autoRefresh.value = !autoRefresh.value;
    if (autoRefresh.value) {
        refreshInterval = window.setInterval(refreshData, currentRefreshInterval.value);
    } else if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
};

const calculateExpectedCounter = (log: Log) => {
    const cycleTime = log.cycle_time || props.device.cycle_time;
    const productionStartedAt = log.production_started_at || props.device.production_started_at;
    if (!productionStartedAt || cycleTime === 0) return '-';
    const productionStarted = new Date(productionStartedAt);
    const logTime = new Date(log.logged_at);
    const elapsedSeconds = Math.floor((logTime.getTime() - productionStarted.getTime()) / 1000);
    if (elapsedSeconds < 0) return 0;
    return Math.floor(elapsedSeconds / cycleTime);
};

const getDelayStatus = (log: Log) => {
    const cycleTime = log.cycle_time || props.device.cycle_time;
    const productionStartedAt = log.production_started_at || props.device.production_started_at;
    const maxCount = log.max_count || props.device.max_count;

    if (!productionStartedAt || cycleTime === 0) {
        return { text: 'N/A', class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400' };
    }

    const productionStarted = new Date(productionStartedAt);
    const logTime = new Date(log.logged_at);
    const elapsedSeconds = Math.floor((logTime.getTime() - productionStarted.getTime()) / 1000);

    if (elapsedSeconds < 0) {
        return { text: 'Future Log', class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400' };
    }

    const actualCounter = log.counter_a;
    const isCompleted = actualCounter >= maxCount;

    if (isCompleted) {
        const actualTime = elapsedSeconds;
        const expectedTime = maxCount * cycleTime;
        const delaySeconds = actualTime - expectedTime;

        if (Math.abs(delaySeconds) <= cycleTime) {
            return { text: 'Tepat Waktu', class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' };
        } else if (delaySeconds > 0) {
            return { text: `+${formatTime(delaySeconds)}`, class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' };
        } else {
            return { text: `-${formatTime(Math.abs(delaySeconds))}`, class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' };
        }
    }

    const expectedCounter = Math.floor(elapsedSeconds / cycleTime);
    const counterDiff = expectedCounter - actualCounter;
    const delaySeconds = counterDiff * cycleTime;

    if (Math.abs(delaySeconds) <= cycleTime) {
        return { text: 'Tepat Waktu', class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' };
    } else if (delaySeconds > 0) {
        return { text: `Delay ${formatTime(delaySeconds)}`, class: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' };
    } else {
        return { text: `Cepat ${formatTime(Math.abs(delaySeconds))}`, class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' };
    }
};

const getHistoryStatusClass = (status: string) => {
    if (status === 'on_time') return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
    if (status === 'delayed') return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
    return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
};

const getHistoryStatusText = (history: ProductionHistory) => {
    if (history.completion_status === 'on_time') return 'Tepat Waktu';
    if (history.completion_status === 'delayed') return `Delay ${formatTime(history.delay_seconds)}`;
    return `Cepat ${formatTime(Math.abs(history.delay_seconds))}`;
};
</script>
<template>
    <Head :title="`Detail - ${device.device_id}`" />
    <AppLayout :breadcrumbs="[
        { title: 'Robot Monitor', href: '/esp32/monitor' },
        { title: device.device_id, href: `/esp32/monitor/${device.device_id}` }
    ]">
        <div class="p-6 space-y-6 bg-white dark:from-gray-900 dark:to-gray-800 min-h-screen">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <Link href="/esp32/monitor" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl transition-all">
                        <ArrowLeft class="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent flex items-center gap-3">
                            <Activity class="w-8 h-8 text-blue-600" />
                            {{ device.device_id }}
                        </h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Last update: {{ formatDateTime(device.last_update) }}</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button @click="openSettingsModal" class="px-4 py-2.5 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium flex items-center gap-2">
                        <Settings class="w-4 h-4" />
                        Settings
                    </button>
                    <button @click="toggleAutoRefresh" :class="['px-4 py-2.5 rounded-xl transition-all duration-300 font-medium flex items-center gap-2', autoRefresh ? 'bg-gradient-to-r from-green-500 to-emerald-500 text-white shadow-lg' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300']">
                        <RefreshCw :class="['w-4 h-4', autoRefresh ? 'animate-spin' : '']" />
                        {{ autoRefresh ? `Auto (${currentRefreshInterval / 1000}s)` : 'Manual' }}
                    </button>
                    <button @click="refreshData" class="px-4 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-all duration-300 font-medium flex items-center gap-2">
                        <RefreshCw class="w-4 h-4" />
                        Refresh
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ device.has_counter_b ? 'Counter A' : 'Counter' }}</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ device.counter_a }}<span class="text-gray-400">/{{ device.max_count }}</span></span>
                            </div>
                            <div class="relative w-full h-4 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-r from-green-400 to-emerald-500 rounded-full transition-all duration-500 shadow-lg" :style="{ width: `${getProgressPercentage(device.counter_a, device.max_count)}%` }"></div>
                            </div>
                        </div>

                        <div v-if="device.has_counter_b">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">Stroke</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ device.counter_b }}<span class="text-gray-400">{{ device.max_stroke > 0 ? `/${device.max_stroke}` : '' }}</span></span>
                            </div>
                            <div class="relative w-full h-4 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-r from-purple-400 to-pink-500 rounded-full transition-all duration-500 shadow-lg" :style="{ width: `${getProgressPercentage(device.counter_b, device.max_stroke > 0 ? device.max_stroke : device.max_count)}%` }"></div>
                            </div>
                        </div>

                        <div v-if="device.production_started_at">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">Target</span>
                                <span :class="['font-bold text-sm', Math.abs(device.delay_seconds) <= device.cycle_time ? 'text-green-600' : device.is_completed ? (device.delay_seconds > 0 ? 'text-red-600' : 'text-blue-600') : (device.delay_seconds > 0 ? 'text-orange-600' : 'text-blue-600')]">
                                    {{ formatDelayTime(device.delay_seconds, device.is_completed, device.cycle_time) }}
                                </span>
                            </div>
                            <div class="relative w-full h-4 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div :class="['absolute inset-0 rounded-full transition-all duration-500 shadow-lg', Math.abs(device.delay_seconds) <= device.cycle_time ? 'bg-gradient-to-r from-green-400 to-emerald-500' : device.is_completed ? (device.delay_seconds > 0 ? 'bg-gradient-to-r from-red-400 to-rose-500' : 'bg-gradient-to-r from-blue-400 to-cyan-500') : (device.delay_seconds > 0 ? 'bg-gradient-to-r from-orange-400 to-amber-500' : 'bg-gradient-to-r from-blue-400 to-cyan-500')]" :style="{ width: `${getDelayPercentage(device)}%` }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-red-500">
                        <div class="text-sm text-gray-600 dark:text-gray-400 font-medium">Reject</div>
                        <div class="text-3xl font-bold text-red-600 mt-1">{{ device.reject }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-blue-500">
                        <div class="text-sm text-gray-600 dark:text-gray-400 font-medium">Cycle Time</div>
                        <div class="text-3xl font-bold text-blue-600 mt-1">{{ device.cycle_time }}s</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-purple-500">
                        <div class="text-sm text-gray-600 dark:text-gray-400 font-medium">Loading Time</div>
                        <div class="text-2xl font-bold text-purple-600 mt-1">{{ formatTime(device.loading_time) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4" :class="device.relay_status ? 'border-green-500' : 'border-gray-500'">
                        <div class="text-sm text-gray-600 dark:text-gray-400 font-medium">Status</div>
                        <div class="flex items-center gap-2 mt-1">
                            <CheckCircle :class="['w-6 h-6', device.relay_status ? 'text-green-600' : 'text-gray-400']" />
                            <span :class="['text-2xl font-bold', device.relay_status ? 'text-green-600' : 'text-gray-400']">
                                {{ device.relay_status ? 'ON' : 'OFF' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="productionHistories.length > 0" class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <History class="w-6 h-6 text-purple-600" />
                        Production History ({{ productionHistories.length }} sessions)
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Started</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Finished</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Shift</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Total Count</th>
                                <th v-if="device.has_counter_b" class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Total Stroke</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Reject</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Duration</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="history in productionHistories" :key="history.id" class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ formatDateTime(history.production_started_at) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ formatDateTime(history.production_finished_at) }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="['px-3 py-1 rounded-full text-xs font-medium', getShiftBadgeColor(history.shift)]">
                                        {{ history.shift_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm font-medium text-blue-600">{{ history.total_counter_a }} / {{ history.max_count }}</td>
                                <td v-if="device.has_counter_b" class="px-6 py-4 text-center text-sm font-medium text-purple-600">{{ history.total_counter_b }}</td>
                                <td class="px-6 py-4 text-center text-sm font-medium text-red-600">{{ history.total_reject }}</td>
                                <td class="px-6 py-4 text-center text-sm text-gray-700 dark:text-gray-300">{{ formatTime(history.actual_time_seconds) }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="['px-3 py-1 rounded-lg text-xs font-semibold', getHistoryStatusClass(history.completion_status)]">
                                        {{ getHistoryStatusText(history) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Clock class="w-6 h-6 text-blue-600" />
                        History Logs ({{ logs.total }} records)
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Timestamp</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Shift</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">{{ device.has_counter_b ? 'Counter A' : 'Counter' }}</th>
                                <th v-if="device.has_counter_b" class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Stroke</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Expected</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Delay Status</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Reject</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Relay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in logs.data" :key="log.id" class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ formatDateTime(log.logged_at) }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="['px-3 py-1 rounded-full text-xs font-medium', getShiftBadgeColor(log.shift)]">
                                        {{ log.shift_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm font-medium text-blue-600">{{ log.counter_a }}</td>
                                <td v-if="device.has_counter_b" class="px-6 py-4 text-center text-sm font-medium text-purple-600">{{ log.counter_b }}</td>
                                <td class="px-6 py-4 text-center text-sm text-gray-600 dark:text-gray-400">{{ calculateExpectedCounter(log) }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="['px-3 py-1 rounded-lg text-xs font-semibold', getDelayStatus(log).class]">
                                        {{ getDelayStatus(log).text }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm font-medium text-red-600">{{ log.reject }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="['px-3 py-1 rounded-lg text-xs font-semibold', log.relay_status ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400']">
                                        {{ log.relay_status ? 'ON' : 'OFF' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td :colspan="device.has_counter_b ? 8 : 7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    Belum ada history log
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="showSettingsModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50" @click.self="showSettingsModal = false">
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-96 shadow-2xl">
                <h3 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">Settings - {{ device.device_id }}</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Max Count</label>
                        <input v-model.number="editMaxCount" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                    </div>
                    <div v-if="device.has_counter_b">
                        <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Max Stroke</label>
                        <input v-model.number="editMaxStroke" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Reject</label>
                        <input v-model.number="editReject" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Cycle Time (s)</label>
                        <input v-model.number="editCycleTime" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                    </div>
                    <div class="p-4 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl border border-blue-100 dark:border-blue-800">
                        <div class="text-xs text-gray-600 dark:text-gray-400 font-medium mb-1">Loading Time</div>
                        <div class="text-lg font-bold text-blue-600">{{ formatTime(editMaxCount * editCycleTime) }}</div>
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <button @click="updateSettings" class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:shadow-lg transition-all">
                        Update
                    </button>
                    <button @click="showSettingsModal = false" class="flex-1 bg-gray-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:shadow-lg transition-all">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
