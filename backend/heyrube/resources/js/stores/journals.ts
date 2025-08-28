import { defineStore } from 'pinia';
import type { JournalListType } from '@/types';

export const useJournalStore = defineStore('journal', {
  state: () => ({
    journals: [] as JournalListType[],
    trashedJournals: [] as JournalListType[],
    trashedEntries: [] as any[],
    allTags: [] as string[],
    selectedJournalId: null as string | null,
    creatingEntry: false,
    creatingJournal: false,
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
    async updateJournal(journalId: string, data: { title?: string, tags?: string[] }) {
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
    const responseData = updatedJournal.data ? updatedJournal.data : updatedJournal;
this.journals.splice(index, 1, { ...this.journals[index], ...responseData });
    // Also update selected if needed
    if (this.selectedJournalId === journalId) {
        this.journals[index] = { ...this.journals[index] };
    }
}
            } else {
                console.error('Failed to update journal');
            }
        } catch (error) {
            console.error('Error updating journal:', error);
        }
    },

    async fetchTags() {
      const xsrf = this.getCookie('XSRF-TOKEN') ?? '';
      try {
        const response = await fetch('/api/tags', {
          credentials: 'include',
          headers: { 'X-XSRF-TOKEN': xsrf },
        });
        if (response.ok) {
          this.allTags = await response.json();
        }
      } catch (e) {
        console.error('Failed to fetch tags', e);
      }
    },

    startCreatingEntry() {
      this.creatingEntry = true;
    },
    stopCreatingEntry() {
      this.creatingEntry = false;
      this.creatingJournal = false;
    },
    startCreatingJournal() {
      this.creatingJournal = true;
      this.creatingEntry = true;
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
        // Fetch trashed journals
        const journalsResponse = await fetch('/api/trash/journals', {
            credentials: 'include',
            headers: {
                'X-XSRF-TOKEN': xsrf,
            },
        });

        if (journalsResponse.ok) {
            this.trashedJournals = await journalsResponse.json();
        } else {
            console.error('Failed to fetch trashed journals');
        }
        
        // Fetch trashed entries
        const entriesResponse = await fetch('/api/trash/entries', {
            credentials: 'include',
            headers: {
                'X-XSRF-TOKEN': xsrf,
            },
        });

        if (entriesResponse.ok) {
            this.trashedEntries = await entriesResponse.json();
        } else {
            console.error('Failed to fetch trashed entries');
        }
      } catch (error) {
        console.error('Error fetching trashed items:', error);
      }
    },

    async restoreJournal(journalId: string) {
      const xsrf = this.getCookie('XSRF-TOKEN') ?? '';

      try {
        const response = await fetch(`/api/trash/journals/${journalId}/restore`,
        {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf,
            },
        });

        if (response.ok) {
            const { journal } = await response.json();
            const id = String(journal.id || journal._id || journalId);
            // Normalize and ensure id is a string
            const normalized = { ...journal, id } as any;

            // Fetch entries for the restored journal so the dashboard shows them immediately
            try {
              const entriesRes = await fetch(`/api/journals/${id}/entries`, {
                credentials: 'include',
                headers: { 'X-XSRF-TOKEN': xsrf },
              });
              if (entriesRes.ok) {
                normalized.entries = await entriesRes.json();
              } else {
                normalized.entries = [];
              }
            } catch {
              normalized.entries = [];
            }

            // Remove from trashed list
            this.trashedJournals = this.trashedJournals.filter(j => (j.id || j._id) !== journalId);

            // Upsert into journals list
            const idx = this.journals.findIndex(j => String(j.id) === id);
            if (idx >= 0) {
              this.journals[idx] = { ...this.journals[idx], ...normalized };
            } else {
              this.journals.push(normalized);
            }

            // Refresh trash lists (entries from this journal should disappear)
            await this.fetchTrashed();
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
            credentials: 'include',
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
            credentials: 'include',
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

    async updateJournalTags(journalId: string, tags: string[]) {
      return this.updateJournal(journalId, { tags });
    },

    async createTag(name: string) {
      const xsrf = this.getCookie('XSRF-TOKEN') ?? '';
      try {
        const response = await fetch('/api/tags', {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrf,
          },
          body: JSON.stringify({ name }),
        });
        if (response.ok) {
          const createdName = await response.json();
          // Update client-side list if not present
          if (!this.allTags.includes(createdName)) {
            this.allTags = [...this.allTags, createdName].sort((a, b) => a.localeCompare(b));
          }
          return createdName as string;
        }
      } catch (e) {
        console.error('Failed to create tag', e);
      }
      return null;
    },
    
    async deleteEntry(journalId: string, entryId: string) {
      const xsrf = this.getCookie('XSRF-TOKEN') ?? '';
      
      try {
        const response = await fetch(`/api/journals/${journalId}/entries/${entryId}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: { 'X-XSRF-TOKEN': xsrf },
        });
        
        if (response.status === 204) {
            // Remove entry client-side from Pinia store
            const journal = this.journals.find(j => j.id === journalId);
            if (journal && journal.entries) {
                const idx = journal.entries.findIndex(e => e.id === entryId);
                if (idx > -1) journal.entries.splice(idx, 1);
                // Renumber local display_order to match current visible order
                journal.entries.forEach((e: any, i: number) => { e.display_order = i; });
            }
            // Fetch updated trash to include the deleted entry
            await this.fetchTrashed();
            return true;
        } else {
            console.error('Failed to delete entry');
            return false;
        }
      } catch (error) {
        console.error('Error deleting entry:', error);
        return false;
      }
    },
    
    async restoreEntry(entryId: string) {
      const xsrf = this.getCookie('XSRF-TOKEN') ?? '';
      
      try {
        const response = await fetch(`/api/trash/entries/${entryId}/restore`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf,
            },
        });
        
        if (response.ok) {
            const { entry } = await response.json();
            // Remove from trashed entries (support _id or id)
            this.trashedEntries = this.trashedEntries.filter(e => (e._id || e.id) !== entryId);
            // Add back to the journal's entries
            const journal = this.journals.find(j => j.id === entry.journal_id);
            if (journal) {
                if (!journal.entries) journal.entries = [];
                journal.entries.push(entry);
            }
        } else {
            console.error('Failed to restore entry');
        }
      } catch (error) {
        console.error('Error restoring entry:', error);
      }
    },
    
    async forceDeleteEntry(entryId: string) {
      const xsrf = this.getCookie('XSRF-TOKEN') ?? '';
      
      try {
        const response = await fetch(`/api/trash/entries/${entryId}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf,
            },
        });
        
        if (response.ok) {
            // Remove from trashed entries (support _id or id)
            this.trashedEntries = this.trashedEntries.filter(e => (e._id || e.id) !== entryId);
        } else {
            console.error('Failed to permanently delete entry');
        }
      } catch (error) {
        console.error('Error permanently deleting entry:', error);
      }
    },
  },
});

