<script setup>
// Final preview: the generated image plus download / start-over actions.
defineProps({
	url: { type: String, required: true },
});

defineEmits(['reset']);
</script>

<template>
	<div class="flex flex-col gap-24">
		<img
			:src="url"
			alt="Ihr «JA zum Kunsthaus» Bild"
			class="w-full max-w-md self-center border-2 border-white bg-white">
		<div class="flex flex-wrap gap-12">
			<a
				:href="url"
				download="ja-zum-kunsthaus.jpg"
				class="font-sans-bold leading-none px-16 py-12 xl:px-20 xl:py-16 bg-white text-accent">
				Herunterladen
			</a>
			<button
				type="button"
				class="font-sans-bold leading-none px-16 py-12 xl:px-20 xl:py-16 border-2 border-white text-white cursor-pointer"
				@click="$emit('reset')">
				Neues Bild
			</button>
		</div>
		<p class="text-tiny text-white/70">Vorschau — noch nicht gespeichert oder veröffentlicht (Bestätigung folgt).</p>
	</div>
</template>
