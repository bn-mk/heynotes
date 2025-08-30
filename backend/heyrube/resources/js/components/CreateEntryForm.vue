<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick, onUnmounted } from 'vue';
import { useJournalStore } from '@/stores/journals';
import MarkdownIt from 'markdown-it';
import { Plus, X, Check, Pin } from 'lucide-vue-next';
import jspreadsheet from 'jspreadsheet-ce';
import LinkEntryButton from '@/components/LinkEntryButton.vue';
import DeleteEntryButton from '@/components/DeleteEntryButton.vue';

// PROPS & STORE
const props = defineProps({
  entryToEdit: { type: Object, default: null },
  createNewJournal: { type: Boolean, default: false }
});
const emit = defineEmits(['cancel', 'success']);
const journalStore = useJournalStore();
const journals = computed(() => journalStore.journals);

// FORM STATE
const creatingNewJournal = ref(props.createNewJournal);
const selectedJournalId = ref(props.createNewJournal ? 'new' : journalStore.selectedJournalId);
const entryContent = ref('');
const entryTitle = ref('');
const newJournalTitle = ref('');
const errors = ref<{ content?: string, title?: string }>({});
const cardType = ref<'text' | 'checkbox' | 'spreadsheet'>('text');
const checkboxItems = ref<Array<{ text: string; checked: boolean }>>([]);
const spreadsheetData = ref('');
const newCheckboxItem = ref('');
const selectedMood = ref<string>('');
const pinAfterSave = ref(false);
// Tags state
const selectedTags = ref<string[]>([]);
const tagFilter = ref('');
const canAddTag = computed(() => {
  const name = tagFilter.value.trim();
  if (!name) return false;
  const lower = name.toLowerCase();
  return !journalStore.allTags.some(t => t.toLowerCase() === lower);
});
const isSubmitting = ref(false);

const spreadsheetContainer = ref<HTMLDivElement | null>(null);
const isPinned = ref<boolean>(!!props.entryToEdit?.pinned);
let spreadsheetInstance: any = null;
let darkModeObserver: MutationObserver | null = null;

watch(cardType, async (newType, oldType) => {
  if (oldType === 'spreadsheet' && spreadsheetInstance) {
    spreadsheetInstance.destroy();
    spreadsheetInstance = null;
  }

  if (newType === 'spreadsheet') {
    await nextTick();
    if (spreadsheetContainer.value) {
      const isDarkMode = document.documentElement.classList.contains('dark');
      if (isDarkMode) {
        spreadsheetContainer.value.classList.add('jspreadsheet_dark');
      } else {
        spreadsheetContainer.value.classList.remove('jspreadsheet_dark');
      }

      try {
        const initialData = spreadsheetData.value.trim() ? JSON.parse(spreadsheetData.value) : [[]];
        spreadsheetInstance = jspreadsheet(spreadsheetContainer.value, {
          data: initialData,
          minDimensions: [5, 10],
          tableOverflow: true,
          tableWidth: '100%',
          tableHeight: '400px',
          allowInsertColumn: true,
          allowInsertRow: true,
          allowDeleteColumn: true,
          allowDeleteRow: true,
          defaultColWidth: 120,
        });
      } catch (e) {
        console.error('Failed to parse spreadsheet data:', e);
        spreadsheetInstance = jspreadsheet(spreadsheetContainer.value, {
          data: [[]],
          minDimensions: [10, 5],
        });
      }
    }
  }
});


// When submitting, get data from spreadsheet
async function beforeSubmit() {
  if (cardType.value === 'spreadsheet' && spreadsheetInstance) {
    spreadsheetData.value = JSON.stringify(spreadsheetInstance.getData());
  }
  submit();
}
const moods = [
  { emoji: '😊', label: 'Happy', value: 'happy' },
  { emoji: '😔', label: 'Sad', value: 'sad' },
  { emoji: '😴', label: 'Tired', value: 'tired' },
  { emoji: '😡', label: 'Angry', value: 'angry' },
  { emoji: '😰', label: 'Anxious', value: 'anxious' },
  { emoji: '🤗', label: 'Grateful', value: 'grateful' },
  { emoji: '😌', label: 'Calm', value: 'calm' },
  { emoji: '🤔', label: 'Thoughtful', value: 'thoughtful' },
  { emoji: '😎', label: 'Confident', value: 'confident' },
  { emoji: '😅', label: 'Stressed', value: 'stressed' },
  { emoji: '🥰', label: 'Loved', value: 'loved' },
  { emoji: '😐', label: 'Neutral', value: 'neutral' },
];

// MARKDOWN
const md = new MarkdownIt();
const renderedMarkdown = computed(() => md.render(entryContent.value || ''));

// Prefill form for editing
defaultPrefill();
function defaultPrefill() {
  if (props.entryToEdit) {
    selectedJournalId.value = props.entryToEdit.journal_id || journalStore.selectedJournalId || '';
    cardType.value = props.entryToEdit.card_type || 'text';
    entryTitle.value = props.entryToEdit.title || '';
    selectedMood.value = props.entryToEdit.mood || '';
    isPinned.value = !!props.entryToEdit.pinned;
    if (props.entryToEdit.card_type === 'checkbox' && props.entryToEdit.checkbox_items) {
      checkboxItems.value = [...props.entryToEdit.checkbox_items];
    } else if (props.entryToEdit.card_type === 'spreadsheet') {
      spreadsheetData.value = props.entryToEdit.content || '';
    } else {
      entryContent.value = props.entryToEdit.content || '';
    }
    // Prefill tags for the journal in edit mode as well
    const j = journalStore.journals.find(j => j.id === selectedJournalId.value);
    selectedTags.value = [...(j?.tags || [])];
  } else if (props.createNewJournal) {
    creatingNewJournal.value = true;
    selectedJournalId.value = 'new';
  }
}
watch(() => props.entryToEdit, (val) => { defaultPrefill(); isPinned.value = !!val?.pinned; });
watch(() => props.createNewJournal, (newVal) => {
  if (newVal) {
    creatingNewJournal.value = true;
    selectedJournalId.value = 'new';
  }
});

// Sync selectedTags when journal changes (creation mode)
watch(selectedJournalId, (jid) => {
  if (jid && jid !== 'new') {
    const j = journalStore.journals.find(j => j.id === jid);
    selectedTags.value = [...(j?.tags || [])];
  } else if (jid === 'new') {
    selectedTags.value = [];
  }
});

onMounted(() => {
  // Ensure tags list is loaded
  journalStore.fetchTags?.();
  // Prefill tags from selected journal if any
  if (selectedJournalId.value && selectedJournalId.value !== 'new') {
    const j = journalStore.journals.find(j => j.id === selectedJournalId.value);
    selectedTags.value = [...(j?.tags || [])];
  }

  darkModeObserver = new MutationObserver(() => {
    if (spreadsheetContainer.value) {
      const isDarkMode = document.documentElement.classList.contains('dark');
      if (isDarkMode) {
        spreadsheetContainer.value.classList.add('jspreadsheet_dark');
      } else {
        spreadsheetContainer.value.classList.remove('jspreadsheet_dark');
      }
    }
  });

  darkModeObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
  });
});

onUnmounted(() => {
  if (darkModeObserver) {
    darkModeObserver.disconnect();
  }
});

// HELPERS
function getCookie(name: string) {
  const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
  return match ? decodeURIComponent(match[3]) : null;
}
async function addNewTag() {
  const name = tagFilter.value.trim();
  if (!name) return;
  const created = await journalStore.createTag(name);
  if (created) {
    if (!selectedTags.value.includes(created)) {
      selectedTags.value.push(created);
    }
    // Clear the filter so the full list is visible again
    tagFilter.value = '';
  }
}

function authHeaders() {
  return { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') ?? '' };
}
function isEditMode() {
  return !!props.entryToEdit;
}

async function togglePinInForm() {
  if (!isEditMode()) return;
  const desired = !isPinned.value;
  const xsrf = getCookie('XSRF-TOKEN') ?? '';
  const jid = selectedJournalId.value || journalStore.selectedJournalId;
  if (!jid) return;
  try {
    await fetch(`/api/journals/${jid}/entries/${props.entryToEdit.id}/pin`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': xsrf,
      },
      body: JSON.stringify({ pinned: desired }),
    });
    isPinned.value = desired;
    // Update store so dashboard re-renders behind the form
    const journal = journalStore.journals.find(j => j.id === jid);
    if (journal && journal.entries) {
      const entry = journal.entries.find((e: any) => e.id === props.entryToEdit!.id);
      if (entry) entry.pinned = desired;
      const unpinned = journal.entries.filter((e: any) => !e.pinned);
      unpinned.forEach((e: any, idx: number) => { e.display_order = idx; });
    }
  } catch (e) {
    // noop
  }
}

// UI LABELS
const formTitle = computed(() => {
  if (isEditMode()) return 'Edit Journal Entry';
  if (creatingNewJournal.value) return 'Create New Journal';
  return 'New Journal Entry';
});
const buttonLabel = computed(() => {
  if (isEditMode()) return 'Save Changes';
  if (creatingNewJournal.value && !hasContent()) return 'Create Journal';
  return 'Save Entry';
});

function hasContent(): boolean {
if (cardType.value === 'text') {
    return entryContent.value.trim().length > 0;
  } else if (cardType.value === 'checkbox') {
    return checkboxItems.value.length > 0;
  } else if (cardType.value === 'spreadsheet') {
    return spreadsheetData.value.trim().length > 0;
  }
  return false;
}
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
  if (isSubmitting.value) return;
  
  isSubmitting.value = true;
  errors.value = {};
  let journalId = selectedJournalId.value;

  if (!isEditMode() && creatingNewJournal.value) {
    if (!newJournalTitle.value.trim()) {
      errors.value.title = 'Journal title is required.';
      isSubmitting.value = false;
      return;
    }
    try {
      const response = await fetch('/api/journals', {
        method: 'POST', 
        credentials: 'include', 
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') ?? '',
        },
        body: JSON.stringify({ title: newJournalTitle.value, tags: selectedTags.value })
      });
      
      const contentType = response.headers.get('content-type');
      
      if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`Journal create failed: ${response.status}`);
      }
      
      // Check if response is JSON
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('Server returned non-JSON response');
      }
      
      const journal = await response.json();
      journalId = journal.id || journal._id;
      
      // Add the new journal to the store
      journalStore.journals.push({
        ...journal,
        id: journalId,
        entries: [],
      });
      
      // If no entry content, just select the new journal and exit
      if (!hasContent()) {
        journalStore.selectJournal(journalId);
        journalStore.stopCreatingEntry();
        isSubmitting.value = false;
        return;
      }
    } catch (e) {
      console.error('Journal creation error:', e);
      errors.value.title = 'Could not create journal.';
      isSubmitting.value = false;
      return;
    }
  }
  
  // Only require entry content if not creating a new journal or if content exists
  if (!creatingNewJournal.value && !hasContent()) {
    errors.value.content = cardType.value === 'checkbox' ? 'At least one checklist item is required.' : 'Entry content is required.';
    isSubmitting.value = false;
    return;
  }
  
  // If we have entry content, create the entry
  if (hasContent()) {
    try {
      const entryPayload: any = {
        card_type: cardType.value,
        journal_id: journalId,
        title: entryTitle.value || null,
        mood: selectedMood.value || null
      };
      
if (cardType.value === 'text') {
        entryPayload.content = entryContent.value;
      } else if (cardType.value === 'checkbox') {
        entryPayload.checkbox_items = checkboxItems.value;
      } else if (cardType.value === 'spreadsheet') {
        entryPayload.content = spreadsheetData.value;
      }
      
      // Update journal tags if changed (for existing journals)
      if (journalId && journalId !== 'new') {
        const current = journalStore.journals.find(j => j.id === journalId)?.tags || [];
        const a = [...current].sort();
        const b = [...selectedTags.value].sort();
        if (JSON.stringify(a) !== JSON.stringify(b)) {
          await journalStore.updateJournalTags(journalId, selectedTags.value);
        }
      }

      if (isEditMode()) {
        // EDIT: PUT
        const response = await fetch(`/api/journals/${journalId}/entries/${props.entryToEdit.id}`, {
          method: 'PUT', 
          credentials: 'include', 
          headers: authHeaders(),
          body: JSON.stringify(entryPayload)
        });
        if (!response.ok) {
          const errorText = await response.text();
          console.error('Entry update failed:', response.status, errorText);
          throw new Error('Entry update failed');
        }
        const updatedEntry = await response.json();
        isSubmitting.value = false;
        emit('success', updatedEntry);
      } else {
        // CREATE: POST
        const createPayload: any = {
          card_type: cardType.value,
          title: entryTitle.value || null,
          mood: selectedMood.value || null
        };
        
if (cardType.value === 'text') {
          createPayload.content = entryContent.value;
        } else if (cardType.value === 'checkbox') {
          createPayload.checkbox_items = checkboxItems.value;
        } else if (cardType.value === 'spreadsheet') {
          createPayload.content = spreadsheetData.value;
        }
        
        const response = await fetch(`/api/journals/${journalId}/entries`, {
          method: 'POST', 
          credentials: 'include', 
          headers: authHeaders(),
          body: JSON.stringify(createPayload)
        });
        if (!response.ok) {
          const errorText = await response.text();
          console.error('Entry create failed:', response.status, errorText);
          throw new Error('Entry create failed');
        }
        const newEntry = await response.json();
        const journal = journalStore.journals.find(j => j.id === journalId);
        if (journal) {
          if (!journal.entries) journal.entries = [];
          journal.entries.unshift(newEntry);
          if (pinAfterSave.value) {
            const xsrf = getCookie('XSRF-TOKEN') ?? '';
            try {
              await fetch(`/api/journals/${journalId}/entries/${newEntry.id}/pin`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                  'Content-Type': 'application/json',
                  'X-XSRF-TOKEN': xsrf,
                },
                body: JSON.stringify({ pinned: true }),
              });
              const entryRef = journal.entries.find((e: any) => e.id === newEntry.id);
              if (entryRef) {
                entryRef.pinned = true;
                entryRef.display_order = null;
              }
              const unpinned = journal.entries.filter((e: any) => !e.pinned);
              unpinned.forEach((e: any, idx: number) => { e.display_order = idx; });
            } catch {}
          } else {
            // Update local manual order to reflect visible order
            journal.entries.forEach((e: any, idx: number) => { e.display_order = idx; });
          }
        }
        journalStore.stopCreatingEntry();
        isSubmitting.value = false;
      }
    } catch (e) {
      console.error('Submit error:', e);
      errors.value.content = isEditMode() ? 'Failed to update entry.' : 'Failed to create entry.';
      isSubmitting.value = false;
    }
  } else {
    isSubmitting.value = false;
  }
}

// Checkbox functions
function addCheckboxItem() {
  if (newCheckboxItem.value.trim()) {
    checkboxItems.value.push({ text: newCheckboxItem.value.trim(), checked: false });
    newCheckboxItem.value = '';
  }
}

function removeCheckboxItem(index: number) {
  checkboxItems.value.splice(index, 1);
}

function toggleCheckbox(index: number) {
  checkboxItems.value[index].checked = !checkboxItems.value[index].checked;
}
</script>
<template>
<form class="flex flex-col flex-1 h-full w-full gap-4 rounded-xl p-4 bg-white/60 dark:bg-zinc-900/60" @submit.prevent="beforeSubmit">
    <!-- Header with actions (edit mode) -->
    <div v-if="isEditMode()" class="flex items-center justify-between -mb-2">
      <h2 class="text-base font-semibold">{{ formTitle }}</h2>
      <div class="flex items-center gap-1">
        <!-- Pin toggle -->
        <button type="button" class="h-8 w-8 inline-flex items-center justify-center rounded hover:bg-zinc-200/60 dark:hover:bg-zinc-800/60" :title="isPinned ? 'Unpin' : 'Pin'" @click="togglePinInForm">
          <Pin class="h-4 w-4" :class="isPinned ? 'text-amber-500' : 'text-zinc-500'" />
        </button>
        <!-- Link and Delete -->
        <LinkEntryButton v-if="props.entryToEdit" :entry-id="String(props.entryToEdit.id)" />
        <DeleteEntryButton v-if="props.entryToEdit" :entry-id="String(props.entryToEdit.id)" :journal-id="selectedJournalId" @deleted="emit('cancel')" />
      </div>
    </div>
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
    
    <!-- Card Type Selection -->
    <div class="flex gap-2 mb-4">
      <button
        type="button"
        @click="cardType = 'text'"
        :class="[
          'px-4 py-2 rounded transition-colors',
          cardType === 'text' 
            ? 'bg-blue-600 text-white' 
            : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
        ]"
      >
        Text Entry
      </button>
      <button
        type="button"
        @click="cardType = 'checkbox'"
        :class="[
          'px-4 py-2 rounded transition-colors',
          cardType === 'checkbox' 
            ? 'bg-blue-600 text-white' 
            : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
        ]"
      >
        Checklist
      </button>
      <button
        type="button"
        @click="cardType = 'spreadsheet'"
        :class="[
          'px-4 py-2 rounded transition-colors',
          cardType === 'spreadsheet' 
            ? 'bg-blue-600 text-white' 
            : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
        ]"
      >
        Spreadsheet
      </button>
    </div>

    <!-- Entry Title -->
    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">Entry Title (optional)</label>
      <input
        type="text"
        v-model="entryTitle"
        placeholder="Give your entry a title..."
        class="w-full px-2 py-1 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
      />
    </div>

    <!-- Pin after save (create mode) -->
    <div v-if="!isEditMode()" class="flex items-center justify-end -mt-2 mb-2">
      <label class="inline-flex items-center gap-2 text-sm cursor-pointer select-none">
        <input type="checkbox" v-model="pinAfterSave" class="accent-amber-500" />
        <span class="inline-flex items-center gap-1">
          <Pin class="h-4 w-4" :class="pinAfterSave ? 'text-amber-500' : 'text-zinc-500'" />
          Pin after save
        </span>
      </label>
    </div>


    <!-- Tags Selector (applies to the selected journal) -->
    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">Tags</label>
      <input
        type="text"
        v-model="tagFilter"
        @keyup.enter.prevent="canAddTag && addNewTag()"
        placeholder="Filter or add tag..."
        class="w-full mb-2 px-2 py-1 border rounded"
      />
      <div v-if="canAddTag" class="flex items-center justify-between text-sm mb-2">
        <span class="text-muted-foreground">New tag:</span>
        <button type="button" class="px-2 py-1 border rounded cursor-pointer dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700" @click="addNewTag">
          Add "{{ tagFilter.trim() }}"
        </button>
      </div>
      <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto p-2 border rounded">
        <label
          v-for="name in (journalStore.allTags || []).filter(t => !tagFilter || t.toLowerCase().includes(tagFilter.toLowerCase()))"
          :key="name"
          class="inline-flex items-center gap-2 text-xs bg-zinc-100 dark:bg-zinc-800 rounded-full px-2 py-1"
        >
          <input type="checkbox" :value="name" v-model="selectedTags" />
          <span>{{ name }}</span>
        </label>
      </div>
    </div>
    
    <!-- Text Entry Content -->
<div v-if="cardType === 'spreadsheet'" ref="spreadsheetContainer" class="w-full border rounded overflow-auto max-h-[400px] dark:border-zinc-700"></div>
    <div v-if="cardType === 'text'">
      <textarea
        class="block w-full border rounded px-2 py-2 min-h-[240px]"
        v-model="entryContent"
        :placeholder="creatingNewJournal ? 'Write your first journal entry (optional)...' : 'Write your journal entry...'"
      ></textarea>
         <!-- Mood Selector -->
    <div class="mt-4">
      <label class="block text-sm font-medium mb-2">How are you feeling?</label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="mood in moods"
          :key="mood.value"
          type="button"
          @click="selectedMood = selectedMood === mood.value ? '' : mood.value"
          :class="[
            'px-3 py-2 rounded-lg border-2 transition-all transform hover:scale-105',
            selectedMood === mood.value 
              ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30' 
              : 'border-gray-300 dark:border-gray-600 hover:border-gray-400'
          ]"
          :title="mood.label"
        >
          <span class="text-2xl">{{ mood.emoji }}</span>
        </button>
      </div>
      <div v-if="selectedMood" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
        Mood: {{ moods.find(m => m.value === selectedMood)?.label }}
      </div>
    </div>
      <div class="mt-6 bg-neutral-900/80 rounded-lg shadow p-3">
        <label class="block font-semibold mb-1 text-sm text-gray-400 dark:text-gray-300">Live Markdown Preview:</label>
        <div class="prose prose-neutral dark:prose-invert max-w-none" v-html="renderedMarkdown"></div>
      </div>
    </div>
    
    <!-- Checkbox Entry Content -->
    <div v-else-if="cardType === 'checkbox'">
      <div class="mb-4">
        <div class="flex gap-2">
          <input
            v-model="newCheckboxItem"
            @keyup.enter="addCheckboxItem"
            type="text"
            placeholder="Add a checklist item..."
            class="flex-1 border rounded px-2 py-1"
          />
          <button
            type="button"
            @click="addCheckboxItem"
            class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition-colors"
          >
            <Plus class="w-4 h-4" />
          </button>
        </div>
      </div>
      
      <div class="space-y-2 max-h-[300px] overflow-y-auto">
        <div
          v-for="(item, index) in checkboxItems"
          :key="index"
          class="flex items-center gap-2 p-2 bg-gray-100 dark:bg-gray-800 rounded"
        >
          <button
            type="button"
            @click="toggleCheckbox(index)"
            class="flex-shrink-0 w-5 h-5 border-2 rounded flex items-center justify-center transition-colors"
            :class="item.checked 
              ? 'bg-blue-600 border-blue-600' 
              : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600'"
          >
            <Check v-if="item.checked" class="w-3 h-3 text-white" />
          </button>
          <span 
            :class="{ 'line-through text-gray-500': item.checked }"
            class="flex-1"
          >
            {{ item.text }}
          </span>
          <button
            type="button"
            @click="removeCheckboxItem(index)"
            class="text-red-500 hover:text-red-700 transition-colors"
          >
            <X class="w-4 h-4" />
          </button>
        </div>
        <div v-if="checkboxItems.length === 0" class="text-gray-500 text-center py-4">
          No checklist items yet. Add one above!
        </div>
      </div>
    </div>
    
    <div v-if="errors.content" class="text-red-500 text-sm mt-1">{{ errors.content }}</div>

    <div class="flex gap-2 mt-4 justify-end">
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded cursor-pointer hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" :disabled="isSubmitting">{{ isSubmitting ? 'Saving...' : buttonLabel }}</button>
      <button type="button" @click="cancel" class="px-4 py-2 rounded border border-gray-400 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">{{ discardLabel }}</button>
    </div>
  </form>
</template>

