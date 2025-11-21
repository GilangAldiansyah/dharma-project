<!-- eslint-disable @typescript-eslint/no-unused-vars -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Edit, Trash2, Search, Image as ImageIcon, X } from 'lucide-vue-next';

interface Part {
    id: number;
    part_code: string;
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
    suppliers: Array<{ id: number; supplier_name: string }>;
}

const props = defineProps<Props>();

const showModal = ref(false);
const editMode = ref(false);
const searchQuery = ref('');
const imagePreviews = ref<string[]>([]);
const showImageModal = ref(false);
const selectedImages = ref<string[]>([]);
const currentImageIndex = ref(0);

const form = useForm({
    id: 0,
    supplier_id: 0,
    part_code: '',
    part_name: '',
    product_image: null as File | null,
    product_images: [] as File[],
    description: '',
});

const openModal = (part?: Part) => {
    if (part) {
        editMode.value = true;
        form.id = part.id;
        form.supplier_id = part.supplier.id;
        form.part_code = part.part_code;
        form.part_name = part.part_name;
        form.description = part.description;

        // Load existing images
        imagePreviews.value = [];
        if (part.product_image) {
            imagePreviews.value.push(`/storage/${part.product_image}`);
        }
        if (part.product_images && part.product_images.length > 0) {
            part.product_images.forEach(img => {
                imagePreviews.value.push(`/storage/${img}`);
            });
        }
    } else {
        editMode.value = false;
        form.reset();
        imagePreviews.value = [];
    }
    showModal.value = true;
};

const handleImagesChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const files = target.files;

    if (files) {
        // Clear previous selections
        form.product_images = [];
        imagePreviews.value = [];

        // Add new files
        Array.from(files).forEach(file => {
            form.product_images.push(file);
            imagePreviews.value.push(URL.createObjectURL(file));
        });

        // Set first image as main image
        if (files.length > 0) {
            form.product_image = files[0];
        }
    }
};

const removeImage = (index: number) => {
    imagePreviews.value.splice(index, 1);
    form.product_images.splice(index, 1);

    if (form.product_images.length > 0) {
        form.product_image = form.product_images[0];
    } else {
        form.product_image = null;
    }
};

const submit = () => {
    if (editMode.value) {
        form.put(`/parts/${form.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                imagePreviews.value = [];
            },
        });
    } else {
        form.post('/parts', {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                imagePreviews.value = [];
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
    router.get('/parts', { search: searchQuery.value }, { preserveState: true });
};

const viewImages = (part: Part) => {
    selectedImages.value = [];
    if (part.product_image) {
        selectedImages.value.push(`/storage/${part.product_image}`);
    }
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
</script>

<template>
    <Head title="Master Parts" />
    <AppLayout :breadcrumbs="[{ title: 'Master Parts', href: '/parts' }]">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center">
                <div class="flex gap-2 items-center flex-1 max-w-md">
                    <input
                        v-model="searchQuery"
                        @keyup.enter="search"
                        type="text"
                        placeholder="Cari kode atau nama part..."
                        class="flex-1 rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                    />
                    <button @click="search" class="p-2 bg-sidebar hover:bg-sidebar-accent rounded-md">
                        <Search class="w-5 h-5" />
                    </button>
                </div>
                <button
                    @click="openModal()"
                    class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                >
                    <Plus class="w-4 h-4" />
                    Tambah Part
                </button>
            </div>

            <div class="border border-sidebar-border rounded-lg overflow-hidden bg-white dark:bg-sidebar">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Gambar</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Kode Part</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Nama Part</th>
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
                                                v-if="part.product_image"
                                                :src="`/storage/${part.product_image}`"
                                                class="w-16 h-16 object-cover rounded"
                                            />
                                            <div v-else class="w-16 h-16 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                                <ImageIcon class="w-6 h-6 text-gray-400" />
                                            </div>

                                            <!-- Badge jumlah foto -->
                                            <div
                                                v-if="part.product_images && part.product_images.length > 0"
                                                class="absolute -top-1 -right-1 bg-blue-600 text-white text-xs px-1.5 py-0.5 rounded-full font-semibold"
                                            >
                                                +{{ part.product_images.length }}
                                            </div>

                                            <!-- Hover overlay -->
                                            <div class="absolute inset-0 bg-black/50 rounded opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                <span class="text-white text-xs">Lihat</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium">{{ part.part_code }}</td>
                                <td class="px-4 py-3 text-sm">{{ part.part_name }}</td>
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
                            <label class="block text-sm font-medium mb-1">Nama Part *</label>
                            <input
                                v-model="form.part_name"
                                type="text"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">
                            Gambar Produk
                            <span class="text-xs text-gray-500 font-normal">(Bisa pilih lebih dari 1)</span>
                        </label>
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            @change="handleImagesChange"
                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                        />

                        <!-- Image Previews Grid -->
                        <div v-if="imagePreviews.length > 0" class="mt-3 grid grid-cols-4 gap-2">
                            <div
                                v-for="(preview, index) in imagePreviews"
                                :key="index"
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
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Gambar pertama akan dijadikan foto utama</p>
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

        <!-- Image Gallery Modal -->
        <div v-if="showImageModal" class="fixed inset-0 bg-black/95 flex items-center justify-center z-50 p-4">
            <div class="relative max-w-5xl w-full">
                <!-- Close Button -->
                <button
                    @click="showImageModal = false"
                    class="absolute -top-12 right-0 text-white hover:text-gray-300 flex items-center gap-2"
                >
                    <span class="text-sm">Tutup</span>
                    <X class="w-8 h-8" />
                </button>

                <!-- Main Image -->
                <div class="relative">
                    <img
                        :src="selectedImages[currentImageIndex]"
                        class="w-full h-auto max-h-[80vh] object-contain rounded-lg"
                    />

                    <!-- Navigation Buttons -->
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

                    <!-- Image Counter -->
                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-black/70 text-white px-4 py-2 rounded-full text-sm">
                        {{ currentImageIndex + 1 }} / {{ selectedImages.length }}
                    </div>
                </div>

                <!-- Thumbnails -->
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
