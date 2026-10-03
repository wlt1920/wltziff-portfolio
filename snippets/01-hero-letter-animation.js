/*
 * Hero headline: letter-by-letter rotation on the compositor
 * Excerpt from the wltziff.nl WordPress theme — the word after "I design & build" changes letter by letter with the Web Animations API (transform only).
 * Not the full source: shown to illustrate how it's built.
 */

    // Hero (front-page.php): the Netherlands clock, and the word after "I design & build" changing every few seconds
    // while the hero is on screen: its letters fly up out of the mask one after another and the next word's letters
    // spring up from below. Web Animations on transform only: the compositor runs them, the main thread just starts
    // them (about 30 small animations every 2.6 s) and drops them when they're done, so nothing lingers.
    const initHero = root => {
        const stops = [];
        const clock = root.querySelector('[data-hx-clock]');
        let format = null;
        try { format = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Amsterdam' }); } catch (error) { /* keep the printed time */ }
        if (clock && format) {
            const tick = () => { clock.textContent = format.format(new Date()); };
            tick();
            const timer = setInterval(tick, 15000);
            stops.push(() => clearInterval(timer));
        }
        const words = [...root.querySelectorAll('[data-hx-words] .hx-word')];
        if (words.length > 1 && !reduced.matches && typeof Element.prototype.animate === 'function') {
            let current = 0;
            let onScreen = true;
            const seen = 'IntersectionObserver' in window ? new IntersectionObserver(([entry]) => { onScreen = entry.isIntersecting; }) : null;
            seen?.observe(root);
            const ease = { in: 'cubic-bezier(.16,1,.3,1)', out: 'cubic-bezier(.6,0,.3,1)' };
            let running = [];
            const letters = word => [...word.querySelectorAll('.hx-ch')];
            const timer = setInterval(() => {
                if (document.hidden || !onScreen) return;
                words[0].parentElement.classList.add('is-cycling'); // from now on main.js alone moves the letters
                running.forEach(animation => animation.cancel());
                words.forEach(word => word.classList.remove('is-out'));
                const leaving = words[current];
                current = (current + 1) % words.length;
                const next = words[current];
                leaving.classList.replace('is-active', 'is-out');
                next.classList.add('is-active');
                const out = letters(leaving).map((ch, i) => ch.animate(
                    [{ transform: 'none' }, { transform: 'translateY(-115%) rotate(-8deg)' }],
                    { duration: 520, delay: i * 22, easing: ease.out, fill: 'forwards' }));
                const into = letters(next).map((ch, i) => ch.animate(
                    [{ transform: 'translateY(115%) rotate(10deg)' }, { transform: 'none' }],
                    { duration: 900, delay: 140 + i * 30, easing: ease.in, fill: 'backwards' }));
                running = [...out, ...into];
                Promise.all(out.map(animation => animation.finished)).then(() => {
                    leaving.classList.remove('is-out');
                    out.forEach(animation => animation.cancel());
                }, () => {});
            }, 2600);
            stops.push(() => { clearInterval(timer); seen?.disconnect(); running.forEach(animation => animation.cancel()); });
        }
        return () => stops.forEach(stop => stop());
    };
