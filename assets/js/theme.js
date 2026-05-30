(function() {
    const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-bs-theme', theme);
})();

document.addEventListener('DOMContentLoaded', () => {
    const setStoredTheme = theme => localStorage.setItem('theme', theme);
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