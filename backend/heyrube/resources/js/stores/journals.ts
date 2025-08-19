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
    async updateJournal(journalId: string, data: { title: string }) {
        const getCookie = (name: string) => {
            const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
            return match ? decodeURIComponent(match[3]) : null;
        };
        const xsrf = getCookie('XSRF-TOKEN') ?? '';

        try {
            const response = await fetch(`/api/journals/${journalId}`,
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrf,
                },
                body: JSON.stringify(data),
            });

            if (response.ok) {
                const updatedJournal = await response.json();
                const index = this.journals.findIndex(j => j.id === journalId);
                if (index !== -1) {
                    this.journals[index] = { ...this.journals[index], ...updatedJournal };
                }
            } else {
                console.error('Failed to update journal');
            }
        } catch (error) {
            console.error('Error updating journal:', error);
        }
    },
    startCreatingEntry() {
      this.creatingEntry = true;
    },
    stopCreatingEntry() {
      this.creatingEntry = false;
    },
  },
});

