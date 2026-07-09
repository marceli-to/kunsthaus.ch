<script setup>
import { ref, computed } from 'vue';
import BaseButton from '../BaseButton.vue';
import FormCheckbox from '../form/FormCheckbox.vue';

// Preview + confirm step. The image is generated but NOT yet stored: the visitor
// must give consent ("Verwenden") before we create a record and email a copy.
// On submit success we swap to the permanent download.
const props = defineProps({
	url: { type: String, required: true },      // signed temp preview URL
	previewId: { type: String, required: true },
	email: { type: String, required: true },
	firstName: { type: String, default: '' },
	lastName: { type: String, default: '' },
});

// Download filename: "ja-zum-kunsthaus-vorname-nachname.jpg", slugified so it's
// filesystem-safe (umlauts folded, spaces → dashes, other chars dropped).
const downloadName = computed(() => {
	const slug = `${props.firstName} ${props.lastName}`
		.toLowerCase()
		.normalize('NFD').replace(/[̀-ͯ]/g, '')  // strip diacritics
		.replace(/ß/g, 'ss')
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');
	return slug ? `ja-zum-kunsthaus-${slug}.jpg` : 'ja-zum-kunsthaus.jpg';
});

defineEmits(['reset']);

const consent = ref(false);
const submitting = ref(false);
const error = ref('');
const downloadUrl = ref('');   // set once submitted → permanent, signed

async function submit() {
	if (!consent.value || submitting.value) return;

	submitting.value = true;
	error.value = '';

	try {
		const res = await fetch('/api/submit', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				Accept: 'application/json',
			},
			body: JSON.stringify({
				preview_id: props.previewId,
				email: props.email,
				consent: true,
			}),
		});
		const json = await res.json().catch(() => ({}));

		if (!res.ok) {
			if (res.status === 429) error.value = 'Zu viele Anfragen — bitte warten Sie einen Moment.';
			else error.value = json.message ?? 'Etwas ist schiefgelaufen.';
			return;
		}
		downloadUrl.value = json.download_url;
	} catch {
		error.value = 'Netzwerkfehler — bitte versuchen Sie es erneut.';
	} finally {
		submitting.value = false;
	}
}
</script>

<template>
	<div class="flex flex-col gap-24 max-w-md">

		<img
			:src="downloadUrl || url"
			alt="Ihr «JA zum Kunsthaus» Bild"
			width="1080"
			height="1350"
			class="w-full h-auto border-2 border-white bg-white">

		<!-- Submitted: permanent download + confirmation -->
		<template v-if="downloadUrl">
      <div class="space-y-16 md:space-y-24">
        <div>Vielen Dank! Ihr Bild wurde gespeichert und wird nun geprüft. Nach der Freigabe erhalten Sie eine E-Mail als Bestätigung.</div>
        <div class="flex flex-col items-center gap-8 md:gap-16">
          <BaseButton
            class="w-full"
            :href="downloadUrl"
            :download="downloadName">
            Herunterladen
          </BaseButton>

          <BaseButton
            variant="ghost"
            @click="$emit('reset')">
            Neues Bild
          </BaseButton>
        </div>
      </div>
		</template>

		<!-- Preview: consent + confirm -->
		<template v-else>
      <div class="space-y-16 md:space-y-24">
        
        <FormCheckbox v-model="consent">
          Ich habe das Recht, dieses Foto und den eingegebenen Namen zu verwenden und bin damit einverstanden, dass mein Bild veröffentlicht wird
        </FormCheckbox>

        <template v-if="error">
          <div class="border border-white px-12 py-10 text-white">
            {{ error }}
          </div>
        </template>

        <div class="flex flex-col items-center gap-8 md:gap-16">
          <BaseButton
            class="w-full"
            :disabled="!consent || submitting"
            @click="submit">
            {{ submitting ? 'Wird gespeichert…' : 'Verwenden' }}
          </BaseButton>

          <BaseButton
            variant="ghost"
            @click="$emit('reset')">
            Neues Bild
          </BaseButton>
        </div>

      </div>
		</template>
	</div>
</template>
