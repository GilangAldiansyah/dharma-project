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
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

interface NavGroup {
    title: string;
    icon: any;
    items: NavItem[];
    routes: string[];
    dashboardRoute: string;
}

const page = usePage();

const allNavGroups: NavGroup[] = [
    {
        title: 'Control Stock System',
        icon: Package,
        routes: ['/dashboard', '/output', '/stock', '/forecast', '/settings'],
        dashboardRoute: '/dashboard',
        items: [
            {
                title: 'Dashboard',
                href: '/dashboard',
                icon: LayoutGrid,
            },
            {
                title: 'Output Product',
                href: '/output',
                icon: PackageSearch,
            },
            {
                title: 'Stock Control',
                href: '/stock',
                icon: Warehouse,
            },
            {
                title: 'Forecast',
                href: '/forecast',
                icon: TrendingUp,
            },
        ],
    },
    {
        title: 'NG System',
        icon: AlertTriangle,
        routes: ['/ng-reports', '/suppliers', '/parts', '/settings'],
        dashboardRoute: '/ng-reports/dashboard',
        items: [
            {
                title: 'Dashboard NG',
                href: '/ng-reports/dashboard',
                icon: BarChart3,
            },
            {
                title: 'NG Reports',
                href: '/ng-reports',
                icon: AlertTriangle,
            },
            {
                title: 'Master Suppliers',
                href: '/suppliers',
                icon: Users,
            },
            {
                title: 'Master Parts',
                href: '/parts',
                icon: BoxIcon,
            },
        ],
    },
    {
        title: 'Die Shop System',
        icon: Wrench,
        routes: ['/die-shop-dashboard', '/die-shop-reports', '/die-parts', '/settings'],
        dashboardRoute: '/die-shop-dashboard',
        items: [
            {
                title: 'Dashboard Die Shop',
                href: '/die-shop-dashboard',
                icon: BarChart3,
            },
            {
                title: 'Laporan Perbaikan',
                href: '/die-shop-reports',
                icon: FileText,
            },
            {
                title: 'Master Die Parts',
                href: '/die-parts',
                icon: Box,
            },
        ],
    },
    {
        title: 'Robot Monitor',
        icon: Bot,
        routes: ['/esp32/monitor','/settings'],
        dashboardRoute: '/esp32/monitor',
        items: [
            {
                title: 'Monitor',
                href: '/esp32/monitor',
                icon: Activity,
            },
        ],
    },
    {
        title: 'Material Monitoring',
        icon: ClipboardList,
        routes: ['/transaksi','transaksi/dashboard','/materials', '/part-materials', '/settings'],
        dashboardRoute: '/transaksi',
        items: [
            {
                title: 'Transaksi Material',
                href: '/transaksi',
                icon: ClipboardList,
            },
            {
                title: 'Dashboard Transaksi',
                href: '/transaksi/dashboard',
                icon: BarChart3,
            },
            {
                title: 'Master Material',
                href: '/materials',
                icon: Database,
            },
            {
                title: 'Master Part',
                href: '/part-materials',
                icon: Layers,
            },
        ],
    },
    {
      title: 'Maintenance Monitoring',
        icon: Activity,
        routes: ['/maintenance', '/settings'],
        dashboardRoute: '/maintenance/lines',
        items: [
            {
                title: 'Dashboard',
                href: '/maintenance/dashboard',
                icon: BarChart3,
            },
            {
                title: 'Laporan Maintenance',
                href: '/maintenance',
                icon: Activity,
            },
               {
                title: 'Line',
                href: '/maintenance/lines',
                icon: Layers,
            },
            {
                title: 'Mesin',
                href: '/maintenance/mesin',
                icon: Cog,
            },
        ],
    },
    {
      title: 'OEE',
        icon: TrendingUp,
        routes: ['/oee', '/settings'],
        dashboardRoute: '/oee',
        items: [
            {
                title: 'Dashboard',
                href: '/oee',
                icon: BarChart3,
            },
        ],
    },
];

const currentSystem = computed(() => {
    const currentUrl = page.url;

    if (currentUrl === '/welcome' || currentUrl === '/') {
        return null;
    }

    for (const group of allNavGroups) {
        for (const route of group.routes) {
            if (currentUrl.startsWith(route)) {
                return group;
            }
        }
    }

    return null;
});

const logoHref = computed(() => {
    if (currentSystem.value) {
        return currentSystem.value.dashboardRoute;
    }
    return '/welcome';
});

const footerNavItems: NavItem[] = [
    {
        title: 'Home',
        href: '/welcome',
        icon: Home,
    },
    {
        title: 'Settings',
        href: '/settings',
        icon: Settings,
    },
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
                        <SidebarMenuItem v-for="item in currentSystem.items" :key="item.title">
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
