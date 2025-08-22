<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useJournalStore } from '@/stores/journals';
import MarkdownIt from 'markdown-it';
import { Plus, X, Check } from 'lucide-vue-next';

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
const newJournalTitle = ref('');
const errors = ref<{ content?: string, title?: string }>({});
const cardType = ref<'text' | 'checkbox'>('text');
const checkboxItems = ref<Array<{ text: string; checked: boolean }>>([]);
const newCheckboxItem = ref('');
const selectedMood = ref<string>('');

// Mood options with emojis
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
    selectedMood.value = props.entryToEdit.mood || '';
    if (props.entryToEdit.card_type === 'checkbox' && props.entryToEdit.checkbox_items) {
      checkboxItems.value = [...props.entryToEdit.checkbox_items];
    } else {
      entryContent.value = props.entryToEdit.content || '';
    }
  } else if (props.createNewJournal) {
    creatingNewJournal.value = true;
    selectedJournalId.value = 'new';
  }
}
watch(() => props.entryToEdit, defaultPrefill);
watch(() => props.createNewJournal, (newVal) => {
  if (newVal) {
    creatingNewJournal.value = true;
    selectedJournalId.value = 'new';
  }
});

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
  } else {
    return checkboxItems.value.length > 0;
  }
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
  errors.value = {};
  let journalId = selectedJournalId.value;

  if (!isEditMode() && creatingNewJournal.value) {
    if (!newJournalTitle.value.trim()) {
      errors.value.title = 'Journal title is required.';
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
        body: JSON.stringify({ title: newJournalTitle.value, tags: '' })
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
        return;
      }
    } catch (e) {
      errors.value.title = 'Could not create journal.';
      return;
    }
  }
  
  // Only require entry content if not creating a new journal or if content exists
  if (!creatingNewJournal.value && !hasContent()) {
    errors.value.content = cardType.value === 'checkbox' ? 'At least one checklist item is required.' : 'Entry content is required.';
    return;
  }
  
  // If we have entry content, create the entry
  if (hasContent()) {
    try {
      const entryPayload: any = {
        card_type: cardType.value,
        journal_id: journalId,
        mood: selectedMood.value || null
      };
      
      if (cardType.value === 'text') {
        entryPayload.content = entryContent.value;
      } else if (cardType.value === 'checkbox') {
        entryPayload.checkbox_items = checkboxItems.value;
      }
      
      if (isEditMode()) {
        // EDIT: PUT
        const response = await fetch(`/api/journals/${journalId}/entries/${props.entryToEdit.id}`, {
          method: 'PUT', 
          credentials: 'include', 
          headers: authHeaders(),
          body: JSON.stringify(entryPayload)
        });
        if (!response.ok) throw new Error('Entry update failed');
        const updatedEntry = await response.json();
        emit('success', updatedEntry);
      } else {
        // CREATE: POST
        const createPayload: any = {
          card_type: cardType.value,
          mood: selectedMood.value || null
        };
        
        if (cardType.value === 'text') {
          createPayload.content = entryContent.value;
        } else if (cardType.value === 'checkbox') {
          createPayload.checkbox_items = checkboxItems.value;
        }
        
        const response = await fetch(`/api/journals/${journalId}/entries`, {
          method: 'POST', 
          credentials: 'include', 
          headers: authHeaders(),
          body: JSON.stringify(createPayload)
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
    </div>
    
    <!-- Mood Selector -->
    <div class="mb-4">
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
    
    <!-- Text Entry Content -->
    <div v-if="cardType === 'text'">
      <textarea
        class="block w-full border rounded px-2 py-2 min-h-[240px]"
        v-model="entryContent"
        :placeholder="creatingNewJournal ? 'Write your first journal entry (optional)...' : 'Write your journal entry...'"
      ></textarea>
      
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
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">{{ buttonLabel }}</button>
      <button type="button" @click="cancel" class="px-4 py-2 rounded border border-gray-400">{{ discardLabel }}</button>
    </div>
  </form>
</template>

