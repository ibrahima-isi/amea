# Staff member and private-document rehearsal

Ticket 06, verified locally 2026-10-24 using `AMEA_PUBLIC_PORT=18444 AMEA_STAFF_PORT=19444 AMEA_REHEARSAL_PROJECT=amea-ui-test-06-green bash tests/apps/run.sh`.

The runner now executes `members.py` after creating synthetic member/admin/ordinary-user fixtures. It exercises dashboard, member search/detail/edit, KYC review and approval, archive/restore, protected identity/CV downloads, and public token correction across shared MySQL/uploads. Ordinary users cannot edit, review KYC, view trash or download private files. Anonymous staff is redirected to login; public downloads are unavailable; archived documents are denied until restoration. Existing private identity/CV files survive public correction and staff edits without replacements.

The HTTP red tracer reproduced correction clearing both document paths; the preservation fix makes it pass. Staff edit uses an authenticated download link. Clarification email URLs use `PUBLIC_APP_URL`, falling back to `APP_URL` for the legacy application; independent deployments must set the public origin explicitly. Email remains suppressed in this rehearsal.

Full suite: 111 HTTP/schema assertions, 10 actual HTTPS Chrome checks, legacy HTTP 35 + token 28, PHPUnit 141 tests/241 assertions (10 existing deprecations), standalone 47/186/78/24. Browser screenshot [member detail](screenshots/staff-member-detail.png) shows the shared corrected/edited member with private download actions. No staff redesign is included. Synthetic infrastructure was removed by the runner; no production, real delivery, DNS, or external storage qualification. Full route compatibility and deployment rollback remain tickets 08/09.
