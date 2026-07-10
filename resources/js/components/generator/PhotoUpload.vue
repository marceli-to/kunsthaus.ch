<script setup>
import { ref } from 'vue';
import { Cropper } from 'vue-advanced-cropper';
import Stencil from './Stencil.vue';
import H4 from '../H4.vue';
import UploadButton from '../form/UploadButton.vue';

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
	// Validation message (e.g. "no photo chosen") shown below the upload button.
	error: { type: String, default: '' },
});

const removeBg = defineModel('removeBg', { type: Boolean, default: false });

const emit = defineEmits(['select', 'clear', 'toggle-bg']);

const uploadButton = ref(null);
const cropperRef = ref(null);

// The parent reads the cropper result here when generating.
defineExpose({
	getCropperResult: () => cropperRef.value?.getResult() ?? null,
});
</script>

<template>
	<div>
		<H4>Ihr Foto</H4>

    <!-- Helper caption + action links arranged above the photo -->
    <template v-if="!cutoutBusy && hasPortrait">
      <p class="mt-8 md:mt-12 text-xs md:text-sm">
        Verschieben Sie den Rahmen, um den Bildausschnitt festzulegen.
      </p>
      <div class="mt-8 md:mt-12 flex flex-col items-start gap-4 text-xs md:text-sm">
        <button
          v-if="bgRemovalEnabled"
          type="button"
          class="decoration-1 underline underline-offset-2 md:underline-offset-4 hover:no-underline cursor-pointer"
          @click="removeBg = !removeBg; emit('toggle-bg')">{{ removeBg ? 'Hintergrund wiederherstellen' : 'Hintergrund entfernen' }}</button>
        <button
          type="button"
          class="decoration-1 underline underline-offset-2 md:underline-offset-4 hover:no-underline cursor-pointer"
          @click="uploadButton?.open()">Anderes Bild hochladen</button>
      </div>
    </template>

		<UploadButton
			ref="uploadButton"
			label="Foto hochladen"
			:class="{ hidden: hasPortrait || cutoutBusy }"
			@select="emit('select', $event)" />

		<template v-if="error && !hasPortrait && !cutoutBusy">
			<p class="text-error text-xs md:text-sm mt-4">
				{{ error }}
			</p>
		</template>

		<template v-if="hasPortrait || cutoutBusy">
			<div class="mt-8 md:mt-12">
				<div class="relative w-fit max-w-full border border-white">
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
					<template v-if="cutoutBusy">
						<div class="absolute inset-0 flex flex-col items-center justify-center gap-8 bg-accent/85 text-white">
							<p class="font-sans-bold">Hintergrund entfernen…</p>
							<div class="h-1 w-40 overflow-hidden bg-white/30">
								<div
									class="h-full bg-white transition-all"
									:style="{ width: cutoutProgress + '%' }">
								</div>
							</div>
						</div>
					</template>
				</div>
			</div>
		</template>
	</div>
</template>
