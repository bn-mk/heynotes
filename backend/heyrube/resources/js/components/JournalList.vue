<script setup lang="ts">
import { SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { NotebookPen } from 'lucide-vue-next';
import { useJournalStore } from '@/stores/journals';
import DeleteJournalButton from '@/components/DeleteJournalButton.vue';
const component = NotebookPen;
const journalStore = useJournalStore();

// Access sidebar context to control the mobile sheet
const { isMobile, setOpenMobile } = useSidebar();

function handleSelect(journalId: string | number) {
  journalStore.selectJournal(String(journalId));
  // On mobile, close the sidebar after selecting a journal
  if (isMobile?.value) setOpenMobile(false);
}

</script>
<template>

    <SidebarMenuItem v-for="journal in journalStore.journals" :key="journal.id">
        <div class="journal-item flex items-center">
            <SidebarMenuButton as-child :is-active="journal.id === journalStore.selectedJournalId" :tooltip="journal.title" class="flex-1">
                <button type="button" @click="handleSelect(journal.id)" :id="`journal-link-${journal.id}`" class="cursor-pointer">
                    <component :icon="component" />
                    <span>{{ journal.title }}</span>
                </button>
            </SidebarMenuButton>
            <div class="delete-btn opacity-0 transition-opacity">
                <DeleteJournalButton 
                    :journal-id="String(journal.id)" 
                    :journal-title="journal.title"
                />
            </div>
        </div>
    </SidebarMenuItem>

</template>

<style scoped>
.journal-item:hover .delete-btn {
    opacity: 1;
}
</style>
