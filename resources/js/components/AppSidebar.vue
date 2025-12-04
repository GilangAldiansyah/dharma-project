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
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

interface NavGroup {
    title: string;
    icon: any;
    items: NavItem[];
    routes: string[];
}

const page = usePage();

const allNavGroups: NavGroup[] = [
    {
        title: 'Control Stock System',
        icon: Package,
        routes: ['/dashboard', '/output', '/stock', '/forecast'],
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
        routes: ['/ng-reports', '/suppliers', '/parts'],
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
        routes: ['/die-shop-dashboard', '/die-shop-reports', '/die-parts'],
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
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/welcome">
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
                            >
                                <Link :href="item.href">
                                    <component :is="item.icon" class="w-4 h-4" />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <div v-if="!currentSystem" class="px-4 py-8 text-center group-data-[collapsible=icon]:py-4">
                <Home class="w-8 h-8 mx-auto mb-2 text-muted-foreground group-data-[collapsible=icon]:w-5 group-data-[collapsible=icon]:h-5 group-data-[collapsible=icon]:mb-0" />
                <p class="text-sm text-muted-foreground group-data-[collapsible=icon]:hidden">
                    Pilih sistem untuk melihat menu
                </p>
            </div>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
