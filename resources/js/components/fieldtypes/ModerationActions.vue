<script setup>
import { computed, ref } from 'vue';
import { Fieldtype } from '@statamic/cms';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose } = Fieldtype.use(emit, props);
defineExpose(expose);

const busy = ref(false);

const status = computed(() => props.value?.status ?? null);
const isSubmitted = computed(() => status.value === 'submitted');

const label = computed(() => {
    if (status.value === 'published') return { text: '✓ Veröffentlicht', kind: 'ok' };
    if (status.value === 'rejected') return { text: '✗ Abgelehnt', kind: 'bad' };
    return null;
});

async function run(url, confirmText) {
    if (busy.value || !url) return;
    if (!window.confirm(confirmText)) return;

    busy.value = true;
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': props.value?.csrf ?? '',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!res.ok) throw new Error(res.status);
        window.location.reload();
    } catch (e) {
        busy.value = false;
        window.alert('Aktion fehlgeschlagen. Bitte erneut versuchen.');
    }
}

function publish() {
    run(props.value?.publish_url, 'Dieses Bild freigeben und den Ersteller benachrichtigen?');
}

function reject() {
    run(props.value?.reject_url, 'Dieses Bild ablehnen? Der Ersteller wird nicht benachrichtigt.');
}
</script>

<template>
    <div class="kh-moderation">
        <template v-if="isSubmitted">
            <button type="button" class="kh-btn kh-btn--accept" :disabled="busy" @click="publish">
                Freigeben &amp; benachrichtigen
            </button>
            <button type="button" class="kh-btn kh-btn--deny" :disabled="busy" @click="reject">
                Ablehnen
            </button>
        </template>
        <span v-else-if="label" class="kh-status" :class="`kh-status--${label.kind}`">{{ label.text }}</span>
        <span v-else class="kh-empty">—</span>
    </div>
</template>

<style scoped>
.kh-moderation {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.6rem;
}

.kh-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.55rem 1rem;
    border-radius: 0.375rem;
    border: 1px solid transparent;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: filter 0.15s ease, opacity 0.15s ease;
}

.kh-btn:disabled {
    opacity: 0.55;
    cursor: default;
}

.kh-btn:not(:disabled):hover {
    filter: brightness(0.94);
}

.kh-btn--accept {
    background-color: #16a34a;
    color: #fff;
}

.kh-btn--deny {
    background-color: transparent;
    border-color: color-mix(in srgb, #dc2626 55%, transparent);
    color: #dc2626;
}

.kh-status {
    display: inline-flex;
    align-items: center;
    padding: 0.4rem 0.75rem;
    border-radius: 0.375rem;
    font-weight: 600;
    border: 1px solid color-mix(in srgb, currentColor 30%, transparent);
}

.kh-status--ok {
    color: #16a34a;
}

.kh-status--bad {
    color: #dc2626;
}

.kh-empty {
    opacity: 0.4;
}
</style>
