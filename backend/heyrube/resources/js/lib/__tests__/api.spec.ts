import { describe, it, expect, beforeEach, vi } from 'vitest';
import { jsonFetch, getXsrfToken } from '@/lib/api';

// Helper to patch global fetch
const makeResponse = (init: Partial<Response> & { status?: number; ok?: boolean; json?: () => any; text?: () => any } = {}) => {
  return {
    status: init.status ?? 200,
    ok: init.ok ?? true,
    // @ts-ignore
    json: init.json ?? (async () => ({})),
    // @ts-ignore
    text: init.text ?? (async () => ''),
    headers: new Headers(init.headers || { 'content-type': 'application/json' }),
    statusText: init.statusText ?? 'OK',
  } as any as Response;
};

describe('lib/api', () => {
  beforeEach(() => {
    // @ts-ignore
    global.fetch = vi.fn();
    document.cookie = 'XSRF-TOKEN=abc123; path=/';
  });

  it('getXsrfToken parses cookie', () => {
    expect(getXsrfToken()).toBe('abc123');
  });

  it('jsonFetch sends JSON and returns JSON', async () => {
    // @ts-ignore
    (global.fetch as any).mockResolvedValueOnce(makeResponse({ json: async () => ({ ok: true }) }));

    const out = await jsonFetch('/x', { method: 'POST', json: { a: 1 } });
    expect(out).toEqual({ ok: true });

    const [url, init] = (global.fetch as any).mock.calls[0];
    expect(url).toBe('/x');
    expect(init.credentials).toBe('include');
    expect(init.headers.get('Content-Type')).toBe('application/json');
    expect(init.headers.get('X-XSRF-TOKEN')).toBe('abc123');
    expect(init.body).toBe(JSON.stringify({ a: 1 }));
  });

  it('jsonFetch throws on non-ok and includes response text', async () => {
    // @ts-ignore
    (global.fetch as any).mockResolvedValueOnce(makeResponse({ status: 500, ok: false, statusText: 'Server Error', text: async () => 'boom' }));

    await expect(jsonFetch('/err')).rejects.toThrowError(/HTTP 500/);
  });

  it('jsonFetch returns text when non-JSON', async () => {
    // @ts-ignore
    (global.fetch as any).mockResolvedValueOnce(makeResponse({ headers: new Headers({ 'content-type': 'text/plain' }), text: async () => 'hello' }));
    const res = await jsonFetch('/txt');
    expect(res).toBe('hello');
  });
});

