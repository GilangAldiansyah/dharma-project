<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
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

interface Props {
    dieParts: DiePart[];
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps<Props>();

const form = useForm({
    activity_type: 'corrective',
    pic_name: '',
    report_date: new Date().toISOString().split('T')[0],
    die_part_id: '',
    repair_process: '',
    problem_description: '',
    cause: '',
    repair_action: '',
    photos: [] as File[],
    status: 'pending',
    spareparts: [] as Sparepart[],
});

const photoPreview = ref<string[]>([]);

const addSparepart = () => {
    form.spareparts.push({
        sparepart_name: '',
        sparepart_code: '',
        quantity: 1,
        notes: '',
    });
};

const removeSparepart = (index: number) => {
    form.spareparts.splice(index, 1);
};

const handlePhotoUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = target.files;

    if (files) {
        Array.from(files).forEach(file => {
            form.photos.push(file);

            const reader = new FileReader();
            reader.onload = (e) => {
                photoPreview.value.push(e.target?.result as string);
            };
            reader.readAsDataURL(file);
        });
    }
};

const removePhoto = (index: number) => {
    form.photos.splice(index, 1);
    photoPreview.value.splice(index, 1);
};

const submit = () => {
    form.post('/die-shop-reports', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Buat Laporan Die Shop" />
    <AppLayout :breadcrumbs="[
        { title: 'Die Shop System', href: '#' },
        { title: 'Laporan Perbaikan', href: '/die-shop-reports' },
        { title: 'Buat Laporan', href: '/die-shop-reports/create' }
    ]">
        <div class="p-4">
            <div class="max-w-4xl mx-auto">
                <h1 class="text-2xl font-bold mb-6">Buat Laporan Perbaikan Die</h1>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                        <h2 class="text-lg font-semibold mb-4">Informasi Dasar</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Jenis Aktivitas *</label>
                                <select
                                    v-model="form.activity_type"
                                    required
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                >
                                    <option value="corrective">Corrective</option>
                                    <option value="preventive">Preventive</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    Corrective: Perbaikan akibat kerusakan | Preventive: Perawatan berkala
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Tanggal Laporan *</label>
                                <input
                                    v-model="form.report_date"
                                    type="date"
                                    required
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Nama PIC *</label>
                                <input
                                    v-model="form.pic_name"
                                    type="text"
                                    required
                                    placeholder="Nama penanggung jawab"
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Die Part *</label>
                                <select
                                    v-model="form.die_part_id"
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
                                    v-model="form.status"
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
                        <p class="text-sm text-gray-500 mb-4">
                            Field berikut bersifat opsional dan bisa diisi nanti setelah laporan dibuat
                        </p>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Proses Perbaikan</label>
                                <textarea
                                    v-model="form.repair_process"
                                    rows="3"
                                    placeholder="Jelaskan proses perbaikan yang dilakukan... (Opsional)"
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Deskripsi Permasalahan</label>
                                <textarea
                                    v-model="form.problem_description"
                                    rows="3"
                                    placeholder="Jelaskan masalah yang ditemukan... (Opsional)"
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Penyebab</label>
                                <textarea
                                    v-model="form.cause"
                                    rows="3"
                                    placeholder="Jelaskan penyebab masalah... (Opsional)"
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Tindakan Perbaikan</label>
                                <textarea
                                    v-model="form.repair_action"
                                    rows="3"
                                    placeholder="Jelaskan tindakan perbaikan yang dilakukan... (Opsional)"
                                    class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Photos -->
                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                        <h2 class="text-lg font-semibold mb-4">Foto Dokumentasi</h2>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-2">Upload Foto</label>
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

                            <!-- Photo Preview -->
                            <div v-if="photoPreview.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div
                                    v-for="(preview, index) in photoPreview"
                                    :key="index"
                                    class="relative group"
                                >
                                    <img
                                        :src="preview"
                                        class="w-full h-32 object-cover rounded-md border border-sidebar-border"
                                    />
                                    <button
                                        type="button"
                                        @click="removePhoto(index)"
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

                        <div v-if="form.spareparts.length === 0" class="text-center py-8 text-gray-500">
                            Belum ada sparepart yang ditambahkan
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="(sparepart, index) in form.spareparts"
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
                                            placeholder="Nama sparepart"
                                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Kode Sparepart</label>
                                        <input
                                            v-model="sparepart.sparepart_code"
                                            type="text"
                                            placeholder="Kode/Part No"
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
                                            placeholder="Catatan tambahan"
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
                            @click="$inertia.visit('/die-shop-reports')"
                            class="px-6 py-2 border border-sidebar-border rounded-md hover:bg-gray-100 dark:hover:bg-sidebar-accent"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Laporan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
