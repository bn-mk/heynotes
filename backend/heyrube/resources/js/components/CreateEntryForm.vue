<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick, onUnmounted } from 'vue';
import { useJournalStore } from '@/stores/journals';
import MarkdownIt from 'markdown-it';
import { Plus, X, Check, Pin } from 'lucide-vue-next';
import jspreadsheet from 'jspreadsheet-ce';
import LinkEntryButton from '@/components/LinkEntryButton.vue';
import DeleteEntryButton from '@/components/DeleteEntryButton.vue';
import AudioWaveform from '@/components/AudioWaveform.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuRadioGroup, DropdownMenuRadioItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { FileText, ListChecks, Table, AudioLines, Book, Tag as TagIcon, Smile, Image as ImageIcon, Loader2 } from 'lucide-vue-next';

// PROPS & STORE
const props = defineProps({
  entryToEdit: { type: Object, default: null },
  createNewJournal: { type: Boolean, default: false }
});
const emit = defineEmits(['cancel', 'success']);
const journalStore = useJournalStore();
const journals = computed(() => journalStore.journals);

// FORM STATE
const creatingNewJournal = ref(props.createNewJournal);
const selectedJournalId = ref(props.createNewJournal ? 'new' : journalStore.selectedJournalId);
const entryContent = ref('');
const entryContentRef = ref<HTMLTextAreaElement | null>(null);
const entryTitle = ref('');
const newJournalTitle = ref('');
const errors = ref<{ content?: string, title?: string }>({});
const cardType = ref<'text' | 'checkbox' | 'spreadsheet' | 'audio'>('text');
const checkboxItems = ref<Array<{ text: string; checked: boolean }>>([]);
const spreadsheetData = ref('');
// Media (image) upload state
const imageUploading = ref(false);
const imageError = ref('');
const fileInputRef = ref<HTMLInputElement | null>(null);
const imageWidth = ref<string>('auto'); // 'auto', '50%', '100%', '300px', '600px'
// Layout state
const showPreview = ref(false); // mobile preview toggle
const newJournalInputRef = ref<HTMLInputElement | null>(null);
// Audio state
const audioRecorder = ref<any | null>(null);
const audioStream = ref<MediaStream | null>(null);
const audioChunks = ref<Blob[]>([]);
const audioBlob = ref<Blob | null>(null);
const audioUrl = ref<string>('');
const audioUploadedUrl = ref<string>('');
const isRecordingAudio = ref(false);
const audioError = ref<string>('');
const audioSeconds = ref<number>(0);
let audioTimerHandle: number | null = null;
const audioPlayerRef = ref<HTMLAudioElement | null>(null);
const audioMime = ref<string>('');
const audioExt = ref<string>('webm');
const audioPlayerType = computed(() => {
  if (audioMime.value) return audioMime.value;
  const url = audioUrl.value || audioUploadedUrl.value || '';
  if (/\.ogg(\?|$)/i.test(url)) return 'audio/ogg';
  if (/\.(mp3)(\?|$)/i.test(url)) return 'audio/mpeg';
  if (/\.(m4a|mp4)(\?|$)/i.test(url)) return 'audio/mp4';
  if (/\.wav(\?|$)/i.test(url)) return 'audio/wav';
  return 'audio/webm';
});
// Mic selection and test meter
const micDevices = ref<Array<{ deviceId: string; label: string }>>([]);
const selectedMicId = ref<string>('');
const micTestStream = ref<MediaStream | null>(null);
let meterAudioCtx: AudioContext | null = null;
let meterAnalyser: AnalyserNode | null = null;
let meterTimer: number | null = null;
const meterLevel = ref<number>(0);
const newCheckboxItem = ref('');
const selectedMood = ref<string>('');
// Tags state
const selectedTags = ref<string[]>([]);
const tagFilter = ref('');
const canAddTag = computed(() => {
  const name = tagFilter.value.trim();
  if (!name) return false;
  const lower = name.toLowerCase();
  return !journalStore.allTags.some(t => t.toLowerCase() === lower);
});
const isSubmitting = ref(false);

const spreadsheetContainer = ref<HTMLDivElement | null>(null);
const isPinned = ref<boolean>(!!props.entryToEdit?.pinned);
let spreadsheetInstance: any = null;
let darkModeObserver: MutationObserver | null = null;
let sheetResizeObserver: ResizeObserver | null = null;

watch(cardType, async (newType, oldType) => {
  if (oldType === 'spreadsheet' && spreadsheetInstance) {
    spreadsheetInstance.destroy();
    spreadsheetInstance = null;
  }

  if (newType === 'audio' && micDevices.value.length === 0) {
    // Lazy-load mic devices after permission
    try { await loadMicDevices(); } catch {}
  }

  if (newType === 'spreadsheet') {
    await nextTick();
    if (spreadsheetContainer.value) {
      const isDarkMode = document.documentElement.classList.contains('dark');
      if (isDarkMode) {
        spreadsheetContainer.value.classList.add('jspreadsheet_dark');
      } else {
        spreadsheetContainer.value.classList.remove('jspreadsheet_dark');
      }

      try {
        const initialData = spreadsheetData.value.trim() ? JSON.parse(spreadsheetData.value) : [[]];
        const colCount = 10;
        const containerWidth = spreadsheetContainer.value.clientWidth || 1200;
        const colWidth = Math.max(60, Math.floor(containerWidth / colCount));
        const columns = Array.from({ length: colCount }, () => ({ width: colWidth }));

        spreadsheetInstance = jspreadsheet(spreadsheetContainer.value, {
          data: initialData,
          minDimensions: [colCount, 10],
          tableOverflow: true,
          tableWidth: '100%',
          tableHeight: '400px',
          allowInsertColumn: true,
          allowInsertRow: true,
          allowDeleteColumn: true,
          allowDeleteRow: true,
          columns,
        });
        // Ensure widths are applied after initial paint
        updateSpreadsheetColWidths();
      } catch (e) {
        console.error('Failed to parse spreadsheet data:', e);
        const colCount = 10;
        const containerWidth = spreadsheetContainer.value?.clientWidth || 1200;
        const colWidth = Math.max(60, Math.floor(containerWidth / colCount));
        const columns = Array.from({ length: colCount }, () => ({ width: colWidth }));
        spreadsheetInstance = jspreadsheet(spreadsheetContainer.value!, {
          data: [[]],
          minDimensions: [colCount, 10],
          tableOverflow: true,
          tableWidth: '100%',
          tableHeight: '400px',
          allowInsertColumn: true,
          allowInsertRow: true,
          allowDeleteColumn: true,
          allowDeleteRow: true,
          columns,
        });
        updateSpreadsheetColWidths();
      }
    }
  }
});

async function startRecording() {
  try {
    audioError.value = '';
    audioSeconds.value = 0;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      audioError.value = 'Audio recording not supported in this browser.';
      return;
    }
    // Stop mic test to free device if running
    stopMicTest();
    const constraints: any = { audio: selectedMicId.value ? { deviceId: { exact: selectedMicId.value }, echoCancellation: true, noiseSuppression: true, autoGainControl: true } : true };
    const stream = await navigator.mediaDevices.getUserMedia(constraints);
    audioStream.value = stream;

    // Pick the best supported MIME type; gracefully fallback if constructor rejects
    const MR: any = (window as any).MediaRecorder;
    const candidates = [
      // Prefer video/webm so backend MIME guessing maps to extension 'webm' (avoids 'weba')
      'video/webm;codecs=opus',
      'video/webm',
      'audio/webm;codecs=opus',
      'audio/webm',
      'audio/ogg;codecs=opus',
      'audio/ogg',
      'audio/mp4',
      'audio/mpeg',
      'audio/wav',
    ];
    let chosen: string | undefined;
    if (MR && typeof MR.isTypeSupported === 'function') {
      for (const c of candidates) {
        try { if (MR.isTypeSupported(c)) { chosen = c; break; } } catch {}
      }
    }
    // Map extension for the chosen type
    audioMime.value = chosen || 'video/webm';
    if (audioMime.value.includes('ogg')) audioExt.value = 'ogg';
    else if (audioMime.value.includes('mp4')) audioExt.value = 'm4a';
    else if (audioMime.value.includes('mpeg')) audioExt.value = 'mp3';
    else if (audioMime.value.includes('wav')) audioExt.value = 'wav';
    else audioExt.value = 'webm';

    let rec: any;
    try {
      rec = chosen ? new MR(stream, { mimeType: chosen }) : new MR(stream);
    } catch (err) {
      // Fallback without mimeType if constructor rejects
      try { rec = new MR(stream); chosen = rec.mimeType || undefined; } catch (err2) {
        console.error('MediaRecorder not supported for this stream', err2);
        audioError.value = 'Recording is not supported in this browser.';
        try { stream.getTracks().forEach(t => t.stop()); } catch {}
        return;
      }
    }

    audioRecorder.value = rec;
    audioChunks.value = [];
    rec.ondataavailable = (e: any) => {
      if (e.data && e.data.size > 0) audioChunks.value.push(e.data);
    };
    rec.onstop = () => {
      let type = (rec && rec.mimeType) || audioMime.value || 'video/webm';
      // Normalize to server-friendly MIME where needed
      if (/^audio\/webm/i.test(type)) type = 'video/webm';
      audioBlob.value = new Blob(audioChunks.value, { type });
      if (audioUrl.value) {
        try { URL.revokeObjectURL(audioUrl.value); } catch {}
      }
      audioUrl.value = URL.createObjectURL(audioBlob.value);
      isRecordingAudio.value = false;
      if (audioTimerHandle) { window.clearInterval(audioTimerHandle); audioTimerHandle = null; }
    };
    rec.onerror = (ev: any) => { console.error('MediaRecorder error', ev?.error || ev); };
    // Use timeslice so ondataavailable fires during recording across browsers
    rec.start(100);
    isRecordingAudio.value = true;
    if (audioTimerHandle) { window.clearInterval(audioTimerHandle); }
    audioTimerHandle = window.setInterval(() => { audioSeconds.value += 1; }, 1000);
  } catch (e: any) {
    console.error('startRecording failed', e);
    audioError.value = e?.message || 'Microphone permission denied or unavailable.';
    isRecordingAudio.value = false;
  }
}

function stopRecording() {
  try {
    // Request final data chunk before stopping when supported
    try { (audioRecorder.value as any)?.requestData?.(); } catch {}
    audioRecorder.value?.stop();
    audioStream.value?.getTracks().forEach(t => t.stop());
    audioStream.value = null;
  } catch {}
}

async function stopRecordingAndWait(): Promise<void> {
  return new Promise<void>((resolve) => {
    try {
      const rec: any = audioRecorder.value;
      if (!rec) { resolve(); return; }
      const onStopped = () => { try { rec.removeEventListener('stop', onStopped); } catch {} resolve(); };
      try { rec.addEventListener('stop', onStopped, { once: true } as any); } catch { /* older browsers */ }
      try { rec.requestData?.(); } catch {}
      try { rec.stop(); } catch {}
      try { audioStream.value?.getTracks().forEach(t => t.stop()); audioStream.value = null; } catch {}
    } catch { resolve(); }
  });
}

function discardAudio() {
  try { if (audioUrl.value) URL.revokeObjectURL(audioUrl.value); } catch {}
  audioUrl.value = '';
  audioBlob.value = null;
  audioUploadedUrl.value = '';
  audioSeconds.value = 0;
}

async function loadMicDevices() {
  if (!navigator.mediaDevices?.enumerateDevices) return;
  try {
    // Request permission to reveal device labels
    const temp = await navigator.mediaDevices.getUserMedia({ audio: true });
    const list = await navigator.mediaDevices.enumerateDevices();
    micDevices.value = list.filter(d => d.kind === 'audioinput').map(d => ({ deviceId: d.deviceId, label: d.label || 'Microphone' }));
    if (!selectedMicId.value && micDevices.value.length > 0) {
      selectedMicId.value = micDevices.value[0].deviceId;
    }
    temp.getTracks().forEach(t => t.stop());
  } catch (e) {
    audioError.value = 'Unable to access microphones.';
  }
}

async function startMicTest() {
  try {
    stopMicTest();
    const constraints: any = { audio: selectedMicId.value ? { deviceId: { exact: selectedMicId.value }, echoCancellation: true, noiseSuppression: true, autoGainControl: true } : true };
    const stream = await navigator.mediaDevices.getUserMedia(constraints);
    micTestStream.value = stream;
    meterAudioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
    await meterAudioCtx.resume();
    const source = meterAudioCtx.createMediaStreamSource(stream);
    meterAnalyser = meterAudioCtx.createAnalyser();
    meterAnalyser.fftSize = 2048;
    source.connect(meterAnalyser);
    const data = new Uint8Array(meterAnalyser.fftSize);
    meterTimer = window.setInterval(() => {
      if (!meterAnalyser) return;
      meterAnalyser.getByteTimeDomainData(data);
      let sum = 0;
      for (let i = 0; i < data.length; i++) {
        const v = (data[i] - 128) / 128;
        sum += v * v;
      }
      const rms = Math.sqrt(sum / data.length);
      meterLevel.value = Math.min(1, rms * 2.5);
    }, 100) as unknown as number;
  } catch (e) {
    audioError.value = 'Mic test failed. Check device permissions.';
  }
}

function stopMicTest() {
  try {
    if (meterTimer) { window.clearInterval(meterTimer); meterTimer = null; }
    if (meterAnalyser) { try { meterAnalyser.disconnect(); } catch {} meterAnalyser = null; }
    if (meterAudioCtx) { try { meterAudioCtx.close(); } catch {} meterAudioCtx = null; }
    if (micTestStream.value) { micTestStream.value.getTracks().forEach(t => t.stop()); micTestStream.value = null; }
    meterLevel.value = 0;
  } catch {}
}

async function uploadAudio(): Promise<boolean> {
  if (!audioBlob.value) return false;
  const xsrf = getCookie('XSRF-TOKEN') ?? '';
  const fd = new FormData();
  const filename = `recording.${audioExt.value || 'webm'}`;
  fd.append('audio', audioBlob.value, filename);
  try {
    const res = await fetch('/api/media/audio', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'X-XSRF-TOKEN': xsrf,
        'Accept': 'application/json',
      },
      body: fd,
    });
    if (!res.ok) {
      const text = await res.text();
      console.error('Audio upload failed', res.status, text);
      audioError.value = 'Audio upload failed.';
      return false;
    }
    const data = await res.json();
    audioUploadedUrl.value = data.url;
    return true;
  } catch (e) {
    audioError.value = 'Audio upload failed.';
    return false;
  }
}


// When submitting, get data from spreadsheet
async function beforeSubmit() {
  if (cardType.value === 'spreadsheet' && spreadsheetInstance) {
    spreadsheetData.value = JSON.stringify(spreadsheetInstance.getData());
  }
  if (cardType.value === 'audio') {
    if (isRecordingAudio.value) {
      // ensure we stop recording and wait for final data chunk
      await stopRecordingAndWait();
    }
    if (audioBlob.value && !audioUploadedUrl.value) {
      const ok = await uploadAudio();
      if (!ok) return; // abort submit on failure
    }
  }
  await submit();
}
const moods = [
  { emoji: '😊', label: 'Happy', value: 'happy' },
  { emoji: '😔', label: 'Sad', value: 'sad' },
  { emoji: '😴', label: 'Tired', value: 'tired' },
  { emoji: '😡', label: 'Angry', value: 'angry' },
  { emoji: '😰', label: 'Anxious', value: 'anxious' },
  { emoji: '🤗', label: 'Grateful', value: 'grateful' },
  { emoji: '😌', label: 'Calm', value: 'calm' },
  { emoji: '🤔', label: 'Thoughtful', value: 'thoughtful' },
  { emoji: '😎', label: 'Confident', value: 'confident' },
  { emoji: '😅', label: 'Stressed', value: 'stressed' },
  { emoji: '🥰', label: 'Loved', value: 'loved' },
  { emoji: '😐', label: 'Neutral', value: 'neutral' },
];
const selectedMoodDef = computed(() => moods.find(m => m.value === selectedMood.value) || null);

// MARKDOWN
const md = new MarkdownIt();
// Enhance image rendering to support URL fragments like #w=300 or #w=50%
(function enhanceMarkdownImages(mdInst: any) {
  const orig = mdInst.renderer.rules.image;
  mdInst.renderer.rules.image = function (tokens: any[], idx: number, options: any, env: any, self: any) {
    const token = tokens[idx];
    let src = token.attrGet('src') || '';
    let width: string | null = null;
    let height: string | null = null;
    const hashIndex = src.indexOf('#');
    if (hashIndex >= 0) {
      const frag = src.slice(hashIndex + 1);
      src = src.slice(0, hashIndex);
      // parse w=...,h=... separated by & or ; or ,
      const parts = frag.split(/[&;,]/);
      for (const p of parts) {
        const [k, v] = p.split('=');
        if (k === 'w' && v) width = decodeURIComponent(v);
        if (k === 'h' && v) height = decodeURIComponent(v);
      }
      token.attrSet('src', src);
    }
    const styles: string[] = ['max-width:100%', 'height:auto'];
    if (width) {
      let w = width.trim();
      if (/^\d+$/.test(w)) w = w + 'px';
      styles.push('width:' + w);
    }
    if (height) {
      let h = height.trim();
      if (/^\d+$/.test(h)) h = h + 'px';
      styles.push('height:' + h);
    }
    const existing = token.attrGet('style');
    token.attrSet('style', existing ? existing + '; ' + styles.join('; ') : styles.join('; '));
    return (orig || self.renderToken).call(this, tokens, idx, options, env, self);
  };
})(md);
const renderedMarkdown = computed(() => md.render(entryContent.value || ''));

// Prefill form for editing
defaultPrefill();
function defaultPrefill() {
  if (props.entryToEdit) {
    selectedJournalId.value = props.entryToEdit.journal_id || journalStore.selectedJournalId || '';
    cardType.value = props.entryToEdit.card_type || 'text';
    entryTitle.value = props.entryToEdit.title || '';
    selectedMood.value = props.entryToEdit.mood || '';
    isPinned.value = !!props.entryToEdit.pinned;
    if (props.entryToEdit.card_type === 'checkbox' && props.entryToEdit.checkbox_items) {
      checkboxItems.value = [...props.entryToEdit.checkbox_items];
    } else if (props.entryToEdit.card_type === 'spreadsheet') {
      spreadsheetData.value = props.entryToEdit.content || '';
    } else if (props.entryToEdit.card_type === 'audio') {
      audioUploadedUrl.value = props.entryToEdit.content || '';
      audioUrl.value = audioUploadedUrl.value;
    } else {
      entryContent.value = props.entryToEdit.content || '';
    }
    // Prefill tags for the journal in edit mode as well
    const j = journalStore.journals.find(j => j.id === selectedJournalId.value);
    selectedTags.value = [...(j?.tags || [])];
  } else if (props.createNewJournal) {
    creatingNewJournal.value = true;
    selectedJournalId.value = 'new';
  }
}
watch(() => props.entryToEdit, (val) => { defaultPrefill(); isPinned.value = !!val?.pinned; });
watch(() => props.createNewJournal, (newVal) => {
  if (newVal) {
    creatingNewJournal.value = true;
    selectedJournalId.value = 'new';
  }
});

// Sync selectedTags when journal changes (creation mode)
watch(selectedJournalId, (jid) => {
  if (jid && jid !== 'new') {
    const j = journalStore.journals.find(j => j.id === jid);
    selectedTags.value = [...(j?.tags || [])];
  } else if (jid === 'new') {
    selectedTags.value = [];
  }
});

onMounted(() => {
  // Ensure tags list is loaded
  journalStore.fetchTags?.();
  // Prefill tags from selected journal if any
  if (selectedJournalId.value && selectedJournalId.value !== 'new') {
    const j = journalStore.journals.find(j => j.id === selectedJournalId.value);
    selectedTags.value = [...(j?.tags || [])];
  }

  darkModeObserver = new MutationObserver(() => {
    if (spreadsheetContainer.value) {
      const isDarkMode = document.documentElement.classList.contains('dark');
      if (isDarkMode) {
        spreadsheetContainer.value.classList.add('jspreadsheet_dark');
      } else {
        spreadsheetContainer.value.classList.remove('jspreadsheet_dark');
      }
    }
  });

  darkModeObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
  });

  // Observe spreadsheet container resize to keep columns filling container
  if ('ResizeObserver' in window && spreadsheetContainer.value) {
    sheetResizeObserver = new ResizeObserver(() => {
      updateSpreadsheetColWidths();
    });
    sheetResizeObserver.observe(spreadsheetContainer.value);
  }

  // Also handle window resize as a fallback
  window.addEventListener('resize', updateSpreadsheetColWidths);
});

onUnmounted(() => {
  if (darkModeObserver) {
    darkModeObserver.disconnect();
  }
  if (sheetResizeObserver) {
    try { sheetResizeObserver.disconnect(); } catch {}
    sheetResizeObserver = null;
  }
  window.removeEventListener('resize', updateSpreadsheetColWidths);
  try {
    audioRecorder.value?.stop?.();
  } catch {}
  try {
    audioStream.value?.getTracks().forEach(t => t.stop());
  } catch {}
  stopMicTest();
});

// HELPERS
function getCookie(name: string) {
  const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
  return match ? decodeURIComponent(match[3]) : null;
}

function insertAtCursor(text: string) {
  const el = entryContentRef.value;
  const insertion = text;
  if (!el) {
    // Fallback: append
    entryContent.value += (entryContent.value && !entryContent.value.endsWith('\n') ? '\n' : '') + insertion + '\n';
    nextTick(() => { try { entryContentRef.value?.focus(); } catch {} ; try { refreshImageBubble(); } catch {} });
    return;
  }
  const start = el.selectionStart ?? entryContent.value.length;
  const end = el.selectionEnd ?? entryContent.value.length;
  const before = entryContent.value.slice(0, start);
  const after = entryContent.value.slice(end);
  entryContent.value = before + insertion + after;
  nextTick(() => {
    const pos = (before + insertion).length;
    try { el.setSelectionRange(pos, pos); el.focus(); } catch {}
    try { refreshImageBubble(); } catch {}
  });
}

function autoResizeTextarea(el?: HTMLTextAreaElement | null) {
  const target = el || entryContentRef.value;
  if (!target) return;
  try {
    target.style.height = 'auto';
    target.style.height = Math.max(160, target.scrollHeight) + 'px';
  } catch {}
}

async function uploadAndInsertImages(files: FileList | File[]) {
  const xsrf = getCookie('XSRF-TOKEN') ?? '';
  imageError.value = '';
  imageUploading.value = true;
  try {
    const arr = Array.from(files as any as File[]);
    for (const file of arr) {
      if (!file || !file.type?.startsWith('image/')) continue;
      const fd = new FormData();
      fd.append('image', file);
      const res = await fetch('/api/media/image', {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-XSRF-TOKEN': xsrf, 'Accept': 'application/json' },
        body: fd,
      });
      if (!res.ok) {
        const text = await res.text();
        console.error('Image upload failed', res.status, text);
        imageError.value = 'Image upload failed.';
        continue;
      }
      const data = await res.json();
      const url = data.url;
      const baseName = (file.name || '').replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
      const alt = baseName || 'image';
      const widthSuffix = imageWidth.value && imageWidth.value !== 'auto' ? `#w=${encodeURIComponent(imageWidth.value)}` : '';
      const finalUrl = `${url}${widthSuffix}`;
      const md = `![${alt}](${finalUrl})`;
      // Ensure separation by blank line if needed
      const needsNewline = entryContent.value && !entryContent.value.endsWith('\n\n') ? '\n\n' : '';
      insertAtCursor(needsNewline + md + '\n');
    }
    // Ensure bubble visibility reflects new caret position
    await nextTick();
    try { refreshImageBubble(); } catch {}
  } catch (e) {
    imageError.value = 'Image upload failed.';
  } finally {
    imageUploading.value = false;
  }
}

function onClickPickImage(e?: Event) {
  try {
    e?.preventDefault?.();
    e?.stopPropagation?.();
  } catch {}
  try { fileInputRef.value?.click(); } catch {}
}
function onFilesSelected(e: Event) {
  const input = e.target as HTMLInputElement;
  if (input?.files?.length) {
    uploadAndInsertImages(input.files);
  }
  // reset input so the same file can be reselected
  if (input) input.value = '';
}
function onTextAreaDrop(e: DragEvent) {
  e.preventDefault();
  const files = e.dataTransfer?.files;
  if (files && files.length) {
    uploadAndInsertImages(files);
  }
}
function onTextAreaDragOver(e: DragEvent) {
  e.preventDefault();
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
}
async function onTextAreaPaste(e: ClipboardEvent) {
  const items = e.clipboardData?.items;
  if (!items) return;
  const imgs: File[] = [];
  for (const item of Array.from(items)) {
    if (item.kind === 'file') {
      const f = item.getAsFile();
      if (f && f.type.startsWith('image/')) imgs.push(f);
    }
  }
  if (imgs.length) {
    e.preventDefault();
    await uploadAndInsertImages(imgs);
  }
}

// Contextual image width bubble helpers
const imageBubbleVisible = ref(false);
const bubbleLineStart = ref(0);
const bubbleLineEnd = ref(0);
const bubbleCurrentWidth = ref<string | null>(null);

function getCaretLineBounds(): { start: number, end: number, text: string } {
  const el = entryContentRef.value as HTMLTextAreaElement | null;
  const s = el?.selectionStart ?? 0;
  const text = entryContent.value || '';
  const start = Math.max(0, text.lastIndexOf('\n', Math.max(0, s - 1)) + 1);
  let end = text.indexOf('\n', s);
  if (end === -1) end = text.length;
  return { start, end, text: text.slice(start, end) };
}

function findMarkdownImageInside(line: string): string | null {
  const m = line.match(/!\[[^\]]*]\(([^)]*)\)/);
  return m ? m[1] : null;
}

function splitInsideToUrlAndTitle(inside: string): { url: string, title: string } {
  let url = inside.trim();
  let title = '';
  const quoteIdx = inside.indexOf('"');
  if (quoteIdx >= 0) {
    const before = inside.slice(0, quoteIdx).trimEnd();
    url = before.trim();
    title = inside.slice(quoteIdx).trim();
  } else {
    const spaceIdx = inside.indexOf(' ');
    if (spaceIdx > 0) {
      url = inside.slice(0, spaceIdx).trim();
      title = inside.slice(spaceIdx).trim();
    }
  }
  return { url, title };
}

function parseWidthFromUrl(url: string): string | null {
  const hashIdx = url.indexOf('#');
  if (hashIdx < 0) return null;
  const frag = url.slice(hashIdx + 1);
  const parts = frag.split(/[&;,]/);
  for (const p of parts) {
    const [k, v] = p.split('=');
    if (k === 'w' && v) return decodeURIComponent(v);
  }
  return null;
}

function setWidthOnUrl(url: string, width: string | null): string {
  const hashIdx = url.indexOf('#');
  const base = hashIdx >= 0 ? url.slice(0, hashIdx) : url;
  const frag = hashIdx >= 0 ? url.slice(hashIdx + 1) : '';
  const map: Record<string, string> = {};
  if (frag) {
    for (const p of frag.split(/[&;,]/)) {
      const [k, v] = p.split('=');
      if (k && v) map[k] = v;
    }
  }
  if (width && width !== 'auto') {
    map['w'] = encodeURIComponent(width);
  } else {
    delete map['w'];
  }
  const newFrag = Object.keys(map).map(k => `${k}=${map[k]}`).join('&');
  return newFrag ? `${base}#${newFrag}` : base;
}

function updateImageWidthInLine(line: string, newWidth: string | null): string {
  const m = line.match(/(!\[[^\]]*]\()([^)]*)(\))/);
  if (!m) return line;
  const before = m[1];
  const inside = m[2];
  const after = m[3];
  const parts = splitInsideToUrlAndTitle(inside);
  const newUrl = setWidthOnUrl(parts.url, newWidth);
  const newSegment = before + newUrl + (parts.title ? ' ' + parts.title : '') + after;
  return line.replace(m[0], newSegment);
}

function refreshImageBubble() {
  const el = entryContentRef.value as HTMLTextAreaElement | null;
  const s = el?.selectionStart ?? 0;
  const full = entryContent.value || '';
  const { start, end, text } = getCaretLineBounds();
  let inside = findMarkdownImageInside(text);
  // If caret is on a blank line right after insertion, inspect previous line
  if (!inside) {
    const prevEnd = start > 0 ? start - 1 : 0;
    const prevStart = Math.max(0, full.lastIndexOf('\n', Math.max(0, prevEnd - 1)) + 1);
    const prevText = full.slice(prevStart, prevEnd);
    const maybe = findMarkdownImageInside(prevText);
    if (maybe) {
      inside = maybe;
      bubbleLineStart.value = prevStart;
      bubbleLineEnd.value = prevEnd;
    } else {
      imageBubbleVisible.value = false;
      return;
    }
  } else {
    bubbleLineStart.value = start;
    bubbleLineEnd.value = end;
  }
  const parts = splitInsideToUrlAndTitle(inside);
  bubbleCurrentWidth.value = parseWidthFromUrl(parts.url);
  imageBubbleVisible.value = true;
}

function setImageWidthPreset(preset: string) {
  const width = preset === 'auto' ? null : preset;
  const start = bubbleLineStart.value;
  const end = bubbleLineEnd.value;
  const full = entryContent.value || '';
  const line = full.slice(start, end);
  const updated = updateImageWidthInLine(line, width);
  entryContent.value = full.slice(0, start) + updated + full.slice(end);
  bubbleCurrentWidth.value = width;
  nextTick(() => refreshImageBubble());
}

async function addNewTag() {
  const name = tagFilter.value.trim();
  if (!name) return;
  const created = await journalStore.createTag(name);
  if (created) {
    if (!selectedTags.value.includes(created)) {
      selectedTags.value.push(created);
    }
    // Clear the filter so the full list is visible again
    tagFilter.value = '';
  }
}

function startCreateNewJournal() {
  creatingNewJournal.value = true;
  selectedJournalId.value = 'new';
  nextTick(() => {
    try { newJournalInputRef.value?.focus(); } catch {}
  });
}

function updateSpreadsheetColWidths() {
  try {
    if (!spreadsheetInstance || !spreadsheetContainer.value) return;
    const colCount = (spreadsheetInstance.options?.columns?.length) || 10;
    const containerWidth = spreadsheetContainer.value.clientWidth || 1200;
    const colWidth = Math.max(60, Math.floor(containerWidth / colCount));
    for (let i = 0; i < colCount; i++) {
      try {
        // jSpreadsheet API: setWidth(columnIndex, width)
        spreadsheetInstance.setWidth?.(i, colWidth);
      } catch {}
      if (spreadsheetInstance.options?.columns?.[i]) {
        spreadsheetInstance.options.columns[i].width = colWidth;
      }
    }
    try { spreadsheetInstance.refresh?.(); } catch {}
  } catch {}
}

function authHeaders() {
  return { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') ?? '' };
}
function isEditMode() {
  return !!props.entryToEdit;
}

async function togglePinInForm() {
  if (!isEditMode()) return;
  const desired = !isPinned.value;
  const xsrf = getCookie('XSRF-TOKEN') ?? '';
  const jid = selectedJournalId.value || journalStore.selectedJournalId;
  if (!jid) return;
  try {
    await fetch(`/api/journals/${jid}/entries/${props.entryToEdit.id}/pin`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': xsrf,
      },
      body: JSON.stringify({ pinned: desired }),
    });
    isPinned.value = desired;
    // Update store so dashboard re-renders behind the form
    const journal = journalStore.journals.find(j => j.id === jid);
    if (journal && journal.entries) {
      const entry = journal.entries.find((e: any) => e.id === props.entryToEdit!.id);
      if (entry) entry.pinned = desired;
      const unpinned = journal.entries.filter((e: any) => !e.pinned);
      unpinned.forEach((e: any, idx: number) => { e.display_order = idx; });
    }
  } catch (e) {
    // noop
  }
}

// UI LABELS
const formTitle = computed(() => {
  if (isEditMode()) return 'Edit Journal Entry';
  if (creatingNewJournal.value) return 'Create New Journal';
  return 'New Journal Entry';
});
const buttonLabel = computed(() => {
  if (isEditMode()) return 'Save Changes';
  if (creatingNewJournal.value && !hasContent()) return 'Create Journal';
  return 'Save Entry';
});

function hasContent(): boolean {
  if (cardType.value === 'text') {
    return entryContent.value.trim().length > 0;
  } else if (cardType.value === 'checkbox') {
    return checkboxItems.value.length > 0;
  } else if (cardType.value === 'spreadsheet') {
    return spreadsheetData.value.trim().length > 0;
  } else if (cardType.value === 'audio') {
    return !!audioUploadedUrl.value || !!audioBlob.value;
  }
  return false;
}
const discardLabel = computed(() => isEditMode() ? 'Cancel' : 'Discard');

// FORM ACTIONS
async function handleJournalChange(event: Event) {
  const value = (event.target as HTMLSelectElement).value;
  if (value === 'new') {
    creatingNewJournal.value = true;
  } else {
    creatingNewJournal.value = false;
    selectedJournalId.value = value;
  }
}
function cancel() {
  if (isEditMode()) {
    emit('cancel');
  } else {
    journalStore.stopCreatingEntry();
  }
}
async function submit() {
  if (isSubmitting.value) return;
  
  isSubmitting.value = true;
  errors.value = {};
  let journalId = selectedJournalId.value;

  if (!isEditMode() && creatingNewJournal.value) {
    if (!newJournalTitle.value.trim()) {
      errors.value.title = 'Journal title is required.';
      isSubmitting.value = false;
      return;
    }
    try {
      const response = await fetch('/api/journals', {
        method: 'POST', 
        credentials: 'include', 
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') ?? '',
        },
        body: JSON.stringify({ title: newJournalTitle.value, tags: selectedTags.value })
      });
      
      const contentType = response.headers.get('content-type');
      
      if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`Journal create failed: ${response.status}`);
      }
      
      // Check if response is JSON
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('Server returned non-JSON response');
      }
      
      const journal = await response.json();
      journalId = journal.id || journal._id;
      
      // Add the new journal to the store
      journalStore.journals.push({
        ...journal,
        id: journalId,
        entries: [],
      });
      
      // If no entry content, just select the new journal and exit
      if (!hasContent()) {
        journalStore.selectJournal(journalId);
        journalStore.stopCreatingEntry();
        isSubmitting.value = false;
        return;
      }
    } catch (e) {
      console.error('Journal creation error:', e);
      errors.value.title = 'Could not create journal.';
      isSubmitting.value = false;
      return;
    }
  }
  
  // Only require entry content if not creating a new journal or if content exists
  if (!creatingNewJournal.value && !hasContent()) {
    errors.value.content = cardType.value === 'checkbox' ? 'At least one checklist item is required.' : 'Entry content is required.';
    isSubmitting.value = false;
    return;
  }
  
  // If we have entry content, create the entry
  if (hasContent()) {
    try {
      const entryPayload: any = {
        card_type: cardType.value,
        journal_id: journalId,
        title: entryTitle.value || null,
        mood: selectedMood.value || null
      };
      
if (cardType.value === 'text') {
        entryPayload.content = entryContent.value;
      } else if (cardType.value === 'checkbox') {
        entryPayload.checkbox_items = checkboxItems.value;
      } else if (cardType.value === 'spreadsheet') {
        entryPayload.content = spreadsheetData.value;
      } else if (cardType.value === 'audio') {
        entryPayload.content = audioUploadedUrl.value || '';
      }
      
      // Update journal tags if changed (for existing journals)
      if (journalId && journalId !== 'new') {
        const current = journalStore.journals.find(j => j.id === journalId)?.tags || [];
        const a = [...current].sort();
        const b = [...selectedTags.value].sort();
        if (JSON.stringify(a) !== JSON.stringify(b)) {
          await journalStore.updateJournalTags(journalId, selectedTags.value);
        }
      }

      if (isEditMode()) {
        // EDIT: PUT
        const response = await fetch(`/api/journals/${journalId}/entries/${props.entryToEdit.id}`, {
          method: 'PUT', 
          credentials: 'include', 
          headers: authHeaders(),
          body: JSON.stringify(entryPayload)
        });
        if (!response.ok) {
          const errorText = await response.text();
          console.error('Entry update failed:', response.status, errorText);
          throw new Error('Entry update failed');
        }
        const updatedEntry = await response.json();
        isSubmitting.value = false;
        emit('success', updatedEntry);
      } else {
        // CREATE: POST
        const createPayload: any = {
          card_type: cardType.value,
          title: entryTitle.value || null,
          mood: selectedMood.value || null
        };
        
if (cardType.value === 'text') {
          createPayload.content = entryContent.value;
        } else if (cardType.value === 'checkbox') {
          createPayload.checkbox_items = checkboxItems.value;
        } else if (cardType.value === 'spreadsheet') {
          createPayload.content = spreadsheetData.value;
        } else if (cardType.value === 'audio') {
          createPayload.content = audioUploadedUrl.value || '';
        }
        
        const response = await fetch(`/api/journals/${journalId}/entries`, {
          method: 'POST', 
          credentials: 'include', 
          headers: authHeaders(),
          body: JSON.stringify(createPayload)
        });
        if (!response.ok) {
          const errorText = await response.text();
          console.error('Entry create failed:', response.status, errorText);
          throw new Error('Entry create failed');
        }
        const newEntry = await response.json();
        const journal = journalStore.journals.find(j => j.id === journalId);
        if (journal) {
          if (!journal.entries) journal.entries = [];
          journal.entries.unshift(newEntry);
          // Update local manual order to reflect visible order
          journal.entries.forEach((e: any, idx: number) => { e.display_order = idx; });
        }
        journalStore.stopCreatingEntry();
        isSubmitting.value = false;
      }
    } catch (e) {
      console.error('Submit error:', e);
      errors.value.content = isEditMode() ? 'Failed to update entry.' : 'Failed to create entry.';
      isSubmitting.value = false;
    }
  } else {
    isSubmitting.value = false;
  }
}

// Checkbox functions
function addCheckboxItem() {
  if (newCheckboxItem.value.trim()) {
    checkboxItems.value.push({ text: newCheckboxItem.value.trim(), checked: false });
    newCheckboxItem.value = '';
  }
}

function removeCheckboxItem(index: number) {
  checkboxItems.value.splice(index, 1);
}

function toggleCheckbox(index: number) {
  checkboxItems.value[index].checked = !checkboxItems.value[index].checked;
}
</script>
<template>
<form class="flex flex-col flex-1 h-full w-full gap-4 rounded-xl p-4 bg-white/60 dark:bg-zinc-900/60" @submit.prevent="beforeSubmit">
    <!-- Sticky header with actions (desktop) -->
    <div class="sticky top-0 z-20 -mx-4 px-4 py-2 bg-white/70 dark:bg-zinc-900/70 backdrop-blur border-b border-zinc-200/60 dark:border-zinc-800/60 flex items-center justify-between">
      <div class="flex items-center gap-1">
        <TooltipProvider :delay-duration="0">
          <!-- Journal dropdown -->
          <Tooltip>
            <TooltipTrigger as-child>
              <div class="inline-flex">
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <button type="button" class="h-8 w-8 inline-flex items-center justify-center rounded hover:bg-zinc-200/60 dark:hover:bg-zinc-800/60" aria-label="Journal">
                      <Book class="h-4 w-4" />
                    </button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="start" class="w-64">
                    <DropdownMenuLabel>Journal</DropdownMenuLabel>
                    <DropdownMenuRadioGroup v-model="selectedJournalId">
                      <DropdownMenuRadioItem v-for="j in journals" :key="j.id" :value="j.id">{{ j.title }}</DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem @click="startCreateNewJournal">+ Create New Journal…</DropdownMenuItem>
                    <div v-if="creatingNewJournal" class="px-2 pb-2">
                      <input ref="newJournalInputRef" class="mt-1 block w-full border rounded px-2 py-1" type="text" v-model="newJournalTitle" placeholder="New Journal Title" />
                      <div v-if="errors.title" class="text-red-500 text-xs mt-1">{{ errors.title }}</div>
                    </div>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </TooltipTrigger>
            <TooltipContent><p>Journal</p></TooltipContent>
          </Tooltip>
          <!-- Card type dropdown -->
        <Tooltip>
            <TooltipTrigger as-child>
              <div class="inline-flex">
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <button type="button" class="h-8 w-8 inline-flex items-center justify-center rounded hover:bg-zinc-200/60 dark:hover:bg-zinc-800/60" aria-label="Type">
                      <FileText v-if="cardType === 'text'" class="h-4 w-4" />
                      <ListChecks v-else-if="cardType === 'checkbox'" class="h-4 w-4" />
                      <Table v-else-if="cardType === 'spreadsheet'" class="h-4 w-4" />
                      <AudioLines v-else class="h-4 w-4" />
                    </button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="start">
                    <DropdownMenuLabel>Type</DropdownMenuLabel>
                    <DropdownMenuRadioGroup v-model="cardType">
                      <DropdownMenuRadioItem value="text"><FileText class="inline mr-1" /> Text</DropdownMenuRadioItem>
                      <DropdownMenuRadioItem value="checkbox"><ListChecks class="inline mr-1" /> Checklist</DropdownMenuRadioItem>
                      <DropdownMenuRadioItem value="spreadsheet"><Table class="inline mr-1" /> Spreadsheet</DropdownMenuRadioItem>
                      <DropdownMenuRadioItem value="audio"><AudioLines class="inline mr-1" /> Audio</DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </TooltipTrigger>
            <TooltipContent><p>Type</p></TooltipContent>
          </Tooltip>
          <!-- Tags dropdown -->
          <Tooltip>
            <TooltipTrigger as-child>
              <div class="inline-flex">
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <button type="button" class="h-8 w-8 inline-flex items-center justify-center rounded hover:bg-zinc-200/60 dark:hover:bg-zinc-800/60" aria-label="Tags">
                      <TagIcon class="h-4 w-4" />
                    </button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="start" class="w-72">
                    <DropdownMenuLabel>Tags</DropdownMenuLabel>
                    <div class="px-2 pb-2">
                      <input type="text" v-model="tagFilter" @keyup.enter.prevent="canAddTag && addNewTag()" placeholder="Filter or add tag..." class="w-full mb-2 px-2 py-1 border rounded" />
                      <div v-if="canAddTag" class="flex items-center justify-between text-xs mb-2">
                        <span class="text-muted-foreground">New tag:</span>
                        <button type="button" class="px-2 py-1 border rounded cursor-pointer dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700" @click="addNewTag">Add "{{ tagFilter.trim() }}"</button>
                      </div>
                      <div class="flex flex-wrap gap-2 max-h-48 overflow-y-auto p-2 border rounded">
                        <label v-for="name in (journalStore.allTags || []).filter(t => !tagFilter || t.toLowerCase().includes(tagFilter.toLowerCase()))" :key="name" class="inline-flex items-center gap-2 text-xs bg-zinc-100 dark:bg-zinc-800 rounded-full px-2 py-1">
                          <input type="checkbox" :value="name" v-model="selectedTags" />
                          <span>{{ name }}</span>
                        </label>
                      </div>
                    </div>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </TooltipTrigger>
            <TooltipContent><p>Tags</p></TooltipContent>
          </Tooltip>
          <!-- Mood dropdown -->
          <Tooltip>
            <TooltipTrigger as-child>
              <div class="inline-flex">
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <button type="button" class="h-8 w-8 inline-flex items-center justify-center rounded hover:bg-zinc-200/60 dark:hover:bg-zinc-800/60" aria-label="Mood">
                      <span v-if="selectedMood && selectedMoodDef" class="text-base leading-none">{{ selectedMoodDef.emoji }}</span>
                      <Smile v-else class="h-4 w-4" />
                    </button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="start" class="w-56">
                    <DropdownMenuLabel>Mood</DropdownMenuLabel>
                    <DropdownMenuRadioGroup v-model="selectedMood">
                      <DropdownMenuRadioItem v-for="m in moods" :key="m.value" :value="m.value">
                        <span class="mr-2">{{ m.emoji }}</span> {{ m.label }}
                      </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem @click="selectedMood = ''">Clear mood</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </TooltipTrigger>
            <TooltipContent><p>Mood</p></TooltipContent>
          </Tooltip>
        </TooltipProvider>
      </div>
      <div class="flex items-center gap-1">
        <!-- Pin/Link/Delete only in edit mode -->
        <button v-if="isEditMode()" type="button" class="h-8 w-8 inline-flex items-center justify-center rounded hover:bg-zinc-200/60 dark:hover:bg-zinc-800/60" :title="isPinned ? 'Unpin' : 'Pin'" @click="togglePinInForm">
          <Pin class="h-4 w-4" :class="isPinned ? 'text-amber-500' : 'text-zinc-500'" />
        </button>
        <LinkEntryButton v-if="props.entryToEdit" :entry-id="String(props.entryToEdit.id)" />
        <DeleteEntryButton v-if="props.entryToEdit" :entry-id="String(props.entryToEdit.id)" :journal-id="selectedJournalId" @deleted="emit('cancel')" />
        <!-- Save/Cancel on desktop -->
        <div class="hidden md:flex items-center gap-2 ml-2">
          <button type="submit" class="bg-blue-600 text-white px-3 py-1.5 rounded cursor-pointer hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" :disabled="isSubmitting">{{ isSubmitting ? 'Saving...' : buttonLabel }}</button>
          <button type="button" @click="cancel" class="px-3 py-1.5 rounded border border-gray-400 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">{{ discardLabel }}</button>
        </div>
      </div>
    </div>

    <!-- Responsive layout: compose + sidebar -->
    <div class="md:grid md:grid-cols-12 md:gap-4 w-full">
      <!-- Compose column -->
      <div class="md:col-span-8">
      

    <!-- Entry Title -->
      <input
        type="text"
        v-model="entryTitle"
        placeholder="Give your entry a title..."
        class="w-full px-2 py-1 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
      />



    
    <!-- Text Entry Content -->
<div v-if="cardType === 'spreadsheet'" ref="spreadsheetContainer" class="w-full border rounded overflow-auto max-h-[400px] dark:border-zinc-700"></div>
    <div v-if="cardType === 'text'">
      <!-- Markdown toolbar -->
      <div class="mb-2 flex items-center gap-2 py-2" @click.stop>
        <TooltipProvider :delay-duration="0">
          <Tooltip>
            <TooltipTrigger as-child>
              <button type="button" @mousedown.stop.prevent @click.stop.prevent="onClickPickImage($event)" aria-label="Insert image" class="h-8 w-8 inline-flex items-center justify-center rounded border hover:bg-zinc-200/60 dark:hover:bg-zinc-800/60">
                <Loader2 v-if="imageUploading" class="h-4 w-4 animate-spin" />
                <ImageIcon v-else class="h-4 w-4" />
              </button>
            </TooltipTrigger>
            <TooltipContent><p>Insert image</p></TooltipContent>
          </Tooltip>
        </TooltipProvider>
        <input ref="fileInputRef" type="file" accept="image/*" class="hidden" multiple @change.stop="onFilesSelected" />
        <span v-if="imageError" class="text-xs text-red-500">{{ imageError }}</span>
      </div>
      <div class="relative">
        <textarea
          ref="entryContentRef"
          class="block w-full border rounded px-2 py-2 min-h-[240px] md:min-h-[320px]"
          v-model="entryContent"
          :placeholder="creatingNewJournal ? 'Write your first journal entry (optional)...' : 'Write your journal entry...'"
          @drop="onTextAreaDrop"
          @dragover.prevent="onTextAreaDragOver"
          @paste="onTextAreaPaste"
          @input="(e: any) => { autoResizeTextarea(e?.target as HTMLTextAreaElement); refreshImageBubble(); }"
          @keyup="refreshImageBubble"
          @click="refreshImageBubble"
          @mouseup="refreshImageBubble"
          @focus="refreshImageBubble"
          @blur="imageBubbleVisible = false"
        ></textarea>
        <div v-if="imageBubbleVisible" class="absolute top-2 right-2 z-10 bg-white/95 dark:bg-zinc-900/95 border border-zinc-200 dark:border-zinc-700 shadow-lg rounded-md px-2 py-1 flex items-center gap-1 text-xs">
          <span class="text-zinc-500 mr-1">Image width:</span>
          <button type="button" @click="setImageWidthPreset('auto')" :class="['px-2 py-0.5 rounded-full border', bubbleCurrentWidth ? 'border-zinc-300 text-zinc-600 dark:border-zinc-700' : 'bg-blue-600 text-white border-blue-600']">Auto</button>
          <button type="button" @click="setImageWidthPreset('50%')" :class="['px-2 py-0.5 rounded-full border', bubbleCurrentWidth === '50%' ? 'bg-blue-600 text-white border-blue-600' : 'border-zinc-300 text-zinc-600 dark:border-zinc-700']">50%</button>
          <button type="button" @click="setImageWidthPreset('100%')" :class="['px-2 py-0.5 rounded-full border', bubbleCurrentWidth === '100%' ? 'bg-blue-600 text-white border-blue-600' : 'border-zinc-300 text-zinc-600 dark:border-zinc-700']">100%</button>
          <button type="button" @click="setImageWidthPreset('300px')" :class="['px-2 py-0.5 rounded-full border', bubbleCurrentWidth === '300px' ? 'bg-blue-600 text-white border-blue-600' : 'border-zinc-300 text-zinc-600 dark:border-zinc-700']">300px</button>
          <button type="button" @click="setImageWidthPreset('600px')" :class="['px-2 py-0.5 rounded-full border', bubbleCurrentWidth === '600px' ? 'bg-blue-600 text-white border-blue-600' : 'border-zinc-300 text-zinc-600 dark:border-zinc-700']">600px</button>
        </div>
      </div>
      <div class="mt-2 flex items-center justify-between md:hidden">
        <button type="button" class="px-2 py-1 rounded border bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-xs" @click="showPreview = !showPreview">{{ showPreview ? 'Hide preview' : 'Preview' }}</button>
      </div>
      <div v-if="showPreview" class="mt-6 bg-neutral-900/80 rounded-lg shadow p-3 md:hidden">
        <label class="block font-semibold mb-1 text-sm text-gray-400 dark:text-gray-300">preview</label>
        <div class="prose prose-neutral dark:prose-invert max-w-none" v-html="renderedMarkdown"></div>
      </div>
    </div>
    
    <!-- Checkbox Entry Content -->
    <div v-else-if="cardType === 'checkbox'">
      <div class="mb-4">
        <div class="flex gap-2">
          <input
            v-model="newCheckboxItem"
            @keyup.enter="addCheckboxItem"
            type="text"
            placeholder="Add a checklist item..."
            class="flex-1 border rounded px-2 py-1"
          />
          <button
            type="button"
            @click="addCheckboxItem"
            class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition-colors"
          >
            <Plus class="w-4 h-4" />
          </button>
        </div>
      </div>
      
      <div class="space-y-2 max-h-[300px] overflow-y-auto">
        <div
          v-for="(item, index) in checkboxItems"
          :key="index"
          class="flex items-center gap-2 p-2 bg-gray-100 dark:bg-gray-800 rounded"
        >
          <button
            type="button"
            @click="toggleCheckbox(index)"
            class="flex-shrink-0 w-5 h-5 border-2 rounded flex items-center justify-center transition-colors"
            :class="item.checked 
              ? 'bg-blue-600 border-blue-600' 
              : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600'"
          >
            <Check v-if="item.checked" class="w-3 h-3 text-white" />
          </button>
          <span 
            :class="{ 'line-through text-gray-500': item.checked }"
            class="flex-1"
          >
            {{ item.text }}
          </span>
          <button
            type="button"
            @click="removeCheckboxItem(index)"
            class="text-red-500 hover:text-red-700 transition-colors"
          >
            <X class="w-4 h-4" />
          </button>
        </div>
        <div v-if="checkboxItems.length === 0" class="text-gray-500 text-center py-4">
          No checklist items yet. Add one above!
        </div>
      </div>
    </div>

    <!-- Audio Entry Content -->
    <div v-else-if="cardType === 'audio'">
      <div class="mb-3 flex flex-col gap-2">
        <!-- Mic selection and test -->
        <div class="flex flex-wrap items-center gap-2">
          <select v-model="selectedMicId" class="border rounded px-2 py-1 min-w-48">
            <option v-for="d in micDevices" :key="d.deviceId" :value="d.deviceId">{{ d.label || 'Microphone' }}</option>
          </select>
          <button type="button" class="px-2 py-1 border rounded" @click="loadMicDevices">Refresh</button>
          <button v-if="!micTestStream" type="button" class="px-2 py-1 border rounded" @click="startMicTest">Test mic</button>
          <button v-else type="button" class="px-2 py-1 border rounded" @click="stopMicTest">Stop test</button>
          <div class="flex-1 h-2 bg-gray-300 dark:bg-gray-700 rounded overflow-hidden">
            <div class="h-2 bg-green-500 transition-all" :style="{ width: Math.round(meterLevel * 100) + '%' }"></div>
          </div>
        </div>

        <div v-if="!audioUrl && !audioUploadedUrl" class="flex flex-col gap-2">
          <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white/50 dark:bg-zinc-800/60 p-3">
            <AudioWaveform v-if="isRecordingAudio && audioStream" :stream="audioStream" :height="64" />
            <div class="flex items-center gap-2 mt-2">
              <button type="button" @click="startRecording" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">Start Recording</button>
              <span v-if="isRecordingAudio" class="text-sm text-red-500">Recording... {{ audioSeconds }}s</span>
              <button v-if="isRecordingAudio" type="button" @click="stopRecording" class="px-3 py-1 border rounded">Stop</button>
            </div>
          </div>
        </div>
        <div v-else>
          <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white/50 dark:bg-zinc-800/60 p-3">
            <AudioWaveform :src="audioUrl || audioUploadedUrl" :audio-el="audioPlayerRef" :height="64" />
            <audio controls class="w-full mt-2 dark-audio" ref="audioPlayerRef">
              <source :src="audioUrl || audioUploadedUrl" :type="audioPlayerType" />
            </audio>
            <div class="mt-2 flex gap-2">
              <button type="button" @click="discardAudio" class="px-3 py-1 border rounded">Discard</button>
              <button type="button" @click="startRecording" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">Re-record</button>
            </div>
          </div>
        </div>
        <div v-if="audioError" class="text-red-500 text-sm mt-1">{{ audioError }}</div>
      </div>
    </div>
    
    <div v-if="errors.content" class="text-red-500 text-sm mt-1">{{ errors.content }}</div>
      </div> <!-- end compose column -->

      <!-- Desktop sidebar: preview -->
      <div class="hidden md:block md:col-span-4 space-y-4">
        <!-- Desktop Preview -->
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white/50 dark:bg-zinc-800/60 p-3">
          <label class="block font-semibold mb-1 text-sm text-gray-600 dark:text-gray-300">preview</label>
          <div class="prose prose-neutral dark:prose-invert max-w-none" v-html="renderedMarkdown"></div>
        </div>
      </div>
    </div> <!-- end grid -->


    <!-- Mobile sticky action bar -->
    <div class="md:hidden sticky bottom-0 -mx-4 px-4 py-3 bg-white/80 dark:bg-zinc-900/80 backdrop-blur border-t border-zinc-200/60 dark:border-zinc-800/60 flex items-center justify-end gap-2 pb-[env(safe-area-inset-bottom)]">
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded cursor-pointer hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" :disabled="isSubmitting">{{ isSubmitting ? 'Saving...' : buttonLabel }}</button>
      <button type="button" @click="cancel" class="px-4 py-2 rounded border border-gray-400 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">{{ discardLabel }}</button>
    </div>
  </form>
</template>

<style>
/* Dark style for native audio controls (WebKit-based browsers) */
html.dark .dark-audio::-webkit-media-controls-panel {
  background-color: #0b1220; /* near slate-900 */
}
html.dark .dark-audio::-webkit-media-controls-enclosure {
  border-radius: 8px;
  background-color: transparent;
}
html.dark .dark-audio::-webkit-media-controls-current-time-display,
html.dark .dark-audio::-webkit-media-controls-time-remaining-display {
  color: #e5e7eb; /* zinc-200 */
}
html.dark .dark-audio::-webkit-media-controls-mute-button,
html.dark .dark-audio::-webkit-media-controls-play-button,
html.dark .dark-audio::-webkit-media-controls-timeline,
html.dark .dark-audio::-webkit-media-controls-volume-slider,
html.dark .dark-audio::-webkit-media-controls-seek-back-button,
html.dark .dark-audio::-webkit-media-controls-seek-forward-button {
  filter: invert(0.9) hue-rotate(180deg) saturate(0.8);
}
</style>

