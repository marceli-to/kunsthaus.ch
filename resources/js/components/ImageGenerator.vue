<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed, nextTick, watch } from 'vue';

// ── Config (fetched from /api/generator on mount) ─────────────────────────
// Single source of truth is config/composite.php (the server needs the geometry
// to build the image); the island reads it here so the crop frame matches.
const bgRemovalEnabled = ref(false);
const geometry = ref({
    portrait: { x: 90, y: 40, w: 900, h: 563 },
    ja: { x: 310, y: 650, w: 460, h: 460 },
});

// ── State ────────────────────────────────────────────────────────────────
const styles = ref([]);

// DEV: prefill the personal data on local hosts only (never in production) to
// speed up manual testing.
const isLocalHost = /(^|\.)(test|localhost)$/.test(window.location.hostname)
    || window.location.hostname === '127.0.0.1';

const form = reactive({
    lastName: isLocalHost ? 'Stadelmann' : '',   // "Name"
    firstName: isLocalHost ? 'Marcel' : '',      // "Vorname"
    email: isLocalHost ? 'marcel.stadelmann@gmail.com' : '', // held for confirm/submit (Phase 4)
    jaStyle: '',
    removeBg: false,
});

const portraitPreview = ref('');       // object URL of the source photo (original or cutout)
const portraitHasAlpha = ref(false);   // true when the source is a transparent cutout
const hasPortrait = ref(false);
const fileInput = ref(null);

const cutoutBusy = ref(false);
const cutoutProgress = ref(0);
const generating = ref(false);
const previewUrl = ref('');
const error = ref('');
const fieldErrors = reactive({});

const emailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim()));

const selectedStyle = computed(() => styles.value.find((s) => s.key === form.jaStyle) ?? null);

const canGenerate = computed(() =>
    hasPortrait.value &&
    !!form.jaStyle &&
    form.firstName.trim() !== '' &&
    form.lastName.trim() !== '' &&
    emailValid.value &&
    !generating.value &&
    !cutoutBusy.value,
);

// ── Crop (pan-only) ──────────────────────────────────────────────────────
const cropFrame = ref(null);
const photoImg = ref(null);
const frameW = ref(0);
const frameH = ref(0);
const crop = reactive({ natW: 0, natH: 0, fracX: 0.5, fracY: 0 }); // fractions of overflow

const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));

// Frame aspect + JA overlay position, straight from the composite geometry.
const frameStyle = computed(() => ({
    aspectRatio: `${geometry.value.portrait.w} / ${geometry.value.portrait.h}`,
}));
const overlayStyle = computed(() => {
    const p = geometry.value.portrait;
    const j = geometry.value.ja;
    return {
        left: `${((j.x - p.x) / p.w) * 100}%`,
        top: `${((j.y - p.y) / p.h) * 100}%`,
        width: `${(j.w / p.w) * 100}%`,
    };
});
// The JA sign only overlaps the portrait in an overlapping layout. In a stacked
// layout (sign below the portrait) there's nothing to overlay — the whole crop
// shows, so the frame is already WYSIWYG.
const jaOverlaps = computed(() => {
    const p = geometry.value.portrait;
    const j = geometry.value.ja;
    return j.y < p.y + p.h && j.x < p.x + p.w && j.x + j.w > p.x;
});

// Cover-fit the photo to the frame; pan offset comes from the fractions.
const cover = computed(() => {
    const F = frameW.value;
    const H = frameH.value;
    if (!F || !H || !crop.natW || !crop.natH) return { w: F, h: H, x: 0, y: 0 };
    const s = Math.max(F / crop.natW, H / crop.natH);
    const w = crop.natW * s;
    const h = crop.natH * s;
    return { w, h, x: -(w - F) * crop.fracX, y: -(h - H) * crop.fracY };
});
const photoStyle = computed(() => ({
    width: `${cover.value.w}px`,
    height: `${cover.value.h}px`,
    transform: `translate(${cover.value.x}px, ${cover.value.y}px)`,
}));

let ro = null;
function measureFrame() {
    if (!cropFrame.value) return;
    frameW.value = cropFrame.value.clientWidth;
    frameH.value = cropFrame.value.clientHeight;
}
watch(hasPortrait, async (has) => {
    if (!has) return;
    await nextTick();
    measureFrame();
    if (ro) ro.disconnect();
    ro = new ResizeObserver(measureFrame);
    if (cropFrame.value) ro.observe(cropFrame.value);
});
onBeforeUnmount(() => ro?.disconnect());

function onPhotoLoad(e) {
    crop.natW = e.target.naturalWidth;
    crop.natH = e.target.naturalHeight;
    crop.fracX = 0.5;
    crop.fracY = 0; // default: show the top of the photo (the head)
    measureFrame();
}

// Pan the photo within the frame (pointer + touch unified).
let drag = null;
function onPointerDown(e) {
    drag = { x: e.clientX, y: e.clientY, fx: crop.fracX, fy: crop.fracY };
    cropFrame.value?.setPointerCapture?.(e.pointerId);
}
function onPointerMove(e) {
    if (!drag) return;
    const s = Math.max(frameW.value / crop.natW, frameH.value / crop.natH);
    const overflowX = crop.natW * s - frameW.value;
    const overflowY = crop.natH * s - frameH.value;
    if (overflowX > 0) crop.fracX = clamp(drag.fx - (e.clientX - drag.x) / overflowX, 0, 1);
    if (overflowY > 0) crop.fracY = clamp(drag.fy - (e.clientY - drag.y) / overflowY, 0, 1);
}
function onPointerUp() {
    drag = null;
}

// Render the framed crop to a canvas at the portrait box's aspect ratio.
function exportCrop() {
    const p = geometry.value.portrait;
    const scaleUp = 1.5; // render above the 760px box for crisp downscaling
    const W = Math.round(p.w * scaleUp);
    const H = Math.round(p.h * scaleUp);
    const s = Math.max(W / crop.natW, H / crop.natH);
    const dw = crop.natW * s;
    const dh = crop.natH * s;
    const dx = -(dw - W) * crop.fracX;
    const dy = -(dh - H) * crop.fracY;

    const canvas = document.createElement('canvas');
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d');
    if (!portraitHasAlpha.value) {
        ctx.fillStyle = '#ffffff'; // flatten onto white for JPEG
        ctx.fillRect(0, 0, W, H);
    }
    ctx.drawImage(photoImg.value, dx, dy, dw, dh);

    const type = portraitHasAlpha.value ? 'image/png' : 'image/jpeg';
    return new Promise((resolve) => canvas.toBlob(resolve, type, 0.92));
}

// ── Boot: styles + bg-removal + geometry ───────────────────────────────────
onMounted(async () => {
    try {
        const res = await fetch('/api/generator');
        const json = await res.json();
        styles.value = json.styles ?? [];
        bgRemovalEnabled.value = !!json.bg_removal;
        if (json.geometry) geometry.value = json.geometry;
    } catch {
        error.value = 'Der Generator konnte nicht geladen werden.';
    }
});

// ── Portrait selection ───────────────────────────────────────────────────
let originalFile = null;

async function onFile(file) {
    if (!file) return;
    if (!file.type.startsWith('image/')) {
        error.value = 'Bitte wählen Sie eine Bilddatei.';
        return;
    }
    error.value = '';
    originalFile = file;
    await applyPortrait();
}

async function applyPortrait() {
    if (!originalFile) return;
    if (form.removeBg && bgRemovalEnabled.value) {
        await runCutout(originalFile);
    } else {
        setPortrait(originalFile, false);
    }
}

function setPortrait(fileOrBlob, hasAlpha) {
    if (portraitPreview.value) URL.revokeObjectURL(portraitPreview.value);
    portraitPreview.value = URL.createObjectURL(fileOrBlob);
    portraitHasAlpha.value = hasAlpha;
    hasPortrait.value = true;
}

function clearPortrait() {
    originalFile = null;
    form.removeBg = false;
    hasPortrait.value = false;
    if (portraitPreview.value) URL.revokeObjectURL(portraitPreview.value);
    portraitPreview.value = '';
    if (fileInput.value) fileInput.value.value = '';
}

// Detect WebGPU once (cleaner/faster on capable devices, WASM fallback else).
let webgpuSupport = null;
async function hasWebGpu() {
    if (webgpuSupport !== null) return webgpuSupport;
    try {
        webgpuSupport = !!navigator.gpu && !!(await navigator.gpu.requestAdapter());
    } catch {
        webgpuSupport = false;
    }
    return webgpuSupport;
}

// ── Client-side background removal (@imgly) ────────────────────────────────
// PROD: @imgly/background-removal is AGPL-3.0 — buy the commercial licence or
// swap to a permissive model (BiRefNet/MODNet) before launch. The raw photo
// never leaves the device: only the finished cut-out is uploaded.
async function runCutout(file) {
    cutoutBusy.value = true;
    cutoutProgress.value = 0;
    error.value = '';
    try {
        const { removeBackground } = await import('@imgly/background-removal');
        const gpu = await hasWebGpu();
        const weakCpu = !gpu && (navigator.hardwareConcurrency ?? 4) < 8;
        const blob = await removeBackground(file, {
            model: weakCpu ? 'isnet_fp16' : 'isnet',
            device: gpu ? 'gpu' : 'cpu',
            output: { format: 'image/png', quality: 1 },
            progress: (_key, current, total) => {
                cutoutProgress.value = total ? Math.round((current / total) * 100) : 0;
            },
        });
        setPortrait(blob, true);
    } catch {
        form.removeBg = false;
        setPortrait(file, false);
        error.value = 'Hintergrund entfernen hat nicht geklappt — das Originalfoto wird verwendet.';
    } finally {
        cutoutBusy.value = false;
    }
}

function onToggleBg() {
    applyPortrait();
}

// ── Generate (preview only — no record yet; confirm + consent is Phase 4) ──
async function generate() {
    generating.value = true;
    error.value = '';
    previewUrl.value = '';
    Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k]);

    try {
        const portraitBlob = await exportCrop();
        const body = new FormData();
        body.append('portrait', new File(
            [portraitBlob],
            portraitHasAlpha.value ? 'portrait.png' : 'portrait.jpg',
            { type: portraitBlob.type },
        ));
        body.append('ja_style', form.jaStyle);
        body.append('first_name', form.firstName);
        body.append('last_name', form.lastName);

        const res = await fetch('/api/generate', {
            method: 'POST',
            body,
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();

        if (!res.ok) {
            if (res.status === 429) error.value = 'Zu viele Anfragen — bitte warten Sie einen Moment.';
            else error.value = json.message ?? 'Etwas ist schiefgelaufen.';
            if (json.errors) Object.assign(fieldErrors, json.errors);
            return;
        }
        previewUrl.value = json.url;
    } catch {
        error.value = 'Netzwerkfehler — bitte versuchen Sie es erneut.';
    } finally {
        generating.value = false;
    }
}

function reset() {
    previewUrl.value = '';
}
</script>

<template>
    <div class="text-white">
        <!-- ── Preview result ─────────────────────────────────────────── -->
        <div v-if="previewUrl" class="flex flex-col gap-24">
            <img :src="previewUrl" alt="Ihr «JA zum Kunsthaus» Bild"
                 class="w-full max-w-md self-center border-2 border-white bg-white" />
            <div class="flex flex-wrap gap-12">
                <a :href="previewUrl" download="ja-zum-kunsthaus.jpg"
                   class="font-sans-bold leading-none px-16 py-12 xl:px-20 xl:py-16 bg-white text-accent">
                    Herunterladen
                </a>
                <button type="button" @click="reset"
                        class="font-sans-bold leading-none px-16 py-12 xl:px-20 xl:py-16 border-2 border-white text-white cursor-pointer">
                    Neues Bild
                </button>
            </div>
            <p class="text-tiny text-white/70">Vorschau — noch nicht gespeichert oder veröffentlicht (Bestätigung folgt).</p>
        </div>

        <!-- ── Form ───────────────────────────────────────────────────── -->
        <div v-else class="grid gap-32">

            <!-- Persönliche Daten -->
            <fieldset class="grid gap-16">
                <legend class="font-sans-bold text-md md:text-lg mb-8">Persönliche Daten</legend>

                <div>
                    <label for="ja-last" class="font-sans-bold block mb-8">Name*</label>
                    <input id="ja-last" v-model="form.lastName" type="text" maxlength="40" autocomplete="family-name"
                           class="w-full bg-transparent border border-white px-12 py-10 text-white placeholder-white/50 focus:outline-none focus:ring-1 focus:ring-white" />
                    <p v-if="fieldErrors.last_name" class="text-tiny mt-4">{{ fieldErrors.last_name[0] }}</p>
                </div>

                <div>
                    <label for="ja-first" class="font-sans-bold block mb-8">Vorname*</label>
                    <input id="ja-first" v-model="form.firstName" type="text" maxlength="40" autocomplete="given-name"
                           class="w-full bg-transparent border border-white px-12 py-10 text-white placeholder-white/50 focus:outline-none focus:ring-1 focus:ring-white" />
                    <p v-if="fieldErrors.first_name" class="text-tiny mt-4">{{ fieldErrors.first_name[0] }}</p>
                </div>

                <div>
                    <label for="ja-email" class="font-sans-bold block mb-8">E-Mail*</label>
                    <input id="ja-email" v-model="form.email" type="email" autocomplete="email"
                           class="w-full bg-transparent border border-white px-12 py-10 text-white placeholder-white/50 focus:outline-none focus:ring-1 focus:ring-white" />
                </div>
            </fieldset>

            <!-- Ihr Foto -->
            <div>
                <p class="font-sans-bold text-md md:text-lg mb-16">Ihr Foto</p>

                <input ref="fileInput" type="file" accept="image/*" class="hidden"
                       @change="onFile($event.target.files?.[0])" />

                <button v-if="!hasPortrait && !cutoutBusy" type="button" @click="fileInput?.click()"
                        class="font-sans-bold leading-none px-16 py-12 xl:px-20 xl:py-16 bg-white text-accent cursor-pointer">
                    Foto hochladen
                </button>

                <div v-else>
                    <!-- Pan-only crop stage: drag the photo; the JA sign shows where it will sit. -->
                    <div ref="cropFrame" :style="frameStyle"
                         class="relative w-full max-w-sm select-none overflow-hidden border border-white bg-white"
                         :class="cutoutBusy ? '' : 'cursor-move touch-none'"
                         @pointerdown="onPointerDown" @pointermove="onPointerMove"
                         @pointerup="onPointerUp" @pointercancel="onPointerUp">
                        <img ref="photoImg" :src="portraitPreview" alt="" draggable="false"
                             class="pointer-events-none absolute left-0 top-0 max-w-none"
                             :style="photoStyle" @load="onPhotoLoad" />

                        <!-- Live JA-sign overlay — only in an overlapping layout -->
                        <img v-if="jaOverlaps && selectedStyle" :src="selectedStyle.url" alt="" draggable="false"
                             class="pointer-events-none absolute" :style="overlayStyle" />

                        <!-- Hint in the visible face zone (overlapping layout only) -->
                        <p v-if="jaOverlaps && !cutoutBusy" class="pointer-events-none absolute inset-x-0 top-8 text-center text-tiny text-accent/70">
                            Gesicht hierhin ziehen
                        </p>

                        <!-- Cutout progress -->
                        <div v-if="cutoutBusy"
                             class="absolute inset-0 flex flex-col items-center justify-center gap-8 bg-accent/85 text-white">
                            <p class="font-sans-bold">Hintergrund entfernen…</p>
                            <div class="h-1 w-40 overflow-hidden bg-white/30">
                                <div class="h-full bg-white transition-all" :style="{ width: cutoutProgress + '%' }"></div>
                            </div>
                            <p class="text-tiny text-white/70">Läuft lokal auf Ihrem Gerät</p>
                        </div>
                    </div>

                    <p v-if="!cutoutBusy" class="mt-8 text-tiny text-white/60">Ziehen Sie das Bild, um es zu positionieren.</p>

                    <div class="mt-12 flex gap-16">
                        <button type="button" @click="fileInput?.click()" :disabled="cutoutBusy"
                                class="font-sans-bold underline underline-offset-2 hover:no-underline cursor-pointer disabled:opacity-50">
                            Foto ändern
                        </button>
                        <button v-if="!cutoutBusy" type="button" @click="clearPortrait"
                                class="text-white/70 underline underline-offset-2 hover:no-underline cursor-pointer">
                            Entfernen
                        </button>
                    </div>

                    <label v-if="bgRemovalEnabled" class="mt-16 flex items-start gap-8">
                        <input type="checkbox" v-model="form.removeBg" :disabled="cutoutBusy"
                               @change="onToggleBg" class="mt-2 accent-white" />
                        <span>Hintergrund entfernen <span class="text-white/60">(im Browser, Ihr Foto bleibt lokal)</span></span>
                    </label>
                </div>
            </div>

            <!-- Stil wählen -->
            <div>
                <p class="font-sans-bold text-md md:text-lg mb-16">Stil wählen</p>

                <div class="relative">
                    <select v-model="form.jaStyle"
                            class="w-full appearance-none bg-transparent border border-white px-12 py-10 pr-40 text-white focus:outline-none focus:ring-1 focus:ring-white">
                        <option value="" disabled class="text-black">Bitte wählen…</option>
                        <option v-for="s in styles" :key="s.key" :value="s.key" class="text-black">{{ s.label }}</option>
                    </select>
                    <span class="pointer-events-none absolute right-12 top-1/2 -translate-y-1/2">
                        <svg viewBox="0 0 12 7" class="w-12 fill-white" xmlns="http://www.w3.org/2000/svg">
                            <path d="M0.877.5a.4.4 0 0 1 .256.108l4.303 4.327.353.356L10.472.608a.2.2 0 0 1 .225-.096.4.4 0 0 1 .262.122.4.4 0 0 1 .108.256.4.4 0 0 1-.108.255l-4.92 4.92a.6.6 0 0 1-.124.09.4.4 0 0 1-.185.023.3.3 0 0 1-.067-.006l-.057-.017-.053-.03a.8.8 0 0 1-.116-.062L.597 1.12A.36.36 0 0 1 .5.882.5.5 0 0 1 .622.608.4.4 0 0 1 .877.5Z"/>
                        </svg>
                    </span>
                </div>
                <p v-if="fieldErrors.ja_style" class="text-tiny mt-4">{{ fieldErrors.ja_style[0] }}</p>
            </div>

            <!-- Actions -->
            <div>
                <p v-if="error" class="mb-16 border border-white px-12 py-10 text-white">{{ error }}</p>
                <button type="button" :disabled="!canGenerate" @click="generate"
                        class="font-sans-bold leading-none px-20 py-14 xl:px-24 xl:py-16 bg-white text-accent cursor-pointer disabled:cursor-not-allowed disabled:opacity-50">
                    {{ generating ? 'Wird erstellt…' : 'erstellen' }}
                </button>
            </div>
        </div>
    </div>
</template>
