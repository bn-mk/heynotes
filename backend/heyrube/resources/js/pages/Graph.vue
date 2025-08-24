<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'

type Node = { id: string; type: 'entry'|'journal'; label: string }
type Edge = { source: { id: string, type: string }, target: { id: string, type: string }, label: string }

const nodes = ref<Node[]>([])
const edges = ref<Edge[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await fetch('/api/graph', { credentials: 'include' })
    if (res.ok) {
      const data = await res.json()
      nodes.value = data.nodes
      edges.value = data.edges
    }
  } finally {
    loading.value = false
  }
})

function layoutCircle(n: number, r: number, cx: number, cy: number) {
  return Array.from({ length: n }, (_, i) => {
    const angle = (i / n) * Math.PI * 2
    return { x: cx + r * Math.cos(angle), y: cy + r * Math.sin(angle) }
  })
}

const radius = 250, cx = 450, cy = 300
const positions = computed(() => {
  const coords = layoutCircle(nodes.value.length, radius, cx, cy)
  const byId: Record<string, { x: number, y: number }> = {}
  nodes.value.forEach((n, i) => { byId[n.id] = coords[i] })
  return { coords, byId }
})
</script>

<template>
  <AppLayout>
    <div class="p-4">
      <h1 class="text-xl font-bold mb-3">Global Graph</h1>
      <div v-if="loading" class="text-sm text-muted-foreground">Loading graph...</div>
      <div v-else>
        <div class="mb-4 text-sm text-muted-foreground">Nodes: {{ nodes.length }}, Links: {{ edges.length }}</div>
        <div class="w-full overflow-auto border rounded bg-card">
          <svg :width="900" :height="600" class="block">
            <g v-if="nodes.length">
              <template v-for="(e, ei) in edges" :key="ei">
                <line v-if="positions.byId[e.source.id] && positions.byId[e.target.id]"
                      :x1="positions.byId[e.source.id].x"
                      :y1="positions.byId[e.source.id].y"
                      :x2="positions.byId[e.target.id].x"
                      :y2="positions.byId[e.target.id].y"
                      stroke="rgba(150,150,150,0.6)" stroke-width="1" />
              </template>
              <template v-for="(n, ni) in nodes" :key="n.id">
                <g>
                  <circle :cx="positions.coords[ni].x" :cy="positions.coords[ni].y" :r="n.type==='journal'?10:7" :fill="n.type==='journal'?'hsl(220 70% 55%)':'hsl(160 60% 45%)'" />
                  <text :x="positions.coords[ni].x + 12" :y="positions.coords[ni].y + 4" font-size="11" fill="currentColor">
                    {{ n.label }}
                  </text>
                </g>
              </template>
            </g>
          </svg>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
