<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { BarChart3, TrendingUp, Package, Undo2, ArrowDownRight, Table, PieChart } from 'lucide-vue-next';

interface Summary {
    total_transaksi: number;
    total_pengembalian: number;
    total_material_diambil: number;
    total_material_dikembalikan: number;
    total_material_terpakai: number;
}

interface MaterialData {
    material_id: string;
    nama_material: string;
    satuan: string;
    jumlah_pengambilan: number;
    total_qty: number;
}

interface MaterialPengembalian {
    material_id: string;
    nama_material: string;
    satuan: string;
    jumlah_pengembalian: number;
    total_qty_pengembalian: number;
}

interface ShiftData {
    shift: number;
    total_transaksi: number;
    total_qty: number;
}

interface TrendData {
    tanggal: string;
    total_transaksi: number;
    total_qty: number;
}

interface Props {
    summary: Summary;
    topMaterialsByFrequency: MaterialData[];
    topMaterialsByQuantity: MaterialData[];
    allMaterialsByFrequency: MaterialData[];
    allMaterialsByQuantity: MaterialData[];
    transaksiPerShift: ShiftData[];
    transaksiTrend: TrendData[];
    topMaterialPengembalian: MaterialPengembalian[];
    allMaterialPengembalian: MaterialPengembalian[];
    filters: {
        start_date: string;
        end_date: string;
    };
}

const props = defineProps<Props>();

const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);
const viewMode = ref<'chart' | 'table'>('chart');

const hoveredQtyBar = ref<number | null>(null);
const hoveredFreqBar = ref<number | null>(null);
const hoveredReturnBar = ref<number | null>(null);
const tooltipPosition = ref({ top: '0px', left: '0px' });

const updateTooltipPosition = (event: MouseEvent) => {
    const x = event.clientX;
    const y = event.clientY;
    tooltipPosition.value = {
        top: `${y - 120}px`,
        left: `${x}px`,
    };
};

const clearHover = () => {
    hoveredQtyBar.value = null;
    hoveredFreqBar.value = null;
    hoveredReturnBar.value = null;
};

const formatNumber = (num: number) => {
    return new Intl.NumberFormat('id-ID').format(num);
};


const applyFilter = () => {
    router.get('/transaksi/dashboard', {
        start_date: startDate.value,
        end_date: endDate.value,
    }, { preserveState: false });
};

const resetFilter = () => {
    const today = new Date().toISOString().split('T')[0];
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

    startDate.value = thirtyDaysAgo.toISOString().split('T')[0];
    endDate.value = today.toString().split('T')[0];
    applyFilter();
};

const setQuickFilter = (days: number) => {
    const today = new Date();
    const pastDate = new Date();
    pastDate.setDate(pastDate.getDate() - days);

    startDate.value = pastDate.toISOString().split('T')[0];
    endDate.value = today.toISOString().split('T')[0];
    applyFilter();
};

// Pie Chart Computation
const shiftChartData = computed(() => {
    const total = props.transaksiPerShift.reduce((sum, item) => sum + item.total_transaksi, 0);
    let currentAngle = -90;

    return props.transaksiPerShift.map((item) => {
        const percentage = (item.total_transaksi / total) * 100;
        const angle = (percentage / 100) * 360;
        const startAngle = currentAngle;
        const endAngle = currentAngle + angle;
        currentAngle = endAngle;

        return {
            shift: item.shift,
            total_transaksi: item.total_transaksi,
            total_qty: item.total_qty,
            percentage: percentage,
            startAngle: startAngle,
            endAngle: endAngle
        };
    });
});

const getShiftColor = (shift: number) => {
    const colors = {
        1: '#3b82f6',
        2: '#10b981',
        3: '#f59e0b'
    };
    return colors[shift as keyof typeof colors] || '#6b7280';
};

const createPieSlicePath = (startAngle: number, endAngle: number, radius: number = 100) => {
    const centerX = 120;
    const centerY = 120;

    const startRad = (startAngle * Math.PI) / 180;
    const endRad = (endAngle * Math.PI) / 180;

    const x1 = centerX + radius * Math.cos(startRad);
    const y1 = centerY + radius * Math.sin(startRad);
    const x2 = centerX + radius * Math.cos(endRad);
    const y2 = centerY + radius * Math.sin(endRad);

    const largeArc = endAngle - startAngle > 180 ? 1 : 0;

    return `M ${centerX} ${centerY} L ${x1} ${y1} A ${radius} ${radius} 0 ${largeArc} 1 ${x2} ${y2} Z`;
};

// Bar Chart Data
const maxQtyValue = computed(() => {
    return Math.max(...props.topMaterialsByQuantity.map(m => m.total_qty), 1);
});

const maxFreqValue = computed(() => {
    return Math.max(...props.topMaterialsByFrequency.map(m => m.jumlah_pengambilan), 1);
});

const maxReturnValue = computed(() => {
    return Math.max(...props.topMaterialPengembalian.map(m => m.jumlah_pengembalian), 1);
});

onMounted(() => {
    document.addEventListener('mousemove', updateTooltipPosition);
});

onUnmounted(() => {
    document.removeEventListener('mousemove', updateTooltipPosition);
});
</script>

<template>
    <Head title="Dashboard Transaksi Material" />
    <AppLayout :breadcrumbs="[
        { title: 'Dashboard', href: '/transaksi/dashboard' }
    ]">
        <div class="p-4 space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <BarChart3 class="w-6 h-6 text-blue-600" />
                        Dashboard Transaksi Material
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Analisis dan statistik pengambilan material
                    </p>
                </div>
            </div>

            <!-- Date Filter -->
            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                <div class="flex flex-col md:flex-row gap-3 items-end">
                    <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Dari Tanggal</label>
                            <input
                                v-model="startDate"
                                type="date"
                                class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar text-sm focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Sampai Tanggal</label>
                            <input
                                v-model="endDate"
                                type="date"
                                class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar text-sm focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="applyFilter"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm"
                        >
                            Terapkan
                        </button>
                        <button
                            @click="resetFilter"
                            class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors text-sm"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <!-- Quick Filters -->
                <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-sidebar-border">
                    <span class="text-xs text-gray-500 dark:text-gray-400 self-center mr-2">Quick Filter:</span>
                    <button
                        @click="setQuickFilter(7)"
                        class="px-2.5 py-1 text-xs rounded-md bg-gray-100 dark:bg-sidebar-accent hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    >
                        7 Hari
                    </button>
                    <button
                        @click="setQuickFilter(30)"
                        class="px-2.5 py-1 text-xs rounded-md bg-gray-100 dark:bg-sidebar-accent hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    >
                        30 Hari
                    </button>
                    <button
                        @click="setQuickFilter(90)"
                        class="px-2.5 py-1 text-xs rounded-md bg-gray-100 dark:bg-sidebar-accent hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    >
                        90 Hari
                    </button>
                </div>
            </div>
            <!-- Summary Cards with Pie Chart -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- Left: 4 Summary Cards -->
                <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Total Transaksi -->
                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-5 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total Transaksi</p>
                                <p class="text-3xl font-bold text-blue-600">{{ formatNumber(summary.total_transaksi) }}</p>
                                <p class="text-xs text-gray-500 mt-2 flex items-center gap-1">
                                    <Package class="w-3 h-3" />
                                    Pengambilan Material
                                </p>
                            </div>
                            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-full">
                                <Package class="w-8 h-8 text-blue-600" />
                            </div>
                        </div>
                    </div>

                    <!-- Total Pengembalian -->
                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-5 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total Pengembalian</p>
                                <p class="text-3xl font-bold text-orange-600">{{ formatNumber(summary.total_pengembalian) }}</p>
                                <p class="text-xs text-gray-500 mt-2 flex items-center gap-1">
                                    <Undo2 class="w-3 h-3" />
                                    Return Material
                                </p>
                            </div>
                            <div class="p-3 bg-orange-100 dark:bg-orange-900/30 rounded-full">
                                <Undo2 class="w-8 h-8 text-orange-600" />
                            </div>
                        </div>
                    </div>

                    <!-- Material Dikembalikan -->
                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-5 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Dikembalikan</p>
                                <p class="text-3xl font-bold text-purple-600">{{ formatNumber(summary.total_material_dikembalikan) }}</p>
                                <p class="text-xs text-gray-500 mt-2 flex items-center gap-1">
                                    <ArrowDownRight class="w-3 h-3" />
                                    Total Quantity
                                </p>
                            </div>
                            <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-full">
                                <Undo2 class="w-8 h-8 text-purple-600" />
                            </div>
                        </div>
                    </div>

                    <!-- Terpakai -->
                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-5 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Terpakai</p>
                                <p class="text-3xl font-bold text-indigo-600">{{ formatNumber(summary.total_material_terpakai) }}</p>
                                <p class="text-xs text-gray-500 mt-2 flex items-center gap-1">
                                    <Package class="w-3 h-3" />
                                    Usage
                                </p>
                            </div>
                            <div class="p-3 bg-indigo-100 dark:bg-indigo-900/30 rounded-full">
                                <Package class="w-8 h-8 text-indigo-600" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Pie Chart -->
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-5 hover:shadow-lg transition-shadow">
                    <div class="mb-4">
                        <h3 class="font-semibold text-base flex items-center gap-2">
                            <PieChart class="w-5 h-5 text-blue-600" />
                            Distribusi per Shift
                        </h3>
                        <p class="text-xs text-gray-500 mt-1">Transaksi per shift kerja</p>
                    </div>

                    <div class="flex flex-col items-center justify-center">
                        <!-- Pie Chart SVG -->
                        <svg width="180" height="180" viewBox="0 0 240 240">
                            <g v-for="(data, index) in shiftChartData" :key="index">
                                <path
                                    :d="createPieSlicePath(data.startAngle, data.endAngle)"
                                    :fill="getShiftColor(data.shift)"
                                    class="transition-all duration-300 hover:opacity-80 cursor-pointer"
                                />
                            </g>
                            <circle cx="120" cy="120" r="50" fill="white" class="dark:fill-gray-800" />
                            <text x="120" y="115" text-anchor="middle" class="fill-gray-700 dark:fill-gray-300 text-xs font-semibold">Total</text>
                            <text x="120" y="135" text-anchor="middle" class="fill-gray-900 dark:fill-gray-100 text-lg font-bold">
                                {{ formatNumber(summary.total_transaksi) }}
                            </text>
                        </svg>

                        <div class="mt-4 space-y-2 w-full">
                            <div v-for="data in shiftChartData" :key="data.shift"
                                 class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: getShiftColor(data.shift) }"></div>
                                    <span class="font-medium">Shift {{ data.shift }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold">{{ formatNumber(data.total_transaksi) }}</span>
                                    <span class="text-gray-500 ml-1">({{ data.percentage.toFixed(1) }}%)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-sidebar rounded-lg shadow-lg border border-sidebar-border">
                <div class="flex border-b border-sidebar-border">
                    <button
                        @click="viewMode = 'chart'"
                        :class="[
                            'flex-1 px-6 py-4 font-semibold text-sm transition-all flex items-center justify-center gap-2',
                            viewMode === 'chart'
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-50 dark:bg-sidebar-accent text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-sidebar-accent/50'
                        ]"
                    >
                        <BarChart3 class="w-5 h-5" />Grafik & Chart
                    </button>
                    <button
                        @click="viewMode = 'table'"
                        :class="[
                            'flex-1 px-6 py-4 font-semibold text-sm transition-all flex items-center justify-center gap-2',
                            viewMode === 'table'
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-50 dark:bg-sidebar-accent text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-sidebar-accent/50'
                        ]"
                    >
                        <Table class="w-5 h-5" />Detail Tabel
                    </button>
                </div>

                <div v-if="viewMode === 'chart'" class="p-6 space-y-8">
                    <!-- Top 10 Material by Quantity -->
                    <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/10 dark:to-emerald-900/10 rounded-xl p-6 border border-green-200 dark:border-green-800">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Package class="w-5 h-5 text-green-600" />
                                Top 10 Material (Berdasarkan Qty)
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400 bg-white dark:bg-sidebar px-3 py-1 rounded-full">
                                {{ topMaterialsByQuantity.length }} material
                            </span>
                        </div>

                        <div v-if="topMaterialsByQuantity.length > 0" class="bg-white dark:bg-sidebar rounded-xl p-6 shadow-sm">
                            <div class="space-y-3">
                                <div v-for="(item, index) in topMaterialsByQuantity" :key="index"
                                     class="flex items-center gap-3"
                                     @mouseenter="hoveredQtyBar = index"
                                     @mouseleave="clearHover">
                                    <div class="w-32 text-xs text-gray-600 dark:text-gray-400 font-medium truncate" :title="item.nama_material">
                                        {{ item.nama_material }}
                                    </div>
                                    <div class="flex-1 flex items-center gap-3">
                                        <div class="flex-1 h-10 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden relative">
                                            <div
                                                class="h-full bg-gradient-to-r from-green-500 to-green-600 transition-all duration-300 rounded-lg"
                                                :class="{ 'from-green-600 to-green-700 shadow-lg': hoveredQtyBar === index }"
                                                :style="{ width: `${(item.total_qty / maxQtyValue) * 100}%` }">
                                            </div>
                                        </div>
                                        <div class="text-right min-w-[100px]">
                                            <div class="text-sm font-bold text-green-600">{{ formatNumber(item.total_qty) }} {{ item.satuan }}</div>
                                            <div class="text-xs text-gray-500">{{ item.jumlah_pengambilan }}x</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-20 text-gray-500">
                            <Package class="w-16 h-16 mx-auto mb-3 opacity-30" />
                            <p class="font-medium">Tidak ada data untuk periode ini</p>
                        </div>

                        <!-- Tooltip for Quantity -->
                        <Teleport to="body">
                            <div
                                v-if="hoveredQtyBar !== null"
                                class="fixed pointer-events-none z-[9999] -translate-x-1/2"
                                :style="tooltipPosition"
                            >
                                <div class="bg-gray-900 text-white text-xs rounded-lg shadow-2xl p-3 min-w-[200px]">
                                    <div class="font-bold mb-1">{{ topMaterialsByQuantity[hoveredQtyBar].nama_material }}</div>
                                    <div class="text-gray-300 text-[10px] mb-2">ID: {{ topMaterialsByQuantity[hoveredQtyBar].material_id }}</div>
                                    <div class="space-y-1 pt-2 border-t border-gray-700">
                                        <div class="flex justify-between">
                                            <span class="text-gray-400">Total Qty:</span>
                                            <span class="font-bold text-green-400">{{ formatNumber(topMaterialsByQuantity[hoveredQtyBar].total_qty) }} {{ topMaterialsByQuantity[hoveredQtyBar].satuan }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-400">Frekuensi:</span>
                                            <span class="font-bold text-blue-400">{{ topMaterialsByQuantity[hoveredQtyBar].jumlah_pengambilan }}x</span>
                                        </div>
                                    </div>
                                    <div class="absolute top-full left-1/2 -translate-x-1/2">
                                        <div class="border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            </div>
                        </Teleport>
                    </div>
                    <!-- Top 10 Material by Frequency -->
                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/10 dark:to-indigo-900/10 rounded-xl p-6 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <TrendingUp class="w-5 h-5 text-blue-600" />
                                Top 10 Material (Berdasarkan Frekuensi)
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400 bg-white dark:bg-sidebar px-3 py-1 rounded-full">
                                {{ topMaterialsByFrequency.length }} material
                            </span>
                        </div>

                        <div v-if="topMaterialsByFrequency.length > 0" class="bg-white dark:bg-sidebar rounded-xl p-6 shadow-sm">
                            <div class="space-y-3">
                                <div v-for="(item, index) in topMaterialsByFrequency" :key="index"
                                     class="flex items-center gap-3"
                                     @mouseenter="hoveredFreqBar = index"
                                     @mouseleave="clearHover">
                                    <div class="w-32 text-xs text-gray-600 dark:text-gray-400 font-medium truncate" :title="item.nama_material">
                                        {{ item.nama_material }}
                                    </div>
                                    <div class="flex-1 flex items-center gap-3">
                                        <div class="flex-1 h-10 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden relative">
                                            <div
                                                class="h-full bg-gradient-to-r from-blue-500 to-blue-600 transition-all duration-300 rounded-lg"
                                                :class="{ 'from-blue-600 to-blue-700 shadow-lg': hoveredFreqBar === index }"
                                                :style="{ width: `${(item.jumlah_pengambilan / maxFreqValue) * 100}%` }">
                                            </div>
                                        </div>
                                        <div class="text-right min-w-[100px]">
                                            <div class="text-sm font-bold text-blue-600">{{ item.jumlah_pengambilan }}x</div>
                                            <div class="text-xs text-gray-500">{{ formatNumber(item.total_qty) }} {{ item.satuan }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-20 text-gray-500">
                            <TrendingUp class="w-16 h-16 mx-auto mb-3 opacity-30" />
                            <p class="font-medium">Tidak ada data untuk periode ini</p>
                        </div>

                        <!-- Tooltip for Frequency -->
                        <Teleport to="body">
                            <div
                                v-if="hoveredFreqBar !== null"
                                class="fixed pointer-events-none z-[9999] -translate-x-1/2"
                                :style="tooltipPosition"
                            >
                                <div class="bg-gray-900 text-white text-xs rounded-lg shadow-2xl p-3 min-w-[200px]">
                                    <div class="font-bold mb-1">{{ topMaterialsByFrequency[hoveredFreqBar].nama_material }}</div>
                                    <div class="text-gray-300 text-[10px] mb-2">ID: {{ topMaterialsByFrequency[hoveredFreqBar].material_id }}</div>
                                    <div class="space-y-1 pt-2 border-t border-gray-700">
                                        <div class="flex justify-between">
                                            <span class="text-gray-400">Frekuensi:</span>
                                            <span class="font-bold text-blue-400">{{ topMaterialsByFrequency[hoveredFreqBar].jumlah_pengambilan }}x</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-400">Total Qty:</span>
                                            <span class="font-bold text-green-400">{{ formatNumber(topMaterialsByFrequency[hoveredFreqBar].total_qty) }} {{ topMaterialsByFrequency[hoveredFreqBar].satuan }}</span>
                                        </div>
                                    </div>
                                    <div class="absolute top-full left-1/2 -translate-x-1/2">
                                        <div class="border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            </div>
                        </Teleport>
                    </div>

                    <!-- Material Pengembalian Chart -->
                    <div class="bg-gradient-to-br from-orange-50 to-amber-50 dark:from-orange-900/10 dark:to-amber-900/10 rounded-xl p-6 border border-orange-200 dark:border-orange-800">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Undo2 class="w-5 h-5 text-orange-600" />
                                Top 10 Material dengan Pengembalian Terbanyak
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400 bg-white dark:bg-sidebar px-3 py-1 rounded-full">
                                {{ topMaterialPengembalian.length }} material
                            </span>
                        </div>

                        <div v-if="topMaterialPengembalian.length > 0" class="bg-white dark:bg-sidebar rounded-xl p-6 shadow-sm">
                            <div class="space-y-3">
                                <div v-for="(item, index) in topMaterialPengembalian" :key="index"
                                     class="flex items-center gap-3"
                                     @mouseenter="hoveredReturnBar = index"
                                     @mouseleave="clearHover">
                                    <div class="w-32 text-xs text-gray-600 dark:text-gray-400 font-medium truncate" :title="item.nama_material">
                                        {{ item.nama_material }}
                                    </div>
                                    <div class="flex-1 flex items-center gap-3">
                                        <div class="flex-1 h-10 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden relative">
                                            <div
                                                class="h-full bg-gradient-to-r from-orange-500 to-orange-600 transition-all duration-300 rounded-lg"
                                                :class="{ 'from-orange-600 to-orange-700 shadow-lg': hoveredReturnBar === index }"
                                                :style="{ width: topMaterialPengembalian.length > 0 ? `${(item.jumlah_pengembalian / maxReturnValue) * 100}%` : '0%' }">
                                            </div>
                                        </div>
                                        <div class="text-right min-w-[100px]">
                                            <div class="text-sm font-bold text-orange-600">{{ item.jumlah_pengembalian }}x</div>
                                            <div class="text-xs text-gray-500">{{ formatNumber(item.total_qty_pengembalian) }} {{ item.satuan }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-20 text-gray-500">
                            <Undo2 class="w-16 h-16 mx-auto mb-3 opacity-30" />
                            <p class="font-medium">Tidak ada data pengembalian</p>
                        </div>

                        <!-- Tooltip for Return -->
                        <Teleport to="body">
                            <div
                                v-if="hoveredReturnBar !== null"
                                class="fixed pointer-events-none z-[9999] -translate-x-1/2"
                                :style="tooltipPosition"
                            >
                                <div class="bg-gray-900 text-white text-xs rounded-lg shadow-2xl p-3 min-w-[200px]">
                                    <div class="font-bold mb-1">{{ topMaterialPengembalian[hoveredReturnBar].nama_material }}</div>
                                    <div class="text-gray-300 text-[10px] mb-2">ID: {{ topMaterialPengembalian[hoveredReturnBar].material_id }}</div>
                                    <div class="space-y-1 pt-2 border-t border-gray-700">
                                        <div class="flex justify-between">
                                            <span class="text-gray-400">Frekuensi Return:</span>
                                            <span class="font-bold text-orange-400">{{ topMaterialPengembalian[hoveredReturnBar].jumlah_pengembalian }}x</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-400">Total Qty Return:</span>
                                            <span class="font-bold text-green-400">{{ formatNumber(topMaterialPengembalian[hoveredReturnBar].total_qty_pengembalian) }} {{ topMaterialPengembalian[hoveredReturnBar].satuan }}</span>
                                        </div>
                                    </div>
                                    <div class="absolute top-full left-1/2 -translate-x-1/2">
                                        <div class="border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            </div>
                        </Teleport>
                    </div>
                </div>
                <!-- Table View -->
                <div v-if="viewMode === 'table'" id="table-section" class="overflow-x-auto">
                    <!-- Transaksi per Shift Table -->
                    <div class="p-6">
                        <div class="mb-6">
                            <h3 class="font-semibold text-lg flex items-center gap-2">
                                <PieChart class="w-5 h-5 text-blue-600" />
                                Detail Transaksi per Shift
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">Rincian lengkap transaksi dan quantity per shift</p>
                        </div>

                        <table class="w-full mb-8">
                            <thead class="bg-gray-50 dark:bg-sidebar-accent">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shift</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Transaksi</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Quantity</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Rata-rata Qty/Transaksi</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Persentase</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border">
                                <tr
                                    v-for="item in transaksiPerShift"
                                    :key="item.shift"
                                    class="hover:bg-sidebar-accent transition-colors"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: getShiftColor(item.shift) }"></div>
                                            <span class="text-sm font-semibold">Shift {{ item.shift }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            {{ formatNumber(item.total_transaksi) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-medium">
                                        {{ formatNumber(item.total_qty) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        {{ formatNumber(Math.round(item.total_qty / item.total_transaksi)) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            {{ ((item.total_transaksi / summary.total_transaksi) * 100).toFixed(1) }}%
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="transaksiPerShift.length === 0">
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        Tidak ada data transaksi per shift
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="mt-8 mb-6">
                            <h3 class="font-semibold text-lg flex items-center gap-2">
                                <Package class="w-5 h-5 text-green-600" />
                                Material Berdasarkan Total Quantity
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">Daftar lengkap material berdasarkan quantity ({{ allMaterialsByQuantity.length }} material)</p>
                        </div>

                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-sidebar-accent">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Material ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Material</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Qty</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Frekuensi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border">
                                <tr
                                    v-for="(item, index) in allMaterialsByQuantity"
                                    :key="item.material_id"
                                    class="hover:bg-sidebar-accent transition-colors"
                                >
                                    <td class="px-4 py-3 text-sm font-medium text-gray-500">{{ index + 1 }}</td>
                                    <td class="px-4 py-3 text-sm font-medium">{{ item.material_id }}</td>
                                    <td class="px-4 py-3 text-sm">{{ item.nama_material }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            {{ formatNumber(item.total_qty) }} {{ item.satuan }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-medium">
                                        {{ formatNumber(item.jumlah_pengambilan) }}x
                                    </td>
                                </tr>
                                <tr v-if="allMaterialsByQuantity.length === 0">
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        Tidak ada data
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                             <div class="mt-8 mb-6">
                            <h3 class="font-semibold text-lg flex items-center gap-2">
                                <TrendingUp class="w-5 h-5 text-blue-600" />
                                Material Berdasarkan Frekuensi Pengambilan
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">Daftar lengkap material berdasarkan frekuensi ({{ allMaterialsByFrequency.length }} material)</p>
                        </div>

                        <table class="w-full mb-8">
                            <thead class="bg-gray-50 dark:bg-sidebar-accent">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Material ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Material</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Frekuensi</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Qty</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border">
                                <tr
                                    v-for="(item, index) in allMaterialsByFrequency"
                                    :key="item.material_id"
                                    class="hover:bg-sidebar-accent transition-colors"
                                >
                                    <td class="px-4 py-3 text-sm font-medium text-gray-500">{{ index + 1 }}</td>
                                    <td class="px-4 py-3 text-sm font-medium">{{ item.material_id }}</td>
                                    <td class="px-4 py-3 text-sm">{{ item.nama_material }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            {{ formatNumber(item.jumlah_pengambilan) }}x
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-medium">
                                        {{ formatNumber(item.total_qty) }} {{ item.satuan }}
                                    </td>
                                </tr>
                                <tr v-if="allMaterialsByFrequency.length === 0">
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        Tidak ada data
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                              <div class="mt-8 mb-6">
                            <h3 class="font-semibold text-lg flex items-center gap-2">
                                <Undo2 class="w-5 h-5 text-orange-600" />
                                Material dengan Pengembalian
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">Daftar lengkap material yang dikembalikan ({{ allMaterialPengembalian.length }} material)</p>
                        </div>

                        <table class="w-full mb-8">
                            <thead class="bg-gray-50 dark:bg-sidebar-accent">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Material ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Material</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Frekuensi Return</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Qty Return</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border">
                                <tr
                                    v-for="(item, index) in allMaterialPengembalian"
                                    :key="item.material_id"
                                    class="hover:bg-sidebar-accent transition-colors"
                                >
                                    <td class="px-4 py-3 text-sm font-medium text-gray-500">{{ index + 1 }}</td>
                                    <td class="px-4 py-3 text-sm font-medium">{{ item.material_id }}</td>
                                    <td class="px-4 py-3 text-sm">{{ item.nama_material }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                            {{ formatNumber(item.jumlah_pengembalian) }}x
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-medium">
                                        {{ formatNumber(item.total_qty_pengembalian) }} {{ item.satuan }}
                                    </td>
                                </tr>
                                <tr v-if="allMaterialPengembalian.length === 0">
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        Tidak ada data pengembalian
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
