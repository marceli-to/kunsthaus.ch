<script setup>
// Sign-style dropdown. Two-way bound via v-model; options come from config.
const modelValue = defineModel({ type: String, default: '' });

defineProps({
	styles: { type: Array, default: () => [] },
	fieldErrors: { type: Object, default: () => ({}) },
});
</script>

<template>
	<div>
		<p class="font-sans-bold text-md md:text-lg mb-16">Stil wählen</p>

		<div class="relative">
			<select
				v-model="modelValue"
				class="w-full appearance-none bg-transparent border border-white px-12 py-10 pr-40 text-white focus:outline-none focus:ring-1 focus:ring-white">
				<option
					value=""
					disabled
					class="text-black">
					Bitte wählen…
				</option>
				<option
					v-for="s in styles"
					:key="s.key"
					:value="s.key"
					class="text-black">
					{{ s.label }}
				</option>
			</select>
			<span class="pointer-events-none absolute right-12 top-1/2 -translate-y-1/2">
				<svg
					viewBox="0 0 12 7"
					class="w-12 fill-white"
					xmlns="http://www.w3.org/2000/svg">
					<path d="M0.877.5a.4.4 0 0 1 .256.108l4.303 4.327.353.356L10.472.608a.2.2 0 0 1 .225-.096.4.4 0 0 1 .262.122.4.4 0 0 1 .108.256.4.4 0 0 1-.108.255l-4.92 4.92a.6.6 0 0 1-.124.09.4.4 0 0 1-.185.023.3.3 0 0 1-.067-.006l-.057-.017-.053-.03a.8.8 0 0 1-.116-.062L.597 1.12A.36.36 0 0 1 .5.882.5.5 0 0 1 .622.608.4.4 0 0 1 .877.5Z"/>
				</svg>
			</span>
		</div>
		<p
			v-if="fieldErrors.ja_style"
			class="text-tiny mt-4">
			{{ fieldErrors.ja_style[0] }}
		</p>
	</div>
</template>
