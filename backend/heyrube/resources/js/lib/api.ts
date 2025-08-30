export function getXsrfToken(): string {
  const match = document.cookie.match(new RegExp('(^|;\s*)(XSRF-TOKEN)=([^;]*)'));
  return match ? decodeURIComponent(match[3]) : '';
}

export interface JsonFetchOptions extends RequestInit {
  json?: any;
}

export async function jsonFetch<T = any>(url: string, opts: JsonFetchOptions = {}): Promise<T> {
  const headers = new Headers(opts.headers || {});
  headers.set('Accept', 'application/json');
  if (opts.json !== undefined) headers.set('Content-Type', 'application/json');
  if (!headers.has('X-XSRF-TOKEN')) headers.set('X-XSRF-TOKEN', getXsrfToken());

  const res = await fetch(url, {
    ...opts,
    credentials: opts.credentials ?? 'include',
    headers,
    body: opts.json !== undefined ? JSON.stringify(opts.json) : opts.body,
  });

  if (!res.ok) {
    const text = await res.text().catch(() => '');
    throw new Error(`HTTP ${res.status} ${res.statusText}: ${text}`);
  }
  const ct = res.headers.get('content-type') || '';
  if (ct.includes('application/json')) return (await res.json()) as T;
  // @ts-expect-error allow non-JSON response fallback
  return (await res.text()) as T;
}
