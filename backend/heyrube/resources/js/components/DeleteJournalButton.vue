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
  journalId: string;
  journalTitle: string;
}>();

const journalStore = useJournalStore();
const isOpen = ref(false);

async function handleDelete() {
  console.log('Delete button clicked for journal:', props.journalId, props.journalTitle);
  await journalStore.deleteJournal(props.journalId);
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
      >
        <Trash2 class="h-4 w-4" />
        <span class="sr-only">Delete journal</span>
      </Button>
    </DialogTrigger>
    <DialogContent>
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <AlertTriangle class="h-5 w-5 text-destructive" />
          Delete Journal
        </DialogTitle>
        <DialogDescription>
          Are you sure you want to delete "{{ journalTitle }}"? 
          <br><br>
          This journal and all its entries will be moved to the trash. 
          You can restore it from the trash later if needed.
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
