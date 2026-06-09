<script setup>
import { computed, ref } from 'vue';

const MAX = 300;
const MIN = 3;

// The campaign headline is composited onto the artwork here (not rendered by
// the image model), so it is always correctly spelled, legible and in-frame.
const HEADLINE = 'Ja zum Kunstmuseum';

const prompt = ref('');
const state = ref('idle'); // idle | loading | success | error
const errorMessage = ref('');
const image = ref(null); // { id, url }
const downloading = ref(false);

const remaining = computed(() => MAX - prompt.value.length);
const tooLong = computed(() => prompt.value.length > MAX);
const canSubmit = computed(
    () => prompt.value.trim().length >= MIN && !tooLong.value && state.value !== 'loading',
);

async function generate() {
    if (!canSubmit.value) return;

    state.value = 'loading';
    errorMessage.value = '';

    try {
        const res = await fetch('/api/generate-image', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({ prompt: prompt.value.trim() }),
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            // Laravel validation errors arrive under `errors`; ours under `error`.
            const validation = data.errors ? Object.values(data.errors)[0]?.[0] : null;
            errorMessage.value =
                data.error || validation || data.message || 'Etwas ist schiefgelaufen. Bitte versuche es erneut.';
            state.value = 'error';
            return;
        }

        image.value = data;
        state.value = 'success';
    } catch {
        errorMessage.value = 'Netzwerkfehler. Bitte überprüfe deine Verbindung und versuche es erneut.';
        state.value = 'error';
    }
}

// Load the source artwork as an <img> we can draw to a canvas. Same-origin
// (public storage), so the canvas stays untainted and exportable.
function loadImage(url) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('image load failed'));
        img.src = url;
    });
}

// Word-wrap a string to fit a max pixel width on the given 2D context.
function wrapLines(ctx, text, maxWidth) {
    const words = text.split(' ');
    const lines = [];
    let line = '';
    for (const word of words) {
        const candidate = line ? `${line} ${word}` : word;
        if (ctx.measureText(candidate).width > maxWidth && line) {
            lines.push(line);
            line = word;
        } else {
            line = candidate;
        }
    }
    if (line) lines.push(line);
    return lines;
}

// Composite the headline onto the artwork at full native resolution and return
// a PNG blob. Mirrors the on-screen overlay so download is WYSIWYG.
async function buildPosterBlob(url) {
    const img = await loadImage(url);
    const w = img.naturalWidth || 1024;
    const h = img.naturalHeight || 1024;

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');

    ctx.drawImage(img, 0, 0, w, h);

    // Bottom scrim so light type stays legible over any artwork.
    const scrimTop = h * 0.58;
    const gradient = ctx.createLinearGradient(0, scrimTop, 0, h);
    gradient.addColorStop(0, 'rgba(28, 26, 23, 0)');
    gradient.addColorStop(1, 'rgba(28, 26, 23, 0.62)');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, scrimTop, w, h - scrimTop);

    // Headline — brand serif, sized and padded relative to the artwork so it
    // never touches the edges.
    const pad = w * 0.06;
    const fontPx = w * 0.072;
    const lineHeight = fontPx * 1.08;
    ctx.font = `700 ${fontPx}px 'Fraunces', Georgia, serif`;
    // Make sure the (possibly lazy) webfont is ready before measuring/drawing.
    if (document.fonts?.load) {
        try {
            await document.fonts.load(`700 ${fontPx}px 'Fraunces'`, HEADLINE);
            await document.fonts.ready;
        } catch {
            /* fall back to serif */
        }
    }

    const lines = wrapLines(ctx, HEADLINE, w - pad * 2);
    ctx.fillStyle = '#f7f3ec'; // canvas
    ctx.textBaseline = 'alphabetic';
    ctx.textAlign = 'left';
    let baseline = h - pad - (lines.length - 1) * lineHeight;
    for (const line of lines) {
        ctx.fillText(line, pad, baseline);
        baseline += lineHeight;
    }

    return await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
}

async function download() {
    if (!image.value || downloading.value) return;
    downloading.value = true;
    try {
        const blob = await buildPosterBlob(image.value.url);
        const href = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = href;
        a.download = `ja-zum-kunstmuseum-${image.value.id}.png`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(href);
    } catch {
        // Fall back to the raw artwork (without the baked-in headline) so the
        // user can still save something.
        window.open(image.value.url, '_blank');
    } finally {
        downloading.value = false;
    }
}

function reset() {
    state.value = 'idle';
    image.value = null;
    errorMessage.value = '';
    prompt.value = '';
}
</script>

<template>
    <div class="rounded-3xl border border-ink/10 bg-white/70 p-6 shadow-sm backdrop-blur-sm sm:p-8">
        <!-- Success -->
        <div v-if="state === 'success' && image" class="space-y-5">
            <figure class="@container relative overflow-hidden rounded-2xl border border-ink/10 bg-canvas">
                <img :src="image.url" alt="Dein generiertes «Ja zum Kunstmuseum» Bild" class="block w-full" />
                <!-- Headline overlay (composited into the file on download). -->
                <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink/60 to-transparent pt-[18cqw]"></div>
                <p
                    class="pointer-events-none absolute bottom-[6cqw] left-[6cqw] right-[6cqw] font-serif font-bold leading-[1.08] text-canvas text-[7.2cqw]"
                >
                    {{ HEADLINE }}
                </p>
            </figure>
            <div class="flex flex-col gap-3 sm:flex-row">
                <button
                    type="button"
                    @click="download"
                    :disabled="downloading"
                    class="flex-1 rounded-full bg-clay px-6 py-3 text-center font-medium text-canvas transition hover:bg-ink disabled:opacity-60"
                >
                    {{ downloading ? 'Bild wird vorbereitet …' : 'Bild herunterladen' }}
                </button>
                <button
                    type="button"
                    @click="reset"
                    class="flex-1 rounded-full border border-ink/15 px-6 py-3 font-medium text-ink/80 transition hover:border-ink/40 hover:text-ink"
                >
                    Neues Bild gestalten
                </button>
            </div>
            <!-- PROD: persistent shareable URL + OG meta tags for social sharing attach here. -->
        </div>

        <!-- Loading -->
        <div v-else-if="state === 'loading'" class="space-y-5">
            <div class="relative aspect-square w-full overflow-hidden rounded-2xl border border-ink/10 bg-sand/50">
                <div class="absolute inset-0 animate-pulse bg-gradient-to-br from-sand/60 via-canvas to-ochre/20"></div>
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-center">
                    <svg class="h-8 w-8 animate-spin text-clay" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4z" />
                    </svg>
                    <p class="text-sm font-medium text-ink/60">Dein Bild entsteht … das dauert einen Moment.</p>
                </div>
            </div>
        </div>

        <!-- Idle / error form -->
        <form v-else @submit.prevent="generate" class="space-y-4">
            <div>
                <label for="prompt" class="mb-2 block text-sm font-medium text-ink/70">
                    Wie sieht dein «Ja zum Kunstmuseum» aus?
                </label>
                <textarea
                    id="prompt"
                    v-model="prompt"
                    rows="3"
                    :maxlength="MAX + 50"
                    placeholder="Beschreibe dein Ja zum Kunstmuseum…"
                    class="w-full resize-none rounded-2xl border border-ink/15 bg-canvas/60 px-4 py-3 text-ink placeholder:text-ink/35 focus:border-clay focus:ring-2 focus:ring-clay/30 focus:outline-none"
                ></textarea>
                <div class="mt-1.5 flex items-center justify-between text-xs">
                    <span :class="tooLong ? 'text-clay' : 'text-ink/40'">
                        {{ remaining }} Zeichen übrig
                    </span>
                </div>
            </div>

            <p v-if="state === 'error'" class="rounded-xl bg-clay/10 px-4 py-3 text-sm text-clay">
                {{ errorMessage }}
            </p>

            <button
                type="submit"
                :disabled="!canSubmit"
                class="w-full rounded-full bg-clay px-6 py-3.5 font-medium text-canvas shadow-sm transition hover:bg-ink disabled:cursor-not-allowed disabled:opacity-40"
            >
                Bild generieren
            </button>
        </form>
    </div>
</template>
