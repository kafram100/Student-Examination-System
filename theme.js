(function () {
    const THEME_KEY = 'ses-theme';
    const DARK_CLASS = 'theme-dark';

    function applyTheme(theme) {
        const preferDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = theme ? theme === 'dark' : preferDark;

        document.body.classList.toggle(DARK_CLASS, isDark);
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
        
        // Also set a cookie for server-side detection if needed
        document.cookie = `theme=${isDark ? 'dark' : 'light'}; path=/; max-age=31536000`; // 1 year
        
        const toggle = document.getElementById('themeToggleFab');
        const sun = document.getElementById('themeSunIcon');
        const moon = document.getElementById('themeMoonIcon');

        if (toggle && sun && moon) {
            sun.style.display = isDark ? 'none' : 'inline';
            moon.style.display = isDark ? 'inline' : 'none';
            toggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        }
    }

    function createToggleButton() {
        if (document.getElementById('themeToggleFab')) {
            return;
        }

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'themeToggleFab';
        btn.className = 'theme-toggle-fab';
        btn.setAttribute('aria-label', 'Switch to dark mode');
        btn.innerHTML = '' +
            '<svg id="themeSunIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' +
            '<circle cx="12" cy="12" r="4"></circle>' +
            '<path d="M12 2v2"></path>' +
            '<path d="M12 20v2"></path>' +
            '<path d="M4.93 4.93l1.41 1.41"></path>' +
            '<path d="M17.66 17.66l1.41 1.41"></path>' +
            '<path d="M2 12h2"></path>' +
            '<path d="M20 12h2"></path>' +
            '<path d="M4.93 19.07l1.41-1.41"></path>' +
            '<path d="M17.66 6.34l1.41-1.41"></path>' +
            '</svg>' +
            '<svg id="themeMoonIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="display:none;">' +
            '<path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"></path>' +
            '</svg>';

        btn.addEventListener('click', function () {
            const isDark = document.body.classList.contains(DARK_CLASS);
            const nextTheme = isDark ? 'light' : 'dark';
            localStorage.setItem(THEME_KEY, nextTheme);
            applyTheme(nextTheme);
        });

        document.body.appendChild(btn);
    }

    function initThemeToggle() {
        // Check for saved theme preference or system preference
        let savedTheme = localStorage.getItem(THEME_KEY);
        
        // If no saved preference, check for cookie
        if (!savedTheme) {
            const cookieTheme = document.cookie.split('; ').find(row => row.startsWith('theme='));
            if (cookieTheme) {
                savedTheme = cookieTheme.split('=')[1];
            }
        }
        
        createToggleButton();
        applyTheme(savedTheme);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initThemeToggle);
    } else {
        initThemeToggle();
    }
})();
