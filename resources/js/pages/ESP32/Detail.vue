<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { ArrowLeft, Activity, CheckCircle, Clock, RefreshCw, Settings, History, PauseCircle, Plus, Trash2, ChevronLeft, ChevronRight, Database } from 'lucide-vue-next';
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
    is_paused: boolean;
    paused_at: string | null;
    total_pause_seconds: number;
    schedule_start_time: string;
    schedule_end_time: string;
    schedule_breaks: Array<{ start: string; end: string }>;
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
    is_paused: boolean;
    paused_at: string | null;
    total_pause_seconds: number;
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
        per_page: number;
        from: number;
        to: number;
    };
    productionHistories: {
        data: ProductionHistory[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        from: number;
        to: number;
    };
    shifts: Array<{ value:number; label:string }>;
    filters: {
        history_date?: string;
        history_shift?: string;
    };
}

const props = defineProps<Props>();

const autoRefresh = ref(true);
let refreshInterval: number | null = null;
const showSettingsModal = ref(false);
const showScheduleModal = ref(false);
const editMaxCount = ref(props.device.max_count);
const editMaxStroke = ref(props.device.max_stroke);
const editReject = ref(props.device.reject);
const editCycleTime = ref(props.device.cycle_time);
const previousDeviceData = ref<string>('');
const currentRefreshInterval = ref(5000);
const noChangeCount = ref(0);
const lastActivityTime = ref(Date.now());

const scheduleStartTime = ref(props.device.schedule_start_time.substring(0, 5));
const scheduleEndTime = ref(props.device.schedule_end_time.substring(0, 5));
const breaks = ref<Array<{ start: string; end: string }>>(props.device.schedule_breaks || []);
const newBreakStart = ref('');
const newBreakEnd = ref('');

const activeHistoryTab = ref<'history' | 'logs'>('history');
const historyDate = ref(props.filters?.history_date || '');
const historyShift = ref(props.filters?.history_shift || '');

const getCurrentTotalPauseSeconds = computed(() => {
    let totalPause = props.device.total_pause_seconds;

    if (props.device.is_paused && props.device.paused_at) {
        const pausedAt = new Date(props.device.paused_at);
        const now = new Date();
        const currentPauseSeconds = Math.floor((now.getTime() - pausedAt.getTime()) / 1000);
        totalPause += currentPauseSeconds;
    }

    return totalPause;
});

const isDeviceActive = computed(() => {
    const lastUpdate = new Date(props.device.last_update);
    const now = new Date();
    const diffMinutes = Math.floor((now.getTime() - lastUpdate.getTime()) / 1000 / 60);
    return diffMinutes < 5;
});

const parseTimeToDate = (timeStr: string, baseDate: Date = new Date()) => {
    const [hours, minutes] = timeStr.split(':').map(Number);
    const date = new Date(baseDate);
    date.setHours(hours, minutes, 0, 0);
    return date;
};
const getTimelineData = computed(() => {
    if (!props.device.production_started_at) {
        return null;
    }

    const actualStartTime = new Date(props.device.production_started_at);
    const baseDate = new Date(actualStartTime);
    baseDate.setHours(0, 0, 0, 0);

    const scheduleStart = parseTimeToDate(scheduleStartTime.value, baseDate);
    const scheduleEnd = parseTimeToDate(scheduleEndTime.value, baseDate);
    const currentTime = new Date();
    const lastUpdateTime = new Date(props.device.last_update);

    const isActive = isDeviceActive.value;

    const effectiveCurrentTime = isActive ? lastUpdateTime : lastUpdateTime;

    const totalScheduleDuration = Math.floor((scheduleEnd.getTime() - scheduleStart.getTime()) / 1000);
    const actualElapsed = Math.floor((currentTime.getTime() - actualStartTime.getTime()) / 1000);
    const netElapsed = actualElapsed - getCurrentTotalPauseSeconds.value;

    const expectedDuration = props.device.loading_time;
    const expectedFinishTime = new Date(actualStartTime.getTime() + (expectedDuration + getCurrentTotalPauseSeconds.value) * 1000);

    const productionStartPercentage = Math.max(0, Math.min(
        ((actualStartTime.getTime() - scheduleStart.getTime()) / (scheduleEnd.getTime() - scheduleStart.getTime())) * 100,
        100
    ));

    const currentPercentage = Math.max(0, Math.min(
        ((effectiveCurrentTime.getTime() - scheduleStart.getTime()) / (scheduleEnd.getTime() - scheduleStart.getTime())) * 100,
        100
    ));

    const lastActivityPercentage = Math.max(0, Math.min(
        ((lastUpdateTime.getTime() - scheduleStart.getTime()) / (scheduleEnd.getTime() - scheduleStart.getTime())) * 100,
        100
    ));

    const barWidth = currentPercentage - productionStartPercentage;

    const processedBreaks = breaks.value.map(brk => {
        const breakStart = parseTimeToDate(brk.start, baseDate);
        const breakEnd = parseTimeToDate(brk.end, baseDate);

        const breakStartPercentage = Math.max(0, Math.min(
            ((breakStart.getTime() - scheduleStart.getTime()) / (scheduleEnd.getTime() - scheduleStart.getTime())) * 100,
            100
        ));

        const breakEndPercentage = Math.max(0, Math.min(
            ((breakEnd.getTime() - scheduleStart.getTime()) / (scheduleEnd.getTime() - scheduleStart.getTime())) * 100,
            100
        ));

        return {
            startPercentage: breakStartPercentage,
            endPercentage: breakEndPercentage,
            width: breakEndPercentage - breakStartPercentage,
            startTime: breakStart,
            endTime: breakEnd
        };
    });

    return {
        scheduleStart,
        scheduleEnd,
        actualStartTime,
        currentTime,
        lastUpdateTime,
        isActive,
        expectedFinishTime,
        actualElapsed,
        netElapsed,
        totalScheduleDuration,
        productionStartPercentage,
        currentPercentage,
        lastActivityPercentage,
        barWidth,
        expectedDuration,
        breaks: processedBreaks,
        isOvertime: netElapsed > expectedDuration
    };
});

const formatTimeOnly = (date: Date) => {
    return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};

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

const goToPage = (page: number) => {
    router.get(`/esp32/monitor/${props.device.device_id}`, { page }, {
        preserveState: true,
        preserveScroll: false,
        only: ['logs']
    });
};

const goToHistoryPage = (page: number) => {
    router.get(`/esp32/monitor/${props.device.device_id}`, {
        history_page: page,
        history_date: historyDate.value,
        history_shift: historyShift.value
    }, {
        preserveState: true,
        preserveScroll: false,
        only: ['productionHistories']
    });
};

const filterHistory = () => {
    router.get(`/esp32/monitor/${props.device.device_id}`, {
        history_date: historyDate.value,
        history_shift: historyShift.value
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['productionHistories']
    });
};

const clearHistoryFilters = () => {
    historyDate.value = '';
    historyShift.value = '';
    router.get(`/esp32/monitor/${props.device.device_id}`, {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['productionHistories']
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
        is_paused: props.device.is_paused,
        total_pause_seconds: props.device.total_pause_seconds,
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

const formatDateTimeStacked = (dateString: string) => {
    const date = new Date(dateString);
    const dateStr = date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
    const timeStr = date.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit'
    });
    return { date: dateStr, time: timeStr };
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

const openSettingsModal = () => {
    editMaxCount.value = props.device.max_count;
    editMaxStroke.value = props.device.max_stroke;
    editReject.value = props.device.reject;
    editCycleTime.value = props.device.cycle_time;
    showSettingsModal.value = true;
};

const openScheduleModal = () => {
    showScheduleModal.value = true;
};

const saveSchedule = () => {
    router.post('/esp32/monitor/update-schedule', {
        device_id: props.device.device_id,
        schedule_start_time: scheduleStartTime.value,
        schedule_end_time: scheduleEndTime.value,
        schedule_breaks: breaks.value
    }, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
            showScheduleModal.value = false;
        }
    });
};

const addBreak = () => {
    if (newBreakStart.value && newBreakEnd.value) {
        breaks.value.push({
            start: newBreakStart.value,
            end: newBreakEnd.value
        });
        breaks.value.sort((a, b) => a.start.localeCompare(b.start));
        newBreakStart.value = '';
        newBreakEnd.value = '';
    }
};

const removeBreak = (index: number) => {
    breaks.value.splice(index, 1);
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
const calculateExpectedCounter = (log: Log) => {
    const cycleTime = log.cycle_time || props.device.cycle_time;
    const productionStartedAt = log.production_started_at;

    if (!productionStartedAt || cycleTime === 0 || log.counter_a === 0) return 0;

    const productionStarted = new Date(productionStartedAt);
    const logTime = new Date(log.logged_at);
    const elapsedSeconds = Math.floor((logTime.getTime() - productionStarted.getTime()) / 1000);

    if (elapsedSeconds < 0) return 0;

    const totalPauseAtLogTime = log.total_pause_seconds || 0;
    const netElapsedSeconds = elapsedSeconds - totalPauseAtLogTime;

    if (netElapsedSeconds < 0) return 0;

    return Math.max(0, Math.floor(netElapsedSeconds / cycleTime));
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

    const totalPauseAtLogTime = log.total_pause_seconds || 0;
    const netElapsedSeconds = elapsedSeconds - totalPauseAtLogTime;
    const actualCounter = log.counter_a;
    const isCompleted = actualCounter >= maxCount;

    if (isCompleted) {
        const actualTime = netElapsedSeconds;
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

    const expectedCounter = Math.floor(netElapsedSeconds / cycleTime);
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

onMounted(() => {
    previousDeviceData.value = JSON.stringify({
        counter_a: props.device.counter_a,
        counter_b: props.device.counter_b,
        reject: props.device.reject,
        relay_status: props.device.relay_status,
        error_b: props.device.error_b,
        last_update: props.device.last_update,
        is_paused: props.device.is_paused,
        total_pause_seconds: props.device.total_pause_seconds,
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
</script>
<template>
    <Head :title="`Detail - ${device.device_id}`" />
    <AppLayout :breadcrumbs="[
        { title: 'Robot Monitor', href: '/esp32/monitor' },
        { title: device.device_id, href: `/esp32/monitor/${device.device_id}` }
    ]">
        <div class="p-6 space-y-6 bg-white dark:bg-gray-900 min-h-screen">
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
                    <button @click="openScheduleModal" class="px-4 py-2.5 bg-gradient-to-r from-green-600 to-teal-600 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium flex items-center gap-2">
                        <Clock class="w-4 h-4" />
                        Schedule
                    </button>
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

            <div v-if="device.is_paused" class="bg-gradient-to-r from-blue-50 to-cyan-50 dark:from-blue-900/20 dark:to-cyan-900/20 border-2 border-blue-300 dark:border-blue-700 rounded-2xl p-5 shadow-lg">
                <div class="flex items-center gap-3">
                    <PauseCircle class="w-8 h-8 text-blue-600" />
                    <div>
                        <h3 class="font-bold text-blue-800 dark:text-blue-300 text-lg">Production Paused</h3>
                        <p class="text-sm text-blue-600 dark:text-blue-400 mt-1">
                            Total pause time: <span class="font-mono font-bold">{{ formatTime(getCurrentTotalPauseSeconds) }}</span>
                            <span v-if="device.paused_at" class="ml-2">| Paused at: {{ formatDateTime(device.paused_at) }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
                    <div class="space-y-6">
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

                        <div v-if="getTimelineData">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">Production Timeline</span>
                                <span :class="['font-bold text-sm', Math.abs(device.delay_seconds) <= device.cycle_time ? 'text-green-600' : device.is_completed ? (device.delay_seconds > 0 ? 'text-red-600' : 'text-blue-600') : (device.delay_seconds > 0 ? 'text-orange-600' : 'text-blue-600')]">
                                    {{ formatDelayTime(device.delay_seconds, device.is_completed, device.cycle_time) }}
                                </span>
                            </div>

                            <div class="space-y-1">
                                <div class="relative w-full h-4 bg-gray-200 dark:bg-gray-700 rounded-full overflow-visible">
                                    <div v-for="(brk, idx) in getTimelineData.breaks" :key="`break-${idx}`"
                                        class="absolute h-full bg-orange-200 dark:bg-orange-800 rounded-full"
                                        :style="{
                                            left: `${brk.startPercentage}%`,
                                            width: `${brk.width}%`
                                        }"
                                    ></div>

                                    <div
                                        :class="['absolute h-full rounded-full transition-all duration-500 shadow-lg', getTimelineData.isOvertime ? 'bg-gradient-to-r from-orange-400 to-red-500' : 'bg-gradient-to-r from-green-400 to-emerald-500']"
                                        :style="{
                                            left: `${getTimelineData.productionStartPercentage}%`,
                                            width: `${getTimelineData.barWidth}%`
                                        }"
                                    ></div>

                                    <div class="absolute top-0 left-0 h-full w-1 bg-gray-600 rounded-full cursor-pointer hover:w-1.5 transition-all group/marker z-10">
                                        <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover/marker:opacity-100 transition-opacity z-20 pointer-events-none">
                                            <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                <div class="font-medium">Schedule Start</div>
                                                <div class="text-[10px] text-gray-300">{{ formatTimeOnly(getTimelineData.scheduleStart) }}</div>
                                                <div class="absolute top-full left-1/2 transform -translate-x-1/2">
                                                    <div class="border-4 border-transparent border-t-gray-900"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="absolute top-0 right-0 h-full w-1 bg-gray-600 rounded-full cursor-pointer hover:w-1.5 transition-all group/marker z-10">
                                        <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover/marker:opacity-100 transition-opacity z-20 pointer-events-none">
                                            <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                <div class="font-medium">Schedule End</div>
                                                <div class="text-[10px] text-gray-300">{{ formatTimeOnly(getTimelineData.scheduleEnd) }}</div>
                                                <div class="absolute top-full left-1/2 transform -translate-x-1/2">
                                                    <div class="border-4 border-transparent border-t-gray-900"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="absolute top-0 h-full w-1 bg-green-600 rounded-full cursor-pointer hover:w-1.5 transition-all group/marker z-10"
                                        :style="{ left: `${getTimelineData.productionStartPercentage}%` }">
                                        <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover/marker:opacity-100 transition-opacity z-20 pointer-events-none">
                                            <div class="bg-green-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                <div class="font-medium">Actual Start</div>
                                                <div class="text-[10px] text-green-300">{{ formatTimeOnly(getTimelineData.actualStartTime) }}</div>
                                                <div class="absolute top-full left-1/2 transform -translate-x-1/2">
                                                    <div class="border-4 border-transparent border-t-green-900"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="!getTimelineData.isActive" class="absolute top-0 h-full w-1 bg-red-600 rounded-full cursor-pointer hover:w-1.5 transition-all group/marker z-10"
                                        :style="{ left: `${getTimelineData.lastActivityPercentage}%` }">
                                        <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover/marker:opacity-100 transition-opacity z-20 pointer-events-none">
                                            <div class="bg-red-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                <div class="font-medium">Last Activity</div>
                                                <div class="text-[10px] text-red-300">{{ formatTimeOnly(getTimelineData.lastUpdateTime) }}</div>
                                                <div class="absolute top-full left-1/2 transform -translate-x-1/2">
                                                    <div class="border-4 border-transparent border-t-red-900"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-for="(brk, idx) in getTimelineData.breaks" :key="`break-marker-${idx}`">
                                        <div class="absolute top-0 h-full w-1 bg-orange-500 rounded-full cursor-pointer hover:w-1.5 transition-all group/marker z-10"
                                            :style="{ left: `${brk.startPercentage}%` }">
                                            <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover/marker:opacity-100 transition-opacity z-20 pointer-events-none">
                                                <div class="bg-orange-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                    <div class="font-medium">Break {{ idx + 1 }} Start</div>
                                                    <div class="text-[10px] text-orange-300">{{ formatTimeOnly(brk.startTime) }}</div>
                                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2">
                                                        <div class="border-4 border-transparent border-t-orange-900"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="absolute top-0 h-full w-1 bg-orange-500 rounded-full cursor-pointer hover:w-1.5 transition-all group/marker z-10"
                                            :style="{ left: `${brk.endPercentage}%` }">
                                            <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover/marker:opacity-100 transition-opacity z-20 pointer-events-none">
                                                <div class="bg-orange-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                    <div class="font-medium">Break {{ idx + 1 }} End</div>
                                                    <div class="text-[10px] text-orange-300">{{ formatTimeOnly(brk.endTime) }}</div>
                                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2">
                                                        <div class="border-4 border-transparent border-t-orange-900"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-between text-[10px] text-gray-600 dark:text-gray-400 font-medium px-1">
                                    <span>{{ formatTimeOnly(getTimelineData.scheduleStart) }}</span>
                                    <span v-if="!device.is_completed && getTimelineData.isActive" class="text-blue-600 dark:text-blue-400 font-bold">Now: {{ formatTimeOnly(getTimelineData.lastUpdateTime) }}</span>
                                    <span v-if="!device.is_completed && !getTimelineData.isActive" class="text-red-600 dark:text-red-400 font-bold">Idle</span>
                                    <span>{{ formatTimeOnly(getTimelineData.scheduleEnd) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4">
                            <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-red-500">
                                <div class="text-sm text-gray-600 dark:text-gray-400 font-medium">NG</div>
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
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <button
                        @click="activeHistoryTab = 'history'"
                        :class="[
                            'flex-1 px-6 py-4 font-semibold transition-all flex items-center justify-center gap-2',
                            activeHistoryTab === 'history'
                                ? 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 border-b-2 border-purple-600'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                        ]"
                    >
                        <History class="w-5 h-5" />
                        Production History
                    </button>
                    <button
                        @click="activeHistoryTab = 'logs'"
                        :class="[
                            'flex-1 px-6 py-4 font-semibold transition-all flex items-center justify-center gap-2',
                            activeHistoryTab === 'logs'
                                ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 border-b-2 border-blue-600'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                        ]"
                    >
                        <Database class="w-5 h-5" />
                        History Logs
                    </button>
                </div>

                <div v-show="activeHistoryTab === 'history'" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <input
                            v-model="historyDate"
                            type="date"
                            @change="filterHistory"
                            class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                        />
                        <select
                            v-model="historyShift"
                            @change="filterHistory"
                            class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                        >
                            <option value="">Semua Shift</option>
                            <option v-for="shift in shifts" :key="shift.value" :value="shift.value">{{ shift.label }}</option>
                        </select>
                        <button @click="clearHistoryFilters" class="px-4 py-3 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all font-medium">
                            Reset
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 border-b-2 border-gray-200 dark:border-gray-600">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Started</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Finished</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Shift</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Total Count</th>
                                    <th v-if="device.has_counter_b" class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Total Stroke</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">NG</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="history in productionHistories.data" :key="history.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDateTimeStacked(history.production_started_at).date }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ formatDateTimeStacked(history.production_started_at).time }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDateTimeStacked(history.production_finished_at).date }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ formatDateTimeStacked(history.production_finished_at).time }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span :class="['px-3 py-1.5 rounded-full text-xs font-semibold', getShiftBadgeColor(history.shift)]">
                                            {{ history.shift_label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="text-lg font-bold text-blue-600">{{ history.total_counter_a }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">/ {{ history.max_count }}</div>
                                    </td>
                                    <td v-if="device.has_counter_b" class="px-6 py-4 text-center">
                                        <div class="text-lg font-bold text-purple-600">{{ history.total_counter_b }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="text-lg font-bold text-red-600">{{ history.total_reject }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ formatTime(history.actual_time_seconds) }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span :class="['px-4 py-2 rounded-lg text-xs font-bold', getHistoryStatusClass(history.completion_status)]">
                                            {{ getHistoryStatusText(history) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="productionHistories.data.length === 0">
                                    <td :colspan="device.has_counter_b ? 8 : 7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        Tidak ada data production history
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="productionHistories.last_page > 1" class="mt-6 flex justify-center gap-2">
                        <button
                            v-for="page in productionHistories.last_page"
                            :key="page"
                            @click="goToHistoryPage(page)"
                            :class="[
                                'px-4 py-2 rounded-lg font-medium transition-all',
                                page === productionHistories.current_page
                                    ? 'bg-purple-600 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                            ]"
                        >
                            {{ page }}
                        </button>
                    </div>
                </div>
                <div v-show="activeHistoryTab === 'logs'" class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 border-b-2 border-gray-200 dark:border-gray-600">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Timestamp</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Shift</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">{{ device.has_counter_b ? 'Counter A' : 'Counter' }}</th>
                                    <th v-if="device.has_counter_b" class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Stroke</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Expected</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Pause Time</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Delay Status</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">NG</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Relay</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDateTimeStacked(log.logged_at).date }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ formatDateTimeStacked(log.logged_at).time }}</div>
                                        <span v-if="log.is_paused" class="inline-flex items-center mt-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                            <PauseCircle class="w-3 h-3 mr-1" />
                                            Paused
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span :class="['px-3 py-1.5 rounded-full text-xs font-semibold', getShiftBadgeColor(log.shift)]">
                                            {{ log.shift_label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="text-lg font-bold text-blue-600">{{ log.counter_a }}</div>
                                    </td>
                                    <td v-if="device.has_counter_b" class="px-6 py-4 text-center">
                                        <div class="text-lg font-bold text-purple-600">{{ log.counter_b }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ calculateExpectedCounter(log) }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div v-if="log.total_pause_seconds > 0" class="space-y-1">
                                            <div class="text-sm font-bold text-blue-600 dark:text-blue-400">
                                                {{ formatTime(log.total_pause_seconds) }}
                                            </div>
                                            <div v-if="log.is_paused && log.paused_at" class="text-xs text-gray-500 dark:text-gray-400">
                                                Since: {{ formatDateTime(log.paused_at).split(' ')[1] }}
                                            </div>
                                        </div>
                                        <div v-else class="text-xs text-gray-400">-</div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span :class="['px-4 py-2 rounded-lg text-xs font-bold', getDelayStatus(log).class]">
                                            {{ getDelayStatus(log).text }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="text-lg font-bold text-red-600">{{ log.reject }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span :class="['px-4 py-2 rounded-lg text-xs font-bold', log.relay_status ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400']">
                                            {{ log.relay_status ? 'ON' : 'OFF' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="logs.data.length === 0">
                                    <td :colspan="device.has_counter_b ? 9 : 8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        Belum ada history log
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="logs.last_page > 1" class="mt-6 flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Showing {{ logs.from }} to {{ logs.to }} of {{ logs.total }} entries
                        </div>
                        <div class="flex gap-2">
                            <button @click="goToPage(logs.current_page - 1)" :disabled="logs.current_page === 1" :class="['px-3 py-2 rounded-lg font-medium flex items-center gap-1', logs.current_page === 1 ? 'bg-gray-200 dark:bg-gray-700 text-gray-400 cursor-not-allowed' : 'bg-blue-600 text-white hover:bg-blue-700']">
                                <ChevronLeft class="w-4 h-4" />
                                Previous
                            </button>
                            <div class="flex gap-1">
                                <button v-for="page in logs.last_page" :key="page" @click="goToPage(page)" :class="['px-4 py-2 rounded-lg font-medium', page === logs.current_page ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600']">
                                    {{ page }}
                                </button>
                            </div>
                            <button @click="goToPage(logs.current_page + 1)" :disabled="logs.current_page === logs.last_page" :class="['px-3 py-2 rounded-lg font-medium flex items-center gap-1', logs.current_page === logs.last_page ? 'bg-gray-200 dark:bg-gray-700 text-gray-400 cursor-not-allowed' : 'bg-blue-600 text-white hover:bg-blue-700']">
                                Next
                                <ChevronRight class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
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
                        <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">NG</label>
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

        <div v-if="showScheduleModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50" @click.self="showScheduleModal = false">
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-[600px] shadow-2xl max-h-[90vh] overflow-y-auto">
                <h3 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">Production Schedule - {{ device.device_id }}</h3>

                <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-200 dark:border-green-800">
                    <p class="text-sm text-green-800 dark:text-green-300">
                        Set jadwal produksi dan waktu break. Timeline akan menampilkan garis di setiap waktu yang Anda tentukan.
                    </p>
                </div>

                <div class="space-y-4 mb-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Start Time</label>
                            <input v-model="scheduleStartTime" type="time" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">End Time</label>
                            <input v-model="scheduleEndTime" type="time" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <h4 class="font-bold text-gray-900 dark:text-white mb-3">Break Times</h4>

                    <div class="space-y-3 mb-4">
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Break Start</label>
                                <input v-model="newBreakStart" type="time" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Break End</label>
                                <input v-model="newBreakEnd" type="time" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                            </div>
                            <div class="flex items-end">
                                <button @click="addBreak" class="px-4 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all font-semibold flex items-center gap-2">
                                    <Plus class="w-4 h-4" />
                                    Add
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div v-for="(brk, index) in breaks" :key="index" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
                            <div class="flex items-center gap-3 flex-1">
                                <div class="flex items-center justify-center w-8 h-8 bg-orange-600 text-white rounded-full font-bold text-sm">
                                    {{ index + 1 }}
                                </div>
                                <div class="font-semibold text-gray-900 dark:text-white">
                                    {{ brk.start }} - {{ brk.end }}
                                </div>
                            </div>
                            <button @click="removeBreak(index)" class="p-2 text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-lg transition-all">
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                        <div v-if="breaks.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                            Belum ada break time. Tambahkan waktu istirahat untuk menandai di timeline.
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button @click="saveSchedule" class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:shadow-lg transition-all">
                        Save Schedule
                    </button>
                    <button @click="showScheduleModal = false" class="flex-1 bg-gray-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:shadow-lg transition-all">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
