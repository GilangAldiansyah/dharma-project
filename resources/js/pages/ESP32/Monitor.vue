<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Search, Activity, AlertCircle, Eye, RefreshCw, Settings, Maximize2, Minimize2, Sun, Moon, Zap, TrendingUp, PauseCircle } from 'lucide-vue-next';

interface Part {
    id: number;
    device_id: string;
    part_id: string;
    part_name: string;
    max_count: number;
    max_stroke: number;
    cycle_time: number;
    production_started_at?: string | null;
}

interface Device {
    id: number;
    device_id: string;
    active_part_id: string | null;
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
    line_id?: number | null;
    schedule_start_time: string;
    schedule_end_time: string;
    schedule_breaks: Array<{ start: string; end: string }>;
    schedules: Array<{ shift: number; start: string; end: string; breaks: Array<{ start: string; end: string }> }>;
    active_schedule: { shift: number | null; start: string; end: string; breaks: Array<{ start: string; end: string }> };
    area?: { id: number; name: string };
    line?: { id: number; line_code: string; line_name: string };
    parts: Part[];
}

interface Area { id: number; name: string }
interface Line { id: number; line_code: string; line_name: string }

interface Props {
    devices: Device[];
    areas: Area[];
    lines: Line[];
    filters: { search?: string; area?: number };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const selectedAreaId = ref(props.filters.area || null);
const autoRefresh = ref(true);
let refreshInterval: number | null = null;
const showSettingsModal = ref(false);
const showResetConfirm = ref(false);
const showPartSettingsModal = ref(false);
const selectedDevice = ref<Device | null>(null);
const selectedPart = ref<Part | null>(null);
const editMaxCount = ref(0);
const editMaxStroke = ref(0);
const editReject = ref(0);
const editCycleTime = ref(0);
const editAreaId = ref<number | null>(null);
const editLineId = ref<number | null>(null);
const editNewAreaName = ref('');
const showNewAreaInput = ref(false);
const editPartName = ref('');
const editPartMaxCount = ref(0);
const editPartMaxStroke = ref(0);
const editPartCycleTime = ref(0);
const previousDevicesData = ref<string>('');
const currentRefreshInterval = ref(5000);
const noChangeCount = ref(0);
const lastActivityTime = ref(Date.now());
const isFullscreen = ref(false);
const fullscreenDarkMode = ref(true);

const PART_COLORS = [
    { bar: 'from-blue-400 to-blue-600',     dot: 'bg-blue-500',   text: 'text-blue-600 dark:text-blue-400',   badge: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',   hex: '#3b82f6' },
    { bar: 'from-violet-400 to-violet-600', dot: 'bg-violet-500', text: 'text-violet-600 dark:text-violet-400', badge: 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200', hex: '#8b5cf6' },
    { bar: 'from-rose-400 to-rose-600',     dot: 'bg-rose-500',   text: 'text-rose-600 dark:text-rose-400',   badge: 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200',   hex: '#f43f5e' },
    { bar: 'from-amber-400 to-amber-600',   dot: 'bg-amber-500',  text: 'text-amber-600 dark:text-amber-400', badge: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200', hex: '#f59e0b' },
    { bar: 'from-teal-400 to-teal-600',     dot: 'bg-teal-500',   text: 'text-teal-600 dark:text-teal-400',   badge: 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200',   hex: '#14b8a6' },
];

const getPartColor = (index: number) => PART_COLORS[index % PART_COLORS.length];

const stats = computed(() => ({
    total: props.devices.length,
    active: props.devices.filter(d => isDeviceActive(d)).length,
    errors: props.devices.filter(d => d.counter_a > d.max_count).length,
    delayed: props.devices.filter(d => d.is_delayed && !d.is_completed).length,
}));

const isDeviceActive = (device: Device): boolean => {
    const lastUpdate = new Date(device.last_update);
    const now = new Date();
    const diffMinutes = Math.floor((now.getTime() - lastUpdate.getTime()) / 1000 / 60);
    return diffMinutes < 5;
};

const getDeviceStatus = (device: Device) => {
    if (device.counter_a > device.max_count || device.error_b) {
        return { label: 'Over Count', class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' };
    }
    if (isDeviceActive(device)) {
        return { label: 'Active', class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' };
    }
    return { label: 'Idle', class: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400' };
};

const getTodayPartHistories = (device: Device) => {
    return (device.parts ?? []).map((part, index) => ({ part, color: getPartColor(index) }));
};

const getActivePartColor = (device: Device) => {
    if (!device.active_part_id) return null;
    const index = (device.parts ?? []).findIndex(p => p.part_id === device.active_part_id);
    return index >= 0 ? getPartColor(index) : null;
};

const parseTime = (timeStr: string, base: Date) => {
    const [h, m] = timeStr.split(':').map(Number);
    const d = new Date(base);
    d.setHours(h, m, 0, 0);
    return d;
};

const clampPct = (val: number) => Math.max(0, Math.min(val, 100));

const getTimelineData = (device: Device) => {
    if (!device.production_started_at) return null;
    const activeSchedule = device.active_schedule;
    if (!activeSchedule) return null;

    const actualStartTime = new Date(device.production_started_at);
    const baseDate = new Date(actualStartTime);
    baseDate.setHours(0, 0, 0, 0);

    let scheduleStart = parseTime(activeSchedule.start, baseDate);
    let scheduleEnd   = parseTime(activeSchedule.end, baseDate);
    if (scheduleEnd <= scheduleStart) {
        scheduleEnd = new Date(scheduleEnd.getTime() + 24 * 60 * 60 * 1000);
    }

    const totalDuration        = scheduleEnd.getTime() - scheduleStart.getTime();
    const lastUpdateTime       = new Date(device.last_update);
    const isActive             = isDeviceActive(device);
    const effectiveCurrentTime = lastUpdateTime;

    const productionStartPercentage = clampPct(((actualStartTime.getTime() - scheduleStart.getTime()) / totalDuration) * 100);
    const currentPercentage         = clampPct(((effectiveCurrentTime.getTime() - scheduleStart.getTime()) / totalDuration) * 100);
    const lastActivityPercentage    = clampPct(((lastUpdateTime.getTime() - scheduleStart.getTime()) / totalDuration) * 100);
    const barWidth                  = currentPercentage - productionStartPercentage;

    const breaks = (activeSchedule.breaks ?? []).map(brk => {
        const breakStart = parseTime(brk.start, baseDate);
        let breakEnd     = parseTime(brk.end, baseDate);
        if (breakEnd <= breakStart) breakEnd = new Date(breakEnd.getTime() + 24 * 60 * 60 * 1000);
        return {
            startPercentage: clampPct(((breakStart.getTime() - scheduleStart.getTime()) / totalDuration) * 100),
            endPercentage:   clampPct(((breakEnd.getTime() - scheduleStart.getTime()) / totalDuration) * 100),
            width:           clampPct(((breakEnd.getTime() - breakStart.getTime()) / totalDuration) * 100),
            startTime: breakStart,
            endTime:   breakEnd,
        };
    });

    const partsWithStartTime = (device.parts ?? [])
        .filter(p => p.production_started_at)
        .map((part) => {
            const partStartTime = new Date(part.production_started_at!);
            const startPct = clampPct(((partStartTime.getTime() - scheduleStart.getTime()) / totalDuration) * 100);
            const partIndex = (device.parts ?? []).findIndex(pp => pp.part_id === part.part_id);
            return {
                part,
                color: getPartColor(partIndex),
                startTime: partStartTime,
                startPercentage: startPct,
            };
        })
        .sort((a, b) => a.startTime.getTime() - b.startTime.getTime());

    return {
        scheduleStart, scheduleEnd, actualStartTime, lastUpdateTime, isActive,
        productionStartPercentage, currentPercentage, lastActivityPercentage,
        barWidth, breaks,
        isOvertime:  device.delay_seconds > 0,
        activeShift: activeSchedule.shift,
        totalDuration,
        partsWithStartTime,
    };
};

const getTimelineSegments = (device: Device) => {
    const td = getTimelineData(device);
    if (!td) return [];

    const parts = td.partsWithStartTime;

    if (parts.length === 0) {
        return [{
            left: td.productionStartPercentage,
            width: td.barWidth,
            color: getActivePartColor(device)?.bar ?? 'from-green-400 to-emerald-500',
            isOvertime: td.isOvertime,
            part: null as Part | null,
            hex: getActivePartColor(device)?.hex ?? '#10b981',
        }];
    }

    const segments: Array<{
        left: number;
        width: number;
        color: string;
        isOvertime: boolean;
        part: Part | null;
        hex: string;
    }> = [];

    for (let i = 0; i < parts.length; i++) {
        const segStart = parts[i].startPercentage;
        const segEnd   = i + 1 < parts.length
            ? parts[i + 1].startPercentage
            : td.currentPercentage;
        const w = segEnd - segStart;
        if (w <= 0) continue;
        segments.push({
            left:       segStart,
            width:      w,
            color:      parts[i].color.bar,
            hex:        parts[i].color.hex,
            isOvertime: false,
            part:       parts[i].part,
        });
    }

    return segments;
};

const formatTimeOnly = (date: Date) => date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

const getShiftBadgeColor = (shift: number | null) => {
    switch (shift) {
        case 1:  return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 2:  return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
        case 3:  return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
    }
};

const search = () => router.get('/esp32/monitor', { search: searchQuery.value, area: selectedAreaId.value }, { preserveState: true, preserveScroll: true });

const filterByArea = (areaId: number | null) => {
    selectedAreaId.value = areaId;
    router.get('/esp32/monitor', { search: searchQuery.value, area: areaId }, { preserveState: true, preserveScroll: true });
};

const refreshData = () => router.reload({ only: ['devices', 'areas', 'lines'], onSuccess: () => adaptRefreshInterval() });

const adaptRefreshInterval = () => {
    const currentData = JSON.stringify(props.devices);
    if (previousDevicesData.value === currentData) noChangeCount.value++;
    else noChangeCount.value = 0;
    previousDevicesData.value = currentData;

    const isUserIdle = Date.now() - lastActivityTime.value > 60000;
    let newInterval  = isUserIdle ? 30000 : noChangeCount.value === 0 ? 5000 : noChangeCount.value < 3 ? 10000 : 15000;

    if (currentRefreshInterval.value !== newInterval) {
        currentRefreshInterval.value = newInterval;
        if (refreshInterval && autoRefresh.value) {
            clearInterval(refreshInterval);
            refreshInterval = window.setInterval(refreshData, newInterval);
        }
    }
};

const resetActivity = () => { lastActivityTime.value = Date.now(); };

const getProgressPercentage = (counter: number, max: number) => {
    if (max === 0) return 0;
    return Math.min((counter / max) * 100, 100);
};

const formatLastUpdate = (dateString: string) => {
    const date     = new Date(dateString);
    const now      = new Date();
    const diffSecs = Math.floor((now.getTime() - date.getTime()) / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    if (diffSecs < 60) return `${diffSecs}d`;
    if (diffMins < 60) return `${diffMins}m`;
    return date.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
};

const formatTime = (seconds: number) => {
    const absSeconds = Math.abs(seconds);
    const hours   = Math.floor(absSeconds / 3600);
    const minutes = Math.floor((absSeconds % 3600) / 60);
    const secs    = absSeconds % 60;
    if (hours > 0)   return `${hours}j ${minutes}m`;
    if (minutes > 0) return `${minutes}m ${secs}d`;
    return `${secs}d`;
};

const formatDelayTime = (seconds: number, isCompleted: boolean, cycleTime: number) => {
    if (Math.abs(seconds) <= cycleTime) return 'Tepat Waktu';
    const formatted = formatTime(seconds);
    if (isCompleted) return seconds > 0 ? `+${formatted}` : `-${formatted}`;
    return seconds > 0 ? `Delay ${formatted}` : `Cepat ${formatted}`;
};

const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value;
    if (isFullscreen.value) document.documentElement.requestFullscreen?.();
    else document.exitFullscreen?.();
};

const handleFullscreenChange = () => { if (!document.fullscreenElement) isFullscreen.value = false; };

const openSettingsModal = (device: Device) => {
    selectedDevice.value   = device;
    editMaxCount.value     = device.max_count;
    editMaxStroke.value    = device.max_stroke;
    editReject.value       = device.reject;
    editCycleTime.value    = device.cycle_time;
    editAreaId.value       = device.area?.id || null;
    editLineId.value       = device.line_id || null;
    editNewAreaName.value  = '';
    showNewAreaInput.value = false;
    showSettingsModal.value = true;
};

const openPartSettingsModal = (device: Device, part: Part) => {
    selectedDevice.value        = device;
    selectedPart.value          = part;
    editPartName.value          = part.part_name ?? part.part_id;
    editPartMaxCount.value      = part.max_count;
    editPartMaxStroke.value     = part.max_stroke;
    editPartCycleTime.value     = part.cycle_time;
    showPartSettingsModal.value = true;
};

const toggleNewAreaInput = () => {
    showNewAreaInput.value = !showNewAreaInput.value;
    if (showNewAreaInput.value) editAreaId.value = null;
    else editNewAreaName.value = '';
};

const updateSettings = () => {
    if (!selectedDevice.value) return;
    const updateData: any = {
        device_id:    selectedDevice.value.device_id,
        max_count:    editMaxCount.value,
        reject:       editReject.value,
        cycle_time:   editCycleTime.value,
        line_id:      editLineId.value,
        reset_counter: false,
    };
    if (showNewAreaInput.value && editNewAreaName.value.trim()) {
        updateData.new_area_name = editNewAreaName.value.trim();
    } else if (editAreaId.value) {
        updateData.area_id = editAreaId.value;
    } else {
        updateData.area_id = null;
    }
    if (selectedDevice.value.has_counter_b) updateData.max_stroke = editMaxStroke.value;

    router.post('/esp32/monitor/update-settings', updateData, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => { showSettingsModal.value = false; refreshData(); },
    });
};

const updatePartSettings = () => {
    if (!selectedDevice.value || !selectedPart.value) return;
    router.post('/esp32/monitor/update-part-settings', {
        device_id:  selectedDevice.value.device_id,
        part_id:    selectedPart.value.part_id,
        part_name:  editPartName.value,
        max_count:  editPartMaxCount.value,
        max_stroke: editPartMaxStroke.value,
        cycle_time: editPartCycleTime.value,
    }, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => { showPartSettingsModal.value = false; refreshData(); },
    });
};

const resetCounter = () => {
    if (!selectedDevice.value) return;
    router.post('/esp32/monitor/update-settings', {
        device_id:     selectedDevice.value.device_id,
        max_count:     selectedDevice.value.max_count,
        reject:        selectedDevice.value.reject,
        cycle_time:    selectedDevice.value.cycle_time,
        max_stroke:    selectedDevice.value.max_stroke || 0,
        area_id:       selectedDevice.value.area?.id || null,
        line_id:       selectedDevice.value.line_id || null,
        reset_counter: true,
    }, {
        preserveState: false,
        onSuccess: () => { showSettingsModal.value = false; showResetConfirm.value = false; refreshData(); },
    });
};

onMounted(() => {
    previousDevicesData.value = JSON.stringify(props.devices);
    if (autoRefresh.value) refreshInterval = window.setInterval(refreshData, currentRefreshInterval.value);
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
    if (autoRefresh.value) refreshInterval = window.setInterval(refreshData, currentRefreshInterval.value);
    else if (refreshInterval) { clearInterval(refreshInterval); refreshInterval = null; }
};
</script>

<template>
    <Head title="Robot Monitor" />
    <AppLayout v-if="!isFullscreen" :breadcrumbs="[{ title: 'Robot Monitor', href: '/esp32/monitor' }]">
        <div class="p-6 space-y-6 bg-gray-50 dark:!bg-gray-900 min-h-screen">
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
                        <Maximize2 class="w-4 h-4" />Fullscreen
                    </button>
                    <button @click="toggleAutoRefresh" :class="['px-4 py-2.5 rounded-xl transition-all duration-300 font-medium flex items-center gap-2', autoRefresh ? 'bg-gradient-to-r from-green-500 to-emerald-500 text-white shadow-lg' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300']">
                        <RefreshCw :class="['w-4 h-4', autoRefresh ? 'animate-spin' : '']" />
                        {{ autoRefresh ? `Auto (${currentRefreshInterval / 1000}s)` : 'Manual' }}
                    </button>
                    <button @click="refreshData" class="px-4 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-all duration-300 font-medium flex items-center gap-2">
                        <RefreshCw class="w-4 h-4" />Refresh
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
                        <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-xl"><Activity class="w-6 h-6 text-blue-600" /></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-green-500 hover:shadow-xl transition-all duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Active</p>
                            <p class="text-3xl font-bold text-green-600 mt-1">{{ stats.active }}</p>
                        </div>
                        <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-xl"><Zap class="w-6 h-6 text-green-600" /></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-red-500 hover:shadow-xl transition-all duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Over Count</p>
                            <p class="text-3xl font-bold text-red-600 mt-1">{{ stats.errors }}</p>
                        </div>
                        <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-xl"><AlertCircle class="w-6 h-6 text-red-600" /></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border-l-4 border-orange-500 hover:shadow-xl transition-all duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Delayed</p>
                            <p class="text-3xl font-bold text-orange-600 mt-1">{{ stats.delayed }}</p>
                        </div>
                        <div class="p-3 bg-orange-100 dark:bg-orange-900/30 rounded-xl"><TrendingUp class="w-6 h-6 text-orange-600" /></div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <div class="flex gap-3">
                    <div class="relative flex-1 max-w-md">
                        <input v-model="searchQuery" @keyup.enter="search" type="text" placeholder="Cari device..." class="w-full pl-11 pr-4 py-3 rounded-xl border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                        <Search class="w-5 h-5 text-gray-400 absolute left-3.5 top-3.5" />
                    </div>
                    <button @click="search" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium">Search</button>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <button @click="filterByArea(null)" :class="['px-5 py-2.5 rounded-xl font-semibold transition-all duration-300 border-2', selectedAreaId === null ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg border-transparent' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-blue-400']">
                        All Areas
                    </button>
                    <button v-for="area in areas" :key="area.id" @click="filterByArea(area.id)" :class="['px-5 py-2.5 rounded-xl font-semibold transition-all duration-300 border-2', selectedAreaId === area.id ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg border-transparent' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-blue-400']">
                        {{ area.name }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                <div v-for="device in devices" :key="device.id" class="group bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 dark:border-gray-700 hover:-translate-y-1">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ device.device_id }}</h3>
                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                <p v-if="device.area" class="text-xs text-blue-600 dark:text-blue-400 font-semibold">{{ device.area.name }}</p>
                                <span v-if="getTimelineData(device)?.activeShift" :class="['px-2 py-0.5 rounded-full text-xs font-bold', getShiftBadgeColor(getTimelineData(device)!.activeShift)]">
                                    Shift {{ getTimelineData(device)!.activeShift }}
                                </span>
                                <span v-if="device.active_part_id" :class="['px-2 py-0.5 rounded-full text-xs font-bold', getActivePartColor(device)?.badge ?? 'bg-gray-100 text-gray-800']">
                                    {{ (device.parts ?? []).find(p => p.part_id === device.active_part_id)?.part_name ?? device.active_part_id }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                                {{ formatLastUpdate(device.last_update) }}
                            </p>
                        </div>
                        <div :class="['px-3 py-1.5 rounded-lg text-xs font-semibold', getDeviceStatus(device).class]">
                            {{ getDeviceStatus(device).label }}
                        </div>
                    </div>

                    <div v-if="device.is_paused" class="mb-3 px-3 py-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg border-2 border-blue-300 dark:border-blue-700">
                        <div class="flex items-center gap-2 text-sm font-semibold text-blue-700 dark:text-blue-300">
                            <PauseCircle class="w-4 h-4" /><span>Production Paused</span>
                        </div>
                        <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">Total pause: {{ formatTime(device.total_pause_seconds) }}</div>
                    </div>

                    <div class="space-y-3 mb-4">
                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ device.has_counter_b ? 'Counter A' : 'Counter' }}</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ device.counter_a }}<span class="text-gray-400">/{{ device.max_count }}</span></span>
                            </div>
                            <div class="relative w-full h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div :class="['absolute inset-0 rounded-full transition-all duration-500 shadow-lg bg-gradient-to-r', getActivePartColor(device)?.bar ?? 'from-green-400 to-emerald-500']"
                                    :style="{ width: `${getProgressPercentage(device.counter_a, device.max_count)}%` }"></div>
                            </div>
                        </div>
                        <div v-if="device.has_counter_b">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">Stroke</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ device.counter_b }}<span class="text-gray-400">{{ device.max_stroke > 0 ? `/${device.max_stroke}` : '' }}</span></span>
                            </div>
                            <div class="relative w-full h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-r from-purple-400 to-pink-500 rounded-full transition-all duration-500 shadow-lg"
                                    :style="{ width: `${getProgressPercentage(device.counter_b, device.max_stroke > 0 ? device.max_stroke : device.max_count)}%` }"></div>
                            </div>
                        </div>

                        <div v-if="(device.parts ?? []).length > 0" class="pt-1">
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">Parts</p>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="({ part, color }) in getTodayPartHistories(device)" :key="part.part_id"
                                    @click="openPartSettingsModal(device, part)"
                                    :class="['flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold border transition-all hover:shadow-md', part.part_id === device.active_part_id ? `${color.badge} border-current` : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 border-transparent opacity-60 hover:opacity-100']">
                                    <span :class="['w-2 h-2 rounded-full shrink-0', color.dot]"></span>
                                    {{ part.part_name ?? part.part_id }}
                                    <span v-if="part.part_id === device.active_part_id" class="ml-0.5">●</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="getTimelineData(device)" class="pt-2">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">Production Timeline</span>
                                <span v-if="device.counter_a > 0" :class="['font-bold text-sm', Math.abs(device.delay_seconds) <= device.cycle_time ? 'text-green-600' : device.is_completed ? (device.delay_seconds > 0 ? 'text-red-600' : 'text-blue-600') : (device.delay_seconds > 0 ? 'text-orange-600' : 'text-blue-600')]">
                                    {{ formatDelayTime(device.delay_seconds, device.is_completed, device.cycle_time) }}
                                </span>
                            </div>

                            <div class="space-y-1">
                                <div class="relative w-full h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-visible">

                                    <div v-for="(brk, idx) in getTimelineData(device)!.breaks" :key="`break-${idx}`"
                                        class="absolute h-full bg-orange-200 dark:bg-orange-800 rounded-sm"
                                        :style="{ left: `${brk.startPercentage}%`, width: `${brk.width}%` }"></div>

                                    <template v-if="getTimelineSegments(device).length > 0">
                                        <div v-for="(seg, sIdx) in getTimelineSegments(device)" :key="`seg-${sIdx}`"
                                            :class="['absolute h-full rounded-full transition-all duration-500 shadow-lg bg-gradient-to-r group/seg', seg.isOvertime ? 'from-orange-400 to-red-500' : seg.color]"
                                            :style="{ left: `${seg.left}%`, width: `${seg.width}%` }">
                                            <div v-if="seg.part" class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover/seg:opacity-100 transition-opacity z-30 pointer-events-none">
                                                <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                    <div class="font-bold">{{ seg.part.part_name ?? seg.part.part_id }}</div>
                                                    <div class="text-[10px] text-gray-300 font-mono">{{ seg.part.part_id }}</div>
                                                    <div v-if="seg.part.production_started_at" class="text-[10px] text-gray-300 mt-0.5">
                                                        Start: {{ formatTimeOnly(new Date(seg.part.production_started_at)) }}
                                                    </div>
                                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2"><div class="border-4 border-transparent border-t-gray-900"></div></div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div :class="['absolute h-full rounded-full transition-all duration-500 shadow-lg bg-gradient-to-r', getTimelineData(device)!.isOvertime ? 'from-orange-400 to-red-500' : (getActivePartColor(device)?.bar ?? 'from-green-400 to-emerald-500')]"
                                            :style="{ left: `${getTimelineData(device)!.productionStartPercentage}%`, width: `${getTimelineData(device)!.barWidth}%` }"></div>
                                    </template>

                                    <div class="absolute top-0 left-0 h-full w-1 bg-gray-600 rounded-full cursor-pointer hover:w-1.5 transition-all group/marker z-10">
                                        <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover/marker:opacity-100 transition-opacity z-20 pointer-events-none">
                                            <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                <div class="font-medium">Schedule Start</div>
                                                <div class="text-[10px] text-gray-300">{{ formatTimeOnly(getTimelineData(device)!.scheduleStart) }}</div>
                                                <div class="absolute top-full left-1/2 -translate-x-1/2"><div class="border-4 border-transparent border-t-gray-900"></div></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="absolute top-0 right-0 h-full w-1 bg-gray-600 rounded-full cursor-pointer hover:w-1.5 transition-all group/marker z-10">
                                        <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover/marker:opacity-100 transition-opacity z-20 pointer-events-none">
                                            <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                <div class="font-medium">Schedule End</div>
                                                <div class="text-[10px] text-gray-300">{{ formatTimeOnly(getTimelineData(device)!.scheduleEnd) }}</div>
                                                <div class="absolute top-full left-1/2 -translate-x-1/2"><div class="border-4 border-transparent border-t-gray-900"></div></div>
                                            </div>
                                        </div>
                                    </div>

                                    <template v-if="getTimelineData(device)!.partsWithStartTime.length > 0">
                                        <div v-for="(ps, psIdx) in getTimelineData(device)!.partsWithStartTime" :key="`ps-marker-${psIdx}`"
                                            class="absolute top-0 h-full w-1 rounded-full cursor-pointer hover:w-1.5 transition-all group/marker z-10"
                                            :style="{ left: `${ps.startPercentage}%`, backgroundColor: ps.color.hex }">
                                            <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover/marker:opacity-100 transition-opacity z-20 pointer-events-none">
                                                <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                    <div class="font-bold">{{ ps.part.part_name ?? ps.part.part_id }}</div>
                                                    <div class="text-[10px] text-gray-300">Actual Start</div>
                                                    <div class="text-[10px] font-mono" :style="{ color: ps.color.hex }">{{ formatTimeOnly(ps.startTime) }}</div>
                                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2"><div class="border-4 border-transparent border-t-gray-900"></div></div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="absolute top-0 h-full w-1 bg-green-600 rounded-full cursor-pointer hover:w-1.5 transition-all group/marker z-10" :style="{ left: `${getTimelineData(device)!.productionStartPercentage}%` }">
                                            <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover/marker:opacity-100 transition-opacity z-20 pointer-events-none">
                                                <div class="bg-green-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                    <div class="font-medium">Actual Start</div>
                                                    <div class="text-[10px] text-green-300">{{ formatTimeOnly(getTimelineData(device)!.actualStartTime) }}</div>
                                                    <div class="absolute top-full left-1/2 -translate-x-1/2"><div class="border-4 border-transparent border-t-green-900"></div></div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <div v-if="!getTimelineData(device)!.isActive" class="absolute top-0 h-full w-1 bg-red-600 rounded-full cursor-pointer hover:w-1.5 transition-all group/marker z-10" :style="{ left: `${getTimelineData(device)!.lastActivityPercentage}%` }">
                                        <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover/marker:opacity-100 transition-opacity z-20 pointer-events-none">
                                            <div class="bg-red-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                <div class="font-medium">Last Activity</div>
                                                <div class="text-[10px] text-red-300">{{ formatTimeOnly(getTimelineData(device)!.lastUpdateTime) }}</div>
                                                <div class="absolute top-full left-1/2 -translate-x-1/2"><div class="border-4 border-transparent border-t-red-900"></div></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-for="(brk, idx) in getTimelineData(device)!.breaks" :key="`break-marker-${idx}`">
                                        <div class="absolute top-0 h-full w-1 bg-orange-500 rounded-full cursor-pointer hover:w-1.5 transition-all group/marker z-10" :style="{ left: `${brk.startPercentage}%` }">
                                            <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 opacity-0 group-hover/marker:opacity-100 transition-opacity z-20 pointer-events-none">
                                                <div class="bg-orange-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                    <div class="font-medium">Break {{ idx + 1 }} Start</div>
                                                    <div class="text-[10px] text-orange-300">{{ formatTimeOnly(brk.startTime) }}</div>
                                                    <div class="absolute top-full left-1/2 -translate-x-1/2"><div class="border-4 border-transparent border-t-orange-900"></div></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="absolute top-0 h-full w-1 bg-orange-500 rounded-full cursor-pointer hover:w-1.5 transition-all group/marker z-10" :style="{ left: `${brk.endPercentage}%` }">
                                            <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 opacity-0 group-hover/marker:opacity-100 transition-opacity z-20 pointer-events-none">
                                                <div class="bg-orange-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                    <div class="font-medium">Break {{ idx + 1 }} End</div>
                                                    <div class="text-[10px] text-orange-300">{{ formatTimeOnly(brk.endTime) }}</div>
                                                    <div class="absolute top-full left-1/2 -translate-x-1/2"><div class="border-4 border-transparent border-t-orange-900"></div></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-between text-[10px] text-gray-600 dark:text-gray-400 font-medium px-1">
                                    <span>{{ formatTimeOnly(getTimelineData(device)!.scheduleStart) }}</span>
                                    <span v-if="!device.is_completed && getTimelineData(device)!.isActive" class="text-blue-600 dark:text-blue-400 font-bold">Now: {{ formatTimeOnly(getTimelineData(device)!.lastUpdateTime) }}</span>
                                    <span v-if="!device.is_completed && !getTimelineData(device)!.isActive" class="text-red-600 dark:text-red-400 font-bold">Idle</span>
                                    <span>{{ formatTimeOnly(getTimelineData(device)!.scheduleEnd) }}</span>
                                </div>

                                <div v-if="getTimelineSegments(device).length > 1" class="flex gap-1 flex-wrap mt-1">
                                    <div v-for="(seg, sIdx) in getTimelineSegments(device)" :key="`legend-${sIdx}`"
                                        class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                                        <span class="w-2 h-2 rounded-full inline-block shrink-0" :style="{ backgroundColor: seg.hex }"></span>
                                        <span>{{ seg.part?.part_name ?? seg.part?.part_id ?? '—' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div class="text-center p-3 bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 rounded-xl border border-red-100 dark:border-red-800">
                            <div class="text-xs text-gray-600 dark:text-gray-400 font-medium">NG</div>
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
                            <Eye class="w-4 h-4" />Detail
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
                <div class="flex gap-3">
                    <div :class="['px-5 py-3 rounded-xl font-semibold', fullscreenDarkMode ? 'bg-gray-800 text-white' : 'bg-white text-gray-900 shadow-lg']">
                        Auto: {{ currentRefreshInterval / 1000 }}s
                    </div>
                    <button @click="fullscreenDarkMode = !fullscreenDarkMode" :class="['px-5 py-3 rounded-xl font-semibold flex items-center gap-2', fullscreenDarkMode ? 'bg-yellow-500 text-white' : 'bg-gray-800 text-white']">
                        <component :is="fullscreenDarkMode ? Sun : Moon" class="w-5 h-5" />
                        {{ fullscreenDarkMode ? 'Light' : 'Dark' }}
                    </button>
                    <button @click="toggleFullscreen" class="px-5 py-3 bg-red-600 text-white rounded-xl font-semibold flex items-center gap-2 hover:bg-red-700">
                        <Minimize2 class="w-5 h-5" />Exit
                    </button>
                </div>
            </div>

            <div v-if="areas.length > 0" class="flex gap-2 flex-wrap mb-6">
                <button @click="filterByArea(null)" :class="['px-5 py-3 rounded-xl font-semibold transition-all duration-300 border-2', selectedAreaId === null ? 'bg-blue-600 text-white shadow-xl border-transparent' : (fullscreenDarkMode ? 'bg-gray-800 text-gray-300 border-gray-700' : 'bg-white text-gray-700 border-gray-300 shadow-lg')]">All Areas</button>
                <button v-for="area in areas" :key="area.id" @click="filterByArea(area.id)" :class="['px-5 py-3 rounded-xl font-semibold transition-all duration-300 border-2', selectedAreaId === area.id ? 'bg-blue-600 text-white shadow-xl border-transparent' : (fullscreenDarkMode ? 'bg-gray-800 text-gray-300 border-gray-700' : 'bg-white text-gray-700 border-gray-300 shadow-lg')]">{{ area.name }}</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 items-stretch">
                <div v-for="device in devices" :key="device.id" :class="['rounded-2xl p-5 shadow-2xl flex flex-col gap-3', fullscreenDarkMode ? 'bg-gray-800' : 'bg-white']">
                    <div class="flex justify-between items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <h3 :class="['font-bold leading-tight truncate', fullscreenDarkMode ? 'text-white' : 'text-gray-900']" style="font-size: clamp(0.85rem, 1.2vw, 1.2rem)">{{ device.device_id }}</h3>
                            <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                <p v-if="device.area" :class="['text-xs font-semibold truncate', fullscreenDarkMode ? 'text-blue-400' : 'text-blue-600']">{{ device.area.name }}</p>
                                <span v-if="getTimelineData(device)?.activeShift" :class="['px-1.5 py-0.5 rounded-full text-[10px] font-bold', getShiftBadgeColor(getTimelineData(device)!.activeShift)]">S{{ getTimelineData(device)!.activeShift }}</span>
                                <span v-if="device.active_part_id" :class="['px-1.5 py-0.5 rounded-full text-[10px] font-bold', getActivePartColor(device)?.badge ?? 'bg-gray-700 text-gray-200']">
                                    {{ (device.parts ?? []).find(p => p.part_id === device.active_part_id)?.part_name ?? device.active_part_id }}
                                </span>
                            </div>
                            <p :class="['text-xs mt-0.5', fullscreenDarkMode ? 'text-gray-400' : 'text-gray-600']">{{ formatLastUpdate(device.last_update) }}</p>
                        </div>
                        <div :class="['px-2.5 py-1 rounded-lg text-xs font-bold shrink-0', device.counter_a > device.max_count || device.error_b ? 'bg-red-500 text-white' : isDeviceActive(device) ? 'bg-green-500 text-white' : 'bg-gray-500 text-white']">
                            {{ device.counter_a > device.max_count || device.error_b ? 'Over' : isDeviceActive(device) ? 'Active' : 'Idle' }}
                        </div>
                    </div>

                    <div v-if="device.is_paused" class="px-3 py-1.5 bg-blue-500/20 rounded-lg border-2 border-blue-500">
                        <div class="flex items-center gap-2 text-xs font-bold text-blue-300">
                            <PauseCircle class="w-3 h-3" /><span>Paused: {{ formatTime(device.total_pause_seconds) }}</span>
                        </div>
                    </div>

                    <div>
                        <div :class="['flex justify-between text-xs mb-1.5', fullscreenDarkMode ? 'text-gray-300' : 'text-gray-700']">
                            <span class="font-semibold">{{ device.has_counter_b ? 'Counter A' : 'Counter' }}</span>
                            <span class="font-bold">{{ device.counter_a }}/{{ device.max_count }}</span>
                        </div>
                        <div :class="['w-full h-3 rounded-full overflow-hidden', fullscreenDarkMode ? 'bg-gray-700' : 'bg-gray-200']">
                            <div :class="['h-full transition-all duration-500 bg-gradient-to-r', getActivePartColor(device)?.bar ?? 'from-green-400 to-emerald-500']"
                                :style="{ width: `${getProgressPercentage(device.counter_a, device.max_count)}%` }"></div>
                        </div>
                    </div>

                    <div v-if="device.has_counter_b">
                        <div :class="['flex justify-between text-xs mb-1.5', fullscreenDarkMode ? 'text-gray-300' : 'text-gray-700']">
                            <span class="font-semibold">Stroke</span>
                            <span class="font-bold">{{ device.counter_b }}{{ device.max_stroke > 0 ? `/${device.max_stroke}` : '' }}</span>
                        </div>
                        <div :class="['w-full h-3 rounded-full overflow-hidden', fullscreenDarkMode ? 'bg-gray-700' : 'bg-gray-200']">
                            <div class="h-full bg-gradient-to-r from-purple-400 to-pink-500 transition-all duration-500" :style="{ width: `${getProgressPercentage(device.counter_b, device.max_stroke > 0 ? device.max_stroke : device.max_count)}%` }"></div>
                        </div>
                    </div>

                    <div v-if="(device.parts ?? []).length > 0" class="flex flex-wrap gap-1">
                        <span v-for="({ part, color }) in getTodayPartHistories(device)" :key="part.part_id"
                            :class="['flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-semibold', part.part_id === device.active_part_id ? color.badge : (fullscreenDarkMode ? 'bg-gray-700 text-gray-400' : 'bg-gray-100 text-gray-400')]">
                            <span :class="['w-1.5 h-1.5 rounded-full', color.dot]"></span>
                            {{ part.part_name ?? part.part_id }}
                        </span>
                    </div>

                    <div>
                        <div class="flex justify-between mb-1.5">
                            <span :class="['font-semibold text-xs', fullscreenDarkMode ? 'text-gray-300' : 'text-gray-700']">Production Timeline</span>
                            <span v-if="device.counter_a > 0 && getTimelineData(device)" :class="['font-bold text-xs', Math.abs(device.delay_seconds) <= device.cycle_time ? 'text-green-400' : device.is_completed ? (device.delay_seconds > 0 ? 'text-red-400' : 'text-blue-400') : (device.delay_seconds > 0 ? 'text-orange-400' : 'text-blue-400')]">
                                {{ formatDelayTime(device.delay_seconds, device.is_completed, device.cycle_time) }}
                            </span>
                            <span v-else-if="!getTimelineData(device)" :class="['text-xs font-bold', fullscreenDarkMode ? 'text-gray-500' : 'text-gray-400']">—</span>
                        </div>
                        <div :class="['relative w-full h-3 rounded-full overflow-visible', fullscreenDarkMode ? 'bg-gray-700' : 'bg-gray-200']">
                            <template v-if="getTimelineData(device)">
                                <div v-for="(brk, idx) in getTimelineData(device)!.breaks" :key="`fs-break-${idx}`"
                                    :class="['absolute h-full rounded-sm', fullscreenDarkMode ? 'bg-orange-800' : 'bg-orange-200']"
                                    :style="{ left: `${brk.startPercentage}%`, width: `${brk.width}%` }"></div>

                                <template v-if="getTimelineSegments(device).length > 0">
                                    <div v-for="(seg, sIdx) in getTimelineSegments(device)" :key="`fs-seg-${sIdx}`"
                                        :class="['absolute h-full rounded-full transition-all duration-500 shadow-lg bg-gradient-to-r group/seg', seg.isOvertime ? 'from-orange-400 to-red-500' : seg.color]"
                                        :style="{ left: `${seg.left}%`, width: `${seg.width}%` }">
                                        <div v-if="seg.part" class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover/seg:opacity-100 transition-opacity z-30 pointer-events-none">
                                            <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                                                <div class="font-bold">{{ seg.part.part_name ?? seg.part.part_id }}</div>
                                                <div class="text-[10px] text-gray-300 font-mono">{{ seg.part.part_id }}</div>
                                                <div v-if="seg.part.production_started_at" class="text-[10px] text-gray-300 mt-0.5">
                                                    Start: {{ formatTimeOnly(new Date(seg.part.production_started_at)) }}
                                                </div>
                                                <div class="absolute top-full left-1/2 transform -translate-x-1/2"><div class="border-4 border-transparent border-t-gray-900"></div></div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div :class="['absolute h-full rounded-full transition-all duration-500 shadow-lg bg-gradient-to-r', getTimelineData(device)!.isOvertime ? 'from-orange-400 to-red-500' : (getActivePartColor(device)?.bar ?? 'from-green-400 to-emerald-500')]"
                                        :style="{ left: `${getTimelineData(device)!.productionStartPercentage}%`, width: `${getTimelineData(device)!.barWidth}%` }"></div>
                                </template>

                                <div class="absolute top-0 left-0 h-full w-1 bg-gray-500 rounded-full z-10"></div>
                                <div class="absolute top-0 right-0 h-full w-1 bg-gray-500 rounded-full z-10"></div>

                                <template v-if="getTimelineData(device)!.partsWithStartTime.length > 0">
                                    <div v-for="(ps, psIdx) in getTimelineData(device)!.partsWithStartTime" :key="`fs-ps-${psIdx}`"
                                        class="absolute top-0 h-full w-1 rounded-full z-10"
                                        :style="{ left: `${ps.startPercentage}%`, backgroundColor: ps.color.hex }"></div>
                                </template>
                                <template v-else>
                                    <div class="absolute top-0 h-full w-1 bg-green-500 rounded-full z-10" :style="{ left: `${getTimelineData(device)!.productionStartPercentage}%` }"></div>
                                </template>

                                <div v-if="!getTimelineData(device)!.isActive" class="absolute top-0 h-full w-1 bg-red-500 rounded-full z-10" :style="{ left: `${getTimelineData(device)!.lastActivityPercentage}%` }"></div>
                            </template>
                        </div>
                        <div :class="['flex justify-between text-[10px] font-medium px-0.5 mt-1', fullscreenDarkMode ? 'text-gray-400' : 'text-gray-600']">
                            <span v-if="getTimelineData(device)">{{ formatTimeOnly(getTimelineData(device)!.scheduleStart) }}</span>
                            <span v-else>—</span>
                            <span v-if="getTimelineData(device) && !device.is_completed && getTimelineData(device)!.isActive" class="text-blue-400 font-bold">Now: {{ formatTimeOnly(getTimelineData(device)!.lastUpdateTime) }}</span>
                            <span v-else-if="getTimelineData(device) && !device.is_completed && !getTimelineData(device)!.isActive" class="text-red-400 font-bold">Idle</span>
                            <span v-else></span>
                            <span v-if="getTimelineData(device)">{{ formatTimeOnly(getTimelineData(device)!.scheduleEnd) }}</span>
                            <span v-else>—</span>
                        </div>

                        <div v-if="getTimelineSegments(device).length > 1" class="flex gap-2 flex-wrap mt-1.5">
                            <div v-for="(seg, sIdx) in getTimelineSegments(device)" :key="`fs-legend-${sIdx}`"
                                class="flex items-center gap-1 text-[10px]"
                                :class="fullscreenDarkMode ? 'text-gray-400' : 'text-gray-500'">
                                <span class="w-2 h-2 rounded-full inline-block shrink-0" :style="{ backgroundColor: seg.hex }"></span>
                                <span>{{ seg.part?.part_name ?? seg.part?.part_id ?? '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="showSettingsModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50" @click.self="showSettingsModal = false">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-96 shadow-2xl max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">Settings - {{ selectedDevice?.device_id }}</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Line</label>
                    <select v-model="editLineId" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500">
                        <option :value="null">No Line Connected</option>
                        <option v-for="line in lines" :key="line.id" :value="line.id">{{ line.line_name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Area</label>
                    <div class="flex gap-2">
                        <select v-if="!showNewAreaInput" v-model="editAreaId" class="flex-1 border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500">
                            <option :value="null">Tidak Ada Area</option>
                            <option v-for="area in areas" :key="area.id" :value="area.id">{{ area.name }}</option>
                        </select>
                        <input v-else v-model="editNewAreaName" type="text" placeholder="Nama area baru..." class="flex-1 border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500" />
                        <button @click="toggleNewAreaInput" :class="['px-4 py-2.5 rounded-xl font-semibold', showNewAreaInput ? 'bg-gray-600 text-white' : 'bg-green-600 text-white']">
                            {{ showNewAreaInput ? 'Pilih' : 'Baru' }}
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Max Count</label>
                    <input v-model.number="editMaxCount" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500" />
                </div>
                <div v-if="selectedDevice?.has_counter_b">
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Max Stroke</label>
                    <input v-model.number="editMaxStroke" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500" />
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">NG</label>
                    <input v-model.number="editReject" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500" />
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Cycle Time (s)</label>
                    <input v-model.number="editCycleTime" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500" />
                </div>
            </div>
            <div class="flex gap-2 mt-6">
                <button @click="updateSettings" class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:shadow-lg transition-all">Update</button>
                <button @click="showResetConfirm = true" class="px-4 py-2.5 bg-red-600 text-white rounded-xl font-semibold hover:shadow-lg transition-all">Reset</button>
                <button @click="showSettingsModal = false" class="flex-1 bg-gray-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:shadow-lg transition-all">Cancel</button>
            </div>
        </div>
    </div>

    <div v-if="showPartSettingsModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50" @click.self="showPartSettingsModal = false">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-96 shadow-2xl">
            <h3 class="text-xl font-bold mb-1 text-gray-900 dark:text-white">Part Settings</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ selectedDevice?.device_id }} — <span class="font-mono text-xs">{{ selectedPart?.part_id }}</span></p>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Part Name</label>
                    <input v-model="editPartName" type="text" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500" />
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Max Count</label>
                    <input v-model.number="editPartMaxCount" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500" />
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Max Stroke</label>
                    <input v-model.number="editPartMaxStroke" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500" />
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Cycle Time (s)</label>
                    <input v-model.number="editPartCycleTime" type="number" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 dark:bg-gray-700 focus:border-blue-500" />
                </div>
            </div>
            <div class="flex gap-2 mt-6">
                <button @click="updatePartSettings" class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:shadow-lg transition-all">Update</button>
                <button @click="showPartSettingsModal = false" class="flex-1 bg-gray-600 text-white px-4 py-2.5 rounded-xl font-semibold hover:shadow-lg transition-all">Cancel</button>
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
