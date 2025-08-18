<script setup lang="ts">
import { ref, nextTick, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/card/Card.vue';
import { onMounted } from 'vue';
import { JournalListType } from '@/types';
import { useJournalStore } from '@/stores/journals';
import CreateEntryForm from '@/components/CreateEntryForm.vue';

const props = defineProps<{
  journals: JournalListType[],
}>();

const journalStore = useJournalStore();
import MarkdownIt from 'markdown-it';
const md = new MarkdownIt();

function renderMarkdown(content: string) {
  return md.render(content || '');
}

const editingEntry = ref(null);

const sortedEntries = computed(() => {
  const entries = journalStore.selectedJournal?.entries || [];
  return entries.slice().sort((a, b) => {
    const da = new Date(a.created_at || 0);
    const db = new Date(b.created_at || 0);
    return db.getTime() - da.getTime();
  });
});

function handleEditEntry(entry: any) {
  editingEntry.value = {...entry};
  entry.openMenu = false;
}
function handleEditCancel() {
  editingEntry.value = null;
}
function handleEditSuccess(updatedEntry) {
  const originalJournalId = editingEntry.value.journal_id;
  const newJournalId = updatedEntry.journal_id;

  // Remove from original journal's entries
  const originalJournal = journalStore.journals.find(j => j.id === originalJournalId);
  if (originalJournal && originalJournal.entries) {
    const idx = originalJournal.entries.findIndex(e => e.id === updatedEntry.id);
    if (idx > -1) originalJournal.entries.splice(idx, 1);
  }
  // Prepend to new journal's entries
  const newJournal = journalStore.journals.find(j => j.id === newJournalId);
  if (newJournal) {
    if (!newJournal.entries) newJournal.entries = [];
    newJournal.entries.unshift(updatedEntry);
    // Switch to the new journal after move
    journalStore.selectJournal(newJournalId);
  }
  editingEntry.value = null;
}
async function handleDeleteEntry(entry: any) {
  if (confirm('Are you sure you want to delete this entry?')) {
    const journalId = journalStore.selectedJournalId;
    // Add XSRF-Token header for Laravel CSRF
    const getCookie = (name: string) => {
      const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
      return match ? decodeURIComponent(match[3]) : null;
    };
    const xsrf = getCookie('XSRF-TOKEN') ?? '';
    try {
      const resp = await fetch(`/api/journals/${journalId}/entries/${entry.id}`, {
        method: 'DELETE',
        credentials: 'include',
        headers: { 'X-XSRF-TOKEN': xsrf },
      });
      if (resp.status === 204) {
        // Remove entry client-side from Pinia store
        const journal = journalStore.journals.find(j => j.id === journalId);
        if (journal && journal.entries) {
          const idx = journal.entries.findIndex(e => e.id === entry.id);
          if (idx > -1) journal.entries.splice(idx, 1);
        }
      } else {
        alert('Delete failed.');
      }
    } catch (e) {
      alert('Request error during delete.');
    }
  }
  entry.openMenu = false;
}

onMounted(() => {
  if (props.journals) {
    // Ensure all ids are strings (for Mongo compatibility)
    journalStore.setJournals(props.journals.map(j => ({ ...j, id: j.id.toString() })));
    // Initialize menu open/close tracking
    nextTick(() => {
      for (const journal of journalStore.journals) {
        if (journal.entries) {
          for (const entry of journal.entries) {
            entry.openMenu = false;
          }
        }
      }
    });
  }
});
</script>

<template>
  <AppLayout>
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <CreateEntryForm v-if="journalStore.creatingEntry" />
      <CreateEntryForm
        v-else-if="editingEntry"
        :entry-to-edit="editingEntry"
        @cancel="handleEditCancel"
        @success="handleEditSuccess"
      />
      <template v-else>
        <div v-if="journalStore.selectedJournal">
          <h1 class="text-2xl font-bold mb-4">{{ journalStore.selectedJournal.title }}</h1>
          <div v-if="journalStore.selectedJournal.entries && journalStore.selectedJournal.entries.length > 0" class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 items-start">
          <Card v-for="entry in sortedEntries" :key="entry.id">
<div class="p-4 flex flex-col relative max-h-[33vh] overflow-hidden">
              <button
                @click="entry.openMenu = !entry.openMenu"
                class="absolute top-2 right-2 p-1 rounded-full hover:bg-gray-200 dark:hover:bg-zinc-700 cursor-pointer"
                aria-label="Show entry menu"
                type="button"
              >
                <!-- three dots icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <circle cx="5" cy="12" r="1.5" />
                  <circle cx="12" cy="12" r="1.5" />
                  <circle cx="19" cy="12" r="1.5" />
                </svg>
              </button>
              <div v-if="entry.openMenu" class="absolute top-9 right-2 z-10 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border py-1 px-2 flex flex-col min-w-[120px]">
                <button @click="handleEditEntry(entry)" class="cursor-pointer px-2 py-1 text-sm text-left hover:bg-blue-100 dark:hover:bg-zinc-700 rounded">Edit</button>
                <button @click="handleDeleteEntry(entry)" class="cursor-pointer px-2 py-1 text-sm text-left hover:bg-red-100 dark:hover:bg-red-700 rounded text-red-600 dark:text-red-300">Delete</button>
              </div>
<div class="text-sm text-white-800 whitespace-pre-line mb-2 pr-8">
<div class="prose prose-neutral dark:prose-invert max-w-none" v-html="renderMarkdown(entry.content)"></div>
              </div>
              <div class="mt-auto text-xs text-gray-400">{{ new Date(entry.created_at).toLocaleString() }}</div>
            </div>
          </Card>
          </div>
          <div v-else class="text-gray-500 text-center">No entries yet.</div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>
