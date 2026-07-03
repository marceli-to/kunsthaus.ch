// Renders the cropper's selection to a blob at the portrait box's aspect ratio.
// `getCropperResult` returns the cropper's { canvas } (or null); `geometry` and
// `portraitHasAlpha` are refs. Resolves to null when the cropper isn't ready or
// encoding fails.
export function useCropExport({ getCropperResult, geometry, portraitHasAlpha }) {
	function exportCrop() {
		const result = getCropperResult();
		if (!result?.canvas) return Promise.resolve(null);

		const portrait = geometry.value.portrait;
		const scaleUp = 1.5; // render above the 760px box for crisp downscaling
		const width = Math.round(portrait.w * scaleUp);
		const height = Math.round(portrait.h * scaleUp);

		const canvas = document.createElement('canvas');
		canvas.width = width;
		canvas.height = height;
		const ctx = canvas.getContext('2d');
		if (!portraitHasAlpha.value) {
			ctx.fillStyle = '#ffffff'; // flatten onto white for JPEG
			ctx.fillRect(0, 0, width, height);
		}
		ctx.drawImage(result.canvas, 0, 0, width, height);

		const type = portraitHasAlpha.value ? 'image/png' : 'image/jpeg';
		return new Promise((resolve) => canvas.toBlob(resolve, type, 0.92));
	}

	return { exportCrop };
}
