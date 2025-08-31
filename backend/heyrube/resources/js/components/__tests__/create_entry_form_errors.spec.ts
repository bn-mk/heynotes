import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { mount } from '@vue/test-utils';
import CreateEntryForm from '@/components/CreateEntryForm.vue';

const stubs = {
  Button: { template: '<button @click="$emit(\'click\')"><slot /></button>' },
  Dialog: { template: '<div><slot /></div>' },
  DialogTrigger: { template: '<div><slot /></div>' },
  DialogContent: { template: '<div><slot /></div>' },
  DialogHeader: { template: '<div><slot /></div>' },
  DialogFooter: { template: '<div><slot /></div>' },
  DialogTitle: { template: '<div><slot /></div>' },
  DialogDescription: { template: '<div><slot /></div>' },
};

describe('CreateEntryForm errors', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    // @ts-ignore
    global.fetch = vi.fn();
    document.cookie = 'XSRF-TOKEN=testxsrf; path=/';
  });

  it('requires new journal title when creating a new journal', async () => {
    // fetchTags
    // @ts-ignore
    (global.fetch as any)
      .mockResolvedValueOnce(new Response(JSON.stringify([]), { status: 200, headers: { 'content-type': 'application/json' } }));

    const wrapper = mount(CreateEntryForm, {
      props: { createNewJournal: true },
      global: { stubs },
    });

    // Submit immediately without title
    const form = wrapper.find('form');
    await form.trigger('submit');
    await new Promise((r) => setTimeout(r, 0));

    expect(wrapper.text()).toContain('Journal title is required.');
    // Only fetchTags was called
    expect((global.fetch as any).mock.calls.length).toBe(1);
  });

  it('shows error when update fails in edit mode', async () => {
    // fetchTags then PUT 500
    // @ts-ignore
    (global.fetch as any)
      .mockResolvedValueOnce(new Response(JSON.stringify([]), { status: 200, headers: { 'content-type': 'application/json' } }))
      .mockResolvedValueOnce(new Response('boom', { status: 500, statusText: 'Server Error' }));

    const wrapper = mount(CreateEntryForm, {
      props: { entryToEdit: { id: 'e1', journal_id: 'jid', card_type: 'text', content: 'old' } },
      global: { stubs },
    });

    const ta = wrapper.find('textarea');
    await ta.setValue('new');
    const form = wrapper.find('form');
    await form.trigger('submit');

    await new Promise((r) => setTimeout(r, 0));
    expect(wrapper.text()).toContain('Failed to update entry.');
  });
});

