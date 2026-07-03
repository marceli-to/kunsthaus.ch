<script setup>
import { ref } from 'vue';

// File-upload button: a styled button that opens a hidden <input type="file">
// and emits the chosen file via `select`. `accept` sets the input filter;
// `hint` shows accepted types / max size below the button.
defineProps({
	label: { type: String, default: 'Datei hochladen' },
	accept: { type: String, default: 'image/*' },
	hint: { type: String, default: '' },
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

		<button
			type="button"
			class="font-sans-bold leading-none px-16 py-12 xl:px-20 xl:py-16 bg-white text-accent cursor-pointer"
			@click="fileInput?.click()">
			{{ label }}
		</button>
		<p v-if="hint" class="mt-8 md:mt-12 text-xxs md:text-xs xl:text-sm">
			{{ hint }}
		</p>
	</div>
</template>
