import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useJournalStore } from '@/stores/journals';

const makeResponse = (init: Partial<Response> & { status?: number; ok?: boolean; json?: () => any } = {}) => {
  return {
    status: init.status ?? 200,
    ok: init.ok ?? true,
    // @ts-ignore
    json: init.json ?? (async () => ({})),
    // minimal shape for tests
    headers: new Headers({ 'content-type': 'application/json' }),
  } as any as Response;
};

describe('journal store - trash ops and entry deletion/restoration', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    // @ts-ignore
    global.fetch = vi.fn();
    // set a dummy cookie for XSFR lookups (not strictly required)
    document.cookie = 'XSRF-TOKEN=dummy';
  });

  it('deleteEntry removes entry, renumbers locally, and fetches trash', async () => {
    const store = useJournalStore();
    store.setJournals([
      { id: 'jid', entries: [ { id: 'e1', display_order: 0 }, { id: 'e2', display_order: 1 } ] } as any,
    ]);

    // mock fetch sequence: DELETE, GET trash journals, GET trash entries
    // @ts-ignore
    (global.fetch as any)
      .mockResolvedValueOnce(makeResponse({ status: 204, ok: true }))
      .mockResolvedValueOnce(makeResponse({ json: async () => [] }))
      .mockResolvedValueOnce(makeResponse({ json: async () => [{ id: 't1', journal_id: 'jid' }] }));

    const ok = await store.deleteEntry('jid', 'e1');
    expect(ok).toBe(true);

    expect(store.journals[0].entries.length).toBe(1);
    expect(store.journals[0].entries[0].id).toBe('e2');
    expect(store.journals[0].entries[0].display_order).toBe(0);

    const calls = (global.fetch as any).mock.calls.map((c: any[]) => c[0]);
    expect(calls[0]).toBe('/api/journals/jid/entries/e1');
    expect(calls).toContain('/api/trash/journals');
    expect(calls).toContain('/api/trash/entries');
  });

  it('restoreEntry removes from trashedEntries and pushes into journal.entries', async () => {
    const store = useJournalStore();
    store.setJournals([{ id: 'jid', entries: [] } as any]);
    store.trashedEntries = [{ id: 'eX' } as any];

    // @ts-ignore
    (global.fetch as any).mockResolvedValueOnce(
      makeResponse({ json: async () => ({ entry: { id: 'eX', journal_id: 'jid', content: 'restored', card_type: 'text' } }) })
    );

    await store.restoreEntry('eX');
    expect(store.trashedEntries.find(e => (e as any).id === 'eX')).toBeUndefined();
    expect(store.journals[0].entries?.some((e: any) => e.id === 'eX')).toBe(true);
  });

  it('toggleTrash(true) triggers fetchTrashed calls', async () => {
    const store = useJournalStore();
    // @ts-ignore
    (global.fetch as any)
      .mockResolvedValueOnce(makeResponse({ json: async () => [] }))
      .mockResolvedValueOnce(makeResponse({ json: async () => [] }));

    store.toggleTrash(true);

    // wait a macrotask tick to allow async fetch calls to resolve
    await new Promise((r) => setTimeout(r, 0));
    // and one more for sequential second fetch
    await new Promise((r) => setTimeout(r, 0));

    const calls = (global.fetch as any).mock.calls.map((c: any[]) => c[0]);
    expect(calls).toContain('/api/trash/journals');
    expect(calls).toContain('/api/trash/entries');
  });
});

