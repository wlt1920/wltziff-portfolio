/*
 * Scroll reveal + pausing animations off screen
 * Excerpt from the wltziff.nl WordPress theme — reveal uses translate/opacity only; infinite CSS animations pause while their section is off screen.
 * Not the full source: shown to illustrate how it's built.
 */

    if ('IntersectionObserver' in window && !reduced.matches) {
        // Blocks below the screen wait hidden (.is-pending: opacity 0, 24px lower) and rise in as they come into view.
        // They used to wait fully visible and jump down on the first frame of their animation, which looked like a
        // stutter just before every animation. translate/opacity only (run by the compositor, and `translate` stays
        // clear of the blocks' own hover transforms). Blocks already on screen at load are left alone.
        const observer = new IntersectionObserver(entries => entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const element = entry.target;
            observer.unobserve(element);
            if (!element.classList.contains('is-pending')) return;
            const delay = (Number(element.style.getPropertyValue('--i')) || 0) * 70;
            element.classList.remove('is-pending');
            element.animate([{ translate: '0 24px', opacity: 0 }, { translate: '0 0', opacity: 1 }], { duration: 700, delay, easing: 'cubic-bezier(.2,.7,.2,1)', fill: 'backwards' });
        }), { rootMargin: '0px 0px -40px 0px' });
        const fold = window.innerHeight;
        document.querySelectorAll('.reveal').forEach(element => {
            if (element.getBoundingClientRect().top > fold) element.classList.add('is-pending');
            observer.observe(element);
        });
        cleanup.push(() => observer.disconnect());
    }
    // Each part of the page (the sections of #main, the footer) pauses its CSS animations while it's off screen
    // (data-paused, see site.css): pulsing dots, radar sweeps and the like then cost nothing while scrolling elsewhere.
    if ('IntersectionObserver' in window) {
        const parts = [...document.querySelectorAll('#main > *, .site-footer')];
        const pauser = new IntersectionObserver(entries => entries.forEach(entry => {
            entry.target.toggleAttribute('data-paused', !entry.isIntersecting);
        }), { rootMargin: '200px 0px' });
        parts.forEach(part => pauser.observe(part));
        cleanup.push(() => { pauser.disconnect(); parts.forEach(part => part.removeAttribute('data-paused')); });
    }
