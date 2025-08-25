<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import jspreadsheet from 'jspreadsheet-ce';

const props = defineProps({
  data: {
    type: String,
    required: true,
  },
});

const spreadsheet = ref<HTMLDivElement | null>(null);
let darkModeObserver: MutationObserver | null = null;

onMounted(() => {
  if (!spreadsheet.value) return;

  try {
    const parsed = JSON.parse(props.data || '[]');
    const src: any[] = Array.isArray(parsed) ? parsed : [];
    // Take first 5 rows and 5 cols, pad to 5x5
    const rows = 5;
    const cols = 5;
    const sliced = src.slice(0, rows).map(r => (Array.isArray(r) ? r.slice(0, cols) : Array(cols).fill('')));
    while (sliced.length < rows) sliced.push(Array(cols).fill(''));
    for (let i = 0; i < rows; i++) {
      while (sliced[i].length < cols) sliced[i].push('');
    }

    jspreadsheet(spreadsheet.value, {
      data: sliced,
      editable: false,
      tableOverflow: false,
      columnDrag: false,
      rowDrag: false,
      defaultColWidth: 72,
      minDimensions: [cols, rows],
      // Hide toolbar and headers for compact dashboard preview
      showToolbar: false,
      showIndex: false,
      showBottomIndex: false,
    });

    // Also remove index column via CSS utility class provided by jspreadsheet
    const table = spreadsheet.value.querySelector('table.jexcel');
    if (table) table.classList.add('jexcel_hidden_index');
  } catch (e) {
    console.error('Failed to parse spreadsheet data on dashboard:', e);
  }

  const root = spreadsheet.value; // jspreadsheet adds classes to the passed element
  const setDark = () => {
    const isDark = document.documentElement.classList.contains('dark');
    root.classList.toggle('jspreadsheet_dark', isDark);
  };
  setDark();

  const obs = new MutationObserver(setDark);
  obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

  darkModeObserver = obs;
});

onUnmounted(() => {
  if (darkModeObserver) darkModeObserver.disconnect();
});

</script>

<template>
  <div class="w-full p-3 overflow-hidden">
    <div ref="spreadsheet" class="inline-block"></div>
  </div>
</template>

<style scoped>
/* Ensure headers are hidden in read-only dashboard preview regardless of plugin defaults */
:deep(.jexcel > thead) {
  display: none !important;
}
:deep(.jexcel > tfoot) {
  display: none !important;
}
:deep(.jexcel > tbody > tr > td:first-child) {
  display: none !important;
}
</style>

