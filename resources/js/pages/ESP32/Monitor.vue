<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import { Search, Activity, AlertCircle, CheckCircle, Eye, RefreshCw, Settings} from 'lucide-vue-next';

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
    filters: {
        search?: string;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const autoRefresh = ref(true);
let refreshInterval: number | null = null;

const showSettingsModal = ref(false);
const selectedDevice = ref<Device | null>(null);
const editMaxCount = ref(0);
const editMaxStroke = ref(0);
const editReject = ref(0);
const editCycleTime = ref(0);

const previousDevicesData = ref<string>('');
const currentRefreshInterval = ref(5000);
const noChangeCount = ref(0);
const lastActivityTime = ref(Date.now());

const search = () => {
    router.get('/esp32/monitor', {
        search: searchQuery.value
    }, { preserveState: true, preserveScroll: true });
};

const refreshData = () => {
    router.reload({
        only: ['devices'],
        onSuccess: () => {
            adaptRefreshInterval();
        }
    });
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
    const diffMs = now.getTime() - date.getTime();
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);

    if (diffSecs < 60) return `${diffSecs}s ago`;
    if (diffMins < 60) return `${diffMins}m ago`;
    return date.toLocaleString('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatTime = (seconds: number) => {
    const absSeconds = Math.abs(seconds);
    const hours = Math.floor(absSeconds / 3600);
    const minutes = Math.floor((absSeconds % 3600) / 60);
    const secs = absSeconds % 60;

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
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

const getDelayPercentage = (device: Device) => {
    if (!device.production_started_at || device.loading_time === 0) return 0;

    if (device.is_completed) {
        if (device.delay_seconds <= device.cycle_time) {
            return 100;
        }
        const percentage = Math.max(100 - (device.delay_seconds / device.loading_time * 100), 0);
        return Math.round(percentage);
    }

    if (device.delay_seconds <= 0) {
        return 100;
    }

    const percentage = Math.max(100 - (device.delay_seconds / device.loading_time * 100), 0);
    return Math.round(percentage);
};

const getDelayColor = (delaySeconds: number, isCompleted: boolean, cycleTime: number) => {
    if (Math.abs(delaySeconds) <= cycleTime) return 'text-green-600';
    if (isCompleted) {
        return delaySeconds > 0 ? 'text-red-600' : 'text-blue-600';
    } else {
        return delaySeconds > 0 ? 'text-orange-600' : 'text-blue-600';
    }
};

const getDelayBgColor = (delaySeconds: number, isCompleted: boolean, cycleTime: number) => {
    if (Math.abs(delaySeconds) <= cycleTime) return 'bg-green-600';
    if (isCompleted) {
        return delaySeconds > 0 ? 'bg-red-600' : 'bg-blue-600';
    } else {
        return delaySeconds > 0 ? 'bg-orange-600' : 'bg-blue-600';
    }
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

    const deviceId = selectedDevice.value.device_id;
    const updateData: any = {
        device_id: deviceId,
        max_count: editMaxCount.value,
        reject: editReject.value,
        cycle_time: editCycleTime.value
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

onMounted(() => {
    previousDevicesData.value = JSON.stringify(props.devices.data);

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
</script>
<template>
    <Head title="Robot Information" />
    <AppLayout :breadcrumbs="[
        { title: 'Robot Information', href: '/esp32/monitor' }
    ]">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Activity class="w-6 h-6 text-blue-600" />
                    Robot Information
                </h1>
                <div class="flex items-center gap-2">
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

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Devices</div>
                    <div class="text-2xl font-bold">{{ devices.total }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Active Progress</div>
                    <div class="text-2xl font-bold text-green-600">
                        {{ devices.data.filter(d => d.relay_status).length }}
                    </div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Errors</div>
                    <div class="text-2xl font-bold text-red-600">
                        {{ devices.data.filter(d => d.error_b).length }}
                    </div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Delayed</div>
                    <div class="text-2xl font-bold text-orange-600">
                        {{ devices.data.filter(d => d.is_delayed && !d.is_completed).length }}
                    </div>
                </div>
            </div>

            <div class="flex gap-2 items-center">
                <div class="flex-1 max-w-md flex gap-2">
                    <input
                        v-model="searchQuery"
                        @keyup.enter="search"
                        type="text"
                        placeholder="Cari device ID..."
                        class="flex-1 rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                    />
                    <button @click="search" class="p-2 bg-sidebar hover:bg-sidebar-accent rounded-md">
                        <Search class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                <div
                    v-for="device in devices.data"
                    :key="device.id"
                    class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-5 hover:shadow-lg transition-shadow"
                >
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="font-bold text-lg">{{ device.device_id }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ formatLastUpdate(device.last_update) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div v-if="device.error_b" class="flex items-center gap-1 text-red-600 text-xs">
                                <AlertCircle class="w-4 h-4" />
                                <span>Error</span>
                            </div>
                            <div v-else-if="device.relay_status" class="flex items-center gap-1 text-green-600 text-xs">
                                <CheckCircle class="w-4 h-4" />
                                <span>Active</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 space-y-2">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium">{{ device.has_counter_b ? 'Counter A' : 'Counter' }}</span>
                                <span class="font-bold">{{ device.counter_a }} / {{ device.max_count }}</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                                <div
                                    class="bg-green-600 h-2.5 rounded-full transition-all duration-300"
                                    :style="{ width: `${getProgressPercentage(device.counter_a, device.max_count)}%` }"
                                ></div>
                            </div>
                        </div>

                        <div v-if="device.has_counter_b">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium">Stroke</span>
                                <span class="font-bold">{{ device.counter_b }}{{ device.max_stroke > 0 ? ` / ${device.max_stroke}` : '' }}</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                                <div
                                    class="bg-purple-600 h-2.5 rounded-full transition-all duration-300"
                                    :style="{ width: `${getProgressPercentage(device.counter_b, device.max_stroke > 0 ? device.max_stroke : device.max_count)}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div v-if="device.production_started_at" class="mb-3">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="font-medium">Target Waktu</span>
                            <span :class="[
                                'font-semibold',
                                getDelayColor(device.delay_seconds, device.is_completed, device.cycle_time)
                            ]">
                                {{ formatDelayTime(device.delay_seconds, device.is_completed, device.cycle_time) }}
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                            <div
                                :class="[
                                    'h-2.5 rounded-full transition-all duration-300',
                                    getDelayBgColor(device.delay_seconds, device.is_completed, device.cycle_time)
                                ]"
                                :style="{ width: `${getDelayPercentage(device)}%` }"
                            ></div>
                        </div>
                        <div class="flex justify-between text-xs mt-1 text-gray-500">
                            <span>{{ device.is_completed ? 'Completed' : Math.abs(device.delay_seconds) <= device.cycle_time ? 'On Time' : device.delay_seconds > 0 ? 'Delayed' : 'Ahead' }}</span>
                            <span>{{ getDelayPercentage(device) }}%</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <div class="text-center py-2 rounded-md bg-gray-100 dark:bg-sidebar-accent">
                            <div class="text-xs text-gray-600 dark:text-gray-400">Reject</div>
                            <div class="font-bold text-sm text-red-600">{{ device.reject }}</div>
                        </div>
                        <div class="text-center py-2 rounded-md bg-gray-100 dark:bg-sidebar-accent">
                            <div class="text-xs text-gray-600 dark:text-gray-400">Cycle Time</div>
                            <div class="font-bold text-sm">{{ device.cycle_time }}s</div>
                        </div>
                        <div class="text-center py-2 rounded-md bg-gray-100 dark:bg-sidebar-accent">
                            <div class="text-xs text-gray-600 dark:text-gray-400">Loading</div>
                            <div class="font-bold text-sm">{{ formatTime(device.loading_time) }}</div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Link
                            :href="`/esp32/monitor/${device.device_id}`"
                            class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                        >
                            <Eye class="w-4 h-4" />
                            Detail
                        </Link>
                        <button
                            @click="openSettingsModal(device)"
                            class="px-3 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors"
                            title="Settings"
                        >
                            <Settings class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div v-if="devices.data.length === 0" class="col-span-full text-center py-12 text-gray-500">
                    <Activity class="w-16 h-16 mx-auto mb-4 opacity-50" />
                    <p>Tidak ada device yang terdaftar</p>
                </div>
            </div>
        </div>

        <div v-if="showSettingsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showSettingsModal = false">
            <div class="bg-white dark:bg-sidebar rounded-lg p-6 w-96">
                <h3 class="text-lg font-bold mb-4">Device Settings - {{ selectedDevice?.device_id }}</h3>

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

                    <div v-if="selectedDevice?.has_counter_b">
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
