<script setup lang="ts">
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarHeader,
    SidebarMenu, SidebarMenuButton, SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutGrid, PackageSearch, Warehouse, Settings, AlertTriangle,
    Users, BoxIcon, Package, BarChart3, Wrench, FileText, Box,
    Home, TrendingUp, ClipboardList, Layers, Database, Bot,
    Activity, Cog, Calendar, History, X,
    Sun, Moon, ChevronRight, LayoutGrid as GridIcon,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();
const page = usePage();
const showSwitcher = ref(false);
const isMobile = ref(false);
const isDark = ref(false);

const checkMobile = () => { isMobile.value = window.innerWidth < 768; };

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
    checkMobile();
    window.addEventListener('resize', checkMobile);
});
onUnmounted(() => window.removeEventListener('resize', checkMobile));

const toggleDark = () => {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
};

interface NavItemWithPermission { title: string; href: string; icon: any; permission?: string; }
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
        title: 'Dies', icon: Wrench, permission: 'dies.view',
        gradient: 'from-orange-500 to-amber-500', accent: '#f97316', accentRgb: '249,115,22',
        routes: ['/dies', '/dies/preventive', '/dies/corrective', '/dies/sparepart', '/dies/dashboard', '/dies/io'],
        dashboardRoute: '/dies/dashboard',
        items: [
            { title: 'Dashboard Dies',         href: '/dies/dashboard',         icon: BarChart3, permission: 'dies.view' },
            { title: 'Master Dies',            href: '/dies',                   icon: Box,       permission: 'dies.view' },
            { title: 'Preventive Maintenance', href: '/dies/preventive',        icon: FileText,  permission: 'dies.view' },
            { title: 'Corrective Maintenance', href: '/dies/corrective',        icon: FileText,  permission: 'dies.view' },
            { title: 'Sparepart',              href: '/dies/sparepart',         icon: Package,   permission: 'dies.view' },
            { title: 'History Sparepart',      href: '/dies/sparepart/history', icon: History,   permission: 'dies.view' },
            { title: 'IO',                      href: '/dies/io', icon: Warehouse,   permission: 'dies.view' },
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

const activeHref = computed(() => {
    const url = page.url;
    const items = visibleItems.value;
    const matched = items.filter(i =>
        url === i.href || url.startsWith(i.href + '/') || url.startsWith(i.href + '?')
    );
    if (!matched.length) return null;
    return matched.reduce((a, b) => a.href.length >= b.href.length ? a : b).href;
});

const isActive = (href: string) => activeHref.value === href;
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">

        <SidebarHeader class="p-0">
            <div class="px-2 pt-3 pb-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" as-child class="rounded-xl hover:bg-sidebar-accent px-2">
                            <Link :href="logoHref"><AppLogo /></Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </div>

            <div class="group-data-[collapsible=icon]:hidden px-3 pb-2">
                <template v-if="currentSystem">
                    <div class="flex items-center gap-2 px-2.5 py-2 rounded-xl"
                        :style="`background: rgba(${currentSystem.accentRgb}, 0.07); border: 1px solid rgba(${currentSystem.accentRgb}, 0.15)`">
                        <div :class="`w-5 h-5 rounded-md bg-gradient-to-br ${currentSystem.gradient} flex items-center justify-center shrink-0`">
                            <component :is="currentSystem.icon" class="w-3 h-3 text-white" />
                        </div>
                        <span class="text-[11px] font-bold uppercase tracking-widest flex-1 truncate"
                            :style="`color: ${currentSystem.accent}`">
                            {{ currentSystem.title }}
                        </span>
                        <button @click="showSwitcher = true"
                            class="w-5 h-5 rounded-md flex items-center justify-center transition-all hover:scale-110 active:scale-95 shrink-0 text-muted-foreground/60 hover:text-muted-foreground">
                            <GridIcon class="w-3 h-3" />
                        </button>
                    </div>
                </template>
                <template v-else>
                    <button @click="showSwitcher = true"
                        class="w-full flex items-center gap-2 px-2.5 py-2 rounded-xl text-muted-foreground hover:text-foreground hover:bg-muted transition-all text-[12px] font-medium">
                        <GridIcon class="w-3.5 h-3.5 shrink-0" />
                        <span>Pilih Sistem</span>
                        <ChevronRight class="w-3 h-3 ml-auto" />
                    </button>
                </template>
            </div>

            <div class="group-data-[collapsible=icon]:hidden mx-3 h-px bg-border/40 mb-1" />
        </SidebarHeader>

        <SidebarContent class="px-2 py-1">
            <SidebarMenu class="gap-0.5">
                <SidebarMenuItem v-for="item in visibleItems" :key="item.title"
                    class="group-data-[collapsible=icon]:flex group-data-[collapsible=icon]:justify-center">
                    <SidebarMenuButton as-child :tooltip="item.title"
                        class="group-data-[collapsible=icon]:!w-auto group-data-[collapsible=icon]:!p-0 !bg-transparent hover:!bg-transparent">
                        <Link :href="item.href"
                            class="flex items-center gap-3 px-3 h-9 text-[13px] transition-all duration-150 group/nav"
                            :style="isActive(item.href) && currentSystem ? `
                                color: ${currentSystem.accent};
                                background: rgba(${currentSystem.accentRgb}, 0.12);
                                border-radius: 50px;
                                font-weight: 600;
                            ` : `border-radius: 12px; font-weight: 500;`"
                            :class="!isActive(item.href) ? 'text-muted-foreground hover:text-foreground hover:bg-muted/60' : ''">
                            <component :is="item.icon" class="shrink-0 transition-transform duration-150 group-hover/nav:scale-110" style="width:15px;height:15px;" />
                            <span class="truncate group-data-[collapsible=icon]:hidden">{{ item.title }}</span>
                            <span v-if="isActive(item.href) && currentSystem"
                                class="ml-auto w-1.5 h-1.5 rounded-full shrink-0 group-data-[collapsible=icon]:hidden"
                                :style="`background: ${currentSystem.accent}`" />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarContent>

        <SidebarFooter class="p-2 gap-1">
            <div class="rounded-xl border border-border/50 overflow-hidden">
                <button @click="toggleDark"
                    class="w-full flex items-center gap-3 px-3 py-2.5 transition-colors hover:bg-muted/60 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0">
                    <div class="group-data-[collapsible=icon]:block hidden">
                        <Moon v-if="isDark" class="w-[14px] h-[14px] text-indigo-400" />
                        <Sun v-else class="w-[14px] h-[14px] text-amber-400" />
                    </div>
                    <div class="group-data-[collapsible=icon]:hidden flex items-center gap-3 w-full">
                        <div class="relative w-8 h-[18px] shrink-0">
                            <div class="absolute inset-0 rounded-full transition-colors duration-300"
                                :class="isDark ? 'bg-indigo-500' : 'bg-amber-400'">
                                <div class="absolute top-[2px] w-[14px] h-[14px] rounded-full bg-white shadow-sm transition-all duration-300 flex items-center justify-center"
                                    :class="isDark ? 'left-[18px]' : 'left-[2px]'">
                                    <Moon v-if="isDark" class="w-2 h-2 text-indigo-500" />
                                    <Sun v-else class="w-2 h-2 text-amber-500" />
                                </div>
                            </div>
                        </div>
                        <span class="text-[12px] font-medium text-muted-foreground">
                            {{ isDark ? 'Dark mode' : 'Light mode' }}
                        </span>
                    </div>
                </button>
                <div class="h-px bg-border/50" />
                <Link href="/welcome"
                    class="flex items-center gap-3 px-3 py-2.5 transition-colors text-muted-foreground hover:text-foreground hover:bg-muted/60 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0">
                    <Home class="w-[14px] h-[14px] shrink-0" />
                    <span class="text-[12px] font-medium group-data-[collapsible=icon]:hidden">Home</span>
                </Link>
                <div class="h-px bg-border/50" />
                <Link href="/settings"
                    class="flex items-center gap-3 px-3 py-2.5 transition-colors text-muted-foreground hover:text-foreground hover:bg-muted/60 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0">
                    <Settings class="w-[14px] h-[14px] shrink-0" />
                    <span class="text-[12px] font-medium group-data-[collapsible=icon]:hidden">Settings</span>
                </Link>
            </div>
            <NavUser />
        </SidebarFooter>

        <Transition
            enter-active-class="transition duration-250 ease-[cubic-bezier(0.16,1,0.3,1)]"
            enter-from-class="opacity-0 translate-y-3"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2">
            <div v-if="showSwitcher"
                class="absolute inset-0 z-50 flex flex-col bg-sidebar"
                style="border-radius: inherit;">

                <div class="flex items-center justify-between px-4 pt-4 pb-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-muted-foreground/60">Workspace</p>
                        <p class="text-[15px] font-bold text-foreground mt-0.5">Pilih Sistem</p>
                    </div>
                    <button @click="showSwitcher = false"
                        class="w-8 h-8 rounded-xl flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-all duration-150 hover:scale-105 active:scale-95">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="mx-4 h-px bg-border/40 mb-3" />

                <div class="flex-1 overflow-y-auto px-3 pb-4 space-y-1.5">
                    <Link
                        v-for="group in visibleNavGroups"
                        :key="group.title"
                        :href="group.dashboardRoute"
                        @click="showSwitcher = false"
                        class="group flex items-center gap-3 px-3 py-2.5 rounded-2xl transition-all duration-150 active:scale-[0.98]"
                        :style="currentSystem?.title === group.title
                            ? `background: rgba(${group.accentRgb}, 0.1); outline: 1.5px solid rgba(${group.accentRgb}, 0.3)`
                            : 'background: transparent'">
                        <div
                            class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-transform duration-150 group-hover:scale-105"
                            :class="`bg-gradient-to-br ${group.gradient}`">
                            <component :is="group.icon" class="w-4 h-4 text-white" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-semibold truncate transition-colors"
                                :style="currentSystem?.title === group.title ? `color: ${group.accent}` : ''"
                                :class="currentSystem?.title !== group.title ? 'text-foreground/80 group-hover:text-foreground' : ''">
                                {{ group.title }}
                            </p>
                            <p class="text-[11px] text-muted-foreground/50 mt-0.5">
                                {{ group.items.length }} menu
                            </p>
                        </div>
                        <div v-if="currentSystem?.title === group.title"
                            class="w-2 h-2 rounded-full shrink-0"
                            :style="`background: ${group.accent}; box-shadow: 0 0 6px ${group.accent}`" />
                        <ChevronRight v-else class="w-3.5 h-3.5 text-muted-foreground/30 shrink-0 transition-all group-hover:text-muted-foreground/60 group-hover:translate-x-0.5" />
                    </Link>
                </div>
            </div>
        </Transition>

    </Sidebar>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="showSwitcher && !isMobile"
                class="fixed inset-0 z-[200] bg-black/30 backdrop-blur-[2px]"
                @click="showSwitcher = false" />
        </Transition>

        <Transition
            enter-active-class="transition duration-200 ease-[cubic-bezier(0.16,1,0.3,1)]"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95 -translate-y-1">
            <div v-if="showSwitcher && !isMobile"
                class="fixed left-[calc(var(--sidebar-width)+12px)] top-[52px] z-[201] w-60 rounded-2xl overflow-hidden shadow-2xl border border-border/60 bg-background/95 backdrop-blur-md">
                <div class="px-4 pt-3.5 pb-2.5 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground/50">Workspace</p>
                        <p class="text-[13px] font-bold text-foreground mt-0.5">Pilih Sistem</p>
                    </div>
                    <button @click="showSwitcher = false"
                        class="w-6 h-6 rounded-lg flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-all">
                        <X class="w-3 h-3" />
                    </button>
                </div>
                <div class="mx-3 h-px bg-border/40 mb-1.5" />
                <div class="p-2 max-h-[360px] overflow-y-auto space-y-0.5">
                    <Link
                        v-for="group in visibleNavGroups"
                        :key="group.title"
                        :href="group.dashboardRoute"
                        @click="showSwitcher = false"
                        class="group flex items-center gap-2.5 px-2.5 py-2 rounded-xl transition-all duration-150 active:scale-[0.98]"
                        :class="currentSystem?.title !== group.title ? 'hover:bg-muted/60' : ''"
                        :style="currentSystem?.title === group.title
                            ? `background: rgba(${group.accentRgb}, 0.08); outline: 1px solid rgba(${group.accentRgb}, 0.2)`
                            : ''">
                        <div :class="`w-7 h-7 rounded-lg bg-gradient-to-br ${group.gradient} flex items-center justify-center shrink-0 transition-transform duration-150 group-hover:scale-110`">
                            <component :is="group.icon" class="w-3.5 h-3.5 text-white" />
                        </div>
                        <span class="text-[12.5px] flex-1 truncate font-medium transition-colors"
                            :style="currentSystem?.title === group.title ? `color: ${group.accent}; font-weight: 600` : ''"
                            :class="currentSystem?.title !== group.title ? 'text-foreground/70 group-hover:text-foreground' : ''">
                            {{ group.title }}
                        </span>
                        <div v-if="currentSystem?.title === group.title"
                            class="w-1.5 h-1.5 rounded-full shrink-0"
                            :style="`background: ${group.accent}`" />
                    </Link>
                </div>
                <div class="h-2" />
            </div>
        </Transition>
    </Teleport>
</template>
