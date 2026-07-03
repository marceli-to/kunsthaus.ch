import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { createApp } from 'vue';
import ImageGenerator from './components/ImageGenerator.vue';
import { initLogoAnimation } from './modules/logo-animation';
import { initShyLogo } from './modules/shy';

// Alpine handles lightweight UI state (menu toggles, transitions).
window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.start();

// Crossfade the interchangeable last word on every [data-ja-logo] lockup.
initLogoAnimation();

// Hide the logo while scrolling down on any opted-in [data-shy-logo] lockup.
initShyLogo();

// Vue island — mount only where the landing page provides a target.
const el = document.getElementById('image-generator');

if (el) {
    // The component fetches its own config (styles, bg-removal, geometry) from
    // /api/generator on mount — no props to wire here.
    createApp(ImageGenerator).mount(el);
}
