<?php

/**
 * ConsentMate example config file
 *
 * Copy to your Craft project's config folder as `consentmate.php` to override
 * any of the default settings below. Supports Craft's multi-environment
 * config format.
 *
 * Client-facing content (services with their scripts and cookies, and the
 * banner copy) is edited in the control panel, under ConsentMate.
 */

use craft\helpers\App;

return [

    '*' => [

        /*
         * The plugin's name in the control panel. Keep it short, the sidebar
         * is narrow. It's translated, so 'Consent' shows as 'Samtykke' in a
         * Norwegian control panel; translate your own name in the project's
         * translations/<language>/consentmate.php.
         */
        //'pluginName' => 'Consent',

        /*
         * Whether ConsentMate is enabled. A boolean, or an array keyed by site
         * handle with '*' as the fallback:
         *
         * 'enabled' => ['*' => true, 'intranet' => false],
         */
        //'enabled' => true,

        /*
         * The consent categories, in display order. `required` marks the one
         * category that can't be refused. `google` lists the Google Consent
         * Mode types granted with the category. Names and descriptions are
         * edited per site in the CP.
         */
        //'categories' => [
        //    'necessary' => ['required' => true, 'google' => ['security_storage']],
        //    'preferences' => ['google' => ['functionality_storage', 'personalization_storage']],
        //    'statistics' => ['google' => ['analytics_storage']],
        //    'marketing' => ['google' => ['ad_storage', 'ad_user_data', 'ad_personalization']],
        //],

        /*
         * How long a consent is kept, and how long a refusal is kept before
         * the visitor is asked again. Seconds, or a duration string.
         */
        //'consentLifetime' => 'P12M',
        //'refusalLifetime' => 'P6M',

        /*
         * The consent cookie. Set `cookieDomain` to a parent domain
         * ('.example.com') to share consent across subdomains.
         */
        //'cookieName' => 'consentmate',
        //'cookieDomain' => null,

        /*
         * Google Consent Mode v2. In 'basic' mode, Google tags only load after
         * consent. 'advanced' loads them right away with everything denied
         * (cookieless pings), which German courts have found to need consent
         * too, so only use it if you know you want it.
         */
        //'googleConsentMode' => [
        //    'enabled' => true,
        //    'mode' => 'basic',
        //    'waitForUpdate' => 500,
        //    'regions' => null,
        //    'urlPassthrough' => false,
        //    'adsDataRedaction' => true,
        //],

        /*
         * A Google Tag Manager container or Google tag that ConsentMate loads
         * itself. In basic mode it loads once any of the `loadOn` categories
         * is granted. Configure consent checks for the tags inside the
         * container: ConsentMate only controls whether the container loads
         * and what consent it reports.
         */
        //'googleTagManager' => [
        //    'id' => App::env('GTM_ID'),
        //    'loadOn' => ['statistics', 'marketing'],
        //],
        //'googleTag' => [
        //    'id' => App::env('GOOGLE_TAG_ID'), // or an array of IDs
        //    'loadOn' => ['statistics', 'marketing'],
        //],

        /*
         * Consent signals for other vendors' tags. Microsoft Clarity reads
         * Google Consent Mode and needs no adapter.
         */
        //'vendorSignals' => [
        //    'microsoft' => false, // Microsoft Advertising UET consent mode
        //    'meta' => false, // Meta Pixel consent API
        //],

        /*
         * Global Privacy Control. While a visitor's browser sends the signal,
         * "Accept all" doesn't grant these categories. The visitor can still
         * turn them on one by one in the settings.
         */
        //'gpc' => [
        //    'enabled' => true,
        //    'categories' => ['marketing'],
        //],

        /*
         * Templates for the banner and settings dialog, the cookie
         * declaration, and placeholders. Null uses the plugin's own. Copy
         * them from vendor/vaersaagod/consentmate/src/templates/_frontend to
         * start from the defaults, and keep the data-consentmate-* attributes.
         */
        //'bannerTemplate' => null,
        //'declarationTemplate' => null,
        //'placeholderTemplate' => null,

        /*
         * Whether to output the default banner CSS. Theme it through the
         * --consentmate-* custom properties, or turn it off and style it
         * yourself.
         */
        //'includeCss' => true,

        /*
         * Whether to show a floating button that reopens the settings. Any
         * element with a data-consentmate-open attribute does the same.
         */
        //'floatingButton' => false,

        /*
         * Whether to reload the page after a visitor withdraws consent, so
         * scripts that already ran are gone.
         */
        //'reloadOnWithdraw' => true,

        /*
         * Whether enabling a service, or moving one to another category, asks
         * every visitor for consent again.
         */
        //'renewOnNewService' => true,

        /*
         * The consent log, the proof of consent GDPR requires. `retention` is
         * how long rows are kept. `ipHash` stores a keyed hash of the IP
         * address, never the address itself.
         */
        //'log' => [
        //    'enabled' => true,
        //    'retention' => 'P24M',
        //    'userAgent' => true,
        //    'ipHash' => false,
        //],

        /*
         * The CSP nonce for ConsentMate's scripts. Null uses ToolMate's nonce
         * when ToolMate's CSP is enabled. With a nonce-based policy, add
         * 'strict-dynamic' to script-src so the scripts ConsentMate activates
         * after consent are allowed to run.
         */
        //'cspNonce' => null,

        /*
         * Cookies declared under the built-in "This website" service. Null
         * declares ConsentMate's cookie and Craft's CSRF, session and login
         * cookies, with names and durations from the general config. Each
         * item takes name, type, party, duration, unit and an optional
         * purpose (translated with the `site` category):
         *
         * 'necessaryCookies' => [
         *     ['name' => 'consentmate', 'type' => 'cookie', 'party' => 'first', 'duration' => 12, 'unit' => 'months', 'purpose' => 'Remembers your cookie choices.'],
         * ],
         */
        //'necessaryCookies' => null,

        /*
         * The maximum number of consent saves per visitor (by IP) per minute.
         */
        //'rateLimit' => 30,

        /*
         * The cookie report. When visitors save their consent, the cookies
         * this website has set are compared with the ones services declare.
         * Undeclared cookies, and declared cookies set before their category
         * was granted, are listed in the CP under ConsentMate → Cookie report.
         * `retention` is how long a report is kept after it was last seen.
         * `ignore` lists cookie names (with * wildcards) never reported, like
         * cookies set by the hosting or a CDN.
         */
        //'cookieReport' => [
        //    'enabled' => true,
        //    'retention' => 'P90D',
        //    'ignore' => [],
        //],

        /*
         * Service presets: add your own, change built-in ones (e.g. their
         * default category), or remove them with false. Each takes the same
         * keys as the files in src/presets. Field patterns are required and
         * should be strict, since the value is put into the code as is.
         */
        //'presets' => [
        //    'mapbox' => ['category' => 'necessary'],
        //    'adobeFonts' => false,
        //],

    ],

];
