import { test } from 'node:test';
import assert from 'node:assert/strict';
import { waitForPaymentConfirmation, mountEmbeddedCheckout } from '../resources/js/embedded-checkout.js';

test('a completed Stripe form stays pending until the server confirms payment', async () => {
    const statuses = ['pending', 'pending', 'paid'];
    let calls = 0;
    const result = await waitForPaymentConfirmation('/pedidos/fixture', {
        fetch: async () => ({ ok: true, json: async () => ({ data: { status: statuses[calls++] } }) }),
        wait: async () => {},
    });
    assert.equal(result, 'paid');
    assert.equal(calls, 3);
});

test('delayed webhooks stop polling without inventing payment confirmation', async () => {
    let calls = 0;
    const result = await waitForPaymentConfirmation('/pedidos/fixture', {
        fetch: async () => { calls++; return { ok: true, json: async () => ({ data: { status: 'pending' } }) }; },
        wait: async () => {},
        attempts: 3,
    });
    assert.equal(result, 'pending');
    assert.equal(calls, 3);
});

test('network failures never authorize another charge or confirm payment', async () => {
    const result = await waitForPaymentConfirmation('/pedidos/fixture', {
        fetch: async () => { throw new Error('offline'); }, wait: async () => {}, attempts: 2,
    });
    assert.equal(result, 'pending');
});

test('lost access stops polling immediately', async () => {
    let calls = 0;
    const result = await waitForPaymentConfirmation('/pedidos/fixture', {
        fetch: async () => { calls++; return { ok: false, status: 404 }; }, wait: async () => {},
    });
    assert.equal(result, 'unavailable');
    assert.equal(calls, 1);
});

function checkoutElement() {
    const handlers = {};
    const status = { textContent: '' };
    const mount = { replaceChildren() {} };
    const retry = { hidden: true, addEventListener: (name, callback) => { handlers[name] = callback; } };
    const nodes = { '[data-checkout-status]': status, '[data-checkout-mount]': mount, '[data-checkout-retry]': retry };
    return {
        dataset: { publishableKey: 'pk_test_fixture', sessionUrl: '/session', resultUrl: '/order', csrfToken: 'csrf_fixture' },
        querySelector: (selector) => nodes[selector], status, mount, retry, handlers,
    };
}

test('initialization uses authorized POST and mounts once; duplicate completion waits for server once', async (t) => {
    const element = checkoutElement();
    let options;
    let mounts = 0;
    let destroys = 0;
    let confirmations = 0;
    let navigations = 0;
    t.mock.method(globalThis, 'fetch', async (url, request) => {
        if (url === '/session') {
            assert.equal(request.method, 'POST');
            assert.equal(request.headers['X-CSRF-TOKEN'], 'csrf_fixture');
            return { ok: true, json: async () => ({ clientSecret: 'secret_fixture' }) };
        }
        confirmations++;
        return { ok: true, json: async () => ({ data: { status: 'paid' } }) };
    });
    globalThis.window = {
        Stripe: () => ({ initEmbeddedCheckout: async (value) => {
            options = value;
            assert.equal(await value.fetchClientSecret(), 'secret_fixture');
            return { mount: () => { mounts++; }, destroy: () => { destroys++; } };
        } }),
        location: { assign: () => { navigations++; } },
    };
    t.after(() => { delete globalThis.window; });
    await mountEmbeddedCheckout(element);
    assert.equal(mounts, 1);
    await Promise.all([options.onComplete(), options.onComplete()]);
    assert.equal(destroys, 1);
    assert.equal(confirmations, 1);
    assert.equal(navigations, 1);
    assert.equal(element.retry.hidden, true);
});

test('temporary session failure retries the same endpoint without submitting a new order', async (t) => {
    const element = checkoutElement();
    let requests = 0;
    let mounts = 0;
    t.mock.method(globalThis, 'fetch', async (url) => {
        assert.equal(url, '/session');
        requests++;
        return requests === 1 ? { ok: false, status: 503 } : { ok: true, json: async () => ({ clientSecret: 'secret_fixture' }) };
    });
    globalThis.window = { Stripe: () => ({ initEmbeddedCheckout: async () => ({ mount: () => { mounts++; } }) }) };
    t.after(() => { delete globalThis.window; });
    await mountEmbeddedCheckout(element);
    assert.equal(element.retry.hidden, false);
    await element.handlers.click();
    assert.equal(requests, 2);
    assert.equal(mounts, 1);
    assert.equal(element.retry.hidden, true);
});

test('a closed unpaid form checks the order without claiming a payment was submitted', async (t) => {
    const element = checkoutElement();
    let mounted = false;
    t.mock.method(globalThis, 'fetch', async (url) => url === '/session'
        ? { ok: false, status: 409 }
        : { ok: true, json: async () => ({ data: { status: 'closed_unpaid' } }) });
    globalThis.window = { Stripe: () => { mounted = true; }, location: { assign() {} } };
    t.after(() => { delete globalThis.window; });
    await mountEmbeddedCheckout(element);
    assert.equal(mounted, false);
    assert.ok(!element.status.textContent.includes('No necesitas volver a pagar'));
    assert.equal(element.retry.hidden, true);
});

test('lost session access hides retries instead of fetching credentials repeatedly', async (t) => {
    const element = checkoutElement();
    t.mock.method(globalThis, 'fetch', async () => ({ ok: false, status: 419 }));
    await mountEmbeddedCheckout(element);
    assert.equal(element.retry.hidden, true);
    assert.ok(element.status.textContent.includes('Actualiza la página'));
});
