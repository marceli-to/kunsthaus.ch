<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useGeneratorConfig } from '../composables/useGeneratorConfig';
import { useGeometry } from '../composables/useGeometry';
import { usePortraitSource } from '../composables/usePortraitSource';
import { useCropExport } from '../composables/useCropExport';
import PersonalFields from './generator/PersonalFields.vue';
import PhotoUpload from './generator/PhotoUpload.vue';
import StyleSelect from './generator/StyleSelect.vue';
import ResultPreview from './generator/ResultPreview.vue';

// ── Config + geometry ──────────────────────────────────────────────────────
const { styles, bgRemovalEnabled, geometry, configError, loadConfig } = useGeneratorConfig();
const { cropAspect, overlayStyle, signOverlapsPortrait, defaultSize, defaultPosition } = useGeometry(geometry);

// ── Form state ─────────────────────────────────────────────────────────────
// DEV: prefill personal data on local hosts only (never in production).
const isLocalHost = /(^|\.)(test|localhost)$/.test(window.location.hostname)
	|| window.location.hostname === '127.0.0.1';

const form = reactive({
	lastName: isLocalHost ? 'Stadelmann' : '',   // "Name"
	firstName: isLocalHost ? 'Marcel' : '',      // "Vorname"
	email: isLocalHost ? 'marcel.stadelmann@gmail.com' : '',
	signStyle: '', // sent as "ja_style"
	removeBg: false,
});

const removeBg = computed({
	get: () => form.removeBg,
	set: (v) => { form.removeBg = v; },
});

const error = ref('');
const fieldErrors = reactive({});
const generating = ref(false);
const previewUrl = ref('');

// ── Portrait source (file, preview, background removal) ─────────────────────
const photoUpload = ref(null);
const {
	portraitPreview,
	portraitHasAlpha,
	hasPortrait,
	cutoutBusy,
	cutoutProgress,
	selectFile,
	applyPortrait,
	clearPortrait,
} = usePortraitSource({
	removeBg,
	bgRemovalEnabled,
	onError: (msg) => { error.value = msg; },
});

const { exportCrop } = useCropExport({
	getCropperResult: () => photoUpload.value?.getCropperResult() ?? null,
	geometry,
	portraitHasAlpha,
});

// ── Derived ────────────────────────────────────────────────────────────────
const emailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim()));
const selectedStyle = computed(() => styles.value.find((s) => s.key === form.signStyle) ?? null);
const overlayUrl = computed(() =>
	signOverlapsPortrait.value && selectedStyle.value ? selectedStyle.value.url : null,
);

const canGenerate = computed(() =>
	hasPortrait.value &&
	!!form.signStyle &&
	form.firstName.trim() !== '' &&
	form.lastName.trim() !== '' &&
	emailValid.value &&
	!generating.value &&
	!cutoutBusy.value,
);

// ── Boot ───────────────────────────────────────────────────────────────────
onMounted(async () => {
	await loadConfig();
	if (configError.value) error.value = configError.value;
});

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
		<ResultPreview
			v-if="previewUrl"
			:url="previewUrl"
			@reset="reset" />

		<div
			v-else
			class="grid gap-32">
			<PersonalFields
				v-model:last-name="form.lastName"
				v-model:first-name="form.firstName"
				v-model:email="form.email"
				:field-errors="fieldErrors" />

			<PhotoUpload
				ref="photoUpload"
				v-model:remove-bg="form.removeBg"
				:portrait-preview="portraitPreview"
				:has-portrait="hasPortrait"
				:cutout-busy="cutoutBusy"
				:cutout-progress="cutoutProgress"
				:bg-removal-enabled="bgRemovalEnabled"
				:crop-aspect="cropAspect"
				:overlay-url="overlayUrl"
				:overlay-style="overlayStyle"
				:default-size="defaultSize"
				:default-position="defaultPosition"
				@select="selectFile"
				@clear="clearPortrait"
				@toggle-bg="applyPortrait" />

			<StyleSelect
				v-model="form.signStyle"
				:styles="styles"
				:field-errors="fieldErrors" />

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
