<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { toUrl, urlIsActive } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

const baseNavItems: NavItem[] = [
    { title: 'Profile',         href: editProfile() },
    { title: 'Password',        href: editPassword() },
    { title: 'Two-Factor Auth', href: show() },
    { title: 'Appearance',      href: editAppearance() },
];

const adminNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];
    if (can('settings.roles')) {
        items.push({ title: 'Roles & Permissions', href: '/settings/roles' as any });
        items.push({ title: 'Permissions',         href: '/settings/permissions' as any });
    }
    if (can('settings.users')) {
        items.push({ title: 'User Management', href: '/settings/users' as any });
    }
    return items;
});

const sidebarNavItems = computed(() => [...baseNavItems, ...adminNavItems.value]);

const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
</script>

<template>
    <div class="px-4 py-6 space-y-6">
        <Heading
            title="Settings"
            description="Manage your profile and account settings"
        />
        <nav class="flex items-center gap-1 border-b overflow-x-auto pb-px">
            <Link
                v-for="item in sidebarNavItems"
                :key="toUrl(item.href)"
                :href="toUrl(item.href) ?? ''"
                :class="[
                    'inline-flex items-center gap-2 px-4 py-2.5 text-sm whitespace-nowrap transition-colors rounded-t-md border-b-2 -mb-px',
                    urlIsActive(item.href, currentPath)
                        ? 'border-foreground font-medium text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-muted-foreground/40',
                ]"
            >
                <component :is="item.icon" v-if="item.icon" class="h-4 w-4 shrink-0" />
                {{ item.title }}
            </Link>
        </nav>
        <div class="w-full">
            <slot />
        </div>
    </div>
</template>
