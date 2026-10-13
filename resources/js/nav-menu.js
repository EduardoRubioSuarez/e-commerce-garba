// Menú desplegable del encabezado en pantallas pequeñas.
export function initializeNavMenu(document) {
    const header = document.querySelector('[data-nav]');
    const toggle = header?.querySelector('[data-nav-toggle]');
    if (!header || !toggle) return;
    const open = header.querySelector('[data-icon-open]');
    const close = header.querySelector('[data-icon-close]');
    const set = (expanded) => {
        header.classList.toggle('is-open', expanded);
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', expanded ? 'Cerrar menú' : 'Abrir menú');
        open?.toggleAttribute('hidden', expanded);
        close?.toggleAttribute('hidden', !expanded);
    };
    toggle.addEventListener('click', () => set(!header.classList.contains('is-open')));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && header.classList.contains('is-open')) {
            set(false);
            toggle.focus();
        }
    });
    header.querySelectorAll('[data-nav-menu] a').forEach((link) => link.addEventListener('click', () => set(false)));
}
