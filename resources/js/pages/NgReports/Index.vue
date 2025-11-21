<!-- eslint-disable @typescript-eslint/no-unused-vars -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Plus, Trash2, Search, Image as ImageIcon, X } from 'lucide-vue-next';

interface NgReport {
    id: number;
    report_number: string;
    ng_image: string;
    ng_images: string[];
    notes: string;
    reported_by: string;
    reported_at: string;
    part: {
        id: number;
        part_code: string;
        part_name: string;
        product_image: string;
        product_images: string[];
        supplier: {
            supplier_name: string;
        };
    };
}

interface Props {
    reports: {
        data: NgReport[];
        current_page: number;
        last_page: number;
    };
    parts: Array<{
        id: number;
        part_code: string;
        part_name: string;
        product_image: string;
        product_images: string[];
        supplier: { supplier_name: string };
    }>;
}

const props = defineProps<Props>();

const showModal = ref(false);
const searchQuery = ref('');
const ngImagePreviews = ref<string[]>([]);
const okImagePreviews = ref<string[]>([]);
const showImageModal = ref(false);
const selectedImages = ref<string[]>([]);
const currentImageIndex = ref(0);
const selectedPart = ref<any>(null);

const form = useForm({
    part_id: 0,
    ng_image: null as File | null,
    ng_images: [] as File[],
    notes: '',
    reported_by: '',
});

// Auto-load foto OK dari master part ketika part dipilih
watch(() => form.part_id, (newPartId) => {
    const part = props.parts.find(p => p.id === newPartId);
    if (part) {
        selectedPart.value = part;
        okImagePreviews.value = [];

        if (part.product_image) {
            okImagePreviews.value.push(`/storage/${part.product_image}`);
        }
        if (part.product_images && part.product_images.length > 0) {
            part.product_images.forEach(img => {
                okImagePreviews.value.push(`/storage/${img}`);
            });
        }
    } else {
        selectedPart.value = null;
        okImagePreviews.value = [];
    }
});

const openModal = () => {
    form.reset();
    ngImagePreviews.value = [];
    okImagePreviews.value = [];
    selectedPart.value = null;
    showModal.value = true;
};

const handleNgImagesChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const files = target.files;

    if (files) {
        form.ng_images = [];
        ngImagePreviews.value = [];

        Array.from(files).forEach(file => {
            form.ng_images.push(file);
            ngImagePreviews.value.push(URL.createObjectURL(file));
        });

        if (files.length > 0) {
            form.ng_image = files[0];
        }
    }
};

const removeNgImage = (index: number) => {
    ngImagePreviews.value.splice(index, 1);
    form.ng_images.splice(index, 1);

    if (form.ng_images.length > 0) {
        form.ng_image = form.ng_images[0];
    } else {
        form.ng_image = null;
    }
};

const submit = () => {
    form.post('/ng-reports', {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
            ngImagePreviews.value = [];
            okImagePreviews.value = [];
            selectedPart.value = null;
        },
    });
};

const deleteReport = (id: number) => {
    if (confirm('Yakin hapus laporan NG ini?')) {
        router.delete(`/ng-reports/${id}`, { preserveScroll: true });
    }
};

const search = () => {
    router.get('/ng-reports', { search: searchQuery.value }, { preserveState: true });
};

const viewImages = (images: string[], type: 'ng' | 'ok') => {
    selectedImages.value = images.map(img => `/storage/${img}`);
    currentImageIndex.value = 0;
    showImageModal.value = true;
};

const viewReportImages = (report: NgReport) => {
    selectedImages.value = [];

    // Add NG images
    if (report.ng_image) {
        selectedImages.value.push(`/storage/${report.ng_image}`);
    }
    if (report.ng_images && report.ng_images.length > 0) {
        report.ng_images.forEach(img => {
            selectedImages.value.push(`/storage/${img}`);
        });
    }

    currentImageIndex.value = 0;
    showImageModal.value = true;
};

const viewPartImages = (part: any) => {
    selectedImages.value = [];

    if (part.product_image) {
        selectedImages.value.push(`/storage/${part.product_image}`);
    }
    if (part.product_images && part.product_images.length > 0) {
        part.product_images.forEach((img: any) => {
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

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('id-ID');
};
</script>

<template>
    <Head title="Laporan NG" />
    <AppLayout :breadcrumbs="[{ title: 'Laporan NG', href: '/ng-reports' }]">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center">
                <div class="flex gap-2 items-center flex-1 max-w-md">
                    <input
                        v-model="searchQuery"
                        @keyup.enter="search"
                        type="text"
                        placeholder="Cari nomor laporan atau part..."
                        class="flex-1 rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                    />
                    <button @click="search" class="p-2 bg-sidebar hover:bg-sidebar-accent rounded-md">
                        <Search class="w-5 h-5" />
                    </button>
                </div>
                <button
                    @click="openModal()"
                    class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                >
                    <Plus class="w-4 h-4" />
                    Lapor NG
                </button>
            </div>

            <div class="border border-sidebar-border rounded-lg overflow-hidden bg-white dark:bg-sidebar">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">No. Laporan</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Part</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Supplier</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Foto NG</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Foto OK</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Keterangan</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Dilaporkan Oleh</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Tanggal</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="report in reports.data"
                                :key="report.id"
                                class="border-b border-sidebar-border hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                            >
                                <td class="px-4 py-3 text-sm font-medium">{{ report.report_number }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <div>{{ report.part.part_name }}</div>
                                    <div class="text-xs text-gray-500">{{ report.part.part_code }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ report.part.supplier.supplier_name }}</td>

                                <!-- Foto NG -->
                                <td class="px-4 py-3">
                                    <div
                                        class="relative cursor-pointer group"
                                        @click="viewReportImages(report)"
                                    >
                                        <img
                                            :src="`/storage/${report.ng_image}`"
                                            class="w-16 h-16 object-cover rounded border-2 border-red-500"
                                            alt="Foto NG"
                                        />
                                        <div class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] px-1.5 py-0.5 rounded-full font-semibold">
                                            NG
                                        </div>
                                        <div
                                            v-if="report.ng_images && report.ng_images.length > 0"
                                            class="absolute -bottom-1 -right-1 bg-blue-600 text-white text-[10px] px-1.5 py-0.5 rounded-full font-semibold"
                                        >
                                            +{{ report.ng_images.length }}
                                        </div>
                                        <div class="absolute inset-0 bg-black/50 rounded opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <span class="text-white text-xs">Lihat</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Foto OK -->
                                <td class="px-4 py-3">
                                    <div
                                        class="relative cursor-pointer group"
                                        @click="viewPartImages(report.part)"
                                    >
                                        <img
                                            v-if="report.part.product_image"
                                            :src="`/storage/${report.part.product_image}`"
                                            class="w-16 h-16 object-cover rounded border-2 border-green-500"
                                            alt="Foto OK"
                                        />
                                        <div v-else class="w-16 h-16 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center border-2 border-gray-300">
                                            <ImageIcon class="w-6 h-6 text-gray-400" />
                                        </div>
                                        <div v-if="report.part.product_image" class="absolute -top-1 -right-1 bg-green-600 text-white text-[10px] px-1.5 py-0.5 rounded-full font-semibold">
                                            OK
                                        </div>
                                        <div
                                            v-if="report.part.product_images && report.part.product_images.length > 0"
                                            class="absolute -bottom-1 -right-1 bg-blue-600 text-white text-[10px] px-1.5 py-0.5 rounded-full font-semibold"
                                        >
                                            +{{ report.part.product_images.length }}
                                        </div>
                                        <div v-if="report.part.product_image" class="absolute inset-0 bg-black/50 rounded opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <span class="text-white text-xs">Lihat</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-sm">{{ report.notes || '-' }}</td>
                                <td class="px-4 py-3 text-sm">{{ report.reported_by }}</td>
                                <td class="px-4 py-3 text-sm">{{ formatDate(report.reported_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center">
                                        <button
                                            @click="deleteReport(report.id)"
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
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-6xl w-full p-6 max-h-[90vh] overflow-y-auto">
                <h2 class="text-xl font-semibold mb-4">Laporan Produk NG</h2>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Part *</label>
                        <select
                            v-model="form.part_id"
                            required
                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                        >
                            <option value="0" disabled>Pilih Part</option>
                            <option v-for="part in parts" :key="part.id" :value="part.id">
                                {{ part.part_name }} ({{ part.part_code }}) - {{ part.supplier.supplier_name }}
                            </option>
                        </select>
                        <p v-if="form.part_id && !selectedPart?.product_image" class="text-xs text-amber-600 mt-1 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Part ini belum memiliki foto referensi
                        </p>
                    </div>

                    <!-- Perbandingan Foto NG vs OK -->
                    <div class="bg-gradient-to-r from-red-50 to-green-50 dark:from-red-900/20 dark:to-green-900/20 rounded-lg p-6 border-2 border-dashed border-gray-300">
                        <h3 class="text-base font-semibold mb-4 text-center">📸 Perbandingan Visual Produk</h3>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                            <!-- Foto NG (Upload Multiple) -->
                            <div class="bg-white dark:bg-sidebar rounded-lg p-4">
                                <label class="block text-sm font-medium mb-2 flex items-center gap-2">
                                    <div class="w-3 h-3 bg-red-600 rounded-full animate-pulse"></div>
                                    Upload Foto NG *
                                    <span class="text-xs text-gray-500 font-normal">(Bisa lebih dari 1)</span>
                                </label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    required
                                    @change="handleNgImagesChange"
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 text-sm"
                                />

                                <!-- NG Images Grid -->
                                <div v-if="ngImagePreviews.length > 0" class="mt-3 grid grid-cols-3 gap-2">
                                    <div
                                        v-for="(preview, index) in ngImagePreviews"
                                        :key="index"
                                        class="relative group"
                                    >
                                        <img
                                            :src="preview"
                                            class="w-full h-24 object-cover rounded border-2"
                                            :class="index === 0 ? 'border-red-600' : 'border-red-300'"
                                        />
                                        <button
                                            type="button"
                                            @click="removeNgImage(index)"
                                            class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                        >
                                            <X class="w-4 h-4" />
                                        </button>
                                        <div class="absolute top-1 left-1 bg-red-600 text-white text-[10px] px-2 py-0.5 rounded font-bold">
                                            {{ index === 0 ? 'Utama' : `#${index + 1}` }}
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="mt-3 w-full h-48 bg-gray-100 dark:bg-gray-800 rounded-lg border-2 border-dashed border-red-300 flex flex-col items-center justify-center">
                                    <ImageIcon class="w-12 h-12 text-red-400 mb-2" />
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Upload foto produk bermasalah</p>
                                    <p class="text-xs text-gray-500 mt-1">Klik untuk pilih beberapa foto</p>
                                </div>
                            </div>

                            <!-- Foto OK (Auto dari Master Part) -->
                            <div class="bg-white dark:bg-sidebar rounded-lg p-4">
                                <label class="block text-sm font-medium mb-2 flex items-center gap-2">
                                    <div class="w-3 h-3 bg-green-600 rounded-full"></div>
                                    Foto Referensi OK (Otomatis)
                                </label>
                                <div class="w-full rounded-md border border-sidebar-border px-3 py-2 bg-gray-50 dark:bg-gray-800 text-sm text-gray-600 dark:text-gray-400 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ selectedPart ? 'Diambil dari Master Part' : 'Pilih part terlebih dahulu' }}
                                </div>

                                <!-- OK Images Grid -->
                                <div v-if="okImagePreviews.length > 0" class="mt-3 grid grid-cols-3 gap-2">
                                    <div
                                        v-for="(preview, index) in okImagePreviews"
                                        :key="index"
                                        class="relative"
                                    >
                                        <img
                                            :src="preview"
                                            class="w-full h-24 object-cover rounded border-2"
                                            :class="index === 0 ? 'border-green-600' : 'border-green-300'"
                                        />
                                        <div class="absolute top-1 left-1 bg-green-600 text-white text-[10px] px-2 py-0.5 rounded font-bold">
                                            {{ index === 0 ? 'Utama' : `#${index + 1}` }}
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="mt-3 w-full h-48 bg-gray-100 dark:bg-gray-800 rounded-lg border-2 border-dashed border-green-300 flex flex-col items-center justify-center">
                                    <ImageIcon class="w-12 h-12 text-green-400 mb-2" />
                                    <p class="text-sm text-gray-600 dark:text-gray-400 text-center px-4">
                                        {{ form.part_id ? 'Part ini belum ada foto' : 'Foto standar dari Master Part' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Keterangan Masalah</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            placeholder="Jelaskan detail masalah yang ditemukan pada produk NG..."
                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Dilaporkan Oleh *</label>
                        <input
                            v-model="form.reported_by"
                            type="text"
                            required
                            placeholder="Nama pelapor"
                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                        />
                    </div>

                    <div class="flex gap-2 justify-end pt-4 border-t border-sidebar-border">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2 border border-sidebar-border rounded-md hover:bg-gray-100 dark:hover:bg-sidebar-accent"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                        >
                            <svg v-if="form.processing" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ form.processing ? 'Menyimpan...' : '📋 Simpan Laporan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Image Gallery Modal -->
        <div v-if="showImageModal" class="fixed inset-0 bg-black/95 flex items-center justify-center z-50 p-4">
            <div class="relative max-w-6xl w-full">
                <button
                    @click="showImageModal = false"
                    class="absolute -top-12 right-0 text-white hover:text-gray-300 flex items-center gap-2 z-10"
                >
                    <span class="text-sm">Tutup</span>
                    <X class="w-8 h-8" />
                </button>

                <div class="relative">
                    <img
                        :src="selectedImages[currentImageIndex]"
                        class="w-full h-auto max-h-[80vh] object-contain rounded-lg"
                    />

                    <button
                        v-if="currentImageIndex > 0"
                        @click="prevImage"
                        class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-white text-black rounded-full p-3 shadow-lg"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <button
                        v-if="currentImageIndex < selectedImages.length - 1"
                        @click="nextImage"
                        class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-white text-black rounded-full p-3 shadow-lg"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>

                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-black/70 text-white px-4 py-2 rounded-full text-sm font-semibold">
                        {{ currentImageIndex + 1 }} / {{ selectedImages.length }}
                    </div>
                </div>

                <div v-if="selectedImages.length > 1" class="flex gap-2 justify-center mt-4 overflow-x-auto pb-2">
                    <img
                        v-for="(image, index) in selectedImages"
                        :key="index"
                        :src="image"
                        @click="currentImageIndex = index"
                        class="w-20 h-20 object-cover rounded cursor-pointer border-2 transition-all flex-shrink-0"
                        :class="index === currentImageIndex ? 'border-blue-500 scale-110' : 'border-white/30 opacity-60 hover:opacity-100'"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
