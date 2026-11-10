/* Woo Checkout */
jQuery( document ).ready(function() {
    jQuery( document.body ).on( 'update_checkout updated_checkout applied_coupon_in_checkout removed_coupon_in_checkout checkout_error', function() {
        if(jQuery('.g-recaptcha').length > 0) {
            if (typeof grecaptcha !== "undefined" && typeof grecaptcha.reset === "function") {
                var count = 0;
                jQuery(".g-recaptcha").each(function () {
                    var widgetId = jQuery(this).data('rcfwc-widget-id');
                    // Container was replaced by a checkout fragment refresh (e.g. "Before Submit Button"), so render it again
                    if (!this.firstElementChild && typeof grecaptcha.render === "function") {
                        try {
                            widgetId = grecaptcha.render(this, {
                                sitekey: this.getAttribute('data-sitekey'),
                                theme: this.getAttribute('data-theme') || 'light'
                            });
                            jQuery(this).data('rcfwc-widget-id', widgetId);
                        } catch (e) {}
                    } else {
                        try {
                            grecaptcha.reset(typeof widgetId !== 'undefined' ? widgetId : count);
                        } catch (e) {}
                    }
                    count++;
                });
            }
        }
    });
});

/* Woo Checkout Block */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        // Global callbacks used by auto-rendered v2 widgets in the block checkout
        window.rcfwcRecaptchaCallback = function(token) {
            try {
                if (typeof wp !== 'undefined' && wp.data) {
                    wp.data.dispatch('wc/store/checkout').__internalSetExtensionData('rcfwc', { token: token });
                }
            } catch (e) {}
        };
        window.rcfwcRecaptchaExpired = function() {
            try {
                if (typeof wp !== 'undefined' && wp.data) {
                    wp.data.dispatch('wc/store/checkout').__internalSetExtensionData('rcfwc', { token: '' });
                }
            } catch (e) {}
        };

        // Try to render explicitly if needed once the blocks mount/update
        if (typeof wp !== 'undefined' && wp.data) {
            var unsubscribe = wp.data.subscribe(function() {
                var el = document.getElementById('g-recaptcha-woo-checkout');
                if (!el) {
                    return;
                }
                // If already rendered (has inner HTML/iframe), stop listening
                if (el.innerHTML && el.innerHTML.trim() !== '') {
                    unsubscribe && unsubscribe();
                    return;
                }
                // Render explicitly with callbacks if the API is ready
                if (typeof grecaptcha !== 'undefined' && typeof grecaptcha.render === 'function') {
                    try {
                        grecaptcha.render(el, {
                            sitekey: el.getAttribute('data-sitekey'),
                            callback: rcfwcRecaptchaCallback,
                            'expired-callback': rcfwcRecaptchaExpired
                        });
                    } catch (e) {
                        // Ignore if already rendered or API not ready
                    }
                    unsubscribe && unsubscribe();
                }
            }, 'wc/store/cart');
        }
    });
})();
