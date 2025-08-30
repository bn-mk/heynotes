import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useJournalStore } from '@/stores/journals';

const journalId = 'jid-1';

describe('journal store - tags', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    // reset fetch mock
    // @ts-ignore
    global.fetch = vi.fn();
  });

  it('optimistically updates tags and reconciles with server', async () => {
    const store = useJournalStore();
    store.setJournals([{ id: journalId, user_id: 'u', title: 'T', tags: ['a'], entries: [] } as any]);

    // mock PUT response returning same tags
    // @ts-ignore
    global.fetch.mockResolvedValueOnce({ ok: true, headers: new Headers({ 'content-type': 'application/json' }), json: async () => ({ id: journalId, title: 'T', user_id: 'u', tags: ['b', 'c'] }) });

    await store.updateJournalTags(journalId, ['b', 'c']);

    expect(store.journals[0].tags).toEqual(['b', 'c']);
  });

  it('createTag updates allTags and returns the name', async () => {
    const store = useJournalStore();
    // @ts-ignore
    global.fetch.mockResolvedValueOnce({ ok: true, headers: new Headers({ 'content-type': 'application/json' }), json: async () => 'newtag' });

    const name = await store.createTag('newtag');
    expect(name).toBe('newtag');
    expect(store.allTags).toContain('newtag');
  });
});
