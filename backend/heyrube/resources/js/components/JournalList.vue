<script setup lang="ts">
import { SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { NotebookPen } from 'lucide-vue-next';
import { useJournalStore } from '@/stores/journals';
const component = NotebookPen;
const journalStore = useJournalStore();

function handleSelect(journalId: number) {
  journalStore.selectJournal(journalId);
}

</script>
<template>

    <SidebarMenuItem v-for="journal in journalStore.journals" :key="journal.id">
        <SidebarMenuButton as-child :is-active="journal.id === journalStore.selectedJournalId" :tooltip="journal.title">
            <button type="button" @click="handleSelect(journal.id)" :id="`journal-link-${journal.id}`" class="cursor-pointer">
                <component :icon="component" />
                <span>{{ journal.title }}</span>
            </button>
        </SidebarMenuButton>
    </SidebarMenuItem>

</template>
