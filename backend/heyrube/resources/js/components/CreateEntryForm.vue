<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useJournalStore } from '@/stores/journals';
import MarkdownIt from 'markdown-it';

// PROPS & STORE
const props = defineProps({ entryToEdit: { type: Object, default: null } });
const emit = defineEmits(['cancel', 'success']);
const journalStore = useJournalStore();
const journals = computed(() => journalStore.journals);

// FORM STATE
const creatingNewJournal = ref(false);
const selectedJournalId = ref(journalStore.selectedJournalId);
const entryContent = ref('');
const newJournalTitle = ref('');
const errors = ref<{ content?: string, title?: string }>({});

// MARKDOWN
const md = new MarkdownIt();
const renderedMarkdown = computed(() => md.render(entryContent.value || ''));

// Prefill form for editing
defaultPrefill();
function defaultPrefill() {
  if (props.entryToEdit) {
    selectedJournalId.value = props.entryToEdit.journal_id || journalStore.selectedJournalId || '';
    entryContent.value = props.entryToEdit.content || '';
  }
}
watch(() => props.entryToEdit, defaultPrefill);

// HELPERS
function getCookie(name: string) {
  const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
  return match ? decodeURIComponent(match[3]) : null;
}
function authHeaders() {
  return { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') ?? '' };
}
function isEditMode() {
  return !!props.entryToEdit;
}

// UI LABELS
const formTitle = computed(() => isEditMode() ? 'Edit Journal Entry' : 'New Journal Entry');
const buttonLabel = computed(() => isEditMode() ? 'Save Changes' : 'Save Entry');
const discardLabel = computed(() => isEditMode() ? 'Cancel' : 'Discard');

// FORM ACTIONS
async function handleJournalChange(event: Event) {
  const value = (event.target as HTMLSelectElement).value;
  if (value === 'new') {
    creatingNewJournal.value = true;
  } else {
    creatingNewJournal.value = false;
    selectedJournalId.value = value;
  }
}
function cancel() {
  if (isEditMode()) {
    emit('cancel');
  } else {
    journalStore.stopCreatingEntry();
  }
}
async function submit() {
  errors.value = {};
  let journalId = selectedJournalId.value;

  if (!isEditMode() && creatingNewJournal.value) {
    if (!newJournalTitle.value.trim()) {
      errors.value.title = 'Journal title is required.';
      return;
    }
    try {
      await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
      const response = await fetch('/journals', {
        method: 'POST', credentials: 'include', headers: authHeaders(),
        body: JSON.stringify({ title: newJournalTitle.value })
      });
      if (!response.ok) throw new Error('Journal create failed');
      const journal = await response.json();
      journalId = journal.id;
    } catch (e) {
      errors.value.title = 'Could not create journal.';
      return;
    }
  }
  if (!entryContent.value.trim()) {
    errors.value.content = 'Entry content is required.';
    return;
  }
  try {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    if (isEditMode()) {
      // EDIT: PUT
      const response = await fetch(`/api/journals/${journalId}/entries/${props.entryToEdit.id}`, {
        method: 'PUT', credentials: 'include', headers: authHeaders(),
        body: JSON.stringify({
          content: entryContent.value,
          journal_id: journalId
        })
      });
      if (!response.ok) throw new Error('Entry update failed');
      const updatedEntry = await response.json();
      emit('success', updatedEntry);
    } else {
      // CREATE: POST
      const response = await fetch(`/api/journals/${journalId}/entries`, {
        method: 'POST', credentials: 'include', headers: authHeaders(),
        body: JSON.stringify({ content: entryContent.value, journal_id: journalId })
      });
      if (!response.ok) throw new Error('Entry create failed');
      const newEntry = await response.json();
      const journal = journalStore.journals.find(j => j.id === journalId);
      if (journal) {
        if (!journal.entries) journal.entries = [];
        journal.entries.unshift(newEntry);
      }
      journalStore.stopCreatingEntry();
    }
  } catch (e) {
    errors.value.content = isEditMode() ? 'Failed to update entry.' : 'Failed to create entry.';
  }
}
</script>
<template>
  <form class="flex flex-col flex-1 h-full w-full gap-4 rounded-xl p-4 bg-white/60 dark:bg-zinc-900/60" @submit.prevent="submit">
    <label class="block mb-2 font-semibold">Journal...</label>
    <select
      class="block w-full mb-4 border rounded px-2 py-1 bg-black text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
      v-model="selectedJournalId"
      @change="handleJournalChange"
    >
      <option
        v-for="journal in journals"
        :key="journal.id"
        :value="journal.id"
      >
        {{ journal.title }}
      </option>
      <option v-if="!isEditMode()" value="new">+ Create New Journal…</option>
    </select>
    <div v-if="creatingNewJournal" class="mb-4">
      <input
        class="block w-full border rounded px-2 py-1"
        type="text"
        v-model="newJournalTitle"
        placeholder="New Journal Title"
      />
      <div v-if="errors.title" class="text-red-500 text-sm mt-1">{{ errors.title }}</div>
    </div>
    <textarea
      class="block w-full border rounded px-2 py-2 min-h-[240px]"
      v-model="entryContent"
      placeholder="Write your journal entry..."
    ></textarea>
    <div v-if="errors.content" class="text-red-500 text-sm mt-1">{{ errors.content }}</div>

    <div class="mt-6 bg-neutral-900/80 rounded-lg shadow p-3">
      <label class="block font-semibold mb-1 text-sm text-gray-400 dark:text-gray-300">Live Markdown Preview:</label>
      <div class="prose prose-neutral dark:prose-invert max-w-none" v-html="renderedMarkdown"></div>
    </div>

    <div class="flex gap-2 mt-4 justify-end">
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
      <button type="button" @click="cancel" class="px-4 py-2 rounded border border-gray-400">Discard</button>
    </div>
  </form>
</template>

