<script setup>
import { computed, ref } from 'vue';
import BaseButton from '../BaseButton.vue';
import FormCheckbox from '../form/FormCheckbox.vue';

// Preview + confirm step. The image is generated but NOT yet delivered.
//
// `public` (landing): the visitor must consent to PUBLICATION ("Verwenden")
// before we store a record and email a copy — POST /api/submit.
//
// `private` (/jatelier, employees): the image is for the person's OWN use, so
// there is no publish-consent and nothing is stored. One click emails it and
// reveals the download — POST /api/deliver.
const props = defineProps({
	url: { type: String, required: true },      // signed temp preview URL
	previewId: { type: String, required: true },
	email: { type: String, required: true },
	mode: { type: String, default: 'public' },
});

defineEmits(['reset']);

const isPrivate = computed(() => props.mode === 'private');

const consent = ref(false);
const submitting = ref(false);
const error = ref('');
const downloadUrl = ref('');   // set once submitted/delivered → permanent, signed

async function submit() {
	// Public needs consent; private delivers straight away.
	if ((!isPrivate.value && !consent.value) || submitting.value) return;

	submitting.value = true;
	error.value = '';

	try {
		const endpoint = isPrivate.value ? '/api/deliver' : '/api/submit';
		const payload = isPrivate.value
			? { preview_id: props.previewId, email: props.email }
			: { preview_id: props.previewId, email: props.email, consent: true };

		const res = await fetch(endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				Accept: 'application/json',
			},
			body: JSON.stringify(payload),
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

		<!-- Delivered: permanent download + confirmation -->
		<template v-if="downloadUrl">
      <div class="space-y-16 md:space-y-24">
        <div v-if="isPrivate">Ihr Bild wurde an {{ email }} gesendet. Sie können es hier auch direkt herunterladen.</div>
        <div v-else>Vielen Dank! Ihr Bild wurde gespeichert und wird nun geprüft. Nach der Freigabe erhalten Sie eine E-Mail als Bestätigung.</div>
        <div class="flex flex-col items-center gap-8 md:gap-16">
          <BaseButton
            class="w-full"
            :href="downloadUrl"
            download="ja-zum-kunsthaus.jpg">
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

		<!-- Preview: confirm (public: + publish-consent) -->
		<template v-else>
      <div class="space-y-16 md:space-y-24">

        <FormCheckbox v-if="!isPrivate" v-model="consent">
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
            :disabled="(!isPrivate && !consent) || submitting"
            @click="submit">
            <template v-if="isPrivate">{{ submitting ? 'Wird gesendet…' : 'Herunterladen &amp; per E-Mail erhalten' }}</template>
            <template v-else>{{ submitting ? 'Wird gespeichert…' : 'Verwenden' }}</template>
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
