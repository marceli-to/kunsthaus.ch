<script setup>
import FormLabel from '../form/FormLabel.vue';
import FormInput from '../form/FormInput.vue';

// Name / Vorname / E-Mail inputs. Two-way bound via v-model:field on the parent.
const lastName = defineModel('lastName', { type: String, default: '' });
const firstName = defineModel('firstName', { type: String, default: '' });
const email = defineModel('email', { type: String, default: '' });

defineProps({
	fieldErrors: { type: Object, default: () => ({}) },
});
</script>

<template>
  <h4 class="font-sans-bold text-sm md:text-md xl:text-lg">
    Persönliche Daten
  </h4>

	<div class="grid gap-20 md:gap-24 xl:gap-28">

		<div>
			<FormLabel
				for="ja-last"
				required>
				Name
			</FormLabel>
			<FormInput
				id="ja-last"
				v-model="lastName"
				type="text"
				maxlength="40"
				autocomplete="family-name"
				:error="fieldErrors.last_name?.[0]" />
		</div>

		<div>
			<FormLabel
				for="ja-first"
				required>
				Vorname
			</FormLabel>
			<FormInput
				id="ja-first"
				v-model="firstName"
				type="text"
				maxlength="40"
				autocomplete="given-name"
				:error="fieldErrors.first_name?.[0]" />
		</div>

		<div>
			<FormLabel
				for="ja-email"
				required>
				E-Mail
			</FormLabel>
			<FormInput
				id="ja-email"
				v-model="email"
				type="email"
				autocomplete="email" />
		</div>
	</div>

</template>
