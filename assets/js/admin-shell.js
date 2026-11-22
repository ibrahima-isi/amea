(() => {
    const toggle = document.getElementById('staff-menu-toggle');
    const close = document.getElementById('staff-menu-close');
    const drawer = document.getElementById('sidebar-wrapper');
    const overlay = document.getElementById('sidebar-overlay');
    const page = document.getElementById('page-content-wrapper');
    if (!toggle || !close || !drawer || !overlay || !page) return;

    const mobile = window.matchMedia('(max-width: 767.98px)');
    const setOpen = (open, returnFocus = false) => {
        const visible = mobile.matches && open;
        document.body.classList.toggle('staff-menu-open', visible);
        toggle.setAttribute('aria-expanded', String(visible));
        overlay.hidden = !visible;
        drawer.inert = mobile.matches && !visible;
        page.inert = visible;

        if (visible) close.focus();
        if (returnFocus) toggle.focus();
    };

    toggle.addEventListener('click', () => setOpen(true));
    close.addEventListener('click', () => setOpen(false, true));
    overlay.addEventListener('click', () => setOpen(false, true));

    drawer.addEventListener('keydown', event => {
        if (toggle.getAttribute('aria-expanded') !== 'true') return;
        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false, true);
        }
        if (event.key === 'Tab') {
            const links = drawer.querySelectorAll('a[href], button');
            const first = links[0];
            const last = links[links.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    mobile.addEventListener('change', () => {
        const restoreFocus = mobile.matches && drawer.contains(document.activeElement);
        setOpen(false, restoreFocus);
        if (!mobile.matches && document.activeElement === close) drawer.querySelector('a').focus();
    });
    setOpen(false);
})();
