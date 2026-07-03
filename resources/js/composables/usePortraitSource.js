import { ref, onBeforeUnmount } from 'vue';
import { useBackgroundRemoval } from './useBackgroundRemoval';

// Reads a file's pixel dimensions (via a throwaway object URL).
function readImageSize(file) {
	return new Promise((resolve, reject) => {
		const url = URL.createObjectURL(file);
		const img = new Image();
		img.onload = () => {
			URL.revokeObjectURL(url);
			resolve({ width: img.naturalWidth, height: img.naturalHeight });
		};
		img.onerror = () => {
			URL.revokeObjectURL(url);
			reject(new Error('decode failed'));
		};
		img.src = url;
	});
}

// Owns the portrait source: file selection, the preview object URL (revoked on
// replace/unmount), and optional client-side background removal. `removeBg` and
// `bgRemovalEnabled` are refs the caller controls (the checkbox + config flag);
// `uploadLimits` mirrors the server's config/composite.php upload rules so we
// can reject bad files before the round-trip.
export function usePortraitSource({ removeBg, bgRemovalEnabled, uploadLimits, onError }) {
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

	// Mirrors the server rules (mimes, max size, min dimension) so a bad upload
	// fails fast in the browser instead of after the generate round-trip.
	// Returns an error message, or '' when the file is acceptable.
	async function validateFile(file) {
		const limits = uploadLimits.value;

		const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
		const isImage = file.type.startsWith('image/');
		if (!isImage || (limits.mimes.length && !limits.mimes.includes(ext))) {
			return 'Bitte wählen Sie ein Bild (' + limits.mimes.join(', ').toUpperCase() + ').';
		}

		if (file.size > limits.max_kb * 1024) {
			return 'Das Bild ist zu gross (max. ' + Math.round(limits.max_kb / 1024) + ' MB).';
		}

		try {
			const { width, height } = await readImageSize(file);
			if (Math.min(width, height) < limits.min_dimension) {
				return 'Das Bild ist zu klein (min. ' + limits.min_dimension + 'px).';
			}
		} catch {
			return 'Die Bilddatei konnte nicht gelesen werden.';
		}

		return '';
	}

	async function selectFile(file) {
		if (!file) return;
		const problem = await validateFile(file);
		if (problem) {
			onError?.(problem);
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
