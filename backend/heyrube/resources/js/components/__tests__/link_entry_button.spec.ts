import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import LinkEntryButton from '@/components/LinkEntryButton.vue'

const stubs = {
  Button: { template: '<button @click="$emit(\'click\')"><slot /></button>' },
  Dialog: { template: '<div><slot /></div>' },
  DialogTrigger: { template: '<div><slot /></div>' },
  DialogContent: { template: '<div><slot /></div>' },
  DialogHeader: { template: '<div><slot /></div>' },
  DialogFooter: { template: '<div><slot /></div>' },
  DialogTitle: { template: '<div><slot /></div>' },
  DialogDescription: { template: '<div><slot /></div>' },
}

describe('LinkEntryButton', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    // @ts-ignore
    global.fetch = vi.fn()
    document.cookie = 'XSRF-TOKEN=abc; path=/'
    vi.useFakeTimers()
  })
  afterEach(() => {
    vi.useRealTimers()
  })

  it('searches by query and creates a link', async () => {
    // 1) Mock search result
    // @ts-ignore
    ;(global.fetch as any)
      .mockResolvedValueOnce(new Response(JSON.stringify({ journals: [{ id: 'j1', label: 'Journal One' }], entries: [] }), { status: 200, headers: { 'content-type': 'application/json' } }))
      // 2) Mock create link
      .mockResolvedValueOnce(new Response(JSON.stringify({ _id: 'link1' }), { status: 201, headers: { 'content-type': 'application/json' } }))
      // 3) Mock loadLinks refresh
      .mockResolvedValueOnce(new Response(JSON.stringify([]), { status: 200, headers: { 'content-type': 'application/json' } }))

    const wrapper = mount(LinkEntryButton, {
      props: { entryId: 'e1' },
      global: { stubs },
    })

    // Type into search input triggers debounce and search
    const input = wrapper.find('input[type="text"]')
    await input.setValue('journal')
    // advance debounce
    await vi.advanceTimersByTimeAsync(250)

    // Click on the result button for the journal
    const resultBtn = wrapper.findAll('button').find(b => b.text().includes('Journal One'))
    expect(resultBtn).toBeTruthy()
    await resultBtn!.trigger('click')

    // Click Confirm link
    const confirmBtn = wrapper.findAll('button').find(b => b.text().includes('Confirm link'))
    expect(confirmBtn).toBeTruthy()
    await confirmBtn!.trigger('click')

    // Expect a success message to appear (created or already exists depending on timing)
    const txt = wrapper.text()
    expect(txt.includes('Link created') || txt.includes('Link already exists')).toBe(true)

    // Verify requests were sent
    const calls = (global.fetch as any).mock.calls.map((c: any[]) => String(c[0]))
    expect(calls.some((u: string) => u.startsWith('/api/search'))).toBe(true)
    expect(calls).toContain('/api/links')
    expect(calls.some((u: string) => u.startsWith('/api/links?'))).toBe(true)
  })
})

