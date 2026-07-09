<script setup>
// Shared button used across the generator.
//   variant: 'solid'   — white fill, accent text (default, primary action)
//            'outline'  — white border, transparent (secondary action)
//            'ghost'    — no fill/border, white text (tertiary action)
//   size:    'md'       — default padding (inherits 16px text)
//            'sm'       — compact (tighter padding, same text)
//            'lg'       — larger padding (main CTA)
// Renders an <a> when `href` is set (e.g. a download link), otherwise a
// <button type="button">. Native attrs (disabled, download, @click…) fall
// through to the rendered element.
defineOptions({ inheritAttrs: false });

defineProps({
	variant: { type: String, default: 'solid' },
	size: { type: String, default: 'md' },
	href: { type: String, default: null },
});

const base = 'font-sans-bold leading-none text-center cursor-pointer disabled:cursor-not-allowed disabled:opacity-50';

const sizes = {
	sm: 'px-12 py-8 xl:px-16 xl:py-12',
	md: 'px-16 py-12 xl:px-20 xl:py-16',
	lg: 'px-20 py-14 xl:px-24 xl:py-16',
};

const variants = {
	solid: 'bg-white text-accent',
	outline: 'border-2 border-white text-white',
	ghost: 'text-white hover:underline decoration-2 underline-offset-4',
};
</script>

<template>
	<component
		:is="href ? 'a' : 'button'"
		:href="href"
		:type="href ? null : 'button'"
		:class="[base, sizes[size] ?? sizes.md, variants[variant] ?? variants.solid]"
		v-bind="$attrs">
		<slot />
	</component>
</template>
