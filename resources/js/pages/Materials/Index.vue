<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Search, Plus, Database, Edit2, Trash2, X, Package, Upload, Download, FileSpreadsheet, ChevronLeft, ChevronRight } from 'lucide-vue-next';

interface Material {
    id: number;
    material_id: string;
    nama_material: string;
    material_type: string;
    satuan: string;
    part_materials_count?: number;
    created_at: string;
}

interface Props {
    materials: {
        data: Material[];
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
const showModal = ref(false);
const showImportModal = ref(false);
const isEdit = ref(false);
const editId = ref<number | null>(null);
const csvFile = ref<File | null>(null);
const parsedData = ref<any[]>([]);
const isProcessing = ref(false);
const parseError = ref('');
const hasHeader = ref(true);

const form = useForm({
    material_id: '',
    nama_material: '',
    material_type: '',
    satuan: '',
});

const search = () => {
    router.get('/materials', {
        search: searchQuery.value
    }, { preserveState: true, preserveScroll: true });
};

const openCreateModal = () => {
    isEdit.value = false;
    editId.value = null;
    form.reset();
    showModal.value = true;
};

const openEditModal = (material: Material) => {
    isEdit.value = true;
    editId.value = material.id;
    form.material_id = material.material_id;
    form.nama_material = material.nama_material;
    form.material_type = material.material_type;
    form.satuan = material.satuan;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    isEdit.value = false;
    editId.value = null;
};

const submit = () => {
    if (isEdit.value && editId.value) {
        form.put(`/materials/${editId.value}`, {
            onSuccess: () => closeModal()
        });
    } else {
        form.post('/materials', {
            onSuccess: () => closeModal()
        });
    }
};

const deleteMaterial = (id: number) => {
    if (confirm('Yakin ingin menghapus material ini?')) {
        router.delete(`/materials/${id}`);
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const goToPage = (page: number) => {
    router.get(`/materials?page=${page}`, {
        search: searchQuery.value
    }, { preserveState: true, preserveScroll: true });
};

const getPaginationRange = () => {
    const currentPage = props.materials.current_page;
    const lastPage = props.materials.last_page;
    const delta = 2;
    const range: (number | string)[] = [];

    for (let i = Math.max(2, currentPage - delta); i <= Math.min(lastPage - 1, currentPage + delta); i++) {
        range.push(i);
    }

    if (currentPage - delta > 2) {
        range.unshift('...');
    }
    if (currentPage + delta < lastPage - 1) {
        range.push('...');
    }

    range.unshift(1);
    if (lastPage > 1) {
        range.push(lastPage);
    }

    return range;
};

const openImportModal = () => {
    showImportModal.value = true;
    csvFile.value = null;
    parsedData.value = [];
    parseError.value = '';
    hasHeader.value = true;
};

const closeImportModal = () => {
    showImportModal.value = false;
    csvFile.value = null;
    parsedData.value = [];
    parseError.value = '';
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        csvFile.value = target.files[0];
        parseError.value = '';
        parseCSV(target.files[0]);
    }
};

const parseCSVToRecords = (text: string, delimiter: string): string[][] => {
    const records: string[][] = [];
    let currentRecord: string[] = [];
    let currentField = '';
    let inQuotes = false;

    for (let i = 0; i < text.length; i++) {
        const char = text[i];
        const nextChar = text[i + 1];

        if (char === '"') {
            if (inQuotes && nextChar === '"') {
                currentField += '"';
                i++;
            } else {
                inQuotes = !inQuotes;
            }
        } else if (char === delimiter && !inQuotes) {
            currentRecord.push(currentField.trim());
            currentField = '';
        } else if ((char === '\n' || (char === '\r' && nextChar === '\n')) && !inQuotes) {
            currentRecord.push(currentField.trim());

            if (currentRecord.some(field => field.length > 0)) {
                records.push(currentRecord);
            }

            currentRecord = [];
            currentField = '';

            if (char === '\r' && nextChar === '\n') {
                i++;
            }
        } else if (char === '\r' && !inQuotes) {
            currentRecord.push(currentField.trim());

            if (currentRecord.some(field => field.length > 0)) {
                records.push(currentRecord);
            }

            currentRecord = [];
            currentField = '';
        } else {
            currentField += char;
        }
    }

    if (currentField.length > 0 || currentRecord.length > 0) {
        currentRecord.push(currentField.trim());
        if (currentRecord.some(field => field.length > 0)) {
            records.push(currentRecord);
        }
    }

    return records;
};

const detectDelimiter = (text: string): string => {
    const firstLine = text.split('\n')[0];
    const delimiters = [',', ';', '\t', '|'];

    for (const delimiter of delimiters) {
        const count = (firstLine.match(new RegExp('\\' + delimiter, 'g')) || []).length;
        if (count >= 3) {
            return delimiter;
        }
    }

    return ',';
};

const parseCSV = (file: File) => {
    const reader = new FileReader();

    reader.onload = (e) => {
        try {
            let text = e.target?.result as string;

            if (!text || text.trim().length === 0) {
                parseError.value = 'File kosong';
                return;
            }

            text = text.replace(/^\uFEFF/, '');

            const delimiter = detectDelimiter(text);

            const records = parseCSVToRecords(text, delimiter);

            if (records.length < 1) {
                parseError.value = 'Tidak ada data yang dapat dibaca';
                return;
            }

            let startIndex = 0;
            const columnIndices: any = {};

            if (hasHeader.value && records.length > 1) {
                const firstRecord = records[0];
                const headers = firstRecord.map(h => h.trim().toLowerCase());

                const headerMap: any = {
                    'id material': 'material_id',
                    'material_id': 'material_id',
                    'idmaterial': 'material_id',
                    'id': 'material_id',
                    'nama material': 'nama_material',
                    'nama_material': 'nama_material',
                    'namamaterial': 'nama_material',
                    'nama': 'nama_material',
                    'name': 'nama_material',
                    'type': 'material_type',
                    'material_type': 'material_type',
                    'materialtype': 'material_type',
                    'tipe': 'material_type',
                    'satuan': 'satuan',
                    'unit': 'satuan',
                    'uom': 'satuan'
                };

                headers.forEach((header, index) => {
                    const mappedKey = headerMap[header];
                    if (mappedKey) {
                        columnIndices[mappedKey] = index;
                    }
                });

                const requiredColumns = ['material_id', 'nama_material', 'material_type', 'satuan'];
                const missingColumns = requiredColumns.filter(col => !(col in columnIndices));

                if (missingColumns.length === 0) {
                    startIndex = 1;
                } else {
                    hasHeader.value = false;
                }
            }

            const data = [];
            const errors = [];

            for (let i = startIndex; i < records.length; i++) {
                const record = records[i];

                if (record.length === 0 || record.every(f => f.length === 0)) {
                    continue;
                }

                try {
                    let row: any;

                    if (hasHeader.value && Object.keys(columnIndices).length > 0) {
                        row = {
                            material_id: record[columnIndices.material_id]?.trim() || '',
                            nama_material: record[columnIndices.nama_material]?.trim() || '',
                            material_type: record[columnIndices.material_type]?.trim() || '',
                            satuan: record[columnIndices.satuan]?.trim() || ''
                        };
                    } else {
                        row = {
                            material_id: record[0]?.trim() || '',
                            nama_material: record[1]?.trim() || '',
                            material_type: record[2]?.trim() || '',
                            satuan: record[3]?.trim() || ''
                        };
                    }

                    if (!row.material_id || row.material_id.length === 0) {
                        errors.push(`Baris ${i + 1}: Material ID kosong - SKIP`);
                        continue;
                    }

                    if (!row.nama_material) row.nama_material = 'N/A';
                    if (!row.material_type) row.material_type = 'UNKNOWN';
                    if (!row.satuan) row.satuan = 'PCS';

                    data.push(row);

                } catch (error: any) {
                    errors.push(`Baris ${i + 1}: Error - ${error.message}`);
                }
            }

            if (data.length === 0) {
                parseError.value = 'Tidak ada data valid:\n' + errors.slice(0, 10).join('\n');
                return;
            }

            parsedData.value = data;

        } catch (error: any) {
            parseError.value = `Error: ${error.message}`;
        }
    };

    reader.readAsText(file, 'UTF-8');
};

const submitImport = () => {
    if (parsedData.value.length === 0) {
        alert('Tidak ada data untuk diimport');
        return;
    }

    if (!confirm(`Anda akan mengimport ${parsedData.value.length} material. Lanjutkan?`)) {
        return;
    }

    isProcessing.value = true;

    router.post('/materials/import', {
        materials: parsedData.value
    }, {
        preserveState: false,
        preserveScroll: false,
    });
};

const downloadTemplate = () => {
    const headers = ['ID MATERIAL', 'NAMA MATERIAL', 'TYPE', 'SATUAN'];
    const sampleData = [
        ['02C31100260280RAW', 'SA1D80 (SACD80) 1.0 X 260 X 280 M PLATE', 'PLATE', 'SHE'],
        ['02C31010COIL1050', 'HSA1D 80 1.0 X 1050 X COIL', 'M.COIL', 'KGM'],
        ['02C31100040121RAW', 'HSA1D80 (SACD80) 1.0 X 40 X 1219 MM PLATE', 'PLATE', 'SHE']
    ];

    const csvContent = [
        headers.join(','),
        ...sampleData.map(row => row.map(cell => `"${cell}"`).join(','))
    ].join('\n');

    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'template_material.csv';
    link.click();
};
</script>

<template>
    <Head title="Master Material" />
    <AppLayout :breadcrumbs="[
        { title: 'Master Material', href: '/materials' }
    ]">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Database class="w-6 h-6 text-blue-600" />
                    Master Material
                </h1>
                <div class="flex gap-2">
                    <button
                        @click="openImportModal"
                        class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                    >
                        <Upload class="w-4 h-4" />
                        Import CSV
                    </button>
                    <button
                        @click="openCreateModal"
                        class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                        <Plus class="w-4 h-4" />
                        Tambah Material
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">Total Material</div>
                <div class="text-2xl font-bold">{{ materials.total }}</div>
            </div>

            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                <div class="flex gap-2">
                    <input
                        v-model="searchQuery"
                        @keyup.enter="search"
                        type="text"
                        placeholder="Cari material ID atau nama..."
                        class="flex-1 rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                    />
                    <button @click="search" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center gap-2">
                        <Search class="w-4 h-4" />
                        Cari
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium">Material ID</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Nama Material</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Tipe</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Satuan</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Jumlah Part</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Dibuat</th>
                                <th class="px-4 py-3 text-center text-sm font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr
                                v-for="material in materials.data"
                                :key="material.id"
                                class="hover:bg-sidebar-accent"
                            >
                                <td class="px-4 py-3 text-sm font-medium">{{ material.material_id }}</td>
                                <td class="px-4 py-3 text-sm">{{ material.nama_material }}</td>
                                <td class="px-4 py-3 text-sm">{{ material.material_type }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded text-xs font-medium">
                                        {{ material.satuan }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ material.part_materials_count || 0 }} part</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(material.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            @click="openEditModal(material)"
                                            class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded"
                                        >
                                            <Edit2 class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deleteMaterial(material.id)"
                                            class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="materials.data.length === 0" class="text-center py-12 text-gray-500">
                    <Package class="w-16 h-16 mx-auto mb-4 opacity-50" />
                    <p>Tidak ada material</p>
                </div>
            </div>

            <div v-if="materials.last_page > 1" class="flex items-center justify-between bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Halaman {{ materials.current_page }} dari {{ materials.last_page }}
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="goToPage(materials.current_page - 1)"
                        :disabled="materials.current_page === 1"
                        class="p-2 rounded-md border border-sidebar-border hover:bg-sidebar-accent disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>

                    <template v-for="(page, index) in getPaginationRange()" :key="index">
                        <button
                            v-if="typeof page === 'number'"
                            @click="goToPage(page)"
                            :class="[
                                'min-w-[40px] h-10 px-3 rounded-md text-sm font-medium transition-colors',
                                page === materials.current_page
                                    ? 'bg-blue-600 text-white'
                                    : 'border border-sidebar-border hover:bg-sidebar-accent'
                            ]"
                        >
                            {{ page }}
                        </button>
                        <span v-else class="px-2 text-gray-500">{{ page }}</span>
                    </template>

                    <button
                        @click="goToPage(materials.current_page + 1)"
                        :disabled="materials.current_page === materials.last_page"
                        class="p-2 rounded-md border border-sidebar-border hover:bg-sidebar-accent disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>

                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Total {{ materials.total }} data
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-2xl w-full">
                <div class="flex items-center justify-between p-6 border-b border-sidebar-border">
                    <h2 class="text-xl font-bold">{{ isEdit ? 'Edit Material' : 'Tambah Material' }}</h2>
                    <button @click="closeModal" class="p-1 hover:bg-sidebar-accent rounded">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Material ID</label>
                        <input
                            v-model="form.material_id"
                            type="text"
                            placeholder="Contoh: MAT-001"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar"
                            required
                        />
                        <div v-if="form.errors.material_id" class="mt-1 text-sm text-red-600">
                            {{ form.errors.material_id }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Nama Material</label>
                        <input
                            v-model="form.nama_material"
                            type="text"
                            placeholder="Contoh: Besi Plat"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar"
                            required
                        />
                        <div v-if="form.errors.nama_material" class="mt-1 text-sm text-red-600">
                            {{ form.errors.nama_material }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Tipe Material</label>
                        <input
                            v-model="form.material_type"
                            type="text"
                            placeholder="Contoh: Raw Material"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar"
                            required
                        />
                        <div v-if="form.errors.material_type" class="mt-1 text-sm text-red-600">
                            {{ form.errors.material_type }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Satuan</label>
                        <input
                            v-model="form.satuan"
                            type="text"
                            placeholder="Contoh: Kg, Pcs, Unit"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar"
                            required
                        />
                        <div v-if="form.errors.satuan" class="mt-1 text-sm text-red-600">
                            {{ form.errors.satuan }}
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4 border-t border-sidebar-border">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex-1 px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Update' : 'Simpan') }}
                        </button>
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-6 py-3 bg-gray-600 text-white rounded-md hover:bg-gray-700"
                        >
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showImportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-4xl w-full max-h-[90vh] overflow-hidden">
                <div class="flex items-center justify-between p-6 border-b border-sidebar-border">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <FileSpreadsheet class="w-6 h-6 text-green-600" />
                        Import Material dari CSV
                    </h2>
                    <button @click="closeImportModal" class="p-1 hover:bg-sidebar-accent rounded">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto max-h-[calc(90vh-180px)]">
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">📋 Panduan Import:</h3>
                        <ul class="text-sm text-blue-800 dark:text-blue-200 space-y-1 list-disc list-inside">
                            <li>Format file: CSV (comma, semicolon, atau tab-separated)</li>
                            <li>Header kolom: ID MATERIAL, NAMA MATERIAL, TYPE, SATUAN</li>
                            <li>Encoding: UTF-8 (recommended)</li>
                            <li>Field kosong akan diisi default (N/A, UNKNOWN, PCS)</li>
                            <li>Jika material ID sudah ada, data akan diupdate</li>
                        </ul>
                        <button
                            @click="downloadTemplate"
                            class="mt-3 flex items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"
                        >
                            <Download class="w-4 h-4" />
                            Download Template CSV
                        </button>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Upload File CSV</label>
                        <input
                            type="file"
                            accept=".csv,.txt"
                            @change="handleFileChange"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar"
                        />
                        <p class="text-xs text-gray-500 mt-1">Supported: CSV, TXT</p>
                    </div>

                    <div class="flex items-center gap-2 p-3 bg-gray-50 dark:bg-sidebar-accent rounded-lg">
                        <input
                            type="checkbox"
                            id="hasHeader"
                            v-model="hasHeader"
                            @change="csvFile ? parseCSV(csvFile) : null"
                            class="w-4 h-4 rounded border-gray-300"
                        />
                        <label for="hasHeader" class="text-sm font-medium cursor-pointer">
                            File memiliki header (baris pertama adalah nama kolom)
                        </label>
                    </div>
                    <div v-if="!hasHeader" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            ℹ️ Mode tanpa header: Kolom harus berurutan <strong>ID MATERIAL, NAMA MATERIAL, TYPE, SATUAN</strong>
                        </p>
                    </div>

                    <div v-if="parseError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <h3 class="font-semibold text-red-900 dark:text-red-100 mb-2">❌ Error:</h3>
                        <pre class="text-sm text-red-800 dark:text-red-200 whitespace-pre-wrap">{{ parseError }}</pre>
                    </div>

                    <div v-if="parsedData.length > 0" class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold">✅ Preview Data ({{ parsedData.length }} baris)</h3>
                        </div>
                        <div class="border border-sidebar-border rounded-lg overflow-hidden">
                            <div class="overflow-x-auto max-h-96">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-sidebar-accent sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium">#</th>
                                            <th class="px-3 py-2 text-left font-medium">Material ID</th>
                                            <th class="px-3 py-2 text-left font-medium">Nama Material</th>
                                            <th class="px-3 py-2 text-left font-medium">Tipe</th>
                                            <th class="px-3 py-2 text-left font-medium">Satuan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-sidebar-border">
                                        <tr v-for="(item, index) in parsedData.slice(0, 100)" :key="index">
                                            <td class="px-3 py-2">{{ index + 1 }}</td>
                                            <td class="px-3 py-2 font-medium">{{ item.material_id }}</td>
                                            <td class="px-3 py-2">{{ item.nama_material }}</td>
                                            <td class="px-3 py-2">{{ item.material_type }}</td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded text-xs">
                                                    {{ item.satuan }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="parsedData.length > 100" class="px-3 py-2 bg-gray-50 dark:bg-sidebar-accent text-sm text-gray-600 dark:text-gray-400 text-center">
                                ... dan {{ parsedData.length - 100 }} baris lainnya
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 p-6 border-t border-sidebar-border">
                    <button
                        @click="submitImport"
                        :disabled="parsedData.length === 0 || isProcessing"
                        class="flex-1 px-6 py-3 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                        <Upload class="w-4 h-4" />
                        {{ isProcessing ? 'Mengimport...' : `Import ${parsedData.length} Material` }}
                    </button>
                    <button
                        @click="closeImportModal"
                        :disabled="isProcessing"
                        class="px-6 py-3 bg-gray-600 text-white rounded-md hover:bg-gray-700 disabled:bg-gray-400"
                    >
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
