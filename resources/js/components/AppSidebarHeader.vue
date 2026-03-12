<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';

withDefaults(
    defineProps<{ breadcrumbs?: BreadcrumbItemType[] }>(),
    { breadcrumbs: () => [] },
);
</script>

<template>
    <header class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b border-sidebar-border/50 bg-background/80 backdrop-blur-xl px-4 transition-all ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
       <SidebarTrigger class="w-9 h-9 rounded-xl text-muted-foreground hover:text-foreground hover:bg-accent active:scale-95 transition-all duration-200 flex items-center justify-center border-0 -ml-1 shrink-0 [&>svg]:w-4 [&>svg]:h-4" />
        <div v-if="breadcrumbs?.length" class="flex items-center gap-2 min-w-0">
            <div class="w-px h-4 bg-border shrink-0"></div>
            <Breadcrumbs :breadcrumbs="breadcrumbs" />
        </div>
    </header>
</template>
