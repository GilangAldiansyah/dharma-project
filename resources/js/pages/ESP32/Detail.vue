<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import { ArrowLeft, Activity, AlertCircle, CheckCircle, Clock, RefreshCw, Settings, History } from 'lucide-vue-next';
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

const refreshData = () => {
    router.reload({
        only: ['device', 'logs', 'productionHistories'],
        onSuccess: () => {
            adaptRefreshInterval();
        }
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

    if (hours > 0) {
        return `${hours}h ${minutes}m ${secs}s`;
    } else if (minutes > 0) {
        return `${minutes}m ${secs}s`;
    }
    return `${secs}s`;
};

const formatDelayTime = (seconds: number, isCompleted: boolean, cycleTime: number) => {
    if (Math.abs(seconds) <= cycleTime) return 'On Time';
    const formatted = formatTime(seconds);

    if (isCompleted) {
        return seconds > 0 ? `Selesai ${formatted} lebih lama` : `Selesai ${formatted} lebih cepat`;
    } else {
        return seconds > 0 ? `Delay ${formatted}` : `Lebih Cepat ${formatted}`;
    }
};

const getDelayColor = (seconds: number, isCompleted: boolean, cycleTime: number) => {
    if (Math.abs(seconds) <= cycleTime) return 'text-green-600';
    if (isCompleted) {
        return seconds > 0 ? 'text-red-600' : 'text-blue-600';
    } else {
        return seconds > 0 ? 'text-orange-600' : 'text-blue-600';
    }
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

    const deviceId = props.device.device_id;
    const updateData: any = {
        device_id: deviceId,
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
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }

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

const getRefreshIntervalText = () => {
    const seconds = currentRefreshInterval.value / 1000;
    return `${seconds}s`;
};

const calculateExpectedCounter = (log: Log) => {
    const cycleTime = log.cycle_time || props.device.cycle_time;
    const productionStartedAt = log.production_started_at || props.device.production_started_at;

    if (!productionStartedAt || cycleTime === 0) {
        return '-';
    }

    const productionStarted = new Date(productionStartedAt);
    const logTime = new Date(log.logged_at);
    const elapsedSeconds = Math.floor((logTime.getTime() - productionStarted.getTime()) / 1000);

    if (elapsedSeconds < 0) {
        return 0;
    }

    const expectedCounter = Math.floor(elapsedSeconds / cycleTime);
    return expectedCounter;
};

const getDelayStatus = (log: Log) => {
    const cycleTime = log.cycle_time || props.device.cycle_time;
    const productionStartedAt = log.production_started_at || props.device.production_started_at;
    const maxCount = log.max_count || props.device.max_count;

    if (!productionStartedAt || cycleTime === 0) {
        return {
            text: 'N/A',
            class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
        };
    }

    const productionStarted = new Date(productionStartedAt);
    const logTime = new Date(log.logged_at);
    const elapsedSeconds = Math.floor((logTime.getTime() - productionStarted.getTime()) / 1000);

    if (elapsedSeconds < 0) {
        return {
            text: 'Future Log',
            class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
        };
    }

    const actualCounter = log.counter_a;
    const isCompleted = actualCounter >= maxCount;

    if (isCompleted) {
        const actualTime = elapsedSeconds;
        const expectedTime = maxCount * cycleTime;
        const delaySeconds = actualTime - expectedTime;

        if (Math.abs(delaySeconds) <= cycleTime) {
            return {
                text: 'On Time',
                class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
            };
        } else if (delaySeconds > 0) {
            return {
                text: `Selesai ${formatTime(delaySeconds)} lebih lama`,
                class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
            };
        } else {
            return {
                text: `Selesai ${formatTime(Math.abs(delaySeconds))} lebih cepat`,
                class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
            };
        }
    }

    const expectedCounter = Math.floor(elapsedSeconds / cycleTime);
    const counterDiff = expectedCounter - actualCounter;
    const delaySeconds = counterDiff * cycleTime;

    if (Math.abs(delaySeconds) <= cycleTime) {
        return {
            text: 'On Time',
            class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
        };
    } else if (delaySeconds > 0) {
        return {
            text: `Delay ${formatTime(delaySeconds)}`,
            class: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
        };
    } else {
        return {
            text: `Ahead ${formatTime(Math.abs(delaySeconds))}`,
            class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
        };
    }
};

const getHistoryStatusClass = (status: string) => {
    if (status === 'on_time') {
        return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
    } else if (status === 'delayed') {
        return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
    } else {
        return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
    }
};

const getHistoryStatusText = (history: ProductionHistory) => {
    if (history.completion_status === 'on_time') {
        return 'On Time';
    } else if (history.completion_status === 'delayed') {
        return `Delay ${formatTime(history.delay_seconds)}`;
    } else {
        return `Lebih Cepat ${formatTime(Math.abs(history.delay_seconds))}`;
    }
};
</script>
<template>
    <Head :title="`Detail - ${device.device_id}`" />
    <AppLayout :breadcrumbs="[
        { title: 'ESP32 Monitor', href: '/esp32/monitor' },
        { title: device.device_id, href: `/esp32/monitor/${device.device_id}` }
    ]">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <Link
                        href="/esp32/monitor"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded-md"
                    >
                        <ArrowLeft class="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold flex items-center gap-2">
                            <Activity class="w-6 h-6 text-blue-600" />
                            {{ device.device_id }}
                        </h1>
                        <p class="text-sm text-gray-500">
                            Last update: {{ formatDateTime(device.last_update) }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="openSettingsModal"
                        class="flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700"
                    >
                        <Settings class="w-4 h-4" />
                        Settings
                    </button>
                    <button
                        @click="toggleAutoRefresh"
                        :class="[
                            'flex items-center gap-2 px-4 py-2 rounded-md transition-colors',
                            autoRefresh
                                ? 'bg-green-600 text-white hover:bg-green-700'
                                : 'bg-gray-600 text-white hover:bg-gray-700'
                        ]"
                    >
                        <RefreshCw :class="['w-4 h-4', autoRefresh ? 'animate-spin' : '']" />
                        {{ autoRefresh ? `Auto Refresh ON (${getRefreshIntervalText()})` : 'Auto Refresh OFF' }}
                    </button>
                    <button
                        @click="refreshData"
                        class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                        <RefreshCw class="w-4 h-4" />
                        Refresh
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">{{ device.has_counter_b ? 'Counter A' : 'Counter' }}</div>
                    <div class="text-3xl font-bold text-blue-600">{{ device.counter_a }}</div>
                    <div class="text-xs text-gray-500 mt-1">Max: {{ device.max_count }}</div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mt-2">
                        <div
                            class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                            :style="{ width: `${getProgressPercentage(device.counter_a, device.max_count)}%` }"
                        ></div>
                    </div>
                </div>

                <div v-if="device.has_counter_b" class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Stroke</div>
                    <div class="text-3xl font-bold text-purple-600">{{ device.counter_b }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ device.max_stroke > 0 ? `Max: ${device.max_stroke}` : 'Current value' }}</div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mt-2">
                        <div
                            class="bg-purple-600 h-2 rounded-full transition-all duration-300"
                            :style="{ width: `${getProgressPercentage(device.counter_b, device.max_stroke > 0 ? device.max_stroke : device.max_count)}%` }"
                        ></div>
                    </div>
                </div>

                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Reject</div>
                    <div class="text-3xl font-bold text-red-600">{{ device.reject }}</div>
                    <div class="text-xs text-gray-500 mt-1">Total rejects</div>
                </div>

                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Cycle Time</div>
                    <div class="text-3xl font-bold text-purple-600">{{ device.cycle_time }}s</div>
                    <div class="text-xs text-gray-500 mt-1">Per cycle</div>
                </div>

                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Loading Time</div>
                    <div class="text-2xl font-bold text-indigo-600">{{ formatTime(device.loading_time) }}</div>
                    <div class="text-xs text-gray-500 mt-1">Total time needed</div>
                </div>

                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Status</div>
                    <div v-if="device.production_started_at" class="space-y-2">
                        <div :class="['text-xl font-bold', getDelayColor(device.delay_seconds, device.is_completed, device.cycle_time)]">
                            {{ formatDelayTime(device.delay_seconds, device.is_completed, device.cycle_time) }}
                        </div>
                    </div>
                    <div v-else class="text-xl font-bold text-gray-400">
                        Not Started
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Progress</div>
                    <div class="flex items-center gap-2 mt-2">
                        <CheckCircle :class="['w-8 h-8', device.relay_status ? 'text-green-600' : 'text-gray-400']" />
                        <span :class="['text-2xl font-bold', device.relay_status ? 'text-green-600' : 'text-gray-400']">
                            {{ device.relay_status ? 'ON' : 'OFF' }}
                        </span>
                    </div>
                </div>

                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Error Status</div>
                    <div class="flex items-center gap-2 mt-2">
                        <AlertCircle :class="['w-8 h-8', device.error_b ? 'text-red-600' : 'text-gray-400']" />
                        <span :class="['text-2xl font-bold', device.error_b ? 'text-red-600' : 'text-gray-400']">
                            {{ device.error_b ? 'ERROR' : 'OK' }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="productionHistories.length > 0" class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg overflow-hidden">
                <div class="p-4 border-b border-sidebar-border">
                    <h2 class="text-lg font-semibold flex items-center gap-2">
                        <History class="w-5 h-5 text-purple-600" />
                        Production History ({{ productionHistories.length }} sessions)
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Started</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Finished</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Total Count</th>
                                <th v-if="device.has_counter_b" class="px-4 py-3 text-center text-sm font-semibold">Total Stroke</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Reject</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Duration</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="history in productionHistories"
                                :key="history.id"
                                class="border-b border-sidebar-border hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                            >
                                <td class="px-4 py-3 text-sm">{{ formatDateTime(history.production_started_at) }}</td>
                                <td class="px-4 py-3 text-sm">{{ formatDateTime(history.production_finished_at) }}</td>
                                <td class="px-4 py-3 text-center text-sm font-medium text-blue-600">
                                    {{ history.total_counter_a }} / {{ history.max_count }}
                                </td>
                                <td v-if="device.has_counter_b" class="px-4 py-3 text-center text-sm font-medium text-purple-600">
                                    {{ history.total_counter_b }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm font-medium text-red-600">
                                    {{ history.total_reject }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm">
                                    {{ formatTime(history.actual_time_seconds) }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm">
                                    <span :class="[
                                        'px-2 py-1 rounded text-xs font-semibold',
                                        getHistoryStatusClass(history.completion_status)
                                    ]">
                                        {{ getHistoryStatusText(history) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg overflow-hidden">
                <div class="p-4 border-b border-sidebar-border">
                    <h2 class="text-lg font-semibold flex items-center gap-2">
                        <Clock class="w-5 h-5 text-blue-600" />
                        History Logs ({{ logs.total }} records)
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Timestamp</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">{{ device.has_counter_b ? 'Counter A' : 'Counter' }}</th>
                                <th v-if="device.has_counter_b" class="px-4 py-3 text-center text-sm font-semibold">Stroke</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Expected</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Delay Status</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Reject</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Relay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="log in logs.data"
                                :key="log.id"
                                class="border-b border-sidebar-border hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                            >
                                <td class="px-4 py-3 text-sm">{{ formatDateTime(log.logged_at) }}</td>
                                <td class="px-4 py-3 text-center text-sm font-medium text-blue-600">
                                    {{ log.counter_a }}
                                </td>
                                <td v-if="device.has_counter_b" class="px-4 py-3 text-center text-sm font-medium text-purple-600">
                                    {{ log.counter_b }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-600">
                                    {{ calculateExpectedCounter(log) }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm">
                                    <span :class="[
                                        'px-2 py-1 rounded text-xs font-semibold',
                                        getDelayStatus(log).class
                                    ]">
                                        {{ getDelayStatus(log).text }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-sm font-medium text-red-600">
                                    {{ log.reject }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm">
                                    <span :class="[
                                        'px-2 py-1 rounded text-xs font-semibold',
                                        log.relay_status ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                                    ]">
                                        {{ log.relay_status ? 'ON' : 'OFF' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td :colspan="device.has_counter_b ? 7 : 6" class="px-4 py-8 text-center text-gray-500">
                                    Belum ada history log
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="showSettingsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showSettingsModal = false">
            <div class="bg-white dark:bg-sidebar rounded-lg p-6 w-96">
                <h3 class="text-lg font-bold mb-4">Device Settings - {{ device.device_id }}</h3>

                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Max Count</label>
                        <input
                            v-model.number="editMaxCount"
                            type="number"
                            class="w-full border border-sidebar-border rounded-md px-3 py-2 dark:bg-sidebar-accent"
                            placeholder="Max Count"
                        />
                    </div>

                    <div v-if="device.has_counter_b">
                        <label class="block text-sm font-medium mb-1">Max Stroke</label>
                        <input
                            v-model.number="editMaxStroke"
                            type="number"
                            class="w-full border border-sidebar-border rounded-md px-3 py-2 dark:bg-sidebar-accent"
                            placeholder="Max Stroke"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Reject Count</label>
                        <input
                            v-model.number="editReject"
                            type="number"
                            class="w-full border border-sidebar-border rounded-md px-3 py-2 dark:bg-sidebar-accent"
                            placeholder="Reject"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Cycle Time (seconds)</label>
                        <input
                            v-model.number="editCycleTime"
                            type="number"
                            class="w-full border border-sidebar-border rounded-md px-3 py-2 dark:bg-sidebar-accent"
                            placeholder="Cycle Time"
                        />
                    </div>

                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-md">
                        <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">Loading Time</div>
                        <div class="font-bold text-sm">{{ formatTime(editMaxCount * editCycleTime) }}</div>
                    </div>
                </div>

                <div class="flex gap-2 mt-4">
                    <button
                        @click="updateSettings"
                        class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
                    >
                        Update
                    </button>
                    <button
                        @click="showSettingsModal = false"
                        class="flex-1 bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
