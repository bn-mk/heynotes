import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { mount } from '@vue/test-utils';
import DeleteEntryButton from '@/components/DeleteEntryButton.vue';
import DeleteJournalButton from '@/components/DeleteJournalButton.vue';
import { useJournalStore } from '@/stores/journals';

const stubs = {
  Button: {
    template: '<button @click="$emit(\'click\')"><slot /></button>',
  },
  Dialog: { template: '<div><slot /></div>' },
  DialogTrigger: { template: '<div><slot /></div>' },
  DialogContent: { template: '<div><slot /></div>' },
  DialogHeader: { template: '<div><slot /></div>' },
  DialogFooter: { template: '<div><slot /></div>' },
  DialogTitle: { template: '<div><slot /></div>' },
  DialogDescription: { template: '<div><slot /></div>' },
};

describe('Delete buttons components', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('DeleteEntryButton calls store.deleteEntry and emits deleted', async () => {
    const store = useJournalStore();
    store.selectedJournalId = 'jid' as any;
    const spy = vi.spyOn(store, 'deleteEntry').mockResolvedValue(true);

    const wrapper = mount(DeleteEntryButton, {
      props: { entryId: 'e1' },
      global: { stubs },
    });

    // directly click the destructive action button by text
    const btn = wrapper.findAll('button').find(b => b.text().includes('Move to Trash'));
    expect(btn).toBeTruthy();
    await btn!.trigger('click');

    expect(spy).toHaveBeenCalledWith('jid', 'e1');
    expect(wrapper.emitted('deleted')).toBeTruthy();
  });

  it('DeleteJournalButton calls store.deleteJournal', async () => {
    const store = useJournalStore();
    const spy = vi.spyOn(store, 'deleteJournal').mockResolvedValue(undefined as any);

    const wrapper = mount(DeleteJournalButton, {
      props: { journalId: 'jid', journalTitle: 'Title' },
      global: { stubs },
    });

    const btn = wrapper.findAll('button').find(b => b.text().includes('Move to Trash'));
    expect(btn).toBeTruthy();
    await btn!.trigger('click');

    expect(spy).toHaveBeenCalledWith('jid');
  });
});

