<script setup lang="ts">
import { ref } from 'vue';
import { NotebookPen, Plus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
const journalTitle = ref('');
const isSubmitting = ref(false);

async function handleCreate() {
  if (!journalTitle.value.trim()) {
    return;
  }
  
  isSubmitting.value = true;
  
  try {
    const xsrf = journalStore.getCookie('XSRF-TOKEN') ?? '';
    
    const response = await fetch('/api/journals', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': xsrf,
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        title: journalTitle.value,
        tags: '',
      }),
    });

    if (response.ok) {
      const newJournal = await response.json();
      // Add to journals list
      journalStore.journals.push({
        ...newJournal,
        id: newJournal.id || newJournal._id,
        entries: [],
      });
      // Select the new journal
      journalStore.selectJournal(newJournal.id || newJournal._id);
      // Clear form and close dialog
      journalTitle.value = '';
      isOpen.value = false;
    } else {
      console.error('Failed to create journal');
    }
  } catch (error) {
    console.error('Error creating journal:', error);
  } finally {
    isSubmitting.value = false;
  }
}

function handleCancel() {
  journalTitle.value = '';
  isOpen.value = false;
}
</script>

<template>
  <Dialog v-model:open="isOpen">
    <DialogTrigger as-child>
      <Button class="w-full gap-2 cursor-pointer">
        <Plus class="h-4 w-4" />
        Create New Journal
      </Button>
    </DialogTrigger>
    <DialogContent>
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <NotebookPen class="h-5 w-5" />
          Create New Journal
        </DialogTitle>
        <DialogDescription>
          Give your new journal a title. You can start adding entries right after creating it.
        </DialogDescription>
      </DialogHeader>
      
      <div class="py-4">
        <Label for="journal-title">Journal Title</Label>
        <Input
          id="journal-title"
          v-model="journalTitle"
          placeholder="Enter journal title..."
          class="mt-2"
          @keyup.enter="handleCreate"
          :disabled="isSubmitting"
        />
      </div>
      
      <DialogFooter>
        <Button
          variant="outline"
          @click="handleCancel"
          :disabled="isSubmitting"
          class="cursor-pointer"
        >
          Cancel
        </Button>
        <Button
          @click="handleCreate"
          :disabled="!journalTitle.trim() || isSubmitting"
          class="cursor-pointer"
        >
          {{ isSubmitting ? 'Creating...' : 'Create Journal' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
