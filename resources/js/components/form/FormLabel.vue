<script setup>
// Bold field label. `for` associates it with an input's id; `required` appends
// the asterisk marker used across the form.
defineProps({
	for: { type: String, default: null },
	required: { type: Boolean, default: false },
});
</script>

<template>
	<label
		:for="$props.for"
		class="block mb-8">
		<slot /><template v-if="required">*</template>
	</label>
</template>
