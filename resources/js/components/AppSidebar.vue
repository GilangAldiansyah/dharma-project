<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
    SidebarGroupContent,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutGrid,
    PackageSearch,
    Warehouse,
    Settings,
    AlertTriangle,
    Users,
    BoxIcon,
    Package,
    BarChart3,
    Wrench,
    FileText,
    Box,
    Home,
    TrendingUp,
    ClipboardList,
    Layers,
    Database,
    Bot,
    Activity,
    Cog,
    Scan,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

interface NavItemWithPermission extends NavItem {
    permission?: string;
}

interface NavGroup {
    title: string;
    icon: any;
    items: NavItemWithPermission[];
    routes: string[];
    dashboardRoute: string;
    permission: string;
}

const page = usePage();

const allNavGroups: NavGroup[] = [
    {
        title: 'Control Stock System',
        icon: Package,
        permission: 'dashboard.view',
        routes: ['/dashboard', '/output', '/stock', '/forecast'],
        dashboardRoute: '/dashboard',
        items: [
            { title: 'Dashboard',      href: '/dashboard', icon: LayoutGrid,    permission: 'dashboard.view' },
            { title: 'Output Product', href: '/output',    icon: PackageSearch, permission: 'output.view' },
            { title: 'Stock Control',  href: '/stock',     icon: Warehouse,     permission: 'stock.view' },
            { title: 'Forecast',       href: '/forecast',  icon: TrendingUp,    permission: 'forecast.view' },
        ],
    },
    {
        title: 'NG System',
        icon: AlertTriangle,
        permission: 'ng.view',
        routes: ['/ng-reports', '/suppliers', '/parts'],
        dashboardRoute: '/ng-reports/dashboard',
        items: [
            { title: 'Dashboard NG',     href: '/ng-reports/dashboard', icon: BarChart3,    permission: 'ng.view' },
            { title: 'NG Reports',       href: '/ng-reports',           icon: AlertTriangle, permission: 'ng.view' },
            { title: 'Master Suppliers', href: '/suppliers',            icon: Users,         permission: 'suppliers.view' },
            { title: 'Master Parts',     href: '/parts',                icon: BoxIcon,       permission: 'parts.view' },
        ],
    },
    {
        title: 'Die Shop System',
        icon: Wrench,
        permission: 'die-shop.view',
        routes: ['/die-shop-dashboard', '/die-shop-reports', '/die-parts'],
        dashboardRoute: '/die-shop-dashboard',
        items: [
            { title: 'Dashboard Die Shop', href: '/die-shop-dashboard', icon: BarChart3, permission: 'die-shop.view' },
            { title: 'Laporan Perbaikan',  href: '/die-shop-reports',  icon: FileText,  permission: 'die-shop.view' },
            { title: 'Master Die Parts',   href: '/die-parts',         icon: Box,       permission: 'die-parts.edit' },
        ],
    },
    {
        title: 'Robot Monitor',
        icon: Bot,
        permission: 'esp32.view',
        routes: ['/esp32/monitor'],
        dashboardRoute: '/esp32/monitor',
        items: [
            { title: 'Monitor', href: '/esp32/monitor', icon: Activity, permission: 'esp32.view' },
        ],
    },
    {
        title: 'Material Monitoring',
        icon: ClipboardList,
        permission: 'transaksi.view',
        routes: ['/transaksi', '/materials', '/part-materials'],
        dashboardRoute: '/transaksi',
        items: [
            { title: 'Transaksi Material',  href: '/transaksi',          icon: ClipboardList, permission: 'transaksi.view' },
            { title: 'Dashboard Transaksi', href: '/transaksi/dashboard', icon: BarChart3,     permission: 'transaksi.dashboard' },
            { title: 'Master Material',     href: '/materials',          icon: Database,      permission: 'materials.view' },
            { title: 'Master Part',         href: '/part-materials',     icon: Layers,        permission: 'materials.view' },
        ],
    },
    {
        title: 'Maintenance Monitoring',
        icon: Activity,
        permission: 'maintenance.view',
        routes: ['/maintenance'],
        dashboardRoute: '/maintenance/lines',
        items: [
            { title: 'Dashboard',           href: '/maintenance/dashboard', icon: BarChart3, permission: 'maintenance.view' },
            { title: 'Laporan Maintenance', href: '/maintenance',           icon: Activity,  permission: 'maintenance.view' },
            { title: 'Line',                href: '/maintenance/lines',     icon: Layers,    permission: 'lines.view' },
            { title: 'Mesin',               href: '/maintenance/mesin',     icon: Cog,       permission: 'lines.view' },
        ],
    },
    {
        title: 'OEE',
        icon: TrendingUp,
        permission: 'oee.view',
        routes: ['/oee'],
        dashboardRoute: '/oee',
        items: [
            { title: 'Dashboard', href: '/oee', icon: BarChart3, permission: 'oee.view' },
        ],
    },
    {
        title: 'Kanban Production',
        icon: Package,
        permission: 'stock.view',
        routes: ['/products', '/kanbans'],
        dashboardRoute: '/products',
        items: [
            { title: 'Products',     href: '/products', icon: Package, permission: 'stock.view' },
            { title: 'Scan History', href: '/kanbans',  icon: Scan,    permission: 'stock.view' },
        ],
    },
];

const visibleNavGroups = computed(() =>
    allNavGroups.filter(group => can(group.permission))
);

const currentSystem = computed(() => {
    const currentUrl = page.url;

    if (currentUrl === '/welcome' || currentUrl === '/') {
        return null;
    }

    let foundSystem: NavGroup | null = null;

    for (const group of visibleNavGroups.value) {
        for (const route of group.routes) {
            if (currentUrl.startsWith(route)) {
                foundSystem = group;
                localStorage.setItem('lastSystem', group.title);
                break;
            }
        }
        if (foundSystem) break;
    }

    if (!foundSystem && currentUrl.startsWith('/settings')) {
        const lastSystemTitle = localStorage.getItem('lastSystem');
        if (lastSystemTitle) {
            foundSystem = visibleNavGroups.value.find(g => g.title === lastSystemTitle) || null;
        }
    }

    return foundSystem;
});

const visibleItems = computed(() => {
    if (!currentSystem.value) return [];
    return currentSystem.value.items.filter(item =>
        !item.permission || can(item.permission)
    );
});

const logoHref = computed(() => {
    if (currentSystem.value) {
        return currentSystem.value.dashboardRoute;
    }
    return '/welcome';
});

const footerNavItems: NavItem[] = [
    { title: 'Home',     href: '/welcome',  icon: Home },
    { title: 'Settings', href: '/settings', icon: Settings },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 dark:hover:from-blue-900/20 dark:hover:to-indigo-900/20 transition-all duration-300">
                        <Link :href="logoHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup v-if="currentSystem">
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in visibleItems" :key="item.title">
                            <SidebarMenuButton
                                as-child
                                :tooltip="item.title"
                                class="hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 dark:hover:from-blue-900/20 dark:hover:to-indigo-900/20 data-[active=true]:bg-gradient-to-r data-[active=true]:from-blue-100 data-[active=true]:to-indigo-100 dark:data-[active=true]:from-blue-900/30 dark:data-[active=true]:to-indigo-900/30 data-[active=true]:text-blue-700 dark:data-[active=true]:text-blue-300 transition-all duration-300 rounded-xl"
                            >
                                <Link :href="item.href">
                                    <component :is="item.icon" class="w-4 h-4" />
                                    <span class="font-medium">{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <div v-if="!currentSystem" class="px-4 py-8 text-center group-data-[collapsible=icon]:py-4">
                <div class="p-4 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-2xl border border-blue-100 dark:border-blue-800 group-data-[collapsible=icon]:p-2">
                    <Home class="w-8 h-8 mx-auto mb-2 text-blue-600 group-data-[collapsible=icon]:w-5 group-data-[collapsible=icon]:h-5 group-data-[collapsible=icon]:mb-0" />
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-medium group-data-[collapsible=icon]:hidden">
                        Pilih sistem untuk melihat menu
                    </p>
                </div>
            </div>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
