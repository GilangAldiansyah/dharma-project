<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Package, Plus, Pencil, Trash2, X, Search } from 'lucide-vue-next';

interface Sparepart { id: number; name: string; satuan: string; }
interface Props { spareparts: Sparepart[]; }

const props = defineProps<Props>();

const search = ref('');
const showModal = ref(false);
const editTarget = ref<Sparepart | null>(null);

const form = useForm({ name: '', satuan: '' });

const filtered = () => {
    if (!search.value) return props.spareparts;
    const q = search.value.toLowerCase();
    return props.spareparts.filter(s => s.name.toLowerCase().includes(q) || s.satuan.toLowerCase().includes(q));
};

const openCreate = () => { editTarget.value = null; form.reset(); showModal.value = true; };
const openEdit = (sp: Sparepart) => { editTarget.value = sp; form.name = sp.name; form.satuan = sp.satuan; showModal.value = true; };
const closeModal = () => { showModal.value = false; form.reset(); editTarget.value = null; };

const submit = () => {
    if (editTarget.value) {
        form.put(`/jig/sparepart/${editTarget.value.id}`, { onSuccess: closeModal });
    } else {
        form.post('/jig/sparepart', { onSuccess: closeModal });
    }
};

const destroy = (id: number) => {
    if (confirm('Yakin ingin menghapus sparepart ini?')) router.delete(`/jig/sparepart/${id}`);
};
</script>

<template>
    <Head title="Master Sparepart" />
    <AppLayout :breadcrumbs="[{ title: 'JIG', href: '/jig/dashboard' }, { title: 'Master Sparepart', href: '/jig/sparepart' }]">
        <div class="p-6 space-y-6 bg-white dark:bg-gray-900 min-h-screen">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent flex items-center gap-3">
                        <Package class="w-8 h-8 text-indigo-600" /> Master Sparepart
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola data master sparepart JIG</p>
                </div>
                <button @click="openCreate"
                    class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-500 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium">
                    <Plus class="w-4 h-4" /> Tambah Sparepart
                </button>
            </div>

            <div class="relative max-w-sm">
                <input v-model="search" type="text" placeholder="Cari sparepart..."
                    class="w-full rounded-xl border-2 border-gray-200 dark:border-gray-700 pl-10 pr-3 py-2.5 dark:bg-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all text-sm" />
                <Search class="absolute left-3 top-3 w-4 h-4 text-gray-400" />
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-gray-700 dark:to-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">No</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Nama Sparepart</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Satuan</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-if="filtered().length === 0">
                                <td colspan="4" class="py-16 text-center text-gray-400 text-sm">Tidak ada data sparepart</td>
                            </tr>
                            <tr v-for="(sp, i) in filtered()" :key="sp.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-4 py-3 text-xs text-gray-500">{{ i + 1 }}</td>
                                <td class="px-4 py-3 text-xs font-semibold text-gray-900 dark:text-white">{{ sp.name }}</td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ sp.satuan }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        <button @click="openEdit(sp)" class="p-1.5 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition-colors"><Pencil class="w-4 h-4" /></button>
                                        <button @click="destroy(sp.id)" class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"><Trash2 class="w-4 h-4" /></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 backdrop-blur-sm bg-white/30 dark:bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full shadow-2xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ editTarget ? 'Edit Sparepart' : 'Tambah Sparepart' }}</h2>
                    <button @click="closeModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Nama Sparepart</label>
                        <input v-model="form.name" type="text" required placeholder="Nama sparepart"
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all text-sm" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Satuan</label>
                        <input v-model="form.satuan" type="text" required placeholder="cth: pcs, set, meter"
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all text-sm" />
                        <p v-if="form.errors.satuan" class="mt-1 text-xs text-red-600">{{ form.errors.satuan }}</p>
                    </div>
                    <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit" :disabled="form.processing"
                            class="flex-1 px-6 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-500 text-white rounded-xl hover:shadow-lg disabled:opacity-50 transition-all duration-300 font-medium">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                        <button type="button" @click="closeModal"
                            class="px-6 py-2.5 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all duration-300 font-medium">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
