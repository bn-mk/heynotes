<script setup lang="ts">
import JournalList from '@/components/JournalList.vue';
import NavUser from '@/components/NavUser.vue';
import TrashBin from '@/components/TrashBin.vue';
import { Button } from '@/components/ui/button';
import { PlusSquare } from 'lucide-vue-next';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import { useJournalStore } from '@/stores/journals';
import AppLogo from './AppLogo.vue';

const journalStore = useJournalStore();

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
            <div class="py-2">
                <Button 
                    @click="journalStore.startCreatingJournal()"
                    size="icon"
                    variant="default"
                    class="cursor-pointer dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700"
                    title="New Journal"
                >
                    <PlusSquare class="h-4 w-4" />
                    <span class="sr-only">Create New Journal</span>
                </Button>
            </div>
        </SidebarHeader>

        <SidebarContent>
        </SidebarContent>

        <SidebarFooter>
            <div class="py-2">
                <TrashBin />
            </div>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
