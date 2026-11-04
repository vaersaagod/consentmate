/**
 * ConsentMate bootstrap
 *
 * Inlined first in <head>, before any other script. Reads the stored consent,
 * sets the Google Consent Mode defaults (and the update for returning
 * visitors), loads Google tags that are configured in ConsentMate, and sets up
 * window.ConsentMate so other scripts can call has() and on() right away.
 *
 * Everything else (the banner, activating gated markup, saving) is in the
 * deferred runtime, consentmate.js.
 */
(() => {
    const w = window;
    const d = document;
    const config = w.ConsentMateConfig;

    if (!config || w.ConsentMate) {
        return;
    }

    const handles = config.categories.map((category) => category.handle);
    const required = config.categories.filter((category) => category.required).map((category) => category.handle);

    const unique = (list) => list.filter((item, index) => list.indexOf(item) === index);

    const readCookie = () => {
        const prefix = `${config.cookie.name}=`;
        const part = d.cookie.split('; ').find((item) => item.indexOf(prefix) === 0);

        if (!part) {
            return null;
        }

        try {
            const value = JSON.parse(decodeURIComponent(part.slice(prefix.length)));

            if (!value || typeof value.id !== 'string' || !Array.isArray(value.c)) {
                return null;
            }

            return value;
        } catch (e) {
            return null;
        }
    };

    const stored = readCookie();
    const given = !!stored && Number(stored.r) >= config.revision;

    const state = {
        id: stored ? stored.id : null,
        given,
        granted: unique(required.concat(given ? stored.c.filter((handle) => handles.indexOf(handle) !== -1) : [])),
        timestamp: given ? stored.t : null,
        revision: given ? Number(stored.r) : null,
        gpc: !!config.gpc && navigator.globalPrivacyControl === true,
    };

    d.documentElement.setAttribute('data-consentmate', given ? 'done' : 'pending');

    // Google Consent Mode. gtag() only pushes to the dataLayer, so it's safe
    // to define it even when no Google tag is ever loaded.
    w.dataLayer = w.dataLayer || [];

    if (typeof w.gtag !== 'function') {
        w.gtag = function gtag() {
            // eslint-disable-next-line prefer-rest-params
            w.dataLayer.push(arguments);
        };
    }

    const consentTypes = (granted) => {
        const types = {};

        config.googleTypes.forEach((type) => {
            types[type] = 'denied';
        });

        config.categories.forEach((category) => {
            if (granted.indexOf(category.handle) !== -1) {
                category.google.forEach((type) => {
                    types[type] = 'granted';
                });
            }
        });

        return types;
    };

    if (config.gcm) {
        if (config.gcm.regions) {
            // Outside the listed regions, everything defaults to granted
            const outside = {};
            config.googleTypes.forEach((type) => {
                outside[type] = 'granted';
            });
            w.gtag('consent', 'default', outside);
        }

        const defaults = consentTypes(required);

        if (config.gcm.waitForUpdate) {
            defaults.wait_for_update = config.gcm.waitForUpdate;
        }

        if (config.gcm.regions) {
            defaults.region = config.gcm.regions;
        }

        w.gtag('consent', 'default', defaults);

        if (config.gcm.adsDataRedaction) {
            w.gtag('set', 'ads_data_redaction', true);
        }

        if (config.gcm.urlPassthrough) {
            w.gtag('set', 'url_passthrough', true);
        }

        if (given) {
            w.gtag('consent', 'update', consentTypes(state.granted));
        }
    }

    // Microsoft Advertising UET consent mode
    const signal = (granted, isDefault) => {
        if (config.signals.microsoft) {
            w.uetq = w.uetq || [];
            w.uetq.push('consent', isDefault ? 'default' : 'update', {
                ad_storage: consentTypes(granted).ad_storage,
            });
        }

        if (config.signals.meta && !isDefault && typeof w.fbq === 'function') {
            w.fbq('consent', consentTypes(granted).ad_storage === 'granted' ? 'grant' : 'revoke');
        }
    };

    signal(required, true);

    if (given) {
        signal(state.granted, false);
    }

    // Google tags configured in ConsentMate. In basic mode they load once one
    // of their categories is granted, in advanced mode right away.
    const loadedTags = {};

    const loadScript = (src) => {
        const script = d.createElement('script');
        script.async = true;
        script.src = src;

        if (config.nonce) {
            script.nonce = config.nonce;
        }

        d.head.appendChild(script);
    };

    const loadGoogleTags = (granted) => {
        const advanced = !!config.gcm && config.gcm.mode === 'advanced';

        config.googleTags.forEach((tag) => {
            if (loadedTags[tag.type]) {
                return;
            }

            if (!advanced && !tag.loadOn.some((handle) => granted.indexOf(handle) !== -1)) {
                return;
            }

            loadedTags[tag.type] = true;

            if (tag.type === 'gtm') {
                w.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
                loadScript(`https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(tag.ids[0])}`);

                return;
            }

            loadScript(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(tag.ids[0])}`);
            w.gtag('js', new Date());
            tag.ids.forEach((id) => w.gtag('config', id));
        });
    };

    loadGoogleTags(state.granted);

    // The public API. The runtime adds show(), showSettings(), acceptAll(),
    // rejectAll() and the rest; calls made before it loads are queued.
    const listeners = {};
    const queue = [];
    const queued = (method) => (...args) => {
        queue.push([method, args]);
    };

    w.ConsentMate = {
        config,
        state,
        has: (handle) => state.granted.indexOf(handle) !== -1,
        on: (event, callback) => {
            (listeners[event] = listeners[event] || []).push(callback);
        },
        show: queued('show'),
        showSettings: queued('showSettings'),
        acceptAll: queued('acceptAll'),
        rejectAll: queued('rejectAll'),
        accept: queued('accept'),
        set: queued('set'),
        // Internals shared with the runtime
        _: {
            consentTypes,
            loadGoogleTags,
            signal,
            listeners,
            queue,
            required,
            handles,
            unique,
        },
    };
})();
