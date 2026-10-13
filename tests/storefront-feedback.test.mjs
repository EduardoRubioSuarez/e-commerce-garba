import test from 'node:test';
import assert from 'node:assert/strict';
import { attachSubmissionFeedback, attachNoticeFeedback } from '../resources/js/storefront-feedback.js';

test('notices stay visible for three seconds and are removed without moving focus', () => {
    const callbacks = new Map();
    let removed = false;
    const notice = { remove: () => { removed = true; } };
    const window = { setTimeout: (callback, delay) => { callbacks.set(delay, callback); return 1; }, clearTimeout: () => {} };
    attachNoticeFeedback(notice, window);
    assert.equal(removed, false);
    assert.equal(callbacks.size, 1);
    callbacks.get(3000)();
    assert.equal(removed, true);
});

test('notices can be cleared when returning through browser history', () => {
    let removed = false;
    let cleared = null;
    const dismiss = attachNoticeFeedback({ remove: () => { removed = true; } }, {
        setTimeout: () => 17,
        clearTimeout: (timer) => { cleared = timer; },
    });
    dismiss();
    assert.equal(removed, true);
    assert.equal(cleared, 17);
});

test('submission feedback blocks repeat submits, preserves action values, and can reset after back navigation', () => {
    const attributes = new Map();
    let submit;
    const form = {
        addEventListener: (_, callback) => { submit = callback; },
        setAttribute: (key, value) => attributes.set(key, value),
        removeAttribute: (key) => attributes.delete(key),
    };
    const buttonAttributes = new Map();
    const button = { textContent: 'Quitar', name: 'action', value: 'remove', disabled: false,
        setAttribute: (key, value) => buttonAttributes.set(key, value),
        removeAttribute: (key) => buttonAttributes.delete(key),
    };
    const reset = attachSubmissionFeedback(form);
    let prevented = 0;
    const event = { submitter: button, preventDefault: () => { prevented++; } };
    submit(event);
    assert.equal(prevented, 0);
    assert.equal(button.value, 'remove');
    assert.equal(button.disabled, false);
    assert.equal(button.textContent, 'Procesando…');
    assert.equal(attributes.get('aria-busy'), 'true');
    submit(event);
    assert.equal(prevented, 1);
    reset();
    assert.equal(button.textContent, 'Quitar');
    assert.equal(attributes.has('aria-busy'), false);
    submit(event);
    assert.equal(prevented, 1);
});

test('an already canceled form submission does not get stuck in a processing state', () => {
    let submit;
    const form = { addEventListener: (_, callback) => { submit = callback; },
        setAttribute: () => assert.fail('No busy state for canceled submission'),
        removeAttribute: () => {},
    };
    attachSubmissionFeedback(form);
    submit({ defaultPrevented: true });
});
