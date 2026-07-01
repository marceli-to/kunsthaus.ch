/**
 * Ja-zum-Kunstmuseum logo — crossfades the interchangeable last word
 * (Standard → Begegnung → Bildung → …) like a looping GIF.
 *
 * Markup contract (see resources/views/components/logos/logo.antlers.html):
 *   <svg data-ja-logo>
 *     …fixed lockup…
 *     <g data-ja-logo-word="standard"  class="… opacity-100">…</g>
 *     <g data-ja-logo-word="begegnung" class="… opacity-0">…</g>
 *     …
 *   </svg>
 *
 * The word groups carry Tailwind's `transition-opacity`; this module only
 * flips `opacity-0` / `opacity-100`, so the fade timing lives in the markup.
 *
 * Usage:
 *   import { initLogoAnimation } from './logo-animation';
 *   initLogoAnimation();                       // all [data-ja-logo] on the page
 *   initLogoAnimation({ interval: 2000 });     // custom cadence
 *   const logo = initLogoAnimation()[0];
 *   logo.show('vielfalt'); logo.pause(); logo.play();
 */

const VISIBLE = 'opacity-100';
const HIDDEN = 'opacity-0';

const prefersReducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

/**
 * Wire up a single logo SVG.
 * @param {SVGElement} svg
 * @param {{ interval?: number, autoplay?: boolean }} [options]
 */
export function createLogoAnimation(svg, { interval = 2400, autoplay = true } = {}) {
    const words = Array.from(svg.querySelectorAll('[data-ja-logo-word]'));
    const names = words.map((el) => el.dataset.jaLogoWord);
    let index = Math.max(0, words.findIndex((el) => el.classList.contains(VISIBLE)));
    if (index === -1) index = 0;
    let timer = null;

    function render() {
        words.forEach((el, i) => {
            const active = i === index;
            el.classList.toggle(VISIBLE, active);
            el.classList.toggle(HIDDEN, !active);
        });
    }

    /** Show a word by name or numeric index; stays put until play() resumes. */
    function show(target) {
        const next = typeof target === 'number' ? target : names.indexOf(target);
        if (next < 0 || next >= words.length) return;
        index = next;
        render();
    }

    function next() {
        index = (index + 1) % words.length;
        render();
    }

    function play() {
        if (timer || words.length < 2 || prefersReducedMotion()) return;
        timer = window.setInterval(next, interval);
    }

    function pause() {
        window.clearInterval(timer);
        timer = null;
    }

    render();
    if (autoplay) play();

    // Pause while the tab is hidden so the loop stays in sync and cheap.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) pause();
        else if (autoplay) play();
    });

    return { svg, names, show, next, play, pause, get current() { return names[index]; } };
}

/**
 * Initialise every logo in the document (or within `root`).
 * @param {{ interval?: number, autoplay?: boolean, root?: ParentNode }} [options]
 * @returns {ReturnType<typeof createLogoAnimation>[]}
 */
export function initLogoAnimation({ root = document, ...options } = {}) {
    return Array.from(root.querySelectorAll('[data-ja-logo]')).map((svg) =>
        createLogoAnimation(svg, options),
    );
}

export default initLogoAnimation;
