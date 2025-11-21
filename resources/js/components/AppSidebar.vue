<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    LayoutGrid,
    // ClipboardList,
    // Package,
    PackageSearch,
    // BarChart3,
    Warehouse,
    Settings,
    // Activity,
    AlertTriangle,
    Users,
    BoxIcon,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    // {
    //     title: 'Production Input',
    //     href: '/production-plans',
    //     icon: ClipboardList,
    // },
    // {
    //     title: 'Production Monitoring',
    //     href: '/production-monitoring',
    //     icon: Activity,
    // },
    // {
    //     title: 'Master Parts',
    //     href: '/parts',
    //     icon: Package,
    // },
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
    // {
    //     title: 'Reports',
    //     href: '/reports',
    //     icon: BarChart3,
    // },
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
];

const footerNavItems: NavItem[] = [
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
                        <Link href="/dashboard">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>
        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>
        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
