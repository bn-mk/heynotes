import { defineStore } from 'pinia';
import type { JournalListType } from '@/types';

export const useJournalStore = defineStore('journal', {
  state: () => ({
    journals: [] as JournalListType[],
    selectedJournalId: null as string | null,
    creatingEntry: false,
  }),
  getters: {
    selectedJournal(state): JournalListType | null {
      return state.journals.find(j => j.id === state.selectedJournalId) || null;
    },
  },
  actions: {
    setJournals(journals: JournalListType[]) {
      this.journals = journals.map(j => ({ ...j, id: j.id.toString() }));
      // Restore last selected from storage if available
      const saved = localStorage.getItem('selectedJournalId');
      if (saved && journals.some(j => j.id === saved)) {
        this.selectedJournalId = saved;
      } else if (!this.selectedJournalId && journals.length > 0) {
        this.selectedJournalId = journals[0].id.toString();
      }
    },
    selectJournal(journalId: string) {
      this.selectedJournalId = journalId.toString();
      localStorage.setItem('selectedJournalId', this.selectedJournalId);
      this.creatingEntry = false;
    },
    startCreatingEntry() {
      this.creatingEntry = true;
    },
    stopCreatingEntry() {
      this.creatingEntry = false;
    },
  },
});

