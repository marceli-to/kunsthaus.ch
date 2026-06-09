import { createApp } from 'vue';
import ImageGenerator from './components/ImageGenerator.vue';

// Mount the Vue island only where the landing page provides a target.
const el = document.getElementById('image-generator');

if (el) {
    createApp(ImageGenerator).mount(el);
}
