<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { Link2, Link2Off, Search } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'

const props = defineProps<{ entryId: string }>()
const open = ref(false)
const query = ref('')
const results = ref<{ journals: any[]; entries: any[] }>({ journals: [], entries: [] })
const loading = ref(false)
const selectedTarget = ref<{ id: string; type: 'entry'|'journal'; label: string } | null>(null)
const saving = ref(false)
const errorMsg = ref('')
const successMsg = ref('')
const links = ref<any[]>([])

// Compute which items are already linked
const linkedItems = computed(() => {
  const items = new Set<string>()
  links.value.forEach(link => {
    if (link.source_id === props.entryId) {
      items.add(`${link.target_type}:${link.target_id}`)
    } else {
      items.add(`${link.source_type}:${link.source_id}`)
    }
  })
  return items
})

function isAlreadyLinked(type: string, id: string): boolean {
  return linkedItems.value.has(`${type}:${id}`)
}

function getCookie(name: string) {
  const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'))
  return match ? decodeURIComponent(match[3]) : null
}

async function fetchResults() {
  const q = query.value.trim()
  if (!q) { results.value = { journals: [], entries: [] }; selectedTarget.value = null; return }
  loading.value = true
  errorMsg.value = ''
  successMsg.value = ''
  try {
    const res = await fetch(`/api/search?q=${encodeURIComponent(q)}&limit=10`, { credentials: 'include' })
    if (res.ok) {
      results.value = await res.json()
    }
  } finally {
    loading.value = false
  }
}

watch(query, (_val, _old, onInvalidate) => {
  const t = window.setTimeout(fetchResults, 200)
  onInvalidate(() => window.clearTimeout(t))
})

function chooseTarget(target: { id: string; type: 'entry'|'journal' }, label: string) {
  if (isAlreadyLinked(target.type, target.id)) {
    errorMsg.value = 'This item is already linked'
    successMsg.value = ''
    return
  }
  selectedTarget.value = { id: target.id, type: target.type, label }
  errorMsg.value = ''
  successMsg.value = ''
}

async function loadLinks() {
  try {
    const res = await fetch(`/api/links?node_type=entry&node_id=${encodeURIComponent(props.entryId)}`, { credentials: 'include' })
    if (res.ok) links.value = await res.json()
  } catch {}
}

async function createLink() {
  const payload = {
    source_type: 'entry',
    source_id: props.entryId,
    target_type: selectedTarget.value!.type,
    target_id: selectedTarget.value!.id,
    label: 'linked to',
  }
  const xsrf = getCookie('XSRF-TOKEN') ?? ''
  saving.value = true
  try {
    const res = await fetch('/api/links', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf,
      },
      body: JSON.stringify(payload),
    })
    if (res.status === 201) {
      successMsg.value = 'Link created'
      await loadLinks()
      // prepare for another link
      selectedTarget.value = null
      query.value = ''
      results.value = { journals: [], entries: [] }
    } else if (res.status === 200) {
      successMsg.value = 'Link already exists'
      await loadLinks()
    } else {
      const txt = await res.text()
      errorMsg.value = txt || 'Failed to create link'
    }
  } finally {
    saving.value = false
  }
}
watch(open, async (val) => {
  if (val) {
    errorMsg.value = ''
    successMsg.value = ''
    selectedTarget.value = null
    await loadLinks()
  }
})

async function removeLink(linkId: string) {
  const xsrf = getCookie('XSRF-TOKEN') ?? ''
  // Find the link to get source and target info
  const link = links.value.find(l => l._id === linkId)
  if (!link) return
  
  // Determine source and target based on current entry
  const payload = {
    source_type: link.source_type,
    source_id: link.source_id,
    target_type: link.target_type,
    target_id: link.target_id,
  }
  
  try {
    const res = await fetch('/api/links', {
      method: 'DELETE',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf,
      },
      body: JSON.stringify(payload),
    })
    if (res.status === 204) {
      links.value = links.value.filter(x => x._id !== linkId)
      successMsg.value = 'Link removed'
    } else if (res.status === 404) {
      errorMsg.value = 'Link not found'
      // Refresh links in case it was already deleted
      await loadLinks()
    } else {
      const txt = await res.text()
      errorMsg.value = txt || 'Failed to remove link'
    }
  } catch (e:any) {
    errorMsg.value = 'Failed to remove link'
  }
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <Button type="button" variant="ghost" size="icon" class="h-8 w-8 cursor-pointer" title="Link" @click.stop>
        <Link2 class="h-4 w-4" />
        <span class="sr-only">Link Entry</span>
      </Button>
    </DialogTrigger>
    <DialogContent class="max-w-md">
      <DialogHeader>
        <DialogTitle>Link this entry</DialogTitle>
        <DialogDescription>Select a journal or another entry to link to.</DialogDescription>
      </DialogHeader>
      <div class="space-y-3">
        <div class="relative">
          <input v-model="query" type="text" placeholder="Search journals and entries..." class="w-full px-3 py-2 border rounded pr-8" />
          <Search class="absolute right-2 top-1/2 -translate-y-1/2 h-4 w-4 opacity-60" />
        </div>
        <div v-if="loading" class="text-sm text-muted-foreground">Searching...</div>
        <div v-if="errorMsg" class="text-sm text-destructive">{{ errorMsg }}</div>
        <div v-if="successMsg" class="text-sm text-green-600">{{ successMsg }}</div>
        <div class="max-h-72 overflow-y-auto space-y-2">
          <div v-if="links.length" class="text-xs font-medium text-muted-foreground">Current links</div>
          <div v-for="l in links" :key="l._id" class="flex items-center justify-between px-2 py-1.5 rounded border">
            <div class="text-xs">
              <span class="opacity-70">{{ (l.source_id===props.entryId ? l.target_type : l.source_type) }}:</span>
              <span class="ml-1 font-medium">{{ (l.source_id===props.entryId ? l.target_id : l.source_id).slice(0,8) }}…</span>
              <span class="ml-2 opacity-70">{{ l.label || 'linked to' }}</span>
            </div>
            <Button type="button" variant="ghost" size="icon" class="h-7 w-7 cursor-pointer" title="Remove link" @click="removeLink(l._id)">
              <Link2Off class="h-4 w-4" />
            </Button>
          </div>
          <div v-if="results.journals.length" class="text-xs font-medium text-muted-foreground">Journals</div>
          <button 
            type="button" 
            v-for="j in results.journals" 
            :key="j.id" 
            class="w-full text-left px-2 py-1.5 rounded"
            :class="isAlreadyLinked('journal', j.id) ? 'opacity-50 bg-muted cursor-not-allowed' : 'hover:bg-accent cursor-pointer'"
            @click.stop="chooseTarget({ id: j.id, type: 'journal' }, j.label)">
            <span v-if="isAlreadyLinked('journal', j.id)" class="text-xs text-muted-foreground">(linked) </span>
            {{ j.label }}
          </button>
          <div v-if="results.entries.length" class="mt-2 text-xs font-medium text-muted-foreground">Entries</div>
          <button 
            type="button" 
            v-for="e in results.entries" 
            :key="e.id" 
            class="w-full text-left px-2 py-1.5 rounded"
            :class="isAlreadyLinked('entry', e.id) ? 'opacity-50 bg-muted cursor-not-allowed' : 'hover:bg-accent cursor-pointer'"
            @click.stop="chooseTarget({ id: e.id, type: 'entry' }, e.label)">
            <span v-if="isAlreadyLinked('entry', e.id)" class="text-xs text-muted-foreground">(linked) </span>
            {{ e.label }}
          </button>
        </div>
      </div>
      <DialogFooter class="flex items-center gap-2">
        <div v-if="selectedTarget" class="flex-1 text-sm">Link to: <span class="font-medium">{{ selectedTarget.label }}</span> ({{ selectedTarget.type }})</div>
        <Button type="button" variant="outline" class="cursor-pointer" @click="open = false">Close</Button>
        <Button type="button" :disabled="!selectedTarget || saving" class="cursor-pointer" @click="createLink">{{ saving ? 'Linking...' : 'Confirm link' }}</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
