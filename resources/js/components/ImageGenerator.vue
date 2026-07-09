<script setup>
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue';
import { useGeneratorConfig } from '../composables/useGeneratorConfig';
import { useGeometry } from '../composables/useGeometry';
import { usePortraitSource } from '../composables/usePortraitSource';
import { useCropExport } from '../composables/useCropExport';
import BaseButton from './BaseButton.vue';
import PersonalFields from './generator/PersonalFields.vue';
import PhotoUpload from './generator/PhotoUpload.vue';
import StyleSelect from './generator/StyleSelect.vue';
import ResultPreview from './generator/ResultPreview.vue';

// ── Config + geometry ──────────────────────────────────────────────────────
const { styles, bgRemovalEnabled, geometry, uploadLimits, configError, loadConfig } = useGeneratorConfig();
const { cropAspect, overlayStyle, signOverlapsPortrait, defaultSize, defaultPosition } = useGeometry(geometry);

// Note: the accepted-types/max-size shown under "Foto hochladen" are hardcoded
// in UploadButton.vue. The server (config/composite.php) remains the source of
// truth for enforcement — mirrored client-side by usePortraitSource for
// fail-fast validation via `uploadLimits`.

// ── Form state ─────────────────────────────────────────────────────────────
const form = reactive({
	lastName: '',   // "Name"
	firstName: '',  // "Vorname"
	email: '',
	signStyle: '', // sent as "ja_style"
	removeBg: false,
});

const removeBg = computed({
	get: () => form.removeBg,
	set: (v) => { form.removeBg = v; },
});

const rootEl = ref(null);
const error = ref('');
const fieldErrors = reactive({});

// The result view is shorter than the form; bring the generator back into view
// so the visitor isn't left staring at whitespace below it.
function scrollToTop() {
	nextTick(() => {
		rootEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
	});
}
const generating = ref(false);
const previewUrl = ref('');
const previewId = ref('');

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
	uploadLimits,
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

// The button stays inactive until the visitor has a photo, a style, both names
// and a valid email — so it only lights up once the form can actually be sent.
const canGenerate = computed(() =>
	hasPortrait.value &&
	!!form.signStyle &&
	form.firstName.trim() !== '' &&
	form.lastName.trim() !== '' &&
	emailValid.value &&
	!generating.value &&
	!cutoutBusy.value,
);

// Clear a field's error as soon as the visitor edits it, so fixed fields don't
// keep showing a stale message.
watch(() => form.firstName, () => { delete fieldErrors.first_name; });
watch(() => form.lastName, () => { delete fieldErrors.last_name; });
watch(() => form.email, () => { delete fieldErrors.email; });
watch(() => form.signStyle, () => { delete fieldErrors.ja_style; });
watch(hasPortrait, () => { delete fieldErrors.portrait; });

// Also clear on focus (fields emit clear-error). The message stays gone until
// the next submit re-runs validate() and re-adds it if still invalid.
function clearFieldError(key) {
	delete fieldErrors[key];
}

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
		body.append('background_removed', form.removeBg ? '1' : '0');

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
		previewId.value = json.preview_id;
		scrollToTop();
	} catch {
		error.value = 'Netzwerkfehler — bitte versuchen Sie es erneut.';
	} finally {
		generating.value = false;
	}
}

function reset() {
	previewUrl.value = '';
	previewId.value = '';
	scrollToTop();
}
</script>

<template>
	<div
		ref="rootEl"
		class="scroll-mt-20 md:scroll-mt-32 xl:scroll-mt-48 text-white">

		<template v-if="previewUrl">
			<ResultPreview
				:url="previewUrl"
				:preview-id="previewId"
				:email="form.email"
				@reset="reset" />
		</template>

		<template v-else>
			<div class="space-y-20 md:space-y-32 xl:space-y-48">
				<PersonalFields
					v-model:last-name="form.lastName"
					v-model:first-name="form.firstName"
					v-model:email="form.email"
					:field-errors="fieldErrors"
					@clear-error="clearFieldError" />

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
					:error="fieldErrors.portrait?.[0]"
					@select="selectFile"
					@clear="clearPortrait"
					@toggle-bg="applyPortrait" />

				<StyleSelect
					v-model="form.signStyle"
					:styles="styles"
					:field-errors="fieldErrors"
					@clear-error="clearFieldError" />

				<!-- Actions -->
				<div>
					<template v-if="error">
						<p class="mb-16 border border-white px-12 py-10 text-white">
							{{ error }}
						</p>
					</template>
					<BaseButton
						size="lg"
						:disabled="!canGenerate"
						@click="generate">
						{{ generating ? 'Vorschau wird erstellt…' : 'Vorschau erstellen' }}
					</BaseButton>
				</div>
			</div>
		</template>
    
	</div>
</template>
