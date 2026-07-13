/**
 * Header visuals slideshow — a light wrapper around Swiper for the
 * `header_visuals` content block. Each slide carries two images (a mobile and a
 * desktop variant); the block markup shows the right one per breakpoint, so
 * Swiper only handles the sliding/pagination.
 *
 * Markup contract (see resources/views/components/blocks/header_visuals.antlers.html):
 *   <div class="swiper" data-slideshow>
 *     <div class="swiper-wrapper">
 *       <div class="swiper-slide">…</div>
 *     </div>
 *   </div>
 *
 * Usage:
 *   import { initSlideshows } from './slideshow';
 *   initSlideshows();                 // all [data-slideshow] on the page
 */

import Swiper from 'swiper';
import { Autoplay, EffectFade } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/effect-fade';

const prefersReducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

/**
 * Wire up a single slideshow element.
 * @param {HTMLElement} el — the `.swiper` container
 */
export function createSlideshow(el) {
    const slides = el.querySelectorAll('.swiper-slide').length;

    return new Swiper(el, {
        modules: [Autoplay, EffectFade],
        loop: slides > 1,
        speed: 1000,
        allowTouchMove: false,
        effect: 'fade',
        fadeEffect: { crossFade: true },
        // A single slide has nothing to advance to; motion-averse users get a
        // static first slide either way.
        autoplay:
            slides > 1 && !prefersReducedMotion()
                ? { delay: 3000, disableOnInteraction: false }
                : false,
    });
}

/**
 * Initialise every slideshow in the document (or within `root`).
 * @param {{ root?: ParentNode }} [options]
 * @returns {ReturnType<typeof createSlideshow>[]}
 */
export function initSlideshows({ root = document } = {}) {
    return Array.from(root.querySelectorAll('[data-slideshow]')).map((el) =>
        createSlideshow(el),
    );
}

export default initSlideshows;
