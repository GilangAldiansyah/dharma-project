<!-- eslint-disable @typescript-eslint/no-unused-vars -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Edit, Trash2, Search } from 'lucide-vue-next';

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
const editMode = ref(false);
const searchQuery = ref('');

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
                <button
                    @click="openModal()"
                    class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                >
                    <Plus class="w-4 h-4" />
                    Tambah Supplier
                </button>
            </div>

            <div class="border border-sidebar-border rounded-lg overflow-hidden bg-white dark:bg-sidebar">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-sidebar-accent border-b border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Kode</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Nama Supplier</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Contact Person</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Telepon</th>
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
                                <td class="px-4 py-3 text-sm">{{ supplier.contact_person || '-' }}</td>
                                <td class="px-4 py-3 text-sm">{{ supplier.phone || '-' }}</td>
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
            </div>
        </div>

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
    </AppLayout>
</template>
