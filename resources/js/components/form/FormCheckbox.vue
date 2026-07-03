<script setup>
// Checkbox with an inline label (slot). Bound with v-model (boolean). Native
// input attributes (disabled, name, @change…) pass through via `inputAttrs`;
// any class/style on the tag styles the outer <label> wrapper as usual.
const modelValue = defineModel({ type: Boolean, default: false });

defineProps({
	inputAttrs: { type: Object, default: () => ({}) },
});
</script>

<template>
	<label class="flex items-start gap-8">
		<input
			v-model="modelValue"
			v-bind="inputAttrs"
			type="checkbox"
			class="mt-2 accent-white">
		<span><slot /></span>
	</label>
</template>
