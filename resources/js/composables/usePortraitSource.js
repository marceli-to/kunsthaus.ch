import { ref, onBeforeUnmount } from 'vue';
import { useBackgroundRemoval } from './useBackgroundRemoval';

// Owns the portrait source: file selection, the preview object URL (revoked on
// replace/unmount), and optional client-side background removal. `removeBg` and
// `bgRemovalEnabled` are refs the caller controls (the checkbox + config flag).
export function usePortraitSource({ removeBg, bgRemovalEnabled, onError }) {
	const { cutoutBusy, cutoutProgress, removeBackgroundFrom } = useBackgroundRemoval();

	const portraitPreview = ref('');     // object URL of the source (original or cutout)
	const portraitHasAlpha = ref(false); // true when the source is a transparent cutout
	const hasPortrait = ref(false);

	let originalFile = null;

	function setPortrait(fileOrBlob, hasAlpha) {
		if (portraitPreview.value) URL.revokeObjectURL(portraitPreview.value);
		portraitPreview.value = URL.createObjectURL(fileOrBlob);
		portraitHasAlpha.value = hasAlpha;
		hasPortrait.value = true;
	}

	async function applyPortrait() {
		if (!originalFile) return;
		if (removeBg.value && bgRemovalEnabled.value) {
			try {
				const blob = await removeBackgroundFrom(originalFile);
				setPortrait(blob, true);
			} catch {
				removeBg.value = false;
				setPortrait(originalFile, false);
				onError?.('Hintergrund entfernen hat nicht geklappt — das Originalfoto wird verwendet.');
			}
		} else {
			setPortrait(originalFile, false);
		}
	}

	async function selectFile(file) {
		if (!file) return;
		if (!file.type.startsWith('image/')) {
			onError?.('Bitte wählen Sie eine Bilddatei.');
			return;
		}
		onError?.('');
		originalFile = file;
		await applyPortrait();
	}

	function clearPortrait() {
		originalFile = null;
		removeBg.value = false;
		hasPortrait.value = false;
		if (portraitPreview.value) URL.revokeObjectURL(portraitPreview.value);
		portraitPreview.value = '';
	}

	onBeforeUnmount(() => {
		if (portraitPreview.value) URL.revokeObjectURL(portraitPreview.value);
	});

	return {
		portraitPreview,
		portraitHasAlpha,
		hasPortrait,
		cutoutBusy,
		cutoutProgress,
		selectFile,
		applyPortrait,
		clearPortrait,
	};
}
