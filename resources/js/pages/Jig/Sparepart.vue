<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Package, Plus, Pencil, Trash2, X, AlertTriangle, ArrowUp, History, Search } from 'lucide-vue-next';

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
    if (sp.stok <= 0) return { label: 'Habis', class: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' };
    if (sp.stok <= sp.stok_minimum) return { label: 'Hampir Habis', class: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' };
    return { label: 'Tersedia', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' };
};
</script>

<template>
    <Head title="Master Sparepart" />
    <AppLayout :breadcrumbs="[{title:'JIG',href:'/jig/dashboard'},{title:'Sparepart',href:'/jig/sparepart'}]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-indigo-600 rounded-xl flex-shrink-0">
                            <Package class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
                        </span>
                        Master Sparepart
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">Kelola sparepart dan stok JIG</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="router.visit('/jig/sparepart/history')"
                        class="flex items-center gap-1.5 px-3 py-2 sm:py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 active:scale-95 transition-all font-medium text-xs sm:text-sm">
                        <History class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                        <span class="hidden sm:inline">History</span>
                    </button>
                    <button @click="openCreate"
                        class="flex items-center gap-1.5 px-3 py-2 sm:px-4 sm:py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 active:scale-95 transition-all font-semibold text-xs sm:text-sm">
                        <Plus class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                        <span class="hidden sm:inline">Tambah Sparepart</span>
                        <span class="sm:hidden">Tambah</span>
                    </button>
                </div>
            </div>

            <div v-if="lowStokCount > 0"
                class="flex items-center gap-3 p-3 sm:p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                <AlertTriangle class="w-4 h-4 sm:w-5 sm:h-5 text-amber-600 flex-shrink-0" />
                <div class="flex-1 min-w-0">
                    <span class="font-bold text-amber-800 dark:text-amber-200 text-xs sm:text-sm">{{ lowStokCount }} sparepart</span>
                    <span class="text-amber-700 dark:text-amber-300 text-xs sm:text-sm"> stoknya di bawah minimum atau habis.</span>
                </div>
                <button @click="filterLow = 'low'"
                    class="text-xs text-amber-700 dark:text-amber-300 font-bold underline hover:text-amber-900 flex-shrink-0">
                    Lihat
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[160px] sm:flex-none">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                    <input v-model="filterSearch" type="text" placeholder="Cari nama / ID SAP..."
                        class="w-full sm:w-52 pl-8 pr-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none" />
                </div>
                <button @click="filterLow = filterLow === 'low' ? '' : 'low'"
                    :class="['flex items-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all border active:scale-95',
                        filterLow === 'low' ? 'bg-amber-500 text-white border-amber-500' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700']">
                    <AlertTriangle class="w-3.5 h-3.5" /> Stok Rendah
                </button>
                <span class="text-xs text-gray-400">{{ spareparts.length }} item</span>
            </div>

            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">ID SAP</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Nama</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Satuan</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Stok</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Minimum</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="spareparts.length === 0">
                                <td colspan="7" class="py-16 text-center text-gray-400 text-sm">
                                    <Package class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                    Belum ada sparepart
                                </td>
                            </tr>
                            <tr v-for="sp in spareparts" :key="sp.id"
                                :class="['hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors',
                                    sp.stok <= sp.stok_minimum && sp.stok_minimum > 0 ? 'bg-amber-50/40 dark:bg-amber-900/10' : '']">
                                <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ sp.sap_id ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs font-bold text-gray-900 dark:text-white">{{ sp.name }}</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ sp.satuan }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['text-sm font-black', sp.stok <= 0 ? 'text-red-600' : sp.stok <= sp.stok_minimum ? 'text-amber-600' : 'text-gray-900 dark:text-white']">
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
                                            class="p-1.5 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg transition-colors" title="Tambah Stok">
                                            <ArrowUp class="w-4 h-4" />
                                        </button>
                                        <button @click="openEdit(sp)"
                                            class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors">
                                            <Pencil class="w-4 h-4" />
                                        </button>
                                        <button @click="destroy(sp)"
                                            class="p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="lg:hidden space-y-2.5">
                <div v-if="spareparts.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <Package class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                    <p class="text-gray-400 text-sm">Belum ada sparepart</p>
                </div>
                <div v-for="sp in spareparts" :key="sp.id"
                    :class="['bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden border-l-4',
                        sp.stok <= 0 ? 'border-l-red-400' : sp.stok <= sp.stok_minimum && sp.stok_minimum > 0 ? 'border-l-amber-400' : 'border-l-emerald-400']">
                    <div class="p-3.5">
                        <div class="flex items-start justify-between gap-2 mb-2.5">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-900 dark:text-white leading-tight">{{ sp.name }}</p>
                                <p v-if="sp.sap_id" class="text-xs text-gray-400 font-mono mt-0.5">{{ sp.sap_id }}</p>
                            </div>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-bold flex-shrink-0', stokStatus(sp).class]">
                                {{ stokStatus(sp).label }}
                            </span>
                        </div>

                        <div class="grid grid-cols-3 gap-2 mb-3">
                            <div class="text-center bg-gray-50 dark:bg-gray-700/50 rounded-xl p-2.5">
                                <p class="text-xs text-gray-400 mb-0.5">Stok</p>
                                <p :class="['text-lg font-black', sp.stok <= 0 ? 'text-red-600' : sp.stok <= sp.stok_minimum ? 'text-amber-600' : 'text-gray-900 dark:text-white']">
                                    {{ Math.floor(sp.stok) }}
                                </p>
                                <p class="text-xs text-gray-400">{{ sp.satuan }}</p>
                            </div>
                            <div class="text-center bg-gray-50 dark:bg-gray-700/50 rounded-xl p-2.5">
                                <p class="text-xs text-gray-400 mb-0.5">Minimum</p>
                                <p class="text-lg font-black text-gray-500 dark:text-gray-400">{{ Math.floor(sp.stok_minimum) }}</p>
                                <p class="text-xs text-gray-400">{{ sp.satuan }}</p>
                            </div>
                            <div class="text-center bg-gray-50 dark:bg-gray-700/50 rounded-xl p-2.5">
                                <p class="text-xs text-gray-400 mb-0.5">Satuan</p>
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-300 mt-1">{{ sp.satuan }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-2.5 border-t border-gray-100 dark:border-gray-700">
                            <button @click="openStok(sp)"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 active:scale-95 transition-all">
                                <ArrowUp class="w-3.5 h-3.5" /> Tambah Stok
                            </button>
                            <button @click="openEdit(sp)"
                                class="flex items-center justify-center p-2 bg-blue-50 dark:bg-blue-900/20 text-blue-600 rounded-xl hover:bg-blue-100 active:scale-95 transition-all">
                                <Pencil class="w-4 h-4" />
                            </button>
                            <button @click="destroy(sp)"
                                class="flex items-center justify-center p-2 bg-red-50 dark:bg-red-900/20 text-red-500 rounded-xl hover:bg-red-100 active:scale-95 transition-all">
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ editTarget ? 'Edit' : 'Tambah' }} Sparepart</h2>
                    <button @click="closeModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <form @submit.prevent="submit" class="p-4 sm:p-5 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">ID SAP</label>
                        <input v-model="form.sap_id" placeholder="Opsional"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none font-mono transition-colors" />
                        <p v-if="form.errors.sap_id" class="text-xs text-red-500 mt-1">{{ form.errors.sap_id }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Nama <span class="text-red-500">*</span></label>
                        <input v-model="form.name" required
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none transition-colors" />
                        <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Satuan <span class="text-red-500">*</span></label>
                        <input v-model="form.satuan" required placeholder="pcs, set, meter, dll"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none transition-colors" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Stok Awal</label>
                            <input v-model="form.stok" type="number" inputmode="numeric" min="0" step="1"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none transition-colors" />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Stok Minimum</label>
                            <input v-model="form.stok_minimum" type="number" inputmode="numeric" min="0" step="1"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none transition-colors" />
                            <p class="text-xs text-gray-400 mt-1">Alert jika stok ≤ nilai ini</p>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-2 pb-safe border-t border-gray-100 dark:border-gray-700">
                        <button type="button" @click="closeModal"
                            class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button type="submit" :disabled="form.processing"
                            class="flex-1 py-3 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 disabled:opacity-50 font-bold text-sm active:scale-95 transition-all">
                            {{ form.processing ? 'Menyimpan...' : editTarget ? 'Update' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showStokModal && stokTarget"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <ArrowUp class="w-4 h-4 text-emerald-600" /> Tambah Stok
                    </h2>
                    <button @click="closeStok" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <form @submit.prevent="submitStok" class="p-4 sm:p-5 space-y-4">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3.5">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ stokTarget.name }}</p>
                        <p v-if="stokTarget.sap_id" class="text-xs text-gray-400 font-mono mt-0.5">{{ stokTarget.sap_id }}</p>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="text-xs text-gray-500">Stok saat ini:</span>
                            <span class="text-sm font-black text-indigo-600">{{ Math.floor(stokTarget.stok) }} {{ stokTarget.satuan }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Jumlah Tambah <span class="text-red-500">*</span></label>
                        <input v-model="stokForm.qty" type="number" inputmode="numeric" min="1" step="1" required placeholder="0"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-emerald-500 focus:outline-none text-center text-lg font-bold transition-colors" />
                        <div v-if="stokForm.qty" class="flex items-center justify-between mt-2 p-2.5 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl">
                            <span class="text-xs text-gray-500">Stok setelah:</span>
                            <span class="text-sm font-black text-emerald-600">
                                {{ Math.floor(parseFloat(stokTarget.stok as any) + parseInt(stokForm.qty || '0')) }} {{ stokTarget.satuan }}
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-3 pb-safe border-t border-gray-100 dark:border-gray-700 pt-3">
                        <button type="button" @click="closeStok"
                            class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button type="submit" :disabled="stokForm.processing"
                            class="flex-1 py-3 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 disabled:opacity-50 font-bold text-sm active:scale-95 transition-all">
                            {{ stokForm.processing ? 'Menyimpan...' : 'Tambah Stok' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AppLayout>
</template>
