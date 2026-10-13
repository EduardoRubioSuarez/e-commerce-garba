export function attachSubmissionFeedback(form) {
    let submitting = false;
    let submitter = null;
    let originalText = '';
    const reset = () => {
        submitting = false;
        form.removeAttribute('aria-busy');
        if (submitter) {
            submitter.textContent = originalText;
            submitter.removeAttribute('aria-disabled');
        }
    };
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        if (submitting) {
            event.preventDefault();
            return;
        }
        submitting = true;
        submitter = event.submitter;
        form.setAttribute('aria-busy', 'true');
        if (submitter) {
            originalText = submitter.textContent;
            submitter.textContent = 'Procesando…';
            // Keep the submitter enabled so its name and value remain in the request.
            submitter.setAttribute('aria-disabled', 'true');
        }
    });
    return reset;
}

// Debe coincidir con --notice-duration en app.css (barra de progreso).
const NOTICE_DURATION_MS = 3000;

export function attachNoticeFeedback(notice, window) {
    const dismiss = () => {
        window.clearTimeout(timer);
        notice.remove();
    };
    const timer = window.setTimeout(dismiss, NOTICE_DURATION_MS);
    notice.querySelector?.('[data-notice-close]')?.addEventListener('click', dismiss);
    return dismiss;
}

export function initializeStorefrontFeedback(document, window) {
    const resets = [...document.querySelectorAll('.storefront form[method="POST"]:not([data-quick-add]), .storefront form[method="post"]:not([data-quick-add])')]
        .map(attachSubmissionFeedback);
    const dismissNotices = [...document.querySelectorAll('[data-notice]')]
        .map((notice) => attachNoticeFeedback(notice, window));
    window.addEventListener('pageshow', (event) => {
        resets.forEach((reset) => reset());
        if (event.persisted) dismissNotices.forEach((dismiss) => dismiss());
    });
    document.querySelectorAll('[data-quick-add]').forEach((form) => attachQuickAdd(form, document, window));
    document.querySelector('[data-error-summary]')?.focus();
}

export function showNotice(document, window, label, message, isError = false) {
    const stack = document.querySelector('.notice-stack');
    if (!stack) return null;
    const notice = document.createElement('div');
    notice.setAttribute('role', 'status');
    notice.setAttribute('aria-live', 'polite');
    notice.className = `store-notice${isError ? ' notice-error' : ''}`;
    notice.setAttribute('data-notice', '');
    const title = document.createElement('span');
    title.className = 'notice-label';
    title.textContent = label;
    const text = document.createElement('p');
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'notice-close';
    close.setAttribute('data-notice-close', '');
    close.setAttribute('aria-label', 'Cerrar notificación');
    close.innerHTML = '<span aria-hidden="true">&times;</span>';
    const progress = document.createElement('span');
    progress.className = 'notice-progress';
    progress.setAttribute('aria-hidden', 'true');
    notice.append(title, text, close, progress);
    stack.append(notice);
    attachNoticeFeedback(notice, window);
    return notice;
}

function updateCartCount(document, count) {
    document.querySelectorAll('.cart-count').forEach((badge) => { badge.textContent = String(count); });
    const link = document.querySelector('.nav-cart');
    link?.setAttribute('aria-label', `Carrito, ${count} ${count === 1 ? 'producto' : 'productos'}`);
}

// Compra rápida desde el catálogo: agrega sin recargar la página y, si falla la red, envía el formulario normal.
export function attachQuickAdd(form, document, window) {
    let busy = false;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) return;
        busy = true;
        const button = form.querySelector('button[type="submit"]');
        const label = button?.textContent;
        button?.setAttribute('aria-busy', 'true');
        try {
            const response = await window.fetch(form.getAttribute('action'), {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));
            if (response.ok) {
                updateCartCount(document, data.cart_count ?? 0);
                showNotice(document, window, 'Tu carrito', data.message ?? 'Equipo agregado al carrito.');
                if (button) {
                    button.textContent = 'Agregado';
                    button.classList.add('is-added');
                    window.setTimeout(() => { button.textContent = label; button.classList.remove('is-added'); }, 1400);
                }
            } else {
                const first = data.errors ? Object.values(data.errors).flat()[0] : null;
                showNotice(document, window, 'Aviso', first ?? data.message ?? 'No pudimos agregar el equipo. Inténtalo de nuevo.', true);
            }
        } catch {
            form.submit();
        } finally {
            button?.removeAttribute('aria-busy');
            busy = false;
        }
    });
}
