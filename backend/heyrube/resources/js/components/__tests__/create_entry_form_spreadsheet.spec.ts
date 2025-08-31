import { describe, it, expect, beforeEach, vi } from 'vitest';

vi.mock('jspreadsheet-ce', () => ({
  default: vi.fn(() => ({
    getData: vi.fn(() => [['cell']]),
    options: {},
    setWidth: vi.fn(),
    refresh: vi.fn(),
    destroy: vi.fn(),
  })),
}));

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

describe('CreateEntryForm (spreadsheet mode)', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    // @ts-ignore
    global.fetch = vi.fn();
    document.cookie = 'XSRF-TOKEN=testxsrf; path=/';
  });

  it('serializes spreadsheet data and posts content JSON', async () => {
    const store = useJournalStore();
    store.journals = [{ id: 'jid', title: 'J', tags: [], entries: [] } as any];
    store.selectedJournalId = 'jid' as any;

    // fetchTags + POST entry
    // @ts-ignore
    (global.fetch as any)
      .mockResolvedValueOnce(new Response(JSON.stringify([]), { status: 200, headers: { 'content-type': 'application/json' } }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ id: 'e1', journal_id: 'jid', card_type: 'spreadsheet', content: '[["cell"]]' }), { status: 201, headers: { 'content-type': 'application/json' } }));

    const wrapper = mount(CreateEntryForm, { global: { stubs } });

    // Switch to spreadsheet
    const btnSpreadsheet = wrapper.findAll('button').find(b => b.text().includes('Spreadsheet'))!;
    await btnSpreadsheet.trigger('click');

    // Submit
    const form = wrapper.find('form');
    await form.trigger('submit');

    const calls = (global.fetch as any).mock.calls as any[];
    const post = calls.find(([u, init]: any[]) => String(u) === '/api/journals/jid/entries');
    expect(post).toBeTruthy();
    const body = JSON.parse(post[1].body);
    expect(body.card_type).toBe('spreadsheet');
    expect(body.content).toContain('cell');
  });
});

