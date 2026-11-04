# ConsentMate

No means no, mate!

ConsentMate is a Craft-native consent manager, a replacement for Cookiebot, Cookie Information and the like. The developer sets up the consent categories, Google Consent Mode and lifetimes in a config file. The client adds tracking services (with their scripts and cookies) and edits the banner copy in the control panel, per site.

It's built for statically cached sites. Every visitor gets the same HTML, consent is read and applied in the browser, and nothing ConsentMate outputs sets a cookie or starts a session.

## Requirements

This plugin requires Craft CMS 5.6.0 or later, and PHP 8.2 or later.

---

## Price, license and support

The plugin is released under the Craft license and could be subject to license fees.
It's made for Værsågod and friends, and no support is given. Submitted issues are resolved if it scratches an itch.

## Not legal advice

ConsentMate is a tool. The client is the data controller and is responsible for what the site does with personal data, for the wording of the banner, and for declaring every cookie and service accurately. Have someone with legal knowledge look over the copy and the setup before a site launches.

## Installation

1. Install with composer via `composer require vaersaagod/consentmate` from your project directory.
2. Install the plugin in the Craft Control Panel under Settings → Plugins, or from the command line via `./craft plugin/install consentmate`.
3. Output ConsentMate in `<head>`, before any other script:

    ```twig
    <head>
        <meta charset="utf-8">
        {% hook 'consentmateHead' %}
        ...
    ```

    This outputs a small inline script, the head code of services, and the runtime and stylesheet. The banner is added at the start of `<body>` automatically.

4. Add a link that reopens the consent settings, for example in the footer. Withdrawing consent must be as easy as giving it:

    ```twig
    <button type="button" data-consentmate-open>{{ craft.consentmate.texts().floatingButtonLabel }}</button>
    ```

    Or turn on `floatingButton` in the config.

5. Gate embeds and scripts in your templates (see below), and put the cookie declaration on the privacy or cookie policy page.
6. In the CP, under ConsentMate → Services, add the services the site uses. Under Texts, fill in the company name (the data controller), check the copy, and add the link to the privacy policy.

## How it works

- **Nothing optional runs before consent.** Gated markup is output inside `<template data-consentmate="category">` elements. Browsers never run or load what's inside a `<template>`, so without consent (or without JavaScript) nothing happens. When a category is granted, the runtime inserts the markup and runs its scripts in order.
- **The consent cookie** (`consentmate`) holds a consent ID, the granted categories, the revision and a timestamp. The runtime writes it right away, then posts the choice to the server, which logs it and sets the same cookie again from the server. Safari caps cookies set from JavaScript at 7 days; the server copy keeps it for its full lifetime.
- **Revisions.** Consent is given for a revision. Enabling a new service, or moving one to another category, bumps the revision (configurable), and everyone is asked again. Clients can also bump it with "Ask everyone again" on the Services page.
- **Proof of consent.** Every choice is logged with the consent ID, the granted categories, the site, the page, the language and a snapshot of the texts and services the visitor was shown. Visitors can find their consent ID in the settings dialog and on the declaration.
- **Withdrawal.** When a category is turned off, the first-party cookies and storage declared on its services are deleted, and the page reloads so scripts that already ran are gone.
- **Static caching.** CacheMate and Blitz are purged automatically when services, texts or the revision change. For other caches, listen to `Caches::EVENT_AFTER_CHANGE`.

## Gating markup in templates

Use the `consent` tag around anything that sets cookies or contacts a third party, like video embeds, maps and widgets:

```twig
{% consent 'marketing' %}
    <script src="https://example.com/widget.js"></script>
{% endconsent %}

{# With a placeholder that explains why the content is missing, and lets the visitor allow it #}
{% consent 'marketing' with { placeholder: true, provider: 'YouTube' } %}
    <iframe src="https://www.youtube-nocookie.com/embed/{{ videoId }}" title="{{ title }}"></iframe>
{% endconsent %}
```

Or the filter, for HTML you already have in a variable (like Toolmate's `getVideoEmbed()`):

```twig
{{ embed.html|consent('marketing', { placeholder: true, provider: 'Vimeo' }) }}
```

The placeholder is a box with the placeholder text from the CP, an "Allow and show" button that grants the category, and a link to the settings. Pass a template path as `placeholder` to use your own. The wrapper around gated markup uses `display: contents`, so CSS like `.video iframe` keeps working.

Markup in a required category (`necessary`) isn't gated.

### Gating by service

Instead of a category, you can name a preset. The markup then follows the category of the service made from that preset, and the placeholder uses the service's name:

```twig
{% consent 'youtube' with { placeholder: true } %}
    <iframe src="https://www.youtube-nocookie.com/embed/{{ videoId }}" title="{{ title }}"></iframe>
{% endconsent %}

{{ embed.html|consent('vimeo', { placeholder: true }) }}
```

If the client moves YouTube to another category, the template doesn't need to change. When no service on the site is made from the preset, the preset's default category is used, and a warning is logged in dev mode.

## Services (CP)

A service is a tool that needs consent: Google Analytics, Meta Pixel, Hotjar, YouTube. Each service has:

- a category
- a provider, a purpose, a privacy policy link and data transfers outside the EU/EEA (the last three per site)
- the cookies and other storage it sets, with type, who sets it, and duration
- head code and body code, pasted as the provider gives it, run once the visitor consents to the category

A service without code is a declaration only, for example Google Analytics when it's loaded through Google Tag Manager. Services can be enabled and disabled per site.

Snippets that use `document.write()` can't be activated after the page has loaded. Current snippets from the big vendors don't use it.

### Presets

"New service" lists presets for common services, grouped by category. A service made from a preset comes with its name, provider, purpose (in each site's language), privacy policy, data transfers and cookies filled in.

Presets for tracking tools ask for an ID, like the Google Analytics measurement ID, and ConsentMate writes the tracking code. That code is updated along with the plugin. To use your own code instead, paste the full tracking code on the Code tab; pasted code replaces the generated code, so the tool never loads twice. Leave both empty to only declare the service, for example when it's loaded through Google Tag Manager.

Presets for embeds and bundled scripts (YouTube, Vimeo, maps) have no code. Gate the markup in your templates by the preset's handle (see [Gating by service](#gating-by-service)).

The built-in presets:

| Preset | Handle | Default category | Asks for |
|---|---|---|---|
| Google Analytics | `googleAnalytics` | Statistics | Measurement ID |
| Plausible | `plausible` | Statistics | Script ID (`pa-…`) |
| Fathom | `fathom` | Statistics | Site ID |
| Vimeo | `vimeo` | Statistics | |
| Meta Pixel | `metaPixel` | Marketing | Pixel ID |
| LinkedIn Insight Tag | `linkedinInsight` | Marketing | Partner ID |
| HubSpot | `hubspot` | Marketing | Hub ID |
| YouTube | `youtube` | Marketing | |
| Spotify | `spotify` | Marketing | |
| Facebook | `facebook` | Marketing | |
| Instagram | `instagram` | Marketing | |
| Google Maps | `googleMaps` | Preferences | |
| Mapbox | `mapbox` | Preferences | |
| Typeform | `typeform` | Preferences | |
| Google reCAPTCHA | `recaptcha` | Strictly necessary | |
| Adobe Fonts | `adobeFonts` | Strictly necessary | |

The cookie lists were checked against the vendors' documentation and live tests in October 2026. Vendors change them, so look over a service's cookies now and then, and keep an eye on the cookie report. Google Tag Manager isn't a preset: it's set up in the config (`googleTagManager`), since it loads for more than one category.

Add your own presets, change built-in ones or remove them in the config. Each one takes the same keys as the files in `src/presets`:

```php
'presets' => [
    // Change a built-in preset's default category
    'mapbox' => ['category' => 'necessary'],
    // Remove a built-in preset
    'adobeFonts' => false,
    // Add a preset
    'acmeChat' => [
        'name' => 'Acme Chat',
        'category' => 'preferences',
        'provider' => 'Acme AS',
        'description' => 'Lets you chat with us.',
        'cookies' => [['name' => 'acme_*', 'type' => 'cookie', 'party' => 'first', 'duration' => 30, 'unit' => 'days']],
        'fields' => ['widgetId' => ['label' => 'Widget ID', 'pattern' => '/^[a-z0-9]{8}$/']],
        'bodyCode' => '<script src="https://chat.acme.no/{widgetId}.js" async></script>',
    ],
],
```

Field patterns are required and should be strict: the value is put into the code as is.

### Moving a site over

Many sites keep their tracking tags in a "Scripts" global set or in the layout. Move each tag to a service:

1. Find the tag's preset under "New service" and fill in its ID. If there's no preset, create a blank service and paste the tag's code.
2. Pick the category, and check the cookies.
3. Remove the tag from the global set or template.

Then check the cookie report after launch: it flags tags you missed.

## The cookie report

ConsentMate keeps an eye on the declaration for you. When visitors save their consent, their browser sends the cookies this website has set, and ConsentMate compares them with the first-party cookies the services declare. Under ConsentMate → Cookie report it lists:

- **Set without consent**: declared cookies that were present while their category wasn't granted. Something sets them before consent, usually a script added straight to a template instead of to a service or inside `{% consent %}`. This is the kind of problem regulators fine for, so fix it, then clear the report.
- **Not declared**: cookies no service declares. Add each one to the service that sets it (straight from the report), or ignore it. When a cookie belongs to a preset, the report suggests it: "Create service" makes a service from the preset, or the existing one is preselected.

The nav item shows a badge with the number of open issues. Reports not seen for 90 days are deleted when Craft collects garbage, and ignored ones are kept so they stay ignored.

Cookies set by other websites (like YouTube's) are never sent to this website, so they can't be checked; declare them by hand. Craft's control panel cookies are left out. Add others that aren't set for visitors (like a CDN's) to `cookieReport.ignore` in the config.

## The cookie declaration

```twig
{{ craft.consentmate.declaration() }}
{{ craft.consentmate.declaration({ headingLevel: 3 }) }}
```

Renders every category with its services and cookies, plus the visitor's current consent and buttons to change or withdraw it. Override the template with `declarationTemplate`, or build your own from `craft.consentmate.categories()`.

## Google Consent Mode and Google Tag Manager

Consent Mode v2 is on by default: the bootstrap sets everything to denied before any tag loads, and sends an update when the visitor chooses. The categories map to consent types in the config.

Let ConsentMate load Google Tag Manager (or a Google tag) itself:

```php
'googleTagManager' => [
    'id' => App::env('GTM_ID'),
    'loadOn' => ['statistics', 'marketing'],
],
```

In the default basic mode, the container only loads once one of the `loadOn` categories is granted. A German court (VG Hannover, 2025) found that loading Google Tag Manager itself needs consent. Advanced mode (`'googleConsentMode' => ['mode' => 'advanced']`) loads it right away with everything denied; only use it if you know you want that.

ConsentMate controls whether the container loads and what consent it reports. The tags inside the container need consent checks of their own: Google's tags read Consent Mode, and other tags need "Additional consent checks" in GTM.

Sites that use Google Ads must tell visitors that data is shared with Google for ads personalization. The default banner text does, with a link to Google's page on how it uses data.

## JavaScript API

The bootstrap sets up `window.ConsentMate` before any other script runs:

```js
ConsentMate.has('statistics');          // true or false
ConsentMate.on('change', (state) => {}); // also 'ready'
ConsentMate.showSettings();
ConsentMate.show();                     // the banner
ConsentMate.acceptAll();
ConsentMate.rejectAll();
ConsentMate.accept(['statistics']);
ConsentMate.getState();                 // { id, given, granted, timestamp, gpc }

document.addEventListener('consentmate:change', (event) => console.log(event.detail));
```

Every choice also pushes a `consentmate_update` event to the `dataLayer`.

## Customizing the banner

Theme the default banner with CSS custom properties:

```css
:root {
    --consentmate-accent: #0b5;
    --consentmate-accent-fg: #fff;
    --consentmate-radius: 0;
    --consentmate-font: "Inter", sans-serif;
}
```

Or copy `src/templates/_frontend/banner.twig` into your project, point `bannerTemplate` at it, and set `includeCss` to `false` to style it from scratch. Keep the `data-consentmate-*` attributes; the comment at the top of the template lists them.

Keep the Accept all, Reject all and Settings buttons on the first layer, and make them equally prominent. Regulators treat a less visible reject option as invalid consent.

## Content Security Policy

ConsentMate's scripts get Toolmate's nonce automatically when Toolmate's CSP header is on. Otherwise set `cspNonce` to a string or a callable. Scripts activated after consent are created by the runtime, so a nonce-based policy needs `'strict-dynamic'` in `script-src`.

## Configuration

ConsentMate is configured in `config/consentmate.php`. See [src/config.php](src/config.php) for every setting, with defaults:

| Setting | Default | |
|---|---|---|
| `pluginName` | `'ConsentMate'` | The name editors see in the control panel, e.g. `'Consent'`. Translated with the `consentmate` category, so `'Consent'` shows as "Samtykke" in a Norwegian CP |
| `enabled` | `true` | A boolean, or per site handle with a `'*'` fallback |
| `categories` | necessary, preferences, statistics, marketing | Handles, `required`, and Google Consent Mode types |
| `consentLifetime` | `'P12M'` | How long consent is kept |
| `refusalLifetime` | `'P6M'` | How long a refusal is kept before asking again |
| `cookieName` / `cookieDomain` | `'consentmate'` / `null` | |
| `googleConsentMode` | enabled, basic | `mode`, `waitForUpdate`, `regions`, `urlPassthrough`, `adsDataRedaction` |
| `googleTagManager` / `googleTag` | none | `id` and `loadOn` |
| `vendorSignals` | off | `microsoft` (UET consent mode), `meta` (Pixel consent API) |
| `gpc` | enabled, marketing | Global Privacy Control: "Accept all" leaves these categories off |
| `bannerTemplate`, `declarationTemplate`, `placeholderTemplate` | `null` | Your own templates |
| `includeCss` | `true` | |
| `floatingButton` | `false` | A button that reopens the settings |
| `reloadOnWithdraw` | `true` | |
| `renewOnNewService` | `true` | Ask everyone again when a service is enabled or moved |
| `log` | enabled, `'P24M'` | `retention`, `userAgent`, `ipHash` (a keyed hash, never the IP) |
| `cspNonce` | `null` | Toolmate's nonce, a string, or a callable |
| `necessaryCookies` | ConsentMate's and Craft's cookies | Declared under "This website" |
| `rateLimit` | `30` | Consent saves per visitor per minute |
| `cookieReport` | enabled, `'P90D'` | `retention`, `ignore` (cookie names with * wildcards) |
| `presets` | `[]` | Add presets, change built-in ones, or remove them with `false` |

## Permissions

- **Manage services**: add and edit services, ask everyone again, and handle the cookie report
- **Edit texts**: edit the banner and category copy
- **View the consent log**

## Setup checklist

- [ ] `{% hook 'consentmateHead' %}` is first in `<head>`, before any other script, on every page template.
- [ ] No tracking script is hardcoded in templates outside `{% consent %}`. Check in a private window with dev tools open (Application → Cookies, and the Network tab) that nothing optional loads before a choice.
- [ ] Every service the site uses is added in the CP, with its cookies, provider and purpose, and enabled on the right sites.
- [ ] Embeds (video, maps, social widgets) are gated with a placeholder.
- [ ] There's a "Cookie settings" link on every page, or the floating button.
- [ ] The cookie declaration is on the privacy or cookie policy page, and the Texts page links to that page.
- [ ] The Texts are checked in every site language, and the company name is filled in.
- [ ] Inside Google Tag Manager, every tag has the right consent checks.
- [ ] Static caches are purged when services change (CacheMate and Blitz are automatic).
- [ ] After launch, the cookie report is empty, or every item in it is handled.

## What it doesn't do (yet)

- No crawling cookie scanner. The cookie report only sees first-party cookies, and only from visitors who save their consent.
- No regional modes. Opt-in is used everywhere, which also satisfies opt-out regimes like the UK's statistics exemption, Switzerland and the US states.
- No IAB TCF. It's only needed by publishers selling programmatic ads.
- No per-service toggles. Consent is per category.

## Credits

The cookie data in the presets is curated by hand from the vendors' own documentation, cross-checked against the [Open Cookie Database](https://github.com/jkwakman/Open-Cookie-Database) (Apache-2.0).
