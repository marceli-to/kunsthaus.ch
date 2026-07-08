<script setup>
// Text-style input bound with v-model. Any native attribute (type, id,
// maxlength, autocomplete, placeholder…) falls through to the <input>. Pass an
// `error` string to show a validation message below the field.
defineOptions({ inheritAttrs: false });

const modelValue = defineModel({ type: String, default: '' });

defineProps({
	error: { type: String, default: '' },
});

const inputClass = 'w-full bg-transparent border-2 border-white px-8 md:px-12 py-10 md:py-12 text-white placeholder-white/50 focus:outline-none!';
</script>

<template>
	<div>
		<input
			v-model="modelValue"
			v-bind="$attrs"
			:class="inputClass">
		<template v-if="error">
			<p class="text-tiny mt-4">
				{{ error }}
			</p>
		</template>
	</div>
</template>
