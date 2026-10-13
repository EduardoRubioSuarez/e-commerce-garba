let stripeScript;

function loadStripe() {
    if (window.Stripe) {
        return Promise.resolve(window.Stripe);
    }
    if (!stripeScript) {
        stripeScript = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            const timer = setTimeout(() => failed(), 15000);
            const failed = () => {
                clearTimeout(timer);
                script.remove();
                stripeScript = undefined;
                reject(new Error('stripe_unavailable'));
            };
            script.src = 'https://js.stripe.com/v3/';
            script.async = true;
            script.onload = () => {
                clearTimeout(timer);
                if (window.Stripe) {
                    resolve(window.Stripe);
                } else {
                    failed();
                }
            };
            script.onerror = failed;
            document.head.append(script);
        });
    }
    return stripeScript;
}

export async function waitForPaymentConfirmation(url, options = {}) {
    const request = options.fetch ?? globalThis.fetch;
    const wait = options.wait ?? ((ms) => new Promise((resolve) => setTimeout(resolve, ms)));
    const attempts = options.attempts ?? 20;
    for (let attempt = 0; attempt < attempts; attempt++) {
        try {
            const response = await request(url, {
                headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store',
                signal: AbortSignal.timeout(5000),
            });
            if ([401, 403, 404].includes(response.status)) {
                return 'unavailable';
            }
            if (response.ok) {
                const body = await response.json();
                if (body.data?.status === 'paid') {
                    return 'paid';
                }
                if (body.data?.status === 'closed_unpaid') {
                    return 'closed_unpaid';
                }
            }
        } catch {
            // A transport error leaves payment confirmation pending.
        }
        if (attempt + 1 < attempts) {
            await wait(1500);
        }
    }
    return 'pending';
}

export function mountEmbeddedCheckout(element) {
    const status = element.querySelector('[data-checkout-status]');
    const mount = element.querySelector('[data-checkout-mount]');
    const retry = element.querySelector('[data-checkout-retry]');
    let checkout;
    let loading = false;
    let completed = false;

    const confirm = async (submitted = true) => {
        if (completed) {
            return;
        }
        completed = true;
        retry.hidden = true;
        checkout?.destroy();
        mount.replaceChildren();
        status.textContent = submitted
            ? 'Estamos verificando el pago. No necesitas volver a pagar.'
            : 'Este formulario ya no está disponible. Estamos consultando el estado del pedido.';
        const result = await waitForPaymentConfirmation(element.dataset.resultUrl);
        if (result === 'paid' || result === 'closed_unpaid') {
            window.location.assign(element.dataset.resultUrl);
        } else {
            status.textContent = result === 'unavailable'
                ? 'No podemos consultar este pedido. Inicia sesión para revisar su estado.'
                : 'La confirmación sigue pendiente. Conserva la referencia y consulta el estado del pedido en unos momentos.';
        }
    };

    const initialize = async () => {
        if (loading || completed) {
            return;
        }
        loading = true;
        retry.hidden = true;
        status.textContent = 'Cargando el formulario de pago seguro…';
        checkout?.destroy();
        checkout = undefined;
        mount.replaceChildren();
        try {
            if (!/^pk_(test|live)_[a-zA-Z0-9]+$/.test(element.dataset.publishableKey ?? '')) {
                throw new Error('configuration_unavailable');
            }
            const response = await fetch(element.dataset.sessionUrl, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': element.dataset.csrfToken },
                signal: AbortSignal.timeout(10000),
            });
            if (response.status === 409) {
                await confirm(false);
                return;
            }
            if ([401, 403, 404, 419].includes(response.status)) {
                completed = true;
                status.textContent = 'Tu acceso al pedido ya no está disponible. Actualiza la página e inicia sesión si es necesario.';
                return;
            }
            if (!response.ok) {
                throw new Error('session_unavailable');
            }
            const body = await response.json();
            const Stripe = await loadStripe();
            checkout = await Stripe(element.dataset.publishableKey).initEmbeddedCheckout({
                fetchClientSecret: async () => body.clientSecret,
                onComplete: () => confirm(true),
            });
            if (completed) {
                checkout.destroy();
                return;
            }
            checkout.mount(mount);
            status.textContent = 'Introduce los datos de tu tarjeta en el formulario seguro de Stripe.';
        } catch {
            status.textContent = 'No pudimos cargar el formulario de pago. Puedes intentarlo de nuevo sin crear otro pedido.';
            retry.hidden = false;
        } finally {
            loading = false;
        }
    };
    retry.addEventListener('click', initialize);
    return initialize();
}
