<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Search, Package, Camera, AlertCircle } from 'lucide-vue-next';

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

const searchQuery = ref('');
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

const searchMaterial = async () => {
    if (!searchQuery.value) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(`/materials/search/api?query=${searchQuery.value}`);
        searchResults.value = await response.json();
    } catch (error) {
        console.error('Search error:', error);
    } finally {
        isSearching.value = false;
    }
};

const selectMaterial = (material: Material) => {
    selectedMaterial.value = material;
    form.material_id = material.id;

    // Auto select part if only one
    if (material.part_materials.length === 1) {
        form.part_material_id = material.part_materials[0].id;
    } else {
        form.part_material_id = null;
    }

    searchResults.value = [];
    searchQuery.value = '';
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files || []);

    if (files.length + form.foto.length > 5) {
        alert('Maksimal 5 foto');
        return;
    }

    form.foto = [...form.foto, ...files].slice(0, 5);

    // Generate previews
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
    form.post(route('transaksi.store'), {
        onSuccess: () => {
            form.reset();
            selectedMaterial.value = null;
            previewImages.value = [];
        },
    });
};

watch(searchQuery, () => {
    if (searchQuery.value.length >= 2) {
        searchMaterial();
    } else {
        searchResults.value = [];
    }
});
</script>

<template>
    <Head title="Input Transaksi" />
    <AppLayout :breadcrumbs="[
        { title: 'Transaksi', href: '/transaksi' },
        { title: 'Input Baru', href: '/transaksi/create' }
    ]">
        <div class="p-6 max-w-4xl mx-auto">
            <h1 class="text-2xl font-bold mb-6">Input Pengambilan Material</h1>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Search Material -->
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
                    <label class="block text-sm font-medium mb-2">Cari Material</label>
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Ketik ID atau nama material..."
                            class="w-full px-4 py-2 border border-sidebar-border rounded-md dark:bg-sidebar"
                        />
                        <Search class="absolute right-3 top-2.5 w-5 h-5 text-gray-400" />
                    </div>

                    <!-- Search Results -->
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

                    <!-- Selected Material -->
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

                        <!-- Part Selection (if multiple) -->
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

                <!-- Tanggal & Shift -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
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

                    <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
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

                <!-- QTY -->
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
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

                <!-- Foto Upload -->
                <div class="bg-white dark:bg-sidebar border border-sidebar-border rounded-lg p-6">
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

                    <!-- Image Previews -->
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

                <!-- Submit -->
                <div class="flex gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing || !selectedMaterial"
                        class="flex-1 px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Transaksi' }}
                    </button>
                    <button
                        type="button"
                        @click="router.visit('/transaksi')"
                        class="px-6 py-3 bg-gray-600 text-white rounded-md hover:bg-gray-700"
                    >
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
