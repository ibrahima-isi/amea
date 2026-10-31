(function() {
    let theme = 'light';
    try {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'light' || savedTheme === 'dark') theme = savedTheme;
    } catch (error) {
        // Storage restrictions leave the initial light theme usable.
    }
    document.documentElement.setAttribute('data-bs-theme', theme);
})();

document.addEventListener('DOMContentLoaded', () => {
    const setStoredTheme = theme => {
        try {
            localStorage.setItem('theme', theme);
        } catch (error) {
            // Page-local switching must also work when saving is unavailable.
        }
    };
    const getPreferredTheme = () => {
        return document.documentElement.getAttribute('data-bs-theme');
    };

    const showActiveTheme = (theme) => {
        const themeSwitchers = document.querySelectorAll('.theme-switcher');
        if (!themeSwitchers.length) return;

        themeSwitchers.forEach(btn => {
            const label = btn.querySelector('.theme-label');
            if(theme === 'dark') {
                if (label) label.textContent = 'Mode clair';
            } else {
                if (label) label.textContent = 'Mode sombre';
            }
        });
    };

    showActiveTheme(getPreferredTheme());

    document.querySelectorAll('.theme-switcher').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const currentTheme = document.documentElement.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-bs-theme', newTheme);
            setStoredTheme(newTheme);
            showActiveTheme(newTheme);
        });
    });
});