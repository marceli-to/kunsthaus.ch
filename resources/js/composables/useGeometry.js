import { computed } from 'vue';

// Derives the crop frame's aspect ratio and the live sign-overlay placement
// from the composite geometry, so what the visitor frames matches the server
// output. `geometry` is the ref returned by useGeneratorConfig().
export function useGeometry(geometry) {
	// Crop aspect straight from the portrait box.
	const cropAspect = computed(() => geometry.value.portrait.w / geometry.value.portrait.h);

	// Sign position/size as percentages relative to the portrait (crop) frame.
	const overlayStyle = computed(() => {
		const portrait = geometry.value.portrait;
		const sign = geometry.value.ja;
		return {
			left: `${((sign.x - portrait.x) / portrait.w) * 100}%`,
			top: `${((sign.y - portrait.y) / portrait.h) * 100}%`,
			width: `${(sign.w / portrait.w) * 100}%`,
		};
	});

	// The sign only overlaps the portrait in an overlapping layout. In a stacked
	// layout (sign below the portrait) there's nothing to overlay — the whole
	// crop shows, so the frame is already WYSIWYG.
	const signOverlapsPortrait = computed(() => {
		const portrait = geometry.value.portrait;
		const sign = geometry.value.ja;
		return sign.y < portrait.y + portrait.h
			&& sign.x < portrait.x + portrait.w
			&& sign.x + sign.w > portrait.x;
	});

	// Default crop: largest possible area, horizontally centered, top-aligned
	// (keeps the head in frame).
	function defaultSize({ imageSize }) {
		const width = Math.min(imageSize.width, imageSize.height * cropAspect.value);
		return { width, height: width / cropAspect.value };
	}

	function defaultPosition({ imageSize, coordinates }) {
		return { left: (imageSize.width - coordinates.width) / 2, top: 0 };
	}

	return { cropAspect, overlayStyle, signOverlapsPortrait, defaultSize, defaultPosition };
}
