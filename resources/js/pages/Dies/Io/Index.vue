<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    Cpu, Search, Plus, Pencil, Trash2,
    AlertTriangle, CheckCircle2, X, Package
} from 'lucide-vue-next';

interface Io {
    id: number;
    nama: string;
    cc: string | null;
    io_number: string | null;
    keterangan: string | null;
    spareparts_count: number;
}

interface Props {
    ios: { data: Io[]; links: any[]; meta: any };
    totalCount: number;
    filters: { search?: string };
}

const props = defineProps<Props>();
const page  = usePage();
const flash = computed(() => (page.props as any).flash);

const search      = ref(props.filters.search ?? '');
const showAdd     = ref(false);
const showEdit    = ref(false);
const showDel     = ref(false);
const selectedIo  = ref<Io | null>(null);

const form = ref({ nama: '', cc: '', io_number: '', keterangan: '' });

let debounce: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => navigate(), 400);
});

const navigate = () => router.get('/dies/io',
    { search: search.value },
    { preserveState: true, preserveScroll: true, replace: true });

const openAdd = () => {
    form.value = { nama: '', cc: '', io_number: '', keterangan: '' };
    showAdd.value = true;
};

const openEdit = (io: Io) => {
    selectedIo.value = io;
    form.value = {
        nama:       io.nama,
        cc:         io.cc ?? '',
        io_number:  io.io_number ?? '',
        keterangan: io.keterangan ?? '',
    };
    showEdit.value = true;
};

const openDel = (io: Io) => { selectedIo.value = io; showDel.value = true; };

const submitAdd = () => {
    router.post('/dies/io', form.value, { onSuccess: () => { showAdd.value = false; } });
};

const submitEdit = () => {
    if (!selectedIo.value) return;
    router.put(`/dies/io/${selectedIo.value.id}`, form.value, {
        onSuccess: () => { showEdit.value = false; selectedIo.value = null; },
    });
};

const submitDel = () => {
    if (!selectedIo.value) return;
    router.delete(`/dies/io/${selectedIo.value.id}`, {
        onSuccess: () => { showDel.value = false; selectedIo.value = null; },
    });
};

const lastPage = computed(() => props.ios.meta?.last_page ?? 1);
</script>

<template>
    <Head title="Master IO" />
    <AppLayout :breadcrumbs="[
        { title: 'Dies', href: '/dies' },
        { title: 'Sparepart', href: '/dies/sparepart' },
        { title: 'Master IO', href: '/dies/io' },
    ]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-indigo-500 rounded-xl flex-shrink-0">
                            <Cpu class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
                        </span>
                        Master IO
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">{{ totalCount }} IO terdaftar</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="router.visit('/dies/sparepart')"
                        class="flex items-center gap-1.5 px-3 py-2 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-semibold hover:border-indigo-400 active:scale-95 transition-all">
                        <Package class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Master Sparepart</span>
                    </button>
                    <button @click="openAdd"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-xl text-xs sm:text-sm font-semibold active:scale-95 transition-all shadow-sm">
                        <Plus class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Tambah IO</span>
                        <span class="sm:hidden">Tambah</span>
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

            <div class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input v-model="search" type="text" placeholder="Cari nama, CC, atau nomor IO..."
                    class="w-full pl-9 pr-4 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-800 text-sm focus:border-indigo-400 focus:outline-none transition-colors" />
            </div>

            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Nama IO</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">CC</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Nomor IO</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Keterangan</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Sparepart</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wide">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-if="ios.data.length === 0">
                                <td colspan="6" class="py-16 text-center text-gray-400 text-sm">
                                    <Cpu class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                    Tidak ada data IO
                                </td>
                            </tr>
                            <tr v-for="io in ios.data" :key="io.id"
                                class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ io.nama }}</p>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-xs font-mono font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded-lg">
                                        {{ io.cc ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-xs font-mono font-semibold text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-900/30 px-2 py-0.5 rounded-lg">
                                        {{ io.io_number ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ io.keterangan ?? '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-2.5 py-1 rounded-lg">
                                        <Package class="w-3 h-3" /> {{ io.spareparts_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button @click="openEdit(io)"
                                            class="p-1.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-lg hover:bg-amber-200 transition-colors">
                                            <Pencil class="w-3.5 h-3.5" />
                                        </button>
                                        <button @click="openDel(io)"
                                            class="p-1.5 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-200 transition-colors">
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="lastPage > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-500">
                        {{ ios.meta?.from }}–{{ ios.meta?.to }} dari {{ ios.meta?.total }}
                    </p>
                    <div class="flex gap-1">
                        <button v-for="link in ios.links" :key="link.label"
                            @click="link.url && router.visit(link.url)"
                            :disabled="!link.url"
                            v-html="link.label"
                            :class="['px-3 py-1.5 text-xs rounded-lg font-semibold transition-colors',
                                link.active ? 'bg-indigo-500 text-white' : link.url ? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200' : 'opacity-40 cursor-default bg-gray-100 dark:bg-gray-700 text-gray-400']">
                        </button>
                    </div>
                </div>
            </div>

            <div class="lg:hidden space-y-2.5">
                <div v-if="ios.data.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <Cpu class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                    <p class="text-gray-400 text-sm">Tidak ada data IO</p>
                </div>
                <div v-for="io in ios.data" :key="io.id"
                    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4">
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <div>
                            <p class="text-base font-bold text-gray-900 dark:text-white">{{ io.nama }}</p>
                            <p v-if="io.keterangan" class="text-xs text-gray-400 mt-0.5">{{ io.keterangan }}</p>
                        </div>
                        <div class="flex gap-1.5 flex-shrink-0">
                            <button @click="openEdit(io)"
                                class="p-1.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-lg hover:bg-amber-200 transition-colors">
                                <Pencil class="w-3.5 h-3.5" />
                            </button>
                            <button @click="openDel(io)"
                                class="p-1.5 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-200 transition-colors">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <div class="flex flex-col items-center bg-indigo-50 dark:bg-indigo-900/20 rounded-xl px-3 py-2 min-w-[100px]">
                            <p class="text-xs text-gray-400 mb-0.5">CC</p>
                            <p class="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ io.cc ?? '—' }}</p>
                        </div>
                        <div class="flex flex-col items-center bg-violet-50 dark:bg-violet-900/20 rounded-xl px-3 py-2 min-w-[120px]">
                            <p class="text-xs text-gray-400 mb-0.5">Nomor IO</p>
                            <p class="text-xs font-mono font-bold text-violet-600 dark:text-violet-400">{{ io.io_number ?? '—' }}</p>
                        </div>
                        <div class="flex flex-col items-center bg-gray-50 dark:bg-gray-700 rounded-xl px-3 py-2 min-w-[80px]">
                            <p class="text-xs text-gray-400 mb-0.5">Sparepart</p>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ io.spareparts_count }}</p>
                        </div>
                    </div>
                </div>
                <div v-if="lastPage > 1" class="flex justify-center gap-1 pt-2">
                    <button v-for="link in ios.links" :key="link.label"
                        @click="link.url && router.visit(link.url)"
                        :disabled="!link.url"
                        v-html="link.label"
                        :class="['px-3 py-1.5 text-xs rounded-lg font-semibold',
                            link.active ? 'bg-indigo-500 text-white' : link.url ? 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' : 'opacity-40 cursor-default']">
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showAdd" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Cpu class="w-4 h-4 text-indigo-500" /> Tambah IO
                    </h3>
                    <button @click="showAdd = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <X class="w-4 h-4 text-gray-500" />
                    </button>
                </div>
                <div class="p-5 space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Nama IO <span class="text-red-500">*</span></label>
                        <input v-model="form.nama" type="text" placeholder="Contoh: REGULER, TMMIN, HYUNDAI..."
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-indigo-400" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">CC</label>
                            <input v-model="form.cc" type="text" placeholder="1101110000"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm font-mono focus:outline-none focus:border-indigo-400" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Nomor IO</label>
                            <input v-model="form.io_number" type="text" placeholder="1102001589"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm font-mono focus:outline-none focus:border-indigo-400" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Keterangan</label>
                        <input v-model="form.keterangan" type="text" placeholder="Deskripsi tambahan (opsional)"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-indigo-400" />
                    </div>
                    <div class="flex gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <button @click="showAdd = false"
                            class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button @click="submitAdd" :disabled="!form.nama"
                            :class="['flex-1 py-3 text-white rounded-xl font-bold text-sm active:scale-95 transition-all',
                                !form.nama ? 'bg-gray-300 dark:bg-gray-600 cursor-not-allowed' : 'bg-indigo-500 hover:bg-indigo-600']">
                            Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showEdit && selectedIo" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Cpu class="w-4 h-4 text-indigo-500" /> Edit IO
                    </h3>
                    <button @click="showEdit = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <X class="w-4 h-4 text-gray-500" />
                    </button>
                </div>
                <div class="p-5 space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Nama IO <span class="text-red-500">*</span></label>
                        <input v-model="form.nama" type="text"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-indigo-400" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">CC</label>
                            <input v-model="form.cc" type="text"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm font-mono focus:outline-none focus:border-indigo-400" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Nomor IO</label>
                            <input v-model="form.io_number" type="text"
                                class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm font-mono focus:outline-none focus:border-indigo-400" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Keterangan</label>
                        <input v-model="form.keterangan" type="text"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:outline-none focus:border-indigo-400" />
                    </div>
                    <div class="flex gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <button @click="showEdit = false"
                            class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button @click="submitEdit" :disabled="!form.nama"
                            :class="['flex-1 py-3 text-white rounded-xl font-bold text-sm active:scale-95 transition-all',
                                !form.nama ? 'bg-gray-300 dark:bg-gray-600 cursor-not-allowed' : 'bg-indigo-500 hover:bg-indigo-600']">
                            Update
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDel && selectedIo" class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="p-5 text-center">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-3">
                        <AlertTriangle class="w-6 h-6 text-red-600" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">Hapus IO?</h3>
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-0.5">{{ selectedIo.nama }}</p>
                    <p class="text-xs text-gray-400 mb-5">IO tidak dapat dihapus jika masih digunakan oleh sparepart.</p>
                    <div class="flex gap-3">
                        <button @click="showDel = false"
                            class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">
                            Batal
                        </button>
                        <button @click="submitDel"
                            class="flex-1 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 font-bold text-sm active:scale-95 transition-all">
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
