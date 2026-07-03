<script setup>
// Text-style input bound with v-model. Any native attribute (type, id,
// maxlength, autocomplete, placeholder…) falls through to the <input>. Pass an
// `error` string to show a validation message below the field.
defineOptions({ inheritAttrs: false });

const modelValue = defineModel({ type: String, default: '' });

defineProps({
	error: { type: String, default: '' },
});

const inputClass = 'w-full bg-transparent border border-white px-12 py-10 text-white placeholder-white/50 focus:outline-none focus:ring-1 focus:ring-white';
</script>

<template>
	<div>
		<input
			v-model="modelValue"
			v-bind="$attrs"
			:class="inputClass">
		<p
			v-if="error"
			class="text-tiny mt-4">
			{{ error }}
		</p>
	</div>
</template>
