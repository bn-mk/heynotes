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

describe('CreateEntryForm (checkbox mode)', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    // @ts-ignore
    global.fetch = vi.fn();
    document.cookie = 'XSRF-TOKEN=testxsrf; path=/';
  });

  it('creates checkbox entry with items', async () => {
    const store = useJournalStore();
    store.journals = [{ id: 'jid', title: 'J', tags: [], entries: [] } as any];
    store.selectedJournalId = 'jid' as any;

    // First fetch: fetchTags
    // @ts-ignore
    (global.fetch as any)
      .mockResolvedValueOnce(new Response(JSON.stringify([]), { status: 200, headers: { 'content-type': 'application/json' } }))
      // Second: POST entry
      .mockResolvedValueOnce(new Response(JSON.stringify({ id: 'e1', journal_id: 'jid', card_type: 'checkbox', checkbox_items: [{ text: 'Task', checked: false }] }), { status: 201, headers: { 'content-type': 'application/json' } }));

    const wrapper = mount(CreateEntryForm, { global: { stubs } });

    // Switch to checkbox mode by clicking button
    const btnChecklist = wrapper.findAll('button').find(b => b.text().includes('Checklist'))!;
    await btnChecklist.trigger('click');

    // Type a checklist item and add it
    const input = wrapper.find('input[placeholder="Add a checklist item..."]');
    await input.setValue('Task');
    await input.trigger('keyup.enter');

    // Submit form
    const form = wrapper.find('form');
    await form.trigger('submit');

    // Allow async submit to run
    await new Promise((r) => setTimeout(r, 0));

    const calls = (global.fetch as any).mock.calls as any[];
    const post = calls.find(([u, init]: any[]) => String(u) === '/api/journals/jid/entries');
    expect(post).toBeTruthy();
    const body = JSON.parse(post[1].body);
    expect(body.card_type).toBe('checkbox');
    expect(Array.isArray(body.checkbox_items) && body.checkbox_items.length).toBeTruthy();
  });
});

