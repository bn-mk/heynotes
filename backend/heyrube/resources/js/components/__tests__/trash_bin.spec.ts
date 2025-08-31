import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { mount } from '@vue/test-utils';
import TrashBin from '@/components/TrashBin.vue';
import { useJournalStore } from '@/stores/journals';

const stubs = {
  Button: { template: '<button @click="$emit(\'click\')"><slot /></button>' },
  Dialog: { template: '<div><slot /></div>' },
  DialogTrigger: { template: '<div><slot /></div>' },
  DialogContent: { template: '<div><slot /></div>' },
  DialogHeader: { template: '<div><slot /></div>' },
  DialogFooter: { template: '<div><slot /></div>' },
  DialogTitle: { template: '<div><slot /></div>' },
  DialogDescription: { template: '<div><slot /></div>' },
  Card: { template: '<div><slot /></div>' },
  CardHeader: { template: '<div><slot /></div>' },
  CardTitle: { template: '<div><slot /></div>' },
  CardDescription: { template: '<div><slot /></div>' },
  CardContent: { template: '<div><slot /></div>' },
};

describe('TrashBin component', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    // @ts-ignore
    global.fetch = vi.fn()
    // Return empty arrays for initial fetchTrashed calls
    ;(global.fetch as any)
      .mockResolvedValueOnce(new Response(JSON.stringify([]), { status: 200, headers: { 'content-type': 'application/json' } }))
      .mockResolvedValueOnce(new Response(JSON.stringify([]), { status: 200, headers: { 'content-type': 'application/json' } }))
  });

  it('calls restore and delete actions for journals, and empties trash', async () => {
    const store = useJournalStore();
    store.trashedJournals = [{ id: 'jid', title: 'Trashed' } as any];
    store.trashedEntries = [{ id: 'e1', journal: { title: 'J' }, card_type: 'text' } as any];

    const restoreJournalSpy = vi.spyOn(store, 'restoreJournal').mockResolvedValue(undefined as any);
    const forceDeleteJournalSpy = vi.spyOn(store, 'forceDeleteJournal').mockResolvedValue(undefined as any);
    const emptySpy = vi.spyOn(store, 'emptyTrash').mockResolvedValue(undefined as any);

    // Confirm always true
    // @ts-ignore
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    const wrapper = mount(TrashBin, { global: { stubs } });

    // Click restore on journal via title attribute
    const restoreBtn = wrapper.find('button[title="Restore"]');
    expect(restoreBtn.exists()).toBe(true);
    await restoreBtn.trigger('click');
    expect(restoreJournalSpy).toHaveBeenCalledWith('jid');

    // Click delete permanently (journal)
    const delBtn = wrapper.find('button[title="Delete permanently"]');
    expect(delBtn.exists()).toBe(true);
    await delBtn.trigger('click');
    expect(forceDeleteJournalSpy).toHaveBeenCalledWith('jid');

    // Click Empty Trash
    const emptyBtn = wrapper.findAll('button').find(b => b.text().toLowerCase().includes('empty trash'));
    expect(emptyBtn).toBeTruthy();
    await emptyBtn!.trigger('click');
    expect(emptySpy).toHaveBeenCalled();
  });
});

