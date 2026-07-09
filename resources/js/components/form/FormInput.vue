<script setup>
// Text-style input bound with v-model. Any native attribute (type, id,
// maxlength, autocomplete, placeholder…) — and @focus — falls through to the
// <input>. Pass an `error` string to show a red message + border; the parent
// clears the error on focus (see ImageGenerator), so there's no local state.
defineOptions({ inheritAttrs: false });

const modelValue = defineModel({ type: String, default: '' });

defineProps({
	error: { type: String, default: '' },
});

const inputClass = 'w-full bg-transparent border-2 px-8 md:px-12 py-10 md:py-12 text-white placeholder-white/50 focus:outline-none!';
</script>

<template>
	<div>
		<input
			v-model="modelValue"
			v-bind="$attrs"
			:class="[inputClass, error ? 'border-error' : 'border-white']">
		<template v-if="error">
			<p class="text-error text-xs md:text-sm mt-4">
				{{ error }}
			</p>
		</template>
	</div>
</template>
