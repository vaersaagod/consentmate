# ConsentMate Changelog

## 1.0.4 - 2026-11-04
### Added
- Added updating services' cookies when their preset's cookies change. Cookies the client added, changed or deleted are kept as they are, and the service's Cookies field says what the last update changed
- Added the `consentmate/presets/sync` command, for after changing a project's own presets
- Added notes to the presets for HubSpot (ad pixels it loads), Mapbox (gating the CDN script), Typeform (its EU data centre), LinkedIn Insight Tag (loading it through Google Tag Manager), Google Analytics (when `_gcl_au` is set) and reCAPTCHA (Google's local companies)

### Fixed
- Fixed the durations of Meta's `datr` and `sb` cookies (400 days) in the Facebook preset, and of `datr` and `csrftoken` in the Instagram preset
- Fixed the LinkedIn Insight Tag preset missing the `li_mc`, `lms_ads`, `lms_analytics`, `_guid`, `ar_debug` and `__cf_bm` cookies
- Fixed the Typeform preset missing the visitor ID it keeps per form in local storage
- Fixed the HubSpot and LinkedIn Insight Tag presets rejecting short Hub IDs and partner IDs
- Fixed the reCAPTCHA preset linking to Google Cloud's privacy notice instead of its Data Processing Addendum, which covers visitor data since April 2026
- Fixed the Adobe Fonts preset's note suggesting its fonts may be self-hosted
- Fixed the data transfers of the Fathom and Spotify presets, and the Plausible preset's description, to match what the vendors say

## 1.0.3 - 2026-11-04
### Fixed
- Fixed searching and filtering the consent log returning a 404
- Fixed the consent log table sitting right under the search bar
- Fixed the revisions table in the consent log not using the full width

## 1.0.2 - 2026-11-04
### Fixed
- Fixed cookie names breaking every few characters in narrow cookie tables, like the settings dialog on phones

## 1.0.1 - 2026-11-04
### Changed
- Changed the default stylesheet to reset the elements in ConsentMate's markup, so global element styles on the site (`p`, `h2`, `li`, `label`, `button`) no longer change the banner, the settings dialog, placeholders and service boxes
- Changed the toggle switch selector to `.consentmate-switch[type="checkbox"]`, so global checkbox styles no longer turn it back into a checkbox

### Fixed
- Fixed the cookie declaration showing the given consent before the visitor had made a choice, on sites that set `display` on paragraphs
- Fixed paragraph styles reaching the cookie declaration's buttons

## 1.0.0 - 2026-11-04
### Added
- Initial public release
