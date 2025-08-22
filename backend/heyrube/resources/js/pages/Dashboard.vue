<script setup lang="ts">
import { ref, nextTick, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/card/Card.vue';
import { Button } from '@/components/ui/button';
import { Plus, PenSquare } from 'lucide-vue-next';
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

const getBorderColor = (index: number, total: number) => {
  if (total <= 1) {
    return 'rgb(147, 197, 253)'; // pastel blue
  }
  // Gradient from pastel blue to pastel pink/red based on recency
  const percent = index / (total - 1);
  
  // Pastel colors with higher lightness and lower saturation
  // Newest (index 0): pastel blue, Oldest: pastel pink/red
  const r = Math.round(180 + (75 * percent));  // 180-255 range for softer reds
  const g = Math.round(150 - (50 * percent));  // 150-100 range for mid tones
  const b = Math.round(250 - (100 * percent)); // 250-150 range for blues
  
  return `rgb(${r}, ${g}, ${b})`;
};

const editingEntry = ref(null);
const editingJournalId = ref<string | null>(null);
const editingJournalTitle = ref('');
const draggedEntry = ref(null);
const draggedOverEntry = ref(null);

const sortedEntries = computed(() => {
  const entries = journalStore.selectedJournal?.entries || [];
  // First sort by display_order if it exists, then by created_at
  return entries.slice().sort((a, b) => {
    // If both have display_order, use that
    if (a.display_order !== undefined && b.display_order !== undefined) {
      return a.display_order - b.display_order;
    }
    // Otherwise fall back to date sorting
    const da = new Date(a.created_at || 0);
    const db = new Date(b.created_at || 0);
    return db.getTime() - da.getTime();
  });
});

function handleEditTitle() {
  if (journalStore.selectedJournal) {
    editingJournalId.value = journalStore.selectedJournal.id;
    editingJournalTitle.value = journalStore.selectedJournal.title;
  }
}

function handleCancelEdit() {
  editingJournalId.value = null;
  editingJournalTitle.value = '';
}

async function handleSaveTitle() {
  if (editingJournalId.value) {
    await journalStore.updateJournal(editingJournalId.value, { title: editingJournalTitle.value });
    editingJournalId.value = null;
    editingJournalTitle.value = '';
  }
}

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

  // Remove from original journal\'s entries
  const originalJournal = journalStore.journals.find(j => j.id === originalJournalId);
  if (originalJournal && originalJournal.entries) {
    const idx = originalJournal.entries.findIndex(e => e.id === updatedEntry.id);
    if (idx > -1) originalJournal.entries.splice(idx, 1);
  }
  // Prepend to new journal\'s entries
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

// Drag and Drop handlers
function handleDragStart(entry: any, event: DragEvent) {
  draggedEntry.value = entry;
  event.dataTransfer!.effectAllowed = 'move';
  // Add dragging class to the element
  (event.target as HTMLElement).classList.add('opacity-50');
}

function handleDragEnd(event: DragEvent) {
  // Remove dragging class
  (event.target as HTMLElement).classList.remove('opacity-50');
  draggedEntry.value = null;
  draggedOverEntry.value = null;
}

function handleDragOver(event: DragEvent) {
  if (event.preventDefault) {
    event.preventDefault(); // Allows us to drop
  }
  event.dataTransfer!.dropEffect = 'move';
  return false;
}

function handleDragEnter(entry: any, event: DragEvent) {
  if (draggedEntry.value && entry.id !== draggedEntry.value.id) {
    draggedOverEntry.value = entry;
    (event.currentTarget as HTMLElement).classList.add('border-4', 'border-blue-400');
  }
}

function handleDragLeave(event: DragEvent) {
  (event.currentTarget as HTMLElement).classList.remove('border-4', 'border-blue-400');
}

async function handleDrop(targetEntry: any, event: DragEvent) {
  if (event.stopPropagation) {
    event.stopPropagation(); // stops some browsers from redirecting.
  }
  
  (event.currentTarget as HTMLElement).classList.remove('border-4', 'border-blue-400');
  
  if (draggedEntry.value && targetEntry.id !== draggedEntry.value.id) {
    const entries = sortedEntries.value;
    const draggedIndex = entries.findIndex(e => e.id === draggedEntry.value.id);
    const targetIndex = entries.findIndex(e => e.id === targetEntry.id);
    
    if (draggedIndex !== -1 && targetIndex !== -1) {
      // Reorder the entries array
      const [removed] = entries.splice(draggedIndex, 1);
      entries.splice(targetIndex, 0, removed);
      
      // Update display_order for all entries
      entries.forEach((entry, index) => {
        entry.display_order = index;
      });
      
      // Update the journal's entries in the store
      const journal = journalStore.journals.find(j => j.id === journalStore.selectedJournalId);
      if (journal) {
        journal.entries = entries;
      }
      
      // Persist the new order to the backend
      await saveEntryOrder(entries);
    }
  }
  
  draggedEntry.value = null;
  draggedOverEntry.value = null;
  return false;
}

async function saveEntryOrder(entries: any[]) {
  const getCookie = (name: string) => {
    const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
    return match ? decodeURIComponent(match[3]) : null;
  };
  const xsrf = getCookie('XSRF-TOKEN') ?? '';
  
  try {
    const orderData = entries.map((entry, index) => ({
      id: entry.id,
      display_order: index
    }));
    
    const response = await fetch(`/api/journals/${journalStore.selectedJournalId}/entries/reorder`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': xsrf
      },
      body: JSON.stringify({ entries: orderData })
    });
    
    if (!response.ok) {
      console.error('Failed to save entry order');
      // Optionally, revert the order in the UI
    }
  } catch (error) {
    console.error('Error saving entry order:', error);
  }
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
      <CreateEntryForm 
        v-if="journalStore.creatingEntry" 
        :create-new-journal="journalStore.creatingJournal"
      />
      <CreateEntryForm
        v-else-if="editingEntry"
        :entry-to-edit="editingEntry"
        @cancel="handleEditCancel"
        @success="handleEditSuccess"
      />
      <template v-else>
        <div v-if="journalStore.selectedJournal">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center">
              <h1 v-if="editingJournalId !== journalStore.selectedJournal.id" class="text-2xl font-bold">
                {{ journalStore.selectedJournal.title }}
              </h1>
              <div v-else class="flex items-center">
                <input v-model="editingJournalTitle" @keyup.enter="handleSaveTitle" @keyup.esc="handleCancelEdit" class="text-2xl font-bold" />
                <button @click="handleSaveTitle" class="ml-2 px-2 py-1 bg-green-500 text-white rounded">Save</button>
                <button @click="handleCancelEdit" class="ml-2 px-2 py-1 bg-red-500 text-white rounded">Cancel</button>
              </div>
              <button @click="handleEditTitle" v-if="editingJournalId !== journalStore.selectedJournal.id" class="ml-2">
                <!-- Pencil Icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
                  <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
            <Button 
              @click="journalStore.startCreatingEntry()"
              class="gap-2 cursor-pointer"
            >
              <PenSquare class="h-4 w-4" />
              New Entry
            </Button>
          </div>
          <div v-if="journalStore.selectedJournal.entries && journalStore.selectedJournal.entries.length > 0" class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 items-start">
          <Card 
            v-for="(entry, index) in sortedEntries" 
            :key="entry.id" 
            class="border-2 transition-all cursor-move"
            :style="{ borderColor: getBorderColor(index, sortedEntries.length) }"
            draggable="true"
            @dragstart="handleDragStart(entry, $event)"
            @dragend="handleDragEnd($event)"
            @dragover="handleDragOver($event)"
            @dragenter="handleDragEnter(entry, $event)"
            @dragleave="handleDragLeave($event)"
            @drop="handleDrop(entry, $event)"
          >
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
