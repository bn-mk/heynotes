<script setup lang="ts">
import { ref, nextTick, computed, reactive } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/card/Card.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Check, Square, CheckSquare, Trash2, Pin, X } from 'lucide-vue-next';
import { onMounted, onUnmounted } from 'vue';
import { JournalListType } from '@/types';
import { useJournalStore } from '@/stores/journals';
import CreateEntryForm from '@/components/CreateEntryForm.vue';
import Spreadsheet from '@/components/Spreadsheet.vue';
import DeleteEntryButton from '@/components/DeleteEntryButton.vue';
import LinkEntryButton from '@/components/LinkEntryButton.vue';
import AudioWaveform from '@/components/AudioWaveform.vue';

const props = defineProps<{
  journals: JournalListType[],
}>();

const journalStore = useJournalStore();
// Local audio element refs per entry id (do not mutate entry objects)
const audioElMap = reactive<Record<string, { value: HTMLAudioElement | null }>>({});
function setAudioEl(id: string, el: HTMLAudioElement | null) {
  if (!audioElMap[id]) (audioElMap as any)[id] = { value: null };
  audioElMap[id].value = el;
}
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
    // Instantly update Pinia/journal tags for UI
    if (journalStore.selectedJournalId) {
      journalStore.addTagToJournal(journalStore.selectedJournalId, created);
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

function onCardClick(entry: any, e: MouseEvent) {
  // If the click originated from the action buttons area, ignore
  const target = e.target as HTMLElement | null;
  if (target && target.closest('.card-actions')) return;
  if (suppressClick.value) {
    // Swiped; don’t open editor
    return;
  }
  handleEditEntry(entry);
}

const sortedEntries = computed(() => {
  const entries = journalStore.selectedJournal?.entries || [];
  const toTs = (e: any) => new Date(e.created_at || 0).getTime();
  const pinned = entries
    .filter((e: any) => !!e.pinned)
    .slice()
    .sort((a: any, b: any) => toTs(b) - toTs(a));

  const unpinned = entries.filter((e: any) => !e.pinned);
  let unpinnedSorted: any[];
  if (unpinned.some((e: any) => e.display_order !== undefined && e.display_order !== null)) {
    unpinnedSorted = unpinned
      .slice()
      .sort((a: any, b: any) => {
        const ao = (a.display_order ?? Number.MAX_SAFE_INTEGER) as number;
        const bo = (b.display_order ?? Number.MAX_SAFE_INTEGER) as number;
        return ao - bo || (toTs(b) - toTs(a));
      });
  } else {
    unpinnedSorted = unpinned.slice().sort((a: any, b: any) => toTs(b) - toTs(a));
  }

  return [...pinned, ...unpinnedSorted];
});

const pinnedEntries = computed(() => {
  const entries = journalStore.selectedJournal?.entries || [];
  return entries
    .filter((e: any) => !!e.pinned)
    .slice()
    .sort((a: any, b: any) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime());
});

const unpinnedEntries = computed(() => {
  const entries = journalStore.selectedJournal?.entries || [];
  const unpinned = entries.filter((e: any) => !e.pinned);
  if (unpinned.some((e: any) => e.display_order !== undefined && e.display_order !== null)) {
    return unpinned
      .slice()
      .sort((a: any, b: any) => {
        const ao = (a.display_order ?? Number.MAX_SAFE_INTEGER) as number;
        const bo = (b.display_order ?? Number.MAX_SAFE_INTEGER) as number;
        return ao - bo || (new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime());
      });
  }
  return unpinned
    .slice()
    .sort((a: any, b: any) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime());
});

const isDragging = computed(() => !!draggedEntry.value);

function handlePinnedZoneDragOver(e: DragEvent) {
  if (e.preventDefault) e.preventDefault();
}
async function handlePinnedZoneDrop(e: DragEvent) {
  if (e.preventDefault) e.preventDefault();
  if (!draggedEntry.value) return;
  if (!draggedEntry.value.pinned) {
    draggedEntry.value.pinned = true;
  }
  const journal = journalStore.journals.find(j => j.id === journalStore.selectedJournalId);
  if (journal && journal.entries) {
    const unpinned = journal.entries.filter((e: any) => !e.pinned);
    unpinned.forEach((e: any, idx: number) => { e.display_order = idx; });
  }
  await saveEntryOrder();
}

function handleUnpinnedZoneDragOver(e: DragEvent) {
  if (e.preventDefault) e.preventDefault();
}
async function handleUnpinnedZoneDrop(e: DragEvent) {
  if (e.preventDefault) e.preventDefault();
  if (!draggedEntry.value) return;
  if (draggedEntry.value.pinned) {
    draggedEntry.value.pinned = false;
  }
  const journal = journalStore.journals.find(j => j.id === journalStore.selectedJournalId);
  if (journal && journal.entries) {
    const unpinned = journal.entries.filter((e: any) => !e.pinned);
    unpinned.forEach((e: any, idx: number) => { e.display_order = idx; });
  }
  await saveEntryOrder();
}

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

// Toggle pin status via API and update local state
async function togglePin(entry: any) {
  const getCookie = (name: string) => {
    const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
    return match ? decodeURIComponent(match[3]) : null;
  };
  const xsrf = getCookie('XSRF-TOKEN') ?? '';
  const journalId = journalStore.selectedJournalId;
  if (!journalId) return;
  try {
    const desired = !entry.pinned;
    await fetch(`/api/journals/${journalId}/entries/${entry.id}/pin`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': xsrf,
      },
      body: JSON.stringify({ pinned: desired }),
    });
    // Update local
    entry.pinned = desired;
    const journal = journalStore.journals.find(j => j.id === journalId);
    if (journal) {
      // Ensure unpinned orders are compact
      const unpinned = (journal.entries || []).filter((e: any) => !e.pinned);
      unpinned.forEach((e: any, idx: number) => { e.display_order = idx; });
    }
    await saveEntryOrder();
  } catch (e) {
    console.error('Failed to toggle pin', e);
  }
}

// Drag and Drop handlers
function handleDragStart(entry: any, event: DragEvent) {
  draggedEntry.value = entry;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move';
    // Ensure some data is set so drops are consistently accepted
    try { event.dataTransfer.setData('text/plain', String(entry.id)); } catch {}
  }
  // Add dragging class to the element being dragged (wrapper)
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
    const card = (event.currentTarget as HTMLElement).closest('.masonry-card');
    if (card) card.classList.add('border-4', 'border-blue-400');
  }
}

function handleDragLeave(event: DragEvent) {
  const card = (event.currentTarget as HTMLElement).closest('.masonry-card');
  if (card) card.classList.remove('border-4', 'border-blue-400');
}

async function handleDrop(targetEntry: any, event: DragEvent) {
  if (event.stopPropagation) {
    event.stopPropagation(); // stops some browsers from redirecting.
  }

  const el = event.currentTarget as HTMLElement;
  el.classList.remove('border-4', 'border-blue-400');
  const cardEl = el.closest('.masonry-card');
  if (cardEl) {
    cardEl.classList.remove('border-4', 'border-blue-400');
  }
  
  if (draggedEntry.value && targetEntry.id !== draggedEntry.value.id) {
    const entries = sortedEntries.value;
    const draggedIndex = entries.findIndex(e => e.id === draggedEntry.value.id);
    const targetIndex = entries.findIndex(e => e.id === targetEntry.id);
    
    if (draggedIndex !== -1 && targetIndex !== -1) {
      // If crossing pin boundary, adopt target's pinned state
      if (!!draggedEntry.value.pinned !== !!targetEntry.pinned) {
        draggedEntry.value.pinned = !!targetEntry.pinned;
      }

      // Reorder in the visible array
      const [removed] = entries.splice(draggedIndex, 1);
      entries.splice(targetIndex, 0, removed);
      
      // Recompute display_order only for unpinned entries in their current visible order
      const unpinned = entries.filter((e: any) => !e.pinned);
      unpinned.forEach((e: any, idx: number) => { e.display_order = idx; });
      
      // Update the journal's entries in the store
      const journal = journalStore.journals.find(j => j.id === journalStore.selectedJournalId);
      if (journal) {
        journal.entries = entries;
      }
      
      // Persist the new order (with pinned flags) to the backend
      await saveEntryOrder();
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

async function saveEntryOrder() {
  const getCookie = (name: string) => {
    const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
    return match ? decodeURIComponent(match[3]) : null;
  };
  const xsrf = getCookie('XSRF-TOKEN') ?? '';
  
  try {
    // Build payload with pinned flags and unpinned display_order
    const visible = sortedEntries.value;
    const orderData: any[] = [];
    let unpinnedIndex = 0;
    for (const e of visible) {
      if (e.pinned) {
        orderData.push({ id: e.id, pinned: true });
      } else {
        orderData.push({ id: e.id, display_order: unpinnedIndex, pinned: false });
        unpinnedIndex++;
      }
    }

    await fetch(`/api/journals/${journalStore.selectedJournalId}/entries/reorder`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': xsrf,
      },
      body: JSON.stringify({ entries: orderData }),
    });
  } catch (error) {
    console.error('Error saving entry order:', error);
  }
}

// Global header action listeners
function handleStartCreatingEntryEvent() {
  journalStore.startCreatingEntry();
}
function handleOpenTagsEvent() {
  openTagsDialog();
}

onMounted(() => {
  window.addEventListener('start-creating-entry', handleStartCreatingEntryEvent);
  window.addEventListener('open-tags-dialog', handleOpenTagsEvent);

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

onUnmounted(() => {
  window.removeEventListener('start-creating-entry', handleStartCreatingEntryEvent);
  window.removeEventListener('open-tags-dialog', handleOpenTagsEvent);
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
                  <input v-model="editingJournalTitle" @keyup.enter="handleSaveTitle" @keyup.esc="handleCancelEdit" class="text-2xl font-bold bg-transparent border-b border-transparent focus:border-zinc-400 dark:focus:border-zinc-600 outline-none" />
                  <Button variant="ghost" size="icon" class="ml-2 size-10 cursor-pointer" title="Save" @click="handleSaveTitle">
                    <Check class="size-5 text-zinc-800 dark:text-zinc-100" />
                  </Button>
                  <Button variant="ghost" size="icon" class="ml-1 size-10 cursor-pointer" title="Cancel" @click="handleCancelEdit">
                    <X class="size-5 text-zinc-800 dark:text-zinc-100" />
                  </Button>
                </div>
              </template>
            </div>
            <div class="flex items-center gap-2"></div>
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
                <Button class="cursor-pointer" @click="async () => { if (journalStore.selectedJournal) { console.log('[Dashboard] Save tags for', journalStore.selectedJournal.id, '->', tagSelection); await journalStore.updateJournalTags(journalStore.selectedJournal.id, tagSelection); tagsDialogOpen = false; } }">Save</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

<div v-if="journalStore.selectedJournal.entries && journalStore.selectedJournal.entries.length > 0">
          <!-- Global Pinned Drop Zone (visible while dragging) -->
          <div
            v-show="isDragging"
            class="mb-3 rounded-md border-2 border-dashed border-neutral-300 dark:border-neutral-700 bg-neutral-50/40 dark:bg-neutral-800/40 text-center text-xs text-neutral-500 p-2"
            @dragover="handlePinnedZoneDragOver"
            @drop="handlePinnedZoneDrop"
          >
            Drop here to pin
          </div>

          <!-- Pinned header + grid -->
          <template v-if="pinnedEntries.length > 0">
            <div class="mb-2 mt-1 flex items-center gap-2 text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
              <span class="font-medium">Pinned</span>
              <div class="flex-1 border-t border-dashed border-neutral-300 dark:border-neutral-700"></div>
            </div>
            <div class="columns-1 sm:columns-1 md:columns-2 lg:columns-3 xl:columns-4 gap-x-6">
              <Card
                v-for="(entry, index) in pinnedEntries"
                :key="entry.id"
                class="border-2 transition-all cursor-pointer group break-inside-avoid mb-6 inline-block w-full will-change-transform hover:-translate-y-0.5 hover:shadow-md masonry-card"
                :style="[{ borderColor: getBorderColor(index, pinnedEntries.length) }, cardSwipeStyle(entry)]"
                @click="onCardClick(entry, $event)"
                @touchstart="onTouchStart(entry, $event)"
                @touchmove="onTouchMove($event)"
                @touchend="onTouchEnd(entry, $event)"
              >
                <div class="p-4 flex flex-col relative" :class="{ 'children-pe-none noselect': isDragging }" draggable="true" @dragstart="handleDragStart(entry, $event)" @dragover="handleDragOver($event)" @dragenter="handleDragEnter(entry, $event)" @dragleave="handleDragLeave($event)" @drop="handleDrop(entry, $event)">
                  <!-- Pin Icon -->
                  <div class="absolute top-2 left-2 z-10" @mousedown.stop @touchstart.stop>
                    <Pin class="w-4 h-4 text-amber-500" @click.stop="togglePin(entry)" />
                  </div>
                  <!-- Mood Emoji Display -->
                  <div v-if="entry.mood" class="absolute top-2 left-8" :title="entry.mood">
                    <span class="text-lg">{{ getMoodEmoji(entry.mood) }}</span>
                  </div>

<div class="card-actions absolute top-2 right-2 z-10 opacity-0 transition-opacity pointer-events-none group-hover:opacity-100 group-focus-within:opacity-100 group-hover:pointer-events-auto group-focus-within:pointer-events-auto flex items-center gap-1" @click.stop @mousedown.stop @touchstart.stop>
                    <LinkEntryButton :entry-id="String(entry.id)" />
                    <DeleteEntryButton :entry-id="String(entry.id)" @deleted="showNotification('Entry moved to trash')" />
                  </div>

                  <!-- Content -->
                  <div v-if="!entry.card_type || entry.card_type === 'text'" class="text-sm text-white-800 whitespace-normal mb-2 pr-20 pl-8">
                    <h3 v-if="entry.title" class="font-semibold text-lg mb-2 text-gray-900 dark:text-gray-100">{{ entry.title }}</h3>
                    <div class="prose prose-neutral dark:prose-invert max-w-none leading-tight prose-headings:my-0 prose-headings:pb-4 prose-headings:leading-tight prose-p:my-0 prose-li:my-0 prose-ul:my-0 prose-ul:pb-4 prose-ol:my-0" v-html="renderMarkdown(entry.content)"></div>
                  </div>
                  <div v-else-if="entry.card_type === 'checkbox'" class="pr-20 pl-8">
                    <h3 v-if="entry.title" class="font-semibold text-lg mb-2 text-gray-900 dark:text-gray-100">{{ entry.title }}</h3>
                    <div class="space-y-0">
                      <div v-for="(item, idx) in (entry.checkbox_items || [])" :key="idx" class="flex items-center gap-0.5 text-sm leading-tight">
                        <CheckSquare v-if="item.checked" class="w-4 h-4 text-green-600 flex-shrink-0" />
                        <Square v-else class="w-4 h-4 text-gray-400 flex-shrink-0" />
                        <span :class="{ 'line-through text-gray-500': item.checked }">{{ item.text }}</span>
                      </div>
                      <div v-if="!entry.checkbox_items || entry.checkbox_items.length === 0" class="text-gray-500 text-sm">Empty checklist</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                      <div class="text-xs text-gray-500">{{ getChecklistProgress(entry.checkbox_items) }}</div>
                    </div>
                  </div>
                  <div v-else-if="entry.card_type === 'spreadsheet'" class="pr-20 pl-8">
                    <h3 v-if="entry.title" class="font-semibold text-lg mb-2 text-gray-900 dark:text-gray-100">{{ entry.title }}</h3>
                    <Spreadsheet :data="entry.content" />
                  </div>
                  <div v-else-if="entry.card_type === 'audio'" class="pr-20 pl-8">
                    <h3 v-if="entry.title" class="font-semibold text-lg mb-2 text-gray-900 dark:text-gray-100">{{ entry.title }}</h3>
                    <AudioWaveform :src="entry.content" :audio-el="audioElMap[entry.id]" :height="48" />
                    <audio :src="entry.content" controls class="w-full mt-2" :ref="(el) => setAudioEl(String(entry.id), el as HTMLAudioElement)"></audio>
                  </div>
                  <div class="mt-auto text-xs text-gray-400">{{ new Date(entry.created_at).toLocaleString() }}</div>
                </div>
              </Card>
            </div>
          </template>

          <!-- Unpinned header + drop zone + grid -->
          <div class="mt-4 mb-2 flex items-center gap-2 text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
            <span class="font-medium">All entries</span>
            <div class="flex-1 border-t border-dashed border-neutral-300 dark:border-neutral-700"></div>
          </div>
          <div
            v-show="isDragging"
            class="mb-3 rounded-md border-2 border-dashed border-neutral-300 dark:border-neutral-700 bg-neutral-50/40 dark:bg-neutral-800/40 text-center text-xs text-neutral-500 p-2"
            @dragover="handleUnpinnedZoneDragOver"
            @drop="handleUnpinnedZoneDrop"
          >
            Drop here to unpin
          </div>

          <div class="columns-1 sm:columns-1 md:columns-2 lg:columns-3 xl:columns-4 gap-x-6">
            <Card
              v-for="(entry, index) in unpinnedEntries"
              :key="entry.id"
              class="border-2 transition-all cursor-pointer group break-inside-avoid mb-6 inline-block w-full will-change-transform hover:-translate-y-0.5 hover:shadow-md masonry-card"
              :style="[{ borderColor: getBorderColor(index, unpinnedEntries.length) }, cardSwipeStyle(entry)]"
              @click="onCardClick(entry, $event)"
              @touchstart="onTouchStart(entry, $event)"
              @touchmove="onTouchMove($event)"
              @touchend="onTouchEnd(entry, $event)"
            >
              <div class="p-4 flex flex-col relative" :class="{ 'children-pe-none noselect': isDragging }" draggable="true" @dragstart="handleDragStart(entry, $event)" @dragover="handleDragOver($event)" @dragenter="handleDragEnter(entry, $event)" @dragleave="handleDragLeave($event)" @drop="handleDrop(entry, $event)">
                <!-- Pin Icon (hover only for unpinned) -->
                <div class="absolute top-2 left-2 z-10 opacity-0 transition-opacity pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto" @mousedown.stop @touchstart.stop>
                  <Pin class="w-4 h-4 text-gray-400 hover:text-amber-500" @click.stop="togglePin(entry)" />
                </div>
                <!-- Mood Emoji Display -->
                <div v-if="entry.mood" class="absolute top-2 left-2" :title="entry.mood">
                  <span class="text-lg">{{ getMoodEmoji(entry.mood) }}</span>
                </div>

<div class="card-actions absolute top-2 right-2 z-10 opacity-0 transition-opacity pointer-events-none group-hover:opacity-100 group-focus-within:opacity-100 group-hover:pointer-events-auto group-focus-within:pointer-events-auto flex items-center gap-1" @click.stop @mousedown.stop @touchstart.stop>
                  <LinkEntryButton :entry-id="String(entry.id)" />
                  <DeleteEntryButton :entry-id="String(entry.id)" @deleted="showNotification('Entry moved to trash')" />
                </div>

                <!-- Content -->
                <div v-if="!entry.card_type || entry.card_type === 'text'" class="text-sm text-white-800 whitespace-normal mb-2 pr-20 pl-8">
                  <h3 v-if="entry.title" class="font-semibold text-lg mb-2 text-gray-900 dark:text-gray-100">{{ entry.title }}</h3>
                  <div class="prose prose-neutral dark:prose-invert max-w-none leading-tight prose-headings:my-0 prose-headings:pb-4 prose-headings:leading-tight prose-p:my-0 prose-li:my-0 prose-ul:my-0 prose-ul:pb-4 prose-ol:my-0" v-html="renderMarkdown(entry.content)"></div>
                </div>
                <div v-else-if="entry.card_type === 'checkbox'" class="pr-20 pl-8">
                  <h3 v-if="entry.title" class="font-semibold text-lg mb-2 text-gray-900 dark:text-gray-100">{{ entry.title }}</h3>
                  <div class="space-y-0">
                    <div v-for="(item, idx) in (entry.checkbox_items || [])" :key="idx" class="flex items-center gap-0.5 text-sm leading-tight">
                      <CheckSquare v-if="item.checked" class="w-4 h-4 text-green-600 flex-shrink-0" />
                      <Square v-else class="w-4 h-4 text-gray-400 flex-shrink-0" />
                      <span :class="{ 'line-through text-gray-500': item.checked }">{{ item.text }}</span>
                    </div>
                    <div v-if="!entry.checkbox_items || entry.checkbox_items.length === 0" class="text-gray-500 text-sm">Empty checklist</div>
                  </div>
                  <div class="mt-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                    <div class="text-xs text-gray-500">{{ getChecklistProgress(entry.checkbox_items) }}</div>
                  </div>
                </div>
                <div v-else-if="entry.card_type === 'spreadsheet'" class="pr-20 pl-8">
                  <h3 v-if="entry.title" class="font-semibold text-lg mb-2 text-gray-900 dark:text-gray-100">{{ entry.title }}</h3>
                  <Spreadsheet :data="entry.content" />
                </div>
                <div v-else-if="entry.card_type === 'audio'" class="pr-20 pl-8">
                  <h3 v-if="entry.title" class="font-semibold text-lg mb-2 text-gray-900 dark:text-gray-100">{{ entry.title }}</h3>
                  <AudioWaveform :src="entry.content" :audio-el="audioElMap[entry.id]" :height="48" />
                  <audio :src="entry.content" controls class="w-full mt-2" :ref="(el) => setAudioEl(String(entry.id), el as HTMLAudioElement)"></audio>
                </div>
                <div class="mt-auto text-xs text-gray-400">{{ new Date(entry.created_at).toLocaleString() }}</div>
              </div>
            </Card>
          </div>
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

.masonry-card {
  animation: fadeSlideIn 240ms ease-out both;
}
@keyframes fadeSlideIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* While dragging, ensure child elements don't intercept dragover/drop so the card wrapper receives it */
.children-pe-none * {
  pointer-events: none !important;
}

/* Prevent text selection during drag to avoid interference */
.noselect {
  user-select: none !important;
  -webkit-user-select: none !important;
}
</style>
