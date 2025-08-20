import { defineStore } from 'pinia';
import type { JournalListType } from '@/types';

export const useJournalStore = defineStore('journal', {
  state: () => ({
    journals: [] as JournalListType[],
    trashedJournals: [] as JournalListType[],
    selectedJournalId: null as string | null,
    creatingEntry: false,
    showTrash: false,
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

    async deleteJournal(journalId: string) {
      const xsrf = this.getCookie('XSRF-TOKEN') ?? '';
      
      console.log('Deleting journal with ID:', journalId);

      try {
        const response = await fetch(`/api/journals/${journalId}`,
        {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            // Remove from active journals
            this.journals = this.journals.filter(j => j.id !== journalId);
            // If it was the selected journal, clear selection
            if (this.selectedJournalId === journalId) {
                this.selectedJournalId = null;
                // Select another journal if available
                if (this.journals.length > 0) {
                    this.selectedJournalId = this.journals[0].id;
                }
            }
            console.log('Journal deleted successfully');
            // Fetch the updated trash
            await this.fetchTrashed();
        } else {
            const errorText = await response.text();
            console.error('Failed to delete journal:', response.status, errorText);
        }
      } catch (error) {
          console.error('Error deleting journal:', error);
      }
    },

    async fetchTrashed() {
      const xsrf = this.getCookie('XSRF-TOKEN') ?? '';

      try {
        const response = await fetch('/api/trash/journals', {
            headers: {
                'X-XSRF-TOKEN': xsrf,
            },
        });

        if (response.ok) {
            this.trashedJournals = await response.json();
        } else {
            console.error('Failed to fetch trashed journals');
        }
      } catch (error) {
        console.error('Error fetching trashed journals:', error);
      }
    },

    async restoreJournal(journalId: string) {
      const xsrf = this.getCookie('XSRF-TOKEN') ?? '';

      try {
        const response = await fetch(`/api/trash/journals/${journalId}/restore`,
        {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf,
            },
        });

        if (response.ok) {
            const { journal } = await response.json();
            this.trashedJournals = this.trashedJournals.filter(j => j.id !== journalId);
            this.journals.push(journal);
        } else {
            console.error('Failed to restore journal');
        }
      } catch (error) {
        console.error('Error restoring journal:', error);
      }
    },

    async forceDeleteJournal(journalId: string) {
      const xsrf = this.getCookie('XSRF-TOKEN') ?? '';

      try {
        const response = await fetch(`/api/trash/journals/${journalId}`,
        {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf,
            },
        });

        if (response.ok) {
            this.trashedJournals = this.trashedJournals.filter(j => j.id !== journalId);
        } else {
            console.error('Failed to permanently delete journal');
        }
      } catch (error) {
        console.error('Error permanently deleting journal:', error);
      }
    },

    async emptyTrash() {
      const xsrf = this.getCookie('XSRF-TOKEN') ?? '';

      try {
        const response = await fetch('/api/trash/empty', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf,
            },
        });

        if (response.ok) {
            this.trashedJournals = [];
        } else {
            console.error('Failed to empty trash');
        }
      } catch (error) {
        console.error('Error emptying trash:', error);
      }
    },

    toggleTrash(show: boolean) {
      this.showTrash = show;
      if (show) {
        this.fetchTrashed();
      }
    },

    getCookie(name: string) {
        const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
        return match ? decodeURIComponent(match[3]) : null;
    },
  },
});

