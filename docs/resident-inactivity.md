# Resident account inactivity

Active resident accounts become Inactive after six calendar months without a recorded sign-in or user interaction. Accounts that have never been used count from registration. Admin and Staff accounts and manually inactive or pending accounts are excluded.

The latest registration, successful sign-in, resident activity, or reactivation timestamp starts the six-month period. Reactivation does not change the last-used date. Background refresh requests, QR scans by staff, and office edits do not count as resident activity.

The rule runs automatically before sign-in and authenticated portal access. Inactive residents cannot sign in or continue existing sessions. Admin and Staff see the inactivity reason and last-used timestamp in QR and Resident Management. Review residency requires confirmation, a review note, and a CSRF token. Reactivation and automatic deactivation are recorded in Audit Logs. Inactivity never marks the person as having moved away or deletes their records.

Existing accounts use their recorded last login, or registration if no activity was recorded. They may become inactive immediately when the rule is first applied. New database columns are added automatically on first use; schema.sql includes them for new installations.

For a hosting installation that needs database status updated even when nobody visits the portal, run `php scripts/resident-inactivity.php` hourly using its server scheduler, with the same database environment variables as the portal. The command is CLI-only and repeatable. Portal access also enforces the rule without a scheduler.
