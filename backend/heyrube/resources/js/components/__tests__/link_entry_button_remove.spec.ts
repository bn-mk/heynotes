import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import LinkEntryButton from '@/components/LinkEntryButton.vue'

const stubs = {
  Button: { template: '<button v-bind="$attrs" @click="$emit(\'click\')"><slot /></button>' },
  Dialog: { template: '<div><slot /></div>' },
  DialogTrigger: { template: '<div><slot /></div>' },
  DialogContent: { template: '<div><slot /></div>' },
  DialogHeader: { template: '<div><slot /></div>' },
  DialogFooter: { template: '<div><slot /></div>' },
  DialogTitle: { template: '<div><slot /></div>' },
  DialogDescription: { template: '<div><slot /></div>' },
}

describe('LinkEntryButton remove link flows', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    // @ts-ignore
    global.fetch = vi.fn()
    document.cookie = 'XSRF-TOKEN=abc; path=/'
  })

  it('removes a link successfully', async () => {
    const link = { _id: 'l1', user_id: 'u', source_type: 'entry', source_id: 'e1', target_type: 'journal', target_id: 'j1', label: 'linked to' }
    // loadLinks -> links; delete -> 204
    // @ts-ignore
    ;(global.fetch as any)
      .mockResolvedValueOnce(new Response(JSON.stringify([link]), { status: 200, headers: { 'content-type': 'application/json' } }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))

    const wrapper = mount(LinkEntryButton, { props: { entryId: 'e1' }, global: { stubs } })

    // Open dialog to trigger loadLinks
    ;(wrapper.vm as any).open = true
    await Promise.resolve()
    await new Promise((r) => setTimeout(r, 0))
    await new Promise((r) => setTimeout(r, 0))

    const btn = wrapper.find('button[title="Remove link"]')
    expect(btn.exists()).toBe(true)
    await btn.trigger('click')

    expect(wrapper.text()).toContain('Link removed')
    const calls = (global.fetch as any).mock.calls.map((c: any[]) => String(c[0]))
    expect(calls[0]).toContain('/api/links?')
    expect(calls[1]).toBe('/api/links')
  })

  it('handles 404 on remove by refreshing links', async () => {
    const link = { _id: 'l1', user_id: 'u', source_type: 'entry', source_id: 'e1', target_type: 'journal', target_id: 'j1', label: 'linked to' }
    // loadLinks -> links; delete -> 404; refresh -> []
    // @ts-ignore
    ;(global.fetch as any)
      .mockResolvedValueOnce(new Response(JSON.stringify([link]), { status: 200, headers: { 'content-type': 'application/json' } }))
      .mockResolvedValueOnce(new Response('not found', { status: 404, headers: { 'content-type': 'text/plain' } }))
      .mockResolvedValueOnce(new Response(JSON.stringify([]), { status: 200, headers: { 'content-type': 'application/json' } }))

    const wrapper = mount(LinkEntryButton, { props: { entryId: 'e1' }, global: { stubs } })

    ;(wrapper.vm as any).open = true
    await Promise.resolve()
    await new Promise((r) => setTimeout(r, 0))
    await new Promise((r) => setTimeout(r, 0))

    const btn = wrapper.find('button[title="Remove link"]')
    expect(btn.exists()).toBe(true)
    await btn.trigger('click')

    expect(wrapper.text()).toContain('Link not found')
    const calls = (global.fetch as any).mock.calls.map((c: any[]) => String(c[0]))
    expect(calls.filter((u: string) => u.startsWith('/api/links?')).length).toBe(2)
  })
})

