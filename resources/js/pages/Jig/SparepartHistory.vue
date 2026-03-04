<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { History, TrendingUp, TrendingDown, X, Search } from 'lucide-vue-next';

interface Sparepart { id: number; name: string; satuan: string; stok: number; }
interface User      { id: number; name: string; }
interface HistoryItem {
    id:           number;
    tipe:         'masuk' | 'keluar';
    report_type:  'pm' | 'cm' | 'manual' | null;
    report_id:    number | null;
    qty:          number;
    stok_before:  number;
    stok_after:   number;
    notes:        string | null;
    created_at:   string;
    sparepart:    Sparepart;
    user:         User;
}

interface Paginator {
    data:          HistoryItem[];
    current_page:  number;
    last_page:     number;
    total:         number;
    per_page:      number;
}

interface Props {
    histories:  Paginator;
    spareparts: { id: number; name: string; satuan: string; stok: number }[];
    filters:    { sparepart_id?: any; tipe?: string };
}

const props = defineProps<Props>();

const filterSp   = ref(props.filters.sparepart_id ?? '');
const filterTipe = ref(props.filters.tipe         ?? '');
const showModal  = ref(false);

const selectedSp = ref<Props['spareparts'][0] | null>(null);

const form = useForm({ sparepart_id: '', qty: '', notes: '' });

const spSearch = ref('');
const spOpen   = ref(false);

const filteredSpareparts = () => {
    const q = spSearch.value.toLowerCase().trim();
    if (!q) return props.spareparts;
    return props.spareparts.filter(s => s.name.toLowerCase().includes(q));
};

const selectModalSp = (s: Props['spareparts'][0]) => {
    form.sparepart_id = String(s.id);
    selectedSp.value  = s;
    spSearch.value    = s.name;
    spOpen.value      = false;
};

const clearModalSp = () => {
    form.sparepart_id = '';
    selectedSp.value  = null;
    spSearch.value    = '';
    spOpen.value      = true;
};

const highlightMatch = (name: string, query: string): { text: string; match: boolean }[] => {
    if (!query.trim()) return [{ text: name, match: false }];
    const idx = name.toLowerCase().indexOf(query.toLowerCase().trim());
    if (idx === -1) return [{ text: name, match: false }];
    return [
        { text: name.slice(0, idx),                         match: false },
        { text: name.slice(idx, idx + query.trim().length), match: true  },
        { text: name.slice(idx + query.trim().length),      match: false },
    ].filter(p => p.text !== '');
};

watch([filterSp, filterTipe], () => {
    router.get('/jig/sparepart/history', {
        sparepart_id: filterSp.value,
        tipe:         filterTipe.value,
    }, { preserveState: true, preserveScroll: true });
});

const goPage = (p: number) => {
    router.get('/jig/sparepart/history', {
        sparepart_id: filterSp.value,
        tipe:         filterTipe.value,
        page:         p,
    }, { preserveState: true, preserveScroll: true });
};

const openModal = () => {
    form.reset();
    selectedSp.value = null;
    spSearch.value   = '';
    spOpen.value     = false;
    showModal.value  = true;
};

const closeModal = () => {
    showModal.value  = false;
    selectedSp.value = null;
    spSearch.value   = '';
    spOpen.value     = false;
    form.reset();
};

const closeSpDropdown = () => { setTimeout(() => { spOpen.value = false; }, 180); };

const submit = () => {
    if (!form.sparepart_id) return;
    form.post(`/jig/sparepart/${form.sparepart_id}/kurangi-stok`, { onSuccess: closeModal });
};

const fmt = (d: string) =>
    new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const reportLabel = (type: string | null, id: number | null) => {
    if (!type || !id) return 'Manual';
    return `${type.toUpperCase()} #${id}`;
};
</script>

<template>
    <Head title="History Sparepart" />
    <AppLayout :breadcrumbs="[
        {title:'JIG',href:'/jig/dashboard'},
        {title:'Master Sparepart',href:'/jig/sparepart'},
        {title:'History',href:'/jig/sparepart/history'}
    ]">
        <div class="p-4 sm:p-6 space-y-5">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <History class="w-6 h-6 text-indigo-600" /> History Transaksi Sparepart
                    </h1>
                    <p class="text-sm text-gray-500 mt-0.5">Log keluar masuk stok sparepart</p>
                </div>
                <button @click="openModal"
                    class="flex items-center gap-2 px-4 py-2.5 bg-red-600 text-white rounded-xl hover:bg-red-700 transition-colors font-medium text-sm">
                    <TrendingDown class="w-4 h-4" /> Ambil Reguler
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <select v-model="filterSp"
                    class="px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none min-w-[180px]">
                    <option value="">Semua Sparepart</option>
                    <option v-for="s in spareparts" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <select v-model="filterTipe"
                    class="px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="">Semua Tipe</option>
                    <option value="masuk">Masuk</option>
                    <option value="keluar">Keluar</option>
                </select>
                <span class="text-xs text-gray-400">{{ histories.total }} transaksi</span>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Waktu</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Sparepart</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Tipe</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Sumber</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Qty</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Stok Sebelum</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Stok Sesudah</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Oleh</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="histories.data.length === 0">
                                <td colspan="9" class="py-14 text-center text-gray-400 text-sm">Belum ada transaksi</td>
                            </tr>
                            <tr v-for="h in histories.data" :key="h.id"
                                :class="['hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors',
                                    h.tipe === 'masuk' ? 'bg-green-50/20' : 'bg-red-50/10']">
                                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ fmt(h.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ h.sparepart?.name }}</p>
                                    <p class="text-xs text-gray-400">{{ h.sparepart?.satuan }}</p>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold',
                                        h.tipe === 'masuk' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                                        <TrendingUp v-if="h.tipe === 'masuk'" class="w-3 h-3" />
                                        <TrendingDown v-else class="w-3 h-3" />
                                        {{ h.tipe === 'masuk' ? 'Masuk' : 'Keluar' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-bold',
                                        h.report_type === 'pm' ? 'bg-indigo-100 text-indigo-700' :
                                        h.report_type === 'cm' ? 'bg-orange-100 text-orange-700' :
                                        'bg-gray-100 text-gray-600']">
                                        {{ reportLabel(h.report_type, h.report_id) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span :class="['text-xs font-black',
                                        h.tipe === 'masuk' ? 'text-green-600' : 'text-red-600']">
                                        {{ h.tipe === 'masuk' ? '+' : '-' }}{{ Math.floor(h.qty) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-xs text-gray-500 font-mono">{{ Math.floor(h.stok_before) }}</td>
                                <td class="px-4 py-3 text-right text-xs font-bold font-mono"
                                    :class="h.tipe === 'masuk' ? 'text-green-600' : 'text-red-600'">
                                    {{ Math.floor(h.stok_after) }}
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ h.user?.name }}</td>
                                <td class="px-4 py-3 text-xs text-gray-400 max-w-[160px]">
                                    <p class="line-clamp-2">{{ h.notes ?? '—' }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="histories.last_page > 1"
                    class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-400">
                        Halaman {{ histories.current_page }} dari {{ histories.last_page }}
                        ({{ histories.total }} total)
                    </p>
                    <div class="flex gap-1">
                        <button v-for="p in histories.last_page" :key="p" @click="goPage(p)"
                            :class="['w-8 h-8 rounded-lg text-xs font-bold transition-colors',
                                p === histories.current_page
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
                            {{ p }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 backdrop-blur-sm bg-black/30 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full shadow-2xl">
                <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Keluar Stok Manual</h2>
                    <button @click="closeModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                </div>
                <form @submit.prevent="submit" class="p-5 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Sparepart <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                            <input
                                v-model="spSearch"
                                type="text"
                                placeholder="Cari sparepart..."
                                autocomplete="off"
                                @focus="spOpen = true"
                                @blur="closeSpDropdown"
                                :class="['w-full pl-7 pr-7 py-2 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                    selectedSp
                                        ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold'
                                        : 'border-gray-200 dark:border-gray-600 focus:border-indigo-400']"
                            />
                            <button v-if="selectedSp" type="button"
                                @click="clearModalSp"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                                <X class="w-3.5 h-3.5" />
                            </button>
                            <div v-if="spOpen"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                <div v-if="filteredSpareparts().length === 0"
                                    class="px-3 py-3 text-xs text-gray-400 text-center">
                                    Tidak ada hasil untuk "{{ spSearch }}"
                                </div>
                                <button
                                    v-for="s in filteredSpareparts()" :key="s.id"
                                    type="button"
                                    @mousedown.prevent="selectModalSp(s)"
                                    :class="['w-full text-left px-3 py-2.5 text-xs hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors flex items-center justify-between gap-2',
                                        selectedSp?.id === s.id ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-gray-700 dark:text-gray-300']">
                                    <span>
                                        <template v-for="(part, pi) in highlightMatch(s.name, spSearch)" :key="pi">
                                            <mark v-if="part.match" class="bg-yellow-200 dark:bg-yellow-700 text-gray-900 dark:text-white rounded px-0.5 not-italic">{{ part.text }}</mark>
                                            <span v-else>{{ part.text }}</span>
                                        </template>
                                    </span>
                                    <span class="text-gray-400 whitespace-nowrap shrink-0">{{ Math.floor(s.stok) }} {{ s.satuan }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedSp" class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3 text-xs">
                        <p class="font-bold text-gray-900 dark:text-white">{{ selectedSp.name }}</p>
                        <p class="text-gray-500 mt-0.5">Stok tersedia: <span class="font-bold text-indigo-600">{{ Math.floor(selectedSp.stok) }} {{ selectedSp.satuan }}</span></p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Jumlah Keluar <span class="text-red-500">*</span></label>
                        <input v-model="form.qty" type="number" min="1" :max="selectedSp?.stok" step="1" required placeholder="0"
                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none" />
                        <p v-if="form.qty && selectedSp" class="text-xs text-gray-400 mt-1">
                            Stok setelah: <span class="font-bold text-red-600">{{ Math.floor(selectedSp.stok - parseInt(form.qty || '0')) }} {{ selectedSp.satuan }}</span>
                        </p>
                        <p v-if="form.errors.qty" class="text-xs text-red-500 mt-1">{{ form.errors.qty }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Keterangan</label>
                        <input v-model="form.notes" type="text" placeholder="Opsional..."
                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none" />
                    </div>

                    <div class="flex gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <button type="submit" :disabled="form.processing || !selectedSp"
                            class="flex-1 py-2.5 bg-red-600 text-white rounded-xl hover:bg-red-700 disabled:opacity-50 font-semibold text-sm">
                            {{ form.processing ? 'Menyimpan...' : 'Keluar Stok' }}
                        </button>
                        <button type="button" @click="closeModal" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 rounded-xl hover:bg-gray-200 font-medium text-sm">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
