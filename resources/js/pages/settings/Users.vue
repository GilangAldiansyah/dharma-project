<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface Role { id: number; name: string; display_name: string; color: string; }
interface User { id: number; name: string; email: string; roles: Role[]; }
interface PaginationLink { url: string | null; label: string; active: boolean; }
interface PaginatedUsers { data: User[]; links: PaginationLink[]; current_page: number; last_page: number; total: number; }

const props = defineProps<{ users: PaginatedUsers; roles: Role[]; filters: { search: string | null }; }>();

const page = usePage();
const currentUserId = (page.props.auth as any)?.user?.id;

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/users' },
    { title: 'User Management', href: '/settings/users' },
];

const colorMap: Record<string, { bg: string; text: string; border: string; dot: string }> = {
    gray:   { bg: 'rgba(107,114,128,0.12)', text: '#4b5563', border: 'rgba(107,114,128,0.25)', dot: '#6b7280' },
    red:    { bg: 'rgba(239,68,68,0.12)',   text: '#dc2626', border: 'rgba(239,68,68,0.25)',   dot: '#ef4444' },
    orange: { bg: 'rgba(249,115,22,0.12)',  text: '#ea580c', border: 'rgba(249,115,22,0.25)',  dot: '#f97316' },
    yellow: { bg: 'rgba(234,179,8,0.12)',   text: '#ca8a04', border: 'rgba(234,179,8,0.25)',   dot: '#eab308' },
    green:  { bg: 'rgba(34,197,94,0.12)',   text: '#16a34a', border: 'rgba(34,197,94,0.25)',   dot: '#22c55e' },
    blue:   { bg: 'rgba(59,130,246,0.12)',  text: '#2563eb', border: 'rgba(59,130,246,0.25)',  dot: '#3b82f6' },
    violet: { bg: 'rgba(139,92,246,0.12)',  text: '#7c3aed', border: 'rgba(139,92,246,0.25)',  dot: '#8b5cf6' },
    pink:   { bg: 'rgba(236,72,153,0.12)',  text: '#db2777', border: 'rgba(236,72,153,0.25)',  dot: '#ec4899' },
};

function c(color: string) { return colorMap[color] ?? colorMap.gray; }

function getInitials(name: string) { return name.split(' ').slice(0,2).map(n=>n[0]).join('').toUpperCase(); }

function avatarBg(name: string) {
    const palette = ['#3b82f6','#8b5cf6','#ec4899','#f97316','#22c55e','#ef4444','#eab308','#06b6d4'];
    let h = 0; for (let i=0; i<name.length; i++) h = name.charCodeAt(i) + ((h<<5)-h);
    return palette[Math.abs(h) % palette.length];
}

const search = ref(props.filters.search ?? '');
let timer: ReturnType<typeof setTimeout>;
watch(search, val => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/settings/users', { search: val }, { preserveState: true, replace: true }), 400);
});

const showCreateDialog = ref(false);
const createForm = useForm({ name:'', email:'', password:'', password_confirmation:'', roles:[] as number[] });

function openCreate() { createForm.reset(); showCreateDialog.value = true; }
function submitCreate() {
    createForm.post('/settings/users', { onSuccess: () => { showCreateDialog.value = false; createForm.reset(); } });
}
function toggleCreateRole(id: number) {
    const i = createForm.roles.indexOf(id);
    if (i === -1) createForm.roles.push(id); else createForm.roles.splice(i, 1);
}

const showEditDialog = ref(false);
const editingUser    = ref<User | null>(null);
const editForm       = useForm({ roles: [] as number[] });

function openEditRoles(user: User) {
    editingUser.value = user; editForm.roles = user.roles.map(r => r.id); showEditDialog.value = true;
}
function toggleEditRole(id: number) {
    const i = editForm.roles.indexOf(id);
    if (i === -1) editForm.roles.push(id); else editForm.roles.splice(i, 1);
}
function submitEditRoles() {
    if (!editingUser.value) return;
    editForm.put(`/settings/users/${editingUser.value.id}/roles`, { onSuccess: () => { showEditDialog.value = false; } });
}

const showDeleteDialog = ref(false);
const deletingUser     = ref<User | null>(null);

function openDelete(user: User) { deletingUser.value = user; showDeleteDialog.value = true; }
function confirmDelete() {
    if (!deletingUser.value) return;
    router.delete(`/settings/users/${deletingUser.value.id}`, { onSuccess: () => { showDeleteDialog.value = false; } });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="User Management" />
        <SettingsLayout>
            <div class="space-y-6">

                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">User Management</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Kelola user dan atur role yang dimiliki</p>
                    </div>
                    <button
                        @click="openCreate"
                        class="inline-flex items-center gap-2 rounded-xl bg-foreground px-4 py-2.5 text-sm font-semibold text-background shadow-sm hover:opacity-90 active:scale-95 transition-all"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Tambah User
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <div class="relative flex-1 max-w-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-foreground"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input
                            v-model="search"
                            placeholder="Cari nama atau email..."
                            type="search"
                            class="w-full rounded-xl border bg-muted/30 py-2.5 pl-10 pr-4 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all"
                        />
                    </div>
                    <div class="rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm font-medium text-muted-foreground">
                        {{ users.total }} user
                    </div>
                </div>

                <div class="rounded-2xl border overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/40">
                                <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-muted-foreground">User</th>
                                <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-muted-foreground">Roles</th>
                                <th class="px-5 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-muted-foreground">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="user in users.data" :key="user.id" class="group hover:bg-muted/20 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl text-xs font-bold text-white shadow-sm"
                                            :style="{ backgroundColor: avatarBg(user.name) }"
                                        >
                                            {{ getInitials(user.name) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold flex items-center gap-1.5">
                                                {{ user.name }}
                                                <span v-if="user.id === currentUserId" class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-600">Anda</span>
                                            </p>
                                            <p class="text-xs text-muted-foreground mt-0.5">{{ user.email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span
                                            v-for="role in user.roles" :key="role.id"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold"
                                            :style="{ backgroundColor: c(role.color).bg, color: c(role.color).text, border: `1px solid ${c(role.color).border}` }"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full flex-shrink-0" :style="{ backgroundColor: c(role.color).dot }" />
                                            {{ role.display_name }}
                                        </span>
                                        <span v-if="user.roles.length === 0" class="text-xs italic text-muted-foreground/50">Tidak ada role</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex gap-1.5 justify-end opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button
                                            @click="openEditRoles(user)"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border bg-background text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                                            title="Edit Roles"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                        </button>
                                        <button
                                            @click="openDelete(user)"
                                            :disabled="user.id === currentUserId"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-500 hover:bg-red-100 transition-colors disabled:opacity-30 disabled:pointer-events-none"
                                            title="Hapus"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="users.data.length === 0" class="flex flex-col items-center py-16 text-center">
                        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-muted">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        </div>
                        <p class="font-semibold text-muted-foreground">Tidak ada user ditemukan</p>
                        <p class="mt-1 text-xs text-muted-foreground/60">Coba ubah kata kunci pencarian</p>
                    </div>
                </div>

                <div v-if="users.last_page > 1" class="flex items-center justify-between">
                    <p class="text-xs text-muted-foreground">Halaman {{ users.current_page }} dari {{ users.last_page }}</p>
                    <div class="flex gap-1">
                        <template v-for="link in users.links" :key="link.label">
                            <button
                                v-if="link.url"
                                @click="router.get(link.url!)"
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2.5 text-xs font-semibold transition-colors"
                                :class="link.active ? 'bg-foreground text-background' : 'border bg-background hover:bg-muted'"
                            >
                                <span v-if="link.label === '&laquo; Previous'">←</span>
                                <span v-else-if="link.label === 'Next &raquo;'">→</span>
                                <span v-else>{{ link.label }}</span>
                            </button>
                            <button v-else class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2.5 text-xs text-muted-foreground/40 cursor-not-allowed">
                                <span v-if="link.label === '&laquo; Previous'">←</span>
                                <span v-else-if="link.label === 'Next &raquo;'">→</span>
                                <span v-else>{{ link.label }}</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>

    <!-- ── Create User Modal ──────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showCreateDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showCreateDialog = false" />
                <div class="relative z-10 w-full max-w-md max-h-[90vh] overflow-y-auto rounded-2xl bg-background shadow-2xl ring-1 ring-black/10">
                    <div class="sticky top-0 z-10 flex items-center justify-between border-b bg-background/95 backdrop-blur-sm px-6 py-4">
                        <div>
                            <h2 class="text-lg font-bold">Tambah User Baru</h2>
                            <p class="text-xs text-muted-foreground mt-0.5">Isi detail dan assign role</p>
                        </div>
                        <button @click="showCreateDialog = false" class="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-muted text-muted-foreground transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitCreate" class="p-6 space-y-5">
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Nama *</label>
                            <input v-model="createForm.name" placeholder="Gilang Aldiansyah" class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all" />
                            <p v-if="createForm.errors.name" class="text-xs text-red-500">{{ createForm.errors.name }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Email *</label>
                            <input v-model="createForm.email" type="email" placeholder="john@example.com" class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all" />
                            <p v-if="createForm.errors.email" class="text-xs text-red-500">{{ createForm.errors.email }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Password *</label>
                                <input v-model="createForm.password" type="password" placeholder="Min. 8 karakter" class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all" />
                                <p v-if="createForm.errors.password" class="text-xs text-red-500">{{ createForm.errors.password }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Konfirmasi *</label>
                                <input v-model="createForm.password_confirmation" type="password" placeholder="Ulangi" class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Roles</label>
                            <div class="rounded-xl border overflow-hidden divide-y">
                                <label
                                    v-for="role in roles" :key="role.id"
                                    class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-muted/30 transition-colors"
                                >
                                    <input type="checkbox" :value="role.id" :checked="createForm.roles.includes(role.id)" @change="toggleCreateRole(role.id)" class="rounded accent-foreground" />
                                    <span class="h-2.5 w-2.5 rounded-full flex-shrink-0" :style="{ backgroundColor: c(role.color).dot }" />
                                    <span class="text-sm font-medium flex-1">{{ role.display_name }}</span>
                                    <code class="text-xs text-muted-foreground">{{ role.name }}</code>
                                </label>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 border-t pt-4">
                            <button type="button" @click="showCreateDialog = false" class="rounded-xl border px-4 py-2.5 text-sm font-medium hover:bg-muted transition-colors">Batal</button>
                            <button type="submit" :disabled="createForm.processing" class="rounded-xl bg-foreground px-5 py-2.5 text-sm font-semibold text-background hover:opacity-90 disabled:opacity-50 transition-all active:scale-95">
                                {{ createForm.processing ? 'Menyimpan...' : 'Buat User' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ── Edit Roles Modal ───────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showEditDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showEditDialog = false" />
                <div class="relative z-10 w-full max-w-md rounded-2xl bg-background shadow-2xl ring-1 ring-black/10 overflow-hidden">
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl text-xs font-bold text-white" :style="{ backgroundColor: editingUser ? avatarBg(editingUser.name) : '#6b7280' }">
                                {{ editingUser ? getInitials(editingUser.name) : '' }}
                            </div>
                            <div>
                                <h2 class="font-bold leading-tight">{{ editingUser?.name }}</h2>
                                <p class="text-xs text-muted-foreground">{{ editingUser?.email }}</p>
                            </div>
                        </div>
                        <button @click="showEditDialog = false" class="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-muted text-muted-foreground transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitEditRoles" class="p-6 space-y-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pilih Roles</label>
                                <span class="text-xs text-muted-foreground">{{ editForm.roles.length }} dipilih</span>
                            </div>
                            <div class="rounded-xl border overflow-hidden divide-y">
                                <label
                                    v-for="role in roles" :key="role.id"
                                    class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-muted/30 transition-colors"
                                >
                                    <input type="checkbox" :value="role.id" :checked="editForm.roles.includes(role.id)" @change="toggleEditRole(role.id)" class="rounded accent-foreground" />
                                    <span class="h-2.5 w-2.5 rounded-full flex-shrink-0" :style="{ backgroundColor: c(role.color).dot }" />
                                    <span class="text-sm font-medium flex-1">{{ role.display_name }}</span>
                                    <code class="text-xs text-muted-foreground">{{ role.name }}</code>
                                </label>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 border-t pt-4">
                            <button type="button" @click="showEditDialog = false" class="rounded-xl border px-4 py-2.5 text-sm font-medium hover:bg-muted transition-colors">Batal</button>
                            <button type="submit" :disabled="editForm.processing" class="rounded-xl bg-foreground px-5 py-2.5 text-sm font-semibold text-background hover:opacity-90 disabled:opacity-50 transition-all active:scale-95">
                                {{ editForm.processing ? 'Menyimpan...' : 'Simpan' }}
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
                                <h3 class="font-bold text-lg">Hapus User?</h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    User <span class="font-semibold text-foreground">{{ deletingUser?.name }}</span> akan dihapus permanen beserta semua data yang terhubung.
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
