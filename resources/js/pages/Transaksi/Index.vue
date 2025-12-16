<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Search, Plus, Package, Eye, Filter, Trash2, X, Camera, Calendar, User, Hash, ChevronLeft, ChevronRight } from 'lucide-vue-next';

interface Material {
    id: number;
    material_id: string;
    nama_material: string;
    material_type: string;
    satuan: string;
    part_materials: PartMaterial[];
}

interface PartMaterial {
    id: number;
    part_id: string;
    nama_part: string;
    material_id: number;
}

interface Transaksi {
    id: number;
    transaksi_id: string;
    tanggal: string;
    shift: number;
    qty: number;
    foto: string[];
    material: {
        material_id: string;
        nama_material: string;
        satuan: string;
        material_type: string;
    };
    part_material?: {
        part_id: string;
        nama_part: string;
    };
    user?: {
        name: string;
    };
}

interface Props {
    transaksi: {
        data: Transaksi[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search?: string;
        shift?: number;
        date_from?: string;
        date_to?: string;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const filterShift = ref(props.filters.shift || '');
const filterDateFrom = ref(props.filters.date_from || '');
const filterDateTo = ref(props.filters.date_to || '');
const showFilters = ref(false);

// Multiple delete state
const selectedItems = ref<number[]>([]);
const selectAll = ref(false);

const showModal = ref(false);
const showDetailModal = ref(false);
const selectedTransaksi = ref<Transaksi | null>(null);
const searchMaterialQuery = ref('');
const searchResults = ref<Material[]>([]);
const selectedMaterial = ref<Material | null>(null);
const isSearching = ref(false);
const previewImages = ref<string[]>([]);

const form = useForm({
    tanggal: new Date().toISOString().split('T')[0],
    shift: 1,
    material_id: null as number | null,
    part_material_id: null as number | null,
    qty: '',
    foto: [] as File[],
});

// Fungsi untuk format qty tanpa .00
const formatQty = (qty: number) => {
    return parseFloat(qty.toString());
};

const search = () => {
    router.get('/transaksi', {
        search: searchQuery.value,
        shift: filterShift.value,
        date_from: filterDateFrom.value,
        date_to: filterDateTo.value,
    }, { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
    searchQuery.value = '';
    filterShift.value = '';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    router.get('/transaksi');
};

const goToPage = (page: number) => {
    router.get(`/transaksi?page=${page}`, {
        search: searchQuery.value,
        shift: filterShift.value,
        date_from: filterDateFrom.value,
        date_to: filterDateTo.value,
    }, { preserveState: true, preserveScroll: true });
};

const getPaginationRange = () => {
    const currentPage = props.transaksi.current_page;
    const lastPage = props.transaksi.last_page;
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

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const deleteTransaksi = (id: number) => {
    if (confirm('Yakin ingin menghapus transaksi ini?')) {
        router.delete(`/transaksi/${id}`, {
            onSuccess: () => {
                // Berhasil dihapus
            },
            onError: (errors) => {
                alert('Gagal menghapus transaksi. Silakan coba lagi.');
                console.error(errors);
            }
        });
    }
};

// Toggle select all
const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedItems.value = props.transaksi.data.map(item => item.id);
    } else {
        selectedItems.value = [];
    }
};

// Toggle individual item
const toggleSelectItem = (id: number) => {
    const index = selectedItems.value.indexOf(id);
    if (index > -1) {
        selectedItems.value.splice(index, 1);
    } else {
        selectedItems.value.push(id);
    }

    // Update select all checkbox
    selectAll.value = selectedItems.value.length === props.transaksi.data.length;
};

// Delete multiple items
const deleteMultiple = () => {
    if (selectedItems.value.length === 0) {
        alert('Pilih minimal 1 transaksi untuk dihapus');
        return;
    }

    if (confirm(`Yakin ingin menghapus ${selectedItems.value.length} transaksi yang dipilih?`)) {
        router.post('/transaksi/delete-multiple', {
            ids: selectedItems.value
        }, {
            onSuccess: () => {
                selectedItems.value = [];
                selectAll.value = false;
            },
            onError: (errors) => {
                alert('Gagal menghapus transaksi. Silakan coba lagi.');
                console.error(errors);
            }
        });
    }
};

// Watch for changes in transaksi data to update select all state
watch(() => props.transaksi.data, () => {
    selectAll.value = selectedItems.value.length === props.transaksi.data.length && props.transaksi.data.length > 0;
}, { deep: true });

const openModal = () => {
    showModal.value = true;
    form.reset();
    selectedMaterial.value = null;
    searchMaterialQuery.value = '';
    searchResults.value = [];
    previewImages.value = [];
    form.tanggal = new Date().toISOString().split('T')[0];
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    selectedMaterial.value = null;
    searchMaterialQuery.value = '';
    searchResults.value = [];
    previewImages.value = [];
};

const viewDetail = (transaksi: Transaksi) => {
    selectedTransaksi.value = transaksi;
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    selectedTransaksi.value = null;
};

const searchMaterial = async () => {
    if (!searchMaterialQuery.value || searchMaterialQuery.value.length < 2) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(`/materials/search/api?query=${encodeURIComponent(searchMaterialQuery.value)}`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            throw new Error("Response is not JSON");
        }

        searchResults.value = await response.json();
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    } catch (error) {
        searchResults.value = [];
        alert('Gagal mencari material. Silakan coba lagi.');
    } finally {
        isSearching.value = false;
    }
};

const selectMaterial = (material: Material) => {
    selectedMaterial.value = material;
    form.material_id = material.id;

    if (material.part_materials.length === 1) {
        form.part_material_id = material.part_materials[0].id;
    } else {
        form.part_material_id = null;
    }

    searchResults.value = [];
    searchMaterialQuery.value = '';
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files || []);

    if (files.length + form.foto.length > 5) {
        alert('Maksimal 5 foto');
        return;
    }

    form.foto = [...form.foto, ...files].slice(0, 5);

    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImages.value.push(e.target?.result as string);
        };
        reader.readAsDataURL(file);
    });
};

const removeImage = (index: number) => {
    form.foto.splice(index, 1);
    previewImages.value.splice(index, 1);
};

const submit = () => {
    form.post('/transaksi', {
        onSuccess: () => {
            closeModal();
        },
    });
};

watch(searchMaterialQuery, () => {
    if (searchMaterialQuery.value.length >= 2) {
        searchMaterial();
    } else {
        searchResults.value = [];
    }
});
</script>

<template>
    <Head title="Transaksi Material" />
    <AppLayout :breadcrumbs="[
        { title: 'Transaksi Material', href: '/transaksi' }
    ]">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Package class="w-6 h-6 text-blue-600" />
                    Transaksi Pengambilan Material
                </h1>
                <div class="flex items-center gap-2">
                    <button
                        v-if="selectedItems.length > 0"
                        @click="deleteMultiple"
                        class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                    >
                        <Trash2 class="w-4 h-4" />
                        Hapus {{ selectedItems.length }} Terpilih
                    </button>
                    <button
                        @click="openModal"
                        class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                        <Plus class="w-4 h-4" />
                        Input Transaksi
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Transaksi</div>
                    <div class="text-2xl font-bold">{{ transaksi.total }}</div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Shift 1</div>
                    <div class="text-2xl font-bold text-blue-600">
                        {{ transaksi.data.filter(t => t.shift === 1).length }}
                    </div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Shift 2</div>
                    <div class="text-2xl font-bold text-green-600">
                        {{ transaksi.data.filter(t => t.shift === 2).length }}
                    </div>
                </div>
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Shift 3</div>
                    <div class="text-2xl font-bold text-purple-600">
                        {{ transaksi.data.filter(t => t.shift === 3).length }}
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4 space-y-3">
                <div class="flex gap-2">
                    <input
                        v-model="searchQuery"
                        @keyup.enter="search"
                        type="text"
                        placeholder="Cari transaksi atau material..."
                        class="flex-1 rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                    />
                    <button
                        @click="showFilters = !showFilters"
                        class="px-4 py-2 bg-sidebar hover:bg-sidebar-accent rounded-md flex items-center gap-2"
                    >
                        <Filter class="w-4 h-4" />
                        Filter
                    </button>
                    <button @click="search" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center gap-2">
                        <Search class="w-4 h-4" />
                        Cari
                    </button>
                </div>

                <div v-if="showFilters" class="grid grid-cols-3 gap-3 pt-3 border-t border-sidebar-border">
                    <div>
                        <label class="block text-sm mb-1">Shift</label>
                        <select
                            v-model="filterShift"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar text-sm"
                        >
                            <option value="">Semua Shift</option>
                            <option :value="1">Shift 1</option>
                            <option :value="2">Shift 2</option>
                            <option :value="3">Shift 3</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Dari Tanggal</label>
                        <input
                            v-model="filterDateFrom"
                            type="date"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar text-sm"
                        />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Sampai Tanggal</label>
                        <input
                            v-model="filterDateTo"
                            type="date"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar text-sm"
                        />
                    </div>
                </div>

                <div v-if="showFilters" class="flex gap-2">
                    <button
                        @click="search"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"
                    >
                        Terapkan Filter
                    </button>
                    <button
                        @click="resetFilters"
                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left">
                                    <input
                                        type="checkbox"
                                        v-model="selectAll"
                                        @change="toggleSelectAll"
                                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                </th>
                                <th class="px-4 py-3 text-left text-sm font-medium">ID Transaksi</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Tanggal</th>
                                <th class="px-7 py-3 text-left text-sm font-medium">Shift</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Material</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Part</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">QTY</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Foto</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">User</th>
                                <th class="px-4 py-3 text-center text-sm font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr
                                v-for="item in transaksi.data"
                                :key="item.id"
                                :class="[
                                    'hover:bg-sidebar-accent',
                                    selectedItems.includes(item.id) ? 'bg-blue-50 dark:bg-blue-900/10' : ''
                                ]"
                            >
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="selectedItems.includes(item.id)"
                                        @change="toggleSelectItem(item.id)"
                                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                </td>
                                <td class="px-4 py-3 text-sm font-medium">{{ item.transaksi_id }}</td>
                                <td class="px-4 py-3 text-sm">{{ formatDate(item.tanggal) }}</td>
                                <td class="px-4 py-3">
                                    <span :class="[
                                        'px-2 py-1 rounded-full text-xs font-medium',
                                        item.shift === 1 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' :
                                        item.shift === 2 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                        'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                                    ]">
                                        Shift {{ item.shift }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium">{{ item.material.material_id }}</div>
                                    <div class="text-gray-500 text-xs">{{ item.material.nama_material }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div v-if="item.part_material">
                                        <div class="font-medium">{{ item.part_material.part_id }}</div>
                                        <div class="text-gray-500 text-xs">{{ item.part_material.nama_part }}</div>
                                    </div>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium">
                                    {{ formatQty(item.qty) }} {{ item.material.satuan }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span v-if="item.foto && item.foto.length > 0" class="text-blue-600">
                                        {{ item.foto.length }} foto
                                    </span>
                                    <span v-else class="text-gray-400">Tidak ada</span>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ item.user?.name || '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            @click="viewDetail(item)"
                                            class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded"
                                        >
                                            <Eye class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deleteTransaksi(item.id)"
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

                <div v-if="transaksi.data.length === 0" class="text-center py-12 text-gray-500">
                    <Package class="w-16 h-16 mx-auto mb-4 opacity-50" />
                    <p>Tidak ada transaksi</p>
                </div>
            </div>

            <div v-if="transaksi.last_page > 1" class="flex items-center justify-between bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Halaman {{ transaksi.current_page }} dari {{ transaksi.last_page }}
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="goToPage(transaksi.current_page - 1)"
                        :disabled="transaksi.current_page === 1"
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
                                page === transaksi.current_page
                                    ? 'bg-blue-600 text-white'
                                    : 'border border-sidebar-border hover:bg-sidebar-accent'
                            ]"
                        >
                            {{ page }}
                        </button>
                        <span v-else class="px-2 text-gray-500">{{ page }}</span>
                    </template>

                    <button
                        @click="goToPage(transaksi.current_page + 1)"
                        :disabled="transaksi.current_page === transaksi.last_page"
                        class="p-2 rounded-md border border-sidebar-border hover:bg-sidebar-accent disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>

                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Total {{ transaksi.total }} data
                </div>
            </div>
        </div>

        <div v-if="showDetailModal && selectedTransaksi" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-6 border-b border-sidebar-border sticky top-0 bg-white dark:bg-sidebar z-10">
                    <h2 class="text-xl font-bold">Detail Transaksi</h2>
                    <button @click="closeDetailModal" class="p-1 hover:bg-sidebar-accent rounded">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                                <Hash class="w-4 h-4" />
                                ID Transaksi
                            </div>
                            <div class="font-semibold text-lg">{{ selectedTransaksi.transaksi_id }}</div>
                        </div>

                        <div>
                            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                                <Calendar class="w-4 h-4" />
                                Tanggal
                            </div>
                            <div class="font-semibold">{{ formatDate(selectedTransaksi.tanggal) }}</div>
                        </div>

                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Shift</div>
                            <span :class="[
                                'inline-block px-3 py-1 rounded-full text-sm font-medium',
                                selectedTransaksi.shift === 1 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' :
                                selectedTransaksi.shift === 2 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                            ]">
                                Shift {{ selectedTransaksi.shift }}
                            </span>
                        </div>

                        <div>
                            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                                <User class="w-4 h-4" />
                                Dibuat Oleh
                            </div>
                            <div class="font-semibold">{{ selectedTransaksi.user?.name || '-' }}</div>
                        </div>
                    </div>

                    <div class="border-t border-sidebar-border pt-6">
                        <h3 class="font-semibold mb-4 flex items-center gap-2">
                            <Package class="w-5 h-5 text-blue-600" />
                            Informasi Material
                        </h3>
                        <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4 space-y-3">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">ID Material</div>
                                    <div class="font-medium">{{ selectedTransaksi.material.material_id }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">Nama Material</div>
                                    <div class="font-medium">{{ selectedTransaksi.material.nama_material }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">Tipe Material</div>
                                    <div class="font-medium">{{ selectedTransaksi.material.material_type }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">Satuan</div>
                                    <div class="font-medium">{{ selectedTransaksi.material.satuan }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedTransaksi.part_material" class="border-t border-sidebar-border pt-6">
                        <h3 class="font-semibold mb-4">Informasi Part</h3>
                        <div class="bg-gray-50 dark:bg-sidebar-accent rounded-lg p-4 space-y-3">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">ID Part</div>
                                    <div class="font-medium">{{ selectedTransaksi.part_material.part_id }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">Nama Part</div>
                                    <div class="font-medium">{{ selectedTransaksi.part_material.nama_part }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-sidebar-border pt-6">
                        <h3 class="font-semibold mb-4">Quantity</h3>
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-6 text-center">
                            <div class="text-4xl font-bold text-blue-600">
                                {{ formatQty(selectedTransaksi.qty) }}
                            </div>
                            <div class="text-lg text-gray-600 dark:text-gray-400 mt-2">
                                {{ selectedTransaksi.material.satuan }}
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedTransaksi.foto && selectedTransaksi.foto.length > 0" class="border-t border-sidebar-border pt-6">
                        <h3 class="font-semibold mb-4 flex items-center gap-2">
                            <Camera class="w-5 h-5" />
                            Foto ({{ selectedTransaksi.foto.length }})
                        </h3>
                        <div class="grid grid-cols-3 gap-4">
                            <a
                                v-for="(foto, index) in selectedTransaksi.foto"
                                :key="index"
                                :href="`/storage/${foto}`"
                                target="_blank"
                                class="relative aspect-square rounded-lg overflow-hidden hover:opacity-80 transition-opacity"
                            >
                                <img
                                    :src="`/storage/${foto}`"
                                    :alt="`Foto ${index + 1}`"
                                    class="w-full h-full object-cover"
                                />
                            </a>
                        </div>
                    </div>
                    <div v-else class="border-t border-sidebar-border pt-6">
                        <h3 class="font-semibold mb-4">Foto</h3>
                        <div class="text-center py-8 text-gray-500">
                            <Camera class="w-12 h-12 mx-auto mb-2 opacity-50" />
                            <p>Tidak ada foto</p>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 p-6 border-t border-sidebar-border">
                    <button
                        @click="closeDetailModal"
                        class="flex-1 px-6 py-3 bg-gray-600 text-white rounded-md hover:bg-gray-700"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-6 border-b border-sidebar-border sticky top-0 bg-white dark:bg-sidebar z-10">
                    <h2 class="text-xl font-bold">Input Pengambilan Material</h2>
                    <button @click="closeModal" class="p-1 hover:bg-sidebar-accent rounded">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="p-6 space-y-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">Cari Material</label>
                        <div class="relative">
                            <input
                                v-model="searchMaterialQuery"
                                type="text"
                                placeholder="Ketik ID atau nama material (min 2 karakter)..."
                                class="w-full px-4 py-2 border border-sidebar-border rounded-md dark:bg-sidebar"
                            />
                            <Search class="absolute right-3 top-2.5 w-5 h-5 text-gray-400" />
                        </div>

                        <div v-if="searchResults.length > 0" class="mt-2 border border-sidebar-border rounded-md max-h-60 overflow-y-auto">
                            <button
                                v-for="material in searchResults"
                                :key="material.id"
                                type="button"
                                @click="selectMaterial(material)"
                                class="w-full text-left px-4 py-3 hover:bg-sidebar-accent border-b border-sidebar-border last:border-b-0"
                            >
                                <div class="font-medium">{{ material.material_id }} - {{ material.nama_material }}</div>
                                <div class="text-sm text-gray-500">{{ material.material_type }} | {{ material.satuan }}</div>
                            </button>
                        </div>

                        <div v-if="selectedMaterial" class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-md border border-blue-200 dark:border-blue-800">
                            <div class="flex items-start gap-3">
                                <Package class="w-5 h-5 text-blue-600 mt-0.5" />
                                <div class="flex-1">
                                    <div class="font-medium">{{ selectedMaterial.material_id }}</div>
                                    <div class="text-sm">{{ selectedMaterial.nama_material }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">
                                        {{ selectedMaterial.material_type }} | {{ selectedMaterial.satuan }}
                                    </div>
                                </div>
                            </div>

                            <div v-if="selectedMaterial.part_materials.length > 1" class="mt-4">
                                <label class="block text-sm font-medium mb-2">Pilih Part</label>
                                <select
                                    v-model="form.part_material_id"
                                    class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar"
                                    required
                                >
                                    <option :value="null">-- Pilih Part --</option>
                                    <option
                                        v-for="part in selectedMaterial.part_materials"
                                        :key="part.id"
                                        :value="part.id"
                                    >
                                        {{ part.part_id }} - {{ part.nama_part }}
                                    </option>
                                </select>
                            </div>
                            <div v-else-if="selectedMaterial.part_materials.length === 1" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Part: {{ selectedMaterial.part_materials[0].part_id }} - {{ selectedMaterial.part_materials[0].nama_part }}
                            </div>
                        </div>

                        <div v-if="form.errors.material_id" class="mt-2 text-sm text-red-600">
                            {{ form.errors.material_id }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Tanggal</label>
                            <input
                                v-model="form.tanggal"
                                type="date"
                                class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar"
                                required
                            />
                            <div v-if="form.errors.tanggal" class="mt-1 text-sm text-red-600">
                                {{ form.errors.tanggal }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Shift</label>
                            <select
                                v-model="form.shift"
                                class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar"
                                required
                            >
                                <option :value="1">Shift 1</option>
                                <option :value="2">Shift 2</option>
                                <option :value="3">Shift 3</option>
                            </select>
                            <div v-if="form.errors.shift" class="mt-1 text-sm text-red-600">
                                {{ form.errors.shift }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">
                            Quantity
                            <span v-if="selectedMaterial" class="text-gray-500">({{ selectedMaterial.satuan }})</span>
                        </label>
                        <input
                            v-model="form.qty"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            class="w-full px-3 py-2 border border-sidebar-border rounded-md dark:bg-sidebar"
                            required
                        />
                        <div v-if="form.errors.qty" class="mt-1 text-sm text-red-600">
                            {{ form.errors.qty }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">
                            Foto (Maksimal 5)
                        </label>

                        <div class="flex items-center gap-4">
                            <label class="cursor-pointer flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <Camera class="w-4 h-4" />
                                Pilih Foto
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    @change="handleFileChange"
                                    class="hidden"
                                    :disabled="form.foto.length >= 5"
                                />
                            </label>
                            <span class="text-sm text-gray-500">{{ form.foto.length }}/5 foto</span>
                        </div>

                        <div v-if="previewImages.length > 0" class="mt-4 grid grid-cols-5 gap-2">
                            <div
                                v-for="(image, index) in previewImages"
                                :key="index"
                                class="relative aspect-square"
                            >
                                <img :src="image" class="w-full h-full object-cover rounded-md" />
                                <button
                                    type="button"
                                    @click="removeImage(index)"
                                    class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-700"
                                >
                                    ×
                                </button>
                            </div>
                        </div>

                        <div v-if="form.errors.foto" class="mt-2 text-sm text-red-600">
                            {{ form.errors.foto }}
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4 border-t border-sidebar-border">
                        <button
                            type="submit"
                            :disabled="form.processing || !selectedMaterial"
                            class="flex-1 px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Transaksi' }}
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
    </AppLayout>
</template>
