<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Edit, Trash2, Search } from 'lucide-vue-next';

interface Material {
    id: number;
    material_code: string;
    material_name: string;
    specification: string;
    thickness: number;
    width: number;
    unit: string;
}

interface Props {
    materials: {
        data: Material[];
        current_page: number;
        last_page: number;
    };
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps<Props>();

const showModal = ref(false);
const editMode = ref(false);
const searchQuery = ref('');

const form = useForm({
    id: 0,
    material_code: '',
    material_name: '',
    specification: '',
    thickness: 0,
    width: 0,
    unit: 'COIL',
});

const openModal = (material?: Material) => {
    if (material) {
        editMode.value = true;
        form.id = material.id;
        form.material_code = material.material_code;
        form.material_name = material.material_name;
        form.specification = material.specification;
        form.thickness = material.thickness;
        form.width = material.width;
        form.unit = material.unit;
    } else {
        editMode.value = false;
        form.reset();
        form.unit = 'COIL';
    }
    showModal.value = true;
};

const submit = () => {
    if (editMode.value) {
        form.put(`/materials/${form.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post('/materials', {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    }
};

const deleteMaterial = (id: number) => {
    if (confirm('Are you sure you want to delete this material?')) {
        router.delete(`/materials/${id}`, {
            preserveScroll: true,
        });
    }
};

const search = () => {
    router.get('/materials', { search: searchQuery.value }, { preserveState: true });
};
</script>

<template>
    <Head title="Master Materials" />
    <AppLayout :breadcrumbs="[{ title: 'Master Materials', href: '/materials' }]">
        <div class="p-4 space-y-4">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div class="flex gap-2 items-center flex-1 max-w-md">
                    <input
                        v-model="searchQuery"
                        @keyup.enter="search"
                        type="text"
                        placeholder="Search material code or name..."
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
                    Add Material
                </button>
            </div>

            <!-- Materials Table -->
            <div class="border border-sidebar-border rounded-lg overflow-hidden bg-white dark:bg-sidebar">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Material Code</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Material Name</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Specification</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Thickness</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Width</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Unit</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="material in materials.data"
                                :key="material.id"
                                class="border-b border-sidebar-border hover:bg-gray-50 dark:hover:bg-sidebar-accent/50"
                            >
                                <td class="px-4 py-3 text-sm font-medium">{{ material.material_code }}</td>
                                <td class="px-4 py-3 text-sm">{{ material.material_name }}</td>
                                <td class="px-4 py-3 text-sm">{{ material.specification || '-' }}</td>
                                <td class="px-4 py-3 text-center text-sm">{{ material.thickness || '-' }}</td>
                                <td class="px-4 py-3 text-center text-sm">{{ material.width || '-' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                        {{ material.unit }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            @click="openModal(material)"
                                            class="p-1 text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-900 rounded"
                                            title="Edit"
                                        >
                                            <Edit class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deleteMaterial(material.id)"
                                            class="p-1 text-red-600 hover:bg-red-100 dark:hover:bg-red-900 rounded"
                                            title="Delete"
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

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-sidebar rounded-lg max-w-2xl w-full p-6">
                <h2 class="text-xl font-semibold mb-4">{{ editMode ? 'Edit' : 'Add' }} Material</h2>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Material Code *</label>
                            <input
                                v-model="form.material_code"
                                type="text"
                                required
                                placeholder="02C3908COIL0298RAW"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Unit *</label>
                            <select
                                v-model="form.unit"
                                required
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            >
                                <option value="COIL">COIL</option>
                                <option value="PCS">PCS</option>
                                <option value="KG">KG</option>
                                <option value="M">M</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Material Name *</label>
                        <input
                            v-model="form.material_name"
                            type="text"
                            required
                            placeholder="SCGA270C-45 0.8 x 298 x COIL"
                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Specification</label>
                        <input
                            v-model="form.specification"
                            type="text"
                            placeholder="SCGA270C-45"
                            class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Thickness (mm)</label>
                            <input
                                v-model.number="form.thickness"
                                type="number"
                                step="0.01"
                                placeholder="0.8"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Width (mm)</label>
                            <input
                                v-model.number="form.width"
                                type="number"
                                step="0.01"
                                placeholder="298"
                                class="w-full rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent"
                            />
                        </div>
                    </div>

                    <div class="flex gap-2 justify-end pt-4">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2 border rounded-md hover:bg-gray-100 dark:hover:bg-sidebar-accent"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ editMode ? 'Update' : 'Save' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
