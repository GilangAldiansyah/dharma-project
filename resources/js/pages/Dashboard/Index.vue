<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, nextTick } from 'vue';
import { TrendingDown, TrendingUp, AlertTriangle, Package, Search, Download, RefreshCw, BarChart3, Activity, Table } from 'lucide-vue-next';
import axios from 'axios';

interface StockData {
    id: number;
    bl_type: 'BL1' | 'BL2';
    sap_finish: string;
    id_sap: string;
    material_name: string;
    part_no: string;
    part_name: string;
    type: string;
    qty_unit: number;
    qty_day: number;
    qty_day_actual: number;
    stock_awal: number;
    total_produksi: number;
    total_out: number;
    ng_shift1: number;
    ng_shift2: number;
    ng_shift3: number;
    soh: number;
    coverage_days: number;
    status: 'critical' | 'warning' | 'normal' | 'overstock';
}

interface Statistics {
    critical: number;
    warning: number;
    normal: number;
    overstock: number;
    totalSOH: number;
    totalOut: number;
    totalProduksi: number;
    totalStockAwal: number;
    totalNG: number;
    avgCoverage: number;
    bl1Count: number;
    bl2Count: number;
    totalItems: number;
}

interface Props {
    stockData: StockData[];
    statistics: Statistics;
    selectedDate: string;
}

const props = defineProps<Props>();

const selectedDate = ref(props.selectedDate);
const filterBL = ref<'all' | 'BL1' | 'BL2'>('all');
const filterStatus = ref<'all' | 'critical' | 'warning' | 'normal' | 'overstock'>('all');
const searchQuery = ref('');
const sortBy = ref<'coverage_asc' | 'coverage_desc' | 'soh_asc' | 'soh_desc'>('coverage_asc');
const isExporting = ref(false);
const viewMode = ref<'bar' | 'table'>('bar');
const highlightedId = ref<number | null>(null);

const changeDate = () => {
    router.get('/dashboard', { date: selectedDate.value }, { preserveState: false });
};

const refreshData = () => {
    router.reload({ only: ['stockData', 'statistics'] });
};

const filteredData = computed(() => {
    let data = [...props.stockData];

    if (filterBL.value !== 'all') {
        data = data.filter(item => item.bl_type === filterBL.value);
    }

    if (filterStatus.value !== 'all') {
        data = data.filter(item => item.status === filterStatus.value);
    }

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        data = data.filter(item =>
            item.material_name.toLowerCase().includes(query) ||
            item.id_sap.toLowerCase().includes(query) ||
            item.sap_finish.toLowerCase().includes(query) ||
            item.part_no.toLowerCase().includes(query)
        );
    }

    // Sorting
    if (sortBy.value === 'coverage_asc') {
        data.sort((a, b) => a.coverage_days - b.coverage_days);
    } else if (sortBy.value === 'coverage_desc') {
        data.sort((a, b) => b.coverage_days - a.coverage_days);
    } else if (sortBy.value === 'soh_asc') {
        data.sort((a, b) => a.soh - b.soh);
    } else if (sortBy.value === 'soh_desc') {
        data.sort((a, b) => b.soh - a.soh);
    }

    return data;
});

const getStatusColor = (status: string) => {
    switch (status) {
        case 'critical': return 'bg-red-100 text-red-700 border-red-300 dark:bg-red-900/30 dark:text-red-400';
        case 'warning': return 'bg-yellow-100 text-yellow-700 border-yellow-300 dark:bg-yellow-900/30 dark:text-yellow-400';
        case 'overstock': return 'bg-purple-100 text-purple-700 border-purple-300 dark:bg-purple-900/30 dark:text-purple-400';
        default: return 'bg-green-100 text-green-700 border-green-300 dark:bg-green-900/30 dark:text-green-400';
    }
};
const getBarColor = (item: StockData) => {
    // Prioritas 1: qty_day_actual = 0 = purple (TIDAK TERPAKAI)
    if (item.qty_day_actual === 0) {
        return 'bg-purple-500';
    }
    // Prioritas 2: SOH < qty_day_actual = merah (CRITICAL - stok kurang dari 1 hari)
    if (item.soh < item.qty_day_actual) {
        return 'bg-red-500';
    }
    // Prioritas 3: Coverage >= 15 = purple (OVERSTOCK)
    if (item.coverage_days >= 15) {
        return 'bg-purple-500';
    }
    // Default: Normal = hijau (semua yang lainnya)
    return 'bg-green-500';
};

const getCoverageColor = (item: StockData) => {
    if (item.qty_day_actual === 0) {
        return 'text-purple-400';
    }
    if (item.soh < item.qty_day_actual) {
        return 'text-red-400';
    }
    if (item.coverage_days >= 15) {
        return 'text-purple-400';
    }
    return 'text-green-400';
};

const getStatusLabel = (item: StockData) => {
    if (item.qty_day_actual === 0) {
        return '📦 TIDAK TERPAKAI';
    }
    if (item.soh < item.qty_day_actual) {
        return '🚨 KRISIS - Stok < 1 Hari';
    }
    if (item.coverage_days >= 15) {
        return '📦 BERLEBIH';
    }
    return '✅ AMAN';
};

const getCoverageTableColor = (item: StockData) => {
    if (item.qty_day_actual === 0) {
        return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
    }
    if (item.soh < item.qty_day_actual) {
        return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
    }
    if (item.coverage_days >= 15) {
        return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
    }
    return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
};
const handleBarClick = async (itemId: number) => {
    viewMode.value = 'table';
    highlightedId.value = itemId;

    await nextTick();

    // Scroll to the row
    const rowElement = document.getElementById(`row-${itemId}`);
    if (rowElement) {
        rowElement.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Remove highlight after 3 seconds
        setTimeout(() => {
            highlightedId.value = null;
        }, 3000);
    }
};

const exportToCSV = async () => {
    isExporting.value = true;
    try {
        const response = await axios.get('/dashboard/export', {
            params: { date: selectedDate.value },
            responseType: 'blob'
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `stock_dashboard_${selectedDate.value}.csv`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Export error:', error);
        alert('Error exporting data');
    } finally {
        isExporting.value = false;
    }
};
</script>

<template>
    <Head title="Dashboard Monitoring Stok" />
    <AppLayout :breadcrumbs="[{ title: 'Dashboard', href: '/dashboard' }]">
        <div class="p-6 space-y-6">
            <!-- Compact Header with Stats -->
            <div class="bg-white dark:bg-sidebar rounded-lg shadow-sm p-3 border border-sidebar-border">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-4">
                        <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                            <BarChart3 class="w-5 h-5 text-blue-600" />
                            Dashboard Stok
                        </h1>
                        <!-- Inline Statistics -->
                        <div class="flex items-center gap-2 text-xs">
                            <span class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded font-semibold">
                                🚨 {{ statistics.critical }}
                            </span>
                            <span class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 rounded font-semibold">
                                ⚠️ {{ statistics.warning }}
                            </span>
                            <span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded font-semibold">
                                ✅ {{ statistics.normal }}
                            </span>
                            <span class="px-2 py-1 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400 rounded font-semibold">
                                📦 {{ statistics.overstock }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="selectedDate"
                            type="date"
                            @change="changeDate"
                            class="px-2 py-1 text-xs border border-sidebar-border rounded focus:ring-2 focus:ring-blue-500 dark:bg-sidebar"
                        />
                        <button
                            @click="refreshData"
                            class="p-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
                            title="Refresh"
                        >
                            <RefreshCw class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filters & Search -->
            <div class="bg-white dark:bg-sidebar rounded-lg shadow-md p-4 border border-sidebar-border">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari material..."
                                class="w-full pl-9 pr-3 py-2 text-sm border border-sidebar-border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-sidebar"
                            />
                        </div>
                    </div>

                    <select
                        v-model="filterBL"
                        class="px-3 py-2 text-sm border border-sidebar-border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-sidebar"
                    >
                        <option value="all">Semua BL</option>
                        <option value="BL1">BL1</option>
                        <option value="BL2">BL2</option>
                    </select>

                    <select
                        v-model="filterStatus"
                        class="px-3 py-2 text-sm border border-sidebar-border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-sidebar"
                    >
                        <option value="all">Semua Status</option>
                        <option value="critical">🚨 Krisis</option>
                        <option value="warning">⚠️ Menipis</option>
                        <option value="normal">✅ Aman</option>
                        <option value="overstock">📦 Berlebih</option>
                    </select>

                    <select
                        v-model="sortBy"
                        class="px-3 py-2 text-sm border border-sidebar-border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-sidebar"
                    >
                        <option value="coverage_asc">Paling Kritis</option>
                        <option value="coverage_desc">Paling Banyak</option>
                        <option value="soh_asc">Stok: Rendah-Tinggi</option>
                        <option value="soh_desc">Stok: Tinggi-Rendah</option>
                    </select>

                    <button
                        @click="exportToCSV"
                        :disabled="isExporting"
                        class="px-3 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2 disabled:opacity-50"
                    >
                        <Download class="w-4 h-4" />
                        Export
                    </button>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="bg-white dark:bg-sidebar rounded-xl shadow-lg border border-sidebar-border" style="overflow: visible;">
                <div class="flex border-b border-sidebar-border">
                    <button
                        @click="viewMode = 'bar'"
                        :class="[
                            'flex-1 px-6 py-4 font-semibold text-sm transition-all flex items-center justify-center gap-2',
                            viewMode === 'bar'
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-50 dark:bg-sidebar-accent text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-sidebar-accent/50'
                        ]"
                    >
                        <BarChart3 class="w-5 h-5" />Bar Chart
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
                        <Table class="w-5 h-5" />Detail
                    </button>
                </div>

                <!-- Bar Chart View (Vertical) -->
                <div v-if="viewMode === 'bar'" class="p-6" style="overflow: visible;">
                    <div v-if="filteredData.length > 0" class="relative" style="overflow: visible;">
                        <!-- Chart Container -->
                        <div class="flex gap-3" style="overflow: visible;">
                            <!-- Y-axis labels -->
                            <div class="w-12 flex flex-col justify-between text-right text-xs text-gray-500 dark:text-gray-400" style="height: 450px; padding-bottom: 8px;">
                                <div>30+</div>
                                <div>20</div>
                                <div>10</div>
                                <div>0</div>
                            </div>

                            <!-- Chart area -->
                            <div class="flex-1 border-l-2 border-b-2 border-gray-300 dark:border-gray-600 relative" style="height: 450px; overflow: visible;">
                                <!-- Grid lines -->
                                <div class="absolute inset-0 pointer-events-none" style="z-index: 0;">
                                    <div class="absolute top-0 left-0 right-0 h-px bg-gray-200 dark:bg-gray-700"></div>
                                    <div class="absolute bg-gray-200 dark:bg-gray-700" style="top: 33.33%; left: 0; right: 0; height: 1px;"></div>
                                    <div class="absolute bg-gray-200 dark:bg-gray-700" style="top: 66.66%; left: 0; right: 0; height: 1px;"></div>
                                </div>

                                <!-- Bars Container -->
                                <div class="absolute inset-0 px-2" style="z-index: 1; overflow: visible;">
                                    <div class="h-full w-full flex items-end gap-px" style="overflow: visible;">
                                        <div
                                            v-for="(item, index) in filteredData"
                                            :key="item.id"
                                            class="relative group flex-1 cursor-pointer h-full flex items-end"
                                            :style="{ minWidth: '8px', maxWidth: '40px' }"
                                            @click="handleBarClick(item.id)"
                                        >
                                            <!-- Bar -->
                                            <div
                                                class="w-full rounded-t-sm transition-all duration-200 hover:brightness-110 hover:scale-105"
                                                :class="getBarColor(item)"
                                                :style="{
                                                    height: item.qty_day === 0 ? '100%' : `${Math.max(Math.min((item.coverage_days / 30) * 100, 100), 3)}%`
                                                }"
                                            ></div>

                                            <!-- Tooltip on hover -->
                                            <div
                                                class="absolute bottom-full mb-3 hidden group-hover:block pointer-events-none"
                                                :style="{
                                                    left: index < 5 ? '0' : index > filteredData.length - 5 ? 'auto' : '50%',
                                                    right: index > filteredData.length - 5 ? '0' : 'auto',
                                                    transform: index >= 5 && index <= filteredData.length - 5 ? 'translateX(-50%)' : 'none',
                                                    zIndex: 9999
                                                }"
                                            >
                                                <div class="bg-gray-900 text-white text-xs rounded-lg shadow-2xl p-3 whitespace-nowrap border border-gray-700">
                                                    <div class="font-bold mb-2 max-w-[250px] truncate text-sm">{{ item.material_name || item.id_sap }}</div>
                                                    <div class="space-y-1.5">
                                                        <div class="flex justify-between gap-6">
                                                            <span class="text-gray-400">SAP:</span>
                                                            <span class="font-semibold">{{ item.id_sap }}</span>
                                                        </div>
                                                        <div class="flex justify-between gap-6">
                                                            <span class="text-gray-400">BL:</span>
                                                            <span class="font-semibold">{{ item.bl_type }}</span>
                                                        </div>
                                                        <div class="flex justify-between gap-6">
                                                            <span class="text-gray-400">STOK:</span>
                                                            <span class="font-semibold">{{ item.soh.toLocaleString() }}</span>
                                                        </div>
                                                        <div class="flex justify-between gap-6">
                                                            <span class="text-gray-400">Kebutuhan/Hari:</span>
                                                            <span class="font-semibold">{{ item.qty_day_actual.toLocaleString() }}</span>
                                                        </div>
                                                        <div class="flex justify-between gap-6 pt-1.5 mt-1.5 border-t border-gray-700">
                                                            <span class="text-gray-400">Coverage:</span>
                                                            <span class="font-bold text-base" :class="getCoverageColor(item)">
                                                                {{ item.qty_day === 0 ? '∞' : item.coverage_days }} hari
                                                            </span>
                                                        </div>
                                                        <div class="pt-1.5 text-center">
                                                            <span class="inline-flex px-3 py-1 rounded-md text-xs font-bold" :class="getBarColor(item)">
                                                                {{ getStatusLabel(item) }}
                                                            </span>
                                                        </div>
                                                        <div class="pt-2 text-center border-t border-gray-700 mt-2">
                                                            <span class="text-blue-400 text-xs">👆 Klik untuk lihat di tabel</span>
                                                        </div>
                                                    </div>
                                                    <!-- Arrow -->
                                                    <div
                                                        class="absolute top-full"
                                                        :style="{
                                                            left: index < 5 ? '20px' : index > filteredData.length - 5 ? 'auto' : '50%',
                                                            right: index > filteredData.length - 5 ? '20px' : 'auto',
                                                            transform: index >= 5 && index <= filteredData.length - 5 ? 'translateX(-50%)' : 'none'
                                                        }"
                                                    >
                                                        <div class="border-[6px] border-transparent border-t-gray-900"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Legend -->
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-4 text-xs">
                            <div class="flex items-center gap-1.5">
                                <div class="w-4 h-4 bg-red-500 rounded"></div>
                                <span class="text-gray-700 dark:text-gray-300">Stok Menipis</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <div class="w-4 h-4 bg-green-500 rounded"></div>
                                <span class="text-gray-700 dark:text-gray-300">Stok Memenuhi</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <div class="w-4 h-4 bg-purple-500 rounded"></div>
                                <span class="text-gray-700 dark:text-gray-300">Stok Berlebih</span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="py-12 text-center text-gray-500 dark:text-gray-400">
                        <Package class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                        <p class="text-sm">Tidak ada data yang sesuai dengan filter</p>
                    </div>
                </div>

                <!-- Table View -->
                <div v-if="viewMode === 'table'" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b-2 border-sidebar-border sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">No</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">BL</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Nomor SAP</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Nama Material</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Stok Awal</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Produksi</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Keluar</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Stok Tersedia</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Kebutuhan/Hari</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Cukup Berapa Hari</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr
                                v-for="(item, index) in filteredData"
                                :key="item.id"
                                :id="`row-${item.id}`"
                                class="transition-all duration-300"
                                :class="[
                                    'hover:bg-gray-50 dark:hover:bg-sidebar-accent/30',
                                    highlightedId === item.id ? 'bg-blue-100 dark:bg-blue-900/30 animate-pulse' : ''
                                ]"
                            >
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ index + 1 }}</td>
                                <td class="px-4 py-3">
                                    <span :class="`inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold border ${getStatusColor(item.status)}`">
                                        <AlertTriangle v-if="item.status === 'critical'" class="w-3 h-3" />
                                        <TrendingDown v-else-if="item.status === 'warning'" class="w-3 h-3" />
                                        <TrendingUp v-else-if="item.status === 'overstock'" class="w-3 h-3" />
                                        <Activity v-else class="w-3 h-3" />
                                        {{ item.status === 'critical' ? 'KRISIS' : item.status === 'warning' ? 'MENIPIS' : item.status === 'overstock' ? 'BERLEBIH' : 'AMAN' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="`inline-flex px-2 py-1 rounded text-xs font-semibold ${
                                        item.bl_type === 'BL1' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
                                    }`">
                                        {{ item.bl_type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ item.id_sap || item.sap_finish }}</td>
                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ item.material_name }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ item.stock_awal.toLocaleString() }}</td>
                                <td class="px-4 py-3 text-right text-sm text-green-600 dark:text-green-400 font-semibold">+{{ item.total_produksi.toLocaleString() }}</td>
                                <td class="px-4 py-3 text-right text-sm text-red-600 dark:text-red-400 font-semibold">-{{ item.total_out.toLocaleString() }}</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-800 dark:text-gray-100">{{ item.soh.toLocaleString() }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ item.qty_day_actual.toLocaleString() }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="`inline-flex px-3 py-1 rounded-full text-sm font-bold ${getCoverageTableColor(item)}`">
                                        {{ item.qty_day === 0 ? '∞' : item.coverage_days }} hari
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="filteredData.length === 0">
                                <td colspan="11" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <Package class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                                    <p class="text-sm">Tidak ada data yang sesuai dengan filter</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="text-center text-sm text-gray-600 dark:text-gray-400">
                Menampilkan {{ filteredData.length }} dari {{ stockData.length }} item | Terakhir diupdate: {{ new Date().toLocaleString('id-ID') }}
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #555;
}

.dark ::-webkit-scrollbar-track {
    background: #1f2937;
}

.dark ::-webkit-scrollbar-thumb {
    background: #4b5563;
}

.dark ::-webkit-scrollbar-thumb:hover {
    background: #6b7280;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.animate-pulse {
    animation: pulse 1s cubic-bezier(0.4, 0, 0.6, 1) 3;
}
</style>
