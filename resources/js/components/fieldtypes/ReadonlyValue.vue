<script setup>
import { computed } from 'vue';
import { Fieldtype } from '@statamic/cms';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose } = Fieldtype.use(emit, props);
defineExpose(expose);

const isEmpty = computed(() => {
    const v = props.value;
    return v === null || v === undefined || v === '';
});

const display = computed(() => {
    if (isEmpty.value) return null;

    const v = props.value;
    const as = props.config?.as;

    if (as === 'bool') return v ? 'Ja' : 'Nein';

    if (as === 'date' || as === 'datetime') {
        const d = new Date(v);
        if (isNaN(d.getTime())) return String(v);
        const opts =
            as === 'date'
                ? { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'Europe/Zurich' }
                : { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Zurich' };
        return new Intl.DateTimeFormat('de-CH', opts).format(d);
    }

    return String(v);
});
</script>

<template>
    <div class="kh-readonly" :class="{ 'kh-readonly--empty': isEmpty }">
        <template v-if="!isEmpty">{{ display }}</template>
        <span v-else>—</span>
    </div>
</template>

<style scoped>
.kh-readonly {
    display: flex;
    align-items: center;
    min-height: 2.375rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.375rem;
    border: 1px solid color-mix(in srgb, currentColor 20%, transparent);
    background-color: transparent;
    color: inherit;
    font-variant-numeric: tabular-nums;
}

.kh-readonly--empty {
    color: color-mix(in srgb, currentColor 45%, transparent);
}
</style>
