<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Trash2, Upload, X } from 'lucide-vue-next';

interface DiePart {
    id: number;
    part_no: string;
    part_name: string;
}

interface Sparepart {
    sparepart_name: string;
    sparepart_code: string;
    quantity: number;
    notes: string;
}

interface DieShopReport {
    id: number;
    report_no: string;
    activity_type: 'corrective' | 'preventive';
    pic_name: string;
    report_date: string;
    die_part_id: number;
    repair_process: string;
    problem_description: string;
    cause: string;
    repair_action: string;
    photos: string[] | null;
    status: 'pending' | 'in_progress' | 'completed';
    spareparts: {
        sparepart_name: string;
        sparepart_code: string | null;
        quantity: number;
        notes: string | null;
    }[];
}

interface Props {
    report: DieShopReport;
    dieParts: DiePart[];
}

const props = defineProps<Props>();

const formData = ref({
    activity_type: props.report.activity_type,
    pic_name: props.report.pic_name,
    report_date: props.report.report_date,
    die_part_id: props.report.die_part_id,
    repair_process: props.report.repair_process || '',
    problem_description: props.report.problem_description || '',
    cause: props.report.cause || '',
    repair_action: props.report.repair_action || '',
    status: props.report.status,
    spareparts: props.report.spareparts.map(sp => ({
        sparepart_name: sp.sparepart_name,
        sparepart_code: sp.sparepart_code || '',
        quantity: sp.quantity,
        notes: sp.notes || '',
    })) as Sparepart[],
});

const existingPhotos = ref<string[]>(props.report.photos || []);
const newPhotos = ref<File[]>([]);
const newPhotoPreview = ref<string[]>([]);
const processing = ref(false);

const addSparepart = () => {
    formData.value.spareparts.push({
        sparepart_name: '',
        sparepart_code: '',
        quantity: 1,
        notes: '',
    });
};

const removeSparepart = (index: number) => {
    formData.value.spareparts.splice(index, 1);
};

const handlePhotoUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = target.files;

    if (files) {
        Array.from(files).forEach(file => {
            newPhotos.value.push(file);

            const reader = new FileReader();
            reader.onload = (e) => {
                newPhotoPreview.value.push(e.target?.result as string);
            };
            reader.readAsDataURL(file);
        });
    }
};

const removeNewPhoto = (index: number) => {
    newPhotos.value.splice(index, 1);
    newPhotoPreview.value.splice(index, 1);
};

const removeExistingPhoto = (index: number) => {
    existingPhotos.value.splice(index, 1);
};

const submit = () => {
    processing.value = true;

    const data = new FormData();

    // Add form fields
    data.append('activity_type', formData.value.activity_type);
    data.append('pic_name', formData.value.pic_name);
    data.append('report_date', formData.value.report_date);
    data.append('die_part_id', formData.value.die_part_id.toString());
    data.append('repair_process', formData.value.repair_process);
    data.append('problem_description', formData.value.problem_description);
    data.append('cause', formData.value.cause);
    data.append('repair_action', formData.value.repair_action);
    data.append('status', formData.value.status);
    data.append('_method', 'PUT');

    // Add existing photos
    existingPhotos.value.forEach((photo, index) => {
        data.append(`existing_photos[${index}]`, photo);
    });

    // Add new photos
    newPhotos.value.forEach((photo) => {
        data.append('photos[]', photo);
    });

    // Add spareparts
    formData.value.spareparts.forEach((sparepart, index) => {
        data.append(`spareparts[${index}][sparepart_name]`, sparepart.sparepart_name);
        data.append(`spareparts[${index}][sparepart_code]`, sparepart.sparepart_code);
        data.append(`spareparts[${index}][quantity]`, sparepart.quantity.toString());
        data.append(`spareparts[${index}][notes]`, sparepart.notes);
    });

    router.post(`/die-shop-reports/${props.report.id}`, data, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Edit ${report.report_no}`" />
    <AppLayout :breadcrumbs="[
        { title: 'Die Shop System', href: '#' },
        { title: 'Laporan Perbaikan', href: '/die-shop-reports' },
        { title: report.report_no, href: `/die-shop-reports/${report.id}` },
        { title: 'Edit', href: `/die-shop-reports/${report.id}/edit` }
    ]">
        <div class="p-4">
            <div class="max-w-4xl mx-auto">
                <h1 class="text-2xl font-bold mb-6">Edit Laporan - {{ report.report_no }}</h1>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                        <h2 class="text-lg font-semibold mb-4">Informasi Dasar</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Jenis Aktivitas *</label>
                                <select
                                    v-model="formData.activity_type"
                                    required
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                >
                                    <option value="corrective">Corrective</option>
                                    <option value="preventive">Preventive</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Tanggal Laporan *</label>
                                <input
                                    v-model="formData.report_date"
                                    type="date"
                                    required
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Nama PIC *</label>
                                <input
                                    v-model="formData.pic_name"
                                    type="text"
                                    required
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Die Part *</label>
                                <select
                                    v-model="formData.die_part_id"
                                    required
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                >
                                    <option value="">Pilih Die Part</option>
                                    <option v-for="part in dieParts" :key="part.id" :value="part.id">
                                        {{ part.part_no }} - {{ part.part_name }}
                                    </option>
                                </select>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium mb-1">Status *</label>
                                <select
                                    v-model="formData.status"
                                    required
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                >
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">Dalam Proses</option>
                                    <option value="completed">Selesai</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Problem Details -->
                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                        <h2 class="text-lg font-semibold mb-4">Detail Permasalahan & Perbaikan</h2>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Proses Perbaikan</label>
                                <textarea
                                    v-model="formData.repair_process"
                                    rows="3"
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                    placeholder="Opsional - diisi jika sudah ada proses perbaikan"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Deskripsi Permasalahan</label>
                                <textarea
                                    v-model="formData.problem_description"
                                    rows="3"
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                    placeholder="Opsional - diisi jika sudah teridentifikasi"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Penyebab</label>
                                <textarea
                                    v-model="formData.cause"
                                    rows="3"
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                    placeholder="Opsional - diisi setelah analisa"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Tindakan Perbaikan</label>
                                <textarea
                                    v-model="formData.repair_action"
                                    rows="3"
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                    placeholder="Opsional - diisi setelah perbaikan dilakukan"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Photos -->
                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                        <h2 class="text-lg font-semibold mb-4">Foto Dokumentasi</h2>

                        <div class="space-y-4">
                            <!-- Existing Photos -->
                            <div v-if="existingPhotos.length > 0">
                                <label class="block text-sm font-medium mb-2">Foto Existing</label>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <div
                                        v-for="(photo, index) in existingPhotos"
                                        :key="`existing-${index}`"
                                        class="relative group"
                                    >
                                        <img
                                            :src="`/storage/${photo}`"
                                            class="w-full h-32 object-cover rounded-md border border-sidebar-border"
                                        />
                                        <button
                                            type="button"
                                            @click="removeExistingPhoto(index)"
                                            class="absolute top-1 right-1 p-1 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                                        >
                                            <X class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Upload New Photos -->
                            <div>
                                <label class="block text-sm font-medium mb-2">Upload Foto Baru</label>
                                <div class="flex items-center gap-3">
                                    <label class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 cursor-pointer">
                                        <Upload class="w-4 h-4" />
                                        Pilih Foto
                                        <input
                                            type="file"
                                            accept="image/*"
                                            multiple
                                            @change="handlePhotoUpload"
                                            class="hidden"
                                        />
                                    </label>
                                    <span class="text-sm text-gray-500">Max 5MB per foto</span>
                                </div>
                            </div>

                            <!-- New Photo Preview -->
                            <div v-if="newPhotoPreview.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div
                                    v-for="(preview, index) in newPhotoPreview"
                                    :key="`new-${index}`"
                                    class="relative group"
                                >
                                    <img
                                        :src="preview"
                                        class="w-full h-32 object-cover rounded-md border border-sidebar-border"
                                    />
                                    <button
                                        type="button"
                                        @click="removeNewPhoto(index)"
                                        class="absolute top-1 right-1 p-1 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        <X class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Spareparts -->
                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold">Penggantian Sparepart</h2>
                            <button
                                type="button"
                                @click="addSparepart"
                                class="flex items-center gap-2 px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                            >
                                <Plus class="w-4 h-4" />
                                Tambah Sparepart
                            </button>
                        </div>

                        <div v-if="formData.spareparts.length === 0" class="text-center py-8 text-gray-500">
                            Belum ada sparepart yang ditambahkan
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="(sparepart, index) in formData.spareparts"
                                :key="index"
                                class="border border-sidebar-border rounded-md p-4 relative"
                            >
                                <button
                                    type="button"
                                    @click="removeSparepart(index)"
                                    class="absolute top-2 right-2 p-1 text-red-600 hover:bg-red-100 dark:hover:bg-red-900 rounded"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pr-8">
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Nama Sparepart *</label>
                                        <input
                                            v-model="sparepart.sparepart_name"
                                            type="text"
                                            required
                                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Kode Sparepart</label>
                                        <input
                                            v-model="sparepart.sparepart_code"
                                            type="text"
                                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Jumlah *</label>
                                        <input
                                            v-model.number="sparepart.quantity"
                                            type="number"
                                            required
                                            min="1"
                                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Catatan</label>
                                        <input
                                            v-model="sparepart.notes"
                                            type="text"
                                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 justify-end">
                        <button
                            type="button"
                            @click="router.visit(`/die-shop-reports/${report.id}`)"
                            class="px-6 py-2 border border-sidebar-border rounded-md hover:bg-gray-100 dark:hover:bg-sidebar-accent"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="processing"
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ processing ? 'Menyimpan...' : 'Update Laporan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
