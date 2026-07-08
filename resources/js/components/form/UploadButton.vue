<script setup>
import { ref } from 'vue';
import BaseButton from '../BaseButton.vue';

// File-upload button: a styled button that opens a hidden <input type="file">
// and emits the chosen file via `select`. `accept` sets the input filter;
// `hint` shows accepted types / max size below the button.
//
// The accept/hint defaults mirror the server rules in config/composite.php
// (enforced there and mirrored for fail-fast validation in usePortraitSource).
// They are hardcoded on purpose — update them here if those rules change.
defineProps({
	label: { type: String, default: 'Datei hochladen' },
	accept: { type: String, default: '.jpeg,.jpg,.png,.webp' },
	hint: { type: String, default: 'JPEG, PNG, WEBP · max. 12 MB' },
});

const emit = defineEmits(['select']);

const fileInput = ref(null);

function onFileChange(event) {
	const file = event.target.files?.[0];
	// Reset the input so picking the same file again still fires "change".
	event.target.value = '';
	if (file) emit('select', file);
}

// Let the parent open the picker programmatically (e.g. a "change photo" link).
defineExpose({ open: () => fileInput.value?.click() });
</script>

<template>
	<div>
		<input
			ref="fileInput"
			type="file"
			:accept="accept"
			class="hidden"
			@change="onFileChange">

		<BaseButton @click="fileInput?.click()">
			{{ label }}
		</BaseButton>

		<template v-if="hint">
			<p class="mt-8 md:mt-12 text-xxs md:text-xs xl:text-sm">
				{{ hint }}
			</p>
		</template>
    
	</div>
</template>
