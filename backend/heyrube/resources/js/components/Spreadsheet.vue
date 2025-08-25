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
    const parsedData = JSON.parse(props.data);
    jspreadsheet(spreadsheet.value, {
      data: parsedData,
      columns: [],
      editable: false,
      tableOverflow: true,
      tableWidth: '100%',
      tableHeight: '100%',
    });
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
  <div ref="spreadsheet" class="w-full overflow-auto max-h-96"></div>
</template>

