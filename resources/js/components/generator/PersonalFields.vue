<script setup>
// Name / Vorname / E-Mail inputs. Two-way bound via v-model:field on the parent.
const lastName = defineModel('lastName', { type: String, default: '' });
const firstName = defineModel('firstName', { type: String, default: '' });
const email = defineModel('email', { type: String, default: '' });

defineProps({
	fieldErrors: { type: Object, default: () => ({}) },
});

const inputClass = 'w-full bg-transparent border border-white px-12 py-10 text-white placeholder-white/50 focus:outline-none focus:ring-1 focus:ring-white';
</script>

<template>
	<fieldset class="grid gap-16">
		<legend class="font-sans-bold text-md md:text-lg mb-8">Persönliche Daten</legend>

		<div>
			<label
				for="ja-last"
				class="font-sans-bold block mb-8">
				Name*
			</label>
			<input
				id="ja-last"
				v-model="lastName"
				type="text"
				maxlength="40"
				autocomplete="family-name"
				:class="inputClass">
			<p
				v-if="fieldErrors.last_name"
				class="text-tiny mt-4">
				{{ fieldErrors.last_name[0] }}
			</p>
		</div>

		<div>
			<label
				for="ja-first"
				class="font-sans-bold block mb-8">
				Vorname*
			</label>
			<input
				id="ja-first"
				v-model="firstName"
				type="text"
				maxlength="40"
				autocomplete="given-name"
				:class="inputClass">
			<p
				v-if="fieldErrors.first_name"
				class="text-tiny mt-4">
				{{ fieldErrors.first_name[0] }}
			</p>
		</div>

		<div>
			<label
				for="ja-email"
				class="font-sans-bold block mb-8">
				E-Mail*
			</label>
			<input
				id="ja-email"
				v-model="email"
				type="email"
				autocomplete="email"
				:class="inputClass">
		</div>
	</fieldset>
</template>
