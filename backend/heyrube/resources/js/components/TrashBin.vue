<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { Trash2, RotateCcw, XCircle } from 'lucide-vue-next';
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

async function emptyTrash() {
  await journalStore.emptyTrash();
}
</script>

<template>
  <Dialog v-model:open="isOpen">
    <DialogTrigger as-child>
      <Button variant="outline" size="sm" class="gap-2 cursor-pointer">
        <Trash2 class="h-4 w-4" />
        Trash ({{ journalStore.trashedJournals.length }})
      </Button>
    </DialogTrigger>
    <DialogContent class="max-w-2xl">
      <DialogHeader>
        <DialogTitle>Trash</DialogTitle>
        <DialogDescription>
          Manage your deleted journals. You can restore them or permanently delete them.
        </DialogDescription>
      </DialogHeader>
      
      <div class="space-y-4 max-h-[400px] overflow-y-auto">
        <div v-if="journalStore.trashedJournals.length === 0" class="text-center py-8 text-muted-foreground">
          <Trash2 class="h-12 w-12 mx-auto mb-4 opacity-50" />
          <p>Trash is empty</p>
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
      
      <DialogFooter v-if="journalStore.trashedJournals.length > 0">
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
