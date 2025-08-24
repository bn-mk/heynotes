<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue';
import { Trash2, RotateCcw, XCircle, FileText, CheckSquare } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { useJournalStore } from '@/stores/journals';

const journalStore = useJournalStore();
const isOpen = ref(false);
const activeTab = ref<'journals' | 'entries'>('journals');

const totalTrashedItems = computed(() => {
  return journalStore.trashedJournals.length + journalStore.trashedEntries.length;
});

// Initial fetch to get the count
onMounted(() => {
  journalStore.fetchTrashed();
});

// Fetch trash when dialog opens (to get fresh data)
watch(isOpen, (newValue) => {
  if (newValue) {
    journalStore.fetchTrashed();
  }
});

async function restoreJournal(journalId: string) {
  await journalStore.restoreJournal(journalId);
}

async function permanentlyDelete(journalId: string) {
  await journalStore.forceDeleteJournal(journalId);
}

async function restoreEntry(entryId: string) {
  await journalStore.restoreEntry(entryId);
}

async function permanentlyDeleteEntry(entryId: string) {
  await journalStore.forceDeleteEntry(entryId);
}

async function emptyTrash() {
  if (confirm('Are you sure you want to permanently delete all items in trash? This cannot be undone.')) {
    await journalStore.emptyTrash();
    // Also empty entries
    journalStore.trashedEntries = [];
  }
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
</script>

<template>
  <Dialog v-model:open="isOpen">
    <DialogTrigger as-child>
      <Button variant="outline" size="sm" class="gap-2 cursor-pointer" title="Trash">
        <Trash2 class="h-4 w-4" />
        Trash ({{ totalTrashedItems }})
      </Button>
    </DialogTrigger>
    <DialogContent class="max-w-2xl">
      <DialogHeader>
        <DialogTitle>Trash</DialogTitle>
        <DialogDescription>
          Manage your deleted items. You can restore them or permanently delete them.
        </DialogDescription>
      </DialogHeader>
      
      <!-- Tab Buttons -->
      <div class="flex gap-2 border-b">
        <button
          @click="activeTab = 'journals'"
          :class="[
            'px-3 py-2 text-sm font-medium transition-colors',
            activeTab === 'journals'
              ? 'border-b-2 border-primary text-primary'
              : 'text-muted-foreground hover:text-foreground'
          ]"
        >
          Journals ({{ journalStore.trashedJournals.length }})
        </button>
        <button
          @click="activeTab = 'entries'"
          :class="[
            'px-3 py-2 text-sm font-medium transition-colors',
            activeTab === 'entries'
              ? 'border-b-2 border-primary text-primary'
              : 'text-muted-foreground hover:text-foreground'
          ]"
        >
          Entries ({{ journalStore.trashedEntries.length }})
        </button>
      </div>
      
      <div class="space-y-4 max-h-[400px] overflow-y-auto">
        <!-- Journals Tab -->
        <div v-if="activeTab === 'journals'">
          <div v-if="journalStore.trashedJournals.length === 0" class="text-center py-8 text-muted-foreground">
            <Trash2 class="h-12 w-12 mx-auto mb-4 opacity-50" />
            <p>No trashed journals</p>
          </div>
          
          <Card v-for="journal in journalStore.trashedJournals" :key="journal.id" class="relative">
          <CardHeader class="pb-3">
            <div class="flex items-start justify-between">
              <div>
                <CardTitle class="text-base">{{ journal.title }}</CardTitle>
                <CardDescription class="text-xs">
                  Deleted {{ new Date(journal.deleted_at).toLocaleDateString() }}
                </CardDescription>
              </div>
              <div class="flex gap-1">
                <Button
                  @click="restoreJournal(journal.id)"
                  variant="ghost"
                  size="icon"
                  class="h-8 w-8 cursor-pointer"
                  title="Restore"
                >
                  <RotateCcw class="h-4 w-4" />
                </Button>
                <Button
                  @click="permanentlyDelete(journal.id)"
                  variant="ghost"
                  size="icon"
                  class="h-8 w-8 text-destructive hover:text-destructive cursor-pointer"
                  title="Delete permanently"
                >
                  <XCircle class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </CardHeader>
          <CardContent class="pt-0">
            <p class="text-xs text-muted-foreground">
              {{ journal.entries?.length || 0 }} entries
            </p>
          </CardContent>
          </Card>
        </div>
        
        <!-- Entries Tab -->
        <div v-if="activeTab === 'entries'">
          <div v-if="journalStore.trashedEntries.length === 0" class="text-center py-8 text-muted-foreground">
            <Trash2 class="h-12 w-12 mx-auto mb-4 opacity-50" />
            <p>No trashed entries</p>
          </div>
          
          <Card v-for="entry in journalStore.trashedEntries" :key="entry._id || entry.id" class="relative">
            <CardHeader class="pb-3">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <FileText v-if="!entry.card_type || entry.card_type === 'text'" class="h-4 w-4 text-muted-foreground" />
                    <CheckSquare v-else-if="entry.card_type === 'checkbox'" class="h-4 w-4 text-muted-foreground" />
                    <CardTitle class="text-base">
                      {{ entry.card_type === 'checkbox' ? 'Checklist' : 'Text Entry' }}
                    </CardTitle>
                    <span v-if="entry.mood" class="text-lg">{{ getMoodEmoji(entry.mood) }}</span>
                  </div>
                  <CardDescription class="text-xs mt-1">
                    From: {{ entry.journal?.title || 'Unknown Journal' }}
                  </CardDescription>
                  <CardDescription class="text-xs">
                    Deleted {{ new Date(entry.deleted_at).toLocaleDateString() }}
                  </CardDescription>
                </div>
                <div class="flex gap-1">
                  <Button
                    @click="restoreEntry(entry._id || entry.id)"
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8 cursor-pointer"
                    title="Restore"
                  >
                    <RotateCcw class="h-4 w-4" />
                  </Button>
                  <Button
                    @click="permanentlyDeleteEntry(entry._id || entry.id)"
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8 text-destructive hover:text-destructive cursor-pointer"
                    title="Delete permanently"
                  >
                    <XCircle class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent class="pt-0">
              <p v-if="entry.content" class="text-xs text-muted-foreground line-clamp-2">
                {{ entry.content }}
              </p>
              <p v-else-if="entry.checkbox_items" class="text-xs text-muted-foreground">
                {{ entry.checkbox_items.length }} items
              </p>
            </CardContent>
          </Card>
        </div>
      </div>
      
      <DialogFooter v-if="totalTrashedItems > 0">
        <Button
          @click="emptyTrash"
          variant="destructive"
          size="sm"
          class="gap-2 cursor-pointer"
        >
          <Trash2 class="h-4 w-4" />
          Empty Trash
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
