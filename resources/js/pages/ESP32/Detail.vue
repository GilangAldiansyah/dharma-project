<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { ArrowLeft, Activity, AlertCircle, CheckCircle, Clock, BarChart3, RefreshCw } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

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

interface Log {
    id: number;
    device_id: string;
    counter_a: number;
    counter_b: number;
    max_count: number;
    relay_status: boolean;
    error_b: boolean;
    logged_at: string;
}

interface Props {
    device: Device;
    logs: {
        data: Log[];
        current_page: number;
        last_page: number;
        total: number;
    };
}

const props = defineProps<Props>();

const autoRefresh = ref(true);
let refreshInterval: number | null = null;

const refreshData = () => {
    router.reload({ only: ['device', 'logs'] });
};

const getProgressPercentage = (counter: number, max: number) => {
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

const chartData = computed(() => {
    // Ambil 20 log terakhir untuk chart
    const recentLogs = props.logs.data.slice(0, 20).reverse();
    return {
        labels: recentLogs.map(log => {
            const date = new Date(log.logged_at);
            return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        }),
        counterA: recentLogs.map(log => log.counter_a),
        counterB: recentLogs.map(log => log.counter_b),
    };
});

const maxChartValue = computed(() => {
    const allValues = [...chartData.value.counterA, ...chartData.value.counterB];
    return Math.max(...allValues, props.device.max_count);
});

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
    <Head :title="`Detail - ${device.device_id}`" />
    <AppLayout :breadcrumbs="[
        { title: 'ESP32 Monitor', href: '/esp32/monitor' },
        { title: device.device_id, href: `/esp32/monitor/${device.device_id}` }
    ]">
        <div class="p-4 space-y-4">
            <!-- Header -->
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

            <!-- Current Status -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Counter A</div>
                    <div class="text-3xl font-bold text-blue-600">{{ device.counter_a }}</div>
                    <div class="text-xs text-gray-500 mt-1">Max: {{ device.max_count }}</div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mt-2">
                        <div
                            class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                            :style="{ width: `${getProgressPercentage(device.counter_a, device.max_count)}%` }"
                        ></div>
                    </div>
                </div>

                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Counter B</div>
                    <div class="text-3xl font-bold text-green-600">{{ device.counter_b }}</div>
                    <div class="text-xs text-gray-500 mt-1">Max: {{ device.max_count }}</div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mt-2">
                        <div
                            class="bg-green-600 h-2 rounded-full transition-all duration-300"
                            :style="{ width: `${getProgressPercentage(device.counter_b, device.max_count)}%` }"
                        ></div>
                    </div>
                </div>

                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Relay Status</div>
                    <div class="flex items-center gap-2 mt-2">
                        <CheckCircle :class="['w-8 h-8', device.relay_status ? 'text-green-600' : 'text-gray-400']" />
                        <span :class="['text-2xl font-bold', device.relay_status ? 'text-green-600' : 'text-gray-400']">
                            {{ device.relay_status ? 'ON' : 'OFF' }}
                        </span>
                    </div>
                </div>

                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Error Status B</div>
                    <div class="flex items-center gap-2 mt-2">
                        <AlertCircle :class="['w-8 h-8', device.error_b ? 'text-red-600' : 'text-gray-400']" />
                        <span :class="['text-2xl font-bold', device.error_b ? 'text-red-600' : 'text-gray-400']">
                            {{ device.error_b ? 'ERROR' : 'OK' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Simple Line Chart -->
            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <BarChart3 class="w-5 h-5 text-blue-600" />
                    Counter Trend (Last 20 Logs)
                </h2>
                <div class="relative h-64">
                    <svg class="w-full h-full" viewBox="0 0 800 300" preserveAspectRatio="none">
                        <!-- Grid Lines -->
                        <line v-for="i in 5" :key="`grid-${i}`"
                            :x1="0" :y1="i * 60" :x2="800" :y2="i * 60"
                            stroke="currentColor"
                            class="text-gray-200 dark:text-gray-700"
                            stroke-width="1"
                        />

                        <!-- Counter A Line (Blue) -->
                        <polyline
                            v-if="chartData.counterA.length > 0"
                            :points="chartData.counterA.map((val, idx) =>
                                `${(idx / (chartData.counterA.length - 1)) * 800},${300 - (val / maxChartValue) * 280}`
                            ).join(' ')"
                            fill="none"
                            stroke="#2563eb"
                            stroke-width="3"
                        />

                        <!-- Counter B Line (Green) -->
                        <polyline
                            v-if="chartData.counterB.length > 0"
                            :points="chartData.counterB.map((val, idx) =>
                                `${(idx / (chartData.counterB.length - 1)) * 800},${300 - (val / maxChartValue) * 280}`
                            ).join(' ')"
                            fill="none"
                            stroke="#16a34a"
                            stroke-width="3"
                        />
                    </svg>
                </div>
                <div class="flex justify-center gap-6 mt-4 text-sm">
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 bg-blue-600 rounded"></div>
                        <span>Counter A</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 bg-green-600 rounded"></div>
                        <span>Counter B</span>
                    </div>
                </div>
            </div>

            <!-- History Logs -->
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
                                <th class="px-4 py-3 text-center text-sm font-semibold">Counter A</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Counter B</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Max Count</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Relay</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Error B</th>
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
                                <td class="px-4 py-3 text-center text-sm font-medium text-green-600">
                                    {{ log.counter_b }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm">{{ log.max_count }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="[
                                        'inline-flex px-2 py-1 rounded-full text-xs font-semibold',
                                        log.relay_status
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400'
                                    ]">
                                        {{ log.relay_status ? 'ON' : 'OFF' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="[
                                        'inline-flex px-2 py-1 rounded-full text-xs font-semibold',
                                        log.error_b
                                            ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                            : 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400'
                                    ]">
                                        {{ log.error_b ? 'ERROR' : 'OK' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    Belum ada history log
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
