import { mountEmbeddedCheckout } from './embedded-checkout';
import { initializeNavMenu } from './nav-menu';
import { initializeStorefrontFeedback } from './storefront-feedback';

document.querySelectorAll('[data-stripe-checkout]').forEach(mountEmbeddedCheckout);
initializeStorefrontFeedback(document, window);
initializeNavMenu(document);

document.querySelectorAll('[data-back-link]').forEach((link) => {
    link.addEventListener('click', (event) => {
        if (window.history.length > 1 && document.referrer.startsWith(window.location.origin)) {
            event.preventDefault();
            window.history.back();
        }
    });
});
