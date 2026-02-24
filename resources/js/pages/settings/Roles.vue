<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface Permission {
    id: number;
    name: string;
    display_name: string;
    group: string;
}

interface Role {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    color: string;
    users_count: number;
    permissions: Permission[];
}

const props = defineProps<{
    roles: Role[];
    permissions: Record<string, Permission[]>;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/roles' },
    { title: 'Roles & Permissions', href: '/settings/roles' },
];

const colorOptions = [
    { value: 'gray',   hex: '#6b7280' },
    { value: 'red',    hex: '#ef4444' },
    { value: 'orange', hex: '#f97316' },
    { value: 'yellow', hex: '#eab308' },
    { value: 'green',  hex: '#22c55e' },
    { value: 'blue',   hex: '#3b82f6' },
    { value: 'violet', hex: '#8b5cf6' },
    { value: 'pink',   hex: '#ec4899' },
];

const colorMap: Record<string, { bg: string; text: string; border: string; dot: string; softBg: string }> = {
    gray:   { bg: 'rgba(107,114,128,0.12)', text: '#4b5563', border: 'rgba(107,114,128,0.25)', dot: '#6b7280', softBg: 'rgba(107,114,128,0.06)' },
    red:    { bg: 'rgba(239,68,68,0.12)',   text: '#dc2626', border: 'rgba(239,68,68,0.25)',   dot: '#ef4444', softBg: 'rgba(239,68,68,0.06)' },
    orange: { bg: 'rgba(249,115,22,0.12)',  text: '#ea580c', border: 'rgba(249,115,22,0.25)',  dot: '#f97316', softBg: 'rgba(249,115,22,0.06)' },
    yellow: { bg: 'rgba(234,179,8,0.12)',   text: '#ca8a04', border: 'rgba(234,179,8,0.25)',   dot: '#eab308', softBg: 'rgba(234,179,8,0.06)' },
    green:  { bg: 'rgba(34,197,94,0.12)',   text: '#16a34a', border: 'rgba(34,197,94,0.25)',   dot: '#22c55e', softBg: 'rgba(34,197,94,0.06)' },
    blue:   { bg: 'rgba(59,130,246,0.12)',  text: '#2563eb', border: 'rgba(59,130,246,0.25)',  dot: '#3b82f6', softBg: 'rgba(59,130,246,0.06)' },
    violet: { bg: 'rgba(139,92,246,0.12)',  text: '#7c3aed', border: 'rgba(139,92,246,0.25)',  dot: '#8b5cf6', softBg: 'rgba(139,92,246,0.06)' },
    pink:   { bg: 'rgba(236,72,153,0.12)',  text: '#db2777', border: 'rgba(236,72,153,0.25)',  dot: '#ec4899', softBg: 'rgba(236,72,153,0.06)' },
};

function c(color: string) { return colorMap[color] ?? colorMap.gray; }
function initials(name: string) { return name.split(' ').slice(0,2).map(n=>n[0]).join('').toUpperCase(); }

const showCreateDialog = ref(false);
const showEditDialog   = ref(false);
const showDeleteDialog = ref(false);
const editingRole      = ref<Role | null>(null);
const deletingRole     = ref<Role | null>(null);

const createForm = useForm({ name:'', display_name:'', description:'', color:'blue', permissions:[] as number[] });
const editForm   = useForm({ name:'', display_name:'', description:'', color:'blue', permissions:[] as number[] });

function openCreate() { createForm.reset(); showCreateDialog.value = true; }
function submitCreate() {
    createForm.post('/settings/roles', { onSuccess: () => { showCreateDialog.value = false; createForm.reset(); } });
}

function openEdit(role: Role) {
    editingRole.value = role;
    editForm.name = role.name; editForm.display_name = role.display_name;
    editForm.description = role.description ?? ''; editForm.color = role.color;
    editForm.permissions = role.permissions.map(p => p.id);
    showEditDialog.value = true;
}
function submitEdit() {
    if (!editingRole.value) return;
    editForm.put(`/settings/roles/${editingRole.value.id}`, { onSuccess: () => { showEditDialog.value = false; } });
}

function openDelete(role: Role) { deletingRole.value = role; showDeleteDialog.value = true; }
function confirmDelete() {
    if (!deletingRole.value) return;
    router.delete(`/settings/roles/${deletingRole.value.id}`, { onSuccess: () => { showDeleteDialog.value = false; } });
}

function togglePerm(form: typeof createForm | typeof editForm, id: number) {
    const i = form.permissions.indexOf(id);
    if (i === -1) form.permissions.push(id); else form.permissions.splice(i, 1);
}
function toggleGroup(form: typeof createForm | typeof editForm, group: string) {
    const ids = props.permissions[group].map(p => p.id);
    const all = ids.every(id => form.permissions.includes(id));
    if (all) form.permissions = form.permissions.filter(id => !ids.includes(id));
    else ids.forEach(id => { if (!form.permissions.includes(id)) form.permissions.push(id); });
}
function groupAll(form: typeof createForm | typeof editForm, group: string) {
    return props.permissions[group].every(p => form.permissions.includes(p.id));
}
function groupPartial(form: typeof createForm | typeof editForm, group: string) {
    const g = props.permissions[group];
    const n = g.filter(p => form.permissions.includes(p.id)).length;
    return n > 0 && n < g.length;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Roles & Permissions" />
        <SettingsLayout>
            <div class="space-y-6">

                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Roles & Permissions</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Kelola roles dan atur hak akses untuk setiap role</p>
                    </div>
                    <button
                        @click="openCreate"
                        class="inline-flex items-center gap-2 rounded-xl bg-foreground px-4 py-2.5 text-sm font-semibold text-background shadow-sm hover:opacity-90 active:scale-95 transition-all"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Tambah Role
                    </button>
                </div>

                <div v-if="roles.length === 0" class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed py-20 text-center">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <p class="font-semibold text-muted-foreground">Belum ada role</p>
                    <p class="mt-1 text-sm text-muted-foreground/60">Klik "Tambah Role" untuk memulai</p>
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <div
                        v-for="role in roles"
                        :key="role.id"
                        class="group relative overflow-hidden rounded-2xl border bg-card transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5"
                    >
                        <div
                            class="absolute inset-x-0 top-0 h-1 rounded-t-2xl"
                            :style="{ backgroundColor: c(role.color).dot }"
                        />

                        <div class="p-5 pt-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl text-sm font-bold"
                                        :style="{ backgroundColor: c(role.color).bg, color: c(role.color).dot, border: `1.5px solid ${c(role.color).border}` }"
                                    >
                                        {{ initials(role.display_name) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-base">{{ role.display_name }}</span>
                                            <span class="rounded-lg bg-muted px-2 py-0.5 font-mono text-xs text-muted-foreground">{{ role.name }}</span>
                                        </div>
                                        <div class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                            <span>{{ role.users_count }} user</span>
                                            <span v-if="role.description" class="mx-1">·</span>
                                            <span v-if="role.description">{{ role.description }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
                                    <button
                                        @click="openEdit(role)"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border bg-background text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                                        title="Edit"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button
                                        @click="openDelete(role)"
                                        :disabled="role.users_count > 0"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-500 hover:bg-red-100 transition-colors disabled:opacity-30 disabled:pointer-events-none"
                                        title="Hapus"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-t border-dashed">
                                <div v-if="role.permissions.length > 0" class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="perm in role.permissions.slice(0, 8)"
                                        :key="perm.id"
                                        class="inline-flex items-center rounded-lg px-2 py-1 text-xs font-medium"
                                        :style="{ backgroundColor: c(role.color).softBg, color: c(role.color).text, border: `1px solid ${c(role.color).border}` }"
                                    >
                                        {{ perm.display_name }}
                                    </span>
                                    <span
                                        v-if="role.permissions.length > 8"
                                        class="inline-flex items-center rounded-lg bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                                    >
                                        +{{ role.permissions.length - 8 }} lainnya
                                    </span>
                                </div>
                                <p v-else class="text-xs italic text-muted-foreground/50">Belum ada permissions</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>

    <!-- ── Create Modal ──────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showCreateDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showCreateDialog = false" />
                <div class="relative z-10 w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-background shadow-2xl ring-1 ring-black/10">
                    <div class="sticky top-0 z-10 flex items-center justify-between border-b bg-background/95 backdrop-blur-sm px-6 py-4">
                        <div>
                            <h2 class="text-lg font-bold">Tambah Role Baru</h2>
                            <p class="text-xs text-muted-foreground mt-0.5">Isi detail role dan pilih permissions yang sesuai</p>
                        </div>
                        <button @click="showCreateDialog = false" class="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-muted text-muted-foreground transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitCreate" class="p-6 space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Nama Slug *</label>
                                <input v-model="createForm.name" placeholder="e.g. supervisor" class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all" />
                                <p class="text-xs text-muted-foreground/70">Huruf kecil, angka, dan dash</p>
                                <p v-if="createForm.errors.name" class="text-xs text-red-500">{{ createForm.errors.name }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Display Name *</label>
                                <input v-model="createForm.display_name" placeholder="e.g. Supervisor" class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all" />
                                <p v-if="createForm.errors.display_name" class="text-xs text-red-500">{{ createForm.errors.display_name }}</p>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Deskripsi</label>
                            <input v-model="createForm.description" placeholder="Opsional — deskripsi singkat role ini" class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Warna Badge</label>
                            <div class="flex gap-3 flex-wrap">
                                <button
                                    v-for="col in colorOptions" :key="col.value" type="button"
                                    @click="createForm.color = col.value"
                                    class="h-8 w-8 rounded-full transition-all duration-150 hover:scale-110"
                                    :style="{
                                        backgroundColor: col.hex,
                                        boxShadow: createForm.color === col.value ? `0 0 0 3px white, 0 0 0 5px ${col.hex}` : 'none',
                                        transform: createForm.color === col.value ? 'scale(1.2)' : 'scale(1)'
                                    }"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Permissions</label>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :style="{ backgroundColor: c(createForm.color).bg, color: c(createForm.color).text }">
                                    {{ createForm.permissions.length }} dipilih
                                </span>
                            </div>
                            <div class="rounded-xl border overflow-hidden divide-y max-h-64 overflow-y-auto">
                                <div v-for="(perms, group) in permissions" :key="group">
                                    <label class="flex items-center gap-3 px-4 py-2.5 cursor-pointer bg-muted/50 hover:bg-muted/80 transition-colors">
                                        <input type="checkbox" :checked="groupAll(createForm, String(group))" :indeterminate="groupPartial(createForm, String(group))" @change="toggleGroup(createForm, String(group))" class="rounded accent-foreground" />
                                        <span class="text-xs font-bold uppercase tracking-wide">{{ group }}</span>
                                        <span class="ml-auto text-xs text-muted-foreground">{{ perms.length }} item</span>
                                    </label>
                                    <div class="grid grid-cols-2">
                                        <label v-for="perm in perms" :key="perm.id" class="flex items-center gap-2.5 px-4 py-2 text-xs cursor-pointer hover:bg-muted/30 transition-colors border-t">
                                            <input type="checkbox" :value="perm.id" :checked="createForm.permissions.includes(perm.id)" @change="togglePerm(createForm, perm.id)" class="rounded accent-foreground" />
                                            {{ perm.display_name }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t pt-4">
                            <button type="button" @click="showCreateDialog = false" class="rounded-xl border px-4 py-2.5 text-sm font-medium hover:bg-muted transition-colors">Batal</button>
                            <button type="submit" :disabled="createForm.processing" class="rounded-xl bg-foreground px-5 py-2.5 text-sm font-semibold text-background hover:opacity-90 disabled:opacity-50 transition-all active:scale-95">
                                {{ createForm.processing ? 'Menyimpan...' : 'Simpan Role' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ── Edit Modal ────────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showEditDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showEditDialog = false" />
                <div class="relative z-10 w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-background shadow-2xl ring-1 ring-black/10">
                    <div class="sticky top-0 z-10 flex items-center justify-between border-b bg-background/95 backdrop-blur-sm px-6 py-4">
                        <div>
                            <h2 class="text-lg font-bold">Edit Role</h2>
                            <p class="text-xs text-muted-foreground mt-0.5">{{ editingRole?.display_name }}</p>
                        </div>
                        <button @click="showEditDialog = false" class="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-muted text-muted-foreground transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitEdit" class="p-6 space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Nama Slug *</label>
                                <input v-model="editForm.name" class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all" />
                                <p v-if="editForm.errors.name" class="text-xs text-red-500">{{ editForm.errors.name }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Display Name *</label>
                                <input v-model="editForm.display_name" class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all" />
                                <p v-if="editForm.errors.display_name" class="text-xs text-red-500">{{ editForm.errors.display_name }}</p>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Deskripsi</label>
                            <input v-model="editForm.description" class="w-full rounded-xl border bg-muted/30 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-foreground/20 focus:border-foreground/40 transition-all" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Warna Badge</label>
                            <div class="flex gap-3 flex-wrap">
                                <button
                                    v-for="col in colorOptions" :key="col.value" type="button"
                                    @click="editForm.color = col.value"
                                    class="h-8 w-8 rounded-full transition-all duration-150"
                                    :style="{
                                        backgroundColor: col.hex,
                                        boxShadow: editForm.color === col.value ? `0 0 0 3px white, 0 0 0 5px ${col.hex}` : 'none',
                                        transform: editForm.color === col.value ? 'scale(1.2)' : 'scale(1)'
                                    }"
                                />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Permissions</label>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :style="{ backgroundColor: c(editForm.color).bg, color: c(editForm.color).text }">
                                    {{ editForm.permissions.length }} dipilih
                                </span>
                            </div>
                            <div class="rounded-xl border overflow-hidden divide-y max-h-64 overflow-y-auto">
                                <div v-for="(perms, group) in permissions" :key="group">
                                    <label class="flex items-center gap-3 px-4 py-2.5 cursor-pointer bg-muted/50 hover:bg-muted/80 transition-colors">
                                        <input type="checkbox" :checked="groupAll(editForm, String(group))" :indeterminate="groupPartial(editForm, String(group))" @change="toggleGroup(editForm, String(group))" class="rounded accent-foreground" />
                                        <span class="text-xs font-bold uppercase tracking-wide">{{ group }}</span>
                                        <span class="ml-auto text-xs text-muted-foreground">{{ perms.length }} item</span>
                                    </label>
                                    <div class="grid grid-cols-2">
                                        <label v-for="perm in perms" :key="perm.id" class="flex items-center gap-2.5 px-4 py-2 text-xs cursor-pointer hover:bg-muted/30 transition-colors border-t">
                                            <input type="checkbox" :value="perm.id" :checked="editForm.permissions.includes(perm.id)" @change="togglePerm(editForm, perm.id)" class="rounded accent-foreground" />
                                            {{ perm.display_name }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 border-t pt-4">
                            <button type="button" @click="showEditDialog = false" class="rounded-xl border px-4 py-2.5 text-sm font-medium hover:bg-muted transition-colors">Batal</button>
                            <button type="submit" :disabled="editForm.processing" class="rounded-xl bg-foreground px-5 py-2.5 text-sm font-semibold text-background hover:opacity-90 disabled:opacity-50 transition-all active:scale-95">
                                {{ editForm.processing ? 'Menyimpan...' : 'Update Role' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ── Delete Modal ──────────────────────────────────────────────────────── -->
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
                                <h3 class="font-bold text-lg">Hapus Role?</h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Role <span class="font-semibold text-foreground">{{ deletingRole?.display_name }}</span> akan dihapus permanen beserta semua permission yang terhubung. Tindakan ini tidak dapat dibatalkan.
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
