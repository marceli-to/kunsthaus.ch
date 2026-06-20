import { createApp } from 'vue';
import ImageGenerator from './components/ImageGenerator.vue';

// Mount the Vue island only where the landing page provides a target.
const el = document.getElementById('image-generator');

if (el) {
    createApp(ImageGenerator, {
        // Server decides whether the client-side cutout is offered at all.
        bgRemovalEnabled: el.dataset.bgRemoval === '1' || el.dataset.bgRemoval === 'true',
    }).mount(el);
}
