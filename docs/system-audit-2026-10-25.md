# System audit — October 25, 2026

## Fixed

- Removed fictional public phone/email details from the login page and registration requirements. Public contact numbers, office hours, and address now come from an allowlisted settings query. Missing contact information directs visitors to the office. Private SMS/security settings are excluded.
- Removed invented contact/hotline fallback values from the settings form.
- Fixed notification state writes failing when a caller already owns a database transaction. Read/unread changes preserve transaction ownership and account isolation.
- Updated obsolete tests that still expected resident-only notification storage and removed template Edit/Delete modal buttons. Current template replacement controls remain functional.

## Verification

- 43 PHP/backend suites passed using temporary databases: 42 original suites, with the two initial failures corrected and rerun, plus the new public office details suite.
- 42 JavaScript suites passed. The additional real QR pixel decoding check also passed.
- 163 application PHP files passed syntax checks; the new test file also executed successfully.
- 41 Admin, Staff, and Resident pages rendered using test accounts without PHP warnings or fatal errors. This includes both Mediation Schedule pages.
- Local image, stylesheet, and script references checked in the 39 original page fixtures: no missing files.
- All 77 standalone system SVG files have valid SVG namespaces.
- Live unauthenticated login smoke test: HTTP 200, login form present, no PHP error output, and no fictional public contact values.
- No remaining demo/coming-soon/not-implemented markers found in the scanned application PHP/JavaScript. Input examples and document template placeholders are intentional form/template functionality.

## Practical limits

These are code, automated behavior, and server-rendering checks. They do not prove every visual layout or every production record is correct. Physical phone-camera access, printer output, and actual SMS receipt were not exercised. No live accounts or documents were changed and no live SMS was sent. Temporary audit databases were removed.
