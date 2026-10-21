// Tema: 'system' | 'light' | 'dark'. La classe iniziale la imposta lo script inline nel <head> (anti-lampo).
const ORDER = ['system', 'light', 'dark'];
const ICONS = { system: '🖥️', light: '☀️', dark: '🌙' };
const LABELS = { system: 'sistema', light: 'chiaro', dark: 'scuro' };

function getTheme() {
    try {
        const t = localStorage.getItem('theme');
        return ORDER.includes(t) ? t : 'system';
    } catch (e) {
        return 'system';
    }
}

function isDark(mode) {
    return mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
}

function applyTheme(mode) {
    try {
        localStorage.setItem('theme', mode);
    } catch (e) {}

    const dark = isDark(mode);
    document.documentElement.classList.toggle('dark', dark);

    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', dark ? '#052e16' : '#15803d');

    const btn = document.getElementById('theme-toggle');
    if (btn) {
        btn.textContent = ICONS[mode];
        btn.title = 'Tema: ' + LABELS[mode];
        btn.setAttribute('aria-label', 'Tema: ' + LABELS[mode] + ' (clicca per cambiare)');
    }
}

function cycleTheme() {
    const next = ORDER[(ORDER.indexOf(getTheme()) + 1) % ORDER.length];
    applyTheme(next);
}

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(getTheme());
    const btn = document.getElementById('theme-toggle');
    if (btn) btn.addEventListener('click', cycleTheme);
});

// In modalità "sistema" segue il cambio del tema del sistema operativo senza ricaricare.
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (getTheme() === 'system') applyTheme('system');
});

window.cavalcalupiTheme = { getTheme, applyTheme, cycleTheme };
