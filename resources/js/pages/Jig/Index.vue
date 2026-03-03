<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Wrench, Plus, Pencil, Trash2, X, Search } from 'lucide-vue-next';

interface User { id: number; name: string; }
interface Jig  {
    id: number; type: string; name: string;
    kategori: 'regular' | 'slow_moving' | 'discontinue';
    line: string; pic_id: number; pic: User;
}

interface Props {
    jigs:    Jig[];
    users:   User[];
    lines:   string[];
    filters: { kategori?: string; line?: string; search?: string };
}

const props = defineProps<Props>();

const showModal  = ref(false);
const editTarget = ref<Jig | null>(null);

const filterKategori = ref(props.filters.kategori ?? '');
const filterLine     = ref(props.filters.line     ?? '');
const filterSearch   = ref(props.filters.search   ?? '');

let searchTimer: any = null;
watch([filterKategori, filterLine], () => {
    router.get('/jig', {
        kategori: filterKategori.value,
        line:     filterLine.value,
        search:   filterSearch.value,
    }, { preserveState: true, preserveScroll: true });
});

watch(filterSearch, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/jig', {
            kategori: filterKategori.value,
            line:     filterLine.value,
            search:   val,
        }, { preserveState: true, preserveScroll: true });
    }, 400);
});

const form = useForm({
    type:     '',
    name:     '',
    kategori: 'regular' as 'regular' | 'slow_moving' | 'discontinue',
    line:     '',
    pic_id:   null as number | null,
});

const openCreate = () => { editTarget.value = null; form.reset(); showModal.value = true; };
const openEdit   = (jig: Jig) => {
    editTarget.value = jig;
    form.type     = jig.type;
    form.name     = jig.name;
    form.kategori = jig.kategori;
    form.line     = jig.line;
    form.pic_id   = jig.pic_id;
    showModal.value = true;
};
const closeModal = () => { showModal.value = false; editTarget.value = null; form.reset(); };

const submit = () => {
    if (editTarget.value) {
        form.put(`/jig/${editTarget.value.id}`, { onSuccess: closeModal });
    } else {
        form.post('/jig', { onSuccess: closeModal });
    }
};

const destroy = (jig: Jig) => {
    if (confirm(`Hapus JIG "${jig.name}"?`)) {
        router.delete(`/jig/${jig.id}`, { preserveScroll: true });
    }
};

const kategoriConfig: Record<string, { label: string; class: string }> = {
    regular:      { label: 'Regular',      class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' },
    slow_moving:  { label: 'Slow Moving',  class: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300' },
    discontinue:  { label: 'Discontinue',  class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' },
};
</script>

<template>
    <Head title="Master JIG" />
    <AppLayout :breadcrumbs="[{ title: 'JIG', href: '/jig/dashboard' }, { title: 'Master JIG', href: '/jig' }]">
        <div class="p-6 space-y-6 bg-white dark:bg-gray-900 min-h-screen">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent flex items-center gap-3">
                        <Wrench class="w-8 h-8 text-indigo-600" /> Master JIG
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Data master jig untuk maintenance</p>
                </div>
                <button @click="openCreate"
                    class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-500 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium">
                    <Plus class="w-4 h-4" /> Tambah JIG
                </button>
            </div>

            <!-- Filter -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 shadow-lg border border-gray-100 dark:border-gray-700">
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Search -->
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input v-model="filterSearch" type="text" placeholder="Cari nama / type..."
                            class="pl-9 pr-3 py-2 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-indigo-500 text-sm w-52" />
                    </div>
                    <!-- Kategori -->
                    <select v-model="filterKategori"
                        class="px-3 py-2 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-indigo-500 text-sm">
                        <option value="">Semua Kategori</option>
                        <option value="regular">Regular</option>
                        <option value="slow_moving">Slow Moving</option>
                        <option value="discontinue">Discontinue</option>
                    </select>
                    <!-- Line -->
                    <select v-model="filterLine"
                        class="px-3 py-2 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-indigo-500 text-sm">
                        <option value="">Semua Line</option>
                        <option v-for="l in lines" :key="l" :value="l">{{ l }}</option>
                    </select>
                    <span class="text-xs text-gray-400">{{ jigs.length }} JIG ditemukan</span>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-gray-700 dark:to-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Nama JIG</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Type</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Kategori</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Line</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">PIC</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-if="jigs.length === 0">
                                <td colspan="6" class="py-16 text-center text-gray-400 text-sm">Belum ada data JIG</td>
                            </tr>
                            <tr v-for="jig in jigs" :key="jig.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-4 py-3 text-xs font-bold text-gray-900 dark:text-white">{{ jig.name }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ jig.type }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-bold', kategoriConfig[jig.kategori].class]">
                                        {{ kategoriConfig[jig.kategori].label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-xs font-semibold">{{ jig.line }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                            {{ jig.pic?.name?.charAt(0)?.toUpperCase() }}
                                        </div>
                                        <span class="text-xs text-gray-700 dark:text-gray-300">{{ jig.pic?.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        <button @click="openEdit(jig)" class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors">
                                            <Pencil class="w-4 h-4" />
                                        </button>
                                        <button @click="destroy(jig)" class="p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
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

        <!-- Create / Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 backdrop-blur-sm bg-white/30 dark:bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full shadow-2xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ editTarget ? 'Edit JIG' : 'Tambah JIG' }}</h2>
                    <button @click="closeModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Nama JIG <span class="text-red-500">*</span></label>
                        <input v-model="form.name" type="text" required placeholder="Nama lengkap JIG"
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-indigo-500 text-sm" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Type <span class="text-red-500">*</span></label>
                        <input v-model="form.type" type="text" required placeholder="Contoh: T86A, 2MM"
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-indigo-500 text-sm" />
                        <p v-if="form.errors.type" class="mt-1 text-xs text-red-600">{{ form.errors.type }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Kategori <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <button v-for="k in ['regular', 'slow_moving', 'discontinue']" :key="k" type="button"
                                @click="form.kategori = k as any"
                                :class="['flex-1 py-2 rounded-xl font-semibold text-xs transition-all',
                                    form.kategori === k
                                        ? k === 'regular' ? 'bg-green-500 text-white shadow-md'
                                            : k === 'slow_moving' ? 'bg-yellow-500 text-white shadow-md'
                                            : 'bg-red-500 text-white shadow-md'
                                        : 'border-2 border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300']">
                                {{ kategoriConfig[k].label }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Line <span class="text-red-500">*</span></label>
                        <input v-model="form.line" type="text" required placeholder="Contoh: G-PART, D26A"
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-indigo-500 text-sm" />
                        <p v-if="form.errors.line" class="mt-1 text-xs text-red-600">{{ form.errors.line }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">PIC <span class="text-red-500">*</span></label>
                        <select v-model="form.pic_id" required
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-indigo-500 text-sm">
                            <option :value="null" disabled>Pilih PIC</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <p v-if="form.errors.pic_id" class="mt-1 text-xs text-red-600">{{ form.errors.pic_id }}</p>
                    </div>
                    <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit" :disabled="form.processing"
                            class="flex-1 px-6 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-500 text-white rounded-xl hover:shadow-lg disabled:opacity-50 transition-all duration-300 font-medium">
                            {{ form.processing ? 'Menyimpan...' : editTarget ? 'Update JIG' : 'Simpan JIG' }}
                        </button>
                        <button type="button" @click="closeModal"
                            class="px-6 py-2.5 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all duration-300 font-medium">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
