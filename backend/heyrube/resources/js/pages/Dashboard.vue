<script setup lang="ts">
import { ref, nextTick, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/card/Card.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { PlusSquare, Tag, Check, Square, CheckSquare, Trash2 } from 'lucide-vue-next';
import { onMounted } from 'vue';
import { JournalListType } from '@/types';
import { useJournalStore } from '@/stores/journals';
import CreateEntryForm from '@/components/CreateEntryForm.vue';
import DeleteEntryButton from '@/components/DeleteEntryButton.vue';

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
const tagsDialogOpen = ref(false);
const tagSelection = ref<string[]>([]);
const tagFilter = ref('');

const canAddTag = computed(() => {
  const name = tagFilter.value.trim();
  if (!name) return false;
  const lower = name.toLowerCase();
  return !journalStore.allTags.some(t => t.toLowerCase() === lower);
});

async function addNewTag() {
  const name = tagFilter.value.trim();
  if (!name) return;
  const created = await journalStore.createTag(name);
  if (created) {
    if (!tagSelection.value.includes(created)) {
      tagSelection.value.push(created);
    }
    // keep the filter so user sees it in the list; alternatively clear it
  }
}

function openTagsDialog() {
  tagsDialogOpen.value = true;
  tagSelection.value = [...(journalStore.selectedJournal?.tags || [])];
}
const draggedEntry = ref(null);
const draggedOverEntry = ref(null);

// Mobile swipe-to-delete state
const touchStartX = ref(0);
const touchStartY = ref(0);
const touchDeltaX = ref(0);
const swipingId = ref<string | null>(null);
const isSwiping = ref(false);
const suppressClick = ref(false);
const vibratedForThisSwipe = ref(false);

// Undo snackbar state
const lastDeletedEntryId = ref<string | null>(null);
let undoTimeout: number | null = null;
const isUndoVisible = ref(false);

function onTouchStart(entry: any, e: TouchEvent) {
  swipingId.value = String(entry.id);
  const t = e.touches[0];
  touchStartX.value = t.clientX;
  touchStartY.value = t.clientY;
  touchDeltaX.value = 0;
  isSwiping.value = false;
  vibratedForThisSwipe.value = false;
}

function onTouchMove(e: TouchEvent) {
  if (!swipingId.value) return;
  const t = e.touches[0];
  const dx = t.clientX - touchStartX.value;
  const dy = t.clientY - touchStartY.value;
  // Only consider horizontal swipes and provide feedback
  if (Math.abs(dx) > Math.abs(dy)) {
    e.preventDefault();
    isSwiping.value = true;
    touchDeltaX.value = dx;
    if (dx < -80 && !vibratedForThisSwipe.value && 'vibrate' in navigator) {
      try { navigator.vibrate(20); } catch {}
      vibratedForThisSwipe.value = true;
    }
  }
}

async function onTouchEnd(entry: any, _e: TouchEvent) {
  if (!swipingId.value) return;
  const dx = touchDeltaX.value;
  swipingId.value = null;
  isSwiping.value = false;
  touchDeltaX.value = 0;
  if (dx < -80) {
    // Significant left swipe: delete with undo snackbar
    suppressClick.value = true;
    await deleteEntryWithUndo(entry);
    setTimeout(() => (suppressClick.value = false), 0);
  }
}

function cardSwipeStyle(entry: any) {
  if (isSwiping.value && swipingId.value === String(entry.id)) {
    const x = Math.min(0, touchDeltaX.value);
    return { transform: `translateX(${x}px)` };
    }
  return {};
}

async function deleteEntryWithUndo(entry: any) {
  const journalId = journalStore.selectedJournalId;
  if (!journalId) return;
  const success = await journalStore.deleteEntry(journalId, entry.id);
  if (success) {
    lastDeletedEntryId.value = String(entry.id);
    notificationMessage.value = 'Entry moved to trash';
    showNotificationFlag.value = true;
    isUndoVisible.value = true;
    if ('vibrate' in navigator) {
      try { navigator.vibrate(30); } catch {}
    }
    if (undoTimeout) window.clearTimeout(undoTimeout);
    undoTimeout = window.setTimeout(() => {
      isUndoVisible.value = false;
      showNotificationFlag.value = false;
      lastDeletedEntryId.value = null;
    }, 4000);
  }
}

async function undoDelete() {
  if (!lastDeletedEntryId.value) return;
  await journalStore.restoreEntry(lastDeletedEntryId.value);
  // cancel any pending hide
  if (undoTimeout) { window.clearTimeout(undoTimeout); undoTimeout = null; }
  notificationMessage.value = 'Entry restored';
  isUndoVisible.value = false;
  showNotificationFlag.value = true;
  lastDeletedEntryId.value = null;
  setTimeout(() => { showNotificationFlag.value = false; }, 2000);
}

function onCardClick(entry: any, _e: MouseEvent) {
  if (suppressClick.value) {
    // Swiped; don’t open editor
    return;
  }
  handleEditEntry(entry);
}

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
  const journalId = journalStore.selectedJournalId;
  if (journalId) {
    const success = await journalStore.deleteEntry(journalId, entry.id);
    if (success) {
      // Show a brief notification that the entry was moved to trash
      showNotification('Entry moved to trash');
    } else {
      alert('Failed to delete entry.');
    }
  }
  entry.openMenu = false;
}

const notificationMessage = ref('');
const showNotificationFlag = ref(false);

function showNotification(message: string) {
  notificationMessage.value = message;
  showNotificationFlag.value = true;
  setTimeout(() => {
    showNotificationFlag.value = false;
  }, 3000);
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

function getChecklistProgress(items: any[]): string {
  if (!items || items.length === 0) return '0/0 completed';
  const checked = items.filter(item => item.checked).length;
  return `${checked}/${items.length} completed`;
}

function getMoodEmoji(mood: string): string {
  const moodMap: { [key: string]: string } = {
    'happy': '😊',
    'sad': '😔',
    'tired': '😴',
    'angry': '😡',
    'anxious': '😰',
    'grateful': '🤗',
    'calm': '😌',
    'thoughtful': '🤔',
    'confident': '😎',
    'stressed': '😅',
    'loved': '🥰',
    'neutral': '😐',
  };
  return moodMap[mood] || '';
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
  // Preload tags for tag manager
  journalStore.fetchTags();
});
</script>

<template>
  <AppLayout>
    <div class="pattern-bg flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
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
              <template v-if="editingJournalId !== journalStore.selectedJournal.id">
                <h1 class="text-6xl font-['Tangerine']">
                  {{ journalStore.selectedJournal.title }}
                </h1>
                <div v-if="journalStore.selectedJournal?.tags?.length" class="flex flex-wrap items-center gap-2 ml-3">
                  <span v-for="t in journalStore.selectedJournal.tags" :key="t" class="px-2 py-0.5 rounded-full text-xs bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-200">{{ t }}</span>
                </div>
                <button @click="handleEditTitle" class="ml-2">
                  <!-- Pencil Icon -->
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
                    <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd" />
                  </svg>
                </button>
              </template>
              <template v-else>
                <div class="flex items-center">
                  <input v-model="editingJournalTitle" @keyup.enter="handleSaveTitle" @keyup.esc="handleCancelEdit" class="text-2xl font-bold" />
                  <button @click="handleSaveTitle" class="ml-2 px-2 py-1 bg-green-500 text-white rounded">Save</button>
                  <button @click="handleCancelEdit" class="ml-2 px-2 py-1 bg-red-500 text-white rounded">Cancel</button>
                </div>
              </template>
            </div>
            <div class="flex items-center gap-2">
              <Button 
                variant="outline"
                size="icon"
                class="cursor-pointer dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700"
                @click="openTagsDialog"
                title="Manage Tags"
              >
                <Tag class="h-4 w-4" />
                <span class="sr-only">Manage Tags</span>
              </Button>
              <Button 
                @click="journalStore.startCreatingEntry()"
                size="icon"
                variant="default"
                class="cursor-pointer dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700"
                title="New Entry"
              >
                <PlusSquare class="h-4 w-4" />
                <span class="sr-only">New Entry</span>
              </Button>
            </div>
          </div>

          <!-- Manage Tags Dialog -->
          <Dialog v-model:open="tagsDialogOpen">
            <DialogContent class="max-w-md">
              <DialogHeader>
                <DialogTitle>Manage Tags</DialogTitle>
                <DialogDescription>Select tags to apply to this journal.</DialogDescription>
              </DialogHeader>
              <div class="space-y-3">
                <input type="text" v-model="tagFilter" placeholder="Filter or add tag..." class="w-full px-2 py-1 border rounded" />
                <div v-if="canAddTag" class="flex items-center justify-between text-sm">
                  <span class="text-muted-foreground">New tag:</span>
                  <Button
                    variant="outline"
                    size="sm"
                    class="cursor-pointer dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700"
                    @click="addNewTag"
                  >
                    Add "{{ tagFilter.trim() }}"
                  </Button>
                </div>
                <div class="max-h-64 overflow-y-auto space-y-1">
                  <label v-for="name in journalStore.allTags.filter(t => t.toLowerCase().includes(tagFilter.toLowerCase()))" :key="name" class="flex items-center gap-2 text-sm">
                    <input type="checkbox" :value="name" v-model="tagSelection" />
                    <span>{{ name }}</span>
                  </label>
                </div>
              </div>
              <DialogFooter>
                <Button variant="outline" class="cursor-pointer" @click="tagsDialogOpen = false">Cancel</Button>
                <Button class="cursor-pointer" @click="async () => { if (journalStore.selectedJournal) { await journalStore.updateJournalTags(journalStore.selectedJournal.id, tagSelection); tagsDialogOpen = false; } }">Save</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

<div v-if="journalStore.selectedJournal.entries && journalStore.selectedJournal.entries.length > 0" class="grid gap-4 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4 items-start">
          <Card
            v-for="(entry, index) in sortedEntries" 
            :key="entry.id" 
            class="border-2 transition-all cursor-pointer group"
            :style="[{ borderColor: getBorderColor(index, sortedEntries.length) }, cardSwipeStyle(entry)]"
            draggable="true"
            @dragstart="handleDragStart(entry, $event)"
            @dragend="handleDragEnd($event)"
            @dragover="handleDragOver($event)"
            @dragenter="handleDragEnter(entry, $event)"
            @dragleave="handleDragLeave($event)"
            @drop="handleDrop(entry, $event)"
            @click="onCardClick(entry, $event)"
            @touchstart="onTouchStart(entry, $event)"
            @touchmove="onTouchMove($event)"
            @touchend="onTouchEnd(entry, $event)"
          >
          <div class="p-4 flex flex-col relative">
              <!-- Mood Emoji Display -->
              <div v-if="entry.mood" class="absolute top-2 left-2" :title="entry.mood">
                <span class="text-lg">{{ getMoodEmoji(entry.mood) }}</span>
              </div>
              
              <div class="absolute top-2 right-2 z-10 opacity-0 transition-opacity pointer-events-none group-hover:opacity-100 group-focus-within:opacity-100 group-hover:pointer-events-auto group-focus-within:pointer-events-auto">
                <DeleteEntryButton
                  :entry-id="String(entry.id)"
                  @deleted="showNotification('Entry moved to trash')"
                />
              </div>
              <!-- Text Card Content -->
              <div v-if="!entry.card_type || entry.card_type === 'text'" class="text-sm text-white-800 whitespace-normal mb-2 pr-8" :class="{ 'pl-8': entry.mood }">
<div class="prose prose-neutral dark:prose-invert max-w-none leading-tight prose-headings:my-0 prose-headings:pb-4 prose-headings:leading-tight prose-p:my-0 prose-li:my-0 prose-ul:my-0 prose-ul:pb-4 prose-ol:my-0" v-html="renderMarkdown(entry.content)"></div>
              </div>
              
              <!-- Checkbox Card Content -->
              <div v-else-if="entry.card_type === 'checkbox'" class="pr-8" :class="{ 'pl-8': entry.mood }">
                <div class="space-y-0">
                  <div 
                    v-for="(item, idx) in (entry.checkbox_items || [])"
                    :key="idx"
                    class="flex items-center gap-0.5 text-sm leading-tight"
                  >
                    <CheckSquare v-if="item.checked" class="w-4 h-4 text-green-600 flex-shrink-0" />
                    <Square v-else class="w-4 h-4 text-gray-400 flex-shrink-0" />
                    <span :class="{ 'line-through text-gray-500': item.checked }">
                      {{ item.text }}
                    </span>
                  </div>
                  <div v-if="!entry.checkbox_items || entry.checkbox_items.length === 0" class="text-gray-500 text-sm">
                    Empty checklist
                  </div>
                </div>
                <!-- Show progress -->
                <div class="mt-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                  <div class="text-xs text-gray-500">
                    {{ getChecklistProgress(entry.checkbox_items) }}
                  </div>
                </div>
              </div>
              <div class="mt-auto text-xs text-gray-400">{{ new Date(entry.created_at).toLocaleString() }}</div>
            </div>
          </Card>
          </div>
          <div v-else class="text-gray-500 text-center">No entries yet.</div>
        </div>
      </template>
    </div>
    <!-- Notification Toast -->
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-2 opacity-0"
    >
      <div
        v-if="showNotificationFlag"
        class="fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-3 z-50"
      >
        <Check v-if="!isUndoVisible || notificationMessage === 'Entry restored'" class="w-4 h-4 text-green-400" />
        <Trash2 v-else class="w-4 h-4 text-red-400" />
        <span>{{ notificationMessage }}</span>
        <button
          v-if="isUndoVisible"
          class="ml-2 inline-flex items-center rounded px-2 py-1 text-xs font-medium bg-white/10 hover:bg-white/20"
          @click="undoDelete"
        >
          Undo
        </button>
      </div>
    </Transition>
  </AppLayout>
</template>

<style scoped>
.pattern-bg {
  /* Lightweight geometric cross‑hatch + grid (light mode) */
  background-image:
    repeating-linear-gradient(45deg, rgba(0,0,0,0.04) 0 1px, transparent 1px 28px),
    repeating-linear-gradient(135deg, rgba(0,0,0,0.04) 0 1px, transparent 1px 28px),
    linear-gradient(to right, rgba(0,0,0,0.035) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(0,0,0,0.035) 1px, transparent 1px);
  background-size: auto, auto, 36px 36px, 36px 36px;
}
html.dark .pattern-bg {
  /* Lightweight geometric cross‑hatch + grid (dark mode) */
  background-image:
    repeating-linear-gradient(45deg, rgba(255,255,255,0.06) 0 1px, transparent 1px 28px),
    repeating-linear-gradient(135deg, rgba(255,255,255,0.06) 0 1px, transparent 1px 28px),
    linear-gradient(to right, rgba(255,255,255,0.05) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255,255,255,0.05) 1px, transparent 1px);
  background-size: auto, auto, 36px 36px, 36px 36px;
}
</style>
