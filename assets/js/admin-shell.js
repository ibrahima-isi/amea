(() => {
    const toggle = document.getElementById('staff-menu-toggle');
    const drawer = document.getElementById('sidebar-wrapper');
    const overlay = document.getElementById('sidebar-overlay');
    if (!toggle || !drawer || !overlay) return;
    const mobile = window.matchMedia('(max-width: 991.98px)');
    const setOpen = (open, returnFocus = false) => {
        const visible = mobile.matches && open;
        document.body.classList.toggle('staff-menu-open', visible);
        toggle.setAttribute('aria-expanded', String(visible));
        toggle.setAttribute('aria-label', visible ? 'Fermer le menu' : 'Ouvrir le menu');
        overlay.hidden = !visible;
        drawer.inert = mobile.matches && !visible;
        if (visible) drawer.querySelector('a')?.focus();
        if (returnFocus) toggle.focus();
    };
    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    overlay.addEventListener('click', () => setOpen(false, true));
    drawer.addEventListener('keydown', event => {
        if (!mobile.matches || toggle.getAttribute('aria-expanded') !== 'true') return;
        if (event.key === 'Escape') { event.preventDefault(); setOpen(false, true); }
        if (event.key === 'Tab') {
            const links = [...drawer.querySelectorAll('a[href]')];
            const first = links[0], last = links[links.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    toggle.addEventListener('keydown', event => {
        if (event.key === 'Escape') setOpen(false, true);
    });
    mobile.addEventListener('change', () => setOpen(false));
    setOpen(false);
})();
