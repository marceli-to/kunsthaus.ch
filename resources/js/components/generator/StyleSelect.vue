<script setup>
import { computed } from 'vue';
import H4 from '../H4.vue';
import FormSelect from '../form/FormSelect.vue';

// Style picker: a styled <select> with a preview thumbnail of the selected
// style shown below it. Two-way bound via v-model on the style key; options
// come from config.
const modelValue = defineModel({ type: String, default: '' });

const props = defineProps({
	styles: { type: Array, default: () => [] },
	fieldErrors: { type: Object, default: () => ({}) },
});

const selected = computed(() => props.styles.find((s) => s.key === modelValue.value) ?? null);
</script>

<template>
	<div>
		<H4>Stil wählen</H4>

		<FormSelect
			v-model="modelValue"
			:options="styles"
			:error="fieldErrors.ja_style?.[0]" />

		<!-- Preview of the selected style -->
		<span
			v-if="selected"
			class="mt-12 block aspect-square w-128 overflow-hidden">
			<img
				:src="selected.url"
				:alt="selected.label"
				class="h-full w-full object-cover" />
		</span>
	</div>
</template>
