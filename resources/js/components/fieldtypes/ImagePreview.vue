<script setup>
import { computed } from 'vue';
import { Fieldtype } from '@statamic/cms';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose } = Fieldtype.use(emit, props);
defineExpose(expose);

const src = computed(() => props.value || null);
const linkText = computed(() => props.config?.text || 'In voller Grösse öffnen');
</script>

<template>
    <div v-if="src" class="kh-image-preview">
        <a :href="src" target="_blank" rel="noopener noreferrer" class="kh-image-preview__frame">
            <img :src="src" alt="" loading="lazy" />
        </a>
        <a :href="src" target="_blank" rel="noopener noreferrer" class="kh-image-preview__link">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M7 17 17 7" /><path d="M8 7h9v9" />
            </svg>
            {{ linkText }}
        </a>
    </div>
    <span v-else class="kh-empty">—</span>
</template>

<style scoped>
.kh-image-preview {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.6rem;
}

.kh-image-preview__frame {
    display: inline-block;
    line-height: 0;
    border-radius: 0.5rem;
    overflow: hidden;
    border: 1px solid color-mix(in srgb, currentColor 15%, transparent);
    background-color: color-mix(in srgb, currentColor 4%, transparent);
}

.kh-image-preview__frame img {
    display: block;
    width: 100%;
    max-width: 300px;
    height: auto;
}

.kh-image-preview__link {
    display: inline-flex;
    align-items: center;
    gap: 0.4em;
    color: inherit;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    opacity: 0.85;
}

.kh-image-preview__link:hover {
    opacity: 1;
    text-decoration: underline;
}

.kh-empty {
    opacity: 0.4;
}
</style>
