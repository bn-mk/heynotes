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

describe('CreateEntryForm (new journal creation)', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    // @ts-ignore
    global.fetch = vi.fn();
    document.cookie = 'XSRF-TOKEN=testxsrf; path=/';
  });

  it('creates a new journal and posts entry', async () => {
    const store = useJournalStore();
    store.journals = [] as any;

    // Mock sequence: fetchTags, POST /api/journals, POST /entries
    // @ts-ignore
    (global.fetch as any)
      .mockResolvedValueOnce(new Response(JSON.stringify([]), { status: 200, headers: { 'content-type': 'application/json' } }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ id: 'newjid', title: 'My New Journal', tags: [] }), { status: 201, headers: { 'content-type': 'application/json' } }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ id: 'eNew', journal_id: 'newjid', card_type: 'text', content: 'Hello' }), { status: 201, headers: { 'content-type': 'application/json' } }));

    const wrapper = mount(CreateEntryForm, {
      props: { createNewJournal: true },
      global: { stubs },
    });

    // Set new journal title programmatically (input is inside dropdown UI)
    ;(wrapper.vm as any).newJournalTitle = 'My New Journal';
    await (wrapper.vm as any).$nextTick?.();

    // Set content text
    const textArea = wrapper.find('textarea');
    await textArea.setValue('Hello');


    // Submit form
    const form = wrapper.find('form');
    await form.trigger('submit');

    // Allow chained async calls (create journal -> create entry)
    await new Promise((r) => setTimeout(r, 0));
    await new Promise((r) => setTimeout(r, 0));

    const calls = (global.fetch as any).mock.calls.map((c: any[]) => String(c[0]));
    expect(calls[1]).toBe('/api/journals');
    expect(calls).toContain('/api/journals/newjid/entries');

    // Store updated with new journal and entry
    const j = store.journals.find(j => (j as any).id === 'newjid') as any;
    expect(j).toBeTruthy();
    expect(j.entries && j.entries.length).toBe(1);
  });
});

