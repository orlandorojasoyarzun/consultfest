// Theme toggle with view transition (circle-blur-top-left)
(function () {
    const STORAGE_KEY = 'consultfest-theme';

    function getPreferredTheme() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') return stored;
        return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    }

    function applyTheme(theme) {
        document.body.setAttribute('data-theme', theme);
        if (theme === 'dark') {
            document.body.classList.add('dark');
        } else {
            document.body.classList.remove('dark');
        }
        localStorage.setItem(STORAGE_KEY, theme);
    }

    function switchTheme() {
        const current = document.body.getAttribute('data-theme') || 'dark';
        const next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next);
    }

    function toggleTheme() {
        if (!document.startViewTransition) {
            switchTheme();
            return;
        }
        document.startViewTransition(switchTheme);
    }

    // Apply immediately to prevent FOUC
    applyTheme(getPreferredTheme());

    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.querySelector('.theme-toggle');
        if (!toggle) return;

        toggle.addEventListener('click', toggleTheme);
    });
})();
