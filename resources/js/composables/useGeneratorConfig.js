import { ref } from 'vue';

// Boot config for the generator island, fetched from /api/generator on mount.
// Single source of truth is config/composite.php (the server needs the geometry
// to build the image); the island reads it here so the crop frame matches.
// Geometry keys ("portrait", "ja") mirror the server config.
export function useGeneratorConfig() {
	const styles = ref([]);
	const bgRemovalEnabled = ref(false);
	const geometry = ref({
		portrait: { x: 90, y: 40, w: 900, h: 563 },
		ja: { x: 310, y: 650, w: 460, h: 460 },
	});
	const configError = ref('');

	async function loadConfig() {
		try {
			const res = await fetch('/api/generator');
			const json = await res.json();
			styles.value = json.styles ?? [];
			bgRemovalEnabled.value = !!json.bg_removal;
			if (json.geometry) geometry.value = json.geometry;
		} catch {
			configError.value = 'Der Generator konnte nicht geladen werden.';
		}
	}

	return { styles, bgRemovalEnabled, geometry, configError, loadConfig };
}
