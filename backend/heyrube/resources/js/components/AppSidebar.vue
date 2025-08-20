<script setup lang="ts">
import JournalList from '@/components/JournalList.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavUser from '@/components/NavUser.vue';
import TrashBin from '@/components/TrashBin.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ListCheckIcon, NotebookPen } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const footerNavItems: NavItem[] = [
    {
        title: 'Create a new Journal',
        href: '/journals/create',
        icon: NotebookPen,
    },
];

</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                        <JournalList />
            </SidebarMenu>
            <div class="px-4 py-2">
                <TrashBin />
            </div>
        </SidebarHeader>

        <SidebarContent>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
