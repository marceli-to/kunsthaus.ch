<script setup>
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
		<h4 class="font-sans-bold text-sm md:text-md xl:text-lg mb-8 md:mb-12">
      Stil wählen
    </h4>

		<FormSelect
			v-model="modelValue"
			:options="styles"
			:error="fieldErrors.ja_style?.[0]" />
	</div>
</template>
