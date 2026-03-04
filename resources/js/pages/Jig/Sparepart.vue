<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Package, Plus, Pencil, Trash2, X, AlertTriangle, ArrowUp, History } from 'lucide-vue-next';

interface Sparepart { id: number; sap_id: string|null; name: string; satuan: string; stok: number; stok_minimum: number; }
interface Props {
    spareparts:   Sparepart[];
    lowStokCount: number;
    filters:      { search?: string; filter?: string };
}

const props = defineProps<Props>();
const showModal      = ref(false);
const showStokModal  = ref(false);
const editTarget     = ref<Sparepart|null>(null);
const stokTarget     = ref<Sparepart|null>(null);

const filterSearch = ref(props.filters.search ?? '');
const filterLow    = ref(props.filters.filter  ?? '');

let timer: any;
watch(filterSearch, () => { clearTimeout(timer); timer = setTimeout(() => applyFilter(), 400); });
watch(filterLow, () => applyFilter());
const applyFilter = () => router.get('/jig/sparepart', { search: filterSearch.value, filter: filterLow.value }, { preserveState: true, preserveScroll: true });

const form     = useForm({ sap_id: '', name: '', satuan: '', stok: 0, stok_minimum: 0 });
const stokForm = useForm({ qty: '' });

const openCreate = () => { editTarget.value = null; form.reset(); showModal.value = true; };
const openEdit   = (sp: Sparepart) => { editTarget.value = sp; form.sap_id = sp.sap_id ?? ''; form.name = sp.name; form.satuan = sp.satuan; form.stok = sp.stok; form.stok_minimum = sp.stok_minimum; showModal.value = true; };
const closeModal = () => { showModal.value = false; editTarget.value = null; form.reset(); };
const openStok   = (sp: Sparepart) => { stokTarget.value = sp; stokForm.reset(); showStokModal.value = true; };
const closeStok  = () => { showStokModal.value = false; stokTarget.value = null; stokForm.reset(); };

const submit = () => {
    if (editTarget.value) {
        form.put(`/jig/sparepart/${editTarget.value.id}`, { onSuccess: closeModal });
    } else {
        form.post('/jig/sparepart', { onSuccess: closeModal });
    }
};

const submitStok = () => {
    if (!stokTarget.value) return;
    stokForm.post(`/jig/sparepart/${stokTarget.value.id}/tambah-stok`, { onSuccess: closeStok });
};

const destroy = (sp: Sparepart) => {
    if (confirm(`Hapus sparepart "${sp.name}"?`)) router.delete(`/jig/sparepart/${sp.id}`, { preserveScroll: true });
};

const stokStatus = (sp: Sparepart) => {
    if (sp.stok <= 0) return { label: 'Habis', class: 'bg-red-100 text-red-700' };
    if (sp.stok <= sp.stok_minimum) return { label: 'Hampir Habis', class: 'bg-yellow-100 text-yellow-700' };
    return { label: 'Tersedia', class: 'bg-green-100 text-green-700' };
};
</script>

<template>
    <Head title="Master Sparepart" />
    <AppLayout :breadcrumbs="[{title:'JIG',href:'/jig/dashboard'},{title:'Sparepart',href:'/jig/sparepart'}]">
        <div class="p-4 sm:p-6 space-y-5">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Package class="w-6 h-6 text-indigo-600" /> Master Sparepart
                    </h1>
                    <p class="text-sm text-gray-500 mt-0.5">Kelola sparepart dan stok JIG</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="router.visit('/jig/sparepart/history')"
                        class="flex items-center gap-2 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors font-medium text-sm">
                        <History class="w-4 h-4" /> History
                    </button>
                    <button @click="openCreate"
                        class="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-colors font-medium text-sm">
                        <Plus class="w-4 h-4" /> Tambah Sparepart
                    </button>
                </div>
            </div>

            <div v-if="lowStokCount > 0" class="flex items-center gap-3 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl text-sm">
                <AlertTriangle class="w-5 h-5 text-yellow-600 flex-shrink-0" />
                <div>
                    <span class="font-bold text-yellow-800 dark:text-yellow-200">{{ lowStokCount }} sparepart</span>
                    <span class="text-yellow-700 dark:text-yellow-300"> stoknya di bawah minimum atau habis.</span>
                    <button @click="filterLow = 'low'" class="ml-2 underline text-yellow-700 font-semibold hover:text-yellow-900">Lihat</button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input v-model="filterSearch" type="text" placeholder="Cari nama / ID SAP..."
                    class="px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none w-52" />
                <button @click="filterLow = filterLow === 'low' ? '' : 'low'"
                    :class="['px-3 py-2 rounded-xl text-xs font-semibold transition-all border',
                        filterLow === 'low' ? 'bg-yellow-500 text-white border-yellow-500' : 'border-gray-200 text-gray-600 hover:bg-gray-50']">
                    ⚠ Stok Rendah
                </button>
                <span class="text-xs text-gray-400">{{ spareparts.length }} item</span>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">ID SAP</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Nama</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Satuan</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Stok</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Minimum</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="spareparts.length === 0">
                                <td colspan="7" class="py-14 text-center text-gray-400 text-sm">Belum ada sparepart</td>
                            </tr>
                            <tr v-for="sp in spareparts" :key="sp.id"
                                :class="['hover:bg-gray-50/80 transition-colors', sp.stok <= sp.stok_minimum && sp.stok_minimum > 0 ? 'bg-yellow-50/30' : '']">
                                <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ sp.sap_id ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs font-bold text-gray-900 dark:text-white">{{ sp.name }}</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ sp.satuan }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['text-sm font-black', sp.stok <= 0 ? 'text-red-600' : sp.stok <= sp.stok_minimum ? 'text-yellow-600' : 'text-gray-900 dark:text-white']">
                                        {{ Math.floor(sp.stok) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ Math.floor(sp.stok_minimum) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-bold', stokStatus(sp).class]">
                                        {{ stokStatus(sp).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        <button @click="openStok(sp)"
                                            class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Tambah Stok">
                                            <ArrowUp class="w-4 h-4" />
                                        </button>
                                        <button @click="openEdit(sp)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                            <Pencil class="w-4 h-4" />
                                        </button>
                                        <button @click="destroy(sp)" class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition-colors">
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

        <div v-if="showModal" class="fixed inset-0 backdrop-blur-sm bg-black/30 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full shadow-2xl">
                <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ editTarget ? 'Edit' : 'Tambah' }} Sparepart</h2>
                    <button @click="closeModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                </div>
                <form @submit.prevent="submit" class="p-5 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">ID SAP</label>
                        <input v-model="form.sap_id" placeholder="Opsional" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none font-mono" />
                        <p v-if="form.errors.sap_id" class="text-xs text-red-500 mt-1">{{ form.errors.sap_id }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Nama <span class="text-red-500">*</span></label>
                        <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none" />
                        <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Satuan <span class="text-red-500">*</span></label>
                        <input v-model="form.satuan" required placeholder="pcs, set, meter, dll" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Stok Awal</label>
                            <input v-model="form.stok" type="number" min="0" step="1" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Stok Minimum</label>
                            <input v-model="form.stok_minimum" type="number" min="0" step="1" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none" />
                            <p class="text-xs text-gray-400 mt-1">Alert jika stok ≤ nilai ini</p>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <button type="submit" :disabled="form.processing"
                            class="flex-1 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 disabled:opacity-50 font-semibold text-sm">
                            {{ form.processing ? 'Menyimpan...' : editTarget ? 'Update' : 'Simpan' }}
                        </button>
                        <button type="button" @click="closeModal" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 rounded-xl hover:bg-gray-200 font-medium text-sm">Batal</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showStokModal && stokTarget" class="fixed inset-0 backdrop-blur-sm bg-black/30 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-sm w-full shadow-2xl">
                <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tambah Stok</h2>
                    <button @click="closeStok" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                </div>
                <form @submit.prevent="submitStok" class="p-5 space-y-4">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3 text-xs">
                        <p class="font-bold text-gray-900 dark:text-white">{{ stokTarget.name }}</p>
                        <p v-if="stokTarget.sap_id" class="text-gray-400 font-mono">{{ stokTarget.sap_id }}</p>
                        <p class="text-gray-500 mt-0.5">Stok saat ini: <span class="font-bold text-indigo-600">{{ Math.floor(stokTarget.stok) }} {{ stokTarget.satuan }}</span></p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Jumlah Tambah <span class="text-red-500">*</span></label>
                        <input v-model="stokForm.qty" type="number" min="1" step="1" required placeholder="0"
                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none" />
                        <p v-if="stokForm.qty" class="text-xs text-gray-400 mt-1">
                            Stok setelah: <span class="font-bold text-green-600">{{ Math.floor(parseFloat(stokTarget.stok as any) + parseInt(stokForm.qty || '0')) }} {{ stokTarget.satuan }}</span>
                        </p>
                    </div>
                    <div class="flex gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <button type="submit" :disabled="stokForm.processing"
                            class="flex-1 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 disabled:opacity-50 font-semibold text-sm">
                            {{ stokForm.processing ? 'Menyimpan...' : 'Tambah Stok' }}
                        </button>
                        <button type="button" @click="closeStok" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 rounded-xl hover:bg-gray-200 font-medium text-sm">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
