import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import Dashboard from '@/pages/Dashboard.vue'

const AppLayoutStub = { template: '<div><slot /></div>' }
const CardStub = { template: '<div class="masonry-card" v-bind="$attrs"><slot /></div>' }
const ButtonStub = { template: '<button v-bind="$attrs" @click="$emit(\'click\')"><slot /></button>' }
const DialogStub = { template: '<div><slot /></div>' }
const DialogTriggerStub = { template: '<div><slot /></div>' }
const DialogContentStub = { template: '<div><slot /></div>' }
const DialogHeaderStub = { template: '<div><slot /></div>' }
const DialogFooterStub = { template: '<div><slot /></div>' }
const DialogTitleStub = { template: '<div><slot /></div>' }
const DialogDescriptionStub = { template: '<div><slot /></div>' }
const SpreadsheetStub = { template: '<div />' }
const LinkEntryButtonStub = { template: '<button title="Link" />' }
const DeleteEntryButtonStub = { template: '<button title="Delete entry" />' }

// Helper to simulate touch events
function touchPayload(x: number, y: number) {
  return { touches: [{ clientX: x, clientY: y }] }
}

describe('Dashboard swipe-to-delete and undo', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    // @ts-ignore
    global.fetch = vi.fn()
    // fetchTags call on mount returns []
    ;(global.fetch as any).mockResolvedValueOnce(new Response(JSON.stringify([]), { status: 200, headers: { 'content-type': 'application/json' } }))
  })

  it('swipes left to delete and then undo restores', async () => {
    // Spy on store actions via component methods: intercept fetch-based calls by stubbing store functions through global mocks is complex here.
    // We assert visible UI signals (toast + Undo), which are driven by component state after calling store.deleteEntry/restoreEntry.

    const journals = [
      {
        id: 'jid',
        title: 'J',
        tags: [],
        entries: [
          { id: 'e1', journal_id: 'jid', card_type: 'text', content: 'One', created_at: new Date().toISOString(), pinned: false },
          { id: 'e2', journal_id: 'jid', card_type: 'text', content: 'Two', created_at: new Date().toISOString(), pinned: false },
        ],
      },
    ]

    // Stub store network actions to succeed without hitting fetch
    const { useJournalStore } = await import('@/stores/journals')
    const store = useJournalStore()
    vi.spyOn(store, 'deleteEntry').mockResolvedValue(true as any)
    vi.spyOn(store, 'restoreEntry').mockResolvedValue(undefined as any)

    const wrapper = mount(Dashboard, {
      props: { journals },
      global: {
        stubs: {
          AppLayout: AppLayoutStub,
          Card: CardStub,
          Button: ButtonStub,
          Dialog: DialogStub,
          DialogTrigger: DialogTriggerStub,
          DialogContent: DialogContentStub,
          DialogHeader: DialogHeaderStub,
          DialogFooter: DialogFooterStub,
          DialogTitle: DialogTitleStub,
          DialogDescription: DialogDescriptionStub,
          Spreadsheet: SpreadsheetStub,
          LinkEntryButton: LinkEntryButtonStub,
          DeleteEntryButton: DeleteEntryButtonStub,
        },
      },
    })

    // Allow onMounted to run
    await Promise.resolve()
    await new Promise((r) => setTimeout(r, 0))

    const card = wrapper.findAll('.masonry-card')[0]
    expect(card.exists()).toBe(true)

    // Simulate a left swipe (touchstart -> touchmove with dx < -80 -> touchend)
    await card.trigger('touchstart', touchPayload(200, 10))
    await card.trigger('touchmove', touchPayload(100, 10)) // dx = -100
    await card.trigger('touchend')

    // Component shows toast and Undo button
    await new Promise((r) => setTimeout(r, 0))
    expect(wrapper.text()).toContain('Entry moved to trash')
    expect(wrapper.text()).toContain('Undo')

    // Mock restore network call
    ;(global.fetch as any).mockResolvedValueOnce(new Response(JSON.stringify({ entry: { id: 'e1', journal_id: 'jid', content: 'One', card_type: 'text' } }), { status: 200, headers: { 'content-type': 'application/json' } }))

    // Click Undo
    const btns = wrapper.findAll('button')
    const found = btns.find(b => b.text().toLowerCase() === 'undo')
    expect(found).toBeTruthy()
    await found!.trigger('click')

    await new Promise((r) => setTimeout(r, 0))
    expect(wrapper.text()).toContain('Entry restored')
  })
})

