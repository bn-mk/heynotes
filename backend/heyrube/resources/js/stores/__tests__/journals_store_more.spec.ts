import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

vi.mock('@/lib/api', () => ({
  jsonFetch: vi.fn(),
}));

import { jsonFetch } from '@/lib/api';
import { useJournalStore } from '@/stores/journals';

const makeResponse = (init: Partial<Response> & { status?: number; ok?: boolean; json?: () => any } = {}) => {
  return {
    status: init.status ?? 200,
    ok: init.ok ?? true,
    // @ts-ignore
    json: init.json ?? (async () => ({})),
    headers: new Headers({ 'content-type': 'application/json' }),
  } as any as Response;
};

describe('journal store - more flows', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    // @ts-ignore
    global.fetch = vi.fn();
    // @ts-ignore
    ;(jsonFetch as any).mockReset();
    document.cookie = 'XSRF-TOKEN=dummy';
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('updateJournalTags optimistically updates and persists via updateJournal', async () => {
    const store = useJournalStore();
    store.setJournals([{ id: 'jid', title: 'Old', tags: ['old'], entries: [] } as any]);

    // Mock updateJournal PUT response, server returns deduped order
    // @ts-ignore
    ;(jsonFetch as any).mockResolvedValueOnce({ id: 'jid', title: 'New', user_id: 'u', tags: ['b', 'a'] });

    await store.updateJournalTags('jid', ['a', 'b', 'a']);

    expect(jsonFetch).toHaveBeenCalledWith('/api/journals/jid', expect.objectContaining({ method: 'PUT' }));
    expect(store.journals[0].tags).toEqual(['b', 'a']);
    expect(store.journals[0].title).toBe('New');
  });

  it('addTagToJournal and removeTagFromJournal update tags without duplicates', () => {
    const store = useJournalStore();
    store.setJournals([{ id: 'jid', title: 'T', tags: ['a'], entries: [] } as any]);

    store.addTagToJournal('jid', 'b');
    store.addTagToJournal('jid', 'a'); // duplicate ignored
    expect(store.journals[0].tags).toEqual(['a', 'b']);

    store.removeTagFromJournal('jid', 'a');
    expect(store.journals[0].tags).toEqual(['b']);
  });

  it('fetchTags populates allTags', async () => {
    const store = useJournalStore();
    // @ts-ignore
    ;(jsonFetch as any).mockResolvedValueOnce(['x', 'y']);
    await store.fetchTags();
    expect(store.allTags).toEqual(['x', 'y']);
  });

  it('createTag posts to API and appends to allTags', async () => {
    const store = useJournalStore();
    // @ts-ignore
    ;(jsonFetch as any).mockResolvedValueOnce('newtag');
    const name = await store.createTag('newtag');
    expect(name).toBe('newtag');
    expect(store.allTags).toContain('newtag');
  });

  it('restoreJournal removes from trashed and upserts into journals (fetches entries)', async () => {
    const store = useJournalStore();
    store.journals = [] as any;
    store.trashedJournals = [{ id: 'jid', title: 'Old' } as any];

    // Fetch sequence: POST restore, GET entries, GET trash journals, GET trash entries
    // @ts-ignore
    ;(global.fetch as any)
      .mockResolvedValueOnce(makeResponse({ json: async () => ({ journal: { id: 'jid', title: 'Restored' } }) }))
      .mockResolvedValueOnce(makeResponse({ json: async () => ([{ id: 'e1' }]) }))
      .mockResolvedValueOnce(makeResponse({ json: async () => ([]) }))
      .mockResolvedValueOnce(makeResponse({ json: async () => ([]) }));

    await store.restoreJournal('jid');

    expect(store.trashedJournals.find(j => (j as any).id === 'jid')).toBeUndefined();
    expect(store.journals.some(j => (j as any).id === 'jid')).toBe(true);
    const inserted = store.journals.find(j => (j as any).id === 'jid') as any;
    expect(inserted.entries?.length).toBe(1);
  });

  it('forceDeleteJournal removes from trashedJournals', async () => {
    const store = useJournalStore();
    store.trashedJournals = [{ id: 'jid' } as any];
    // @ts-ignore
    ;(global.fetch as any).mockResolvedValueOnce(makeResponse({ status: 200 }));

    await store.forceDeleteJournal('jid');
    expect(store.trashedJournals.some(j => (j as any).id === 'jid')).toBe(false);
  });

  it('emptyTrash clears trashedJournals', async () => {
    const store = useJournalStore();
    store.trashedJournals = [{ id: 'a' } as any, { id: 'b' } as any];
    // @ts-ignore
    ;(global.fetch as any).mockResolvedValueOnce(makeResponse({ status: 200 }));

    await store.emptyTrash();
    expect(store.trashedJournals).toEqual([]);
  });
});

