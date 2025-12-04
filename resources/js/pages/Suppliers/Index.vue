<!-- eslint-disable @typescript-eslint/no-unused-vars -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Edit, Trash2, Search, Upload } from 'lucide-vue-next';

interface Supplier {
    id: number;
    supplier_code: string;
    supplier_name: string;
    contact_person: string;
    phone: string;
    address: string;
}

interface Props {
    suppliers: {
        data: Supplier[];
        current_page: number;
        last_page: number;
    };
}

const props = defineProps<Props>();

const showModal = ref(false);
const showImportModal = ref(false);
const editMode = ref(false);
const searchQuery = ref('');
const importData = ref<any[]>([]);
const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    id: 0,
    supplier_code: '',
    supplier_name: '',
    contact_person: '',
    phone: '',
    address: '',
});

const openModal = (supplier?: Supplier) => {
    if (supplier) {
        editMode.value = true;
        form.id = supplier.id;
        form.supplier_code = supplier.supplier_code;
        form.supplier_name = supplier.supplier_name;
        form.contact_person = supplier.contact_person;
        form.phone = supplier.phone;
        form.address = supplier.address;
    } else {
        editMode.value = false;
        form.reset();
    }
    showModal.value = true;
};

const submit = () => {
    if (editMode.value) {
        form.put(`/suppliers/${form.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post('/suppliers', {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    }
};

const deleteSupplier = (id: number) => {
    if (confirm('Yakin hapus supplier ini?')) {
        router.delete(`/suppliers/${id}`, { preserveScroll: true });
    }
};

const search = () => {
    router.get('/suppliers', { search: searchQuery.value }, { preserveState: true });
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
            const lines = text.split('\n');

            const suppliers: any[] = [];
            const vendorSet = new Set<string>();

            let delimiter = ',';
            if (lines[0]?.includes(';')) delimiter = ';';
            else if (lines[0]?.includes('\t')) delimiter = '\t';

            let headerIndex = -1;
            let vendorColumnIndex = -1;

            for (let i = 0; i < Math.min(20, lines.length); i++) {
                const columns = lines[i].split(delimiter);
                const vendorIdx = columns.findIndex(col =>
                    col.trim().toUpperCase().includes('VENDOR')
                );

                if (vendorIdx !== -1) {
                    headerIndex = i;
                    vendorColumnIndex = vendorIdx;
                    console.log(`Found VENDOR column at row ${i}, column ${vendorIdx}`);
                    break;
                }
            }

            if (vendorColumnIndex === -1) {
                alert('Kolom VENDOR tidak ditemukan. Pastikan ada header "VENDOR" di file Excel.');
                return;
            }

            // Proses data mulai dari baris setelah header
            for (let i = headerIndex + 1; i < lines.length; i++) {
                const line = lines[i].trim();
                if (!line) continue;

                const columns = line.split(delimiter);
                const vendor = columns[vendorColumnIndex]?.trim().replace(/"/g, '');

                if (vendor && vendor !== '' && !vendorSet.has(vendor)) {
                    vendorSet.add(vendor);

                    let supplierCode = vendor
                        .toUpperCase()
                        .replace(/[^A-Z0-9]/g, '')
                        .substring(0, 10);

                    if (supplierCode.length < 3) {
                        supplierCode = vendor.substring(0, 10).toUpperCase();
                    }

                    suppliers.push({
                        supplier_code: supplierCode,
                        supplier_name: vendor,
                        contact_person: '',
                        phone: '',
                        address: ''
                    });
                }
            }

            console.log('Found suppliers:', suppliers);

            importData.value = suppliers;

            if (suppliers.length === 0) {
                alert('Tidak ada data vendor ditemukan. Pastikan kolom VENDOR terisi di file Excel.');
            }

        } catch (error) {
            console.error('Error:', error);
            alert('Error membaca file: ' + error);
        }
    };

    reader.readAsText(file);
};

const submitImport = () => {
    if (importData.value.length === 0) {
        alert('Tidak ada data untuk diimport');
        return;
    }

    router.post('/suppliers/import', {
        suppliers: importData.value
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
    <Head title="Master Suppliers" />
    <AppLayout :breadcrumbs="[{ title: 'Master Suppliers', href: '/suppliers' }]">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center">
                <div class="flex gap-2 items-center flex-1 max-w-md">
                    <input
                        v-model="searchQuery"
                        @keyup.enter="search"
                        type="text"
                        placeholder="Cari kode atau nama supplier..."
                        class="flex-1 rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                    />
                    <button @click="search" class="p-2 bg-sidebar hover:bg-sidebar-accent rounded-md">
                        <Search class="w-5 h-5" />
                    </button>
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
                        Tambah Supplier
                    </button>
                </div>
            </div>

            <div class="border border-sidebar-border rounded-lg overflow-hidden bg-white dark:bg-sidebar">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Kode</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Nama Supplier</th>
                                <!-- <th class="px-4 py-3 text-left text-sm font-semibold">Contact Person</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Telepon</th> -->
                                <th class="px-4 py-3 text-center text-sm font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="supplier in suppliers.data"
                                :key="supplier.id"
                                class="border-b border-sidebar-border hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                            >
                                <td class="px-4 py-3 text-sm font-medium">{{ supplier.supplier_code }}</td>
                                <td class="px-4 py-3 text-sm">{{ supplier.supplier_name }}</td>
                                <!-- <td class="px-4 py-3 text-sm">{{ supplier.contact_person || '-' }}</td>
                                <td class="px-4 py-3 text-sm">{{ supplier.phone || '-' }}</td> -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            @click="openModal(supplier)"
                                            class="p-1 text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-900 rounded"
                                        >
                                            <Edit class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deleteSupplier(supplier.id)"
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
                <div v-if="suppliers.last_page > 1" class="px-4 py-3 border-t border-sidebar-border bg-gray-50 dark:bg-sidebar-accent flex items-center justify-between">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Halaman {{ suppliers.current_page }} dari {{ suppliers.last_page }}
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="router.get('/suppliers', { page: suppliers.current_page - 1, search: searchQuery })"
                            :disabled="suppliers.current_page === 1"
                            class="px-3 py-1 rounded border border-sidebar-border hover:bg-sidebar disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                        >
                            Previous
                        </button>
                        <button
                            @click="router.get('/suppliers', { page: suppliers.current_page + 1, search: searchQuery })"
                            :disabled="suppliers.current_page === suppliers.last_page"
                            class="px-3 py-1 rounded border border-sidebar-border hover:bg-sidebar disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah/Edit -->
        <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-2xl w-full p-6">
                <h2 class="text-xl font-semibold mb-4">{{ editMode ? 'Edit' : 'Tambah' }} Supplier</h2>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Kode Supplier *</label>
                            <input
                                v-model="form.supplier_code"
                                type="text"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Nama Supplier *</label>
                            <input
                                v-model="form.supplier_name"
                                type="text"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Contact Person</label>
                            <input
                                v-model="form.contact_person"
                                type="text"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Telepon</label>
                            <input
                                v-model="form.phone"
                                type="text"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Alamat</label>
                        <textarea
                            v-model="form.address"
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
                            {{ editMode ? 'Update' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Import Excel -->
        <div v-if="showImportModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-4xl w-full p-6 max-h-[90vh] overflow-auto">
                <h2 class="text-xl font-semibold mb-4">Import Supplier dari Excel</h2>

                <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-md">
                    <p class="text-sm font-semibold mb-2">Cara Import:</p>
                    <ol class="text-sm space-y-1 list-decimal list-inside">
                        <li>Buka file Excel Anda</li>
                        <li>Save As → pilih <strong>CSV UTF-8 (Comma delimited) (*.csv)</strong> atau <strong>Text (Tab delimited) (*.txt)</strong></li>
                        <li>Upload file CSV/TXT yang baru dibuat</li>
                        <li>Data akan diambil dari kolom VENDOR (kolom M atau T) mulai baris ke-8</li>
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
                        <p class="text-sm font-medium">✓ {{ importData.length }} supplier ditemukan dan siap diimport</p>
                    </div>

                    <div class="border border-sidebar-border rounded-lg overflow-hidden">
                        <div class="overflow-x-auto max-h-96">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left">No</th>
                                        <th class="px-3 py-2 text-left">Kode Supplier</th>
                                        <th class="px-3 py-2 text-left">Nama Supplier</th>
                                        <th class="px-3 py-2 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, index) in importData"
                                        :key="index"
                                        class="border-b border-sidebar-border"
                                    >
                                        <td class="px-3 py-2">{{ index + 1 }}</td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-model="item.supplier_code"
                                                type="text"
                                                class="w-full px-2 py-1 rounded border border-sidebar-border dark:bg-sidebar"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-model="item.supplier_name"
                                                type="text"
                                                class="w-full px-2 py-1 rounded border border-sidebar-border dark:bg-sidebar"
                                            />
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
                        Import {{ importData.length }} Supplier
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
