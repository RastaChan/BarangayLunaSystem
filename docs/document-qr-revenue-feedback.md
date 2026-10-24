# Document QR, revenue and feedback ratings

Every newly prepared document receives a locally generated QR image in its DOCX.
Scanning opens the public document verification page. That page shows the document
number, type, copies and release status. It shows a release date only after Confirm
Release. It does not expose the applicant's contact details. The QR confirms the
portal record; users still need to compare the printed document's number and type.

Set **System Settings → Document QR verification → Portal address** to the full
portal URL that phones can access, including its folder. A LAN address works for
devices on the same network; access outside that network requires a reachable
hosted portal. Do not use localhost for phone scanning. Blank uses the address of
the browser preparing the document. `BARANGAY_PUBLIC_URL`, if configured on the
server, overrides this setting. Existing QR links stay unchanged on reprint.
Opening an older document's print view adds its verification QR to its DOCX.
Printing PDF previews continues to use the existing Microsoft Word/COM integration.
QR generation requires PHP GD and ZipArchive.

Print screens load before PDF conversion begins. Word conversion runs in a separate
PHP process with a 20-second deadline. A stalled conversion shows Retry preview and
keeps Download DOCX available. Completed PDF files are cached by document contents;
changing a document, including upgrading its QR layout, creates a fresh preview.
Only one Word conversion runs at a time. Preparing or retrying a preview never
confirms release. Image-based certificate templates place the QR above the footer
instead of in the header, and earlier QR stamps are upgraded on opening Print.
The server needs the PHP CLI executable alongside its PHP configuration, Word/COM,
and permission to start a local worker process. Document/template preview requests
release their session lock before conversion so other portal actions can continue.

**Reports & Analytics** has date-filtered document revenue, payment counts,
cash tendered, change due, unpaid balance, daily collections and totals by document
type. Revenue sums the fees due on Paid transactions by payment date. Cash given
above the fee is change, not additional revenue. Unpaid and Voided transactions are
excluded. The unpaid balance is a separate current all-time total. Payments for
prepared documents count when paid; document release counts remain tied to actual
Confirm Release. The revenue CSV exports the selected period's paid transactions.

**Resident → Send Feedback** requires a 1–5 rating. Admin and Staff see it in the
feedback table and View popup. Reports & Analytics shows the all-time average and
each rating's count, excluding archived feedback. Older feedback remains unrated
and does not change the average. Anonymous submissions display Anonymous.

Existing installations add the new database columns on feature load. The baseline
schema includes the same columns for new installations. Regression tests use
isolated databases and do not send SMS or modify live records:

    php tests/research-features-test.php
    php tests/short-issuance-workflow-test.php
    php tests/document-pdf-test.php
    node tests/document-print-test.cjs
