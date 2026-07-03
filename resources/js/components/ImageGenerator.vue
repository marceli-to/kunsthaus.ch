<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed } from 'vue';
import { Cropper } from 'vue-advanced-cropper';
import Stencil from './Stencil.vue';

// ── Config (fetched from /api/generator on mount) ─────────────────────────
// Single source of truth is config/composite.php (the server needs the geometry
// to build the image); the island reads it here so the crop frame matches.
// Keys ("portrait", "ja") mirror the server config.
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
	signStyle: '', // sent as "ja_style"
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

const selectedStyle = computed(() => styles.value.find((s) => s.key === form.signStyle) ?? null);

const canGenerate = computed(() =>
	hasPortrait.value &&
	!!form.signStyle &&
	form.firstName.trim() !== '' &&
	form.lastName.trim() !== '' &&
	emailValid.value &&
	!generating.value &&
	!cutoutBusy.value,
);

// ── Crop (vue-advanced-cropper, fixed-aspect stencil) ────────────────────
const cropperRef = ref(null);

// Crop aspect + sign overlay position, straight from the composite geometry.
const cropAspect = computed(() => geometry.value.portrait.w / geometry.value.portrait.h);
const overlayStyle = computed(() => {
	const portrait = geometry.value.portrait;
	const sign = geometry.value.ja;
	return {
		left: `${((sign.x - portrait.x) / portrait.w) * 100}%`,
		top: `${((sign.y - portrait.y) / portrait.h) * 100}%`,
		width: `${(sign.w / portrait.w) * 100}%`,
	};
});
// The sign only overlaps the portrait in an overlapping layout. In a stacked
// layout (sign below the portrait) there's nothing to overlay — the whole crop
// shows, so the frame is already WYSIWYG.
const signOverlapsPortrait = computed(() => {
	const portrait = geometry.value.portrait;
	const sign = geometry.value.ja;
	return sign.y < portrait.y + portrait.h
		&& sign.x < portrait.x + portrait.w
		&& sign.x + sign.w > portrait.x;
});

// Default crop: largest possible area, horizontally centered, top-aligned
// (keeps the head in frame).
function defaultSize({ imageSize }) {
	const width = Math.min(imageSize.width, imageSize.height * cropAspect.value);
	return { width, height: width / cropAspect.value };
}

function defaultPosition({ imageSize, coordinates }) {
	return { left: (imageSize.width - coordinates.width) / 2, top: 0 };
}

// Render the selected crop to a canvas at the portrait box's aspect ratio.
// Resolves to null when the cropper isn't ready or encoding fails.
function exportCrop() {
	const result = cropperRef.value?.getResult();
	if (!result?.canvas) return Promise.resolve(null);

	const portrait = geometry.value.portrait;
	const scaleUp = 1.5; // render above the 760px box for crisp downscaling
	const width = Math.round(portrait.w * scaleUp);
	const height = Math.round(portrait.h * scaleUp);

	const canvas = document.createElement('canvas');
	canvas.width = width;
	canvas.height = height;
	const ctx = canvas.getContext('2d');
	if (!portraitHasAlpha.value) {
		ctx.fillStyle = '#ffffff'; // flatten onto white for JPEG
		ctx.fillRect(0, 0, width, height);
	}
	ctx.drawImage(result.canvas, 0, 0, width, height);

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

onBeforeUnmount(() => {
	if (portraitPreview.value) URL.revokeObjectURL(portraitPreview.value);
});

// ── Portrait selection ───────────────────────────────────────────────────
let originalFile = null;

async function onFile(file) {
	if (!file) return;
	// Reset the input so picking the same file again still fires "change".
	if (fileInput.value) fileInput.value.value = '';
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

// ── Generate (preview only — no record yet; confirm + consent is Phase 4) ──
async function generate() {
	generating.value = true;
	error.value = '';
	previewUrl.value = '';
	Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k]);

	try {
		const portraitBlob = await exportCrop();
		if (!portraitBlob) {
			error.value = 'Der Bildausschnitt konnte nicht erstellt werden.';
			return;
		}

		const body = new FormData();
		body.append('portrait', new File(
			[portraitBlob],
			portraitHasAlpha.value ? 'portrait.png' : 'portrait.jpg',
			{ type: portraitBlob.type },
		));
		body.append('ja_style', form.signStyle);
		body.append('first_name', form.firstName);
		body.append('last_name', form.lastName);

		const res = await fetch('/api/generate', {
			method: 'POST',
			body,
			headers: { Accept: 'application/json' },
		});
		const json = await res.json().catch(() => ({}));

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
		<div
			v-if="previewUrl"
			class="flex flex-col gap-24">
			<img
				:src="previewUrl"
				alt="Ihr «JA zum Kunsthaus» Bild"
				class="w-full max-w-md self-center border-2 border-white bg-white">
			<div class="flex flex-wrap gap-12">
				<a
					:href="previewUrl"
					download="ja-zum-kunsthaus.jpg"
					class="font-sans-bold leading-none px-16 py-12 xl:px-20 xl:py-16 bg-white text-accent">
					Herunterladen
				</a>
				<button
					type="button"
					class="font-sans-bold leading-none px-16 py-12 xl:px-20 xl:py-16 border-2 border-white text-white cursor-pointer"
					@click="reset">
					Neues Bild
				</button>
			</div>
			<p class="text-tiny text-white/70">Vorschau — noch nicht gespeichert oder veröffentlicht (Bestätigung folgt).</p>
		</div>

		<!-- ── Form ───────────────────────────────────────────────────── -->
		<div
			v-else
			class="grid gap-32">

			<!-- Persönliche Daten -->
			<fieldset class="grid gap-16">
				<legend class="font-sans-bold text-md md:text-lg mb-8">Persönliche Daten</legend>

				<div>
					<label
						for="ja-last"
						class="font-sans-bold block mb-8">
						Name*
					</label>
					<input
						id="ja-last"
						v-model="form.lastName"
						type="text"
						maxlength="40"
						autocomplete="family-name"
						class="w-full bg-transparent border border-white px-12 py-10 text-white placeholder-white/50 focus:outline-none focus:ring-1 focus:ring-white">
					<p
						v-if="fieldErrors.last_name"
						class="text-tiny mt-4">
						{{ fieldErrors.last_name[0] }}
					</p>
				</div>

				<div>
					<label
						for="ja-first"
						class="font-sans-bold block mb-8">
						Vorname*
					</label>
					<input
						id="ja-first"
						v-model="form.firstName"
						type="text"
						maxlength="40"
						autocomplete="given-name"
						class="w-full bg-transparent border border-white px-12 py-10 text-white placeholder-white/50 focus:outline-none focus:ring-1 focus:ring-white">
					<p
						v-if="fieldErrors.first_name"
						class="text-tiny mt-4">
						{{ fieldErrors.first_name[0] }}
					</p>
				</div>

				<div>
					<label
						for="ja-email"
						class="font-sans-bold block mb-8">
						E-Mail*
					</label>
					<input
						id="ja-email"
						v-model="form.email"
						type="email"
						autocomplete="email"
						class="w-full bg-transparent border border-white px-12 py-10 text-white placeholder-white/50 focus:outline-none focus:ring-1 focus:ring-white">
				</div>
			</fieldset>

			<!-- Ihr Foto -->
			<div>
				<p class="font-sans-bold text-md md:text-lg mb-16">Ihr Foto</p>

				<input
					ref="fileInput"
					type="file"
					accept="image/*"
					class="hidden"
					@change="onFile($event.target.files?.[0])">

				<button
					v-if="!hasPortrait && !cutoutBusy"
					type="button"
					class="font-sans-bold leading-none px-16 py-12 xl:px-20 xl:py-16 bg-white text-accent cursor-pointer"
					@click="fileInput?.click()">
					Foto hochladen
				</button>

				<div v-else>
					<!-- Crop stage: vue-advanced-cropper with a fixed-aspect custom stencil.
					     The sign overlay lives inside the stencil (Stencil.vue). -->
					<div class="relative w-full max-w-sm border border-white">
						<Cropper
							ref="cropperRef"
							:src="portraitPreview"
							:stencil-component="Stencil"
							:stencil-props="{
								aspectRatio: cropAspect,
								overlayUrl: signOverlapsPortrait && selectedStyle ? selectedStyle.url : null,
								overlayStyle: overlayStyle,
							}"
							:default-size="defaultSize"
							:default-position="defaultPosition" />

						<!-- Cutout progress -->
						<div
							v-if="cutoutBusy"
							class="absolute inset-0 flex flex-col items-center justify-center gap-8 bg-accent/85 text-white">
							<p class="font-sans-bold">Hintergrund entfernen…</p>
							<div class="h-1 w-40 overflow-hidden bg-white/30">
								<div
									class="h-full bg-white transition-all"
									:style="{ width: cutoutProgress + '%' }">
								</div>
							</div>
							<p class="text-tiny text-white/70">Läuft lokal auf Ihrem Gerät</p>
						</div>
					</div>

					<p
						v-if="!cutoutBusy"
						class="mt-8 text-tiny text-white/60">
						Rahmen verschieben oder an den Ecken ziehen, um den Ausschnitt zu wählen.
					</p>

					<div class="mt-12 flex gap-16">
						<button
							type="button"
							:disabled="cutoutBusy"
							class="font-sans-bold underline underline-offset-2 hover:no-underline cursor-pointer disabled:opacity-50"
							@click="fileInput?.click()">
							Foto ändern
						</button>
						<button
							v-if="!cutoutBusy"
							type="button"
							class="text-white/70 underline underline-offset-2 hover:no-underline cursor-pointer"
							@click="clearPortrait">
							Entfernen
						</button>
					</div>

					<label
						v-if="bgRemovalEnabled"
						class="mt-16 flex items-start gap-8">
						<input
							v-model="form.removeBg"
							type="checkbox"
							:disabled="cutoutBusy"
							class="mt-2 accent-white"
							@change="applyPortrait">
						<span>Hintergrund entfernen <span class="text-white/60">(im Browser, Ihr Foto bleibt lokal)</span></span>
					</label>
				</div>
			</div>

			<!-- Stil wählen -->
			<div>
				<p class="font-sans-bold text-md md:text-lg mb-16">Stil wählen</p>

				<div class="relative">
					<select
						v-model="form.signStyle"
						class="w-full appearance-none bg-transparent border border-white px-12 py-10 pr-40 text-white focus:outline-none focus:ring-1 focus:ring-white">
						<option
							value=""
							disabled
							class="text-black">
							Bitte wählen…
						</option>
						<option
							v-for="s in styles"
							:key="s.key"
							:value="s.key"
							class="text-black">
							{{ s.label }}
						</option>
					</select>
					<span class="pointer-events-none absolute right-12 top-1/2 -translate-y-1/2">
						<svg
							viewBox="0 0 12 7"
							class="w-12 fill-white"
							xmlns="http://www.w3.org/2000/svg">
							<path d="M0.877.5a.4.4 0 0 1 .256.108l4.303 4.327.353.356L10.472.608a.2.2 0 0 1 .225-.096.4.4 0 0 1 .262.122.4.4 0 0 1 .108.256.4.4 0 0 1-.108.255l-4.92 4.92a.6.6 0 0 1-.124.09.4.4 0 0 1-.185.023.3.3 0 0 1-.067-.006l-.057-.017-.053-.03a.8.8 0 0 1-.116-.062L.597 1.12A.36.36 0 0 1 .5.882.5.5 0 0 1 .622.608.4.4 0 0 1 .877.5Z"/>
						</svg>
					</span>
				</div>
				<p
					v-if="fieldErrors.ja_style"
					class="text-tiny mt-4">
					{{ fieldErrors.ja_style[0] }}
				</p>
			</div>

			<!-- Actions -->
			<div>
				<p
					v-if="error"
					class="mb-16 border border-white px-12 py-10 text-white">
					{{ error }}
				</p>
				<button
					type="button"
					:disabled="!canGenerate"
					class="font-sans-bold leading-none px-20 py-14 xl:px-24 xl:py-16 bg-white text-accent cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
					@click="generate">
					{{ generating ? 'Wird erstellt…' : 'erstellen' }}
				</button>
			</div>
		</div>
	</div>
</template>
