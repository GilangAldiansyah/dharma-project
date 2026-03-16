<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    History, Search, Filter, Trash2, AlertTriangle, CheckCircle2,
    Package, Calendar, User, Wrench, ArrowUpCircle, ArrowDownCircle
} from 'lucide-vue-next';

interface Sparepart { id: number; sparepart_code: string; sparepart_name: string }
interface Dies { id_sap: string; no_part: string; nama_dies: string; line: string }
interface HistoryItem {
    id: number;
    tipe: 'preventive' | 'corrective' | 'reguler';
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
    histories: { data: HistoryItem[]; links: any[]; meta: any };
    spareparts: Sparepart[];
    dies: Dies[];
    filters: { tipe?: string; sparepart_id?: string; dies_id?: string };
}

const props = defineProps<Props>();
const page  = usePage();
const flash = computed(() => (page.props as any).flash);

const filterTipe      = ref(props.filters.tipe        ?? '');
const filterSparepart = ref(props.filters.sparepart_id ?? '');
const filterDies      = ref(props.filters.dies_id     ?? '');
const showFilter      = ref(false);
const showDelModal    = ref(false);
const selectedH       = ref<HistoryItem | null>(null);

const tipeCfg: Record<string, { label: string; badge: string; dot: string }> = {
    preventive: { label: 'Preventive', badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',     dot: 'bg-blue-500' },
    corrective: { label: 'Corrective', badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',         dot: 'bg-red-500'  },
    reguler:    { label: 'Reguler',    badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',         dot: 'bg-gray-400' },
};

const activeFilterCount = computed(() =>
    [filterTipe.value, filterSparepart.value, filterDies.value].filter(Boolean).length);

watch([filterTipe, filterSparepart, filterDies], () => navigate());

const navigate = () => router.get('/dies/sparepart/history',
    { tipe: filterTipe.value, sparepart_id: filterSparepart.value, dies_id: filterDies.value },
    { preserveState: true, preserveScroll: true, replace: true });

const fmtDate = (d: string | null) => !d ? '-' : new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
const fmtDatetime = (d: string) => new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const openDelete = (h: HistoryItem) => { selectedH.value = h; showDelModal.value = true; };
const submitDelete = () => {
    if (!selectedH.value) return;
    router.delete(`/dies/sparepart/history/${selectedH.value.id}`, {
        onSuccess: () => { showDelModal.value = false; selectedH.value = null; },
    });
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

            <!-- Header -->
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-orange-500 rounded-xl flex-shrink-0">
                            <History class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
                        </span>
                        History Pemakaian Sparepart
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">{{ histories.meta?.total ?? 0 }} riwayat</p>
                </div>
                <button @click="router.visit('/dies/sparepart')"
                    class="flex items-center gap-1.5 px-3 py-2 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-semibold hover:border-orange-400 active:scale-95 transition-all flex-shrink-0">
                    <Package class="w-3.5 h-3.5" />
                    <span class="hidden sm:inline">Master Sparepart</span>
                </button>
            </div>

            <!-- Flash -->
            <div v-if="flash?.success"
                class="flex items-center gap-3 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                <p class="text-emerald-800 dark:text-emerald-200 font-medium text-xs sm:text-sm">{{ flash.success }}</p>
            </div>

            <!-- Summary Chips -->
            <div class="flex gap-2 overflow-x-auto scrollbar-none pb-0.5">
                <button v-for="[v, c] in Object.entries(tipeCfg)" :key="v"
                    @click="filterTipe = filterTipe === v ? '' : v"
                    :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all flex-shrink-0',
                        filterTipe === v ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:border-orange-300']">
                    <span :class="['w-1.5 h-1.5 rounded-full', c.dot]"></span>
                    {{ c.label }}
                    <span class="font-bold">{{ histories.data.filter(x => x.tipe === v).length }}</span>
                </button>
            </div>

            <!-- Filter -->
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <div class="flex-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ histories.meta?.total ?? 0 }} riwayat ditemukan
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

                <div v-if="showFilter" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-3 space-y-3 shadow-sm">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Tipe</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="t in [{ v: '', l: 'Semua' }, ...Object.entries(tipeCfg).map(([v, c]) => ({ v, l: c.label }))]" :key="t.v"
                                @click="filterTipe = t.v"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                                    filterTipe === t.v ? 'bg-orange-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
                                {{ t.l }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Sparepart</label>
                        <select v-model="filterSparepart"
                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400">
                            <option value="">Semua Sparepart</option>
                            <option v-for="sp in spareparts" :key="sp.id" :value="sp.id">
                                {{ sp.sparepart_code }} — {{ sp.sparepart_name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Dies</label>
                        <select v-model="filterDies"
                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-orange-400">
                            <option value="">Semua Dies</option>
                            <option v-for="d in dies" :key="d.id_sap" :value="d.id_sap">
                                {{ d.no_part }} — {{ d.nama_dies }} ({{ d.line }})
                            </option>
                        </select>
                    </div>
                    <div class="flex justify-end">
                        <button @click="filterTipe = ''; filterSparepart = ''; filterDies = ''"
                            class="text-xs text-orange-500 font-semibold hover:underline">Reset filter</button>
                    </div>
                </div>
            </div>

            <!-- Desktop Table -->
            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Tipe</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Sparepart</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Dies</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Qty</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Catatan</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Dicatat oleh</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Tanggal</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="histories.data.length === 0">
                                <td colspan="8" class="py-16 text-center text-gray-400 text-sm">
                                    <History class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                    Tidak ada riwayat pemakaian
                                </td>
                            </tr>
                            <tr v-for="h in histories.data" :key="h.id"
                                class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
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
                                <td class="px-4 py-3">
                                    <template v-if="h.dies">
                                        <p class="text-xs font-bold text-gray-900 dark:text-white">{{ h.dies.no_part }}</p>
                                        <p class="text-xs text-gray-400">{{ h.dies.nama_dies }}</p>
                                    </template>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ h.quantity }}</span>
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
                <div v-if="histories.meta?.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-500">{{ histories.meta.from }}–{{ histories.meta.to }} dari {{ histories.meta.total }}</p>
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

            <!-- Mobile Cards -->
            <div class="lg:hidden space-y-2.5">
                <div v-if="histories.data.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <p class="text-gray-400 text-sm">Tidak ada riwayat pemakaian</p>
                </div>
                <div v-for="h in histories.data" :key="h.id"
                    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
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
                                <span class="text-lg font-black text-gray-900 dark:text-white">{{ h.quantity }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-gray-500 mb-3">
                            <span class="flex items-center gap-1"><User class="w-3 h-3" /> {{ h.created_by?.name ?? '—' }}</span>
                            <span class="flex items-center gap-1"><Calendar class="w-3 h-3" /> {{ fmtDate(h.created_at) }}</span>
                        </div>
                        <p v-if="h.notes" class="text-xs text-gray-500 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-2.5 py-1.5 mb-3">{{ h.notes }}</p>
                        <div class="flex justify-end pt-2.5 border-t border-gray-100 dark:border-gray-700">
                            <button @click="openDelete(h)"
                                class="flex items-center gap-1.5 py-2 px-3 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-xl text-xs font-bold hover:bg-red-200 active:scale-95 transition-all">
                                <Trash2 class="w-3.5 h-3.5" /> Hapus & Kembalikan Stok
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="histories.meta?.last_page > 1" class="flex justify-center gap-1 pt-2">
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

        <!-- Delete Modal -->
        <div v-if="showDelModal && selectedH" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="p-5 text-center">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-3">
                        <AlertTriangle class="w-6 h-6 text-red-600" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">Hapus Riwayat?</h3>
                    <p class="text-sm text-gray-500 mb-0.5">{{ selectedH.sparepart?.sparepart_name }}</p>
                    <p class="text-xs text-gray-400 mb-5">Stok sebanyak <strong>{{ selectedH.quantity }}</strong> akan dikembalikan ke master sparepart.</p>
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
