<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch, nextTick } from 'vue';

interface Props {
  src?: string;
  stream?: MediaStream | null;
  audioEl?: HTMLAudioElement | { value?: HTMLAudioElement | null } | null;
  height?: number;
  color?: string;
  progressColor?: string;
  backgroundColor?: string;
}

const props = defineProps<Props>();
const canvasRef = ref<HTMLCanvasElement | null>(null);
let rafId: number | null = null;
let audioCtx: AudioContext | null = null;
let analyser: AnalyserNode | null = null;
let sourceNode: MediaStreamAudioSourceNode | null = null;
let timeData: Uint8Array | null = null;
let resizeObserver: ResizeObserver | null = null;
let parentResizeObserver: ResizeObserver | null = null;
let themeObserver: MutationObserver | null = null;

function isDarkMode() {
  return document.documentElement.classList.contains('dark');
}

const dpr = Math.max(1, Math.floor(window.devicePixelRatio || 1));

function scheduleStaticRender() {
  // Double RAF to ensure layout has settled before measuring widths
  requestAnimationFrame(() => requestAnimationFrame(() => {
    renderStaticWave();
  }));
}

function getAudioElement(): HTMLAudioElement | null {
  const el = (props.audioEl as any)?.value ?? props.audioEl;
  return (el && el instanceof HTMLAudioElement) ? el : null;
}

function clearCanvas(ctx: CanvasRenderingContext2D, w: number, h: number, bg?: string) {
  ctx.clearRect(0, 0, w, h);
  if (bg) {
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, w, h);
  }
}

function drawLive() {
  if (!canvasRef.value || !analyser || !timeData) return;
  const canvas = canvasRef.value;
  const rect = canvas.getBoundingClientRect();
  const parentRect = canvas.parentElement?.getBoundingClientRect();
  const baseWidth = rect.width || parentRect?.width || canvas.clientWidth || 300;
  const width = Math.max(1, Math.floor(baseWidth * dpr));
  const height = Math.max(1, Math.floor((props.height || 64) * dpr));
  if (canvas.width !== width || canvas.height !== height) {
    canvas.width = width;
    canvas.height = height;
    canvas.style.height = `${props.height || 64}px`;
    canvas.style.width = '100%';
  }
  const ctx = canvas.getContext('2d');
  if (!ctx) return;
  analyser.getByteTimeDomainData(timeData);

  clearCanvas(ctx, width, height);

  ctx.lineWidth = Math.max(1, Math.floor(1 * dpr));
  ctx.strokeStyle = props.color || '#64748b';
  ctx.beginPath();

  const sliceWidth = width / timeData.length;
  let x = 0;
  for (let i = 0; i < timeData.length; i++) {
    const v = timeData[i] / 128.0; // 0..2
    const y = (v * height) / 2; // center around midline
    if (i === 0) ctx.moveTo(x, y);
    else ctx.lineTo(x, y);
    x += sliceWidth;
  }
  ctx.stroke();

  rafId = requestAnimationFrame(drawLive);
}

async function startLive() {
  if (!props.stream || !canvasRef.value) return;
  stopLive();
  audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
  try { if (audioCtx.state === 'suspended') await audioCtx.resume(); } catch {}
  sourceNode = audioCtx.createMediaStreamSource(props.stream);
  analyser = audioCtx.createAnalyser();
  analyser.fftSize = 2048;
  sourceNode.connect(analyser);
  timeData = new Uint8Array(analyser.fftSize);
  drawLive();
}

function stopLive() {
  if (rafId) cancelAnimationFrame(rafId);
  rafId = null;
  if (sourceNode) {
    try { sourceNode.disconnect(); } catch {}
    sourceNode = null;
  }
  if (analyser) {
    try { analyser.disconnect(); } catch {}
    analyser = null;
  }
  timeData = null;
  // do not close audioCtx here to avoid affecting other audio; let it be GC'd
}

// Static waveform drawing
let decodedBuffer: AudioBuffer | null = null;
let progressHandlerAttached = false;

async function ensureDecoded(): Promise<void> {
  if (!props.src || decodedBuffer) return;
  try {
    const res = await fetch(props.src);
    const ab = await res.arrayBuffer();
    if (!audioCtx) audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
    decodedBuffer = await new Promise<AudioBuffer>((resolve, reject) => {
      audioCtx!.decodeAudioData(ab, resolve, reject);
    });
  } catch (e) {
    // blob: URLs or CORS could fail; silently ignore to avoid breaking UI
    decodedBuffer = null;
  }
}

function computeMinMaxPeaks(buffer: AudioBuffer, buckets: number): { min: Float32Array, max: Float32Array } {
  const chs = buffer.numberOfChannels;
  const length = buffer.length;
  const samplesPerBucket = Math.max(1, Math.floor(length / buckets));
  const stride = Math.max(1, Math.floor(samplesPerBucket / 64));
  const min = new Float32Array(buckets);
  const max = new Float32Array(buckets);
  for (let b = 0; b < buckets; b++) {
    let start = b * samplesPerBucket;
    let end = Math.min(length, start + samplesPerBucket);
    let mn = 1.0;
    let mx = -1.0;
    for (let i = start; i < end; i += stride) {
      let val = 0;
      for (let c = 0; c < chs; c++) {
        val += buffer.getChannelData(c)[i] || 0;
      }
      val = val / chs; // average channels
      if (val < mn) mn = val;
      if (val > mx) mx = val;
    }
    min[b] = mn;
    max[b] = mx;
  }
  // normalize by global max abs
  let globalMaxAbs = 0;
  for (let i = 0; i < buckets; i++) {
    globalMaxAbs = Math.max(globalMaxAbs, Math.abs(min[i]), Math.abs(max[i]));
  }
  const norm = globalMaxAbs > 0 ? 1 / globalMaxAbs : 1;
  for (let i = 0; i < buckets; i++) { min[i] *= norm; max[i] *= norm; }
  return { min, max };
}

function drawStatic(progress = -1) {
  if (!canvasRef.value) return;
  const canvas = canvasRef.value;
  const rect = canvas.getBoundingClientRect();
  const parentRect = canvas.parentElement?.getBoundingClientRect();
  const baseWidth = rect.width || parentRect?.width || canvas.clientWidth || (canvas.parentElement as HTMLElement | null)?.clientWidth || 300;
  const width = Math.max(1, Math.floor(baseWidth * dpr));
  const heightCss = props.height || 64;
  const height = Math.max(1, Math.floor(heightCss * dpr));
  if (canvas.width !== width || canvas.height !== height) {
    canvas.width = width;
    canvas.height = height;
    canvas.style.height = `${heightCss}px`;
    canvas.style.width = '100%';
  }
  const ctx = canvas.getContext('2d');
  if (!ctx) return;
  clearCanvas(ctx, width, height);

  const dark = isDarkMode();
  const bgBar = props.backgroundColor || (dark ? '#374151' : '#e5e7eb');
  const fg = props.color || (dark ? '#9ca3af' : '#64748b');
  const prog = props.progressColor || (dark ? '#60a5fa' : '#3b82f6');

  if (!decodedBuffer) {
    // Rich placeholder: draw multiple soft bars to indicate waveform area
    const bars = Math.max(24, Math.floor(width / 20));
    const barW = Math.max(2, Math.floor(width / (bars * 1.3)));
    const gap = Math.max(1, Math.floor(barW * 0.3));
    const mid = Math.floor(height / 2);
    for (let i = 0; i < bars; i++) {
      const x = i * (barW + gap);
      // Pseudo-random but stable heights using sine
      const amp = Math.max(4, Math.floor((0.35 + 0.65 * Math.abs(Math.sin(i * 0.7))) * height * 0.6));
      const y = Math.floor(amp / 2);
      ctx.fillStyle = i % 3 === 0 ? bgBar : fg;
      ctx.fillRect(x, mid - y, barW, y * 2);
    }
    if (progress >= 0) {
      const px = Math.floor(progress * width);
      ctx.save();
      ctx.beginPath();
      ctx.rect(0, 0, px, height);
      ctx.clip();
      ctx.fillStyle = prog + 'cc';
      for (let i = 0; i < bars; i++) {
        const x = i * (barW + gap);
        const amp = Math.max(4, Math.floor((0.35 + 0.65 * Math.abs(Math.sin(i * 0.7))) * height * 0.6));
        const y = Math.floor(amp / 2);
        ctx.fillRect(x, mid - y, barW, y * 2);
      }
      ctx.restore();
    }
    return;
  }

  // Use one bucket per device pixel for crispness, cap for perf
  const buckets = Math.min(width, 4000);
  const { min, max } = computeMinMaxPeaks(decodedBuffer, buckets);

  // Draw filled waveform area
  const mid = Math.floor(height / 2);
  const scaleY = height * 0.45; // vertical scale

  // Background area (bgBar)
  ctx.fillStyle = bgBar;
  ctx.beginPath();
  // upper path
  for (let i = 0; i < buckets; i++) {
    const x = Math.floor((i / (buckets - 1)) * (width - 1));
    const y = Math.floor(mid - (max[i] * scaleY));
    if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
  }
  // lower path (reverse)
  for (let i = buckets - 1; i >= 0; i--) {
    const x = Math.floor((i / (buckets - 1)) * (width - 1));
    const y = Math.floor(mid - (min[i] * scaleY));
    ctx.lineTo(x, y);
  }
  ctx.closePath();
  ctx.fill();

  // Foreground stroke for detail
  ctx.strokeStyle = fg;
  ctx.lineWidth = Math.max(1, Math.floor(1 * dpr));
  ctx.beginPath();
  for (let i = 0; i < buckets; i++) {
    const x = Math.floor((i / (buckets - 1)) * (width - 1));
    const y = Math.floor(mid - ((max[i] + min[i]) * 0.5 * scaleY));
    if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
  }
  ctx.stroke();

  // Progress overlay if provided
  if (progress >= 0) {
    const px = Math.floor(progress * width);
    if (px > 0) {
      ctx.save();
      ctx.beginPath();
      ctx.rect(0, 0, px, height);
      ctx.clip();
      // Fill progress area
      ctx.fillStyle = prog;
      ctx.beginPath();
      for (let i = 0; i < buckets; i++) {
        const x = Math.floor((i / (buckets - 1)) * (width - 1));
        const y = Math.floor(mid - (max[i] * scaleY));
        if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
      }
      for (let i = buckets - 1; i >= 0; i--) {
        const x = Math.floor((i / (buckets - 1)) * (width - 1));
        const y = Math.floor(mid - (min[i] * scaleY));
        ctx.lineTo(x, y);
      }
      ctx.closePath();
      ctx.fill();

      // Progress stroke
      ctx.strokeStyle = prog;
      ctx.lineWidth = Math.max(1, Math.floor(1 * dpr));
      ctx.beginPath();
      for (let i = 0; i < buckets; i++) {
        const x = Math.floor((i / (buckets - 1)) * (width - 1));
        const y = Math.floor(mid - ((max[i] + min[i]) * 0.5 * scaleY));
        if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
      }
      ctx.stroke();
      ctx.restore();
    }
  }
}

function attachProgressUpdates() {
  if (progressHandlerAttached) return;
  const el = getAudioElement();
  if (!el) return;
  const handler = () => {
    const ratio = el.duration ? el.currentTime / el.duration : 0;
    drawStatic(ratio);
  };
  el.addEventListener('timeupdate', handler);
  el.addEventListener('seeking', handler);
  el.addEventListener('play', handler);
  el.addEventListener('pause', handler);
  el.addEventListener('loadedmetadata', handler);
  progressHandlerAttached = true;
}

async function renderStaticWave() {
  if (!props.src) return;
  await ensureDecoded();
  drawStatic(getAudioElement() ? 0 : -1);
  attachProgressUpdates();
}


onMounted(async () => {
  if (props.stream) {
    await nextTick();
    startLive();
  } else if (props.src) {
    await nextTick();
    await renderStaticWave();
  }
  if ('ResizeObserver' in window && canvasRef.value) {
    resizeObserver = new ResizeObserver(() => {
      if (props.stream) drawLive();
      else renderStaticWave();
    });
    resizeObserver.observe(canvasRef.value);
    if (canvasRef.value.parentElement) {
      parentResizeObserver = new ResizeObserver(() => {
        if (!props.stream) renderStaticWave();
      });
      parentResizeObserver.observe(canvasRef.value.parentElement);
    }
  }
  // React to theme toggles
  try {
    themeObserver = new MutationObserver(() => {
      if (!props.stream) renderStaticWave();
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
  } catch {}

  // Attempt to resume audio context on first user interaction (autoplay policies)
  const resumeIfNeeded = async () => {
    try { if (audioCtx && audioCtx.state === 'suspended') await audioCtx.resume(); } catch {}
    window.removeEventListener('pointerdown', resumeIfNeeded, { capture: true } as any);
    window.removeEventListener('keydown', resumeIfNeeded, { capture: true } as any);
    window.removeEventListener('touchstart', resumeIfNeeded, { capture: true } as any);
  };
  window.addEventListener('pointerdown', resumeIfNeeded, { once: true, capture: true } as any);
  window.addEventListener('keydown', resumeIfNeeded, { once: true, capture: true } as any);
  window.addEventListener('touchstart', resumeIfNeeded, { once: true, capture: true } as any);
});

onUnmounted(() => {
  stopLive();
  if (resizeObserver) {
    try { resizeObserver.disconnect(); } catch {}
    resizeObserver = null;
  }
  if (themeObserver) {
    try { themeObserver.disconnect(); } catch {}
    themeObserver = null;
  }
  if (parentResizeObserver) {
    try { parentResizeObserver.disconnect(); } catch {}
    parentResizeObserver = null;
  }
});

watch(() => props.stream, (s) => {
  if (s) startLive(); else stopLive();
});
watch(() => props.src, async (s) => {
  if (s) {
    decodedBuffer = null;
    scheduleStaticRender();
  }
});
watch(() => props.audioEl, (el) => {
  if (el) attachProgressUpdates();
});
</script>

<template>
  <div class="w-full rounded-md border border-zinc-200 dark:border-zinc-700 bg-zinc-50/80 dark:bg-zinc-800/60 overflow-hidden">
    <canvas ref="canvasRef" style="display:block;width:100%;height:64px" />
  </div>
</template>

