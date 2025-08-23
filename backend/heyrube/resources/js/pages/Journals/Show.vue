<script setup lang="ts">
import { PropType } from 'vue';
import Card from '@/components/ui/card/Card.vue';

defineProps({
  journal: Object,
  entries: Array
});
</script>

<template>
  <section class="py-8 px-4 mx-auto max-w-6xl">
    <h1 class="text-2xl font-bold mb-6">{{ journal.title }}</h1>

    <div v-if="entries.length === 0" class="text-gray-500 text-center">
      No entries yet.
    </div>

    <!-- Card Grid -->
    <div v-else class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
      <Card v-for="entry in entries" :key="entry.id">
        <div class="p-4 flex flex-col h-full">
          <div class="text-sm text-gray-800 whitespace-normal mb-2">{{ entry.content }}</div>
          <div class="mt-auto text-xs text-gray-400">{{ new Date(entry.created_at).toLocaleString() }}</div>
        </div>
      </Card>
    </div>
  </section>
</template>

