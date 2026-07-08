<script setup>
import { ref } from 'vue';
import { Cropper } from 'vue-advanced-cropper';
import Stencil from './Stencil.vue';
import H4 from '../H4.vue';
import BaseButton from '../BaseButton.vue';
import UploadButton from '../form/UploadButton.vue';
import FormCheckbox from '../form/FormCheckbox.vue';

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

		<UploadButton
			ref="uploadButton"
			label="Foto hochladen"
			:class="{ hidden: hasPortrait || cutoutBusy }"
			@select="emit('select', $event)" />

		<template v-if="hasPortrait || cutoutBusy">
			<div>
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

				<div class="mt-16 md:mt-24 flex gap-16 md:gap-24">
					<BaseButton
						:disabled="cutoutBusy"
						@click="uploadButton?.open()">
						Foto ändern
					</BaseButton>
					<template v-if="!cutoutBusy">
						<BaseButton
							variant="outline"
							@click="emit('clear')">
							Entfernen
						</BaseButton>
					</template>
				</div>

				<template v-if="bgRemovalEnabled">
					<FormCheckbox
						v-model="removeBg"
						:input-attrs="{ disabled: cutoutBusy, onChange: () => emit('toggle-bg') }"
						class="mt-16">
						Hintergrund entfernen <span class="text-white/60">(im Browser, Ihr Foto bleibt lokal)</span>
					</FormCheckbox>
				</template>
			</div>
		</template>
	</div>
</template>
