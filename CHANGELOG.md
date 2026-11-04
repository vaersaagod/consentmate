# ConsentMate Changelog

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
