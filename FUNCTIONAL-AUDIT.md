# Functional audit

## Completed in this pass

- Replaced the shared Security Settings password demo with a database-backed change for admin, staff and resident accounts. Current-password verification, CSRF protection, length/match validation, audit logging and transactional updates are included.
- Removed obsolete demo-success JavaScript for staff creation and announcement posting; their existing server forms remain intact.
- Connected Resident Verification search, including household numbers.
- Connected optional resident complaint evidence and document-request attachments. Accepts JPG, PNG and PDF up to 5 MB. Request and attachment are saved together; downloads require an active authorized account. The uploading resident and staff/admin can retrieve them.
- Added staff complaint detail viewing and editable remarks for both staff and admin. Residents see saved remarks in My Complaints.
- Included document fees, payment records, notification read-state and new attachments in database backups. Backup writes now check for failure. Direct Apache access to the backup directory is denied; admin-download.php remains the authorized download route.

## Still requires implementation or follow-up

- Registration requirements now persist per resident, including status, remarks, protected downloads, replacement and removal. Staff/Admin select a resident under Resident Account Registration > Registration requirements.
- System Settings now stores a shared logo with a 2 MB JPG/PNG limit, local preview and restore-default option. Login, sidebars and ID previews use the same logo endpoint. The logo is included in database backups.
- Password uppercase/number/special-character rules are enforced for creation, reset and self-service changes. The 8-72-byte length requirement is mandatory.
- Idle session timeout is enforced server-side. Background polling does not prolong sessions; actual user interaction refreshes activity at most once per minute. Inactive accounts lose access on their next authenticated request.
- SMS two-factor sign-in and mobile verification are implemented but default OFF. The administrator must enable them in System Settings after configuring httpSMS and a valid administrator mobile number. First-time mobile verification applies on the next sign-in to an unverified number, including existing accounts; number changes require verification again. 2FA requests a code at every sign-in. Existing signed-in sessions are not forced through verification when the option changes.
- Verification codes expire after 5 minutes, allow 5 attempts per 15-minute window, and are limited to 3 sends per account per 15 minutes with a 60-second resend delay. Challenges are session-bound and single-use. Only hashes are stored; codes are not written to staff SMS history or backups.
- The mock OTP form has been removed. Forgot password now directs users to the existing administrator-assisted reset process; self-service OTP recovery is not implemented.
- Unsupported SMS providers were removed from settings; only httpSMS is offered.
- Existing DOCX templates, generated documents, profile/announcement/feedback image files are not embedded in JSON database backups. Copy their storage directories separately when moving devices. Newly added evidence/supporting attachments are included in the database backup.
- A full end-to-end browser/device audit of every button remains outstanding. Real SMS delivery, phone camera access, Word conversion and physical printing were not exercised.

## Verification

## Workflow synchronization follow-up

- Manual pickup SMS and reminders use the saved automatic pickup template, including resident names and total fees.
- Archived document requests are excluded from SMS selection, submission and retry.
- Older Admin/Staff SMS handlers now open the shared compose/review workflow rather than send through the old unprotected path and label provider acceptance as delivered.
- The separate resident Change Password page uses the shared password validator, CSRF verification, active-account check, transactional audit logging and session ID rotation.
- Removed the login mock OTP form and its unverified dashboard redirect.

## Test results

### Mobile audit follow-up

- Custom select menus now attach inside their native dialog so modal inertness does not block selection; a failed/unavailable Popover API does not stop the menu from opening. Required-field validation focuses the replacement control instead of a hidden native select.
- Profile cropping recalculates when the crop area changes size. New image selections clear stale crops, ignore out-of-order image loads and report unsupported images. Saving while a photo is loading is blocked.
- QR camera startup ignores repeated taps, cancels late permission results after hiding/navigating away, and releases camera tracks on pagehide or backgrounding.
- Mobile-focused mocked regression tests and existing feedback, overview and notification tests passed. Actual Android/iOS camera hardware, browser permission prompts and device orientation were not tested on a physical phone.
- No demo-success or simulated-result code was found in the scanned JS/include files. This scan does not establish that every mobile workflow is tested end to end.

- PHP syntax checks passed for changed PHP files; JavaScript syntax check passed.
- Existing PHP and JavaScript test suites passed, along with new functional-audit regression checks.
- Database tests use temporary records rolled back at completion. No real SMS messages were sent, documents issued, or production passwords changed.
- No live backup restore was performed.
- Pending-function tests cover checklist persistence, attachment replacement/removal, logo storage/reset, password policy, idle expiry, OTP success/expiry/replay/session binding, changed mobile numbers and attempt/send limits. SMS transport was mocked; real verification delivery still requires testing with the configured sender phone.
