<script setup>
import { computed } from 'vue';
import { StencilPreview, BoundingBox, DraggableArea } from 'vue-advanced-cropper';

// Custom rectangle stencil for vue-advanced-cropper: fixed aspect ratio,
// corner handles only, plus a live overlay image (the sign) inside the frame.
const props = defineProps({
	// Passed by the Cropper itself
	image: Object,
	coordinates: Object,
	transitions: Object,
	stencilCoordinates: Object,
	// Via stencil-props
	aspectRatio: Number,
	overlayUrl: String, // null/undefined hides the overlay (stacked layout)
	overlayStyle: Object, // left/top/width in % relative to the frame
});

const emit = defineEmits(['move', 'move-end', 'resize', 'resize-end']);

const style = computed(() => {
	const { height, width, left, top } = props.stencilCoordinates;
	const s = {
		width: `${width}px`,
		height: `${height}px`,
		transform: `translate(${left}px, ${top}px)`,
	};
	if (props.transitions?.enabled) {
		s.transition = `${props.transitions.time}ms ${props.transitions.timingFunction}`;
	}
	return s;
});

// Corner handles only — with a fixed aspect ratio, edge handles add nothing.
const handlers = { eastNorth: true, westNorth: true, eastSouth: true, westSouth: true };

// The Cropper queries the stencil for its aspect-ratio constraints.
defineExpose({
	aspectRatios: () => ({ minimum: props.aspectRatio, maximum: props.aspectRatio }),
});
</script>

<template>
	<div
		class="absolute cursor-move"
		:style="style">
		<BoundingBox
			:width="stencilCoordinates.width"
			:height="stencilCoordinates.height"
			:transitions="transitions"
			:handlers="handlers"
			:handlers-classes="{ default: 'size-14 border border-accent' }"
			:lines="{}"
			@resize="emit('resize', $event)"
			@resize-end="emit('resize-end')">
			<DraggableArea
				@move="emit('move', $event)"
				@move-end="emit('move-end')">
				<StencilPreview
					class="ring-1 ring-white"
					:image="image"
					:coordinates="coordinates"
					:width="stencilCoordinates.width"
					:height="stencilCoordinates.height"
					:transitions="transitions" />
			</DraggableArea>
		</BoundingBox>
		<img
			v-if="overlayUrl"
			:src="overlayUrl"
			alt=""
			draggable="false"
			class="pointer-events-none absolute"
			:style="overlayStyle" />
	</div>
</template>
