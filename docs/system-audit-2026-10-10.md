# System review — October 10, 2026

## Fixes

- Corrected the undefined account variable used by Admin, Staff, and Resident dashboard counters.
- Handled missing staff position and mobile values without PHP warnings.
- Replaced the QR Management internet scanner dependencies with the bundled QR decoder. Camera scanning falls back to local decoding when native QR detection is unavailable; photo reading uses the same local decoder.
- Added QR photo format/size checks, lookup timeouts, and cancellation when the page is hidden. Camera streams stop after decoding and before photo scans.
- Corrected hidden enhanced dropdown controls that escaped scrollable registration tables and expanded the mobile page. Dropdown selection and form values remain functional.
- Made Backup & Restore cards fit small phones and aligned their buttons. Scoped its button/badge styles so they cannot override shared controls.
- Scoped the Reports toolbar's button size styles.
- Replaced an inert legacy Resident announcement button with a functional expand control, preserving line breaks and safe text rendering.
- Corrected the walk-in preview instruction to support applicants without accounts.
- Updated obsolete checks for the removed Resident Verification page and older issuance/download controls.

## Verification

- PHP syntax scan across the application, plus syntax checks for edited files.
- 25 PHP test suites passed using temporary databases, including document release/payment handling, resident registration and inactivity, resident lookup, protected attachments, revenue, ratings, and mocked SMS workflows. Four obsolete checks were corrected and rerun successfully.
- 17 JavaScript suites passed, including local QR decoding, camera lifecycle, mobile dropdowns, profile cropping, notifications, resident search, and document preview recovery.
- Rendered 39 Admin, Staff, and Resident pages using test records without PHP warnings or fatal errors after fixes.
- Reviewed the 39 rendered pages at 320px phone, 768px tablet, and 1280px desktop widths. Final checks found no expanded page widths; large tables retain their own scrolling.
- Verified Resident profile ID popup sizing at 390px and changed a registration dropdown option through the mobile UI.

These checks used isolated records and browser layout fixtures. No production documents were released, production accounts changed, or live SMS sent. Physical cameras, printers, and live SMS delivery were not exercised. Temporary databases and browser fixtures were removed.

Repeat backend checks with `tests/run-isolated-audit.php`, JavaScript checks with the `.cjs` suites, and responsive rendering with `tests/audit-layout-preview.php`.
