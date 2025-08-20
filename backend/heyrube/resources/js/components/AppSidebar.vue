<script setup lang="ts">
import JournalList from '@/components/JournalList.vue';
import NavUser from '@/components/NavUser.vue';
import TrashBin from '@/components/TrashBin.vue';
import CreateJournalDialog from '@/components/CreateJournalDialog.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import AppLogo from './AppLogo.vue';

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
            <div class="px-4 py-2">
                <CreateJournalDialog />
            </div>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
