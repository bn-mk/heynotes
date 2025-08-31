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
let sourceNode: (MediaStreamAudioSourceNode | MediaElementAudioSourceNode | null) = null;
let timeData: Uint8Array | null = null;
let resizeObserver: ResizeObserver | null = null;

const dpr = Math.max(1, Math.floor(window.devicePixelRatio || 1));

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

function computePeaks(buffer: AudioBuffer, buckets: number): Float32Array {
  const chs = buffer.numberOfChannels;
  const length = buffer.length;
  const samplesPerBucket = Math.max(1, Math.floor(length / buckets));
  const peaks = new Float32Array(buckets);
  for (let b = 0; b < buckets; b++) {
    let start = b * samplesPerBucket;
    let end = Math.min(length, start + samplesPerBucket);
    let max = 0;
    for (let i = start; i < end; i += 16) { // stride for performance
      let sum = 0;
      for (let c = 0; c < chs; c++) {
        sum += Math.abs(buffer.getChannelData(c)[i] || 0);
      }
      const v = sum / chs;
      if (v > max) max = v;
    }
    peaks[b] = max;
  }
  // normalize
  let globalMax = 0;
  for (let i = 0; i < buckets; i++) globalMax = Math.max(globalMax, peaks[i]);
  const norm = globalMax > 0 ? 1 / globalMax : 1;
  for (let i = 0; i < buckets; i++) peaks[i] *= norm;
  return peaks;
}

function drawStatic(progress = -1) {
  if (!canvasRef.value) return;
  const canvas = canvasRef.value;
  const rect = canvas.getBoundingClientRect();
  const width = Math.max(1, Math.floor(rect.width * dpr));
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

  if (!decodedBuffer) {
    // Placeholder: draw a midline bar and optional progress overlay
    const bg = props.backgroundColor || '#e5e7eb';
    const prog = props.progressColor || '#3b82f6';
    const mid = Math.floor(height / 2);
    const barH = Math.max(2, Math.floor(3 * dpr));
    ctx.fillStyle = bg;
    ctx.fillRect(0, mid - Math.floor(barH / 2), width, barH);
    if (progress >= 0) {
      const px = Math.floor(progress * width);
      ctx.fillStyle = prog;
      ctx.fillRect(0, mid - Math.floor(barH / 2), Math.max(1, px), barH);
    }
    return;
  }

  const buckets = Math.min(width, 1200);
  const peaks = computePeaks(decodedBuffer, buckets);

  // Draw background waveform
  const bg = props.backgroundColor || '#e5e7eb';
  const fg = props.color || '#64748b';
  const prog = props.progressColor || '#3b82f6';
  const mid = Math.floor(height / 2);
  const barW = Math.max(1, Math.floor(width / buckets));

  // Background
  ctx.fillStyle = bg;
  for (let i = 0; i < buckets; i++) {
    const x = i * barW;
    const amp = Math.max(1, Math.floor(peaks[i] * height * 0.9));
    const y = Math.max(1, Math.floor(amp / 2));
    ctx.fillRect(x, mid - y, barW - 1, y * 2);
  }

  // Foreground (full waveform)
  ctx.fillStyle = fg;
  for (let i = 0; i < buckets; i++) {
    const x = i * barW;
    const amp = Math.max(1, Math.floor(peaks[i] * height * 0.66));
    const y = Math.max(1, Math.floor(amp / 2));
    ctx.fillRect(x, mid - y, barW - 1, y * 2);
  }

  // Progress overlay if provided
  if (progress >= 0) {
    const px = Math.floor(progress * width);
    if (px > 0) {
      ctx.save();
      ctx.beginPath();
      ctx.rect(0, 0, px, height);
      ctx.clip();
      ctx.fillStyle = prog;
      for (let i = 0; i < buckets; i++) {
        const x = i * barW;
        const amp = Math.max(1, Math.floor(peaks[i] * height * 0.9));
        const y = Math.max(1, Math.floor(amp / 2));
        ctx.fillRect(x, mid - y, barW - 1, y * 2);
      }
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

async function attachMediaElementAnalysis() {
  const el = getAudioElement();
  if (!el) return;
  if (!audioCtx) audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
  try { if (audioCtx.state === 'suspended') await audioCtx.resume(); } catch {}
  stopLive();
  try {
    // Create analyser from media element without altering audible output
    sourceNode = (audioCtx as any).createMediaElementSource(el);
    analyser = audioCtx.createAnalyser();
    analyser.fftSize = 2048;
    (sourceNode as any).connect(analyser);
    // Do not connect analyser to destination to avoid duplicating audio
    timeData = new Uint8Array(analyser.fftSize);
    drawLive();
  } catch (e) {
    // If creating media element source fails (cross-origin), ignore
  }
}

onMounted(async () => {
  if (props.stream) {
    await nextTick();
    startLive();
  } else if (props.src) {
    await nextTick();
    await renderStaticWave();
    await attachMediaElementAnalysis();
  }
  if ('ResizeObserver' in window && canvasRef.value) {
    resizeObserver = new ResizeObserver(() => {
      if (props.stream) drawLive();
      else renderStaticWave();
    });
    resizeObserver.observe(canvasRef.value);
  }
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
});

watch(() => props.stream, (s) => {
  if (s) startLive(); else stopLive();
});
watch(() => props.src, async (s) => {
  if (s) {
    decodedBuffer = null;
    await renderStaticWave();
  }
});
watch(() => props.audioEl, async (el) => {
  if (el) {
    await attachMediaElementAnalysis();
  }
});
</script>

<template>
  <canvas ref="canvasRef" style="display:block;width:100%;height:64px;border-radius:6px;" />
</template>

