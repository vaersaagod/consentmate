# ConsentMate Changelog

## 1.0.0-alpha.1 - Unreleased
### Added
- Added the consent banner and settings dialog, with Accept all, Reject all and Settings on the first layer
- Added the Service element type, for tracking services with their scripts and cookies, enabled per site
- Added per-site texts for the banner, settings, placeholders and categories, with English and Norwegian defaults
- Added gating of markup with the `{% consent %}` tag and `|consent` filter, with optional placeholders
- Added the consent cookie, re-set by the server so it isn't capped by Safari
- Added consent revisions, asking everyone again when a service is enabled or moved, or on demand
- Added the consent log with snapshots of what visitors were shown, and retention through garbage collection
- Added the cookie declaration (`craft.consentmate.declaration()`)
- Added Google Consent Mode v2, loading of Google Tag Manager or a Google tag after consent, and Microsoft UET and Meta consent signals
- Added Global Privacy Control support
- Added purging of CacheMate and Blitz when services, texts or the revision change
- Added CSP nonce support, using ToolMate's nonce when available
- Added the cookie report, listing undeclared cookies and cookies set without consent
- Added an asset bundle for the front-end files, so they're published to the CDN on Craft Cloud
- Added service presets for common tracking tools and embeds, with cookie declarations and English and Norwegian texts
- Added generated tracking code for presets, from an ID like the Google Analytics measurement ID, with pasted code taking its place when there is any
- Added the `presets` config setting and `Presets::EVENT_REGISTER_PRESETS`, to add, change or remove presets
- Added gating by service, e.g. `{% consent 'youtube' %}`, which follows the category of the service made from the preset
- Added preset suggestions to the cookie report
- Added the `pluginName` setting, to change (and translate) the name editors see in the control panel
