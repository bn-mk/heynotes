<script setup lang="ts">
import { ref } from 'vue';
import { Trash2, AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
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

const props = defineProps<{
  entryId: string;
  journalId?: string;
}>();

const emit = defineEmits<{ (e: 'deleted'): void }>();

const journalStore = useJournalStore();
const isOpen = ref(false);

async function handleDelete() {
  const journalId = props.journalId ?? journalStore.selectedJournalId;
  if (!journalId) return;
  const ok = await journalStore.deleteEntry(journalId, props.entryId);
  if (ok) {
    emit('deleted');
  }
  isOpen.value = false;
}
</script>

<template>
  <Dialog v-model:open="isOpen">
    <DialogTrigger as-child>
      <Button 
        variant="ghost" 
        size="icon"
        class="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10 cursor-pointer"
        @click.stop
        title="Delete entry"
      >
        <Trash2 class="h-4 w-4" />
        <span class="sr-only">Delete entry</span>
      </Button>
    </DialogTrigger>
    <DialogContent>
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <AlertTriangle class="h-5 w-5 text-destructive" />
          Delete Entry
        </DialogTitle>
        <DialogDescription>
          Are you sure you want to delete this entry?
          <br /><br />
          The entry will be moved to the trash. You can restore it later from the trash.
        </DialogDescription>
      </DialogHeader>
      <DialogFooter>
        <Button
          @click="isOpen = false"
          variant="outline"
          class="cursor-pointer"
        >
          Cancel
        </Button>
        <Button
          @click="handleDelete"
          class="bg-destructive text-destructive-foreground hover:bg-destructive/90 cursor-pointer"
        >
          Move to Trash
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
