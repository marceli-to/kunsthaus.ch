import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { createApp } from 'vue';
import ImageGenerator from './components/ImageGenerator.vue';
import { initLogoAnimation } from './modules/logo-animation';
import { initShyLogo } from './modules/shy';
import { initSlideshows } from './modules/slideshow';

// Alpine handles lightweight UI state (menu toggles, transitions).
window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.start();

// Crossfade the interchangeable last word on every [data-ja-logo] lockup.
initLogoAnimation();

// Hide the logo while scrolling down on any opted-in [data-shy-logo] lockup.
initShyLogo();

// Header visuals slideshow — Swiper on every [data-slideshow] block.
initSlideshows();

// Vue island — mount where a page provides a target (landing block or the
// /jatelier employee page). The component fetches its own config (styles,
// bg-removal, geometry) from /api/generator on mount. The only prop is `mode`:
// `public` (default, landing) stores + publishes; `private` (data-mode="private",
// /jatelier) only emails/downloads the image and never touches the database.
const el = document.getElementById('image-generator');

if (el) {
    createApp(ImageGenerator, { mode: el.dataset.mode ?? 'public' }).mount(el);
}
