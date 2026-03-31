<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    History, Search, Trash2, AlertTriangle, CheckCircle2,
    Package, Calendar, User, X, TrendingDown, ArrowDownCircle, ArrowUpCircle
} from 'lucide-vue-next';

interface Sparepart { id: number; sparepart_code: string; sparepart_name: string; stok: number; unit: string; }
interface Dies { id_sap: string; no_part: string; nama_dies: string; line: string }
interface HistoryItem {
    id: number;
    tipe: 'preventive' | 'corrective' | 'reguler' | 'masuk';
    maintenance_id: number | null;
    sparepart_id: number;
    dies_id: string | null;
    quantity: number;
    notes: string | null;
    created_at: string;
    sparepart: Sparepart | null;
    dies: Dies | null;
    created_by: { id: number; name: string } | null;
}

interface Props {
    histories: { data: HistoryItem[]; links: any[]; meta: any; total?: number; from?: number; to?: number; last_page?: number };
    spareparts: Sparepart[];
    dies: Dies[];
    filters: { tipe?: string; sparepart_id?: string; dies_id?: string; flow?: string; search?: string };
}

const props = defineProps<Props>();
const page  = usePage();
const flash = computed(() => (page.props as any).flash);

const filterTipe        = ref(props.filters.tipe         ?? '');
const filterSparepart   = ref(props.filters.sparepart_id ?? '');
const filterDies        = ref(props.filters.dies_id      ?? '');
const filterFlow        = ref(props.filters.flow         ?? '');
const filterSearch      = ref(props.filters.search       ?? '');
const showDelModal      = ref(false);
const showRegulerModal  = ref(false);
const selectedH         = ref<HistoryItem | null>(null);
const spSearchFilter    = ref('');
const spFilterOpen      = ref(false);
const selectedFilterSp  = ref<Sparepart | null>(null);

const tipeCfg: Record<string, { label: string; badge: string; dot: string }> = {
    masuk:      { label: 'Masuk',      badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400', dot: 'bg-emerald-500' },
    preventive: { label: 'Preventive', badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',             dot: 'bg-blue-500'    },
    corrective: { label: 'Corrective', badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',                 dot: 'bg-red-500'     },
    reguler:    { label: 'Reguler',    badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',                dot: 'bg-gray-400'    },
};

const isMasuk  = (h: HistoryItem) => h.tipe === 'masuk';

const totalHistories = computed(() => props.histories.meta?.total ?? props.histories.total ?? 0);
const fromHistories  = computed(() => props.histories.meta?.from  ?? props.histories.from  ?? 0);
const toHistories    = computed(() => props.histories.meta?.to    ?? props.histories.to    ?? 0);
const lastPage       = computed(() => props.histories.meta?.last_page ?? props.histories.last_page ?? 1);

watch([filterTipe, filterSparepart, filterDies, filterFlow], () => navigate());

let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(filterSearch, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => navigate(), 400);
});

const navigate = () => router.get('/dies/sparepart/history',
    { tipe: filterTipe.value, sparepart_id: filterSparepart.value, dies_id: filterDies.value, flow: filterFlow.value, search: filterSearch.value },
    { preserveState: true, preserveScroll: true, replace: true });

const fmtDate     = (d: string | null) => !d ? '-' : new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
const fmtDatetime = (d: string) => new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const openDelete   = (h: HistoryItem) => { selectedH.value = h; showDelModal.value = true; };
const submitDelete = () => {
    if (!selectedH.value) return;
    router.delete(`/dies/sparepart/history/${selectedH.value.id}`, {
        onSuccess: () => { showDelModal.value = false; selectedH.value = null; },
    });
};

const filteredSparepartsForFilter = computed(() => {
    const q = spSearchFilter.value.toLowerCase().trim();
    if (!q) return props.spareparts;
    return props.spareparts.filter(s =>
        s.sparepart_name.toLowerCase().includes(q) || s.sparepart_code.toLowerCase().includes(q)
    );
});

const selectFilterSp = (s: Sparepart) => {
    selectedFilterSp.value = s;
    filterSparepart.value  = String(s.id);
    spSearchFilter.value   = s.sparepart_name;
    spFilterOpen.value     = false;
};

const clearFilterSp = () => {
    selectedFilterSp.value = null;
    filterSparepart.value  = '';
    spSearchFilter.value   = '';
    spFilterOpen.value     = true;
};

const closeFilterSpDropdown = () => setTimeout(() => { spFilterOpen.value = false; }, 180);

const highlightMatch = (text: string, query: string) => {
    if (!query.trim()) return [{ text, match: false }];
    const idx = text.toLowerCase().indexOf(query.toLowerCase().trim());
    if (idx === -1) return [{ text, match: false }];
    return [
        { text: text.slice(0, idx),                         match: false },
        { text: text.slice(idx, idx + query.trim().length), match: true  },
        { text: text.slice(idx + query.trim().length),      match: false },
    ].filter(p => p.text !== '');
};

const form = useForm({ sparepart_id: '', quantity: 1, notes: '' });

const selectedSp = ref<Sparepart | null>(null);
const spSearch   = ref('');
const spOpen     = ref(false);

const filteredSpareparts = computed(() => {
    const q = spSearch.value.toLowerCase().trim();
    if (!q) return props.spareparts;
    return props.spareparts.filter(s =>
        s.sparepart_name.toLowerCase().includes(q) || s.sparepart_code.toLowerCase().includes(q)
    );
});

const selectSp = (s: Sparepart) => {
    selectedSp.value  = s;
    form.sparepart_id = String(s.id);
    spSearch.value    = s.sparepart_name;
    spOpen.value      = false;
};
const clearSp = () => {
    selectedSp.value  = null;
    form.sparepart_id = '';
    spSearch.value    = '';
    spOpen.value      = true;
};
const closeSpDropdown = () => setTimeout(() => { spOpen.value = false; }, 180);

const openReguler = () => {
    form.reset();
    form.quantity    = 1;
    selectedSp.value = null;
    spSearch.value   = '';
    spOpen.value     = false;
    showRegulerModal.value = true;
};

const closeReguler = () => {
    showRegulerModal.value = false;
    form.reset();
    form.quantity    = 1;
    selectedSp.value = null;
    spSearch.value   = '';
};

const submitReguler = () => {
    form.transform(data => ({ ...data, tipe: 'reguler' }))
        .post('/dies/sparepart/history', { onSuccess: () => closeReguler() });
};

const stokSetelah = computed(() => {
    if (!selectedSp.value) return null;
    const qty = Number(form.quantity);
    if (isNaN(qty) || qty < 0) return selectedSp.value.stok;
    return selectedSp.value.stok - qty;
});

const setFlow = (val: string) => {
    filterFlow.value = filterFlow.value === val ? '' : val;
    filterTipe.value = '';
};
</script>

<template>
    <Head title="History Pemakaian Sparepart" />
    <AppLayout :breadcrumbs="[
        { title: 'Dies', href: '/dies' },
        { title: 'Sparepart', href: '/dies/sparepart' },
        { title: 'History', href: '/dies/sparepart/history' },
    ]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-orange-500 rounded-xl flex-shrink-0">
                            <History class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
                        </span>
                        History Sparepart
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">{{ totalHistories }} riwayat</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="router.visit('/dies/sparepart')"
                        class="flex items-center gap-1.5 px-3 py-2 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-semibold hover:border-orange-400 active:scale-95 transition-all">
                        <Package class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Master Sparepart</span>
                    </button>
                    <button @click="openReguler"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-xs sm:text-sm font-semibold active:scale-95 transition-all shadow-sm">
                        <TrendingDown class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Ambil Reguler</span>
                        <span class="sm:hidden">Ambil</span>
                    </button>
                </div>
            </div>

            <div v-if="flash?.success"
                class="flex items-center gap-3 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                <p class="text-emerald-800 dark:text-emerald-200 font-medium text-xs sm:text-sm">{{ flash.success }}</p>
            </div>

            <div class="space-y-2.5">
                <div class="flex items-center gap-2 flex-wrap">
                    <button @click="setFlow('masuk')"
                        :class="['flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition-all',
                            filterFlow === 'masuk'
                                ? 'bg-emerald-500 border-emerald-500 text-white'
                                : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:border-emerald-400']">
                        <ArrowUpCircle class="w-3.5 h-3.5" /> Stok Masuk
                    </button>
                    <button @click="setFlow('keluar')"
                        :class="['flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition-all',
                            filterFlow === 'keluar'
                                ? 'bg-red-500 border-red-500 text-white'
                                : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:border-red-400']">
                        <ArrowDownCircle class="w-3.5 h-3.5" /> Stok Keluar
                    </button>

                    <div class="w-px h-5 bg-gray-200 dark:bg-gray-700 mx-1"></div>

                    <div class="flex gap-1.5 flex-wrap" v-if="!filterFlow">
                        <button v-for="[v, c] in Object.entries(tipeCfg)" :key="v"
                            @click="filterTipe = filterTipe === v ? '' : v"
                            :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all',
                                filterTipe === v
                                    ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400'
                                    : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:border-orange-300']">
                            <span :class="['w-1.5 h-1.5 rounded-full flex-shrink-0', c.dot]"></span>
                            {{ c.label }}
                        </button>
                    </div>

                    <div class="flex gap-1.5 flex-wrap" v-else>
                        <template v-if="filterFlow === 'masuk'">
                            <button @click="filterTipe = filterTipe === 'masuk' ? '' : 'masuk'"
                                :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all',
                                    filterTipe === 'masuk'
                                        ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400'
                                        : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:border-orange-300']">
                                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 bg-emerald-500"></span>
                                Masuk
                            </button>
                        </template>
                        <template v-else>
                            <button v-for="v in ['preventive','corrective','reguler']" :key="v"
                                @click="filterTipe = filterTipe === v ? '' : v"
                                :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all',
                                    filterTipe === v
                                        ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400'
                                        : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:border-orange-300']">
                                <span :class="['w-1.5 h-1.5 rounded-full flex-shrink-0', tipeCfg[v]?.dot]"></span>
                                {{ tipeCfg[v]?.label }}
                            </button>
                        </template>
                    </div>
                </div>

                <div class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                    <input
                        v-model="spSearchFilter"
                        type="text"
                        placeholder="Cari sparepart..."
                        autocomplete="off"
                        @focus="spFilterOpen = true"
                        @blur="closeFilterSpDropdown"
                        :class="['w-full pl-9 pr-9 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-800',
                            selectedFilterSp
                                ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-300 font-semibold'
                                : 'border-gray-200 dark:border-gray-700 focus:border-orange-400']"
                    />
                    <button v-if="selectedFilterSp" type="button" @click="clearFilterSp"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                        <X class="w-3.5 h-3.5" />
                    </button>
                    <div v-if="spFilterOpen && !selectedFilterSp"
                        class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                        <div v-if="filteredSparepartsForFilter.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">
                            Tidak ada hasil untuk "{{ spSearchFilter }}"
                        </div>
                        <button v-for="s in filteredSparepartsForFilter" :key="s.id" type="button"
                            @mousedown.prevent="selectFilterSp(s)"
                            class="w-full text-left px-3 py-2.5 text-xs hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors flex items-center justify-between gap-2 text-gray-700 dark:text-gray-300">
                            <span class="min-w-0">
                                <span class="font-mono text-orange-500 mr-1.5">{{ s.sparepart_code }}</span>
                                <template v-for="(part, pi) in highlightMatch(s.sparepart_name, spSearchFilter)" :key="pi">
                                    <mark v-if="part.match" class="bg-yellow-200 dark:bg-yellow-700 text-gray-900 dark:text-white rounded px-0.5 not-italic">{{ part.text }}</mark>
                                    <span v-else>{{ part.text }}</span>
                                </template>
                            </span>
                            <span class="text-gray-400 whitespace-nowrap shrink-0 text-xs">{{ s.stok }} {{ s.unit }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Tipe</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Sparepart</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Qty</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Catatan</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">PIC</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Tanggal</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="histories.data.length === 0">
                                <td colspan="7" class="py-16 text-center text-gray-400 text-sm">
                                    <History class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                    Tidak ada riwayat
                                </td>
                            </tr>
                            <tr v-for="h in histories.data" :key="h.id"
                                :class="['hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors',
                                    isMasuk(h) ? 'bg-emerald-50/30 dark:bg-emerald-900/10' : '']">
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-bold', tipeCfg[h.tipe]?.badge ?? '']">
                                        <span :class="['w-1.5 h-1.5 rounded-full', tipeCfg[h.tipe]?.dot ?? '']"></span>
                                        {{ tipeCfg[h.tipe]?.label ?? h.tipe }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-xs font-mono font-bold text-orange-600 dark:text-orange-400">{{ h.sparepart?.sparepart_code }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ h.sparepart?.sparepart_name }}</p>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['text-sm font-bold', isMasuk(h) ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400']">
                                        {{ isMasuk(h) ? '+' : '-' }}{{ h.quantity }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 max-w-xs">
                                    <p class="line-clamp-2">{{ h.notes ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ h.created_by?.name ?? '—' }}</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500 whitespace-nowrap">{{ fmtDatetime(h.created_at) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <button @click="openDelete(h)"
                                        class="p-1.5 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-200 transition-colors">
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="lastPage > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-500">{{ fromHistories }}–{{ toHistories }} dari {{ totalHistories }}</p>
                    <div class="flex gap-1">
                        <button v-for="link in histories.links" :key="link.label"
                            @click="link.url && router.visit(link.url)"
                            :disabled="!link.url"
                            v-html="link.label"
                            :class="['px-3 py-1.5 text-xs rounded-lg font-semibold transition-colors',
                                link.active ? 'bg-orange-500 text-white' : link.url ? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200' : 'opacity-40 cursor-default bg-gray-100 dark:bg-gray-700 text-gray-400']">
                        </button>
                    </div>
                </div>
            </div>

            <div class="lg:hidden space-y-2.5">
                <div v-if="histories.data.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <p class="text-gray-400 text-sm">Tidak ada riwayat</p>
                </div>
                <div v-for="h in histories.data" :key="h.id"
                    :class="['rounded-2xl border shadow-sm overflow-hidden',
                        isMasuk(h)
                            ? 'bg-emerald-50 dark:bg-emerald-900/10 border-emerald-200 dark:border-emerald-800'
                            : 'bg-white dark:bg-gray-800 border-gray-100 dark:border-gray-700']">
                    <div class="p-3.5">
                        <div class="flex items-start justify-between gap-2 mb-2.5">
                            <div class="min-w-0">
                                <p class="text-xs font-mono font-bold text-orange-600 dark:text-orange-400">{{ h.sparepart?.sparepart_code }}</p>
                                <p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5">{{ h.sparepart?.sparepart_name }}</p>
                                <p v-if="h.dies" class="text-xs text-gray-400 mt-0.5">{{ h.dies.no_part }} · {{ h.dies.nama_dies }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold', tipeCfg[h.tipe]?.badge ?? '']">
                                    <span :class="['w-1.5 h-1.5 rounded-full', tipeCfg[h.tipe]?.dot ?? '']"></span>
                                    {{ tipeCfg[h.tipe]?.label ?? h.tipe }}
                                </span>
                                <span :class="['text-xl font-black', isMasuk(h) ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500']">
                                    {{ isMasuk(h) ? '+' : '-' }}{{ h.quantity }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-gray-500 mb-3">
                            <span class="flex items-center gap-1"><User class="w-3 h-3" /> {{ h.created_by?.name ?? '—' }}</span>
                            <span class="flex items-center gap-1"><Calendar class="w-3 h-3" /> {{ fmtDate(h.created_at) }}</span>
                        </div>
                        <p v-if="h.notes" class="text-xs text-gray-500 bg-white dark:bg-gray-700/50 rounded-lg px-2.5 py-1.5 mb-3">{{ h.notes }}</p>
                        <div class="flex justify-end pt-2.5 border-t border-gray-100 dark:border-gray-700">
                            <button @click="openDelete(h)"
                                class="flex items-center gap-1.5 py-2 px-3 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-xl text-xs font-bold hover:bg-red-200 active:scale-95 transition-all">
                                <Trash2 class="w-3.5 h-3.5" />
                                {{ isMasuk(h) ? 'Hapus & Kurangi Stok' : 'Hapus & Kembalikan Stok' }}
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="lastPage > 1" class="flex justify-center gap-1 pt-2">
                    <button v-for="link in histories.links" :key="link.label"
                        @click="link.url && router.visit(link.url)"
                        :disabled="!link.url"
                        v-html="link.label"
                        :class="['px-3 py-1.5 text-xs rounded-lg font-semibold',
                            link.active ? 'bg-orange-500 text-white' : link.url ? 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' : 'opacity-40 cursor-default']">
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showRegulerModal"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <TrendingDown class="w-4 h-4 text-orange-500" /> Ambil Sparepart Reguler
                    </h2>
                    <button @click="closeReguler" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <div class="p-4 sm:p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1.5">Sparepart <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                            <input v-model="spSearch" type="text" placeholder="Cari kode atau nama..." autocomplete="off"
                                @focus="spOpen = true" @blur="closeSpDropdown"
                                :class="['w-full pl-8 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                    selectedSp
                                        ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-300 font-semibold'
                                        : 'border-gray-200 dark:border-gray-600 focus:border-orange-400']" />
                            <button v-if="selectedSp" type="button" @click="clearSp"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                                <X class="w-3.5 h-3.5" />
                            </button>
                            <div v-if="spOpen"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                <div v-if="filteredSpareparts.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">
                                    Tidak ada hasil untuk "{{ spSearch }}"
                                </div>
                                <button v-for="s in filteredSpareparts" :key="s.id" type="button"
                                    @mousedown.prevent="selectSp(s)"
                                    :class="['w-full text-left px-3 py-2.5 text-xs hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors flex items-center justify-between gap-2',
                                        selectedSp?.id === s.id ? 'bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-300 font-semibold' : 'text-gray-700 dark:text-gray-300']">
                                    <span>
                                        <template v-for="(part, pi) in highlightMatch(s.sparepart_name, spSearch)" :key="pi">
                                            <mark v-if="part.match" class="bg-yellow-200 dark:bg-yellow-700 text-gray-900 dark:text-white rounded px-0.5 not-italic">{{ part.text }}</mark>
                                            <span v-else>{{ part.text }}</span>
                                        </template>
                                    </span>
                                    <span class="text-gray-400 whitespace-nowrap shrink-0">{{ s.stok }} {{ s.unit }}</span>
                                </button>
                            </div>
                        </div>
                        <div v-if="selectedSp" class="mt-1.5 flex items-center justify-between bg-gray-50 dark:bg-gray-700/50 rounded-xl px-3 py-2">
                            <p class="text-xs font-bold text-gray-900 dark:text-white truncate mr-2">{{ selectedSp.sparepart_name }}</p>
                            <p class="text-xs text-gray-500 whitespace-nowrap">Stok: <span class="font-bold text-orange-500">{{ selectedSp.stok }} {{ selectedSp.unit }}</span></p>
                        </div>
                        <p v-if="form.errors.sparepart_id" class="text-xs text-red-500 mt-1">{{ form.errors.sparepart_id }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1.5">Jumlah <span class="text-red-500">*</span></label>
                        <input v-model.number="form.quantity" type="number" min="1" :max="selectedSp?.stok" required
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400 text-center text-lg font-bold transition-colors" />
                        <div v-if="selectedSp && stokSetelah !== null" class="flex items-center justify-between mt-2 p-2.5 bg-orange-50 dark:bg-orange-900/20 rounded-xl">
                            <span class="text-xs text-gray-500">Stok setelah:</span>
                            <span class="text-sm font-black text-orange-600">{{ stokSetelah }} {{ selectedSp.unit }}</span>
                        </div>
                        <p v-if="form.errors.quantity" class="text-xs text-red-500 mt-1">{{ form.errors.quantity }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1.5">Catatan (opsional)</label>
                        <input v-model="form.notes" type="text" placeholder="Keterangan..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400 transition-colors" />
                    </div>

                    <div class="flex gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" @click="closeReguler"
                            class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button type="button" @click="submitReguler" :disabled="form.processing || !selectedSp"
                            class="flex-1 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white rounded-xl font-bold text-sm active:scale-95 transition-all">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDelModal && selectedH" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="p-5 text-center">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-3">
                        <AlertTriangle class="w-6 h-6 text-red-600" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">Hapus Riwayat?</h3>
                    <p class="text-sm text-gray-500 mb-0.5">{{ selectedH.sparepart?.sparepart_name }}</p>
                    <p class="text-xs text-gray-400 mb-5">
                        <template v-if="isMasuk(selectedH)">
                            Stok sebanyak <strong>{{ selectedH.quantity }}</strong> akan dikurangi dari master sparepart.
                        </template>
                        <template v-else>
                            Stok sebanyak <strong>{{ selectedH.quantity }}</strong> akan dikembalikan ke master sparepart.
                        </template>
                    </p>
                    <div class="flex gap-3">
                        <button @click="showDelModal = false"
                            class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button @click="submitDelete"
                            class="flex-1 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 font-bold text-sm active:scale-95 transition-all">
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
