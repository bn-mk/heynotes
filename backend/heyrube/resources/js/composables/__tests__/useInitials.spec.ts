import { describe, it, expect } from 'vitest';
import { getInitials } from '@/composables/useInitials';

describe('useInitials.getInitials', () => {
  it('returns empty for undefined/empty', () => {
    expect(getInitials()).toBe('');
    expect(getInitials('')).toBe('');
  });

  it('returns first letter for single name', () => {
    expect(getInitials('madonna')).toBe('M');
  });

  it('uses first and last name initials', () => {
    expect(getInitials('John Doe')).toBe('JD');
    expect(getInitials('  Ada   Lovelace  ')).toBe('AL');
  });
});

