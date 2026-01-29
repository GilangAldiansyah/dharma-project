<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    TrendingUp,
    Calendar,
    Clock,
    Activity,
    CheckCircle2,
    Package,
    AlertCircle,
    ArrowLeft,
    Gauge
} from 'lucide-vue-next';

interface OeeRecord {
    id: number;
    line: {
        id: number;
        line_code: string;
        line_name: string;
        plant: string;
        esp32_device?: {
            device_id: string;
        };
    };
    period_type: string;
    period_date: string;
    period_start: string;
    period_end: string;
    operation_time_hours: number;
    uptime_hours: number;
    downtime_hours: number;
    total_counter_a: number;
    total_reject: number;
    good_count: number;
    avg_cycle_time: number;
    availability: number;
    performance: number;
    quality: number;
    oee: number;
    total_failures: number;
    calculated_by: string;
    created_at: string;
}

interface Props {
    oeeRecord: OeeRecord;
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps<Props>();

const formatNumber = (num: number) => {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
};

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getOeeStatusColor = (oee: number) => {
    if (oee >= 85) return 'bg-green-100 text-green-800 border-green-300';
    if (oee >= 70) return 'bg-blue-100 text-blue-800 border-blue-300';
    if (oee >= 50) return 'bg-yellow-100 text-yellow-800 border-yellow-300';
    return 'bg-red-100 text-red-800 border-red-300';
};

const getOeeStatusLabel = (oee: number) => {
    if (oee >= 85) return 'Excellent';
    if (oee >= 70) return 'Good';
    if (oee >= 50) return 'Fair';
    return 'Poor';
};

const getProgressColor = (value: number) => {
    if (value >= 85) return 'bg-green-600';
    if (value >= 70) return 'bg-blue-600';
    if (value >= 50) return 'bg-yellow-600';
    return 'bg-red-600';
};
</script>

<template>
    <Head :title="`OEE Detail - ${oeeRecord.line.line_code}`" />
    <AppLayout :breadcrumbs="[
        { title: 'OEE Dashboard', href: '/oee' },
        { title: 'Detail', href: '#' }
    ]">
        <div class="p-4 space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        href="/oee"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-sidebar-accent rounded-lg transition-colors"
                    >
                        <ArrowLeft class="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold flex items-center gap-2">
                            <TrendingUp class="w-6 h-6 text-violet-600" />
                            OEE Detail - {{ oeeRecord.line.line_code }}
                        </h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ oeeRecord.line.line_name }} | {{ formatDateTime(oeeRecord.period_start) }} - {{ formatDateTime(oeeRecord.period_end) }}
                        </p>
                    </div>
                </div>
                <div :class="[
                    'px-4 py-2 rounded-full text-sm font-bold border-2',
                    getOeeStatusColor(oeeRecord.oee)
                ]">
                    {{ getOeeStatusLabel(oeeRecord.oee) }}
                </div>
            </div>

            <div class="bg-gradient-to-br from-violet-50 to-purple-50 dark:from-violet-900/10 dark:to-purple-900/10 border border-violet-200 dark:border-violet-800 rounded-xl p-8">
                <div class="text-center mb-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Overall Equipment Effectiveness</p>
                    <div class="text-7xl font-bold text-violet-600 mb-4">{{ formatNumber(oeeRecord.oee) }}%</div>
                    <div class="flex items-center justify-center gap-2">
                        <Gauge class="w-5 h-5 text-violet-600" />
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            OEE = Availability × Performance × Quality
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
                    <div class="bg-white dark:bg-sidebar rounded-lg p-6 border border-violet-200 dark:border-violet-800">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                                <Clock class="w-6 h-6 text-blue-600" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Availability</p>
                                <p class="text-2xl font-bold text-blue-600">{{ formatNumber(oeeRecord.availability) }}%</p>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                            <div
                                :class="['h-full transition-all duration-500', getProgressColor(oeeRecord.availability)]"
                                :style="{ width: `${oeeRecord.availability}%` }"
                            ></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-3">Uptime / Operation Time</p>
                    </div>

                    <div class="bg-white dark:bg-sidebar rounded-lg p-6 border border-violet-200 dark:border-violet-800">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                                <Activity class="w-6 h-6 text-green-600" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Performance</p>
                                <p class="text-2xl font-bold text-green-600">{{ formatNumber(oeeRecord.performance) }}%</p>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                            <div
                                :class="['h-full transition-all duration-500', getProgressColor(oeeRecord.performance)]"
                                :style="{ width: `${oeeRecord.performance}%` }"
                            ></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-3">Production Efficiency</p>
                    </div>

                    <div class="bg-white dark:bg-sidebar rounded-lg p-6 border border-violet-200 dark:border-violet-800">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-3 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                                <CheckCircle2 class="w-6 h-6 text-orange-600" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Quality</p>
                                <p class="text-2xl font-bold text-orange-600">{{ formatNumber(oeeRecord.quality) }}%</p>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                            <div
                                :class="['h-full transition-all duration-500', getProgressColor(oeeRecord.quality)]"
                                :style="{ width: `${oeeRecord.quality}%` }"
                            ></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-3">Good Count / Total</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                    <h3 class="font-semibold text-lg mb-4 flex items-center gap-2">
                        <Clock class="w-5 h-5 text-blue-600" />
                        Time Metrics
                    </h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center pb-3 border-b border-sidebar-border">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Operation Time</span>
                            <span class="text-lg font-bold">{{ formatNumber(oeeRecord.operation_time_hours) }} hrs</span>
                        </div>
                        <div class="flex justify-between items-center pb-3 border-b border-sidebar-border">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Uptime</span>
                            <span class="text-lg font-bold text-green-600">{{ formatNumber(oeeRecord.uptime_hours) }} hrs</span>
                        </div>
                        <div class="flex justify-between items-center pb-3 border-b border-sidebar-border">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Downtime</span>
                            <span class="text-lg font-bold text-red-600">{{ formatNumber(oeeRecord.downtime_hours) }} hrs</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Avg Cycle Time</span>
                            <span class="text-lg font-bold">{{ formatNumber(oeeRecord.avg_cycle_time) }} sec</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                    <h3 class="font-semibold text-lg mb-4 flex items-center gap-2">
                        <Package class="w-5 h-5 text-green-600" />
                        Production Metrics
                    </h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center pb-3 border-b border-sidebar-border">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Total Production</span>
                            <span class="text-lg font-bold">{{ oeeRecord.total_counter_a.toLocaleString() }} pcs</span>
                        </div>
                        <div class="flex justify-between items-center pb-3 border-b border-sidebar-border">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Good Count</span>
                            <span class="text-lg font-bold text-green-600">{{ oeeRecord.good_count.toLocaleString() }} pcs</span>
                        </div>
                        <div class="flex justify-between items-center pb-3 border-b border-sidebar-border">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Reject</span>
                            <span class="text-lg font-bold text-red-600">{{ oeeRecord.total_reject.toLocaleString() }} pcs</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Total Failures</span>
                            <span class="text-lg font-bold text-orange-600">{{ oeeRecord.total_failures }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                <h3 class="font-semibold text-lg mb-4 flex items-center gap-2">
                    <AlertCircle class="w-5 h-5 text-gray-600" />
                    Record Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-sidebar-accent rounded-lg">
                        <Calendar class="w-5 h-5 text-gray-400" />
                        <div>
                            <p class="text-xs text-gray-500">Period Type</p>
                            <p class="font-medium capitalize">{{ oeeRecord.period_type }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-sidebar-accent rounded-lg">
                        <Calendar class="w-5 h-5 text-gray-400" />
                        <div>
                            <p class="text-xs text-gray-500">Period Date</p>
                            <p class="font-medium">{{ formatDateTime(oeeRecord.period_date) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-sidebar-accent rounded-lg">
                        <Activity class="w-5 h-5 text-gray-400" />
                        <div>
                            <p class="text-xs text-gray-500">Calculated By</p>
                            <p class="font-medium">{{ oeeRecord.calculated_by }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-sidebar-accent rounded-lg">
                        <Clock class="w-5 h-5 text-gray-400" />
                        <div>
                            <p class="text-xs text-gray-500">Created At</p>
                            <p class="font-medium">{{ formatDateTime(oeeRecord.created_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
