const applyTheme = (theme) => {
    const normalizedTheme = theme === 'light' ? 'light' : 'dark';

    document.documentElement.dataset.theme = normalizedTheme;

    try {
        localStorage.setItem('rpg-arena-theme', normalizedTheme);
    } catch {
        // Theme still applies for the current page even when storage is unavailable.
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const label = normalizedTheme === 'light' ? 'Светлая' : 'Тёмная';

        button.setAttribute('aria-pressed', normalizedTheme === 'light' ? 'true' : 'false');
        button.querySelector('[data-theme-toggle-label]').textContent = label;
    });
};

document.addEventListener('DOMContentLoaded', () => {
    let savedTheme = document.documentElement.dataset.theme;

    try {
        savedTheme = localStorage.getItem('rpg-arena-theme') || savedTheme;
    } catch {
        savedTheme = savedTheme || 'dark';
    }

    applyTheme(savedTheme);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            applyTheme(document.documentElement.dataset.theme === 'light' ? 'dark' : 'light');
        });
    });
});
