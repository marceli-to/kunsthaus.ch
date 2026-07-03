<script setup>
import { ref } from 'vue';
import { Cropper } from 'vue-advanced-cropper';
import Stencil from './Stencil.vue';

// Photo step: upload button → fixed-aspect cropper (with live sign overlay) →
// change/remove actions and the optional background-removal toggle. All source
// state lives in the parent's usePortraitSource(); this component is the view.
const props = defineProps({
	portraitPreview: { type: String, default: '' },
	hasPortrait: { type: Boolean, default: false },
	cutoutBusy: { type: Boolean, default: false },
	cutoutProgress: { type: Number, default: 0 },
	bgRemovalEnabled: { type: Boolean, default: false },
	// Geometry-derived crop props
	cropAspect: { type: Number, required: true },
	overlayUrl: { type: String, default: null },
	overlayStyle: { type: Object, default: () => ({}) },
	defaultSize: { type: Function, required: true },
	defaultPosition: { type: Function, required: true },
	// Upload hint (accepted types + max size) + the input's accept attribute,
	// both sourced from config.
	acceptHint: { type: String, default: '' },
	acceptAttr: { type: String, default: 'image/*' },
});

const removeBg = defineModel('removeBg', { type: Boolean, default: false });

const emit = defineEmits(['select', 'change-photo', 'clear', 'toggle-bg']);

const fileInput = ref(null);
const cropperRef = ref(null);

function onFileChange(event) {
	const file = event.target.files?.[0];
	// Reset the input so picking the same file again still fires "change".
	event.target.value = '';
	if (file) emit('select', file);
}

// The parent reads the cropper result here when generating.
defineExpose({
	getCropperResult: () => cropperRef.value?.getResult() ?? null,
});
</script>

<template>
	<div>
		<p class="font-sans-bold text-md md:text-lg mb-16">Ihr Foto</p>

		<input
			ref="fileInput"
			type="file"
			:accept="acceptAttr"
			class="hidden"
			@change="onFileChange">

		<template v-if="!hasPortrait && !cutoutBusy">
			<button
				type="button"
				class="font-sans-bold leading-none px-16 py-12 xl:px-20 xl:py-16 bg-white text-accent cursor-pointer"
				@click="fileInput?.click()">
				Foto hochladen
			</button>
			<p
				v-if="acceptHint"
				class="mt-8 text-tiny text-white/60">
				{{ acceptHint }}
			</p>
		</template>

		<div v-else>
			<!-- Crop stage: vue-advanced-cropper with a fixed-aspect custom stencil. The sign overlay lives inside the stencil (Stencil.vue). -->
			<div class="relative w-full max-w-sm border border-white">
				<Cropper
					ref="cropperRef"
					:src="portraitPreview"
					:stencil-component="Stencil"
					:stencil-props="{
						aspectRatio: cropAspect,
						overlayUrl: overlayUrl,
						overlayStyle: overlayStyle,
					}"
					:default-size="defaultSize"
					:default-position="defaultPosition"
					:resize-image="false"
					:move-image="false" />

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
					@click="emit('clear')">
					Entfernen
				</button>
			</div>

			<label
				v-if="bgRemovalEnabled"
				class="mt-16 flex items-start gap-8">
				<input
					v-model="removeBg"
					type="checkbox"
					:disabled="cutoutBusy"
					class="mt-2 accent-white"
					@change="emit('toggle-bg')">
				<span>Hintergrund entfernen <span class="text-white/60">(im Browser, Ihr Foto bleibt lokal)</span></span>
			</label>
		</div>
	</div>
</template>
