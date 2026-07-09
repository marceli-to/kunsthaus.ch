<script setup>
import H4 from '../H4.vue';
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
	<H4>Persönliche Daten</H4>

  <div class="grid gap-20 md:gap-24 xl:gap-28">

		<div>
			<FormLabel
				for="firstname"
				required>
				Vorname
			</FormLabel>
			<FormInput
				id="firstname"
				v-model="firstName"
				type="text"
				maxlength="40"
				autocomplete="given-name"
				:error="fieldErrors.first_name?.[0]" />
		</div>

		<div>
			<FormLabel for="lastname" required>
				Name
			</FormLabel>
			<FormInput
				id="lastname"
				v-model="lastName"
				type="text"
				maxlength="40"
				autocomplete="family-name"
				:error="fieldErrors.last_name?.[0]" />
		</div>

		<div>
			<FormLabel for="email"	required>
				E-Mail
			</FormLabel>
			<FormInput
				id="email"
				v-model="email"
				type="email"
				autocomplete="email" />
		</div>
	</div>

</template>
