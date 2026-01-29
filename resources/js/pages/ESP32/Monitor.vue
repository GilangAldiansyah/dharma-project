<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Search, Activity, AlertCircle, Eye, RefreshCw, Settings, Maximize2, Minimize2, Sun, Moon, Zap, TrendingUp } from 'lucide-vue-next';

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

interface Props {
    devices: {
        data: Device[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { search?: string; shift?: number };
    shifts: Array<{ value: number; label: string }>;
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const autoRefresh = ref(true);
let refreshInterval: number | null = null;
const showSettingsModal = ref(false);
const showResetConfirm = ref(false);
const selectedDevice = ref<Device | null>(null);
const editMaxCount = ref(0);
const editMaxStroke = ref(0);
const editReject = ref(0);
const editCycleTime = ref(0);
const previousDevicesData = ref<string>('');
const currentRefreshInterval = ref(5000);
const noChangeCount = ref(0);
const lastActivityTime = ref(Date.now());
const isFullscreen = ref(false);
const fullscreenDarkMode = ref(true);

const stats = computed(() => ({
    total: props.devices.total,
    active: props.devices.data.filter(d => d.relay_status).length,
    errors: props.devices.data.filter(d => d.error_b).length,
    delayed: props.devices.data.filter(d => d.is_delayed && !d.is_completed).length,
}));

const search = () => {
    router.get('/esp32/monitor', { search: searchQuery.value }, { preserveState: true, preserveScroll: true });
};

const refreshData = () => {
    router.reload({ only: ['devices'], onSuccess: () => adaptRefreshInterval() });
};

const adaptRefreshInterval = () => {
    const currentData = JSON.stringify(props.devices.data);
    if (previousDevicesData.value === currentData) {
        noChangeCount.value++;
    } else {
        noChangeCount.value = 0;
    }
    previousDevicesData.value = currentData;

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

const formatLastUpdate = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffSecs = Math.floor((now.getTime() - date.getTime()) / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    if (diffSecs < 60) return `${diffSecs}d`;
    if (diffMins < 60) return `${diffMins}m`;
    return date.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
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

const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value;
    if (isFullscreen.value) {
        document.documentElement.requestFullscreen?.();
    } else {
        document.exitFullscreen?.();
    }
};

const handleFullscreenChange = () => {
    if (!document.fullscreenElement) isFullscreen.value = false;
};

const openSettingsModal = (device: Device) => {
    selectedDevice.value = device;
    editMaxCount.value = device.max_count;
    editMaxStroke.value = device.max_stroke;
    editReject.value = device.reject;
    editCycleTime.value = device.cycle_time;
    showSettingsModal.value = true;
};
const updateSettings = () => {
    if (!selectedDevice.value) return;
    const updateData: any = {
        device_id: selectedDevice.value.device_id,
        max_count: editMaxCount.value,
        reject: editReject.value,
        cycle_time: editCycleTime.value,
        reset_counter: false,
    };
    if (selectedDevice.value.has_counter_b) {
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

const resetCounter = () => {
    if (!selectedDevice.value) return;
    router.post('/esp32/monitor/update-settings', {
        device_id: selectedDevice.value.device_id,
        max_count: selectedDevice.value.max_count,
        reject: selectedDevice.value.reject,
        cycle_time: selectedDevice.value.cycle_time,
        max_stroke: selectedDevice.value.max_stroke || 0,
        reset_counter: true,
    }, {
        preserveState: false,
        onSuccess: () => {
            showSettingsModal.value = false;
            showResetConfirm.value = false;
            refreshData();
        }
    });
};

onMounted(() => {
    previousDevicesData.value = JSON.stringify(props.devices.data);
    if (autoRefresh.value) {
        refreshInterval = window.setInterval(refreshData, currentRefreshInterval.value);
    }
    window.addEventListener('mousemove', resetActivity);
    window.addEventListener('keydown', resetActivity);
    window.addEventListener('click', resetActivity);
    document.addEventListener('fullscreenchange', handleFullscreenChange);
});

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
    window.removeEventListener('mousemove', resetActivity);
    window.removeEventListener('keydown', resetActivity);
    window.removeEventListener('click', resetActivity);
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
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
    <Head title="Robot Monitor" />
    <AppLayout v-if="!isFullscreen" :breadcrumbs="[{ title: 'Robot Monitor', href: '/esp32/monitor' }]">
        <div class="p-6 space-y-6 bg-white dark:from-gray-900 dark:to-gray-800 min-h-screen">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent flex items-center gap-3">
                        <Activity class="w-8 h-8 text-blue-600" />
                        Robot Monitor
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Real-time production monitoring</p>
                </div>
                <div class="flex gap-3">
                    <button @click="toggleFullscreen" class="px-4 py-2.5 bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium flex items-center gap-2">
                        <Maximize2 class="w-4 h-4" />
                        Fullscreen
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

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-blue-500 hover:shadow-xl transition-all duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Total Devices</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.total }}</p>
                        </div>
                        <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-xl">
                            <Activity class="w-6 h-6 text-blue-600" />
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-green-500 hover:shadow-xl transition-all duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Active</p>
                            <p class="text-3xl font-bold text-green-600 mt-1">{{ stats.active }}</p>
                        </div>
                        <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-xl">
                            <Zap class="w-6 h-6 text-green-600" />
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-red-500 hover:shadow-xl transition-all duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Errors</p>
                            <p class="text-3xl font-bold text-red-600 mt-1">{{ stats.errors }}</p>
                        </div>
                        <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-xl">
                            <AlertCircle class="w-6 h-6 text-red-600" />
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-orange-500 hover:shadow-xl transition-all duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Delayed</p>
                            <p class="text-3xl font-bold text-orange-600 mt-1">{{ stats.delayed }}</p>
                        </div>
                        <div class="p-3 bg-orange-100 dark:bg-orange-900/30 rounded-xl">
                            <TrendingUp class="w-6 h-6 text-orange-600" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <div class="relative flex-1 max-w-md">
                    <input v-model="searchQuery" @keyup.enter="search" type="text" placeholder="Cari device..." class="w-full pl-11 pr-4 py-3 rounded-xl border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                    <Search class="w-5 h-5 text-gray-400 absolute left-3.5 top-3.5" />
                </div>
                <button @click="search" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium">
                    Search
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                <div v-for="device in devices.data" :key="device.id" class="group bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 dark:border-gray-700 hover:-translate-y-1">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ device.device_id }}</h3>
                            <p class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                                {{ formatLastUpdate(device.last_update) }}
                            </p>
                        </div>
                        <div :class="['px-3 py-1.5 rounded-lg text-xs font-semibold', device.error_b ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : device.relay_status ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400']">
                            {{ device.error_b ? 'Error' : device.relay_status ? 'Active' : 'Idle' }}
                        </div>
                    </div>
                    <div class="space-y-3 mb-4">
                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ device.has_counter_b ? 'Counter A' : 'Counter' }}</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ device.counter_a }}<span class="text-gray-400">/{{ device.max_count }}</span></span>
                            </div>
                            <div class="relative w-full h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-r from-green-400 to-emerald-500 rounded-full transition-all duration-500 shadow-lg" :style="{ width: `${getProgressPercentage(device.counter_a, device.max_count)}%` }"></div>
                            </div>
                        </div>

                        <div v-if="device.has_counter_b">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">Stroke</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ device.counter_b }}<span class="text-gray-400">{{ device.max_stroke > 0 ? `/${device.max_stroke}` : '' }}</span></span>
                            </div>
                            <div class="relative w-full h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-r from-purple-400 to-pink-500 rounded-full transition-all duration-500 shadow-lg" :style="{ width: `${getProgressPercentage(device.counter_b, device.max_stroke > 0 ? device.max_stroke : device.max_count)}%` }"></div>
                            </div>
                        </div>

                        <div v-if="device.production_started_at" class="pt-2">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">Target</span>
                                <span :class="['font-bold text-sm', Math.abs(device.delay_seconds) <= device.cycle_time ? 'text-green-600' : device.is_completed ? (device.delay_seconds > 0 ? 'text-red-600' : 'text-blue-600') : (device.delay_seconds > 0 ? 'text-orange-600' : 'text-blue-600')]">
                                    {{ formatDelayTime(device.delay_seconds, device.is_completed, device.cycle_time) }}
                                </span>
                            </div>
                            <div class="relative w-full h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div :class="['absolute inset-0 rounded-full transition-all duration-500 shadow-lg', Math.abs(device.delay_seconds) <= device.cycle_time ? 'bg-gradient-to-r from-green-400 to-emerald-500' : device.is_completed ? (device.delay_seconds > 0 ? 'bg-gradient-to-r from-red-400 to-rose-500' : 'bg-gradient-to-r from-blue-400 to-cyan-500') : (device.delay_seconds > 0 ? 'bg-gradient-to-r from-orange-400 to-amber-500' : 'bg-gradient-to-r from-blue-400 to-cyan-500')]" :style="{ width: `${getDelayPercentage(device)}%` }"></div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div class="text-center p-3 bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 rounded-xl border border-red-100 dark:border-red-800">
                            <div class="text-xs text-gray-600 dark:text-gray-400 font-medium">Reject</div>
                            <div class="text-lg font-bold text-red-600 mt-1">{{ device.reject }}</div>
                        </div>
                        <div class="text-center p-3 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl border border-blue-100 dark:border-blue-800">
                            <div class="text-xs text-gray-600 dark:text-gray-400 font-medium">Cycle</div>
                            <div class="text-lg font-bold text-blue-600 mt-1">{{ device.cycle_time }}s</div>
                        </div>
                        <div class="text-center p-3 bg-gradient-to-br from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 rounded-xl border border-purple-100 dark:border-purple-800">
                            <div class="text-xs text-gray-600 dark:text-gray-400 font-medium">Load</div>
                            <div class="text-lg font-bold text-purple-600 mt-1">{{ formatTime(device.loading_time) }}</div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Link :href="`/esp32/monitor/${device.device_id}`" class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium">
                            <Eye class="w-4 h-4" />
                            Detail
                        </Link>
                        <button @click="openSettingsModal(device)" class="px-4 py-2.5 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl hover:shadow-lg transition-all duration-300">
                            <Settings class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <div v-else :class="['fixed inset-0 overflow-auto', fullscreenDarkMode ? 'bg-gray-900' : 'bg-gray-50']">
        <div class="min-h-screen p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 :class="['text-4xl font-bold flex items-center gap-3', fullscreenDarkMode ? 'text-white' : 'text-gray-900']">
                    <Activity class="w-10 h-10 text-blue-500" />
                    Robot Monitor Display
                </h1>
                <div class="flex gap-3">
                    <div :class="['px-5 py-3 rounded-xl font-semibold', fullscreenDarkMode ? 'bg-gray-800 text-white' : 'bg-white text-gray-900 shadow-lg']">
                        Auto: {{ currentRefreshInterval / 1000 }}s
                    </div>
                    <button @click="fullscreenDarkMode = !fullscreenDarkMode" :class="['px-5 py-3 rounded-xl font-semibold flex items-center gap-2', fullscreenDarkMode ? 'bg-yellow-500 text-white' : 'bg-gray-800 text-white']">
                        <component :is="fullscreenDarkMode ? Sun : Moon" class="w-5 h-5" />
                        {{ fullscreenDarkMode ? 'Light' : 'Dark' }}
                    </button>
                    <button @click="toggleFullscreen" class="px-5 py-3 bg-red-600 text-white rounded-xl font-semibold flex items-center gap-2 hover:bg-red-700">
                        <Minimize2 class="w-5 h-5" />
                        Exit
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <div v-for="device in devices.data" :key="device.id" :class="['rounded-2xl p-6 shadow-2xl', fullscreenDarkMode ? 'bg-gray-800' : 'bg-white']">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 :class="['text-2xl font-bold', fullscreenDarkMode ? 'text-white' : 'text-gray-900']">{{ device.device_id }}</h3>
                            <p :class="['text-sm mt-1', fullscreenDarkMode ? 'text-gray-400' : 'text-gray-600']">{{ formatLastUpdate(device.last_update) }}</p>
                        </div>
                        <div :class="['px-3 py-1.5 rounded-lg text-sm font-bold', device.error_b ? 'bg-red-500 text-white' : device.relay_status ? 'bg-green-500 text-white' : 'bg-gray-500 text-white']">
                            {{ device.error_b ? 'Error' : device.relay_status ? 'Active' : 'Idle' }}
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <div :class="['flex justify-between mb-2', fullscreenDarkMode ? 'text-gray-300' : 'text-gray-700']">
                                <span class="font-semibold">{{ device.has_counter_b ? 'Counter A' : 'Counter' }}</span>
                                <span class="font-bold text-lg">{{ device.counter_a }}/{{ device.max_count }}</span>
                            </div>
                            <div :class="['w-full h-4 rounded-full overflow-hidden', fullscreenDarkMode ? 'bg-gray-700' : 'bg-gray-200']">
                                <div class="h-full bg-gradient-to-r from-green-400 to-emerald-500 transition-all duration-500" :style="{ width: `${getProgressPercentage(device.counter_a, device.max_count)}%` }"></div>
                            </div>
                        </div>
                        <div v-if="device.has_counter_b">
                            <div :class="['flex justify-between mb-2', fullscreenDarkMode ? 'text-gray-300' : 'text-gray-700']">
                                <span class="font-semibold">Stroke</span>
                                <span class="font-bold text-lg">{{ device.counter_b }}{{ device.max_stroke > 0 ? `/${device.max_stroke}` : '' }}</span>
                            </div>
                            <div :class="['w-full h-4 rounded-full overflow-hidden', fullscreenDarkMode ? 'bg-gray-700' : 'bg-gray-200']">
                                <div class="h-full bg-gradient-to-r from-purple-400 to-pink-500 transition-all duration-500" :style="{ width: `${getProgressPercentage(device.counter_b, device.max_stroke > 0 ? device.max_stroke : device.max_count)}%` }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="showSettingsModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50" @click.self="showSettingsModal = false">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-96 shadow-2xl">
            <h3 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">Settings - {{ selectedDevice?.device_id }}</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Max Count</label>
                    <input v-model.number="editMaxCount" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200" />
                </div>
                <div v-if="selectedDevice?.has_counter_b">
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Max Stroke</label>
                    <input v-model.number="editMaxStroke" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200" />
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Reject</label>
                    <input v-model.number="editReject" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200" />
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Cycle Time (s)</label>
                    <input v-model.number="editCycleTime" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200" />
                </div>
            </div>
            <div class="flex gap-2 mt-6">
                <button @click="updateSettings" class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:shadow-lg transition-all">Update</button>
                <button @click="showResetConfirm = true" class="px-4 py-2.5 bg-red-600 text-white rounded-xl font-semibold hover:shadow-lg transition-all">Reset</button>
                <button @click="showSettingsModal = false" class="flex-1 bg-gray-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:shadow-lg transition-all">Cancel</button>
            </div>
        </div>
    </div>

    <div v-if="showResetConfirm" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-[60]" @click.self="showResetConfirm = false">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 max-w-sm shadow-2xl">
            <h3 class="text-xl font-bold mb-4 text-red-600">⚠️ Konfirmasi Reset</h3>
            <p class="mb-4 text-gray-700 dark:text-gray-300">Reset counter <strong>{{ selectedDevice?.device_id }}</strong> ke 0?</p>
            <div class="flex gap-2">
                <button @click="resetCounter" class="flex-1 bg-red-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:shadow-lg transition-all">Ya, Reset</button>
                <button @click="showResetConfirm = false" class="flex-1 bg-gray-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:shadow-lg transition-all">Batal</button>
            </div>
        </div>
    </div>
</template>
