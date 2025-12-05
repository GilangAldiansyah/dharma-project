<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Plus, Trash2, Save, TrendingUp, Loader, Search, Calendar, X, Copy } from 'lucide-vue-next';
import axios from 'axios';

interface MonthlyForecast {
    id: number | null;
    sap_no: string;
    product_unit: string;
    part_name: string;
    type: string;
    year: number;
    month: number;
    forecast_qty: number;
    working_days: number;
    qty_per_day: number;
}

interface AvailableSap {
    sap_no: string;
    product_unit: string;
    type: string;
}

interface Props {
    forecasts: MonthlyForecast[];
    availableSapNumbers: AvailableSap[];
    selectedYear: number;
    selectedMonth: number;
    months: { [key: number]: string };
}

const props = defineProps<Props>();

const selectedYear = ref(props.selectedYear);
const selectedMonth = ref(props.selectedMonth);
const forecasts = ref<MonthlyForecast[]>(props.forecasts);
const savingAll = ref(false);
const deletingAll = ref(false);

const showAddModal = ref(false);
const searchQuery = ref('');
const showBulkWorkingDaysModal = ref(false);
const bulkWorkingDays = ref(20);
const showCopyModal = ref(false);
const copyFromYear = ref(props.selectedYear);
const copyFromMonth = ref(props.selectedMonth);
const copyingData = ref(false);

const newForecast = ref({
    sap_no: '',
    product_unit: '',
    type: '',
    forecast_qty: 0,
    working_days: 20
});

const filteredSapNumbers = computed(() => {
    if (!searchQuery.value) return props.availableSapNumbers;

    const query = searchQuery.value.toLowerCase();
    return props.availableSapNumbers.filter(item =>
        item.sap_no.toLowerCase().includes(query) ||
        item.product_unit.toLowerCase().includes(query) ||
        item.type.toLowerCase().includes(query)
    );
});

const totalForecasts = computed(() => forecasts.value.length);

const changeDate = () => {
    router.get('/forecast', {
        year: selectedYear.value,
        month: selectedMonth.value
    }, { preserveState: false });
};

const calculateQtyPerDay = (forecastQty: number, workingDays: number): number => {
    return workingDays > 0 ? Math.round((forecastQty / workingDays) * 100) / 100 : 0;
};

const handleForecastChange = (index: number, field: string, value: any) => {
    const forecast = forecasts.value[index];
    (forecast as any)[field] = value;

    if (field === 'forecast_qty' || field === 'working_days') {
        forecast.qty_per_day = calculateQtyPerDay(forecast.forecast_qty, forecast.working_days);
    }
};

const saveAllForecasts = async () => {
    if (forecasts.value.length === 0) {
        alert('⚠️ Tidak ada forecast untuk disimpan');
        return;
    }

    if (!confirm(`Simpan semua ${forecasts.value.length} forecast?`)) return;

    savingAll.value = true;

    try {
        let successCount = 0;

        for (const forecast of forecasts.value) {
            try {
                const response = await axios.post('/forecast/update', {
                    id: forecast.id,
                    sap_no: forecast.sap_no,
                    product_unit: forecast.product_unit,
                    part_name: forecast.part_name,
                    type: forecast.type,
                    year: selectedYear.value,
                    month: selectedMonth.value,
                    forecast_qty: forecast.forecast_qty,
                    working_days: forecast.working_days,
                });

                if (response.data.success) {
                    if (response.data.data?.id) forecast.id = response.data.data.id;
                    successCount++;
                }
            } catch (error) {
                console.error('Error saving forecast:', error);
            }
        }

        savingAll.value = false;
        alert(`✅ Berhasil menyimpan ${successCount} forecast!\n\n📊 Synced to Output Products & Control Stock via BOM`);
    } catch (error: any) {
        savingAll.value = false;
        alert('❌ Error: ' + error.message);
    }
};

const deleteAllForecasts = async () => {
    if (forecasts.value.length === 0) {
        alert('⚠️ Tidak ada forecast untuk dihapus');
        return;
    }

    if (!confirm(`⚠️ PERINGATAN!\n\nHapus SEMUA ${forecasts.value.length} forecast untuk ${props.months[selectedMonth.value]} ${selectedYear.value}?\n\nTindakan ini tidak dapat dibatalkan!`)) {
        return;
    }

    deletingAll.value = true;

    try {
        for (let i = forecasts.value.length - 1; i >= 0; i--) {
            const forecast = forecasts.value[i];

            if (forecast.id) {
                try {
                    await axios.delete(`/forecast/${forecast.id}`);
                } catch (error) {
                    console.error('Error deleting forecast:', error);
                }
            }
            forecasts.value.splice(i, 1);
        }

        deletingAll.value = false;
        alert(`✅ Semua forecast berhasil dihapus!`);
    } catch (error: any) {
        deletingAll.value = false;
        alert('❌ Error: ' + error.message);
    }
};

const applyBulkWorkingDays = () => {
    if (forecasts.value.length === 0) {
        alert('⚠️ Tidak ada forecast untuk diubah');
        return;
    }

    if (!confirm(`Ubah hari kerja untuk semua ${forecasts.value.length} forecast menjadi ${bulkWorkingDays.value} hari?`)) {
        return;
    }

    forecasts.value.forEach(forecast => {
        forecast.working_days = bulkWorkingDays.value;
        forecast.qty_per_day = calculateQtyPerDay(forecast.forecast_qty, forecast.working_days);
    });

    showBulkWorkingDaysModal.value = false;
    alert(`✅ Hari kerja diubah menjadi ${bulkWorkingDays.value} hari\n\n⚠️ Jangan lupa klik "Save All"!`);
};

const addForecast = async () => {
    if (!newForecast.value.sap_no) {
        alert('⚠️ Pilih SAP NO terlebih dahulu');
        return;
    }

    try {
        const response = await axios.post('/forecast/update', {
            id: null,
            sap_no: newForecast.value.sap_no,
            product_unit: newForecast.value.product_unit,
            type: newForecast.value.type,
            year: selectedYear.value,
            month: selectedMonth.value,
            forecast_qty: newForecast.value.forecast_qty,
            working_days: newForecast.value.working_days,
        });

        if (response.data.success) {
            forecasts.value.push({
                id: response.data.data.id,
                sap_no: newForecast.value.sap_no,
                product_unit: newForecast.value.product_unit,
                part_name: '',
                type: newForecast.value.type,
                year: selectedYear.value,
                month: selectedMonth.value,
                forecast_qty: newForecast.value.forecast_qty,
                working_days: newForecast.value.working_days,
                qty_per_day: response.data.data.qty_per_day
            });

            closeAddModal();
            alert(`✅ Forecast berhasil ditambahkan!`);
        }
    } catch (error: any) {
        alert('❌ Error: ' + (error.response?.data?.message || error.message));
    }
};

const deleteForecast = async (index: number) => {
    const forecast = forecasts.value[index];

    if (!forecast.id) {
        forecasts.value.splice(index, 1);
        return;
    }

    if (!confirm(`Hapus forecast untuk ${forecast.sap_no}?`)) return;

    try {
        const response = await axios.delete(`/forecast/${forecast.id}`);
        if (response.data.success) {
            forecasts.value.splice(index, 1);
            alert('✅ Forecast berhasil dihapus');
        }
    } catch (error: any) {
        alert('❌ Error: ' + (error.response?.data?.message || error.message));
    }
};

const selectSapNo = (sapNo: string) => {
    const selected = props.availableSapNumbers.find(s => s.sap_no === sapNo);
    if (selected) {
        newForecast.value.sap_no = selected.sap_no;
        newForecast.value.product_unit = selected.product_unit;
        newForecast.value.type = selected.type;
    }
};

const closeAddModal = () => {
    showAddModal.value = false;
    searchQuery.value = '';
    newForecast.value = {
        sap_no: '',
        product_unit: '',
        type: '',
        forecast_qty: 0,
        working_days: 20
    };
};

const copyFromOtherMonth = async () => {
    if (copyFromYear.value === selectedYear.value && copyFromMonth.value === selectedMonth.value) {
        alert('⚠️ Tidak dapat menyalin dari bulan yang sama!');
        return;
    }

    if (!confirm(`Salin semua forecast dari ${props.months[copyFromMonth.value]} ${copyFromYear.value} ke ${props.months[selectedMonth.value]} ${selectedYear.value}?\n\nData yang ada akan ditimpa!`)) {
        return;
    }

    copyingData.value = true;

    try {
        const response = await axios.post('/forecast/copy', {
            from_year: copyFromYear.value,
            from_month: copyFromMonth.value,
            to_year: selectedYear.value,
            to_month: selectedMonth.value,
        });

        if (response.data.success) {
            showCopyModal.value = false;
            alert(`✅ Berhasil menyalin ${response.data.copied} forecast!\n\n📊 Synced to Output Products & Control Stock via BOM`);

            changeDate();
        }
    } catch (error: any) {
        alert('❌ Error: ' + (error.response?.data?.message || error.message));
    } finally {
        copyingData.value = false;
    }
};
</script>

<template>
    <Head title="Forecast Management" />
    <AppLayout :breadcrumbs="[{ title: 'Forecast Management', href: '/forecast' }]">
        <div class="p-4 space-y-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <TrendingUp class="w-7 h-7 text-blue-600" />
                            Forecast Management
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Total: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ totalForecasts }}</span> forecast
                        </p>
                    </div>
                    <div class="flex gap-2 flex-wrap justify-end">
                        <button
                            @click="showCopyModal = true"
                            class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition"
                        >
                            <Copy class="w-4 h-4" />
                            Copy dari Bulan Lain
                        </button>
                        <button
                            @click="showBulkWorkingDaysModal = true"
                            :disabled="forecasts.length === 0"
                            class="flex items-center gap-2 px-4 py-2 bg-purple-600 text-white text-sm rounded-lg hover:bg-purple-700 transition disabled:opacity-50"
                        >
                            <Calendar class="w-4 h-4" />
                            Set Hari Kerja
                        </button>
                        <button
                            @click="saveAllForecasts"
                            :disabled="savingAll || forecasts.length === 0"
                            class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition disabled:opacity-50"
                        >
                            <Loader v-if="savingAll" class="w-4 h-4 animate-spin" />
                            <Save v-else class="w-4 h-4" />
                            Save All
                        </button>
                        <button
                            @click="deleteAllForecasts"
                            :disabled="deletingAll || forecasts.length === 0"
                            class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition disabled:opacity-50"
                        >
                            <Loader v-if="deletingAll" class="w-4 h-4 animate-spin" />
                            <Trash2 v-else class="w-4 h-4" />
                            Delete All
                        </button>
                        <button
                            @click="showAddModal = true"
                            class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition"
                        >
                            <Plus class="w-4 h-4" />
                            Add Forecast
                        </button>
                    </div>
                </div>

                <div class="flex gap-4 items-center">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Year</label>
                        <select
                            v-model="selectedYear"
                            @change="changeDate"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="2024">2024</option>
                            <option value="2025">2025</option>
                            <option value="2026">2026</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Month</label>
                        <select
                            v-model="selectedMonth"
                            @change="changeDate"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="(name, num) in months" :key="num" :value="num">{{ name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gradient-to-r from-blue-50 to-blue-100 dark:from-gray-700 dark:to-gray-600">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Type</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">SAP NO</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Product Unit</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Forecast QTY</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Hari Kerja</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300 bg-green-50 dark:bg-green-900/20">QTY/Day</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="(forecast, index) in forecasts" :key="forecast.id || index" class="hover:bg-blue-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-4 py-3">
                                    <input
                                        :value="forecast.type"
                                        @input="handleForecastChange(index, 'type', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        class="w-20 px-2 py-1.5 text-sm border rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-mono text-sm font-semibold text-blue-700 dark:text-blue-400">{{ forecast.sap_no }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-700 dark:text-gray-300">{{ forecast.product_unit }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input
                                        :value="forecast.forecast_qty"
                                        @input="handleForecastChange(index, 'forecast_qty', parseInt(($event.target as HTMLInputElement).value) || 0)"
                                        type="number"
                                        min="0"
                                        class="w-28 px-2 py-1.5 text-sm text-center border rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-semibold focus:ring-2 focus:ring-blue-500"
                                    />
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input
                                        :value="forecast.working_days"
                                        @input="handleForecastChange(index, 'working_days', parseInt(($event.target as HTMLInputElement).value) || 1)"
                                        type="number"
                                        min="1"
                                        max="31"
                                        class="w-20 px-2 py-1.5 text-sm text-center border rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-semibold focus:ring-2 focus:ring-blue-500"
                                    />
                                </td>
                                <td class="px-4 py-3 bg-green-50 dark:bg-green-900/10 text-center">
                                    <div class="inline-flex flex-col">
                                        <span class="text-lg font-bold text-green-700 dark:text-green-400">{{ forecast.qty_per_day }}</span>
                                        <span class="text-xs text-green-600 dark:text-green-500">unit/day</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button
                                        @click="deleteForecast(index)"
                                        class="p-2 text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-lg transition"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="forecasts.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <TrendingUp class="w-16 h-16 text-gray-400" />
                                        <span class="text-base text-gray-500 dark:text-gray-400">Belum ada forecast</span>
                                        <button @click="showAddModal = true" class="mt-2 flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                            <Plus class="w-4 h-4" />
                                            Tambah Forecast
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Modal -->
        <div v-if="showAddModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="closeAddModal">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-white dark:bg-gray-800 border-b p-6 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Add New Forecast</h3>
                    <button @click="closeAddModal" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                        <X class="w-5 h-5 text-gray-500" />
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Cari SAP NO <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Ketik untuk mencari..."
                                class="w-full pl-10 pr-4 py-2.5 border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    </div>

                    <div class="border rounded-lg max-h-60 overflow-y-auto">
                        <div
                            v-for="item in filteredSapNumbers"
                            :key="item.sap_no"
                            @click="selectSapNo(item.sap_no)"
                            class="p-3 hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer border-b last:border-b-0"
                            :class="{ 'bg-blue-100 dark:bg-blue-900/30': newForecast.sap_no === item.sap_no }"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-mono text-sm font-semibold text-blue-700 dark:text-blue-400">{{ item.sap_no }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ item.product_unit }}</div>
                                </div>
                                <div class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs font-medium">{{ item.type }}</div>
                            </div>
                        </div>
                        <div v-if="filteredSapNumbers.length === 0" class="p-6 text-center text-gray-500">
                            <p class="text-sm">Tidak ada hasil</p>
                        </div>
                    </div>

                    <div v-if="newForecast.sap_no" class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <div class="text-sm font-medium mb-1">Dipilih:</div>
                        <div class="font-mono text-base font-bold text-blue-700 dark:text-blue-400">{{ newForecast.sap_no }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ newForecast.product_unit }} • {{ newForecast.type }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Forecast QTY <span class="text-red-500">*</span></label>
                            <input v-model.number="newForecast.forecast_qty" type="number" min="0" class="w-full px-3 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Hari Kerja <span class="text-red-500">*</span></label>
                            <input v-model.number="newForecast.working_days" type="number" min="1" max="31" class="w-full px-3 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>
                </div>

                <div class="sticky bottom-0 bg-gray-50 dark:bg-gray-900 border-t p-6 flex justify-end gap-3">
                    <button @click="closeAddModal" class="px-5 py-2.5 border rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">Cancel</button>
                    <button @click="addForecast" :disabled="!newForecast.sap_no" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">Add Forecast</button>
                </div>
            </div>
        </div>

        <!-- Bulk Working Days Modal -->
        <div v-if="showBulkWorkingDaysModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="showBulkWorkingDaysModal = false">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md">
                <div class="p-6 border-b">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Calendar class="w-6 h-6 text-purple-600" />
                        Set Hari Kerja untuk Semua
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Mengubah {{ totalForecasts }} forecast</p>
                </div>

                <div class="p-6">
                    <label class="block text-sm font-medium mb-2">Jumlah Hari Kerja</label>
                    <input
                        v-model.number="bulkWorkingDays"
                        type="number"
                        min="1"
                        max="31"
                        class="w-full px-4 py-3 text-lg font-bold text-center border-2 rounded-lg focus:ring-2 focus:ring-purple-500"
                    />
                    <p class="text-xs text-gray-500 mt-2">Nilai ini akan diterapkan ke semua forecast bulan ini</p>
                </div>

                <div class="bg-gray-50 dark:bg-gray-900 p-6 flex justify-end gap-3">
                    <button @click="showBulkWorkingDaysModal = false" class="px-5 py-2.5 border rounded-lg hover:bg-gray-100">Cancel</button>
                    <button @click="applyBulkWorkingDays" class="px-5 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Apply</button>
                </div>
            </div>
        </div>

        <div v-if="showCopyModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="showCopyModal = false">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md">
                <div class="p-6 border-b">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Copy class="w-6 h-6 text-indigo-600" />
                        Copy Data Forecast
                    </h3>
                </div>

                <div class="p-6 space-y-4">
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border-l-4 border-blue-500">
                        <div class="text-sm font-medium text-blue-900 dark:text-blue-300">Target:</div>
                        <div class="text-lg font-bold text-blue-700 dark:text-blue-400 mt-1">
                            {{ months[selectedMonth] }} {{ selectedYear }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Salin dari Bulan</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Year</label>
                                <select
                                    v-model="copyFromYear"
                                    class="w-full px-3 py-2.5 border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="2024">2024</option>
                                    <option value="2025">2025</option>
                                    <option value="2026">2026</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Month</label>
                                <select
                                    v-model="copyFromMonth"
                                    class="w-full px-3 py-2.5 border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option v-for="(name, num) in months" :key="num" :value="num">{{ name }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-900 p-6 flex justify-end gap-3">
                    <button
                        @click="showCopyModal = false"
                        :disabled="copyingData"
                        class="px-5 py-2.5 border rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="copyFromOtherMonth"
                        :disabled="copyingData"
                        class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"
                    >
                        <Loader v-if="copyingData" class="w-4 h-4 animate-spin" />
                        <Copy v-else class="w-4 h-4" />
                        {{ copyingData ? 'Copying...' : 'Copy Data' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    opacity: 1;
}
</style>
