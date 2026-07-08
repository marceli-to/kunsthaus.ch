<script setup>
import H4 from '../H4.vue';
import FormSelect from '../form/FormSelect.vue';

// Sign-style dropdown. Two-way bound via v-model; options come from config.
const modelValue = defineModel({ type: String, default: '' });

defineProps({
	styles: { type: Array, default: () => [] },
	fieldErrors: { type: Object, default: () => ({}) },
});
</script>

<template>
	<div>
		<H4>Stil wählen</H4>

		<FormSelect
			v-model="modelValue"
			:options="styles"
			:error="fieldErrors.ja_style?.[0]" />
	</div>
</template>
