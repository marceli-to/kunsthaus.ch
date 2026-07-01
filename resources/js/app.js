import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { createApp } from 'vue';
import ImageGenerator from './components/ImageGenerator.vue';
import { initLogoAnimation } from './modules/logo-animation';

// Alpine handles lightweight UI state (menu toggles, transitions).
window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.start();

// Crossfade the interchangeable last word on every [data-ja-logo] lockup.
initLogoAnimation();

// Vue island — mount only where the landing page provides a target.
const el = document.getElementById('image-generator');

if (el) {
    createApp(ImageGenerator, {
        // Server decides whether the client-side cutout is offered at all.
        bgRemovalEnabled: el.dataset.bgRemoval === '1' || el.dataset.bgRemoval === 'true',
    }).mount(el);
}
