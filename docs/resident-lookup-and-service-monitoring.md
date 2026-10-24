# Resident lookup, duplicate review, and service monitoring

Accounts are created only by authorized Admin or Staff. Registration keeps the existing requirements checklist and automatic account SMS. Saved accounts remain active and immediately usable. Unregistered walk-ins can still receive documents without an account.

## Issue Documents

The Applicant type dropdown contains only **Walk-in — no registered account** and **Registered Resident**. Walk-in makes the search box read-only, disables Search, hides QR controls, and allows manual applicant details. Registered Resident enables searching by partial name or Resident ID, hides the walk-in fields, and allows camera/photo QR lookup.

Names are searched on the server after at least two characters, with a 300ms typing delay and a maximum of 20 active resident matches per response. Staff must choose the correct name and ID from those results. Additional matches prompt staff to refine the name. There is no all-resident dropdown or full resident directory embedded in the issuance page. Each selected record's saved details are fetched separately and displayed without editable identity fields. Editing the search clears the previous selection so it cannot accidentally issue a document for the earlier person. Switching to Walk-in ignores pending registered-resident search responses and stops any camera scan.

Select **Scan QR** to use a desktop webcam or a phone camera; the first decoded QR automatically selects the resident and stops the camera. Select **Read QR photo** to read a saved JPG, PNG, or WEBP image (up to 10 MB) on a desktop without a webcam. Photos are decoded locally in the browser and are not uploaded. QR/photo scanning is available in Registered Resident mode.

The bundled jsQR decoder is served locally so scanning does not need a public CDN. Live camera permission requires HTTPS or localhost; photo reading works where a browser blocks live camera access on a local HTTP network address. Leaving or hiding the page stops the camera, including camera streams that arrive after a pending permission request has been cancelled.

Selecting a matching resident fills the applicant details; it does not prepare, print, pay for, or release a document. Staff must review the details and continue the usual workflow.

Inactive accounts and inactive QR codes cannot be selected through QR lookup. Old/altered QR strings cannot fall back to their embedded Resident ID. Lookup requires an active Admin/Staff session and the issuance form's CSRF token. Unknown residents can continue through the unregistered walk-in fields.

## Registration duplicate warning

Saving first checks for existing resident accounts with the same full name and birthdate, including inactive accounts. Matching ignores letter case and repeated whitespace. Different birthdates are separate; fuzzy name matching is not performed. A blank optional birthdate cannot establish this match.

Staff see the existing IDs, names, birthdates, and account statuses. Use the existing account if it is the same person. For separate people with matching details, staff can confirm that they checked the records and save again. The server independently repeats the check and requires a session-signed confirmation tied to the name, birthdate, and matching record IDs. Editing identity details clears the confirmation. A lookup failure leaves the form available for retry and prevents an unchecked save. Overrides are recorded in the account creation audit entry.

This is a review warning, not an absolute uniqueness constraint on people. Simultaneous registrations that both begin before either account exists can still require later manual review.

## Processing time reports

Admin Reports & Analytics and Admin/Staff Service Reports show:

- Request submission to preparation, for requests prepared in the chosen dates.
- Request submission to actual release, for online documents released in the chosen dates.
- Walk-in preparation to actual release, for walk-in documents released in the chosen dates.
- Current open request count, oldest age, and the oldest 20 open requests across all dates.

The shared date filters and timing CSV export use the same calculations. Timings are elapsed calendar seconds, including overnight and pickup delays, not working hours or pure staff handling time. Walk-in timing begins when the prepared document record is saved, not when the applicant arrives. Preparation still does not count as release.

New issued documents record `prepared_at`; requests record `ready_at` on their first successful ready transition. Existing release timestamps are used where available. Schema upgrades leave old missing preparation timestamps null; reports show sample counts and exclude missing or reversed timestamps from averages. No historical timestamps are guessed. Archived/rejected requests are excluded from the open list; historical completed transactions remain in the applicable timing cohorts.

## Verification

Run `tests/resident-service-tools-test.php` for isolated database checks, `tests/resident-service-endpoint-test.php` for Admin/Staff access and CSRF checks, and `tests/resident-service-tools-ui-test.cjs` for lookup/form behavior. Existing short issuance and registration workflow suites verify payment/release synchronization and requirement/account SMS behavior. Test fixtures never send real SMS or change live resident records. Desktop and 390px mobile previews were checked with the real view markup and dashboard stylesheet; temporary preview files were removed afterwards.

Camera and photo behavior is covered by `tests/issue-qr-capture-test.cjs`. Pipe the CLI JSON output of `tests/issue-qr-pixels.php` into `tests/issue-qr-decoder-test.cjs` to check the actual bundled decoder against a generated QR and a blank image. A browser photo-selection check decoded the same test QR and passed its value into the resident lookup. The temporary image and preview page were removed afterwards.
