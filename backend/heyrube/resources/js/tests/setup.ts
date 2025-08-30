import { expect, vi } from 'vitest';

// Basic fetch mock placeholder; tests can override per-case
if (!globalThis.fetch) {
  // @ts-ignore
  globalThis.fetch = vi.fn();
}

// Extend expect here if needed
export { expect, vi };
