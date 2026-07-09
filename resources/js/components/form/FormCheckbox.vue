<script setup>
// Checkbox with an inline label (slot). Bound with v-model (boolean). Native
// input attributes (disabled, name, @change…) pass through via `inputAttrs`.
// Styled to match FormInput: a white-bordered box on a transparent field that
// fills white with an accent check when selected. The real <input> is visually
// hidden (still focusable) and the box mirrors its state.
const modelValue = defineModel({ type: Boolean, default: false });

defineProps({
	inputAttrs: { type: Object, default: () => ({}) },
});
</script>

<template>
	<label class="flex items-start gap-16 md:gap-20 cursor-pointer">
		<input
			v-model="modelValue"
			v-bind="inputAttrs"
			type="checkbox"
			class="peer sr-only">
		<span
			class="mt-2 grid size-20 md:size-24 shrink-0 place-items-center border-2 border-white text-accent transition-colors peer-focus-visible:ring-1 peer-focus-visible:ring-white peer-disabled:opacity-50"
			:class="modelValue ? 'bg-white' : 'bg-transparent'">
			<svg
				v-show="modelValue"
				class="size-12"
				viewBox="0 0 12 12"
				fill="none"
				aria-hidden="true">
				<path
					d="M2 6.5 5 9.5 10 3"
					stroke="currentColor"
					stroke-width="2"
					stroke-linecap="round"
					stroke-linejoin="round" />
			</svg>
		</span>
		<span><slot /></span>
	</label>
</template>
