/**
 * "Shy" logo — hides a lockup once the user scrolls away from the top and
 * only brings it back when they return to the top. Useful in the sticky/fixed
 * headers where the logo should get out of the way.
 *
 * Markup contract (see the sticky headers in resources/views/components/layout/header):
 *   <a data-shy-logo
 *      class="… transition-opacity data-[shy-hidden]:opacity-0 …">…</a>
 *
 * Opt in by putting `data-shy-logo` on the outer element (the header link or
 * wrapper) that should fade. This module only flips the `data-shy-hidden`
 * attribute; the hide/show styling (opacity + transition timing) lives in the
 * markup via Tailwind data-variant utilities, mirroring logo-animation.js.
 *
 * Usage:
 *   import { initShyLogo } from './shy';
 *   initShyLogo();                     // all [data-shy-logo] on the page
 *   initShyLogo({ ratio: 0.2 });       // reveal within the top 20% instead
 *   const shy = initShyLogo()[0];
 *   shy.show(); shy.hide(); shy.destroy();
 */

const HIDDEN = 'shyHidden'; // dataset key → data-shy-hidden attribute

const prefersReducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

/**
 * Wire up a single shy element.
 * @param {HTMLElement} el
 * @param {{ ratio?: number }} [options]
 *   ratio — reveal only within the top fraction of the viewport (0–1);
 *           0.1 keeps it visible in the top 10%, then hides until the user
 *           scrolls back up into that band.
 */
export function createShyLogo(el, { ratio = 0.1 } = {}) {
    let ticking = false;

    const show = () => delete el.dataset[HIDDEN];
    const hide = () => { el.dataset[HIDDEN] = ''; };

    function update() {
        ticking = false;
        const y = Math.max(0, window.scrollY);
        // Visible only near the top; once scrolled past the band it stays
        // hidden until the user returns to it.
        if (y <= window.innerHeight * ratio) show();
        else hide();
    }

    function onScroll() {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(update);
    }

    // Motion-averse users keep the logo permanently visible.
    if (prefersReducedMotion()) {
        show();
        return { el, show, hide, destroy() {} };
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    update();

    return {
        el,
        show,
        hide,
        destroy() {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
            show();
        },
    };
}

/**
 * Initialise every shy logo in the document (or within `root`).
 * @param {{ ratio?: number, root?: ParentNode }} [options]
 * @returns {ReturnType<typeof createShyLogo>[]}
 */
export function initShyLogo({ root = document, ...options } = {}) {
    return Array.from(root.querySelectorAll('[data-shy-logo]')).map((el) =>
        createShyLogo(el, options),
    );
}

export default initShyLogo;
