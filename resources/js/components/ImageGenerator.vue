<script setup>
import { ref, reactive, onMounted, computed } from 'vue';

const props = defineProps({
    bgRemovalEnabled: { type: Boolean, default: false },
});

// ── State ────────────────────────────────────────────────────────────────
const styles = ref([]);            // JA styles from the API
const form = reactive({
    firstName: '',
    lastName: '',
    jaStyle: '',
    removeBg: false,
});

const portraitFile = ref(null);    // File to upload (cutout result or original)
const portraitPreview = ref('');   // object URL shown in the picker
const isDragging = ref(false);

const cutoutBusy = ref(false);
const cutoutProgress = ref(0);
const generating = ref(false);
const previewUrl = ref('');
const error = ref('');
const fieldErrors = reactive({});

const canGenerate = computed(
    () => portraitFile.value && form.jaStyle && form.firstName.trim() && !generating.value && !cutoutBusy.value,
);

// ── JA styles ────────────────────────────────────────────────────────────
onMounted(async () => {
    try {
        const res = await fetch('/api/ja-styles');
        const json = await res.json();
        styles.value = json.data ?? [];
        if (styles.value.length) form.jaStyle = styles.value[0].key;
    } catch {
        error.value = 'Die Stile konnten nicht geladen werden.';
    }
});

// ── Portrait selection ───────────────────────────────────────────────────
let originalFile = null; // keep the raw pick so toggling the cutout can re-run

async function onFile(file) {
    if (!file || !file.type.startsWith('image/')) {
        error.value = 'Bitte wähle eine Bilddatei.';
        return;
    }
    error.value = '';
    originalFile = file;
    await applyPortrait();
}

async function applyPortrait() {
    if (!originalFile) return;

    if (form.removeBg && props.bgRemovalEnabled) {
        await runCutout(originalFile);
    } else {
        setPortrait(originalFile);
    }
}

function setPortrait(fileOrBlob, name = 'portrait.png') {
    portraitFile.value = fileOrBlob instanceof File
        ? fileOrBlob
        : new File([fileOrBlob], name, { type: fileOrBlob.type });
    if (portraitPreview.value) URL.revokeObjectURL(portraitPreview.value);
    portraitPreview.value = URL.createObjectURL(portraitFile.value);
}

// Detect WebGPU once: capable devices get the full-precision model on the GPU
// (cleaner edges, still fast); CPU-only phones get the lighter half-precision
// model so they stay responsive (the brief flags weak mid/low-end devices).
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
        // Prefer the full-precision model ('isnet') for the cleanest matte. Only
        // step down to half-precision on weak CPU-only devices (no WebGPU AND few
        // cores) so the brief's low-end phones stay responsive.
        const weakCpu = !gpu && (navigator.hardwareConcurrency ?? 4) < 8;
        const blob = await removeBackground(file, {
            model: weakCpu ? 'isnet_fp16' : 'isnet',
            device: gpu ? 'gpu' : 'cpu', // gpu auto-falls back to wasm if unsupported
            output: { format: 'image/png', quality: 1 },
            progress: (_key, current, total) => {
                cutoutProgress.value = total ? Math.round((current / total) * 100) : 0;
            },
        });
        setPortrait(blob, 'cutout.png');
    } catch (e) {
        // Fall back to the original photo so the user is never stuck.
        form.removeBg = false;
        setPortrait(file);
        error.value = 'Hintergrund entfernen hat nicht geklappt — das Originalfoto wird verwendet.';
    } finally {
        cutoutBusy.value = false;
    }
}

function onToggleBg() {
    // Re-derive the portrait from the original whenever the toggle changes.
    applyPortrait();
}

// ── Drag & drop ────────────────────────────────────────────────────────────
function onDrop(e) {
    isDragging.value = false;
    onFile(e.dataTransfer.files?.[0]);
}

// ── Generate ───────────────────────────────────────────────────────────────
async function generate() {
    generating.value = true;
    error.value = '';
    previewUrl.value = '';
    Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k]);

    try {
        const body = new FormData();
        body.append('portrait', portraitFile.value);
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
            if (res.status === 429) error.value = 'Zu viele Anfragen — bitte warte einen Moment.';
            else error.value = json.message ?? 'Etwas ist schiefgelaufen.';
            if (json.errors) Object.assign(fieldErrors, json.errors);
            return;
        }
        previewUrl.value = json.url;
    } catch {
        error.value = 'Netzwerkfehler — bitte versuche es erneut.';
    } finally {
        generating.value = false;
    }
}

function reset() {
    previewUrl.value = '';
}
</script>

<template>
    <div class="rounded-2xl border border-ink/10 bg-canvas p-6 shadow-sm sm:p-8">
        <!-- Preview result -->
        <div v-if="previewUrl" class="flex flex-col items-center gap-5">
            <img :src="previewUrl" alt="Dein «Ja zum Kunsthaus» Bild" class="w-full max-w-sm rounded-lg shadow-md" />
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a :href="previewUrl" download="ja-zum-kunsthaus.jpg"
                   class="rounded-full bg-clay px-6 py-2.5 font-medium text-canvas transition hover:bg-ink">
                    Herunterladen
                </a>
                <button type="button" @click="reset"
                        class="rounded-full px-6 py-2.5 font-medium text-ink/70 transition hover:text-ink">
                    Neues Bild
                </button>
            </div>
            <!-- PROD: "Use this image" → submit (email + consent) is Phase 4. -->
            <p class="text-sm text-ink/40">Vorschau — noch nicht gespeichert (Bestätigung folgt in Phase 4).</p>
        </div>

        <!-- Form -->
        <div v-else class="grid gap-6 sm:grid-cols-2">
            <!-- Portrait picker -->
            <div class="sm:col-span-2">
                <label class="mb-2 block text-sm font-medium">Foto</label>
                <div
                    class="relative flex aspect-[4/3] items-center justify-center overflow-hidden rounded-xl border-2 border-dashed transition"
                    :class="isDragging ? 'border-clay bg-sand/40' : 'border-ink/20 bg-sand/20'"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="onDrop"
                >
                    <img v-if="portraitPreview" :src="portraitPreview" alt="Vorschau"
                         class="h-full w-full object-contain" />
                    <div v-else class="px-6 text-center text-ink/50">
                        <p class="font-medium">Foto hierher ziehen</p>
                        <p class="text-sm">oder klicken zum Auswählen</p>
                    </div>
                    <input type="file" accept="image/*"
                           class="absolute inset-0 cursor-pointer opacity-0"
                           @change="onFile($event.target.files?.[0])" />

                    <div v-if="cutoutBusy"
                         class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-canvas/85 text-ink">
                        <p class="font-medium">Hintergrund entfernen…</p>
                        <div class="h-1.5 w-40 overflow-hidden rounded-full bg-ink/10">
                            <div class="h-full bg-clay transition-all" :style="{ width: cutoutProgress + '%' }"></div>
                        </div>
                        <p class="text-sm text-ink/50">Läuft lokal auf deinem Gerät</p>
                    </div>
                </div>

                <!-- Background removal toggle -->
                <label v-if="bgRemovalEnabled" class="mt-3 flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="form.removeBg" :disabled="cutoutBusy || !portraitFile"
                           @change="onToggleBg" class="rounded border-ink/30 text-clay focus:ring-clay" />
                    <span>Hintergrund entfernen <span class="text-ink/40">(im Browser, dein Foto bleibt lokal)</span></span>
                </label>
            </div>

            <!-- JA style -->
            <div>
                <label class="mb-2 block text-sm font-medium">Maltechnik</label>
                <div class="grid grid-cols-3 gap-2">
                    <button v-for="s in styles" :key="s.key" type="button" @click="form.jaStyle = s.key"
                            class="flex flex-col items-center gap-1 rounded-lg border p-2 transition"
                            :class="form.jaStyle === s.key ? 'border-clay bg-sand/40' : 'border-ink/15 hover:border-ink/30'">
                        <img :src="s.url" :alt="s.label" class="h-10 w-full object-contain" />
                        <span class="text-xs">{{ s.label }}</span>
                    </button>
                </div>
                <p v-if="fieldErrors.ja_style" class="mt-1 text-sm text-clay">{{ fieldErrors.ja_style[0] }}</p>
            </div>

            <!-- Name -->
            <div class="grid content-start gap-3">
                <div>
                    <label class="mb-2 block text-sm font-medium">Vorname</label>
                    <input v-model="form.firstName" type="text" maxlength="40"
                           class="w-full rounded-lg border border-ink/20 bg-canvas px-3 py-2 focus:border-clay focus:ring-clay" />
                    <p v-if="fieldErrors.first_name" class="mt-1 text-sm text-clay">{{ fieldErrors.first_name[0] }}</p>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Name <span class="text-ink/40">(optional)</span></label>
                    <input v-model="form.lastName" type="text" maxlength="40"
                           class="w-full rounded-lg border border-ink/20 bg-canvas px-3 py-2 focus:border-clay focus:ring-clay" />
                </div>
            </div>

            <!-- Actions -->
            <div class="sm:col-span-2">
                <p v-if="error" class="mb-3 rounded-lg bg-clay/10 px-4 py-2 text-sm text-clay">{{ error }}</p>
                <button type="button" :disabled="!canGenerate" @click="generate"
                        class="w-full rounded-full bg-clay px-6 py-3 font-medium text-canvas transition hover:bg-ink disabled:cursor-not-allowed disabled:opacity-40">
                    {{ generating ? 'Wird erstellt…' : 'Vorschau erstellen' }}
                </button>
            </div>
        </div>
    </div>
</template>
