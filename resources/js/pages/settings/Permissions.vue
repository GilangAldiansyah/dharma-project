<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface Permission {
    id: number;
    name: string;
    display_name: string;
    group: string;
    description: string | null;
}

const props = defineProps<{
    permissions: Record<string, Permission[]>;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/roles' },
    { title: 'Permissions', href: '/settings/permissions' },
];

const search = ref('');

const filteredPermissions = computed(() => {
    if (!search.value) return props.permissions;
    const q = search.value.toLowerCase();
    const result: Record<string, Permission[]> = {};
    for (const [group, perms] of Object.entries(props.permissions)) {
        const filtered = perms.filter(p =>
            p.name.toLowerCase().includes(q) ||
            p.display_name.toLowerCase().includes(q) ||
            group.toLowerCase().includes(q)
        );
        if (filtered.length) result[group] = filtered;
    }
    return result;
});

const totalCount = computed(() =>
    Object.values(props.permissions).reduce((sum, perms) => sum + perms.length, 0)
);

const filteredCount = computed(() =>
    Object.values(filteredPermissions.value).reduce((sum, perms) => sum + perms.length, 0)
);

const existingGroups = computed(() => Object.keys(props.permissions));

const showCreateDialog = ref(false);
const showEditDialog   = ref(false);
const showDeleteDialog = ref(false);
const editingPerm      = ref<Permission | null>(null);
const deletingPerm     = ref<Permission | null>(null);

const createForm = useForm({ name:'', display_name:'', group:'', description:'' });
const editForm   = useForm({ name:'', display_name:'', group:'', description:'' });

function openCreate() { createForm.reset(); showCreateDialog.value = true; }
function submitCreate() {
    createForm.post('/settings/permissions', {
        onSuccess: () => { showCreateDialog.value = false; createForm.reset(); },
    });
}

function openEdit(perm: Permission) {
    editingPerm.value     = perm;
    editForm.name         = perm.name;
    editForm.display_name = perm.display_name;
    editForm.group        = perm.group;
    editForm.description  = perm.description ?? '';
    showEditDialog.value  = true;
}
function submitEdit() {
    if (!editingPerm.value) return;
    editForm.put(`/settings/permissions/${editingPerm.value.id}`, {
        onSuccess: () => { showEditDialog.value = false; },
    });
}

function openDelete(perm: Permission) { deletingPerm.value = perm; showDeleteDialog.value = true; }
function confirmDelete() {
    if (!deletingPerm.value) return;
    router.delete(`/settings/permissions/${deletingPerm.value.id}`, {
        onSuccess: () => { showDeleteDialog.value = false; },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Permissions" />
        <SettingsLayout>
            <div class="space-y-6">

                <!-- Header -->
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Permissions</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Kelola daftar permission yang tersedia untuk di-assign ke roles</p>
                    </div>
                    <button
                        @click="openCreate"
                        class="inline-flex items-center gap-2 rounded-xl bg-foreground px-4 py-2.5 text-sm font-semibold text-background shadow-sm hover:opacity-90 active:scale-95 transition-all"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Tambah Permission
                    </button>
                </div>

                <!-- Search + Stats -->
                <div class="flex items-center gap-3">
                    <div class="relative flex-1 max-w-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-foreground"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input
                            v-model="search"
                            placeholder="Cari permission atau grup..."
                            type="search"
                            class="w-full rounded-xl border bg-muted/30 py-2.5 pl-10 pr-4 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all"
                        />
                    </div>
                    <div class="rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm font-medium text-muted-foreground whitespace-nowrap">
                        <span v-if="search">{{ filteredCount }} / </span>{{ totalCount }} permissions
                    </div>
                </div>

                <!-- Empty -->
                <div v-if="Object.keys(filteredPermissions).length === 0" class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed py-20 text-center">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </div>
                    <p class="font-semibold text-muted-foreground">Tidak ada permission ditemukan</p>
                    <p class="mt-1 text-sm text-muted-foreground/60">Coba ubah kata kunci pencarian</p>
                </div>

                <!-- Groups -->
                <div v-for="(perms, group) in filteredPermissions" :key="group" class="space-y-2">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-6 w-6 items-center justify-center rounded-md bg-muted">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        </div>
                        <h3 class="text-sm font-bold tracking-wide">{{ group }}</h3>
                        <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">{{ perms.length }}</span>
                    </div>

                    <div class="rounded-2xl border overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody class="divide-y">
                                <tr
                                    v-for="perm in perms"
                                    :key="perm.id"
                                    class="group hover:bg-muted/20 transition-colors"
                                >
                                    <td class="px-5 py-3 w-56">
                                        <code class="rounded-lg bg-muted px-2 py-1 font-mono text-xs text-muted-foreground">{{ perm.name }}</code>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="font-semibold text-sm">{{ perm.display_name }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">
                                        <span v-if="perm.description">{{ perm.description }}</span>
                                        <span v-else class="italic opacity-40">—</span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="flex gap-1.5 justify-end opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button
                                                @click="openEdit(perm)"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg border bg-background text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                                                title="Edit"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </button>
                                            <button
                                                @click="openDelete(perm)"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-500 hover:bg-red-100 transition-colors"
                                                title="Hapus"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </SettingsLayout>
    </AppLayout>

    <!-- ── Create Modal ───────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showCreateDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showCreateDialog = false" />
                <div class="relative z-10 w-full max-w-md rounded-2xl bg-background shadow-2xl ring-1 ring-black/10 overflow-hidden">
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <div>
                            <h2 class="text-lg font-bold">Tambah Permission</h2>
                            <p class="text-xs text-muted-foreground mt-0.5">Buat permission baru untuk di-assign ke role</p>
                        </div>
                        <button @click="showCreateDialog = false" class="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-muted text-muted-foreground transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitCreate" class="p-6 space-y-5">
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Nama Slug *</label>
                            <input
                                v-model="createForm.name"
                                placeholder="e.g. laporan.view"
                                class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm font-mono outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all"
                            />
                            <p class="text-xs text-muted-foreground/70">Format: modul.aksi (huruf kecil, titik, dash)</p>
                            <p v-if="createForm.errors.name" class="text-xs text-red-500">{{ createForm.errors.name }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Display Name *</label>
                            <input
                                v-model="createForm.display_name"
                                placeholder="e.g. Lihat Laporan"
                                class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all"
                            />
                            <p v-if="createForm.errors.display_name" class="text-xs text-red-500">{{ createForm.errors.display_name }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Grup *</label>
                            <input
                                v-model="createForm.group"
                                placeholder="e.g. Stock Control"
                                list="group-suggestions-create"
                                class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all"
                            />
                            <datalist id="group-suggestions-create">
                                <option v-for="g in existingGroups" :key="g" :value="g" />
                            </datalist>
                            <p class="text-xs text-muted-foreground/70">Pilih grup yang ada atau ketik nama grup baru</p>
                            <p v-if="createForm.errors.group" class="text-xs text-red-500">{{ createForm.errors.group }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Deskripsi</label>
                            <input
                                v-model="createForm.description"
                                placeholder="Opsional — jelaskan fungsi permission ini"
                                class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all"
                            />
                        </div>

                        <div class="flex justify-end gap-2 border-t pt-4">
                            <button type="button" @click="showCreateDialog = false" class="rounded-xl border px-4 py-2.5 text-sm font-medium hover:bg-muted transition-colors">Batal</button>
                            <button type="submit" :disabled="createForm.processing" class="rounded-xl bg-foreground px-5 py-2.5 text-sm font-semibold text-background hover:opacity-90 disabled:opacity-50 transition-all active:scale-95">
                                {{ createForm.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ── Edit Modal ─────────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showEditDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showEditDialog = false" />
                <div class="relative z-10 w-full max-w-md rounded-2xl bg-background shadow-2xl ring-1 ring-black/10 overflow-hidden">
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <div>
                            <h2 class="text-lg font-bold">Edit Permission</h2>
                            <p class="text-xs text-muted-foreground mt-0.5 font-mono">{{ editingPerm?.name }}</p>
                        </div>
                        <button @click="showEditDialog = false" class="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-muted text-muted-foreground transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitEdit" class="p-6 space-y-5">
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Nama Slug *</label>
                            <input
                                v-model="editForm.name"
                                class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm font-mono outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all"
                            />
                            <p v-if="editForm.errors.name" class="text-xs text-red-500">{{ editForm.errors.name }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Display Name *</label>
                            <input
                                v-model="editForm.display_name"
                                class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all"
                            />
                            <p v-if="editForm.errors.display_name" class="text-xs text-red-500">{{ editForm.errors.display_name }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Grup *</label>
                            <input
                                v-model="editForm.group"
                                list="group-suggestions-edit"
                                class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all"
                            />
                            <datalist id="group-suggestions-edit">
                                <option v-for="g in existingGroups" :key="g" :value="g" />
                            </datalist>
                            <p v-if="editForm.errors.group" class="text-xs text-red-500">{{ editForm.errors.group }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Deskripsi</label>
                            <input
                                v-model="editForm.description"
                                placeholder="Opsional"
                                class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all"
                            />
                        </div>

                        <div class="flex justify-end gap-2 border-t pt-4">
                            <button type="button" @click="showEditDialog = false" class="rounded-xl border px-4 py-2.5 text-sm font-medium hover:bg-muted transition-colors">Batal</button>
                            <button type="submit" :disabled="editForm.processing" class="rounded-xl bg-foreground px-5 py-2.5 text-sm font-semibold text-background hover:opacity-90 disabled:opacity-50 transition-all active:scale-95">
                                {{ editForm.processing ? 'Menyimpan...' : 'Update' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ── Delete Modal ───────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showDeleteDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteDialog = false" />
                <div class="relative z-10 w-full max-w-md rounded-2xl bg-background shadow-2xl ring-1 ring-black/10 overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-red-100">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg">Hapus Permission?</h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Permission <span class="font-semibold text-foreground">{{ deletingPerm?.display_name }}</span> akan dihapus dari semua role yang memilikinya. Tindakan ini tidak dapat dibatalkan.
                                </p>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-2">
                            <button @click="showDeleteDialog = false" class="rounded-xl border px-4 py-2.5 text-sm font-medium hover:bg-muted transition-colors">Batal</button>
                            <button @click="confirmDelete" class="rounded-xl bg-red-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-600 transition-colors active:scale-95">Ya, Hapus</button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: all 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .relative, .modal-leave-to .relative { transform: scale(0.95) translateY(8px); }
</style>
