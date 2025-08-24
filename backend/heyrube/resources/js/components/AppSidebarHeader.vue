<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItemType } from '@/types';
import { PlusSquare, Tag } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

function triggerStartCreatingEntry() {
    window.dispatchEvent(new CustomEvent('start-creating-entry'));
}
function triggerOpenTagsDialog() {
    window.dispatchEvent(new CustomEvent('open-tags-dialog'));
}
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="flex items-center gap-2">
            <Button
                variant="ghost"
                size="icon"
                class="h-9 w-9 cursor-pointer"
                title="Manage Tags"
                @click="triggerOpenTagsDialog"
            >
                <Tag class="h-5 w-5" />
                <span class="sr-only">Manage Tags</span>
            </Button>
            <Button
                variant="ghost"
                size="icon"
                class="h-9 w-9 cursor-pointer"
                title="New Entry"
                @click="triggerStartCreatingEntry"
            >
                <PlusSquare class="h-5 w-5" />
                <span class="sr-only">New Entry</span>
            </Button>
        </div>
    </header>
</template>
