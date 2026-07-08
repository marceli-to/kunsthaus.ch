<script setup>
import BaseButton from '../BaseButton.vue';

// Final preview: the generated image plus download / start-over actions.
defineProps({
	url: { type: String, required: true },
});

defineEmits(['reset']);
</script>

<template>
	<div class="flex flex-col gap-24 max-w-md">

		<img
			:src="url"
			alt="Ihr «JA zum Kunsthaus» Bild"
			width="1080"
			height="1350"
			class="w-full h-auto border-2 border-white bg-white">

		<div class="flex gap-16 md:gap-24">
			<BaseButton
				:href="url"
				download="ja-zum-kunsthaus.jpg">
				Herunterladen
			</BaseButton>

			<BaseButton
				variant="ghost"
				@click="$emit('reset')">
				Neues Bild
			</BaseButton>

		</div>
		<p class="text-sm">Vorschau — noch nicht gespeichert oder veröffentlicht (Bestätigung folgt).</p>
	</div>
</template>
