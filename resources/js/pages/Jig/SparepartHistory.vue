<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { History, TrendingUp, TrendingDown, X, Search, Filter } from 'lucide-vue-next';

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

const filterSp        = ref(props.filters.sparepart_id ?? '');
const filterTipe      = ref(props.filters.tipe         ?? '');
const showModal       = ref(false);
const showFilterPanel = ref(false);

const selectedSp    = ref<Props['spareparts'][0] | null>(null);
const filterSpObj   = ref<Props['spareparts'][0] | null>(
    props.filters.sparepart_id
        ? props.spareparts.find(s => s.id == props.filters.sparepart_id) ?? null
        : null
);

const form = useForm({ sparepart_id: '', qty: '', notes: '' });

const spSearch     = ref('');
const spOpen       = ref(false);
const filterSpSearch = ref(filterSpObj.value?.name ?? '');
const filterSpOpen   = ref(false);

const filteredSpareparts = computed(() => {
    const q = spSearch.value.toLowerCase().trim();
    if (!q) return props.spareparts;
    return props.spareparts.filter(s =>
        s.name.toLowerCase().includes(q) ||
        String(s.id).includes(q)
    );
});

const filteredFilterSpareparts = computed(() => {
    const q = filterSpSearch.value.toLowerCase().trim();
    if (!q) return props.spareparts;
    return props.spareparts.filter(s => s.name.toLowerCase().includes(q));
});

const selectFilterSp = (s: Props['spareparts'][0]) => {
    filterSp.value      = s.id;
    filterSpObj.value   = s;
    filterSpSearch.value = s.name;
    filterSpOpen.value  = false;
};

const clearFilterSp = () => {
    filterSp.value       = '';
    filterSpObj.value    = null;
    filterSpSearch.value = '';
    filterSpOpen.value   = true;
};

const closeFilterSpDropdown = () => { setTimeout(() => { filterSpOpen.value = false; }, 180); };

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

const activeFilterCount = computed(() => {
    let c = 0;
    if (filterSp.value)   c++;
    if (filterTipe.value) c++;
    return c;
});

const resetFilters = () => {
    filterSp.value       = '';
    filterTipe.value     = '';
    filterSpObj.value    = null;
    filterSpSearch.value = '';
};

const fmt = (d: string) =>
    new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const fmtShort = (d: string) =>
    new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });

const reportLabel = (type: string | null, id: number | null) => {
    if (!type || !id) return 'Manual';
    return `${type.toUpperCase()} #${id}`;
};
</script>

<template>
    <Head title="History Sparepart" />
    <AppLayout :breadcrumbs="[
        {title:'JIG',href:'/jig/dashboard'},
        {title:'Sparepart',href:'/jig/sparepart'},
        {title:'History',href:'/jig/sparepart/history'}
    ]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-indigo-600 rounded-xl flex-shrink-0">
                            <History class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
                        </span>
                        History Sparepart
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">Log keluar masuk stok</p>
                </div>
                <button @click="openModal"
                    class="flex items-center gap-1.5 px-3 py-2 sm:px-4 sm:py-2.5 bg-red-600 text-white rounded-xl hover:bg-red-700 active:scale-95 transition-all font-semibold text-xs sm:text-sm flex-shrink-0">
                    <TrendingDown class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                    <span class="hidden sm:inline">Ambil Reguler</span>
                    <span class="sm:hidden">Ambil</span>
                </button>
            </div>

            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <button @click="showFilterPanel = !showFilterPanel"
                        :class="['relative flex items-center gap-1.5 px-3 py-2.5 border rounded-xl text-sm font-medium transition-colors',
                            showFilterPanel || activeFilterCount > 0
                                ? 'bg-indigo-600 border-indigo-600 text-white'
                                : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-indigo-400']">
                        <Filter class="w-4 h-4" />
                        <span>Filter</span>
                        <span v-if="activeFilterCount > 0"
                            class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold">
                            {{ activeFilterCount }}
                        </span>
                    </button>
                    <span class="text-xs text-gray-400">{{ histories.total }} transaksi</span>
                    <button v-if="activeFilterCount > 0" @click="resetFilters" class="text-xs text-indigo-600 font-semibold ml-auto">Reset</button>
                </div>

                <div v-if="showFilterPanel" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-3 space-y-3 shadow-sm">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Tipe</label>
                        <div class="flex gap-2">
                            <button v-for="opt in [{v:'',l:'Semua'},{v:'masuk',l:'Masuk'},{v:'keluar',l:'Keluar'}]"
                                :key="opt.v"
                                @click="filterTipe = opt.v"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors active:scale-95',
                                    filterTipe === opt.v
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300']">
                                {{ opt.l }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Sparepart</label>
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                            <input
                                v-model="filterSpSearch"
                                type="text"
                                placeholder="Cari nama sparepart..."
                                autocomplete="off"
                                @focus="filterSpOpen = true"
                                @blur="closeFilterSpDropdown"
                                :class="['w-full pl-8 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                    filterSpObj
                                        ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold'
                                        : 'border-gray-200 dark:border-gray-600 focus:border-indigo-400']"
                            />
                            <button v-if="filterSpObj" type="button" @click="clearFilterSp"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                                <X class="w-3.5 h-3.5" />
                            </button>
                            <div v-if="filterSpOpen"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                <div v-if="filteredFilterSpareparts.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">
                                    Tidak ada hasil untuk "{{ filterSpSearch }}"
                                </div>
                                <button
                                    v-for="s in filteredFilterSpareparts" :key="s.id"
                                    type="button"
                                    @mousedown.prevent="selectFilterSp(s)"
                                    :class="['w-full text-left px-3 py-2.5 text-xs hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors flex items-center justify-between gap-2',
                                        filterSpObj?.id === s.id ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-gray-700 dark:text-gray-300']">
                                    <span>
                                        <template v-for="(part, pi) in highlightMatch(s.name, filterSpSearch)" :key="pi">
                                            <mark v-if="part.match" class="bg-yellow-200 dark:bg-yellow-700 text-gray-900 dark:text-white rounded px-0.5 not-italic">{{ part.text }}</mark>
                                            <span v-else>{{ part.text }}</span>
                                        </template>
                                    </span>
                                    <span class="text-gray-400 whitespace-nowrap shrink-0">{{ Math.floor(s.stok) }} {{ s.satuan }}</span>
                                </button>
                            </div>
                        </div>
                        <div v-if="filterSpObj" class="mt-1.5 flex items-center gap-1.5 text-xs text-indigo-600 dark:text-indigo-400">
                            <span class="font-semibold">{{ filterSpObj.name }}</span>
                            <span class="text-gray-400">· {{ Math.floor(filterSpObj.stok) }} {{ filterSpObj.satuan }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Waktu</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Sparepart</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Tipe</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Sumber</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wide">Qty</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wide">Sebelum</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wide">Sesudah</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Oleh</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="histories.data.length === 0">
                                <td colspan="9" class="py-16 text-center text-gray-400 text-sm">
                                    <History class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                    Belum ada transaksi
                                </td>
                            </tr>
                            <tr v-for="h in histories.data" :key="h.id"
                                :class="['hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors',
                                    h.tipe === 'masuk' ? 'bg-emerald-50/20' : 'bg-red-50/10']">
                                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ fmt(h.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ h.sparepart?.name }}</p>
                                    <p class="text-xs text-gray-400">{{ h.sparepart?.satuan }}</p>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold',
                                        h.tipe === 'masuk' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400']">
                                        <TrendingUp v-if="h.tipe === 'masuk'" class="w-3 h-3" />
                                        <TrendingDown v-else class="w-3 h-3" />
                                        {{ h.tipe === 'masuk' ? 'Masuk' : 'Keluar' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-bold',
                                        h.report_type === 'pm' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400' :
                                        h.report_type === 'cm' ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400' :
                                        'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400']">
                                        {{ reportLabel(h.report_type, h.report_id) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span :class="['text-xs font-black', h.tipe === 'masuk' ? 'text-emerald-600' : 'text-red-600']">
                                        {{ h.tipe === 'masuk' ? '+' : '-' }}{{ Math.floor(h.qty) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-xs text-gray-500 font-mono">{{ Math.floor(h.stok_before) }}</td>
                                <td class="px-4 py-3 text-right text-xs font-bold font-mono"
                                    :class="h.tipe === 'masuk' ? 'text-emerald-600' : 'text-red-600'">
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
                    <p class="text-xs text-gray-400">Hal. {{ histories.current_page }} / {{ histories.last_page }} ({{ histories.total }} total)</p>
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

            <div class="lg:hidden space-y-2">
                <div v-if="histories.data.length === 0" class="py-14 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <History class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                    <p class="text-gray-400 text-sm">Belum ada transaksi</p>
                </div>

                <div v-for="h in histories.data" :key="h.id"
                    :class="['bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden border-l-4',
                        h.tipe === 'masuk' ? 'border-l-emerald-400' : 'border-l-red-400']">
                    <div class="px-3 py-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-gray-900 dark:text-white leading-tight truncate">{{ h.sparepart?.name }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ fmtShort(h.created_at) }} · {{ h.user?.name }}</p>
                            </div>
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <span :class="['px-1.5 py-0.5 rounded-full text-xs font-bold',
                                    h.report_type === 'pm' ? 'bg-indigo-100 text-indigo-700' :
                                    h.report_type === 'cm' ? 'bg-orange-100 text-orange-700' :
                                    'bg-gray-100 text-gray-500']">
                                    {{ reportLabel(h.report_type, h.report_id) }}
                                </span>
                                <span :class="['inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-xs font-bold',
                                    h.tipe === 'masuk' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700']">
                                    <TrendingUp v-if="h.tipe === 'masuk'" class="w-3 h-3" />
                                    <TrendingDown v-else class="w-3 h-3" />
                                    {{ h.tipe === 'masuk' ? 'Masuk' : 'Keluar' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mt-2">
                            <div class="flex items-center gap-1.5">
                                <span :class="['text-sm font-black', h.tipe === 'masuk' ? 'text-emerald-600' : 'text-red-600']">
                                    {{ h.tipe === 'masuk' ? '+' : '-' }}{{ Math.floor(h.qty) }}
                                </span>
                                <span class="text-xs text-gray-400">{{ h.sparepart?.satuan }}</span>
                            </div>
                            <span class="text-gray-200 dark:text-gray-600">|</span>
                            <div class="flex items-center gap-1 text-xs text-gray-400">
                                <span class="font-mono">{{ Math.floor(h.stok_before) }}</span>
                                <span>→</span>
                                <span :class="['font-mono font-bold', h.tipe === 'masuk' ? 'text-emerald-600' : 'text-red-600']">
                                    {{ Math.floor(h.stok_after) }}
                                </span>
                            </div>
                            <p v-if="h.notes" class="text-xs text-gray-400 truncate flex-1 min-w-0">{{ h.notes }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="histories.last_page > 1"
                    class="flex items-center justify-between px-1 py-2">
                    <p class="text-xs text-gray-400">Hal. {{ histories.current_page }} / {{ histories.last_page }}</p>
                    <div class="flex gap-1">
                        <button v-for="p in histories.last_page" :key="p" @click="goPage(p)"
                            :class="['w-8 h-8 rounded-lg text-xs font-bold transition-colors active:scale-95',
                                p === histories.current_page
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300']">
                            {{ p }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <TrendingDown class="w-4 h-4 text-red-600" /> Keluar Stok Manual
                    </h2>
                    <button @click="closeModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <form @submit.prevent="submit" class="p-4 sm:p-5 space-y-4">
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
                                :class="['w-full pl-8 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                    selectedSp
                                        ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold'
                                        : 'border-gray-200 dark:border-gray-600 focus:border-indigo-400']"
                            />
                            <button v-if="selectedSp" type="button" @click="clearModalSp"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                                <X class="w-3.5 h-3.5" />
                            </button>
                            <div v-if="spOpen"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                <div v-if="filteredSpareparts.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">
                                    Tidak ada hasil untuk "{{ spSearch }}"
                                </div>
                                <button
                                    v-for="s in filteredSpareparts" :key="s.id"
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

                    <div v-if="selectedSp" class="flex items-center justify-between bg-gray-50 dark:bg-gray-700/50 rounded-xl px-3 py-2.5">
                        <p class="text-xs font-bold text-gray-900 dark:text-white truncate mr-2">{{ selectedSp.name }}</p>
                        <p class="text-xs text-gray-500 whitespace-nowrap">Stok: <span class="font-bold text-indigo-600">{{ Math.floor(selectedSp.stok) }} {{ selectedSp.satuan }}</span></p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Jumlah Keluar <span class="text-red-500">*</span></label>
                        <input v-model="form.qty" type="number" inputmode="numeric" min="1" :max="selectedSp?.stok" step="1" required placeholder="0"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-red-500 focus:outline-none text-center text-lg font-bold transition-colors" />
                        <div v-if="form.qty && selectedSp" class="flex items-center justify-between mt-2 p-2.5 bg-red-50 dark:bg-red-900/20 rounded-xl">
                            <span class="text-xs text-gray-500">Stok setelah:</span>
                            <span class="text-sm font-black text-red-600">{{ Math.floor(selectedSp.stok - parseInt(form.qty || '0')) }} {{ selectedSp.satuan }}</span>
                        </div>
                        <p v-if="form.errors.qty" class="text-xs text-red-500 mt-1">{{ form.errors.qty }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Keterangan</label>
                        <input v-model="form.notes" type="text" placeholder="Opsional..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-indigo-500 focus:outline-none transition-colors" />
                    </div>

                    <div class="flex gap-3 pt-2 pb-safe border-t border-gray-100 dark:border-gray-700">
                        <button type="button" @click="closeModal"
                            class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button type="submit" :disabled="form.processing || !selectedSp"
                            class="flex-1 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 disabled:opacity-50 font-bold text-sm active:scale-95 transition-all">
                            {{ form.processing ? 'Menyimpan...' : 'Keluar Stok' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
