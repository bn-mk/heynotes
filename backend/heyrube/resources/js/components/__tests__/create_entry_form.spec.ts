import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { mount } from '@vue/test-utils';
import CreateEntryForm from '@/components/CreateEntryForm.vue';
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
  LinkEntryButton: { template: '<div />' },
  DeleteEntryButton: { template: '<div />' },
};

describe('CreateEntryForm (edit mode)', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    // @ts-ignore
    global.fetch = vi.fn();
    document.cookie = 'XSRF-TOKEN=testxsrf; path=/';
  });

  it('submits PUT with updated text content and emits success', async () => {
    const store = useJournalStore();
    store.journals = [{ id: 'jid', title: 'J', tags: [], entries: [] } as any];
    store.selectedJournalId = 'jid' as any;

    // First call: fetchTags -> return []
    // @ts-ignore
    (global.fetch as any)
      .mockResolvedValueOnce(new Response(JSON.stringify([]), { status: 200, headers: { 'content-type': 'application/json' } }))
      // Second call: PUT update entry
      .mockResolvedValueOnce(new Response(JSON.stringify({ id: 'e1', journal_id: 'jid', content: 'new content', card_type: 'text' }), { status: 200, headers: { 'content-type': 'application/json' } }));

    const wrapper = mount(CreateEntryForm, {
      props: {
        entryToEdit: { id: 'e1', journal_id: 'jid', card_type: 'text', content: 'old content', pinned: false },
      },
      global: { stubs },
    });

    // Update textarea content
    const ta = wrapper.find('textarea');
    expect(ta.exists()).toBe(true);
    await ta.setValue('new content');

    // Submit the form (component listens on form submit)
    const form = wrapper.find('form');
    await form.trigger('submit');

    // Assert fetch called with PUT to correct URL
    // Find the PUT call among fetch calls
    const calls = (global.fetch as any).mock.calls as any[];
    const putCall = calls.find(([u, init]: any[]) => String(u).includes('/api/journals/jid/entries/e1'));
    expect(putCall).toBeTruthy();
    expect(putCall[1].method).toBe('PUT');

    // Wait for async handlers and emits
    await new Promise((r) => setTimeout(r, 0));
    await Promise.resolve();

    // Emitted success
    const emitted = wrapper.emitted('success');
    expect(emitted && emitted.length).toBe(1);
    expect(emitted?.[0]?.[0]).toMatchObject({ id: 'e1', content: 'new content' });
  });
});

