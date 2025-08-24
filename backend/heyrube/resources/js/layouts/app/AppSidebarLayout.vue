<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Button } from '@/components/ui/button';
import { Link, usePage } from '@inertiajs/vue3';
import { Network, LayoutGrid } from 'lucide-vue-next';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const isGraph = () => page.url?.startsWith('/graph');
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
            <!-- Floating Graph link bottom-right -->
            <div class="fixed bottom-4 right-4 z-40">
                <Link v-if="!isGraph()" href="/graph" title="Global Graph">
                    <Button size="icon" class="rounded-full shadow-md cursor-pointer dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700">
                        <Network class="h-5 w-5" />
                        <span class="sr-only">Open Global Graph</span>
                    </Button>
                </Link>
                <Link v-else href="/dashboard" title="Back to Dashboard">
                    <Button size="icon" class="rounded-full shadow-md cursor-pointer dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700">
                        <LayoutGrid class="h-5 w-5" />
                        <span class="sr-only">Back to Dashboard</span>
                    </Button>
                </Link>
            </div>
        </AppContent>
    </AppShell>
</template>
