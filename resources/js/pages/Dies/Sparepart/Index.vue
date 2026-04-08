<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    Package, Search, Filter, Plus, Pencil, Trash2,
    AlertTriangle, CheckCircle2, ArrowUpCircle, History, X, Layers
} from 'lucide-vue-next';

interface Sparepart {
    id: number;
    sparepart_code: string;
    sparepart_name: string;
    unit: string;
    stok: number;
    stok_minimum: number;
}

interface Props {
    spareparts: { data: Sparepart[]; links: any[]; meta: any };
    lowStokCount: number;
    totalCount: number;
    filters: { search?: string; filter?: string };
}

const props = defineProps<Props>();
const page  = usePage();
const flash = computed(() => (page.props as any).flash);

const search     = ref(props.filters.search ?? '');
const filterStok = ref(props.filters.filter ?? '');
const showFilter = ref(false);

const showAddModal  = ref(false);
const showEditModal = ref(false);
const showAdjModal  = ref(false);
const showDelModal  = ref(false);
const showBulkModal = ref(false);
const selectedSp    = ref<Sparepart | null>(null);

const form = ref({ sparepart_code: '', sparepart_name: '', unit: 'pcs', stok: 0, stok_minimum: 0 });
const adjQty   = ref<number>(1);
const adjNotes = ref('');

interface BulkItem {
    id: number;
    sparepart_code: string;
    sparepart_name: string;
    unit: string;
    stok: number;
    stok_minimum: number;
    stok_tambah: number | null;
    new_stok_minimum: number | null;
}

const bulkItems     = ref<BulkItem[]>([]);
const bulkNotes     = ref('');
const bulkSearch    = ref('');
const allSpareparts = ref<Sparepart[]>([]);
const isFetchingAll = ref(false);

const filteredBulkCandidates = computed(() => {
    const q = bulkSearch.value.toLowerCase().trim();
    return allSpareparts.value.filter(sp => {
        const notAdded = !bulkItems.value.find(b => b.id === sp.id);
        if (!notAdded) return false;
        if (!q) return true;
        return sp.sparepart_name.toLowerCase().includes(q) || sp.sparepart_code.toLowerCase().includes(q);
    });
});

const addToBulk = (sp: Sparepart) => {
    bulkItems.value.push({
        id: sp.id,
        sparepart_code: sp.sparepart_code,
        sparepart_name: sp.sparepart_name,
        unit: sp.unit,
        stok: sp.stok,
        stok_minimum: sp.stok_minimum,
        stok_tambah: null,
        new_stok_minimum: null,
    });
    bulkSearch.value = '';
};

const removeFromBulk = (id: number) => {
    bulkItems.value = bulkItems.value.filter(b => b.id !== id);
};

const openBulk = async () => {
    bulkItems.value     = [];
    bulkNotes.value     = '';
    bulkSearch.value    = '';
    showBulkModal.value = true;

    isFetchingAll.value = true;
    try {
        const res = await fetch('/dies/sparepart/all');
        allSpareparts.value = await res.json();
    } finally {
        isFetchingAll.value = false;
    }
};

const submitBulk = () => {
    const items = bulkItems.value
        .filter(b => (b.stok_tambah !== null && b.stok_tambah > 0) || b.new_stok_minimum !== null)
        .map(b => ({
            id:           b.id,
            stok_tambah:  b.stok_tambah ?? 0,
            stok_minimum: b.new_stok_minimum ?? b.stok_minimum,
        }));

    if (!items.length) return;

    router.post('/dies/sparepart/bulk-update', { items, notes: bulkNotes.value }, {
        onSuccess: () => { showBulkModal.value = false; },
    });
};

const bulkHasChanges = computed(() =>
    bulkItems.value.some(b => (b.stok_tambah !== null && b.stok_tambah > 0) || b.new_stok_minimum !== null)
);

const activeFilterCount = computed(() => [filterStok.value].filter(Boolean).length);
const isLow = (sp: Sparepart) => sp.stok <= sp.stok_minimum;
const totalLabel = computed(() => `${props.totalCount} item`);

let debounce: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => navigate(), 400);
});
watch(filterStok, () => navigate());

const navigate = () => router.get('/dies/sparepart',
    { search: search.value, filter: filterStok.value },
    { preserveState: true, preserveScroll: true, replace: true });

const clearFilters = () => {
    filterStok.value = '';
    search.value     = '';
    showFilter.value = false;
};

const openAdd = () => {
    form.value = { sparepart_code: '', sparepart_name: '', unit: 'pcs', stok: 0, stok_minimum: 0 };
    showAddModal.value = true;
};

const openEdit = (sp: Sparepart) => {
    selectedSp.value = sp;
    form.value = {
        sparepart_code: sp.sparepart_code,
        sparepart_name: sp.sparepart_name,
        unit:           sp.unit,
        stok:           sp.stok,
        stok_minimum:   sp.stok_minimum,
    };
    showEditModal.value = true;
};

const openAdj = (sp: Sparepart) => {
    selectedSp.value = sp;
    adjQty.value     = 1;
    adjNotes.value   = '';
    showAdjModal.value = true;
};

const openDelete = (sp: Sparepart) => { selectedSp.value = sp; showDelModal.value = true; };

const submitAdd = () => {
    router.post('/dies/sparepart', form.value, { onSuccess: () => { showAddModal.value = false; } });
};

const submitEdit = () => {
    if (!selectedSp.value) return;
    router.put(`/dies/sparepart/${selectedSp.value.id}`, form.value, {
        onSuccess: () => { showEditModal.value = false; selectedSp.value = null; },
    });
};

const submitAdj = () => {
    if (!selectedSp.value) return;
    const qty = Number(adjQty.value);
    if (!qty || qty < 1) return;
    router.post(`/dies/sparepart/${selectedSp.value.id}/adjust-stok`,
        { qty, type: 'tambah', notes: adjNotes.value },
        { onSuccess: () => { showAdjModal.value = false; selectedSp.value = null; } });
};

const clampQty = () => { if (!adjQty.value || adjQty.value < 1) adjQty.value = 1; };

const submitDelete = () => {
    if (!selectedSp.value) return;
    router.delete(`/dies/sparepart/${selectedSp.value.id}`, {
        onSuccess: () => { showDelModal.value = false; selectedSp.value = null; },
    });
};
</script>

<template>
    <Head title="Sparepart Dies" />
    <AppLayout :breadcrumbs="[
        { title: 'Dies', href: '/dies' },
        { title: 'Sparepart', href: '/dies/sparepart' },
    ]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-orange-500 rounded-xl flex-shrink-0">
                            <Package class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
                        </span>
                        Sparepart Dies
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">{{ totalLabel }}</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="router.visit('/dies/sparepart/history')"
                        class="flex items-center gap-1.5 px-3 py-2 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-semibold hover:border-orange-400 active:scale-95 transition-all">
                        <History class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">History</span>
                    </button>
                    <button @click="openBulk"
                        class="flex items-center gap-1.5 px-3 py-2 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-semibold hover:border-orange-400 active:scale-95 transition-all">
                        <Layers class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Multiple Update</span>
                    </button>
                    <button @click="openAdd"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-xs sm:text-sm font-semibold active:scale-95 transition-all shadow-sm">
                        <Plus class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Tambah</span>
                    </button>
                </div>
            </div>

            <div v-if="flash?.success"
                class="flex items-center gap-3 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                <p class="text-emerald-800 dark:text-emerald-200 font-medium text-xs sm:text-sm">{{ flash.success }}</p>
            </div>
            <div v-if="flash?.error"
                class="flex items-center gap-3 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl">
                <AlertTriangle class="w-4 h-4 text-red-600 flex-shrink-0" />
                <p class="text-red-800 dark:text-red-200 font-medium text-xs sm:text-sm">{{ flash.error }}</p>
            </div>

            <div v-if="lowStokCount > 0 && filterStok !== 'low'"
                class="flex items-center gap-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                <AlertTriangle class="w-4 h-4 text-amber-600 flex-shrink-0" />
                <p class="text-amber-800 dark:text-amber-200 font-medium text-xs sm:text-sm">
                    {{ lowStokCount }} sparepart memiliki stok di bawah minimum.
                </p>
                <button @click="filterStok = 'low'" class="ml-auto text-xs font-bold text-amber-600 hover:underline whitespace-nowrap">Lihat</button>
            </div>

            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                        <input v-model="search" type="text" placeholder="Cari kode atau nama sparepart..."
                            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-orange-400 focus:outline-none transition-colors" />
                    </div>
                    <button @click="showFilter = !showFilter"
                        :class="['relative flex items-center gap-1.5 px-3 py-2.5 border rounded-xl text-sm font-medium transition-colors',
                            showFilter || activeFilterCount > 0
                                ? 'bg-orange-500 border-orange-500 text-white'
                                : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-orange-400']">
                        <Filter class="w-4 h-4" />
                        <span v-if="activeFilterCount > 0"
                            class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold">
                            {{ activeFilterCount }}
                        </span>
                    </button>
                </div>

                <div v-if="showFilter" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-3 shadow-sm">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-gray-500 uppercase">Stok</label>
                        <button v-if="activeFilterCount > 0" @click="clearFilters"
                            class="flex items-center gap-1 text-xs text-red-500 font-semibold hover:underline">
                            <X class="w-3 h-3" /> Reset
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="f in [{ v: '', l: 'Semua' }, { v: 'low', l: 'Stok Menipis' }]" :key="f.v"
                            @click="filterStok = f.v"
                            :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                                filterStok === f.v ? 'bg-orange-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
                            {{ f.l }}
                        </button>
                    </div>
                </div>

                <div v-if="filterStok === 'low'" class="flex items-center gap-2 px-1">
                    <span class="flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400 font-semibold">
                        <AlertTriangle class="w-3.5 h-3.5" />
                        Menampilkan sparepart stok menipis
                    </span>
                    <button @click="filterStok = ''" class="ml-auto text-xs text-gray-400 hover:text-gray-600 underline">Hapus filter</button>
                </div>
            </div>

            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Kode</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Nama Sparepart</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Satuan</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Stok</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Min. Stok</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="spareparts.data.length === 0">
                                <td colspan="6" class="py-16 text-center text-gray-400 text-sm">
                                    <Package class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                    <p>Tidak ada data sparepart</p>
                                    <button v-if="filterStok || search" @click="clearFilters"
                                        class="mt-2 text-xs text-orange-500 hover:underline font-semibold">
                                        Hapus filter
                                    </button>
                                </td>
                            </tr>
                            <tr v-for="sp in spareparts.data" :key="sp.id"
                                class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="text-xs font-mono font-bold text-orange-600 dark:text-orange-400">{{ sp.sparepart_code }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ sp.sparepart_name }}</p>
                                    <span v-if="isLow(sp)" class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400 font-semibold mt-0.5">
                                        <AlertTriangle class="w-3 h-3" /> Stok menipis
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ sp.unit }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-lg text-sm font-bold',
                                        isLow(sp)
                                            ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'
                                            : 'text-gray-900 dark:text-white']">
                                        {{ sp.stok }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ sp.stok_minimum }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button @click="openAdj(sp)"
                                            class="p-1.5 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400 rounded-lg hover:bg-emerald-200 transition-colors">
                                            <ArrowUpCircle class="w-3.5 h-3.5" />
                                        </button>
                                        <button @click="openEdit(sp)"
                                            class="p-1.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-lg hover:bg-amber-200 transition-colors">
                                            <Pencil class="w-3.5 h-3.5" />
                                        </button>
                                        <button @click="openDelete(sp)"
                                            class="p-1.5 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-200 transition-colors">
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="spareparts.links && spareparts.links.length > 3" class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-500">
                        <template v-if="spareparts.meta?.from">
                            {{ spareparts.meta.from }}–{{ spareparts.meta.to }} dari {{ spareparts.meta.total }}
                        </template>
                        <template v-else>
                            {{ spareparts.data.length }} item
                        </template>
                    </p>
                    <div class="flex gap-1">
                        <button v-for="link in spareparts.links" :key="link.label"
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
                <div v-if="spareparts.data.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <Package class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                    <p class="text-gray-400 text-sm">Tidak ada data sparepart</p>
                    <button v-if="filterStok || search" @click="clearFilters"
                        class="mt-2 text-xs text-orange-500 hover:underline font-semibold">
                        Hapus filter
                    </button>
                </div>
                <div v-for="sp in spareparts.data" :key="sp.id"
                    :class="['bg-white dark:bg-gray-800 rounded-2xl border shadow-sm overflow-hidden',
                        isLow(sp) ? 'border-amber-300 dark:border-amber-700' : 'border-gray-100 dark:border-gray-700']">
                    <div class="p-3.5">
                        <div class="flex items-start justify-between gap-2 mb-2.5">
                            <div class="min-w-0">
                                <p class="text-xs font-mono font-bold text-orange-600 dark:text-orange-400">{{ sp.sparepart_code }}</p>
                                <p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5 truncate">{{ sp.sparepart_name }}</p>
                            </div>
                            <span v-if="isLow(sp)"
                                class="flex items-center gap-1 px-2 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-full text-xs font-bold flex-shrink-0">
                                <AlertTriangle class="w-3 h-3" /> Menipis
                            </span>
                        </div>
                        <div class="flex items-center gap-5 mb-3">
                            <div class="text-center">
                                <p :class="['text-2xl font-black tabular-nums', isLow(sp) ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white']">{{ sp.stok }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">Stok</p>
                            </div>
                            <div class="w-px h-8 bg-gray-100 dark:bg-gray-700"></div>
                            <div class="text-center">
                                <p class="text-2xl font-black tabular-nums text-gray-300 dark:text-gray-600">{{ sp.stok_minimum }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">Min.</p>
                            </div>
                            <div class="w-px h-8 bg-gray-100 dark:bg-gray-700"></div>
                            <div class="text-center">
                                <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ sp.unit }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">Satuan</p>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-2.5 border-t border-gray-100 dark:border-gray-700">
                            <button @click="openAdj(sp)"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400 rounded-xl text-xs font-bold hover:bg-emerald-200 active:scale-95 transition-all">
                                <ArrowUpCircle class="w-3.5 h-3.5" /> Tambah Stok
                            </button>
                            <button @click="openEdit(sp)"
                                class="flex items-center justify-center py-2 px-3.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-xl hover:bg-amber-200 active:scale-95 transition-all">
                                <Pencil class="w-3.5 h-3.5" />
                            </button>
                            <button @click="openDelete(sp)"
                                class="flex items-center justify-center py-2 px-3.5 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-xl hover:bg-red-200 active:scale-95 transition-all">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="spareparts.links && spareparts.links.length > 3" class="flex justify-center gap-1 pt-2">
                    <button v-for="link in spareparts.links" :key="link.label"
                        @click="link.url && router.visit(link.url)"
                        :disabled="!link.url"
                        v-html="link.label"
                        :class="['px-3 py-1.5 text-xs rounded-lg font-semibold',
                            link.active ? 'bg-orange-500 text-white' : link.url ? 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' : 'opacity-40 cursor-default']">
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showAddModal" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="p-5">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4">Tambah Sparepart</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Kode Sparepart</label>
                            <input v-model="form.sparepart_code" type="text" placeholder="SP-001"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Nama Sparepart</label>
                            <input v-model="form.sparepart_name" type="text" placeholder="Nama sparepart..."
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400" />
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Satuan</label>
                                <input v-model="form.unit" type="text" placeholder="pcs"
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Stok Awal</label>
                                <input v-model.number="form.stok" type="number" min="0"
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Min. Stok</label>
                                <input v-model.number="form.stok_minimum" type="number" min="0"
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400" />
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-5">
                        <button @click="showAddModal = false"
                            class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button @click="submitAdd"
                            class="flex-1 py-3 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold text-sm active:scale-95 transition-all">
                            Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showEditModal && selectedSp" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="p-5">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4">Edit Sparepart</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Kode Sparepart</label>
                            <input v-model="form.sparepart_code" type="text"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Nama Sparepart</label>
                            <input v-model="form.sparepart_name" type="text"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400" />
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Satuan</label>
                                <input v-model="form.unit" type="text"
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Stok</label>
                                <input v-model.number="form.stok" type="number" min="0"
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Min. Stok</label>
                                <input v-model.number="form.stok_minimum" type="number" min="0"
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400" />
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-5">
                        <button @click="showEditModal = false"
                            class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button @click="submitEdit"
                            class="flex-1 py-3 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold text-sm active:scale-95 transition-all">
                            Update
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showAdjModal && selectedSp" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center bg-emerald-100 dark:bg-emerald-900/40">
                            <ArrowUpCircle class="w-5 h-5 text-emerald-600" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">Tambah Stok</h3>
                            <p class="text-xs text-gray-400">{{ selectedSp.sparepart_name }} · Stok saat ini: <span class="font-bold text-gray-600 dark:text-gray-300">{{ selectedSp.stok }} {{ selectedSp.unit }}</span></p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Jumlah Tambah</label>
                            <div class="flex items-center gap-2">
                                <button @click="adjQty = Math.max(1, (adjQty || 1) - 1)"
                                    class="w-10 h-10 flex items-center justify-center bg-gray-100 dark:bg-gray-700 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-200 active:scale-95 transition-all text-lg flex-shrink-0">
                                    −
                                </button>
                                <input v-model.number="adjQty" type="number" min="1" @blur="clampQty"
                                    class="flex-1 text-center px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm font-bold focus:outline-none focus:border-orange-400" />
                                <button @click="adjQty = (adjQty || 0) + 1"
                                    class="w-10 h-10 flex items-center justify-center bg-gray-100 dark:bg-gray-700 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-200 active:scale-95 transition-all text-lg flex-shrink-0">
                                    +
                                </button>
                            </div>
                            <p v-if="adjQty >= 1" class="text-xs text-gray-400 mt-1.5 text-center">
                                Stok akan menjadi <span class="font-bold text-gray-700 dark:text-gray-200">{{ selectedSp.stok + (adjQty || 0) }} {{ selectedSp.unit }}</span>
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Catatan (opsional)</label>
                            <input v-model="adjNotes" type="text" placeholder="Keterangan pengadaan..."
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400" />
                        </div>
                    </div>
                    <div class="flex gap-3 mt-5">
                        <button @click="showAdjModal = false"
                            class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button @click="submitAdj" :disabled="!adjQty || adjQty < 1"
                            :class="['flex-1 py-3 text-white rounded-xl font-bold text-sm active:scale-95 transition-all',
                                !adjQty || adjQty < 1 ? 'bg-gray-300 dark:bg-gray-600 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700']">
                            Tambah
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDelModal && selectedSp" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="p-5 text-center">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-3">
                        <AlertTriangle class="w-6 h-6 text-red-600" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">Hapus Sparepart?</h3>
                    <p class="text-sm text-gray-700 dark:text-gray-300 font-semibold mb-0.5">{{ selectedSp.sparepart_name }}</p>
                    <p class="text-xs text-gray-400 mb-5">Sparepart tidak dapat dihapus jika sudah memiliki riwayat pemakaian.</p>
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

        <div v-if="showBulkModal" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex-shrink-0">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Layers class="w-4 h-4 text-orange-500" /> Bulk Update Stok & Min. Stok
                    </h3>
                    <button @click="showBulkModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <X class="w-4 h-4 text-gray-500" />
                    </button>
                </div>

                <div class="p-5 space-y-4 overflow-y-auto flex-1">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1.5">Cari & Tambahkan Sparepart</label>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                            <input v-model="bulkSearch" type="text"
                                :placeholder="isFetchingAll ? 'Memuat data...' : 'Cari kode atau nama sparepart...'"
                                :disabled="isFetchingAll"
                                class="w-full pl-9 pr-4 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400 disabled:opacity-50" />
                        </div>
                        <div v-if="isFetchingAll" class="mt-2 flex items-center gap-2 text-xs text-gray-400 px-1">
                            <svg class="w-3.5 h-3.5 animate-spin text-orange-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Memuat semua sparepart...
                        </div>
                        <div v-if="!isFetchingAll && bulkSearch && filteredBulkCandidates.length > 0"
                            class="mt-1 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden max-h-40 overflow-y-auto">
                            <button v-for="sp in filteredBulkCandidates.slice(0, 8)" :key="sp.id"
                                @click="addToBulk(sp)"
                                class="w-full text-left px-3 py-2 text-xs hover:bg-orange-50 dark:hover:bg-orange-900/20 flex items-center justify-between gap-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ sp.sparepart_name }}</span>
                                <span class="text-gray-400 whitespace-nowrap font-mono">{{ sp.sparepart_code }} · {{ sp.stok }} {{ sp.unit }}</span>
                            </button>
                        </div>
                        <p v-if="!isFetchingAll && bulkSearch && filteredBulkCandidates.length === 0"
                            class="text-xs text-gray-400 mt-1.5 px-1">
                            Tidak ada sparepart yang cocok atau sudah ditambahkan.
                        </p>
                    </div>

                    <div v-if="bulkItems.length > 0">
                        <div class="hidden lg:grid grid-cols-12 gap-2 px-1 mb-1">
                            <p class="col-span-4 text-xs font-semibold text-gray-400">Sparepart</p>
                            <p class="col-span-2 text-xs font-semibold text-gray-400 text-center">Stok Skrg</p>
                            <p class="col-span-3 text-xs font-semibold text-gray-400 text-center">Tambah Stok</p>
                            <p class="col-span-2 text-xs font-semibold text-gray-400 text-center">Min. Stok</p>
                            <p class="col-span-1"></p>
                        </div>
                        <div class="space-y-2">
                            <div v-for="item in bulkItems" :key="item.id" class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3">
                                <div class="flex items-start justify-between gap-2 mb-2 lg:hidden">
                                    <div>
                                        <p class="text-xs font-mono font-bold text-orange-600 dark:text-orange-400">{{ item.sparepart_code }}</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ item.sparepart_name }}</p>
                                        <p class="text-xs text-gray-400">Stok saat ini: <span class="font-bold text-gray-600 dark:text-gray-300">{{ item.stok }} {{ item.unit }}</span></p>
                                    </div>
                                    <button @click="removeFromBulk(item.id)" class="p-1 text-gray-400 hover:text-red-500 transition-colors flex-shrink-0">
                                        <X class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 lg:grid-cols-12 gap-2 items-center">
                                    <div class="hidden lg:block lg:col-span-4">
                                        <p class="text-xs font-mono font-bold text-orange-600 dark:text-orange-400">{{ item.sparepart_code }}</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ item.sparepart_name }}</p>
                                    </div>
                                    <div class="hidden lg:flex lg:col-span-2 items-center justify-center">
                                        <span class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ item.stok }} <span class="text-xs font-normal text-gray-400">{{ item.unit }}</span></span>
                                    </div>
                                    <div class="col-span-1 lg:col-span-3">
                                        <label class="block text-xs text-gray-400 mb-1 lg:hidden">Tambah Stok</label>
                                        <input v-model.number="item.stok_tambah" type="number" min="0" placeholder="0"
                                            class="w-full px-2 py-2 border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-center font-bold focus:outline-none focus:border-emerald-400" />
                                        <p v-if="item.stok_tambah && item.stok_tambah > 0" class="text-xs text-emerald-600 dark:text-emerald-400 text-center mt-0.5">→ {{ item.stok + item.stok_tambah }}</p>
                                    </div>
                                    <div class="col-span-1 lg:col-span-2">
                                        <label class="block text-xs text-gray-400 mb-1 lg:hidden">Min. Stok Baru</label>
                                        <input v-model.number="item.new_stok_minimum" type="number" min="0" :placeholder="String(item.stok_minimum)"
                                            class="w-full px-2 py-2 border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-center font-bold focus:outline-none focus:border-orange-400" />
                                    </div>
                                    <div class="hidden lg:flex lg:col-span-1 items-center justify-center">
                                        <button @click="removeFromBulk(item.id)" class="p-1.5 text-gray-400 hover:text-red-500 transition-colors">
                                            <X class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="bulkItems.length === 0 && !isFetchingAll" class="py-8 text-center text-gray-400 text-sm">
                        <Layers class="w-8 h-8 mx-auto mb-2 text-gray-300" />
                        Cari dan tambahkan sparepart di atas untuk mulai bulk update.
                    </div>

                    <div v-if="bulkItems.length > 0">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Catatan (opsional)</label>
                        <input v-model="bulkNotes" type="text" placeholder="Keterangan pengadaan batch ini..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400" />
                    </div>
                </div>

                <div class="flex gap-3 p-5 border-t border-gray-100 dark:border-gray-700 flex-shrink-0">
                    <button @click="showBulkModal = false"
                        class="px-5 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                        Batal
                    </button>
                    <button @click="submitBulk" :disabled="!bulkHasChanges || bulkItems.length === 0"
                        :class="['flex-1 py-3 text-white rounded-xl font-bold text-sm active:scale-95 transition-all',
                            !bulkHasChanges || bulkItems.length === 0 ? 'bg-gray-300 dark:bg-gray-600 cursor-not-allowed' : 'bg-orange-500 hover:bg-orange-600']">
                        Simpan Semua ({{ bulkItems.filter(b => (b.stok_tambah ?? 0) > 0 || b.new_stok_minimum !== null).length }} item)
                    </button>
                </div>
            </div>
        </div>

    </AppLayout>
</template>
