<!-- eslint-disable @typescript-eslint/no-unused-vars -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Edit, Trash2, Search, Image as ImageIcon, X, Upload } from 'lucide-vue-next';

interface Part {
    id: number;
    part_code: string;
    id_sap: string;
    type_line: string;
    part_name: string;
    product_image: string;
    product_images: string[];
    description: string;
    supplier: {
        id: number;
        supplier_name: string;
    };
}

interface Props {
    parts: {
        data: Part[];
        current_page: number;
        last_page: number;
    };
    suppliers: Array<{ id: number; supplier_name: string; supplier_code: string }>;
}

const props = defineProps<Props>();

const showModal = ref(false);
const showImportModal = ref(false);
const editMode = ref(false);
const searchQuery = ref('');
const filterSupplier = ref(0);
const filterTypeLine = ref('');
const imagePreviews = ref<string[]>([]);
const imagePreviewsNew = ref<string[]>([]);
const showImageModal = ref(false);
const selectedImages = ref<string[]>([]);
const currentImageIndex = ref(0);
const importData = ref<any[]>([]);
const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    _method: 'PUT',
    id: 0,
    supplier_id: 0,
    part_code: '',
    id_sap: '',
    type_line: '',
    part_name: '',
    product_images: [] as File[],
    existing_images: [] as string[],
    description: '',
});

const openModal = (part?: Part) => {
    if (part) {
        editMode.value = true;
        form.id = part.id;
        form.supplier_id = part.supplier.id;
        form.part_code = part.part_code;
        form.id_sap = part.id_sap || '';
        form.type_line = part.type_line || '';
        form.part_name = part.part_name;
        form.description = part.description;

        form.product_images = [];
        form.existing_images = [];

        imagePreviews.value = [];
        imagePreviewsNew.value = [];

        if (part.product_images && part.product_images.length > 0) {
            part.product_images.forEach(img => {
                imagePreviews.value.push(`/storage/${img}`);
                form.existing_images.push(img);
            });
        }
    } else {
        editMode.value = false;
        form.reset();
        imagePreviews.value = [];
        imagePreviewsNew.value = [];
    }
    showModal.value = true;
};

const clearAllImages = () => {
    if (confirm('Hapus semua gambar yang ada?')) {
        imagePreviews.value = [];
        imagePreviewsNew.value = [];
        form.product_images = [];
        form.existing_images = [];
    }
};

const handleImagesChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const files = target.files;

    if (files) {
        imagePreviewsNew.value = [];
        form.product_images = [];

        Array.from(files).forEach(file => {
            form.product_images.push(file);
            imagePreviewsNew.value.push(window.URL.createObjectURL(file));
        });
    }
};

const removeImage = (index: number) => {
    const totalExisting = form.existing_images.length;

    if (index < totalExisting) {
        const removedPath = form.existing_images[index];
        form.existing_images = form.existing_images.filter(img => img !== removedPath);
        imagePreviews.value.splice(index, 1);
    } else {
        const newImageIndex = index - totalExisting;
        form.product_images.splice(newImageIndex, 1);
        imagePreviewsNew.value.splice(newImageIndex, 1);
        imagePreviews.value.splice(index, 1);
    }
};

const submit = () => {
    if (editMode.value) {
        form.transform((data) => ({
            ...data,
            _method: 'PUT'
        })).post(`/parts/${form.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                imagePreviews.value = [];
                imagePreviewsNew.value = [];
            },
        });
    } else {
        form.post('/parts', {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                imagePreviews.value = [];
                imagePreviewsNew.value = [];
            },
        });
    }
};

const deletePart = (id: number) => {
    if (confirm('Yakin hapus part ini?')) {
        router.delete(`/parts/${id}`, { preserveScroll: true });
    }
};

const search = () => {
    router.get('/parts', {
        search: searchQuery.value,
        supplier: filterSupplier.value > 0 ? filterSupplier.value : undefined,
        type_line: filterTypeLine.value || undefined // ✅ TAMBAH INI
    }, { preserveState: true });
};

const filterBySupplier = () => {
    router.get('/parts', {
        search: searchQuery.value || undefined,
        supplier: filterSupplier.value > 0 ? filterSupplier.value : undefined,
        type_line: filterTypeLine.value || undefined // ✅ TAMBAH INI
    }, { preserveState: true });
};

const filterByTypeLine = () => {
    router.get('/parts', {
        search: searchQuery.value || undefined,
        supplier: filterSupplier.value > 0 ? filterSupplier.value : undefined,
        type_line: filterTypeLine.value || undefined
    }, { preserveState: true });
};

const resetFilters = () => {
    searchQuery.value = '';
    filterSupplier.value = 0;
    filterTypeLine.value = '';
    router.get('/parts', {}, { preserveState: true });
};

const viewImages = (part: Part) => {
    selectedImages.value = [];
    if (part.product_images && part.product_images.length > 0) {
        part.product_images.forEach(img => {
            selectedImages.value.push(`/storage/${img}`);
        });
    }
    currentImageIndex.value = 0;
    showImageModal.value = true;
};

const nextImage = () => {
    if (currentImageIndex.value < selectedImages.value.length - 1) {
        currentImageIndex.value++;
    }
};

const prevImage = () => {
    if (currentImageIndex.value > 0) {
        currentImageIndex.value--;
    }
};

const openImportModal = () => {
    showImportModal.value = true;
    importData.value = [];
};

const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        try {
            const text = e.target?.result as string;
            const lines = text.split('\n').map(line => line.trim()).filter(line => line.length > 0);

            const parts: any[] = [];
            const partSet = new Set<string>();

            // Deteksi delimiter
            let delimiter = ',';
            if (lines[0]?.includes(';')) delimiter = ';';
            else if (lines[0]?.includes('\t')) delimiter = '\t';

            let headerIndex = -1;
            let idSapColumnIndex = -1;
            let nameColumnIndex = -1;
            let typeLineColumnIndex = -1;
            let vendorColumnIndex = -1;

            // ✅ Cari header dengan kolom baru
            for (let i = 0; i < Math.min(20, lines.length); i++) {
                const columns = lines[i].split(delimiter).map(col => col.trim().replace(/^"|"$/g, ''));

                const idSapIdx = columns.findIndex(col => {
                    const upper = col.toUpperCase();
                    return upper === 'ID SAP' || upper === 'IDSAP' || upper.includes('ID SAP');
                });

                const nameIdx = columns.findIndex(col => {
                    const upper = col.toUpperCase();
                    return (upper.includes('NAME') && upper.includes('MATERIAL')) ||
                           upper === 'MATERIAL NAME' ||
                           upper === 'PART NAME' ||
                           upper.includes('MATERIAL');
                });

                const typeLineIdx = columns.findIndex(col => {
                    const upper = col.toUpperCase();
                    return upper === 'TYPE LINE' || upper === 'TYPELINE' || upper.includes('TYPE LINE');
                });

                const vendorIdx = columns.findIndex(col => {
                    const upper = col.toUpperCase();
                    return upper.includes('VENDOR') || upper.includes('SUPPLIER');
                });

                if (nameIdx !== -1) {
                    headerIndex = i;
                    idSapColumnIndex = idSapIdx;
                    nameColumnIndex = nameIdx;
                    typeLineColumnIndex = typeLineIdx;
                    vendorColumnIndex = vendorIdx;
                    break;
                }
            }

            if (nameColumnIndex === -1) {
                alert('Kolom NAME MATERIAL tidak ditemukan. Pastikan ada header "NAME MATERIAL" di file Excel.');
                return;
            }

            // Parse data rows
            let processedRows = 0;
            let skippedRows = 0;

            for (let i = headerIndex + 1; i < lines.length; i++) {
                const line = lines[i].trim();
                if (!line) {
                    skippedRows++;
                    continue;
                }

                const columns = line.split(delimiter).map(col =>
                    col.trim().replace(/^"|"$/g, '').trim()
                );

                const idSap = idSapColumnIndex !== -1 ? (columns[idSapColumnIndex] || '') : '';
                const partName = columns[nameColumnIndex] || '';
                const typeLine = typeLineColumnIndex !== -1 ? (columns[typeLineColumnIndex] || '') : '';
                const vendor = columns[vendorColumnIndex] || '';

                // Skip jika nama part kosong
                if (!partName || partName === '') {
                    skippedRows++;
                    continue;
                }

                // Skip duplikat
                if (partSet.has(partName.toUpperCase())) {
                    skippedRows++;
                    continue;
                }

                partSet.add(partName.toUpperCase());

                // ✅ Generate part code
                let partCode = '';

                // Strategi 1: Gunakan ID SAP jika ada
                if (idSap && idSap.length >= 5) {
                    partCode = idSap
                        .toUpperCase()
                        .replace(/[^A-Z0-9]/g, '')
                        .substring(0, 20);
                } else {
                    const capsAndNumbers = partName.match(/[A-Z0-9]/g);
                    if (capsAndNumbers && capsAndNumbers.length >= 8) {
                        partCode = capsAndNumbers.join('').substring(0, 20);
                    } else {
                        partCode = partName
                            .toUpperCase()
                            .replace(/[^A-Z0-9]/g, '')
                            .substring(0, 20);
                    }
                }

                if (partCode.length < 5) {
                    partCode = partName
                        .substring(0, 20)
                        .replace(/\s+/g, '')
                        .toUpperCase();
                }

                let finalPartCode = partCode;
                let suffix = 1;
                while (parts.some(p => p.part_code === finalPartCode)) {
                    finalPartCode = partCode.substring(0, 17) + '_' + String(suffix).padStart(2, '0');
                    suffix++;
                }

                const matchedSupplier = props.suppliers.find(s => {
                    const supplierNameUpper = s.supplier_name.toUpperCase();
                    const supplierCodeUpper = s.supplier_code?.toUpperCase() || '';
                    const vendorUpper = vendor.toUpperCase();

                    return supplierNameUpper === vendorUpper ||
                           supplierCodeUpper === vendorUpper ||
                           supplierNameUpper.includes(vendorUpper) ||
                           vendorUpper.includes(supplierNameUpper);
                });

                parts.push({
                    part_code: finalPartCode,
                    id_sap: idSap || '',
                    type_line: typeLine || '',
                    part_name: partName,
                    supplier_id: matchedSupplier?.id || 0,
                    supplier_name: vendor || '-',
                    description: ''
                });

                processedRows++;
            }

            importData.value = parts;

            if (parts.length === 0) {
                alert('Tidak ada data part ditemukan. Pastikan:\n1. File CSV memiliki header NAME MATERIAL\n2. Ada data di bawah header\n3. Kolom NAME MATERIAL tidak kosong');
            } else {
                const noSupplier = parts.filter(p => p.supplier_id === 0).length;
                if (noSupplier > 0) {
                    alert(`✓ ${parts.length} parts berhasil dibaca\n⚠ ${noSupplier} parts belum memiliki supplier yang cocok`);
                }
            }

        } catch (error) {
            console.error('Error parsing file:', error);
            alert('Error membaca file: ' + error + '\n\nPastikan file dalam format CSV UTF-8 atau TXT Tab-delimited');
        }
    };

    reader.onerror = () => {
        alert('Error membaca file. Pastikan file tidak rusak.');
    };

    reader.readAsText(file, 'UTF-8');
};

const submitImport = () => {
    if (importData.value.length === 0) {
        alert('Tidak ada data untuk diimport');
        return;
    }

    const invalidParts = importData.value.filter(p => p.supplier_id === 0);
    if (invalidParts.length > 0) {
        if (!confirm(`Ada ${invalidParts.length} part tanpa supplier yang valid. Lanjutkan import?`)) {
            return;
        }
    }

    router.post('/parts/import', {
        parts: importData.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showImportModal.value = false;
            importData.value = [];
            if (fileInput.value) {
                fileInput.value.value = '';
            }
            alert('Data berhasil diimport!');
        },
        onError: (errors) => {
            console.error('Import errors:', errors);
            alert('Terjadi kesalahan saat import data. Lihat console untuk detail.');
        }
    });
};

const removeImportRow = (index: number) => {
    importData.value.splice(index, 1);
};
</script>

<template>
    <Head title="Master Parts" />
    <AppLayout :breadcrumbs="[{ title: 'Master Parts', href: '/parts' }]">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center">
                <div class="flex-1 max-w-3xl">
                    <div class="flex gap-2 items-center">
                        <input
                            v-model="searchQuery"
                            @keyup.enter="search"
                            type="text"
                            placeholder="Cari ID SAP, nama part, type line, atau supplier..."
                            class="flex-1 rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                        />
                        <select
                            v-model="filterSupplier"
                            @change="filterBySupplier"
                            class="rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar min-w-[180px]"
                        >
                            <option value="0">Semua Supplier</option>
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                {{ supplier.supplier_name }}
                            </option>
                        </select>

                        <select
                            v-model="filterTypeLine"
                            @change="filterByTypeLine"
                            class="rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar min-w-[150px]"
                        >
                            <option value="">Semua Type Line</option>
                            <option value="TG4">TG4</option>
                            <option value="2TQ/TG4">2TQ/TG4</option>
                            <option value="2TQ/2MU/2SJ/TG4">2TQ/2MU/2SJ/TG4</option>
                            <option value="2TQ/2MU/TG4">2TQ/2MU/TG4</option>
                            <option value="C">C</option>
                        </select>

                        <button
                            @click="search"
                            class="p-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md"
                            title="Cari"
                        >
                            <Search class="w-5 h-5" />
                        </button>
                        <button
                            v-if="searchQuery || filterSupplier > 0 || filterTypeLine"
                            @click="resetFilters"
                            class="p-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md"
                            title="Reset Filter"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button
                        @click="openImportModal()"
                        class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                    >
                        <Upload class="w-4 h-4" />
                        Import Data
                    </button>
                    <button
                        @click="openModal()"
                        class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                        <Plus class="w-4 h-4" />
                        Tambah Part
                    </button>
                </div>
            </div>

            <div class="border border-sidebar-border rounded-lg overflow-hidden bg-white dark:bg-sidebar">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Gambar</th>
                                <!-- <th class="px-4 py-3 text-left text-sm font-semibold">Kode Part</th> -->
                                <th class="px-4 py-3 text-left text-sm font-semibold">ID SAP</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Nama Part</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Type Line</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Supplier</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="part in parts.data"
                                :key="part.id"
                                class="border-b border-sidebar-border hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <div
                                            class="relative cursor-pointer group"
                                            @click="viewImages(part)"
                                        >
                                            <img
                                                v-if="part.product_images && part.product_images.length > 0"
                                                :src="`/storage/${part.product_images[0]}`"
                                                class="w-16 h-16 object-cover rounded"
                                            />
                                            <div v-else class="w-16 h-16 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                                <ImageIcon class="w-6 h-6 text-gray-400" />
                                            </div>

                                            <div
                                                v-if="part.product_images && part.product_images.length > 1"
                                                class="absolute -top-1 -right-1 bg-blue-600 text-white text-xs px-1.5 py-0.5 rounded-full font-semibold"
                                            >
                                                +{{ part.product_images.length - 1 }}
                                            </div>

                                            <div class="absolute inset-0 bg-black/50 rounded opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                <span class="text-white text-xs">Lihat</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <!-- <td class="px-4 py-3 text-sm font-medium">{{ part.part_code }}</td> -->
                                <td class="px-4 py-3 text-sm">{{ part.id_sap || '-' }}</td>
                                <td class="px-4 py-3 text-sm">{{ part.part_name }}</td>
                                <td class="px-4 py-3 text-sm">{{ part.type_line || '-' }}</td>
                                <td class="px-4 py-3 text-sm">{{ part.supplier.supplier_name }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            @click="openModal(part)"
                                            class="p-1 text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-900 rounded"
                                        >
                                            <Edit class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deletePart(part.id)"
                                            class="p-1 text-red-600 hover:bg-red-100 dark:hover:bg-red-900 rounded"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="parts.last_page > 1" class="px-4 py-3 border-t border-sidebar-border bg-gray-50 dark:bg-sidebar-accent">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Halaman {{ parts.current_page }} dari {{ parts.last_page }}
                        </div>
                        <div class="flex gap-1">
                            <button
                                v-if="parts.current_page > 2"
                                @click="router.get('/parts', {
                                    page: 1,
                                    search: searchQuery,
                                    supplier: filterSupplier > 0 ? filterSupplier : undefined,
                                    type_line: filterTypeLine || undefined
                                })"
                                class="px-3 py-1 rounded border border-sidebar-border hover:bg-sidebar text-sm"
                            >
                                1
                            </button>
                            <span v-if="parts.current_page > 3" class="px-2 py-1 text-gray-500">...</span>

                            <button
                                v-if="parts.current_page > 1"
                                @click="router.get('/parts', { page: parts.current_page - 1, search: searchQuery, supplier: filterSupplier > 0 ? filterSupplier : undefined })"
                                class="px-3 py-1 rounded border border-sidebar-border hover:bg-sidebar text-sm"
                            >
                                {{ parts.current_page - 1 }}
                            </button>

                            <button class="px-3 py-1 rounded bg-blue-600 text-white text-sm font-semibold">
                                {{ parts.current_page }}
                            </button>

                            <button
                                v-if="parts.current_page < parts.last_page"
                                @click="router.get('/parts', { page: parts.current_page + 1, search: searchQuery, supplier: filterSupplier > 0 ? filterSupplier : undefined })"
                                class="px-3 py-1 rounded border border-sidebar-border hover:bg-sidebar text-sm"
                            >
                                {{ parts.current_page + 1 }}
                            </button>

                            <span v-if="parts.current_page < parts.last_page - 2" class="px-2 py-1 text-gray-500">...</span>

                            <button
                                v-if="parts.current_page < parts.last_page - 1"
                                @click="router.get('/parts', { page: parts.last_page, search: searchQuery, supplier: filterSupplier > 0 ? filterSupplier : undefined })"
                                class="px-3 py-1 rounded border border-sidebar-border hover:bg-sidebar text-sm"
                            >
                                {{ parts.last_page }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Form Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-3xl w-full p-6 max-h-[90vh] overflow-y-auto">
                <h2 class="text-xl font-semibold mb-4">{{ editMode ? 'Edit' : 'Tambah' }} Part</h2>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Supplier *</label>
                        <select
                            v-model="form.supplier_id"
                            required
                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                        >
                            <option value="0" disabled>Pilih Supplier</option>
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                {{ supplier.supplier_name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Kode Part *</label>
                            <input
                                v-model="form.part_code"
                                type="text"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">ID SAP</label>
                            <input
                                v-model="form.id_sap"
                                type="text"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Nama Part *</label>
                            <input
                                v-model="form.part_name"
                                type="text"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Type Line</label>
                            <input
                                v-model="form.type_line"
                                type="text"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">
                            Gambar Produk
                            <span class="text-xs text-gray-500 font-normal">(Bisa pilih lebih dari 1)</span>
                        </label>

                        <div v-if="imagePreviews.length > 0 || imagePreviewsNew.length > 0" class="mb-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-600">
                                    {{ imagePreviews.length + imagePreviewsNew.length }} gambar
                                </span>
                                <button
                                    type="button"
                                    @click="clearAllImages"
                                    class="text-xs text-red-600 hover:text-red-800 flex items-center gap-1"
                                >
                                    <Trash2 class="w-3 h-3" />
                                    Hapus Semua
                                </button>
                            </div>

                            <div class="grid grid-cols-4 gap-2">
                                <div
                                    v-for="(preview, index) in imagePreviews"
                                    :key="'existing-' + index"
                                    class="relative group"
                                >
                                    <img
                                        :src="preview"
                                        class="w-full h-24 object-cover rounded border-2"
                                        :class="index === 0 ? 'border-blue-500' : 'border-gray-300'"
                                    />
                                    <button
                                        type="button"
                                        @click="removeImage(index)"
                                        class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        <X class="w-4 h-4" />
                                    </button>
                                    <div v-if="index === 0" class="absolute bottom-1 left-1 bg-blue-600 text-white text-[10px] px-2 py-0.5 rounded">
                                        Utama
                                    </div>
                                    <div v-if="index < form.existing_images.length" class="absolute top-1 left-1 bg-green-600 text-white text-[10px] px-2 py-0.5 rounded">
                                        ✓
                                    </div>
                                </div>

                                <div
                                    v-for="(preview, index) in imagePreviewsNew"
                                    :key="'new-' + index"
                                    class="relative group"
                                >
                                    <img
                                        :src="preview"
                                        class="w-full h-24 object-cover rounded border-2 border-orange-500"
                                    />
                                    <button
                                        type="button"
                                        @click="removeImage(imagePreviews.length + index)"
                                        class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        <X class="w-4 h-4" />
                                    </button>
                                    <div class="absolute bottom-1 left-1 bg-orange-600 text-white text-[10px] px-2 py-0.5 rounded">
                                        Baru
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            @change="handleImagesChange"
                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                        />

                        <p class="text-xs text-gray-500 mt-1">
                            {{ editMode ? 'Gambar baru akan ditambahkan ke gambar existing. Klik X untuk hapus gambar tertentu.' : 'Gambar pertama akan dijadikan foto utama' }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Deskripsi</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                        ></textarea>
                    </div>

                    <div class="flex gap-2 justify-end pt-4">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2 border rounded-md hover:bg-gray-100 dark:hover:bg-sidebar-accent"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Menyimpan...' : (editMode ? 'Update' : 'Simpan') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Import Modal -->
        <div v-if="showImportModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-6xl w-full p-6 max-h-[90vh] overflow-auto">
                <h2 class="text-xl font-semibold mb-4">Import Parts dari Excel</h2>

                <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-md">
                    <p class="text-sm font-semibold mb-2">Cara Import:</p>
                    <ol class="text-sm space-y-1 list-decimal list-inside">
                        <li>Buka file Excel Anda</li>
                        <li>Save As → pilih <strong>CSV UTF-8 (Comma delimited) (*.csv)</strong> atau <strong>Text (Tab delimited) (*.txt)</strong></li>
                        <li>Upload file CSV/TXT yang baru dibuat</li>
                        <li>Data akan diambil dari kolom <strong>ID SAP, NAME MATERIAL, TYPE LINE, dan VENDOR</strong></li>
                        <li>Supplier akan otomatis di-match berdasarkan nama vendor</li>
                    </ol>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Pilih File CSV/TXT</label>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".csv,.txt"
                        @change="handleFileUpload"
                        class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                    />
                </div>

                <div v-if="importData.length > 0" class="space-y-4">
                    <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-md">
                        <p class="text-sm font-medium">✓ {{ importData.length }} parts ditemukan dan siap diimport</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                            Parts tanpa supplier yang valid akan dilewati atau bisa dipilih manual
                        </p>
                    </div>

                    <div class="border border-sidebar-border rounded-lg overflow-hidden">
                        <div class="overflow-x-auto max-h-96">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left">No</th>
                                        <th class="px-3 py-2 text-left">Kode Part</th>
                                        <th class="px-3 py-2 text-left">ID SAP</th>
                                        <th class="px-3 py-2 text-left">Nama Part</th>
                                        <th class="px-3 py-2 text-left">Type Line</th>
                                        <th class="px-3 py-2 text-left">Supplier</th>
                                        <th class="px-3 py-2 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, index) in importData"
                                        :key="index"
                                        class="border-b border-sidebar-border"
                                        :class="item.supplier_id === 0 ? 'bg-yellow-50 dark:bg-yellow-900/10' : ''"
                                    >
                                        <td class="px-3 py-2">{{ index + 1 }}</td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-model="item.part_code"
                                                type="text"
                                                class="w-full px-2 py-1 rounded border border-sidebar-border dark:bg-sidebar text-xs"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-model="item.id_sap"
                                                type="text"
                                                class="w-full px-2 py-1 rounded border border-sidebar-border dark:bg-sidebar text-xs"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-model="item.part_name"
                                                type="text"
                                                class="w-full px-2 py-1 rounded border border-sidebar-border dark:bg-sidebar text-xs"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-model="item.type_line"
                                                type="text"
                                                class="w-full px-2 py-1 rounded border border-sidebar-border dark:bg-sidebar text-xs"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <select
                                                v-model="item.supplier_id"
                                                class="w-full px-2 py-1 rounded border border-sidebar-border dark:bg-sidebar text-xs"
                                                :class="item.supplier_id === 0 ? 'border-yellow-500' : ''"
                                            >
                                                <option value="0">Pilih Supplier</option>
                                                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                                    {{ supplier.supplier_name }}
                                                </option>
                                            </select>
                                            <span v-if="item.supplier_name && item.supplier_id === 0" class="text-xs text-yellow-600">
                                                ({{ item.supplier_name }} tidak ditemukan)
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <button
                                                @click="removeImportRow(index)"
                                                class="p-1 text-red-600 hover:bg-red-100 dark:hover:bg-red-900 rounded"
                                            >
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 justify-end pt-4">
                    <button
                        type="button"
                        @click="showImportModal = false"
                        class="px-4 py-2 border rounded-md hover:bg-gray-100 dark:hover:bg-sidebar-accent"
                    >
                        Batal
                    </button>
                    <button
                        v-if="importData.length > 0"
                        @click="submitImport"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                    >
                        Import {{ importData.length }} Parts
                    </button>
                </div>
            </div>
        </div>
        <!-- Image Gallery Modal -->
        <div v-if="showImageModal" class="fixed inset-0 bg-black/95 flex items-center justify-center z-50 p-4">
            <div class="relative max-w-5xl w-full">
                <button
                    @click="showImageModal = false"
                    class="absolute top-4 right-4 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white rounded-full p-3 transition-all z-50 shadow-lg"
                >
                    <X class="w-6 h-6" />
                </button>

                <div class="relative">
                    <img
                        :src="selectedImages[currentImageIndex]"
                        class="w-full h-auto max-h-[80vh] object-contain rounded-lg"
                    />

                    <button
                        v-if="currentImageIndex > 0"
                        @click="prevImage"
                        class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-white text-black rounded-full p-3"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <button
                        v-if="currentImageIndex < selectedImages.length - 1"
                        @click="nextImage"
                        class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-white text-black rounded-full p-3"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>

                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-black/70 text-white px-4 py-2 rounded-full text-sm">
                        {{ currentImageIndex + 1 }} / {{ selectedImages.length }}
                    </div>
                </div>

                <div v-if="selectedImages.length > 1" class="flex gap-2 justify-center mt-4 overflow-x-auto">
                    <img
                        v-for="(image, index) in selectedImages"
                        :key="index"
                        :src="image"
                        @click="currentImageIndex = index"
                        class="w-20 h-20 object-cover rounded cursor-pointer border-2 transition-all"
                        :class="index === currentImageIndex ? 'border-blue-500 scale-110' : 'border-white/30 opacity-60 hover:opacity-100'"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

