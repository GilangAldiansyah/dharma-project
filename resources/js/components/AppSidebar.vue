<script setup lang="ts">
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarHeader,
    SidebarMenu, SidebarMenuButton, SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutGrid, PackageSearch, Warehouse, Settings, AlertTriangle,
    Users, BoxIcon, Package, BarChart3, Wrench, FileText, Box,
    Home, TrendingUp, ClipboardList, Layers, Database, Bot,
    Activity, Cog, Scan, Calendar, History, X,
    Sun, Moon, ChevronRight, LayoutGrid as GridIcon,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed, ref, onMounted } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();
const page = usePage();
const showSwitcher = ref(false);
const isMobile = ref(false);

const isDark = ref(false);
onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
    const check = () => { isMobile.value = window.innerWidth < 768; };
    check();
    window.addEventListener('resize', check);
});

const toggleDark = () => {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
};

interface NavItemWithPermission extends NavItem { permission?: string; }
interface NavGroup {
    title: string; icon: any; items: NavItemWithPermission[];
    routes: string[]; dashboardRoute: string; permission: string;
    gradient: string; accent: string; accentRgb: string;
}

const allNavGroups: NavGroup[] = [
    {
        title: 'Control Stock', icon: Package, permission: 'dashboard.view',
        gradient: 'from-emerald-500 to-teal-500', accent: '#10b981', accentRgb: '16,185,129',
        routes: ['/dashboard', '/output', '/stock', '/forecast'], dashboardRoute: '/dashboard',
        items: [
            { title: 'Dashboard',      href: '/dashboard', icon: LayoutGrid,    permission: 'dashboard.view' },
            { title: 'Output Product', href: '/output',    icon: PackageSearch, permission: 'output.view' },
            { title: 'Stock Control',  href: '/stock',     icon: Warehouse,     permission: 'stock.view' },
            { title: 'Forecast',       href: '/forecast',  icon: TrendingUp,    permission: 'forecast.view' },
        ],
    },
    {
        title: 'NG System', icon: AlertTriangle, permission: 'ng.view',
        gradient: 'from-red-500 to-rose-500', accent: '#f43f5e', accentRgb: '244,63,94',
        routes: ['/ng-reports', '/suppliers', '/parts'], dashboardRoute: '/ng-reports/dashboard',
        items: [
            { title: 'Dashboard NG',     href: '/ng-reports/dashboard', icon: BarChart3,     permission: 'ng.view' },
            { title: 'NG Reports',       href: '/ng-reports',           icon: AlertTriangle, permission: 'ng.view' },
            { title: 'Master Suppliers', href: '/suppliers',            icon: Users,         permission: 'suppliers.view' },
            { title: 'Master Parts',     href: '/parts',                icon: BoxIcon,       permission: 'parts.view' },
        ],
    },
    {
        title: 'Die Shop', icon: Wrench, permission: 'die-shop.view',
        gradient: 'from-orange-500 to-amber-500', accent: '#f97316', accentRgb: '249,115,22',
        routes: ['/die-shop-dashboard', '/die-shop-reports', '/die-parts'], dashboardRoute: '/die-shop-dashboard',
        items: [
            { title: 'Dashboard Die Shop', href: '/die-shop-dashboard', icon: BarChart3, permission: 'die-shop.view' },
            { title: 'Laporan Perbaikan',  href: '/die-shop-reports',  icon: FileText,  permission: 'die-shop.view' },
            { title: 'Master Die Parts',   href: '/die-parts',         icon: Box,       permission: 'die-parts.edit' },
        ],
    },
    {
        title: 'Robot Monitor', icon: Bot, permission: 'esp32.view',
        gradient: 'from-cyan-500 to-sky-500', accent: '#06b6d4', accentRgb: '6,182,212',
        routes: ['/esp32/monitor'], dashboardRoute: '/esp32/monitor',
        items: [{ title: 'Monitor', href: '/esp32/monitor', icon: Activity, permission: 'esp32.view' }],
    },
    {
        title: 'Material', icon: ClipboardList, permission: 'transaksi.view',
        gradient: 'from-violet-500 to-purple-500', accent: '#8b5cf6', accentRgb: '139,92,246',
        routes: ['/transaksi', '/materials', '/part-materials'], dashboardRoute: '/transaksi',
        items: [
            { title: 'Transaksi Material',  href: '/transaksi',           icon: ClipboardList, permission: 'transaksi.view' },
            { title: 'Dashboard Transaksi', href: '/transaksi/dashboard', icon: BarChart3,     permission: 'transaksi.dashboard' },
            { title: 'Master Material',     href: '/materials',           icon: Database,      permission: 'materials.view' },
            { title: 'Master Part',         href: '/part-materials',      icon: Layers,        permission: 'materials.view' },
        ],
    },
    {
        title: 'Maintenance', icon: Activity, permission: 'maintenance.view',
        gradient: 'from-blue-500 to-indigo-500', accent: '#3b82f6', accentRgb: '59,130,246',
        routes: ['/maintenance'], dashboardRoute: '/maintenance/lines',
        items: [
            { title: 'Dashboard',           href: '/maintenance/dashboard', icon: BarChart3, permission: 'maintenance.view' },
            { title: 'Laporan Maintenance', href: '/maintenance',           icon: Activity,  permission: 'maintenance.view' },
            { title: 'Line',                href: '/maintenance/lines',     icon: Layers,    permission: 'lines.view' },
            { title: 'Mesin',               href: '/maintenance/mesin',     icon: Cog,       permission: 'lines.view' },
        ],
    },
    {
        title: 'OEE', icon: TrendingUp, permission: 'oee.view',
        gradient: 'from-pink-500 to-fuchsia-500', accent: '#ec4899', accentRgb: '236,72,153',
        routes: ['/oee'], dashboardRoute: '/oee',
        items: [{ title: 'Dashboard', href: '/oee', icon: BarChart3, permission: 'oee.view' }],
    },
    {
        title: 'Kanban', icon: Package, permission: 'stock.view',
        gradient: 'from-amber-500 to-yellow-500', accent: '#f59e0b', accentRgb: '245,158,11',
        routes: ['/products', '/kanbans'], dashboardRoute: '/products',
        items: [
            { title: 'Products',     href: '/products', icon: Package, permission: 'stock.view' },
            { title: 'Scan History', href: '/kanbans',  icon: Scan,    permission: 'stock.view' },
        ],
    },
    {
        title: 'JIG', icon: Wrench, permission: 'jig.view',
        gradient: 'from-indigo-500 to-blue-500', accent: '#6366f1', accentRgb: '99,102,241',
        routes: ['/jig'], dashboardRoute: '/jig/dashboard',
        items: [
            { title: 'Dashboard',         href: '/jig/dashboard',         icon: BarChart3,     permission: 'jig.view' },
            { title: 'Master JIG',        href: '/jig',                   icon: Wrench,        permission: 'jig.edit' },
            { title: 'Sparepart',         href: '/jig/sparepart',         icon: Package,       permission: 'jig.edit' },
            { title: 'History Sparepart', href: '/jig/sparepart/history', icon: History,       permission: 'jig.view' },
            { title: 'PM Schedule',       href: '/jig/pm/schedule',       icon: Calendar,      permission: 'jig.leader' },
            { title: 'PM Report',         href: '/jig/pm/report',         icon: ClipboardList, permission: 'jig.view' },
            { title: 'CM Report',         href: '/jig/cm',                icon: AlertTriangle, permission: 'jig.view' },
            { title: 'Improvement',       href: '/jig/improvement',       icon: TrendingUp,    permission: 'jig.view' },
        ],
    },
];

const visibleNavGroups = computed(() => allNavGroups.filter(g => can(g.permission)));

const currentSystem = computed(() => {
    const url = page.url;
    if (url === '/welcome' || url === '/') return null;
    let found: NavGroup | null = null;
    for (const g of visibleNavGroups.value) {
        for (const r of g.routes) {
            if (url.startsWith(r)) { found = g; localStorage.setItem('lastSystem', g.title); break; }
        }
        if (found) break;
    }
    if (!found && url.startsWith('/settings')) {
        const last = localStorage.getItem('lastSystem');
        if (last) found = visibleNavGroups.value.find(g => g.title === last) || null;
    }
    return found;
});

const visibleItems = computed(() =>
    currentSystem.value?.items.filter(i => !i.permission || can(i.permission)) ?? []
);

const logoHref = computed(() => currentSystem.value?.dashboardRoute ?? '/welcome');
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">

        <SidebarHeader class="p-0">
            <div class="px-3 pt-3 pb-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" as-child class="rounded-xl hover:bg-sidebar-accent px-2">
                            <Link :href="logoHref"><AppLogo /></Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </div>

            <template v-if="currentSystem">
                <!-- Expanded: card pembungkus dengan tinted accent -->
                <div class="group-data-[collapsible=icon]:hidden mx-3 mb-2 rounded-2xl overflow-hidden"
                    :style="`
                        background: rgba(${currentSystem.accentRgb}, 0.08);
                        border: 1.5px solid rgba(${currentSystem.accentRgb}, 0.2);
                        box-shadow: 0 2px 12px rgba(${currentSystem.accentRgb}, 0.08);
                    `">

                    <!-- Header sistem -->
                    <div class="flex items-center gap-2.5 px-3 pt-3 pb-2.5">
                        <div :class="`w-8 h-8 rounded-xl bg-gradient-to-br ${currentSystem.gradient} flex items-center justify-center shrink-0`"
                            :style="`box-shadow: 0 4px 14px rgba(${currentSystem.accentRgb}, 0.4)`">
                            <component :is="currentSystem.icon" class="w-4 h-4 text-white" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-[0.14em] leading-none mb-0.5"
                                :style="`color: ${currentSystem.accent}`">Aktif</p>
                            <p class="text-[13px] font-bold text-foreground truncate leading-none">{{ currentSystem.title }}</p>
                        </div>
                        <button @click="showSwitcher = true"
                            class="w-7 h-7 rounded-lg flex items-center justify-center transition-all hover:scale-105 active:scale-95 shrink-0"
                            :style="`color: ${currentSystem.accent}; background: rgba(${currentSystem.accentRgb}, 0.15)`">
                            <GridIcon class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Divider -->
                    <div class="mx-3 h-px" :style="`background: rgba(${currentSystem.accentRgb}, 0.15)`" />

                    <!-- Nav items -->
                    <div class="px-1.5 py-1.5">
                        <SidebarMenu class="gap-px">
                            <SidebarMenuItem v-for="item in visibleItems" :key="item.title">
                                <SidebarMenuButton as-child :tooltip="item.title"
                                    class="relative rounded-xl h-9 text-[13px] transition-all duration-150">
                                    <Link :href="item.href" class="flex items-center gap-2.5 px-2.5 font-medium text-foreground/60 hover:text-foreground hover:bg-background/50">
                                        <component :is="item.icon" class="w-[14px] h-[14px] shrink-0" />
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </div>
                </div>

                <!-- Collapsed: icon sistem saja -->
                <div class="group-data-[collapsible=icon]:flex hidden justify-center px-2 pb-2">
                    <button @click="showSwitcher = true"
                        class="w-9 h-9 rounded-xl flex items-center justify-center transition-all hover:scale-105"
                        :style="`background: rgba(${currentSystem.accentRgb}, 0.12); color: ${currentSystem.accent}`">
                        <component :is="currentSystem.icon" class="w-4 h-4" />
                    </button>
                </div>
            </template>

            <div v-if="!currentSystem" class="group-data-[collapsible=icon]:hidden mx-4 mb-3 h-px bg-border" />
        </SidebarHeader>

        <SidebarContent class="px-3 py-0">
            <div v-if="!currentSystem" class="group-data-[collapsible=icon]:hidden py-12 text-center px-2">
                <div class="w-12 h-12 rounded-2xl bg-muted flex items-center justify-center mx-auto mb-3">
                    <GridIcon class="w-5 h-5 text-muted-foreground/40" />
                </div>
                <p class="text-xs text-muted-foreground mb-3">Belum ada sistem aktif</p>
                <button @click="showSwitcher = true"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg bg-foreground text-background hover:opacity-90 transition-opacity">
                    Pilih sistem <ChevronRight class="w-3 h-3" />
                </button>
            </div>
        </SidebarContent>

        <SidebarFooter class="p-3 gap-0">
            <div class="rounded-2xl border border-border/60 overflow-hidden mb-2" :class="isDark ? 'bg-white/[0.03]' : 'bg-black/[0.02]'">
                <button @click="toggleDark"
                    class="w-full flex items-center gap-3 px-3 py-2.5 transition-colors group-data-[collapsible=icon]:justify-center"
                    :class="isDark ? 'hover:bg-white/[0.06]' : 'hover:bg-black/[0.04]'">
                    <div class="relative w-8 h-[18px] shrink-0">
                        <div class="absolute inset-0 rounded-full transition-colors duration-300"
                            :class="isDark ? 'bg-indigo-500' : 'bg-amber-400'">
                            <div class="absolute top-[2px] w-[14px] h-[14px] rounded-full bg-white shadow transition-all duration-300 flex items-center justify-center"
                                :class="isDark ? 'left-[18px]' : 'left-[2px]'">
                                <Moon v-if="isDark" class="w-2 h-2 text-indigo-500" />
                                <Sun v-else class="w-2 h-2 text-amber-400" />
                            </div>
                        </div>
                    </div>
                    <span class="text-[13px] font-medium text-muted-foreground group-data-[collapsible=icon]:hidden">
                        {{ isDark ? 'Dark mode' : 'Light mode' }}
                    </span>
                </button>
                <div class="h-px bg-border/50" />
                <Link href="/welcome"
                    class="flex items-center gap-3 px-3 py-2.5 transition-colors text-muted-foreground hover:text-foreground group-data-[collapsible=icon]:justify-center"
                    :class="isDark ? 'hover:bg-white/[0.06]' : 'hover:bg-black/[0.04]'">
                    <Home class="w-[14px] h-[14px] shrink-0" />
                    <span class="text-[13px] font-medium group-data-[collapsible=icon]:hidden">Home</span>
                </Link>
                <div class="h-px bg-border/50" />
                <Link href="/settings"
                    class="flex items-center gap-3 px-3 py-2.5 transition-colors text-muted-foreground hover:text-foreground group-data-[collapsible=icon]:justify-center"
                    :class="isDark ? 'hover:bg-white/[0.06]' : 'hover:bg-black/[0.04]'">
                    <Settings class="w-[14px] h-[14px] shrink-0" />
                    <span class="text-[13px] font-medium group-data-[collapsible=icon]:hidden">Settings</span>
                </Link>
            </div>
            <NavUser />
        </SidebarFooter>
    </Sidebar>

    <Teleport to="body">
        <!-- Backdrop — z lebih tinggi dari sidebar drawer (biasanya z-40/50) -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="showSwitcher"
                class="fixed inset-0 z-[200] bg-black/50 backdrop-blur-sm"
                @click="showSwitcher = false" />
        </Transition>

        <!-- Mobile: bottom sheet — z lebih tinggi dari backdrop -->
        <Transition
            enter-active-class="transition duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
            enter-from-class="translate-y-full"
            enter-to-class="translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0"
            leave-to-class="translate-y-full">
            <div v-if="showSwitcher && isMobile"
                class="fixed bottom-0 left-0 right-0 z-[201] bg-background rounded-t-3xl shadow-2xl border-t border-border"
                style="max-height: 85vh; padding-bottom: env(safe-area-inset-bottom)">
                <div class="flex justify-center pt-3 pb-1">
                    <div class="w-10 h-1 rounded-full bg-muted-foreground/20" />
                </div>
                <div class="flex items-center justify-between px-5 py-2 mb-1">
                    <p class="text-base font-bold">Pilih Sistem</p>
                    <button @click="showSwitcher = false"
                        class="w-8 h-8 rounded-full bg-muted flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <div class="px-4 pb-6 overflow-y-auto grid grid-cols-2 gap-2.5"
                    style="max-height: calc(85vh - 90px)">
                    <Link v-for="group in visibleNavGroups" :key="group.title"
                        :href="group.dashboardRoute"
                        @click="showSwitcher = false"
                        class="relative flex flex-col items-center gap-2.5 p-4 rounded-2xl transition-all duration-150 active:scale-95"
                        :style="currentSystem?.title === group.title
                            ? `border: 2px solid ${group.accent}; background: rgba(${group.accentRgb}, 0.08)`
                            : 'border: 2px solid transparent; background: var(--muted)'">
                        <div v-if="currentSystem?.title === group.title"
                            class="absolute top-2.5 right-2.5 w-2 h-2 rounded-full"
                            :style="`background: ${group.accent}`" />
                        <div :class="`w-12 h-12 rounded-2xl bg-gradient-to-br ${group.gradient} flex items-center justify-center`"
                            :style="currentSystem?.title === group.title ? `box-shadow: 0 8px 20px rgba(${group.accentRgb}, 0.4)` : ''">
                            <component :is="group.icon" class="w-6 h-6 text-white" />
                        </div>
                        <span class="text-[12px] font-semibold text-center leading-tight"
                            :style="currentSystem?.title === group.title ? `color: ${group.accent}` : ''">
                            {{ group.title }}
                        </span>
                    </Link>
                </div>
            </div>
        </Transition>

        <!-- Desktop: dropdown popup -->
        <Transition
            enter-active-class="transition duration-200 ease-[cubic-bezier(0.16,1,0.3,1)]"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95 -translate-y-1">
            <div v-if="showSwitcher && !isMobile"
                class="fixed left-[calc(var(--sidebar-width)+12px)] top-[52px] z-[201] w-60 rounded-2xl overflow-hidden shadow-2xl border border-border bg-background">
                <div class="px-4 py-3 flex items-center justify-between border-b border-border">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-muted-foreground">Pilih Sistem</p>
                    <button @click="showSwitcher = false"
                        class="w-6 h-6 rounded-lg flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-all">
                        <X class="w-3 h-3" />
                    </button>
                </div>
                <div class="p-2 max-h-80 overflow-y-auto">
                    <Link v-for="group in visibleNavGroups" :key="group.title"
                        :href="group.dashboardRoute"
                        @click="showSwitcher = false"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-150 group/sw"
                        :class="currentSystem?.title !== group.title ? 'hover:bg-muted' : ''"
                        :style="currentSystem?.title === group.title
                            ? `background: rgba(${group.accentRgb}, 0.08); border: 1px solid rgba(${group.accentRgb}, 0.2)`
                            : 'border: 1px solid transparent'">
                        <div :class="`w-7 h-7 rounded-xl bg-gradient-to-br ${group.gradient} flex items-center justify-center shrink-0 transition-transform duration-150 group-hover/sw:scale-110`"
                            :style="currentSystem?.title === group.title ? `box-shadow: 0 4px 10px rgba(${group.accentRgb}, 0.4)` : ''">
                            <component :is="group.icon" class="w-3.5 h-3.5 text-white" />
                        </div>
                        <span class="text-[13px] flex-1 transition-colors"
                            :style="currentSystem?.title === group.title
                                ? `color: ${group.accent}; font-weight: 600`
                                : 'color: var(--muted-foreground); font-weight: 500'"
                            :class="currentSystem?.title !== group.title ? 'group-hover/sw:text-foreground' : ''">
                            {{ group.title }}
                        </span>
                        <div v-if="currentSystem?.title === group.title"
                            class="w-1.5 h-1.5 rounded-full shrink-0"
                            :style="`background: ${group.accent}`" />
                    </Link>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
