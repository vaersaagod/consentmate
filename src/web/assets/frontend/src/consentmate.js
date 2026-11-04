/**
 * ConsentMate runtime
 *
 * Loaded with `defer`, after the inline bootstrap. Shows the banner, wires up
 * the settings dialog, activates gated markup (<template data-consentmate>)
 * once its category is granted, saves choices (cookie + server log), and
 * cleans up cookies when consent is withdrawn.
 *
 * Every visitor gets the same HTML; all of this happens in the browser, so
 * statically cached pages work.
 */
(() => {
    const w = window;
    const d = document;
    const cm = w.ConsentMate;

    if (!cm || cm._ === undefined || cm._.runtime) {
        return;
    }

    const { config, state } = cm;
    const internals = cm._;
    const { required, handles, unique, listeners } = internals;
    const optional = handles.filter((handle) => required.indexOf(handle) === -1);

    internals.runtime = true;

    let ready = false;

    // Events
    // =========================================================================

    const publicState = () => ({
        id: state.id,
        given: state.given,
        granted: state.granted.slice(),
        timestamp: state.timestamp,
        gpc: state.gpc,
    });

    const emit = (name, detail) => {
        (listeners[name] || []).forEach((callback) => {
            try {
                callback(detail);
            } catch (e) {
                console.error(e);
            }
        });

        d.dispatchEvent(new CustomEvent(`consentmate:${name}`, { detail }));
    };

    // Activating gated markup
    // =========================================================================

    const activated = [];

    // How long an external script can hold up the scripts after it
    const scriptTimeout = 10000;

    const loadScriptInOrder = (original, marker) => new Promise((resolve) => {
        const script = d.createElement('script');

        for (let i = 0; i < original.attributes.length; i++) {
            const attribute = original.attributes[i];
            script.setAttribute(attribute.name, attribute.value);
        }

        if (config.nonce) {
            script.nonce = config.nonce;
        }

        if (original.src) {
            const isAsync = original.hasAttribute('async');
            // Browsers that support modules skip nomodule scripts without
            // firing load or error
            const isSkipped = original.hasAttribute('nomodule') && 'noModule' in script;
            script.async = isAsync;

            if (!isAsync && !isSkipped) {
                // Wait for external scripts before running the next one, so
                // snippets like <script src=lib.js> + <script>Lib.init()</script>
                // run in order. A script that never loads (or has a type
                // the browser doesn't run) only holds the rest up for a while.
                script.onload = () => resolve();
                script.onerror = () => resolve();
                w.setTimeout(resolve, scriptTimeout);
            }

            marker.parentNode.replaceChild(script, marker);

            if (isAsync || isSkipped) {
                resolve();
            }

            return;
        }

        script.text = original.text;
        marker.parentNode.replaceChild(script, marker);
        resolve();
    });

    const activateTemplate = (template) => {
        const fragment = template.content.cloneNode(true);
        const scripts = [];

        // Swap scripts for markers; they're recreated one by one below, since
        // scripts cloned from a template don't run in a predictable order
        fragment.querySelectorAll('script').forEach((script) => {
            const marker = d.createComment('consentmate');
            script.parentNode.replaceChild(marker, script);
            scripts.push([script, marker]);
        });

        const gate = template.closest('[data-consentmate-gate]');
        template.parentNode.replaceChild(fragment, template);

        if (gate) {
            gate.querySelectorAll('[data-consentmate-placeholder]').forEach((placeholder) => placeholder.remove());
            gate.setAttribute('data-consentmate-gate', 'active');
        }

        return scripts.reduce(
            (chain, [script, marker]) => chain.then(() => loadScriptInOrder(script, marker)),
            Promise.resolve()
        );
    };

    let activation = Promise.resolve();

    const activateGranted = () => {
        const templates = Array.prototype.slice.call(d.querySelectorAll('template[data-consentmate]'));

        templates.forEach((template) => {
            const category = template.getAttribute('data-consentmate');

            if (activated.indexOf(template) !== -1 || state.granted.indexOf(category) === -1) {
                return;
            }

            activated.push(template);
            activation = activation.then(() => activateTemplate(template)).catch((e) => console.error(e));
        });

        return activation;
    };

    // Cookies
    // =========================================================================

    const uuid = () => {
        if (w.crypto && typeof w.crypto.randomUUID === 'function') {
            return w.crypto.randomUUID();
        }

        const bytes = new Uint8Array(16);
        w.crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.prototype.map.call(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    };

    const writeCookie = () => {
        const value = encodeURIComponent(JSON.stringify({
            id: state.id,
            r: state.revision,
            c: state.granted,
            t: state.timestamp,
        }));
        const hasOptional = state.granted.some((handle) => required.indexOf(handle) === -1);
        const maxAge = hasOptional ? config.cookie.lifetime : config.cookie.refusalLifetime;
        let cookie = `${config.cookie.name}=${value}; Path=/; Max-Age=${maxAge}; SameSite=Lax`;

        if (config.cookie.domain) {
            cookie += `; Domain=${config.cookie.domain}`;
        }

        if (w.location.protocol === 'https:') {
            cookie += '; Secure';
        }

        d.cookie = cookie;
    };

    const escapeRegex = (value) => value.replace(/[.+?^${}()|[\]\\]/g, '\\$&');

    const patternsFor = (categories) => {
        const patterns = [];

        categories.forEach((handle) => {
            (config.cookies[handle] || []).forEach((name) => {
                patterns.push(new RegExp(`^${name.split('*').map(escapeRegex).join('.*')}$`));
            });
        });

        return patterns;
    };

    const deleteStorage = (categories) => {
        const patterns = patternsFor(categories);

        if (!patterns.length) {
            return;
        }

        const matches = (name) => patterns.some((pattern) => pattern.test(name));

        // Cookies, on the current host and every parent domain
        const hostParts = w.location.hostname.split('.');
        const domains = [''];

        for (let i = 0; i < hostParts.length - 1; i++) {
            domains.push(`; Domain=.${hostParts.slice(i).join('.')}`);
        }

        d.cookie.split('; ').forEach((part) => {
            const name = part.split('=')[0];

            if (name && name !== config.cookie.name && matches(name)) {
                domains.forEach((domain) => {
                    d.cookie = `${name}=; Path=/; Max-Age=0${domain}`;
                });
            }
        });

        // Local and session storage
        [w.localStorage, w.sessionStorage].forEach((storage) => {
            try {
                Object.keys(storage).filter(matches).forEach((key) => storage.removeItem(key));
            } catch (e) {
                // Storage can be unavailable (privacy modes)
            }
        });
    };

    // Saving
    // =========================================================================

    // Goes by the latest choice, since an earlier save can finish after the
    // visitor has made a new one
    const deleteDenied = () => deleteStorage(handles.filter((handle) => state.granted.indexOf(handle) === -1));

    // Each response sets the cookie, so a save still on its way is aborted
    // when the visitor makes a new choice. Its response could otherwise
    // arrive last (even after a reload) and bring back the earlier choice.
    // Saves aren't sent with keepalive, since browsers don't cancel aborted
    // keepalive requests; one still on its way when the visitor leaves the
    // page is sent again with keepalive instead (see init()).
    let pending = null;

    const post = (body, keepalive, signal) => w.fetch(config.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
        body,
        signal,
    });

    const send = (action, previous) => {
        if (!config.endpoint || typeof w.fetch !== 'function') {
            return Promise.resolve();
        }

        const body = JSON.stringify({
            consentId: state.id,
            categories: state.granted,
            // What was granted before this choice, for the cookie report
            previous,
            action,
            revision: state.revision,
            snapshot: config.snapshot,
            gpc: state.gpc,
            path: w.location.pathname,
            language: d.documentElement.lang || '',
        });

        if (pending && pending.controller) {
            pending.controller.abort();
        }

        const controller = typeof w.AbortController === 'function' ? new w.AbortController() : null;
        const current = { body, controller };
        pending = current;

        return post(body, false, controller ? controller.signal : undefined).catch(() => {}).then(() => {
            if (pending === current) {
                pending = null;
            }
        });
    };

    const sendPendingOnLeave = () => {
        if (!pending) {
            return;
        }

        const { body, controller } = pending;
        pending = null;

        if (controller) {
            controller.abort();
        }

        post(body, true).catch(() => {});
    };

    let banner = null;
    let dialog = null;
    let floating = null;

    const save = (action, next) => {
        const granted = unique(required.concat(next.filter((handle) => handles.indexOf(handle) !== -1)));
        // What was granted before this choice, including an out-of-date
        // consent's categories, since their cookies can still be there
        const previous = unique(required.concat(state.given ? state.granted : (internals.previous || [])));
        // What was active on this page and is now turned off
        const withdrawn = state.given ? state.granted.filter((handle) => granted.indexOf(handle) === -1) : [];

        state.id = state.id || uuid();
        state.given = true;
        state.granted = granted;
        state.timestamp = Math.floor(Date.now() / 1000);
        state.revision = config.revision;

        writeCookie();

        if (config.gcm) {
            w.gtag('consent', 'update', internals.consentTypes(granted));
        }

        internals.signal(granted, false);
        w.dataLayer.push({ event: 'consentmate_update', consentmate: { granted: granted.slice(), action } });

        const request = send(withdrawn.length ? 'withdraw' : action, previous);

        // Cookies of categories that aren't granted are deleted, whether
        // they're from a withdrawn or out-of-date consent or were set without
        // consent. Not until the save is sent, since the cookie report reads
        // them from that request.
        const cleanUp = request.then(deleteDenied);

        showBanner(false);

        if (dialog && dialog.open) {
            dialog.close();
        }

        updateStatus();
        emit('change', publicState());

        if (withdrawn.length && config.reload) {
            // Scripts that already ran can't be unloaded. Reload once the
            // choice is saved, or after a second at most.
            const reload = () => {
                deleteDenied();
                w.location.reload();
            };
            cleanUp.then(reload);
            w.setTimeout(reload, 1000);

            return;
        }

        internals.loadGoogleTags(granted);
        activateGranted();
    };

    const acceptAllList = () => {
        if (!state.gpc) {
            return optional;
        }

        // With Global Privacy Control on, "accept all" leaves out the GPC
        // categories, unless the visitor already granted them one by one
        return optional.filter((handle) => config.gpc.categories.indexOf(handle) === -1 || state.granted.indexOf(handle) !== -1);
    };

    // UI
    // =========================================================================

    const showBanner = (show) => {
        d.documentElement.setAttribute('data-consentmate', state.given && !show ? 'done' : 'pending');

        if (banner) {
            if (!show && banner.contains(d.activeElement)) {
                d.activeElement.blur();
            }

            banner.hidden = !show;
        }

        if (floating) {
            floating.hidden = show || !state.given;
        }
    };

    let opener = null;

    const syncSettings = () => {
        if (!dialog) {
            return;
        }

        dialog.querySelectorAll('input[data-consentmate-category]').forEach((input) => {
            const handle = input.getAttribute('data-consentmate-category');
            input.checked = required.indexOf(handle) !== -1 || state.granted.indexOf(handle) !== -1;
        });

        dialog.querySelectorAll('[data-consentmate-gpc]').forEach((element) => {
            element.hidden = !state.gpc;
        });
    };

    const openSettings = () => {
        if (!dialog) {
            return;
        }

        syncSettings();
        updateStatus();
        opener = d.activeElement;

        if (typeof dialog.showModal === 'function') {
            if (!dialog.open) {
                dialog.showModal();
            }
        } else {
            dialog.setAttribute('open', '');
        }
    };

    const formatDate = (timestamp) => {
        const date = new Date(timestamp * 1000);

        try {
            return date.toLocaleString(d.documentElement.lang || undefined);
        } catch (e) {
            // An invalid lang attribute, like nb_NO
            return date.toLocaleString();
        }
    };

    const updateStatus = () => {
        d.querySelectorAll('[data-consentmate-status]').forEach((element) => {
            element.querySelectorAll('[data-consentmate-status-given]').forEach((given) => {
                given.hidden = !state.given;
            });

            element.querySelectorAll('[data-consentmate-status-none]').forEach((none) => {
                none.hidden = state.given;
            });

            element.querySelectorAll('[data-consentmate-status-id]').forEach((id) => {
                id.textContent = state.id || '';
            });

            element.querySelectorAll('[data-consentmate-status-date]').forEach((date) => {
                date.textContent = state.timestamp ? formatDate(state.timestamp) : '';
            });

            element.querySelectorAll('[data-consentmate-status-categories]').forEach((list) => {
                list.textContent = config.categories
                    .filter((category) => state.granted.indexOf(category.handle) !== -1)
                    .map((category) => category.name)
                    .join(', ');
            });
        });
    };

    const trackBannerHeight = () => {
        if (!banner || typeof w.ResizeObserver !== 'function') {
            return;
        }

        // Keep focused elements from scrolling under a fixed banner (WCAG 2.4.11)
        new w.ResizeObserver(() => {
            d.documentElement.style.setProperty('--consentmate-banner-height', `${banner.hidden ? 0 : banner.offsetHeight}px`);
        }).observe(banner);
    };

    const onClick = (event) => {
        const target = event.target.closest('[data-consentmate-open], [data-consentmate-action], [data-consentmate-accept]');

        if (!target) {
            return;
        }

        if (target.hasAttribute('data-consentmate-accept')) {
            event.preventDefault();
            const category = target.getAttribute('data-consentmate-accept');
            save('placeholder', state.granted.concat([category]));

            return;
        }

        if (target.hasAttribute('data-consentmate-open')) {
            event.preventDefault();
            openSettings();

            return;
        }

        const action = target.getAttribute('data-consentmate-action');

        switch (action) {
            case 'accept-all':
                event.preventDefault();
                save('accept-all', acceptAllList());
                break;
            case 'reject-all':
                event.preventDefault();
                save('reject-all', []);
                break;
            case 'settings':
                event.preventDefault();
                openSettings();
                break;
            case 'save': {
                event.preventDefault();
                const checked = [];
                const scope = target.closest('[data-consentmate-dialog]') || d;
                scope.querySelectorAll('input[data-consentmate-category]').forEach((input) => {
                    if (input.checked) {
                        checked.push(input.getAttribute('data-consentmate-category'));
                    }
                });
                save('custom', checked);
                break;
            }
            case 'close':
                event.preventDefault();
                if (dialog && dialog.open) {
                    dialog.close();
                }
                break;
            default:
                break;
        }
    };

    // Init
    // =========================================================================

    const init = () => {
        const root = d.querySelector('[data-consentmate-root]');

        if (root) {
            banner = root.querySelector('[data-consentmate-banner]');
            dialog = root.querySelector('[data-consentmate-dialog]');
            floating = root.querySelector('[data-consentmate-floating]');
        }

        if (dialog) {
            dialog.addEventListener('close', () => {
                if (opener && typeof opener.focus === 'function' && d.contains(opener)) {
                    opener.focus();
                }

                opener = null;
            });
        }

        d.addEventListener('click', onClick);
        w.addEventListener('pagehide', sendPendingOnLeave);

        trackBannerHeight();
        showBanner(!state.given);
        updateStatus();

        Object.assign(cm, {
            show: () => showBanner(true),
            showSettings: openSettings,
            acceptAll: () => save('accept-all', acceptAllList()),
            rejectAll: () => save('reject-all', []),
            accept: (categories) => save('custom', state.granted.concat([].concat(categories))),
            set: (categories) => save('custom', [].concat(categories)),
            getState: publicState,
            on: (event, callback) => {
                (listeners[event] = listeners[event] || []).push(callback);

                if (event === 'ready' && ready) {
                    callback(publicState());
                }
            },
        });

        activateGranted().then(() => {
            ready = true;
            emit('ready', publicState());
        });

        // Calls made before the runtime loaded
        internals.queue.splice(0).forEach(([method, args]) => cm[method](...args));
    };

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
