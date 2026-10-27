/* Public carousel: manual control stays available even when motion is reduced. */
document.querySelectorAll('[data-community-carousel]').forEach(gallery => {
    const slides = [...gallery.querySelectorAll('.community-slide')];
    if (slides.length < 2) return;
    const controls = gallery.querySelector('.gallery-controls');
    const pause = gallery.querySelector('[data-carousel-pause]');
    const count = gallery.querySelector('[data-carousel-count]');
    const announcement = gallery.querySelector('.carousel-announcement');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0;
    let paused = motion.matches;
    let timer;
    let hovered = false;
    const schedule = () => {
        window.clearInterval(timer);
        if (!paused && !hovered && !document.hidden) timer = window.setInterval(() => show(index + 1, false), 6500);
    };
    const show = (next, manual) => {
        index = (next + slides.length) % slides.length;
        slides.forEach((slide, i) => { slide.hidden = i !== index; });
        count.textContent = `${index + 1} / ${slides.length}`;
        if (manual) announcement.textContent = `Photo ${index + 1} sur ${slides.length}. ${slides[index].querySelector('img').alt}`;
    };
    const updatePause = () => {
        pause.textContent = paused ? 'Reprendre le défilement' : 'Mettre en pause';
        pause.setAttribute('aria-pressed', String(paused));
        schedule();
    };
    controls.hidden = false;
    pause.addEventListener('click', () => { paused = !paused; updatePause(); });
    gallery.querySelector('[data-carousel-prev]').addEventListener('click', () => { show(index - 1, true); schedule(); });
    gallery.querySelector('[data-carousel-next]').addEventListener('click', () => { show(index + 1, true); schedule(); });
    // Keyboard focus stops rotation until the visitor explicitly resumes it.
    gallery.addEventListener('focusin', event => {
        if (event.target.matches(':focus-visible')) { paused = true; updatePause(); }
    });
    gallery.addEventListener('mouseenter', () => { hovered = true; schedule(); });
    gallery.addEventListener('mouseleave', () => { hovered = false; schedule(); });
    document.addEventListener('visibilitychange', schedule);
    motion.addEventListener('change', event => { if (event.matches) { paused = true; updatePause(); } });
    updatePause();
});
