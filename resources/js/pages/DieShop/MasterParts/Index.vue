<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Edit, Trash2, Search, Package } from 'lucide-vue-next';

interface DiePart {
    id: number;
    part_no: string;
    part_name: string;
    description: string | null;
    location: string | null;
    status: 'active' | 'inactive';
}

interface Props {
    dieParts: {
        data: DiePart[];
        current_page: number;
        last_page: number;
    };
    filters: {
        search?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

const showModal = ref(false);
const editMode = ref(false);
const searchQuery = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');

const form = useForm({
    id: 0,
    part_no: '',
    part_name: '',
    description: '',
    location: '',
    status: 'active' as 'active' | 'inactive',
});

const openModal = (diePart?: DiePart) => {
    if (diePart) {
        editMode.value = true;
        form.id = diePart.id;
        form.part_no = diePart.part_no;
        form.part_name = diePart.part_name;
        form.description = diePart.description || '';
        form.location = diePart.location || '';
        form.status = diePart.status;
    } else {
        editMode.value = false;
        form.reset();
        form.status = 'active';
    }
    showModal.value = true;
};

const submit = () => {
    if (editMode.value) {
        form.put(`/die-parts/${form.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post('/die-parts', {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    }
};

const deleteDiePart = (id: number) => {
    if (confirm('Yakin hapus die part ini?')) {
        router.delete(`/die-parts/${id}`, { preserveScroll: true });
    }
};

const search = () => {
    router.get('/die-parts', {
        search: searchQuery.value,
        status: statusFilter.value
    }, { preserveState: true });
};
</script>

<template>
    <Head title="Master Die Parts" />
    <AppLayout :breadcrumbs="[
        { title: 'Master Die Parts', href: '/die-parts' }
    ]">
        <div class="p-4 space-y-4">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Package class="w-6 h-6 text-blue-600" />
                    Master Die Parts
                </h1>
                <button
                    @click="openModal()"
                    class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                >
                    <Plus class="w-4 h-4" />
                    Tambah Die Part
                </button>
            </div>

            <!-- Filters -->
            <div class="flex gap-2 items-center">
                <div class="flex-1 max-w-md flex gap-2">
                    <input
                        v-model="searchQuery"
                        @keyup.enter="search"
                        type="text"
                        placeholder="Cari part no atau nama part..."
                        class="flex-1 rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                    />
                    <select
                        v-model="statusFilter"
                        @change="search"
                        class="rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                    >
                        <option value="">Semua Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <button @click="search" class="p-2 bg-sidebar hover:bg-sidebar-accent rounded-md">
                        <Search class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="border border-sidebar-border rounded-lg overflow-hidden bg-white dark:bg-sidebar">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Part No</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Nama Part</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Lokasi</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Deskripsi</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Status</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="part in dieParts.data"
                                :key="part.id"
                                class="border-b border-sidebar-border hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                            >
                                <td class="px-4 py-3 text-sm font-medium">{{ part.part_no }}</td>
                                <td class="px-4 py-3 text-sm">{{ part.part_name }}</td>
                                <td class="px-4 py-3 text-sm">{{ part.location || '-' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="max-w-xs truncate" :title="part.description || '-'">
                                        {{ part.description || '-' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="[
                                        'inline-flex px-2 py-1 rounded-full text-xs font-semibold',
                                        part.status === 'active'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                    ]">
                                        {{ part.status === 'active' ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            @click="openModal(part)"
                                            class="p-1 text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-900 rounded"
                                        >
                                            <Edit class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deleteDiePart(part.id)"
                                            class="p-1 text-red-600 hover:bg-red-100 dark:hover:bg-red-900 rounded"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="dieParts.data.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    Tidak ada data die part
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-2xl w-full p-6">
                <h2 class="text-xl font-semibold mb-4">{{ editMode ? 'Edit' : 'Tambah' }} Die Part</h2>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Part No *</label>
                            <input
                                v-model="form.part_no"
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

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Lokasi</label>
                            <input
                                v-model="form.location"
                                type="text"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Status *</label>
                            <select
                                v-model="form.status"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
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
                            {{ editMode ? 'Update' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
