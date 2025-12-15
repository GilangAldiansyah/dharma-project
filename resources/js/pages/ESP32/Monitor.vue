<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import { Search, Activity, AlertCircle, CheckCircle, Eye, RefreshCw } from 'lucide-vue-next';

interface Device {
    id: number;
    device_id: string;
    counter_a: number;
    counter_b: number;
    max_count: number;
    relay_status: boolean;
    error_b: boolean;
    last_update: string;
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

const search = () => {
    router.get('/esp32/monitor', {
        search: searchQuery.value
    }, { preserveState: true, preserveScroll: true });
};

const refreshData = () => {
    router.reload({ only: ['devices'] });
};


const getProgressPercentage = (counter: number, max: number) => {
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

onMounted(() => {
    if (autoRefresh.value) {
        refreshInterval = window.setInterval(refreshData, 5000);
    }
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});

const toggleAutoRefresh = () => {
    autoRefresh.value = !autoRefresh.value;

    if (autoRefresh.value) {
        refreshInterval = window.setInterval(refreshData, 5000);
    } else if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
};
</script>

<template>
    <Head title="ESP32 Monitor" />
    <AppLayout :breadcrumbs="[
        { title: 'ESP32 Monitor', href: '/esp32/monitor' }
    ]">
        <div class="p-4 space-y-4">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Activity class="w-6 h-6 text-blue-600" />
                    ESP32 Device Monitor
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
                        {{ autoRefresh ? 'Auto Refresh ON' : 'Auto Refresh OFF' }}
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

            <!-- Stats Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Devices</div>
                    <div class="text-2xl font-bold">{{ devices.total }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Active Relays</div>
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
                    <div class="text-sm text-gray-600 dark:text-gray-400">Completed</div>
                    <div class="text-2xl font-bold text-blue-600">
                        {{ devices.data.filter(d => d.counter_a >= d.max_count && d.counter_b >= d.max_count).length }}
                    </div>
                </div>
            </div>

            <!-- Search -->
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

            <!-- Devices Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                <div
                    v-for="device in devices.data"
                    :key="device.id"
                    class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-5 hover:shadow-lg transition-shadow"
                >
                    <!-- Device Header -->
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

                    <!-- Counter A -->
                    <div class="mb-3">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium">Counter A</span>
                            <span class="font-bold">{{ device.counter_a }} / {{ device.max_count }}</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                            <div
                                class="bg-blue-600 h-2.5 rounded-full transition-all duration-300"
                                :style="{ width: `${getProgressPercentage(device.counter_a, device.max_count)}%` }"
                            ></div>
                        </div>
                    </div>

                    <!-- Counter B -->
                    <div class="mb-4">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium">Counter B</span>
                            <span class="font-bold">{{ device.counter_b }} / {{ device.max_count }}</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                            <div
                                class="bg-green-600 h-2.5 rounded-full transition-all duration-300"
                                :style="{ width: `${getProgressPercentage(device.counter_b, device.max_count)}%` }"
                            ></div>
                        </div>
                    </div>

                    <!-- Status Indicators -->
                    <div class="flex gap-2 mb-4">
                        <div class="flex-1 text-center py-2 rounded-md bg-gray-100 dark:bg-sidebar-accent">
                            <div class="text-xs text-gray-600 dark:text-gray-400">Relay</div>
                            <div :class="['font-bold text-sm', device.relay_status ? 'text-green-600' : 'text-gray-400']">
                                {{ device.relay_status ? 'ON' : 'OFF' }}
                            </div>
                        </div>
                        <div class="flex-1 text-center py-2 rounded-md bg-gray-100 dark:bg-sidebar-accent">
                            <div class="text-xs text-gray-600 dark:text-gray-400">Error B</div>
                            <div :class="['font-bold text-sm', device.error_b ? 'text-red-600' : 'text-gray-400']">
                                {{ device.error_b ? 'YES' : 'NO' }}
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <Link
                        :href="`/esp32/monitor/${device.device_id}`"
                        class="flex items-center justify-center gap-2 w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                    >
                        <Eye class="w-4 h-4" />
                        Lihat Detail
                    </Link>
                </div>

                <!-- Empty State -->
                <div v-if="devices.data.length === 0" class="col-span-full text-center py-12 text-gray-500">
                    <Activity class="w-16 h-16 mx-auto mb-4 opacity-50" />
                    <p>Tidak ada device yang terdaftar</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
